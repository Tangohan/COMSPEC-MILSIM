<?php
declare(strict_types=1);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$awards = is_array($awards ?? null) ? $awards : [];

$tsOf = static fn (array $row): int => (int) (strtotime((string) ($row['awarded_at'] ?? '')) ?: 0);
usort($awards, static fn (array $a, array $b): int => $tsOf($b) <=> $tsOf($a));
$latest = $awards[0] ?? null;
$thisYear = 0;
$authorities = [];
foreach ($awards as $row) {
    $ts = $tsOf($row);
    if ($ts > 0 && date('Y', $ts) === date('Y')) {
        $thisYear++;
    }
    $auth = trim((string) ($row['authority'] ?? ''));
    if ($auth !== '') {
        $authorities[mb_strtolower($auth)] = true;
    }
}
$date = static function (array $row) use ($tsOf): string {
    $ts = $tsOf($row);

    return $ts > 0 ? date('d/m/Y', $ts) : '—';
};
$iconMedal = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="15" r="5"/><path d="M8.5 11 6 3h4l2 5 2-5h4l-2.5 8"/></svg>';

$summary = [
    'kicker' => 'Dossier individuel · Décorations',
    'focus' => [
        'label' => $latest !== null ? 'Dernière distinction' : 'Distinctions',
        'value' => $latest !== null ? (string) ($latest['definition_name'] ?? '') : 'Aucune pour l’instant',
        'meta' => $latest !== null
            ? 'Attribuée le ' . $date($latest) . (trim((string) ($latest['authority'] ?? '')) !== '' ? ' par ' . trim((string) $latest['authority']) : '')
            : 'Ce que l’on vous a reconnu avoir fait. Vos savoir-faire sont dans Mes qualifications.',
        'badge_html' => $iconMedal,
        'empty' => $latest === null,
    ],
    'links' => [
        ['label' => 'Mes qualifications', 'href' => url('back-office/ma-situation/qualifications')],
        ['label' => 'Dossier de carrière', 'href' => url('back-office/ma-situation/carriere')],
    ],
    'stats' => [
        ['label' => 'Décorations', 'value' => count($awards), 'note' => 'Citations et médailles enregistrées.'],
        ['label' => 'Cette année', 'value' => $thisYear, 'note' => 'Attribuées en ' . date('Y') . '.'],
        ['label' => 'Autorités', 'value' => count($authorities), 'note' => 'Ont signé au moins une distinction.'],
        ['label' => 'Dernière', 'value' => $latest !== null ? $date($latest) : '—', 'note' => 'Date d’attribution.', 'small' => true],
    ],
];
?>
<div class="msd">
    <?php require __DIR__ . '/_summary.php'; ?>

    <section class="msd-card" aria-labelledby="msd-decorations-title">
        <header class="msd-card__head">
            <div>
                <p class="msd-card__kicker">Reconnaissance</p>
                <h2 class="msd-card__title" id="msd-decorations-title">Mes décorations</h2>
            </div>
            <?php if ($awards !== []): ?>
                <span class="msd-card__count">Du plus récent au plus ancien</span>
            <?php endif; ?>
        </header>
        <?php if ($awards === []): ?>
            <div class="msd-empty">
                <span class="msd-empty__icon"><?= $iconMedal ?></span>
                <strong>Aucune décoration pour l’instant</strong>
                <p>Les citations et médailles attribuées par l’encadrement apparaîtront ici, avec leur motif.</p>
            </div>
        <?php else: ?>
            <div class="msd-items">
                <?php foreach ($awards as $row): ?>
                    <?php $grade = trim((string) ($row['decoration_grade'] ?? '')); ?>
                    <article class="msd-item">
                        <div class="msd-item__top">
                            <span class="msd-item__icon msd-item__icon--gold"><?= $iconMedal ?></span>
                            <?php if ($grade !== ''): ?>
                                <span class="msd-pill msd-pill--gold"><?= $h($grade) ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="msd-item__kicker">Décoration</p>
                            <h3 class="msd-item__title"><?= $h((string) ($row['definition_name'] ?? '')) ?></h3>
                        </div>
                        <?php if (trim((string) ($row['citation_text'] ?? '')) !== ''): ?>
                            <p class="msd-quote"><?= $h((string) $row['citation_text']) ?></p>
                        <?php endif; ?>
                        <dl class="msd-item__facts">
                            <div><dt>Attribuée le</dt><dd><?= $h($date($row)) ?></dd></div>
                            <div><dt>Autorité</dt><dd><?= $h(trim((string) ($row['authority'] ?? '')) !== '' ? $row['authority'] : '—') ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
