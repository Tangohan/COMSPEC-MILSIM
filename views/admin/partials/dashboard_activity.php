<?php

declare(strict_types=1);

use App\Services\Admin\PlatformDashboardActivity;

$activity = $dashboardActivity;
$series = [
    [
        'key' => 'signups',
        'title' => 'Nouveaux comptes',
        'help' => 'Premières inscriptions par jour, une par adresse e-mail.',
        'unit' => 'Sur la période',
        'link' => 'admin/users',
        'linkLabel' => 'Voir les comptes',
    ],
    [
        'key' => 'accounts',
        'title' => 'Flux utilisateurs',
        'help' => 'Comptes connectés distincts observés par jour dans l’échantillon web.',
        'unit' => 'Pic quotidien',
        'link' => 'admin/analytics',
        'linkLabel' => 'Analyser les usages',
    ],
    [
        'key' => 'downloads',
        'title' => 'Téléchargements du modpack',
        'help' => 'Téléchargements démarrés depuis le portail Athena.',
        'unit' => 'Sur la période',
        'link' => 'modpacks',
        'linkLabel' => 'Voir le modpack',
    ],
];
$shortDate = static fn (string $date): string => substr($date, 8, 2) . '/' . substr($date, 5, 2);
?>
<section class="ph-activity" aria-labelledby="ph-activity-title">
    <div class="ph-activity__head">
        <div>
            <h2 id="ph-activity-title">Activité du site</h2>
            <p><?= $h($activity['periodLabel']) ?> · jours complets</p>
        </div>
        <nav class="ph-periods" aria-label="Période de l’activité">
            <?php foreach (PlatformDashboardActivity::PERIODS as $period): ?>
                <a href="<?= $h(url('admin') . '?days=' . $period) ?>"
                   <?= $activity['days'] === $period ? 'aria-current="page"' : '' ?>><?= $period ?> j</a>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="ph-activity__grid">
        <?php foreach ($series as $chart): ?>
            <?php
            $key = $chart['key'];
            $rows = $key === 'signups' ? ($activity['engagement']['signups'] ?? null) : $activity[$key];
            $values = $rows === null ? [] : array_column($rows, 'total');
            $peak = $values === [] ? 0 : max($values);
            $headline = $key === 'accounts' ? $peak : array_sum($values);
            ?>
            <article class="ph-chart ph-chart--<?= $h($key) ?>" aria-labelledby="ph-chart-<?= $h($key) ?>">
                <div class="ph-chart__head">
                    <h3 id="ph-chart-<?= $h($key) ?>"><?= $h($chart['title']) ?></h3>
                    <p><?= $h($chart['help']) ?></p>
                </div>
                <?php if ($rows === null): ?>
                    <p class="ph-chart__empty">Données indisponibles pour cette période.</p>
                <?php else: ?>
                    <div class="ph-chart__metric"><strong><?= $nf($headline) ?></strong><span><?= $h($chart['unit']) ?></span></div>
                    <div class="ph-chart__plot" role="img" aria-label="<?= $h($chart['title']) ?> par jour. Valeurs exactes dans le tableau ci-dessous.">
                        <span class="ph-chart__scale" aria-hidden="true"><?= $nf($peak) ?></span>
                        <div class="ph-chart__bars" aria-hidden="true">
                            <?php foreach ($rows as $point): ?>
                                <?php $percentage = $peak > 0 ? (int) round(100 * $point['total'] / $peak) : 0; ?>
                                <span class="ph-chart__slot" title="<?= $h($shortDate($point['date'])) ?> · <?= $nf($point['total']) ?>">
                                    <span class="<?= $key === 'signups' ? 'ph-chart__point' : 'ph-chart__bar' ?>" style="--ph-bar:<?= $percentage ?>%"></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ph-chart__dates" aria-hidden="true">
                        <span><?= $h($shortDate($rows[0]['date'])) ?></span>
                        <span><?= $h($shortDate($rows[intdiv(count($rows), 2)]['date'])) ?></span>
                        <span><?= $h($shortDate($rows[count($rows) - 1]['date'])) ?></span>
                    </div>
                <?php endif; ?>
                <div class="ph-chart__footer">
                    <?php if ($rows !== null): ?>
                        <details>
                            <summary>Voir les valeurs</summary>
                            <table class="pa-table" aria-label="<?= $h($chart['title']) ?> par jour">
                                <thead><tr><th scope="col">Date</th><th scope="col">Total</th></tr></thead>
                                <tbody>
                                <?php foreach ($rows as $point): ?>
                                    <tr><th scope="row"><?= $h($shortDate($point['date'])) ?></th><td><?= $nf($point['total']) ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                    <a href="<?= $h(url($chart['link'])) ?>"><?= $h($chart['linkLabel']) ?></a>
                </div>
            </article>
            <?php if ($key === 'signups') { require base_path('views/admin/partials/dashboard_account_engagement.php'); } ?>
        <?php endforeach; ?>
        <article class="ph-chart ph-chart--pages" aria-labelledby="ph-chart-pages">
            <div class="ph-chart__head">
                <h3 id="ph-chart-pages">Pages les moins visitées</h3>
                <p>Vues échantillonnées sur une sélection de pages publiques.</p>
            </div>
            <?php if ($activity['pages'] === null): ?>
                <p class="ph-chart__empty">Données indisponibles pour cette période.</p>
            <?php else: ?>
                <?php $pageMax = max(1, ...array_column($activity['pages'], 'total')); ?>
                <ol class="ph-chart__ranking">
                    <?php foreach ($activity['pages'] as $page): ?>
                        <li>
                            <a href="<?= $h(url(ltrim($page['path'], '/'))) ?>"><?= $h($page['label']) ?></a>
                            <strong aria-label="<?= $nf($page['total']) ?> vues observées"><?= $nf($page['total']) ?></strong>
                            <span aria-hidden="true" class="ph-chart__track">
                                <span style="--ph-bar:<?= (int) round(100 * $page['total'] / $pageMax) ?>%"></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>
    </div>
</section>
