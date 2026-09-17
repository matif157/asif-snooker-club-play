<?php
/** @var array $tables — each with current_session, elapsed_seconds, camera, shortcut, amount */
/** @var bool $canViewCctv */
/** @var string $streamMode, $serverUrl */
$occupied  = count(array_filter($tables, fn($t) => $t['status'] === 'occupied'));
$available = count(array_filter($tables, fn($t) => $t['status'] === 'available'));
$reserved  = count(array_filter($tables, fn($t) => $t['status'] === 'reserved'));
?>

<div class="space-y-6 fade-in" x-data="{ q: '' }">

    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">Play</h1>
            <p class="text-sm text-slate-400 mt-1">
                Pick a table — or press <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-[11px] font-mono text-slate-200">Ctrl</kbd>
                + <kbd class="px-1.5 py-0.5 rounded bg-white/10 text-[11px] font-mono text-slate-200">1…9</kbd> anywhere to jump.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="q" placeholder="Find table…"
                       class="bg-ink-800 border border-white/10 rounded-xl text-sm text-white placeholder-slate-500 pl-9 pr-4 py-2.5 focus:ring-2 focus:ring-emerald-500/50 focus:outline-none transition w-44">
            </div>
            <a href="/tables" class="btn-secondary text-xs !py-2.5">Command Center</a>
        </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span> Free (<?= $available ?>)</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Playing (<?= $occupied ?>)</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span> Reserved (<?= $reserved ?>)</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span> Maintenance</span>
    </div>

    <!-- Table board -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 fade-in-stagger">
        <?php foreach ($tables as $t): ?>
            <?php
                $status  = $t['status'];
                $session = $t['current_session'] ?? null;
                $live    = $status === 'occupied' && $session;
                $isFixed = $live && ($session['charge_type'] ?? 'timer') === 'fixed';
                $players = $live ? array_values(array_filter([
                    trim((string) ($session['player_winner'] ?? '')),
                    trim((string) ($session['player_loser'] ?? '')),
                ])) : [];
                $client = $live ? trim((string) ($session['client_name'] ?? '')) : '';
                if ($client === '' && $live) { $client = trim((string) ($session['customer_name'] ?? '')); }
                $payStatus = $live ? (string) ($session['payment_status'] ?? 'unpaid') : '';
                $shortcut  = (string) ($t['shortcut'] ?? '');
                $searchKey = strtolower((string) $t['number'] . ' ' . (string) $t['name']);
                $accent = match ($status) {
                    'occupied'    => 'border-emerald-500/40 bg-emerald-500/[0.06]',
                    'reserved'    => 'border-indigo-500/40 bg-indigo-500/[0.06]',
                    'maintenance' => 'border-rose-500/30 bg-rose-500/[0.04]',
                    default       => 'border-white/10 bg-white/[0.02]',
                };
            ?>
            <a href="/play/<?= (int) $t['id'] ?>" data-play-card
               x-show="q === '' || '<?= e($searchKey) ?>'.includes(q.toLowerCase())"
               class="group block rounded-2xl border <?= $accent ?> p-4 transition hover:border-emerald-400/60 hover:bg-emerald-500/[0.08]">

                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold text-white">#<?= e($t['number']) ?></span>
                            <span class="badge badge-<?= match ($status) {
                                'occupied'    => 'emerald',
                                'reserved'    => 'violet',
                                'maintenance' => 'rose',
                                default       => 'slate',
                            } ?> !text-[10px]">
                                <?= e(ucfirst($status === 'available' ? 'free' : $status)) ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 truncate mt-0.5"><?= e($t['name']) ?></p>
                    </div>
                    <?php if ($shortcut !== ''): ?>
                        <kbd class="shrink-0 text-[10px] font-mono text-slate-400 bg-white/5 border border-white/10 rounded-md px-1.5 py-1"><?= e($shortcut) ?></kbd>
                    <?php endif; ?>
                </div>

                <?php if ($canViewCctv): ?>
                    <div class="relative mb-3 rounded-lg overflow-hidden bg-black/50 ring-1 ring-white/10 aspect-video">
                        <?php if (!empty($t['camera']['hls_url'])): ?>
                            <video class="play-cam w-full h-full object-cover" muted autoplay playsinline
                                   data-hls-src="<?= e($t['camera']['hls_url']) ?>"></video>
                            <span class="cam-offline hidden absolute inset-0 flex-col items-center justify-center text-slate-600">
                                <span class="text-[10px]">No signal</span>
                            </span>
                            <button type="button" title="Fullscreen"
                                    data-player="<?= e($t['camera']['player_url'] ?? '') ?>"
                                    onclick="event.preventDefault(); event.stopPropagation(); openPlayCam(this)"
                                    class="absolute bottom-1 right-1 p-1 rounded-md bg-black/60 text-slate-300 hover:text-white opacity-0 group-hover:opacity-100 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                            </button>
                        <?php else: ?>
                            <div class="w-full h-full flex flex-col items-center justify-center text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/><path stroke-linecap="round" d="M3 3l18 18"/></svg>
                                <span class="text-[10px] mt-0.5">No signal</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($live): ?>
                    <div class="flex items-center gap-2 mb-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-400 timer-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-sm font-mono font-semibold text-emerald-400 play-timer"
                              data-start="<?= date('U', strtotime($session['start_time'])) ?>"
                              data-paused="<?= (int) ($session['paused_total_sec'] ?? 0) ?>"
                              data-status="<?= e($session['status']) ?>"><?= format_duration((int) $t['elapsed_seconds']) ?></span>
                    </div>
                    <?php if ($players !== []): ?>
                        <p class="text-[12px] text-white/90 font-medium truncate"><?= e(implode(' vs ', $players)) ?></p>
                    <?php endif; ?>
                    <?php if ($client !== ''): ?>
                        <p class="text-[11px] text-slate-400 truncate"><?= e($client) ?></p>
                    <?php endif; ?>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-white play-amount"
                              <?php if ($isFixed): ?>
                                  data-fixed="<?= (float) ($session['fixed_amount'] ?? 0) ?>"
                              <?php else: ?>
                                  data-rate="<?= (float) ($session['rate'] ?? $t['hourly_rate']) ?>"
                                  data-min="<?= (float) ($t['min_charge'] ?? 100) ?>"
                              <?php endif; ?>>
                            Rs <?= number_format((float) $t['amount']) ?>
                        </span>
                        <span class="badge badge-<?= match ($payStatus) { 'paid' => 'emerald', 'partial' => 'amber', default => 'rose' } ?> !text-[9px]">
                            <?= $payStatus === 'paid' ? 'Paid' : ($payStatus === 'partial' ? 'Partial' : 'Udhaar') ?>
                        </span>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-400">Rs <?= number_format((float) $t['hourly_rate']) ?><span class="text-xs text-slate-600">/hr</span></p>
                    <p class="text-[11px] text-emerald-400/80 mt-1 opacity-0 group-hover:opacity-100 transition">
                        <?= $status === 'available' ? 'Tap to start a session →' : 'Tap to open →' ?>
                    </p>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <?php if (empty($tables)): ?>
            <div class="col-span-full empty-state">
                <p class="text-slate-400">No tables yet</p>
                <p class="text-xs text-slate-600 mt-1">Add tables from the Command Center to use Play mode.</p>
                <a href="/tables/create" class="btn-primary text-xs mt-4">Add Table</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/resources/views/partials/play-scripts.php'; ?>
