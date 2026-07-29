<?php
declare(strict_types=1);

/**
 * Panneau « Repères » — colonne droite de la maquette PowerPoint.
 *
 * @var array<string, mixed>      $lesson
 * @var array<string, mixed>|null $currentModule
 * @var array<string, mixed>|null $course
 * @var bool                      $lessonAlreadyCompleted
 * @var int                       $progressPctDisplay
 * @var string                    $nextStepHumanLabel
 * @var string                    $lessonSummary
 */

$lesson = $lesson ?? [];
$currentModule = $currentModule ?? null;
$course = is_array($course ?? null) ? $course : [];
$lessonAlreadyCompleted = !empty($lessonAlreadyCompleted);
$progressPctDisplay = (int) ($progressPctDisplay ?? 0);
$nextStepHumanLabel = trim((string) ($nextStepHumanLabel ?? ''));
$lessonSummary = trim((string) ($lessonSummary ?? ($lesson['summary'] ?? '')));

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$objectives = [];
if (function_exists('training_lms_learning_objectives')) {
    $raw = training_lms_learning_objectives($currentModule ?: $lesson);
    if (is_array($raw)) {
        foreach ($raw as $o) {
            $t = trim((string) $o);
            if ($t !== '') {
                $objectives[] = $t;
            }
        }
    }
}
$objectives = array_slice($objectives, 0, 5);

if ($lessonSummary === '' && !empty($course['short_description'])) {
    $lessonSummary = trim((string) $course['short_description']);
}

$duration = (int) ($lesson['duration_minutes'] ?? 0);
$moduleMinutes = (int) ($currentModule['estimated_minutes'] ?? 0);

$statusLabel = $lessonAlreadyCompleted ? 'Validée' : 'En cours';
$statusClass = $lessonAlreadyCompleted ? 'lms-deck-aside__card-value--ok' : 'lms-deck-aside__card-value--warn';
?>
<aside class="lms-deck-aside" aria-label="Repères de la leçon">
    <p class="lms-deck-aside__kicker">Repères</p>

    <?php if ($objectives !== []): ?>
        <p class="lms-deck-aside__heading">Objectifs du module</p>
        <ul class="lms-deck-aside__list">
            <?php foreach ($objectives as $obj): ?>
                <li><?= $h($obj) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($lessonSummary !== ''): ?>
        <p class="lms-deck-aside__heading">Résumé</p>
        <p class="lms-deck-aside__summary"><?= $h($lessonSummary) ?></p>
    <?php endif; ?>

    <?php if ($duration > 0 || $moduleMinutes > 0): ?>
        <div class="lms-deck-aside__card">
            <p class="lms-deck-aside__card-label">Durée indicative</p>
            <?php if ($duration > 0): ?>
                <p class="lms-deck-aside__card-value"><?= (int) $duration ?> min</p>
            <?php endif; ?>
            <?php if ($moduleMinutes > 0): ?>
                <p class="lms-deck-aside__card-hint">Module (estimation) : <?= (int) $moduleMinutes ?> min</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="lms-deck-aside__card">
        <p class="lms-deck-aside__card-label">État</p>
        <p class="lms-deck-aside__card-value <?= $statusClass ?>"><?= $h($statusLabel) ?></p>
    </div>

    <div class="lms-deck-aside__card">
        <p class="lms-deck-aside__card-label">Avancement du parcours</p>
        <p class="lms-deck-aside__card-value lms-deck-aside__card-value--lg"><?= (int) $progressPctDisplay ?> %</p>
        <?php if ($nextStepHumanLabel !== '' && !$lessonAlreadyCompleted): ?>
            <p class="lms-deck-aside__card-hint">Ensuite : <?= $h($nextStepHumanLabel) ?></p>
        <?php elseif ($lessonAlreadyCompleted && $nextStepHumanLabel !== ''): ?>
            <p class="lms-deck-aside__card-hint">Suite : <?= $h($nextStepHumanLabel) ?></p>
        <?php endif; ?>
    </div>
</aside>
