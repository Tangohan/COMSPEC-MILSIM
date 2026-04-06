<?php
declare(strict_types=1);
/** @var string|null $cardState nouvelle|inscrit|valide|en_cours|non_termine */
/** @var int|null $cardProgressPercent affiché si en_cours */
$cardProgressPercent = $cardProgressPercent ?? null;
if (empty($cardState)) {
    return;
}

$pct = max(0, min(100, (int) ($cardProgressPercent ?? 0)));

$wrap = 'mb-4 rounded-2xl border overflow-hidden shadow-sm ring-1 ring-black/5';

if ($cardState === 'en_cours') {
    ?>
<div class="<?= $wrap ?> border-amber-200/90 bg-white">
    <div class="h-2 w-full bg-amber-100" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progression du parcours">
        <div class="h-full bg-gradient-to-r from-amber-500 to-orange-500 transition-[width] duration-300 ease-out" style="width: <?= $pct ?>%; min-width: <?= $pct > 0 ? '6px' : '0' ?>"></div>
    </div>
    <div class="grid grid-cols-[auto_1fr_auto] gap-3 items-center px-3.5 py-3 bg-gradient-to-b from-amber-50/90 to-white">
        <svg class="h-6 w-6 shrink-0 text-amber-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M8 5v14l11-7z"/>
        </svg>
        <span class="text-center text-[11px] font-black uppercase tracking-[0.12em] text-amber-950">En cours</span>
        <span class="text-xs font-black tabular-nums text-amber-800 shrink-0"><?= $pct ?> %</span>
    </div>
</div>
    <?php
    return;
}

if ($cardState === 'valide') {
    ?>
<div class="<?= $wrap ?> border-emerald-200/90 bg-white">
    <div class="h-1.5 w-full bg-emerald-500" aria-hidden="true"></div>
    <div class="grid grid-cols-[auto_1fr] gap-3 items-center px-3.5 py-3 bg-gradient-to-b from-emerald-50/80 to-white">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
        </span>
        <span class="text-center sm:text-left text-[11px] font-black uppercase tracking-[0.12em] text-emerald-950">Validé</span>
    </div>
</div>
    <?php
    return;
}

$rest = match ($cardState) {
    'nouvelle' => [
        'label' => 'Nouveau dans le catalogue',
        'border' => 'border-violet-200/90',
        'accent' => 'bg-violet-500',
        'bg' => 'from-violet-50/80 to-white',
        'text' => 'text-violet-950',
        'icon' => 'text-violet-600',
    ],
    'inscrit' => [
        'label' => 'Inscrit · à commencer',
        'border' => 'border-sky-200/90',
        'accent' => 'bg-sky-500',
        'bg' => 'from-sky-50/80 to-white',
        'text' => 'text-sky-950',
        'icon' => 'text-sky-600',
    ],
    'non_termine' => [
        'label' => 'À reprendre',
        'border' => 'border-rose-200/90',
        'accent' => 'bg-rose-500',
        'bg' => 'from-rose-50/80 to-white',
        'text' => 'text-rose-950',
        'icon' => 'text-rose-600',
    ],
    default => null,
};
if ($rest === null) {
    return;
}
?>
<div class="<?= $wrap ?> <?= htmlspecialchars($rest['border'], ENT_QUOTES, 'UTF-8') ?> bg-white">
    <div class="h-1 w-full <?= htmlspecialchars($rest['accent'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
    <div class="flex items-center gap-3 px-3.5 py-3 bg-gradient-to-b <?= htmlspecialchars($rest['bg'], ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($cardState === 'nouvelle'): ?>
        <svg class="h-6 w-6 shrink-0 <?= htmlspecialchars($rest['icon'], ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
        <?php elseif ($cardState === 'inscrit'): ?>
        <svg class="h-6 w-6 shrink-0 <?= htmlspecialchars($rest['icon'], ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
        </svg>
        <?php else: ?>
        <svg class="h-6 w-6 shrink-0 <?= htmlspecialchars($rest['icon'], ENT_QUOTES, 'UTF-8') ?>" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <?php endif; ?>
        <span class="min-w-0 flex-1 text-center text-[11px] font-black uppercase tracking-[0.12em] <?= htmlspecialchars($rest['text'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($rest['label'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</div>
