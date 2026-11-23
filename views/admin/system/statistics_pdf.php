<?php

declare(strict_types=1);

$h = static fn (string|int|float|null $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$number = static fn (?int $value): string => $value === null ? 'Indisponible' : number_format($value, 0, ',', ' ');
$share = static fn (int $n, int $total): string => $total === 0 ? '—' : number_format(100 * $n / $total, 1, ',', ' ') . ' %';
$seriesTotal = static fn (?array $rows): ?int => $rows === null ? null : array_sum(array_column($rows, 'total'));
$signups = $activity['engagement']['signups'] ?? null;
$returns = $activity['returns'];
$followup = $activity['followup'];
$summary = [
    'Nouveaux comptes' => $number($seriesTotal($signups)),
    'Comptes avec au moins une connexion enregistrée' => $number($returns['signed_in'] ?? null),
    'Taux de retour sur au moins deux jours distincts' => $returns === null ? 'Indisponible' : $share($returns['returned'], $returns['signed_in'])
        . ' (' . $number($returns['returned']) . ' / ' . $number($returns['signed_in']) . ')',
    'Téléchargements démarrés du modpack' => $number($seriesTotal($activity['downloads'])),
    'Communautés avec une activité mesurée' => $communities === null ? 'Indisponible' : $number($communities['active'])
        . ' / ' . $number($communities['total']) . ' (' . $share($communities['active'], $communities['total']) . ')',
    'Événements mesurés dans les communautés' => $number($communities['events'] ?? null),
];
$table = static function (array $rows) use ($h): void {
    ?>
    <table class="report">
        <thead><tr><th scope="col">Indicateur</th><th scope="col" class="num">Résultat</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $label => $value): ?>
            <tr><th scope="row"><?= $h($label) ?></th><td class="num"><?= $h($value) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
};
?>
<h1><?= $h($title) ?></h1>
<p class="period"><?= $h($period) ?> · <?= (int) $activity['days'] ?> jours complets en UTC</p>
<h2>Adoption et engagement</h2>
<?php $table($summary); ?>
<p class="note">Les inscriptions sont dédoublonnées par adresse e-mail. Le taux de retour est calculé parmi les comptes ayant une connexion réussie dans le journal de la période. Une communauté est comptée comme active si au moins un événement d’usage y a été enregistré. Les espaces système sont exclus.</p>

<h2>Première semaine après l’inscription</h2>
<p class="note">Inscriptions du <?= $h($activity['cohortLabel']) ?>. Chaque inscrit a une fenêtre complète jusqu’à J7.</p>
<?php if ($followup === null): ?>
    <p>Données indisponibles.</p>
<?php else: ?>
    <?php $table([
        'Inscriptions suivies' => $number($followup['total']),
        'Connectés de J0 à J7' => $share($followup['activated'], $followup['total']) . ' (' . $number($followup['activated']) . ')',
        'Connectés de J1 à J7' => $share($followup['returned'], $followup['total']) . ' (' . $number($followup['returned']) . ')',
    ]); ?>
<?php endif; ?>

<?php if (!$partner): ?>
    <h2>Fréquence des connexions</h2>
    <?php if ($returns === null): ?>
        <p>Journal indisponible.</p>
    <?php else: ?>
        <?php
        $frequency = ['Nombre moyen de jours par compte connecté' => $returns['signed_in'] === 0 ? '—' : number_format($returns['average_days'], 1, ',', ' ')];
        foreach (['one_day' => '1 jour', 'two_to_three' => '2 à 3 jours', 'four_to_six' => '4 à 6 jours', 'seven_plus' => '7 jours ou plus'] as $key => $label) {
            $frequency[$label] = $number($returns[$key]) . ' (' . $share($returns[$key], $returns['signed_in']) . ')';
        }
        $table($frequency);
        ?>
    <?php endif; ?>

    <h2>Ancienneté et dernière connexion</h2>
    <p class="note">Situation au moment de l’export. Les seuils d’inactivité sont calculés depuis la dernière connexion connue.</p>
    <?php if ($activity['engagement'] === null): ?>
        <p>Données des comptes indisponibles.</p>
    <?php else: ?>
        <?php
        $engagement = $activity['engagement'];
        $loginTotal = array_sum($engagement['lastLogins']);
        $lastSeen = ['Comptes recensés' => $number($loginTotal)];
        foreach (['recent' => '0 à 30 jours', '30' => 'Plus de 30 à 90 jours', '90' => 'Plus de 90 à 180 jours', '180' => 'Plus de 180 jours', 'never' => 'Jamais connectés'] as $key => $label) {
            $count = $engagement['lastLogins'][$key] ?? 0;
            $lastSeen[$label] = $number($count) . ' (' . $share($count, $loginTotal) . ')';
        }
        $table($lastSeen);
        $ages = [];
        foreach (['new' => 'Moins de 30 jours', 'established' => '30 à moins de 180 jours', 'old' => '180 jours et plus', 'unknown' => 'Date inconnue'] as $key => $label) {
            $group = $engagement['ages'][$key] ?? ['recent' => 0, 'total' => 0];
            $ages['Connectés depuis 30 jours ou moins · ' . $label] = $number($group['recent']) . ' / ' . $number($group['total'])
                . ' (' . $share($group['recent'], $group['total']) . ')';
        }
        $table($ages);
        ?>
    <?php endif; ?>
