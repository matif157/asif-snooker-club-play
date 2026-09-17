<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ClubSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Table as TableModel;
use App\Services\AuditService;
use App\Services\RateService;
use App\Services\SettingsService;

/**
 * "Play" mode — a table-first, spreadsheet-like way to run the floor.
 *
 * /play            board of every table
 * /play/{id}       one table: live session (or start sheet), camera + history
 */
class PlayController extends Controller
{
    private const HISTORY_PER_PAGE = 10;

    public function index(): void
    {
        $tables = TableModel::activeTables();

        $canViewCctv = user_can('cctv.view') || user_can('cctv.manage');
        $streamMode  = (string) SettingsService::get('cctv_stream_mode', 'img');
        $serverUrl   = rtrim((string) SettingsService::get('cctv_server_url', 'http://127.0.0.1:1984'), '/');
        $cameraMap   = $canViewCctv ? $this->cameraByTable() : [];

        foreach ($tables as $i => &$t) {
            $session = ClubSession::activeForTable((int) $t['id']);
            $t['current_session'] = $session;
            $t['elapsed_seconds'] = $this->elapsed($session);
            $t['camera']          = $this->cameraFor($cameraMap, (int) $t['id'], $serverUrl);
            $t['shortcut']        = self::shortcutLabel($i);
            $t['amount']          = $this->amountFor($session);
        }
        unset($t);

        $this->view('play/index', [
            'tables'      => $tables,
            'canViewCctv' => $canViewCctv,
            'streamMode'  => $streamMode,
            'serverUrl'   => $serverUrl,
        ]);
    }

    public function show(int $id): void
    {
        $table = TableModel::find($id);
        if (!$table) {
            Response::error('Table not found', 404);
        }

        $canViewCctv = user_can('cctv.view') || user_can('cctv.manage');
        $serverUrl   = rtrim((string) SettingsService::get('cctv_server_url', 'http://127.0.0.1:1984'), '/');
        $camera      = $canViewCctv ? $this->cameraFor($this->cameraByTable(), $id, $serverUrl) : null;

        $session = null;
        $active  = ClubSession::activeForTable($id);
        if ($active) {
            $session = ClubSession::withDetails((int) $active['id']) ?? $active;
        }
        $elapsed = $this->elapsed($session);
        $amount  = $this->amountFor($session);
        $paid    = $session ? (float) ($session['paid_total'] ?? 0) : 0.0;

        // ── History (paginated) ────────────────────────────────────────
        $total   = (int) (Database::fetchOne(
            'SELECT COUNT(*) AS c FROM sessions WHERE table_id = ?',
            [$id]
        )['c'] ?? 0);
        $pages   = max(1, (int) ceil($total / self::HISTORY_PER_PAGE));
        $page    = min($pages, max(1, (int) Request::get('page', 1)));
        $offset  = ($page - 1) * self::HISTORY_PER_PAGE;

        $history = Database::query(
            "SELECT s.*,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    u.name AS staff_name,
                    (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                     WHERE p.session_id = s.id AND p.status = 'paid') AS paid_total
             FROM sessions s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN users u ON u.id = s.staff_id
             WHERE s.table_id = ?
             ORDER BY s.id DESC
             LIMIT " . self::HISTORY_PER_PAGE . " OFFSET {$offset}",
            [$id]
        );

        $this->view('play/show', [
            'table'       => $table->toArray(),
            'session'     => $session,
            'elapsed'     => $elapsed,
            'amount'      => $amount,
            'paid'        => $paid,
            'remaining'   => max(0, round($amount - $paid, 2)),
            'camera'      => $camera,
            'canViewCctv' => $canViewCctv,
            'serverUrl'   => $serverUrl,
            'history'     => $history,
            'page'        => $page,
            'pages'       => $pages,
            'total'       => $total,
            'methods'     => Payment::METHODS,
            'customers'   => Customer::search(''),
            'rateTypes'   => ClubSession::RATE_TYPES,
        ]);
    }

