<?php
declare(strict_types=1);

/**
 * Hero immersif — aligné sur Module PowerPoint.dc.html
 *
 * @var array<string, mixed>      $lesson
 * @var array<string, mixed>|null $currentModule
 * @var string                    $lessonSummary
 * @var string                    $diffLabel
 * @var string                    $lessonTypeLabel
 */

$lesson = $lesson ?? [];
$currentModule = $currentModule ?? null;
$lessonSummary = trim((string) ($lessonSummary ?? ($lesson['summary'] ?? '')));
$diffLabel = trim((string) ($diffLabel ?? ''));
$lessonTypeLabel = trim((string) ($lessonTypeLabel ?? ''));

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$title = trim((string) ($lesson['title'] ?? ''));
$moduleTitle = trim((string) ($currentModule['title'] ?? ''));
$moduleIndex = 0;
$courseMods = is_array($course ?? null) ? ($course['modules'] ?? []) : [];
if (is_array($courseMods) && $currentModule) {
    $mid = (int) ($currentModule['id'] ?? 0);
    foreach ($courseMods as $mi => $mod) {
        if (is_array($mod) && (int) ($mod['id'] ?? 0) === $mid) {
            $moduleIndex = (int) $mi + 1;
            break;
        }
    }
}

$eyebrow = '';
if ($moduleTitle !== '') {
    $eyebrow = $moduleIndex > 0
        ? 'Module ' . $moduleIndex . ' · ' . $moduleTitle
        : $moduleTitle;
}

$duration = (int) ($lesson['duration_minutes'] ?? 0);
$objective = '';
if (function_exists('training_lms_learning_objectives')) {
    $objectives = training_lms_learning_objectives($lesson);
    if (!is_array($objectives) || $objectives === []) {
        $objectives = $currentModule ? training_lms_learning_objectives($currentModule) : [];
    }
    if (is_array($objectives) && $objectives !== []) {
        $objective = trim((string) reset($objectives));
    }
}

$metas = [];
if ($duration > 0) {
    $metas[] = ['label' => 'Durée indicative', 'value' => '~' . $duration . ' min'];
}
if ($diffLabel !== '') {
    $metas[] = ['label' => 'Niveau', 'value' => $diffLabel];
} elseif ($lessonTypeLabel !== '') {
    $metas[] = ['label' => 'Format', 'value' => $lessonTypeLabel];
}
if ($objective !== '') {
    $metas[] = ['label' => 'Objectif', 'value' => $objective, 'wide' => true];
} elseif ($lessonTypeLabel !== '' && $diffLabel !== '') {
    $metas[] = ['label' => 'Format', 'value' => $lessonTypeLabel, 'wide' => true];
}
?>
<section class="lms-deck-hero" aria-labelledby="lms-deck-hero-title">
    <div class="lms-deck-hero__main">
        <?php if ($eyebrow !== ''): ?>
            <p class="lms-deck-hero__eyebrow"><?= $h($eyebrow) ?></p>
        <?php endif; ?>
        <h1 class="lms-deck-hero__title" id="lms-deck-hero-title"><?= $h($title !== '' ? $title : 'Leçon') ?></h1>
        <span class="lms-deck-hero__rule" aria-hidden="true"></span>
        <?php if ($lessonSummary !== ''): ?>
            <p class="lms-deck-hero__lead"><?= $h($lessonSummary) ?></p>
        <?php else: ?>
            <p class="lms-deck-hero__lead">Parcourez le contenu, puis validez la leçon pour poursuivre le parcours.</p>
        <?php endif; ?>
    </div>

    <?php if ($metas !== []): ?>
        <dl class="lms-deck-hero__metas">
            <?php foreach ($metas as $meta): ?>
                <div class="lms-deck-hero__meta<?= !empty($meta['wide']) ? ' lms-deck-hero__meta--wide' : '' ?>">
                    <dt><?= $h($meta['label']) ?></dt>
                    <dd><?= $h($meta['value']) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</section>
