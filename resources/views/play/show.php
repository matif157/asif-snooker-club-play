<?php
/** @var array $table, $session, $history, $methods, $customers, $rateTypes */
/** @var int $elapsed, $page, $pages, $total */
/** @var float $amount, $paid, $remaining */
/** @var array|null $camera */
/** @var bool $canViewCctv */
/** @var string $serverUrl */
$canManage = user_can('sessions.manage');
$live      = !empty($session) && ($session['status'] ?? '') === 'active';

$dt = fn($v) => !empty($v) ? date('Y-m-d\TH:i', strtotime((string) $v)) : '';
$money = fn($v) => number_format((float) $v);

$playersList = $live ? array_values(array_filter([
    trim((string) ($session['player_winner'] ?? '')),
    trim((string) ($session['player_loser'] ?? '')),
])) : [];
$clientLabel = $live ? trim((string) ($session['client_name'] ?? '')) : '';
if ($clientLabel === '' && $live) { $clientLabel = trim((string) ($session['customer_name'] ?? '')); }

$isFixed = $live && ($session['charge_type'] ?? 'timer') === 'fixed';
$payStatus = $live ? (string) ($session['payment_status'] ?? 'unpaid') : '';

$config = [
    'sessionId' => $live ? (int) $session['id'] : null,
    'tableId'   => (int) $table['id'],
    'remaining' => (float) $remaining,
    'amount'    => (float) $amount,
];

// Keyboard shortcut hint for this table's position.
$activeTables = \App\Models\Table::activeTables();
$index = 0;
foreach ($activeTables as $i => $t) {
    if ((int) $t['id'] === (int) $table['id']) { $index = $i; break; }
}
$shortcut = \App\Controllers\PlayController::shortcutLabel($index);
?>

