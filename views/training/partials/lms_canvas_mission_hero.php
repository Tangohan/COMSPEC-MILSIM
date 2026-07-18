<?php
declare(strict_types=1);
/** @var array<string, mixed> $canvasDeck */
/** @var array<string, mixed> $lesson */
/** @var array<string, mixed>|null $currentModule */
/** @var string $lessonSummary */
/** @var string $diffLabel */
/** @var bool $autoLessonComplete */
$opening = isset($canvasDeck['opening']) && is_array($canvasDeck['opening']) ? $canvasDeck['opening'] : [];
$eyebrow = trim((string) ($opening['eyebrow'] ?? ''));
if ($eyebrow === '' && $currentModule) {
    $eyebrow = trim((string) ($currentModule['title'] ?? ''));
    if ($eyebrow !== '') {
        $eyebrow = 'Module · ' . $eyebrow;
    }
}
if ($eyebrow === '') {
    $eyebrow = 'Parcours visuel';
}
$heroTitle = trim((string) ($opening['title'] ?? ''));
if ($heroTitle === '') {
    $heroTitle = trim((string) ($lesson['title'] ?? ''));
}
$heroLead = trim((string) ($opening['lead'] ?? ''));
if ($heroLead === '') {
    $heroLead = $lessonSummary !== '' ? $lessonSummary : 'Parcourez les étapes interactives ci-dessous pour valider cette leçon.';
}
$durMin = (int) ($lesson['duration_minutes'] ?? 0);
$durLabel = $durMin > 0 ? (string) $durMin . ' min' : '—';
$levelLabel = $diffLabel !== '' ? $diffLabel : '—';
$stats = $opening['stats'] ?? null;
if (!is_array($stats) || $stats === []) {
    $stats = [
        ['label' => 'Durée', 'value' => $durLabel],
        ['label' => 'Niveau', 'value' => $levelLabel],
        ['label' => 'Format', 'value' => 'Parcours visuel'],
        ['label' => 'Validation', 'value' => $autoLessonComplete ? 'Bouton Terminer' : 'Manuelle', 'emphasis' => $autoLessonComplete],
    ];
}
$metaRows = [];
foreach ($stats as $row) {
    if (!is_array($row)) {
        continue;
    }
    $slab = trim((string) ($row['label'] ?? ''));
    $sval = trim((string) ($row['value'] ?? ''));
    if ($slab === '' && $sval === '') {
        continue;
    }
    $metaRows[] = [
        'label' => $slab !== '' ? $slab : '—',
        'value' => $sval !== '' ? $sval : '—',
        'emphasis' => !empty($row['emphasis']),
    ];
}
?>
<div class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 p-8 text-white md:p-10">
    <div class="pointer-events-none absolute inset-0 opacity-[0.06] lms-canvas-hero-grid"></div>
    <div class="relative z-10 grid items-end gap-8 lg:grid-cols-[1.35fr_0.65fr]">
        <div>
            <p class="mb-3 text-xs font-semibold tracking-wide text-emerald-200/90"><?= htmlspecialchars($eyebrow) ?></p>
            <h1 class="text-3xl font-semibold leading-tight tracking-tight md:text-5xl"><?= htmlspecialchars($heroTitle) ?></h1>
            <div class="my-6 h-px w-24 bg-white/20"></div>
            <p class="max-w-2xl text-sm leading-relaxed text-white/75 md:text-base"><?= htmlspecialchars($heroLead) ?></p>
        </div>
        <?php if ($metaRows !== []): ?>
        <dl class="lms-canvas-hero-meta">
            <?php foreach ($metaRows as $meta): ?>
            <div class="lms-canvas-hero-meta__row<?= !empty($meta['emphasis']) ? ' lms-canvas-hero-meta__row--emphasis' : '' ?>">
                <dt><?= htmlspecialchars((string) $meta['label']) ?></dt>
                <dd><?= htmlspecialchars((string) $meta['value']) ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>
    </div>
</div>
