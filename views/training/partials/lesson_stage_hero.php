<?php
declare(strict_types=1);

/**
 * Hero de leçon du lecteur « scène » — bandeau sombre + carte de progression du module.
 *
 * Affiché uniquement quand training_courses.lesson_player_mode vaut « stage » : les
 * formations en mode classique conservent leur en-tête actuel, inchangé.
 *
 * @var array<string, mixed>      $lesson
 * @var array<string, mixed>|null $currentModule
 * @var array{current:int,total:int}|null $moduleLessonStep
 */

$lesson = $lesson ?? [];
$currentModule = $currentModule ?? null;
$moduleLessonStep = $moduleLessonStep ?? null;

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$title = trim((string) ($lesson['title'] ?? ''));
$lead = trim((string) ($lesson['summary'] ?? ''));
$moduleTitle = trim((string) ($currentModule['title'] ?? ''));

$duration = (int) ($lesson['duration_minutes'] ?? 0);
$difficulty = trim((string) ($lesson['difficulty'] ?? ''));

/** Objectif : première ligne des objectifs de la leçon, sinon ceux du module. */
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

/** Cartes de méta : on n'affiche que ce qui est réellement renseigné. */
$metas = [];
if ($duration > 0) {
    $metas[] = ['label' => 'Durée indicative', 'value' => '~' . $duration . ' min'];
}
if ($difficulty !== '') {
    $metas[] = ['label' => 'Niveau', 'value' => $difficulty];
}
if ($objective !== '') {
    $metas[] = ['label' => 'Objectif', 'value' => $objective, 'wide' => true];
}
?>
<section class="stage-hero">
    <div class="stage-hero__main">
        <?php if ($moduleTitle !== ''): ?>
            <p class="stage-hero__eyebrow"><?= $h($moduleTitle) ?></p>
        <?php endif; ?>
        <h1 class="stage-hero__title"><?= $h($title !== '' ? $title : 'Leçon') ?></h1>
        <span class="stage-hero__rule" aria-hidden="true"></span>
        <?php if ($lead !== ''): ?>
            <p class="stage-hero__lead"><?= $h($lead) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($metas !== []): ?>
        <dl class="stage-hero__metas">
            <?php foreach ($metas as $meta): ?>
                <div class="stage-hero__meta<?= !empty($meta['wide']) ? ' stage-hero__meta--wide' : '' ?>">
                    <dt><?= $h($meta['label']) ?></dt>
                    <dd><?= $h($meta['value']) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</section>

<?php if ($moduleLessonStep !== null && (int) ($moduleLessonStep['total'] ?? 0) > 0): ?>
    <?php
    $cur = max(1, (int) $moduleLessonStep['current']);
    $tot = max(1, (int) $moduleLessonStep['total']);
    $pct = (int) round(($cur / $tot) * 100);
    ?>
    <section class="stage-modmeter">
        <div class="stage-modmeter__head">
            <span>Progression dans le module</span>
            <strong>Leçon <?= (int) $cur ?> sur <?= (int) $tot ?></strong>
        </div>
        <div class="stage-modmeter__bar" role="img"
             aria-label="Progression du module : leçon <?= (int) $cur ?> sur <?= (int) $tot ?>">
            <span style="width: <?= (int) $pct ?>%"></span>
        </div>
    </section>
<?php endif; ?>