<div class="space-y-6 fade-in" x-data="playTable(<?= htmlspecialchars((string) json_encode($config), ENT_QUOTES) ?>)" data-play-card>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="/play" title="Back to Play"
               class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center text-slate-400 hover:text-white transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">Table #<?= e($table['number']) ?></h1>
                    <span class="badge badge-<?= $live ? 'emerald' : ($table['status'] === 'maintenance' ? 'rose' : 'slate') ?>">
                        <?= e(ucfirst($table['status'] === 'available' ? 'free' : (string) $table['status'])) ?>
                    </span>
                    <?php if ($shortcut !== ''): ?>
                        <kbd class="text-[10px] font-mono text-slate-400 bg-white/5 border border-white/10 rounded-md px-1.5 py-1"><?= e($shortcut) ?></kbd>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-400 mt-0.5"><?= e($table['name']) ?> &middot; Rs <?= $money($table['hourly_rate']) ?>/hr &middot; min Rs <?= $money($table['min_charge']) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="/sessions?table_id=<?= (int) $table['id'] ?>" class="btn-secondary text-xs !py-2">All Sessions</a>
            <a href="/tables" class="btn-secondary text-xs !py-2">Command Center</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- ── Main column ─────────────────────────────────────────── -->
        <div class="lg:col-span-2 space-y-5">

            <?php if ($live): ?>
            <!-- Current session -->
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-4">
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-500 font-medium">Running for</p>
                            <p class="text-2xl font-bold text-emerald-400 font-mono play-timer"
                               data-start="<?= date('U', strtotime($session['start_time'])) ?>"
                               data-paused="<?= (int) ($session['paused_total_sec'] ?? 0) ?>"
                               data-status="<?= e($session['status']) ?>"><?= format_duration((int) $elapsed) ?></p>
                        </div>
                        <div class="w-px h-10 bg-white/10"></div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-500 font-medium">Amount due</p>
                            <p class="text-2xl font-bold text-white play-amount"
                               <?php if ($isFixed): ?>data-fixed="<?= (float) ($session['fixed_amount'] ?? 0) ?>"
                               <?php else: ?>data-rate="<?= (float) ($session['rate'] ?? $table['hourly_rate']) ?>" data-min="<?= (float) ($table['min_charge'] ?? 100) ?>"<?php endif; ?>>
                                Rs <?= $money($amount) ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-<?= match ($payStatus) { 'paid' => 'emerald', 'partial' => 'amber', default => 'rose' } ?>">
                            <?= $payStatus === 'paid' ? 'Paid' : ($payStatus === 'partial' ? 'Partial' : 'Udhaar') ?>
                        </span>
                        <?php if ($canManage): ?>
                            <button type="button" class="btn-secondary !py-2 !px-3 text-xs"
                                    @click="showCharge = !showCharge">
                                + Charge
                            </button>
                            <button type="button" class="btn-secondary !py-2 !px-3 text-xs"
                                    @click="showDiscount = !showDiscount">
                                Discount
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Money strip -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="rounded-xl bg-white/[0.03] border border-white/10 p-3">
                        <p class="text-[11px] text-slate-500">Billed</p>
                        <p class="text-sm font-semibold text-white">Rs <?= $money($amount) ?></p>
                    </div>
                    <div class="rounded-xl bg-white/[0.03] border border-white/10 p-3">
                        <p class="text-[11px] text-slate-500">Paid</p>
                        <p class="text-sm font-semibold text-emerald-400">Rs <?= $money($paid) ?></p>
                    </div>
                    <div class="rounded-xl bg-white/[0.03] border border-white/10 p-3">
                        <p class="text-[11px] text-slate-500">Remaining</p>
                        <p class="text-sm font-semibold <?= $remaining > 0 ? 'text-rose-400' : 'text-emerald-400' ?>">Rs <?= $money($remaining) ?></p>
                    </div>
                </div>

                <?php if ($live && !empty($playersList)): ?>
                    <p class="text-sm text-slate-300 mb-1"><?= e(implode(' vs ', $playersList)) ?></p>
                <?php endif; ?>

                <!-- Editable sheet -->
                <div class="flex items-center justify-between mt-4 mb-2">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wider">Session Details</h2>
                    <?php if ($canManage): ?>
                        <div class="flex items-center gap-2">
                            <template x-if="!editing">
                                <button type="button" class="btn-secondary !py-1.5 !px-3 text-xs" @click="editing = true">Edit</button>
                            </template>
                            <template x-if="editing">
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-secondary !py-1.5 !px-3 text-xs" @click="location.reload()">Cancel</button>
                                    <button type="button" class="btn-primary !py-1.5 !px-3 text-xs"
                                            :disabled="saving" :class="{ 'opacity-50': saving }"
                                            @click="saveEdit()" x-text="saving ? 'Saving…' : 'Save'"></button>
                                </div>
                            </template>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="sheet" data-sheet>
                    <tbody>
                        <tr>
                            <th>Winner</th>
                            <td><input class="cell-input" data-field="player_winner" type="text" value="<?= e($session['player_winner'] ?? '') ?>" placeholder="Winning player" :disabled="!editing"></td>
                        </tr>
                        <tr>
                            <th>Loser</th>
                            <td><input class="cell-input" data-field="player_loser" type="text" value="<?= e($session['player_loser'] ?? '') ?>" placeholder="Losing player" :disabled="!editing"></td>
                        </tr>
                        <tr>
                            <th>Client Name</th>
                            <td><input class="cell-input" data-field="client_name" type="text" value="<?= e($clientLabel) ?>" placeholder="Who is paying?" :disabled="!editing"></td>
                        </tr>
                        <tr>
                            <th>Client Phone</th>
                            <td>
                                <input class="cell-input" data-field="client_phone" type="tel" value="<?= e($session['customer_phone'] ?? '') ?>" placeholder="03xx-xxxxxxx" :disabled="!editing">
                                <span class="cell-hint">Udhaar trace karne ke liye phone zaroori hai</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Players</th>
                            <td>
                                <select class="cell-input" data-field="players_count" :disabled="!editing">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int) ($session['players_count'] ?? 1) === $i ? 'selected' : '' ?>><?= $i ?> player<?= $i > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Charge Mode</th>
                            <td>
                                <select class="cell-input" data-field="charge_type" x-model="chargeType" :disabled="!editing">
                                    <option value="timer" <?= !$isFixed ? 'selected' : '' ?>>Live Timer</option>
                                    <option value="fixed" <?= $isFixed ? 'selected' : '' ?>>Fixed Amount</option>
                                </select>
                            </td>
                        </tr>
                        <tr x-show="chargeType === 'fixed'">
                            <th>Fixed Amount</th>
                            <td><input class="cell-input" data-field="fixed_amount" type="number" min="0" step="1" value="<?= (float) ($session['fixed_amount'] ?? 0) ?>" :disabled="!editing"></td>
                        </tr>
                        <tr x-show="chargeType !== 'fixed'">
                            <th>Rate Type</th>
                            <td>
                                <select class="cell-input" data-field="rate_type" :disabled="!editing">
                                    <?php foreach ($rateTypes as $k => $lbl): ?>
                                        <option value="<?= e($k) ?>" <?= ($session['rate_type'] ?? 'hourly') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Expected End</th>
                            <td><input class="cell-input" data-field="expected_end_time" type="datetime-local" value="<?= $dt($session['expected_end_time'] ?? '') ?>" :disabled="!editing"></td>
                        </tr>
                        <tr>
                            <th>Payment Method</th>
                            <td>
                                <select class="cell-input" data-field="payment_method" :disabled="!editing">
                                    <?php foreach ($methods as $k => $lbl): ?>
                                        <option value="<?= e($k) ?>" <?= ($session['payment_method'] ?? 'cash') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Notes</th>
                            <td><textarea class="cell-input" data-field="notes" rows="2" placeholder="Any notes…" :disabled="!editing"><?= e($session['notes'] ?? '') ?></textarea></td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($canManage): ?>
                <!-- Charge / discount inline rows -->
                <div x-show="showCharge" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="number" min="0" step="1" x-model.number="chargeAmount" placeholder="Extra amount (Rs)"
                           class="input sm:col-span-1">
                    <input type="text" x-model="chargeLabel" placeholder="Label (chai, coaching…)" class="input sm:col-span-1">
                    <button type="button" class="btn-primary text-sm" :disabled="busy" @click="addCharge()">Add Charge</button>
                </div>
                <div x-show="showDiscount" x-cloak class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <input type="number" min="0" step="1" x-model.number="discountAmount" placeholder="Discount (Rs)" class="input sm:col-span-1">
                    <button type="button" class="btn-secondary text-sm sm:col-span-1" :disabled="busy" @click="applyDiscount()">Apply Discount</button>
                </div>
                <?php endif; ?>

                <?php if ($canManage): ?>
                <!-- Settle actions -->
                <div class="flex flex-wrap items-center gap-2 mt-5 pt-4 border-t border-white/[0.06]">
                    <button type="button" class="btn-primary text-sm" :disabled="busy" @click="showPay = true">Record Payment</button>
                    <button type="button" class="btn-danger text-sm ml-auto" :disabled="busy" @click="showEnd = true">End Session</button>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Start sheet -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wider">Start a Session</h2>
                    <span class="text-[11px] text-slate-500">Table is free — fill the sheet and submit</span>
                </div>

                <?php if (!$canManage): ?>
                    <p class="text-sm text-amber-400">You do not have permission to start sessions.</p>
                <?php else: ?>
                <table class="sheet">
                    <tbody>
                        <tr>
                            <th>Winner</th>
                            <td><input class="cell-input" x-model="form.player_winner" type="text" placeholder="Winning player"></td>
                        </tr>
                        <tr>
                            <th>Loser</th>
                            <td><input class="cell-input" x-model="form.player_loser" type="text" placeholder="Losing player"></td>
                        </tr>
                        <tr>
                            <th>Client Name</th>
                            <td><input class="cell-input" x-model="form.client_name" type="text" placeholder="Who is paying?"></td>
                        </tr>
                        <tr>
                            <th>Client Phone</th>
                            <td>
                                <input class="cell-input" x-model="form.client_phone" type="tel" placeholder="03xx-xxxxxxx">
                                <span class="cell-hint">Udhaar trace karne ke liye zaroori</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Players</th>
                            <td>
                                <select class="cell-input" x-model="form.players_count">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?> player<?= $i > 1 ? 's' : '' ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Charge Mode</th>
                            <td>
                                <select class="cell-input" x-model="form.charge_type">
                                    <option value="timer">Live Timer</option>
                                    <option value="fixed">Fixed Amount</option>
                                </select>
                            </td>
                        </tr>
                        <tr x-show="form.charge_type === 'fixed'">
                            <th>Fixed Amount</th>
                            <td><input class="cell-input" x-model.number="form.fixed_amount" type="number" min="0" step="1" placeholder="0"></td>
                        </tr>
                        <tr x-show="form.charge_type !== 'fixed'">
                            <th>Rate Type</th>
                            <td>
                                <select class="cell-input" x-model="form.rate_type">
                                    <?php foreach ($rateTypes as $k => $lbl): ?>
                                        <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Expected End</th>
                            <td><input class="cell-input" x-model="form.expected_end_time" type="datetime-local"></td>
                        </tr>
                        <tr>
                            <th>Payment Method</th>
                            <td>
                                <select class="cell-input" x-model="form.payment_method">
                                    <?php foreach ($methods as $k => $lbl): ?>
                                        <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Payment Timing</th>
                            <td>
                                <select class="cell-input" x-model="form.pay_mode">
                                    <option value="later">Pay Later (Udhaar)</option>
                                    <option value="now" :disabled="form.charge_type !== 'fixed'">Pay Now</option>
                                </select>
                                <span class="cell-hint" x-show="form.charge_type !== 'fixed'">Timer sessions are settled when the session ends.</span>
                                <span class="cell-hint text-rose-400/80" x-show="form.pay_mode === 'later' && form.charge_type === 'fixed'">Udhaar requires a client name and phone.</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Notes</th>
                            <td><textarea class="cell-input" x-model="form.notes" rows="2" placeholder="Any notes…"></textarea></td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex items-center justify-end gap-3 mt-4">
                    <span class="text-xs text-slate-500" x-text="form.charge_type === 'fixed'
                        ? 'Fixed: Rs ' + Number(form.fixed_amount || 0).toLocaleString()
                        : 'Rate: Rs <?= $money($table['hourly_rate']) ?>/hr'"></span>
                    <button type="button" class="btn-primary" :disabled="busy" :class="{ 'opacity-50': busy }"
                            @click="startSession()" x-text="busy ? 'Starting…' : 'Submit'"></button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- History -->
            <div class="card p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h2 class="text-sm font-semibold text-white uppercase tracking-wider">Table History</h2>
                        <span class="badge badge-slate"><?= (int) $total ?></span>
                    </div>
                    <?php if ($pages > 1): ?>
                        <span class="text-xs text-slate-500">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <p class="text-slate-400">No sessions on this table yet</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Start → End</th>
                                    <th>Winner / Loser</th>
                                    <th>Client</th>
                                    <th>Charge</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Balance</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $h):
                                    $hLive = ($h['status'] ?? '') === 'active';
                                    $hAmount = (float) ($h['amount'] ?? 0);
                                    if ($hLive) {
                                        $hModel = \App\Models\ClubSession::find((int) $h['id']);
                                        $hAmount = $hModel ? $hModel->computeAmount() : 0.0;
                                    }
                                    $hPaid = (float) ($h['paid_total'] ?? 0);
                                    $hBalance = max(0, $hAmount - $hPaid);
                                    $hDur = 0;
                                    if (!empty($h['start_time'])) {
                                        $hEnd = $hLive ? time() : strtotime((string) ($h['end_time'] ?? $h['start_time']));
                                        $hDur = max(0, $hEnd - strtotime((string) $h['start_time']) - (int) ($h['paused_total_sec'] ?? 0));
                                    }
                                    $hClient = trim((string) ($h['client_name'] ?? ''));
                                    if ($hClient === '') { $hClient = trim((string) ($h['customer_name'] ?? '')); }
                                ?>
                                <tr>
                                    <td class="text-slate-500 font-mono">#<?= (int) $h['id'] ?></td>
                                    <td class="whitespace-nowrap"><?= date('M j, Y', strtotime((string) $h['start_time'])) ?></td>
                                    <td class="whitespace-nowrap text-slate-400">
                                        <?= date('g:i A', strtotime((string) $h['start_time'])) ?>
                                        <?php if (!empty($h['end_time'])): ?>
                                            → <?= date('g:i A', strtotime((string) $h['end_time'])) ?>
                                        <?php elseif ($hLive): ?>
                                            → <span class="text-emerald-400">now</span>
                                        <?php endif; ?>
                                        <span class="text-[11px] text-slate-600 block"><?= format_duration($hDur) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($h['player_winner']) || !empty($h['player_loser'])): ?>
                                            <span class="text-slate-200"><?= e(trim(($h['player_winner'] ?? '') . ' vs ' . ($h['player_loser'] ?? ''), ' vs')) ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-600">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hClient !== ''): ?>
                                            <span class="text-slate-200"><?= e($hClient) ?></span>
                                            <?php if (!empty($h['customer_phone'])): ?>
                                                <span class="text-[11px] text-slate-500 block"><?= e($h['customer_phone']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-600">Walk-in</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($h['charge_type'] ?? 'timer') === 'fixed'): ?>
                                            <span class="badge badge-amber">Fixed</span>
                                            <span class="text-[11px] text-slate-500">Rs <?= $money($h['fixed_amount'] ?? 0) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-sky"><?= e(ucfirst((string) ($h['rate_type'] ?? 'hourly'))) ?></span>
                                            <span class="text-[11px] text-slate-500">Rs <?= $money($h['rate'] ?? 0) ?>/hr</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right font-semibold text-white">Rs <?= $money($hAmount) ?></td>
                                    <td class="text-right text-emerald-400">Rs <?= $money($hPaid) ?></td>
                                    <td class="text-right <?= $hBalance > 0 ? 'text-rose-400' : 'text-slate-500' ?>">Rs <?= $money($hBalance) ?></td>
                                    <td class="text-slate-400"><?= e($methods[($h['payment_method'] ?? 'cash')] ?? ucfirst((string) ($h['payment_method'] ?? '—'))) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $hLive ? 'emerald' : ($h['status'] === 'cancelled' ? 'rose' : 'slate') ?>">
                                            <?= e(ucfirst((string) $h['status'])) ?>
                                        </span>
                                        <?php if ($hBalance > 0 && !$hLive): ?>
                                            <span class="badge badge-amber ml-1">Udhaar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <a href="/sessions/<?= (int) $h['id'] ?>/invoice" target="_blank" rel="noopener"
                                           class="text-xs text-emerald-400 hover:text-emerald-300">Invoice</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($pages > 1): ?>
                        <div class="flex items-center justify-center gap-1.5 mt-4">
                            <a href="/play/<?= (int) $table['id'] ?>?page=<?= max(1, $page - 1) ?>"
                               class="btn-secondary !py-1.5 !px-3 text-xs <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">Prev</a>
                            <?php
                                $start = max(1, $page - 2);
                                $end   = min($pages, $start + 4);
                                $start = max(1, $end - 4);
                                for ($p = $start; $p <= $end; $p++): ?>
                                <a href="/play/<?= (int) $table['id'] ?>?page=<?= $p ?>"
                                   class="min-w-[34px] text-center rounded-lg px-2.5 py-1.5 text-xs font-medium transition <?= $p === $page ? 'bg-emerald-500 text-white' : 'bg-white/5 text-slate-300 hover:bg-white/10' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <a href="/play/<?= (int) $table['id'] ?>?page=<?= min($pages, $page + 1) ?>"
                               class="btn-secondary !py-1.5 !px-3 text-xs <?= $page >= $pages ? 'opacity-40 pointer-events-none' : '' ?>">Next</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Side column ─────────────────────────────────────────── -->
        <div class="space-y-5">
            <!-- Camera -->
            <div class="card p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-white uppercase tracking-wider">Camera</h2>
                    <?php if (!empty($camera['player_url'])): ?>
                        <button type="button" class="text-xs text-emerald-400 hover:text-emerald-300"
                                onclick="openPlayCam(this)" data-player="<?= e($camera['player_url']) ?>">Fullscreen</button>
                    <?php endif; ?>
                </div>
                <?php if (!$canViewCctv): ?>
                    <div class="rounded-xl bg-white/[0.03] border border-white/10 p-6 text-center text-xs text-slate-500">
                        You do not have CCTV access.
                    </div>
                <?php elseif (!empty($camera['hls_url'])): ?>
                    <div class="relative rounded-xl overflow-hidden bg-black ring-1 ring-white/10 aspect-video">
                        <video class="play-cam w-full h-full object-cover" muted autoplay playsinline
                               data-hls-src="<?= e($camera['hls_url']) ?>"></video>
                        <span class="cam-offline hidden absolute inset-0 flex-col items-center justify-center text-slate-500">
                            <span class="text-xs">No signal</span>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl bg-black/50 ring-1 ring-white/10 aspect-video flex flex-col items-center justify-center text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" d="M3 3l18 18"/></svg>
                        <span class="text-[11px] mt-1">No camera linked</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick facts -->
            <div class="card p-4">
                <h2 class="text-sm font-semibold text-white uppercase tracking-wider mb-3">Quick Info</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Status</dt>
                        <dd class="text-white font-medium"><?= e(ucfirst((string) $table['status'])) ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Hourly rate</dt>
                        <dd class="text-white">Rs <?= $money($table['hourly_rate']) ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Min charge</dt>
                        <dd class="text-white">Rs <?= $money($table['min_charge']) ?></dd>
                    </div>
                    <?php if ($live): ?>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Started</dt>
                        <dd class="text-white"><?= date('g:i A', strtotime((string) $session['start_time'])) ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Operator</dt>
                        <dd class="text-white"><?= e($session['staff_name'] ?? '—') ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
                <div class="mt-4 pt-3 border-t border-white/[0.06] text-[11px] text-slate-500">
                    Tip: press <kbd class="px-1 py-0.5 rounded bg-white/10 font-mono text-slate-300"><?= e($shortcut ?: 'Ctrl+1') ?></kbd>
                    on any page to open this table.
                </div>
            </div>
        </div>
    </div>

    <?php if ($live && $canManage): ?>
    <!-- Record payment modal -->
    <div x-show="showPay" x-cloak class="modal-overlay" @click.self="showPay = false" @keydown.escape.window="showPay = false">
        <div class="modal-card max-w-md" @click.stop>
            <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
                <h3 class="text-base font-semibold text-white">Record Payment</h3>
                <button @click="showPay = false" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-slate-400">Remaining: <span class="text-white font-semibold">Rs <?= $money($remaining) ?></span></p>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Amount (Rs)</label>
                    <input type="number" min="0" step="1" x-model.number="payAmount" class="input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Method</label>
                    <select x-model="payMethod" class="input">
                        <?php foreach ($methods as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showPay = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="busy" @click="recordPayment()">Record</button>
                </div>
            </div>
        </div>
    </div>

    <!-- End session modal -->
    <div x-show="showEnd" x-cloak class="modal-overlay" @click.self="showEnd = false" @keydown.escape.window="showEnd = false">
        <div class="modal-card max-w-md" @click.stop>
            <div class="px-6 py-4 border-b border-white/[0.06] flex items-center justify-between">
                <h3 class="text-base font-semibold text-white">End Session</h3>
                <button @click="showEnd = false" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-slate-400">
                    Collect now — leave 0 to send the whole balance to Udhaar.
                </p>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Amount collected (Rs)</label>
                    <input type="number" min="0" step="1" x-model.number="endPayAmount" class="input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-2">Method</label>
                    <select x-model="endMethod" class="input">
                        <?php foreach ($methods as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showEnd = false">Cancel</button>
                    <button type="button" class="btn-danger" :disabled="busy" @click="endSession()">End Session</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function playTable(cfg) {
    return {
        sessionId: cfg.sessionId,
        remaining: cfg.remaining,
        editing: false,
        busy: false,
        saving: false,
        chargeType: <?= $isFixed ? "'fixed'" : "'timer'" ?>,
        showCharge: false,
        showDiscount: false,
        showPay: false,
        showEnd: false,
        chargeAmount: 0,
        chargeLabel: '',
        discountAmount: 0,
        payAmount: cfg.remaining,
        payMethod: 'cash',
        endPayAmount: cfg.remaining,
        endMethod: 'cash',
        form: {
            player_winner: '',
            player_loser: '',
            client_name: '',
            client_phone: '',
            players_count: 1,
            charge_type: 'timer',
            fixed_amount: 0,
            rate_type: 'hourly',
            expected_end_time: '',
            payment_method: 'cash',
            pay_mode: 'later',
            notes: ''
        },

        async saveEdit() {
            if (!this.sessionId) return;
            const payload = {};
            document.querySelectorAll('[data-sheet] [data-field]').forEach(el => {
                payload[el.dataset.field] = el.value;
            });
            this.saving = true;
            try {
                const res = await apiPost('/api/sessions/' + this.sessionId + '/edit', payload);
                if (res.success) { location.reload(); }
                else { alert(res.message || 'Failed to save'); }
            } catch (e) {
                alert('Network error. Please try again.');
            }
            this.saving = false;
        },

        async addCharge() {
            if (!(Number(this.chargeAmount) > 0)) { alert('Enter a charge amount.'); return; }
            this.busy = true;
            try {
                const res = await apiPost('/api/sessions/' + this.sessionId + '/charge', {
                    amount: Number(this.chargeAmount),
                    label: this.chargeLabel
                });
                if (res.success) { location.reload(); } else { alert(res.message || 'Failed'); }
            } catch (e) { alert('Network error.'); }
            this.busy = false;
        },

        async applyDiscount() {
            if (!(Number(this.discountAmount) > 0)) { alert('Enter a discount amount.'); return; }
            this.busy = true;
            try {
                const res = await apiPost('/api/sessions/' + this.sessionId + '/discount', {
                    amount: Number(this.discountAmount)
                });
                if (res.success) { location.reload(); } else { alert(res.message || 'Failed'); }
            } catch (e) { alert('Network error.'); }
            this.busy = false;
        },

        async recordPayment() {
            if (!(Number(this.payAmount) > 0)) { alert('Enter an amount.'); return; }
            this.busy = true;
            try {
                const res = await apiPost('/api/sessions/' + this.sessionId + '/pay', {
                    amount: Number(this.payAmount),
                    method: this.payMethod
                });
                if (res.success) { location.reload(); } else { alert(res.message || 'Failed'); }
            } catch (e) { alert('Network error.'); }
            this.busy = false;
        },

        async endSession() {
            this.busy = true;
            try {
                const res = await apiPost('/api/sessions/' + this.sessionId + '/end', {
                    pay_amount: Number(this.endPayAmount) || 0,
                    method: this.endMethod
                });
                if (res.success) { location.reload(); } else { alert(res.message || 'Failed'); }
            } catch (e) { alert('Network error.'); }
            this.busy = false;
        },

        async startSession() {
            const f = this.form;
            if (f.charge_type === 'fixed' && !(Number(f.fixed_amount) > 0)) {
                alert('Fixed amount must be greater than zero.'); return;
            }
            if (f.pay_mode === 'later' && f.charge_type === 'fixed' && !String(f.client_phone).trim()) {
                alert('Udhaar ke liye client ka phone number zaroori hai.'); return;
            }
            this.busy = true;
            try {
                const res = await apiPost('/api/tables/' + cfg.tableId + '/start', {
                    players_count: parseInt(f.players_count),
                    rate_type: f.rate_type,
                    player_winner: f.player_winner,
                    player_loser: f.player_loser,
                    client_name: f.client_name,
                    customer_name: f.client_name,
                    customer_phone: f.client_phone,
                    charge_type: f.charge_type,
                    fixed_amount: f.charge_type === 'fixed' ? Number(f.fixed_amount) : 0,
                    expected_end_time: f.expected_end_time,
                    payment_method: f.payment_method,
                    pay_mode: f.pay_mode,
                    notes: f.notes
                });
                if (res.success) { location.reload(); } else { alert(res.message || 'Failed to start session'); }
            } catch (e) { alert('Network error.'); }
            this.busy = false;
        }
    };
}
</script>

<?php include ROOT_PATH . '/resources/views/partials/play-scripts.php'; ?>
