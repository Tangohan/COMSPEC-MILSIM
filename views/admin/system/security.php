<?php

declare(strict_types=1);

use App\Services\Watch\LoadLevel;

$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$trafficTime = new DateTimeZone('Europe/Paris');
$loginTables = [
    'sources' => ['Force brute et balayage de comptes', 'Au moins 10 échecs depuis la même IP en 15 minutes.', 'Adresse IP', 'ip', 'Échecs', 'failures', 'Adresses e-mail visées', 'targets'],
    'targets' => ['Adresses e-mail ciblées', 'Au moins 5 échecs depuis 3 IP ou plus en 24 heures.', 'Adresse e-mail', 'email', 'Échecs', 'failures', 'IP distinctes', 'sources'],
    'successful' => ['Connexions réussies depuis plusieurs IP', 'Au moins 3 IP distinctes pour la même adresse e-mail en une heure. Un VPN ou un changement de réseau peut expliquer ce signal.', 'Adresse e-mail', 'email', 'Connexions', 'logins', 'IP distinctes', 'sources'],
];
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb"><a href="<?= $h(url('admin')) ?>">Administration du site</a> / Sécurité</p>
            <h1 class="pa-hero__title">Surveillance de la sécurité</h1>
            <p class="pa-hero__lead">Examinez les signaux avant de prendre une mesure sur un compte ou une adresse IP.</p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/users')) ?>">Gérer les comptes</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/blocklist')) ?>">Liste de restriction</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/surcharge')) ?>">Charge et alertes</a>
            </div>
        </header>

        <?php if ($loginSignals === null): ?>
            <p class="pa-flash pa-flash--warn" role="status">Le journal des connexions est indisponible.</p>
        <?php else: ?>
            <?php foreach ($loginTables as $key => [$title, $description, $identityLabel, $identity, $countLabel, $count, $scopeLabel, $scope]): ?>
                <section class="pa-panel" aria-labelledby="security-<?= $h($key) ?>">
                    <h2 id="security-<?= $h($key) ?>" class="pa-card__title"><?= $h($title) ?></h2>
                    <p class="pa-hint"><?= $h($description) ?> Jusqu’à 20 résultats.</p>
                    <?php if ($loginSignals[$key] === []): ?>
                        <p>Aucun signal sur cette période.</p>
                    <?php else: ?>
                        <div class="pa-table-wrap">
                            <table class="pa-table">
                                <thead><tr><th scope="col"><?= $h($identityLabel) ?></th><th scope="col"><?= $h($countLabel) ?></th><th scope="col"><?= $h($scopeLabel) ?></th><th scope="col">Dernière tentative (heure serveur)</th></tr></thead>
                                <tbody>
                                <?php foreach ($loginSignals[$key] as $row): ?>
                                    <tr>
                                        <th scope="row">
                                            <?php if ($identity === 'email'): ?>
                                                <a href="<?= $h(url('admin/recherche') . '?' . http_build_query(['q' => $row[$identity]])) ?>"><?= $h($row[$identity]) ?></a>
                                            <?php else: ?>
                                                <?= $h($row[$identity]) ?>
                                            <?php endif; ?>
                                        </th>
                                        <td><?= $h($row[$count]) ?></td><td><?= $h($row[$scope]) ?></td><td><?= $h($row['last_seen']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <section class="pa-panel" aria-labelledby="security-traffic">
            <h2 id="security-traffic" class="pa-card__title">Pics de trafic et risque de DDoS</h2>
            <p class="pa-hint">Le trafic mesuré par Athena permet de repérer une surcharge. Pour confirmer un DDoS, comparez les pics avec les données du proxy, du CDN ou de l’hébergeur.</p>
            <?php if ($trafficSensorAge === null || $trafficSensorAge > LoadLevel::MAX_AGE_SECONDS): ?>
                <p class="pa-flash pa-flash--warn">Le capteur de charge ne fournit pas d’état récent.</p>
            <?php endif; ?>
            <?php if ($trafficPeaks === []): ?>
                <p>Aucune mesure de trafic disponible sur la dernière heure.</p>
            <?php else: ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <caption>Les cinq minutes les plus chargées de la dernière heure</caption>
                        <thead><tr><th scope="col">Heure de Paris</th><th scope="col">Requêtes</th><th scope="col">Erreurs 5xx</th><th scope="col">Requêtes ralenties</th></tr></thead>
                        <tbody>
                        <?php foreach ($trafficPeaks as $row): ?>
                            <tr>
                                <th scope="row"><?= $h((new DateTimeImmutable('@' . $row['minute']))->setTimezone($trafficTime)->format('d/m H:i T')) ?></th>
                                <td><?= $h($row['requests']) ?></td><td><?= $h($row['errors']) ?></td><td><?= $h($row['shed']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($trafficRoutes !== []): ?>
                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <caption>Routes les plus sollicitées sur les 15 dernières minutes</caption>
                        <thead><tr><th scope="col">Route</th><th scope="col">Requêtes</th><th scope="col">15 minutes précédentes</th><th scope="col">Erreurs 5xx</th></tr></thead>
                        <tbody>
                        <?php foreach ($trafficRoutes as $row): ?>
                            <tr>
                                <th scope="row"><?= $h($row['route']) ?></th>
                                <td><?= $h($row['requests']) ?></td><td><?= $h($row['previous']) ?></td><td><?= $h($row['errors']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