<?php endif; ?>

    <h2>Comptes par région</h2>
    <p class="note">Région que les personnes ont choisi d’indiquer, en totaux uniquement. Les régions de moins de <?= (int) \App\Support\AccountRegions::PRIVACY_THRESHOLD ?> comptes sont masquées.</p>
    <?php $regionData = $activity['regions'] ?? null; ?>
    <?php if (!is_array($regionData) || empty($regionData['available'])): ?>
        <p>Indisponible.</p>
    <?php else: ?>
        <?php
        $regionRows = [];
        foreach ($regionData['rows'] as $row) {
            $regionRows[$row['label']] = $number($row['count']) . ' (' . $share($row['count'], $regionData['total']) . ')';
        }
        if ($regionData['others'] > 0) {
            $regionRows['Autres régions (effectifs trop faibles pour être détaillés)'] = $number($regionData['others']) . ' (' . $share($regionData['others'], $regionData['total']) . ')';
        }
        $regionRows['Région non indiquée'] = $number($regionData['none']) . ' (' . $share($regionData['none'], $regionData['total']) . ')';
        $table($regionRows);
        ?>
    <?php endif; ?>

<h2>Évolution quotidienne</h2>
<?php
$daily = ['Inscriptions' => $signups, 'Téléchargements' => $activity['downloads']];
if (!$partner) {
    $daily['Comptes web observés'] = $activity['accounts'];
}
$dates = [];
$columns = [];
$peaks = [];
foreach ($daily as $label => $rows) {
    $columns[$label] = array_column($rows ?? [], 'total', 'date');
    $dates += $columns[$label];
    $peaks[$label] = max([1, ...array_values($columns[$label])]);
}
ksort($dates);
?>
<?php if ($dates === []): ?>
    <p>Données quotidiennes indisponibles.</p>
<?php else: ?>
    <table class="report">
        <thead><tr><th scope="col">Date (UTC)</th><?php foreach ($daily as $label => $rows): ?><th scope="col"><?= $h($label) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($dates as $date => $_): ?>
            <tr>
                <th scope="row"><?= $h($date) ?></th>
                <?php foreach ($daily as $label => $rows): ?>
                    <?php $count = $columns[$label][$date] ?? null; ?>
                    <td><span class="daily-value"><?= $h($count === null ? '—' : $number($count)) ?></span>
                        <?php if ($count !== null): ?><div class="daily-track"><div class="daily-bar" style="width:<?= (int) round(100 * $count / $peaks[$label]) ?>%"></div></div><?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="note">Les barres indiquent la valeur par rapport au maximum de chaque colonne. — indique une source indisponible.<?= $partner ? '' : ' Les comptes web proviennent d’un échantillon et les valeurs quotidiennes ne sont pas cumulables.' ?></p>
<?php endif; ?>

<?php if (!$partner): ?>
    <h2>Usage des communautés</h2>
    <?php if ($ranking === []): ?>
        <p><?= $communities === null ? 'Données indisponibles.' : 'Aucune communauté avec un événement mesuré.' ?></p>
    <?php else: ?>
        <p class="note">Les 15 communautés les plus actives au maximum, classées par nombre d’événements.</p>
        <table class="report">
            <thead><tr><th scope="col">Communauté</th><th scope="col" class="num">Événements</th><th scope="col" class="num">Identifiants observés</th></tr></thead>
            <tbody>
            <?php foreach ($ranking as $row): ?>
                <tr><th scope="row"><?= $h($row['name']) ?></th><td class="num"><?= $number((int) $row['events']) ?></td><td class="num"><?= $number((int) $row['actors']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <h2>Pages publiques les moins visitées</h2>
    <p class="note">Échantillon des vues sur la sélection de pages suivies par le tableau de bord.</p>
    <?php if ($activity['pages'] === null): ?>
        <p>Données indisponibles.</p>
    <?php else: ?>
        <?php $table(array_combine(array_column($activity['pages'], 'label'), array_map($number, array_column($activity['pages'], 'total')))); ?>
    <?php endif; ?>
<?php endif; ?>
