<?php
declare(strict_types=1);
/** @var array<string, mixed> $sl */
$metric = $sl['metric'] ?? null;
if (is_array($metric) && (trim((string) ($metric['label'] ?? '')) !== '' || trim((string) ($metric['value'] ?? '')) !== '')):
    $ml = trim((string) ($metric['label'] ?? ''));
    ?>
<div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
    <p class="text-[9px] font-black uppercase tracking-[0.24em] text-slate-400"><?= htmlspecialchars($ml !== '' ? $ml : 'Indicateur') ?></p>
    <p class="mt-1 text-lg font-black text-slate-900"><?= htmlspecialchars((string) ($metric['value'] ?? '—')) ?></p>
</div>
<?php
endif;

$cards = $sl['cards'] ?? null;
if (is_array($cards) && $cards !== []):
    ?>
<div class="mt-8 grid gap-4 md:grid-cols-3">
    <?php foreach ($cards as $c):
        if (!is_array($c)) {
            continue;
        }
        $clab = trim((string) ($c['label'] ?? ''));
        $cbody = trim((string) ($c['body'] ?? ''));
        if ($clab === '' && $cbody === '') {
            continue;
        }
        ?>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <?php if ($clab !== ''): ?>
        <p class="mb-2 text-[9px] font-black uppercase tracking-[0.24em] text-slate-400"><?= htmlspecialchars($clab) ?></p>
        <?php endif; ?>
        <?php if ($cbody !== ''): ?>
        <p class="text-sm leading-relaxed text-slate-700"><?= nl2br(htmlspecialchars($cbody)) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php
endif;

$insights = $sl['insights'] ?? null;
if (!is_array($insights) || $insights === []) {
    $hi = $sl['highlight'] ?? null;
    if (is_array($hi) && ($hi !== [])) {
        $insights = [$hi];
    } else {
        $insights = [];
    }
}
if ($insights !== []):
    ?>
<div class="mt-8 grid gap-4 md:grid-cols-3">
    <?php foreach ($insights as $ins):
        if (!is_array($ins)) {
            continue;
        }
        $v = strtolower(trim((string) ($ins['variant'] ?? 'retain')));
        $map = match ($v) {
            'key', 'point' => ['border-sky-200', 'bg-sky-50', 'text-sky-700', 'text-sky-950', 'Point clé'],
            'vigilance', 'warn' => ['border-amber-200', 'bg-amber-50', 'text-amber-700', 'text-amber-950', 'Vigilance'],
            'result' => ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'text-emerald-950', 'Résultat attendu'],
            default => ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'text-emerald-900', 'À retenir'],
        };
        [$bc, $bg, $tc, $txc, $defTitle] = $map;
        $ititle = trim((string) ($ins['title'] ?? ''));
        if ($ititle === '') {
            $ititle = $defTitle;
        }
        $ibody = trim((string) ($ins['body'] ?? ''));
        if ($ititle === '' && $ibody === '') {
            continue;
        }
        ?>
    <div class="rounded-2xl border p-4 <?= $bc ?> <?= $bg ?>">
        <p class="mb-2 text-[9px] font-black uppercase tracking-[0.24em] <?= $tc ?>"><?= htmlspecialchars($ititle) ?></p>
        <?php if ($ibody !== ''): ?>
        <p class="text-sm leading-relaxed <?= $txc ?>"><?= nl2br(htmlspecialchars($ibody)) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
