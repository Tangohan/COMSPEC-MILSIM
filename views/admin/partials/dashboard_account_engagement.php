<?php

declare(strict_types=1);

$engagement = $activity['engagement'];
$returns = $activity['returns'];
$percentage = static fn (int $count, int $total): ?float => $total > 0 ? round(100 * $count / $total, 1) : null;
$percentLabel = static fn (?float $value): string => $value === null ? '—' : number_format($value, 1, ',', ' ') . ' %';
$ageLabels = ['new' => 'Moins de 30 j', 'established' => '30 à moins de 180 j', 'old' => '180 j et plus'];
$loginLabels = ['recent' => '0 à 30 j', '30' => 'Plus de 30 à 90 j', '90' => 'Plus de 90 à 180 j', '180' => 'Plus de 180 j', 'never' => 'Jamais connecté'];
$agePoints = [];
$loginPoints = [];
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
    <?php endif; ?>
</article>
<?php foreach ([
    'age' => ['Connexions selon l’ancienneté', 'Part des comptes de chaque tranche d’âge dont la dernière connexion remonte à 30 jours ou moins.', $agePoints],
    'inactivity' => ['Temps depuis la dernière connexion', 'Répartition actuelle de tous les comptes selon leur dernière connexion enregistrée.', $loginPoints],
] as $chartKey => [$title, $description, $points]): ?>
    <article class="ph-chart" aria-labelledby="ph-chart-<?= $h($chartKey) ?>">
        <div class="ph-chart__head">
            <h3 id="ph-chart-<?= $h($chartKey) ?>"><?= $h($title) ?></h3>
            <p><?= $h($description) ?></p>
        </div>
        <?php if ($engagement === null): ?>
            <p class="ph-chart__empty">Données des comptes indisponibles.</p>
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
