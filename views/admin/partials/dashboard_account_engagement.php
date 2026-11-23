<?php

declare(strict_types=1);

$engagement = $activity['engagement'];
$returns = $activity['returns'];
$followup = $activity['followup'];
$percentage = static fn (int $count, int $total): ?float => $total > 0 ? round(100 * $count / $total, 1) : null;
$percentLabel = static fn (?float $value): string => $value === null ? '—' : number_format($value, 1, ',', ' ') . ' %';
$ageLabels = ['new' => 'Moins de 30 j', 'established' => '30 à moins de 180 j', 'old' => '180 j et plus'];
$loginLabels = ['recent' => '0 à 30 j', '30' => 'Plus de 30 à 90 j', '90' => 'Plus de 90 à 180 j', '180' => 'Plus de 180 j', 'never' => 'Jamais connecté'];
$agePoints = [];
$loginPoints = [];
$frequencyPoints = [];
$followupPoints = [];
if ($returns !== null && $returns['signed_in'] > 0) {
    foreach (['one_day' => '1 jour', 'two_to_three' => '2 à 3 jours', 'four_to_six' => '4 à 6 jours', 'seven_plus' => '7 jours ou plus'] as $key => $label) {
        $frequencyPoints[] = ['label' => $label, 'recent' => $returns[$key], 'total' => $returns['signed_in']];
    }
}
if ($followup !== null && $followup['total'] > 0) {
    $followupPoints = [
        ['label' => 'Connectés entre J0 et J7', 'recent' => $followup['activated'], 'total' => $followup['total']],
        ['label' => 'Connectés les jours suivant l’inscription (J1 à J7)', 'recent' => $followup['returned'], 'total' => $followup['total']],
    ];
}
if ($engagement !== null) {
    if (isset($engagement['ages']['unknown'])) {
        $ageLabels['unknown'] = 'Date inconnue';
    }
    foreach ($ageLabels as $key => $label) {
        $agePoints[] = ['label' => $label] + ($engagement['ages'][$key] ?? ['recent' => 0, 'total' => 0]);
    }
    $totalAccounts = array_sum($engagement['lastLogins']);
    foreach ($loginLabels as $key => $label) {
        $loginPoints[] = ['label' => $label, 'recent' => $engagement['lastLogins'][$key] ?? 0, 'total' => $totalAccounts];
    }
}
$regions = $activity['regions'] ?? null;
$regionPoints = [];
if (is_array($regions) && !empty($regions['available'])) {
    foreach ($regions['rows'] as $row) {
        $regionPoints[] = ['label' => $row['label'], 'recent' => $row['count'], 'total' => $regions['total']];
    }
    if ($regions['others'] > 0) {
        $regionPoints[] = ['label' => 'Autres régions (effectifs trop faibles pour être détaillés)', 'recent' => $regions['others'], 'total' => $regions['total']];
    }
    if ($regions['none'] > 0) {
        $regionPoints[] = ['label' => 'Région non indiquée', 'recent' => $regions['none'], 'total' => $regions['total']];
    }
}
?>
<article class="ph-chart" aria-labelledby="ph-chart-returns">
    <div class="ph-chart__head">
        <h3 id="ph-chart-returns">Taux de retour</h3>
        <p>Part des comptes connectés sur au moins deux jours distincts, parmi ceux ayant une connexion réussie enregistrée sur la période.</p>
    </div>
    <?php if ($returns === null): ?>
        <p class="ph-chart__empty">Journal des connexions indisponible.</p>
    <?php elseif ($returns['signed_in'] === 0): ?>
        <p class="ph-chart__empty">Aucune connexion enregistrée sur la période.</p>
    <?php else: ?>
        <div class="ph-chart__metric">
            <strong><?= $h($percentLabel($percentage($returns['returned'], $returns['signed_in']))) ?></strong>
            <span><?= $nf($returns['returned']) ?> sur <?= $nf($returns['signed_in']) ?> comptes</span>
        </div>
        <p class="ph-chart__head">En moyenne, <?= $h(number_format($returns['average_days'], 1, ',', ' ')) ?> jours de connexion par compte connecté.</p>
    <?php endif; ?>
</article>
<?php foreach ([
    'frequency' => ['Fréquence de connexion', 'Nombre de jours distincts avec une connexion réussie sur la période.', $frequencyPoints, $returns !== null, 'Aucune connexion enregistrée sur la période.'],
    'signup-followup' => ['Première semaine après l’inscription', 'Inscriptions du ' . $activity['cohortLabel'] . ' · suivi complet jusqu’à J7, selon les connexions enregistrées.', $followupPoints, $followup !== null, 'Aucune inscription sur les dates observées.'],
    'age' => ['Connexions selon l’ancienneté', 'Part des comptes de chaque tranche d’âge dont la dernière connexion remonte à 30 jours ou moins.', $agePoints, $engagement !== null, ''],
    'inactivity' => ['Temps depuis la dernière connexion', 'Répartition actuelle de tous les comptes selon leur dernière connexion enregistrée.', $loginPoints, $engagement !== null, ''],
    'regions' => ['Comptes par région', 'Région que les personnes ont choisi d’indiquer, en totaux uniquement. Les régions de moins de 5 comptes sont masquées.', $regionPoints, is_array($regions) && !empty($regions['available']), 'Aucun compte n’a encore indiqué de région.'],
] as $chartKey => [$title, $description, $points, $available, $emptyMessage]): ?>
    <article class="ph-chart" aria-labelledby="ph-chart-<?= $h($chartKey) ?>">
        <div class="ph-chart__head">
            <h3 id="ph-chart-<?= $h($chartKey) ?>"><?= $h($title) ?></h3>
            <p><?= $h($description) ?></p>
        </div>
        <?php if (!$available): ?>
            <p class="ph-chart__empty">Données des comptes indisponibles.</p>
        <?php elseif ($points === []): ?>
            <p class="ph-chart__empty"><?= $h($emptyMessage) ?></p>
        <?php else: ?>
            <ul class="ph-dot-chart">
                <?php foreach ($points as $point): ?>
                    <?php $share = $percentage($point['recent'], $point['total']); ?>
                    <li>
                        <span><?= $h($point['label']) ?></span>
                        <strong><?= $h($percentLabel($share)) ?></strong>
                        <span class="ph-dot-chart__count"><?= $nf($point['recent']) ?> / <?= $nf($point['total']) ?> comptes</span>
                        <span class="ph-dot-chart__axis" aria-hidden="true">
                            <?php if ($share !== null): ?>
                                <span class="ph-chart__point" style="--ph-share:<?= $h($share) ?>%"></span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
