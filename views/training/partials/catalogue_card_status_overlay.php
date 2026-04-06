<?php
declare(strict_types=1);
/** @var string|null $cardState nouvelle|inscrit|valide|en_cours|non_termine */
/** @var int|null $cardProgressPercent affiché si en_cours */
$cardProgressPercent = $cardProgressPercent ?? null;
if (empty($cardState)) {
    return;
}

$pct = max(0, min(100, (int) ($cardProgressPercent ?? 0)));
?>
<div class="mb-3 flex flex-wrap items-center gap-2" role="status">
    <?php if ($cardState === 'nouvelle'): ?>
    <span class="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-violet-900">Nouveau</span>
    <?php elseif ($cardState === 'inscrit'): ?>
    <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-sky-900">Inscrit</span>
    <?php elseif ($cardState === 'valide'): ?>
    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-emerald-900">Validé</span>
    <?php elseif ($cardState === 'en_cours'): ?>
    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-amber-950">
        En cours
        <span class="tabular-nums opacity-90"><?= (int) $pct ?> %</span>
    </span>
    <?php elseif ($cardState === 'non_termine'): ?>
    <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.14em] text-rose-900">À reprendre</span>
    <?php endif; ?>
</div>