    /**
     * Patch the editable fields of a live session (the "excel" sheet).
     * Only the fields actually sent are touched.
     */
    public function apiEdit(int $id): void
    {
        if (!user_can('sessions.manage')) {
            Response::error('Forbidden', 403);
        }

        $session = ClubSession::find($id);
        if (!$session || in_array($session->status, ['completed', 'cancelled'], true)) {
            Response::error('Session not found or already closed', 404);
        }

        $patch = [];
        $before = $session->toArray();

        // Player / client labels
        foreach (['player_winner' => 'player_winner', 'player_loser' => 'player_loser', 'client_name' => 'client_name'] as $field => $key) {
            if (Request::input($field) !== null) {
                $val = trim((string) Request::input($field));
                $patch[$key] = $val !== '' ? $val : null;
            }
        }

        if (Request::input('players_count') !== null) {
            $patch['players_count'] = max(1, (int) Request::input('players_count'));
        }

        if (Request::input('notes') !== null) {
            $notes = trim((string) Request::input('notes'));
            $patch['notes'] = $notes !== '' ? $notes : null;
        }

        // Charge mode + amount
        if (Request::input('charge_type') !== null) {
            $chargeType = (string) Request::input('charge_type');
            $patch['charge_type'] = in_array($chargeType, [ClubSession::CHARGE_TIMER, ClubSession::CHARGE_FIXED], true)
                ? $chargeType
                : ClubSession::CHARGE_TIMER;
        }

        if (Request::input('fixed_amount') !== null) {
            $fixed = round((float) Request::input('fixed_amount'), 2);
            $effectiveType = $patch['charge_type'] ?? ($session->charge_type ?: ClubSession::CHARGE_TIMER);
            if ($effectiveType === ClubSession::CHARGE_FIXED && $fixed <= 0) {
                Response::error('Fixed amount must be greater than zero.');
            }
            $patch['fixed_amount'] = $effectiveType === ClubSession::CHARGE_FIXED ? $fixed : null;
        }

        // Rate band (re-resolve the effective hourly rate)
        if (Request::input('rate_type') !== null) {
            $requested = (string) Request::input('rate_type');
            $resolved  = RateService::resolveRate($requested, TableModel::find((int) $session->table_id)?->toArray() ?? []);
            $patch['rate_type'] = $resolved['rate_type'];
            $patch['rate']      = $resolved['rate'];
        }

        if (Request::input('expected_end_time') !== null) {
            $patch['expected_end_time'] = $this->normaliseDateTime((string) Request::input('expected_end_time'));
        }

        if (Request::input('payment_method') !== null) {
            $patch['payment_method'] = Payment::normalizeMethod(Request::input('payment_method'));
        }

        // Client phone → attach (or create) a customer so udhaar stays traceable.
        if (Request::input('client_phone') !== null) {
            $phone = trim((string) Request::input('client_phone'));
            if ($phone !== '') {
                $existing = Customer::findByPhone($phone);
                if ($existing) {
                    $patch['customer_id'] = (int) $existing['id'];
                    if (empty($patch['client_name'])) {
                        $patch['client_name'] = $existing['name'];
                    }
                } else {
                    $name = trim((string) (Request::input('client_name') ?? $session->client_name ?? ''));
                    $newId = Customer::create([
                        'name'       => $name !== '' ? $name : ('Walk-in ' . $phone),
                        'phone'      => Customer::normalizePhone($phone),
                        'category'   => 'regular',
                        'status'     => 'active',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $patch['customer_id'] = $newId;
                }
            }
        }

        if ($patch === []) {
            Response::error('Nothing to update');
        }

        $session->update($patch);

        AuditService::log('session_edited', 'session', $id, $before, $patch);

        Response::success(['session' => $session->toArray()], 'Session updated');
    }

    /**
     * Full details for a single session (used when a table's history row opens).
     */
    public function apiSession(int $id): void
    {
        $session = ClubSession::withDetails($id);
        if (!$session) {
            Response::error('Session not found', 404);
        }

        Response::success(['session' => $session]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function elapsed(?array $session): int
    {
        if (!$session || ($session['status'] ?? '') !== 'active') {
            return 0;
        }
        $elapsed = time() - strtotime((string) $session['start_time']);
        $elapsed -= (int) ($session['paused_total_sec'] ?? 0);
        return max(0, $elapsed);
    }

    private function amountFor(?array $session): float
    {
        if (!$session) {
            return 0.0;
        }
        $model = ClubSession::find((int) $session['id']);
        return $model ? $model->computeAmount() : 0.0;
    }

    /** First enabled camera bound to each table, keyed by table id. */
    private function cameraByTable(): array
    {
        $map = [];
        foreach (TableModel::camerasByTable() as $row) {
            $tableId = (int) $row['table_id'];
            if (!isset($map[$tableId])) {
                $map[$tableId] = $row;
            }
        }
        return $map;
    }

    private function cameraFor(array $map, int $tableId, string $serverUrl): ?array
    {
        $cam = $map[$tableId] ?? null;
        if (!$cam) {
            return null;
        }

        return [
            'name'        => $cam['name'],
            'stream_name' => $cam['stream_name'],
            'hls_url'     => CameraController::hlsUrl($serverUrl, $cam['stream_name']),
            'player_url'  => CameraController::playerUrl($serverUrl, $cam['stream_name']),
        ];
    }

    /** Human label for the keyboard shortcut of the Nth table. */
    public static function shortcutLabel(int $index): string
    {
        if ($index < 9) {
            return 'Ctrl+' . ($index + 1);
        }
        if ($index === 9) {
            return 'Ctrl+0';
        }
        if ($index < 19) {
            return 'Ctrl+Shift+' . ($index - 9);
        }
        return '';
    }

    private function normaliseDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
}
