<?php
/** @var list<array{id: string, label: string, value: string|null, hint: string|null, error: string|null}> $kpis */
/** @var string|null $blockError */
$kpis = $kpis ?? [];
$blockError = $blockError ?? null;
?>
<?php if ($blockError): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 mb-6">
        <?= htmlspecialchars($blockError, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php elseif (!empty($kpis)): ?>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <?php foreach ($kpis as $k): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1"><?= htmlspecialchars($k['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($k['error'])): ?>
                    <p class="text-sm text-rose-600"><?= htmlspecialchars($k['error'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <p class="text-2xl font-black text-slate-900 tabular-nums"><?= htmlspecialchars((string) ($k['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (!empty($k['hint'])): ?>
                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($k['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
