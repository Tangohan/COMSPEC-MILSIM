<?php
declare(strict_types=1);

use App\Support\AdvancementCodes;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$timeline = is_array($timeline ?? null) ? $timeline : [];
$gradeHistory = is_array($gradeHistory ?? null) ? $gradeHistory : [];
$success = trim((string) ($success ?? ''));
$error = trim((string) ($error ?? ''));
$kindLabel = static fn (string $k): string => match ($k) {
    'grade' => 'Grade',
    'promotion' => 'Promotion',
    'qualification' => 'Qualification',
    'award' => 'Décoration',
    'billet', 'assignment' => 'Affectation',
    'deployment' => 'Opération',
    'equipment' => 'Dotation',
    'discipline' => 'Discipline',
    'note' => 'Note',
    default => $k,
};
$kindTone = static fn (string $k): string => match ($k) {
    'grade', 'promotion' => 'ok',
    'qualification' => 'info',
    'award' => 'gold',
    'discipline' => 'bad',
    'deployment' => 'warn',
    default => 'muted',
};
$date = static function (mixed $raw): string {
    $ts = strtotime((string) $raw);

    return $raw !== null && $raw !== '' && $ts !== false ? date('d/m/Y', $ts) : '—';
};
$gradeName = static fn (array $row): string => (string) ($row['label'] ?? $row['grade_label'] ?? '');

$currentGrade = null;
foreach ($gradeHistory as $row) {
    if (empty($row['ends_at'])) {
        $currentGrade = $row;
        break;
    }
}

$counts = ['grade' => 0, 'qualification' => 0, 'award' => 0, 'deployment' => 0];
$byYear = [];
foreach ($timeline as $item) {
    $kind = (string) ($item['kind'] ?? '');
    $bucket = $kind === 'promotion' ? 'grade' : $kind;
    if (isset($counts[$bucket])) {
        $counts[$bucket]++;
    }
    $ts = strtotime((string) ($item['at'] ?? ''));
    $year = ($item['at'] ?? '') !== '' && $ts !== false ? date('Y', $ts) : 'Sans date';
    $byYear[$year][] = $item;
}
$iconRank = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6-4 6 4"/><path d="m6 14 6-4 6 4"/><path d="m6 19 6-4 6 4"/></svg>';
$iconClock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';

$summary = [
    'kicker' => 'Dossier individuel · Carrière',
    'focus' => [
        'label' => 'Grade actuel',
        'value' => $currentGrade !== null ? $gradeName($currentGrade) : 'Non renseigné',
        'meta' => $currentGrade !== null
            ? 'Depuis le ' . $date($currentGrade['obtained_at'] ?? '') . ' · ' . AdvancementCodes::viaLabel((string) ($currentGrade['obtained_via'] ?? ''))
            : 'Grades, affectations, qualifications, décorations et opérations, dans l’ordre.',
        'badge_html' => $iconRank,
        'empty' => $currentGrade === null,
    ],
    'links' => [
        ['label' => 'Mon avancement', 'href' => url('back-office/ma-situation/avancement'), 'primary' => true],
        ['label' => 'Mes qualifications', 'href' => url('back-office/ma-situation/qualifications')],
        ['label' => 'Mes décorations', 'href' => url('back-office/ma-situation/decorations')],
    ],
    'stats' => [
        ['label' => 'Événements', 'value' => count($timeline), 'note' => 'Inscrits à votre dossier.'],
        ['label' => 'Grades', 'value' => max($counts['grade'], count($gradeHistory)), 'note' => 'Nominations et promotions.'],
        ['label' => 'Qualifications', 'value' => $counts['qualification'], 'note' => 'Obtenues en formation.'],
        ['label' => 'Décorations', 'value' => $counts['award'], 'note' => 'Citations et médailles.'],
    ],
];
?>
<div class="msd">
    <?php if ($success !== ''): ?><p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p><?php endif; ?>

    <?php require __DIR__ . '/_summary.php'; ?>

    <?php if ($gradeHistory !== []): ?>
        <section class="msd-card" aria-labelledby="msd-grades-title">
            <header class="msd-card__head">
                <div>
                    <p class="msd-card__kicker">Grades</p>
                    <h2 class="msd-card__title" id="msd-grades-title">Historique de grade</h2>
                </div>
                <span class="msd-card__count"><?= count($gradeHistory) ?> grade<?= count($gradeHistory) > 1 ? 's' : '' ?></span>
            </header>
            <ol class="msd-timeline">
                <?php foreach ($gradeHistory as $row): ?>
                    <?php $isCurrent = empty($row['ends_at']); ?>
                    <li class="msd-timeline__item<?= $isCurrent ? ' is-current' : '' ?>">
                        <span class="msd-timeline__dot" aria-hidden="true"></span>
                        <div class="msd-timeline__body">
                            <strong><?= $h($gradeName($row)) ?></strong>
                            <span>
                                <?= $h(AdvancementCodes::viaLabel((string) ($row['obtained_via'] ?? ''))) ?>
                                · <?= $isCurrent ? 'Grade actuel' : 'Jusqu’au ' . $h($date($row['ends_at'])) ?>
                            </span>
                        </div>
                        <span class="msd-timeline__date"><?= $h($date($row['obtained_at'] ?? '')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <section class="msd-card" aria-labelledby="msd-timeline-title">
        <header class="msd-card__head">
            <div>
                <p class="msd-card__kicker">Chronologie</p>
                <h2 class="msd-card__title" id="msd-timeline-title">Mon parcours</h2>
            </div>
            <?php if ($timeline !== []): ?>
                <span class="msd-card__count"><?= count($timeline) ?> événement<?= count($timeline) > 1 ? 's' : '' ?></span>
            <?php endif; ?>
        </header>
        <?php if ($timeline === []): ?>
            <div class="msd-empty">
                <span class="msd-empty__icon"><?= $iconClock ?></span>
                <strong>Aucun événement de carrière pour l’instant</strong>
                <p>Vos nominations, affectations, qualifications, décorations et opérations s’ajouteront ici au fil du temps.</p>
            </div>
        <?php else: ?>
            <?php foreach ($byYear as $year => $items): ?>
                <h3 class="msd-year"><?= $h((string) $year) ?></h3>
                <ol class="msd-timeline">
                    <?php foreach ($items as $item): ?>
                        <?php $kind = (string) ($item['kind'] ?? ''); ?>
                        <li class="msd-timeline__item">
                            <span class="msd-timeline__dot" aria-hidden="true"></span>
                            <div class="msd-timeline__body">
                                <div><span class="msd-pill msd-pill--<?= $h($kindTone($kind)) ?>"><?= $h($kindLabel($kind)) ?></span></div>
                                <strong><?= $h((string) ($item['title'] ?? '')) ?></strong>
                                <?php if (trim((string) ($item['detail'] ?? '')) !== '' || !empty($item['via'])): ?>
                                    <span><?= $h((string) ($item['detail'] ?? '')) ?><?= !empty($item['via']) ? (trim((string) ($item['detail'] ?? '')) !== '' ? ' · ' : '') . $h((string) $item['via']) : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="msd-timeline__date"><?= $h($date($item['at'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
