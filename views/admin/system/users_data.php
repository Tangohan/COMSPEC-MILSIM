<?php
declare(strict_types=1);

use App\Services\Admin\PlatformDataVolumeService;

$period = (int) ($dataPeriod ?? PlatformDataVolumeService::DEFAULT_PERIOD);
$view = (string) ($dataView ?? 'communaute');
$tenantId = (int) ($dataTenantId ?? 0);
$tenants = is_array($dataTenants ?? null) ? $dataTenants : [];
$totals = is_array($dataTotals ?? null) ? $dataTotals : [];
$table = is_array($dataTable ?? null) ? $dataTable : ['days' => [], 'rows' => [], 'truncated' => false];
$days = array_values(array_map('strval', is_array($table['days'] ?? null) ? $table['days'] : []));
$rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
$sources = is_array($dataSources ?? null) ? $dataSources : ['communities' => false, 'players' => false, 'photos' => false];
$error = is_string($dataError ?? null) ? $dataError : null;

$h = static fn (string|int|float|null $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$bytes = static fn (int $n): string => PlatformDataVolumeService::formatBytes($n);
$count = static fn (int $n): string => number_format($n, 0, ',', "\u{202F}");
$dayLabel = static function (string $day): string {
    $ts = strtotime($day . ' 00:00:00 UTC');

    return $ts ? gmdate('d/m', $ts) : $day;
};
$dataUrl = static function (array $override = []) use ($period, $view, $tenantId): string {
    $params = array_merge(['periode' => $period, 'vue' => $view, 'tenant_id' => $tenantId], $override);
    $bits = [];
    if ((int) $params['periode'] !== PlatformDataVolumeService::DEFAULT_PERIOD) {
        $bits['periode'] = (string) (int) $params['periode'];
    }
    if ((string) $params['vue'] === 'compte') {
        $bits['vue'] = 'compte';
    }
    if ((int) $params['tenant_id'] > 0) {
        $bits['tenant_id'] = (string) (int) $params['tenant_id'];
    }

    return url('admin/users/donnees') . ($bits !== [] ? '?' . http_build_query($bits) : '');
};
$maxDay = 0;
foreach ($totals as $t) {
    $maxDay = max($maxDay, (int) ($t['bytes'] ?? 0));
}
$grand = 0;
$grandPhoto = 0;
$grandReq = 0;
foreach ($totals as $t) {
    $grand += (int) ($t['bytes'] ?? 0);
    $grandPhoto += (int) ($t['photo_bytes'] ?? 0);
    $grandReq += (int) ($t['requests'] ?? 0);
}
$isAccount = $view === 'compte';
$hasSource = $isAccount ? !empty($sources['players']) : !empty($sources['communities']);
?>
<div class="pa pu pa--wide">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/users')) ?>">Comptes utilisateurs</a>
                <span aria-hidden="true"> / </span>
                Données par jour
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Comptes &amp; accès</p>
                    <h1 class="pa-hero__title">Données par jour</h1>
                    <p class="pa-hero__lead">
                        Combien de données le jeu envoie au site, jour après jour, par communauté ou par personne.
                        Les chiffres viennent des mesures déjà faites par le site : rien n’est estimé.
                    </p>
                    <div class="pa-hero__actions">
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/users')) ?>">Retour aux comptes</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Sur <?= $h($period) ?> jours</p>
                    <dl>
                        <div class="pa-hero__meta-row"><dt>Reçu du jeu</dt><dd><?= $h($bytes($grand)) ?></dd></div>
                        <div class="pa-hero__meta-row"><dt>dont photos</dt><dd><?= $h($bytes($grandPhoto)) ?></dd></div>
                        <div class="pa-hero__meta-row"><dt>Messages reçus</dt><dd><?= $h($count($grandReq)) ?></dd></div>
                    </dl>
                </aside>
            </div>
        </header>

        <?php if ($error !== null): ?>
            <p class="pa-flash pa-flash--err" role="alert"><?= $h($error) ?></p>
        <?php endif; ?>

        <section class="pa-card" aria-labelledby="data-measure-title">
            <p class="pa-card__kicker">À lire avant les chiffres</p>
            <h2 id="data-measure-title" class="pa-card__title">Ce que mesure chaque chiffre</h2>
            <ul class="pu-data-measure">
                <li><strong>Données reçues</strong> : la taille des messages que le jeu envoie au site (positions, rapports, photos). Ce n’est pas la place prise sur le disque ni dans la base de données.</li>
                <li><strong>dont photos</strong> : la part de ces messages qui transporte des photos de reconnaissance.</li>
                <li><strong>Messages reçus</strong> : le nombre de messages envoyés par le jeu, quelle que soit leur taille.</li>
                <li><strong>Photos enregistrées</strong> : le nombre de photos de reconnaissance gardées sur le site ce jour-là. C’est un nombre, jamais un poids : le site ne conserve pas la taille de chaque fichier.</li>
                <li><strong>Par personne</strong> : seuls les joueurs dont le Steam ID est relié à un compte sont rattachés à une personne. Les autres apparaissent sous leur indicatif, « non relié ». La somme par personne peut être un peu plus basse que le total par communauté, car certains messages n’indiquent pas de joueur.</li>
                <li><strong>Pas mesuré</strong> : documents des communautés, messages, e-mails et base de données. Aucun chiffre n’est affiché pour eux.</li>
                <li><strong>Jours</strong> : de 00 h à 24 h, heure UTC (une à deux heures de moins qu’à Paris). La période est limitée à 31 jours pour garder la page rapide.</li>
            </ul>
        </section>

        <form method="get" action="<?= $h(url('admin/users/donnees')) ?>" class="pa-filters pu-filters" style="margin-top:1rem">
            <input type="hidden" name="vue" value="<?= $h($view) ?>">
            <div class="pu-filters__grid">
                <div class="pa-field">
                    <label for="data-periode">Période</label>
                    <select id="data-periode" name="periode">
                        <?php foreach (PlatformDataVolumeService::PERIODS as $p): ?>
                            <option value="<?= $h($p) ?>" <?= $p === $period ? 'selected' : '' ?>><?= $h($p) ?> derniers jours</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pa-field">
                    <label for="data-tenant">Communauté</label>
                    <select id="data-tenant" name="tenant_id">
                        <option value="">Toutes</option>
                        <?php foreach ($tenants as $t): ?>
                            <?php $tid = (int) ($t['id'] ?? 0); ?>
                            <option value="<?= $h($tid) ?>" <?= $tid === $tenantId ? 'selected' : '' ?>><?= $h(community_display_name($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="pu-filters__buttons">
                    <button type="submit" class="pa-btn pa-btn--solid">Afficher</button>
                </div>
            </div>
        </form>

        <nav class="pu-data-tabs" aria-label="Type de tableau" style="margin-top:1rem">
            <a class="pa-btn pa-btn--sm <?= $isAccount ? 'pa-btn--line' : 'pa-btn--solid' ?>" href="<?= $h($dataUrl(['vue' => 'communaute'])) ?>"<?= $isAccount ? '' : ' aria-current="page"' ?>>Par communauté</a>
            <a class="pa-btn pa-btn--sm <?= $isAccount ? 'pa-btn--solid' : 'pa-btn--line' ?>" href="<?= $h($dataUrl(['vue' => 'compte'])) ?>"<?= $isAccount ? ' aria-current="page"' : '' ?>>Par personne</a>
        </nav>

        <section class="pa-card" aria-labelledby="data-days-title">
            <p class="pa-card__kicker"><?= $tenantId > 0 ? 'Communauté choisie' : 'Toutes les communautés' ?></p>
            <h2 id="data-days-title" class="pa-card__title">Jour par jour</h2>
            <?php if (empty($sources['communities'])): ?>
                <p class="pa-card__help">Le site n’a encore enregistré aucune mesure de données reçues du jeu : le tableau reste vide plutôt que d’afficher des zéros trompeurs.</p>
            <?php endif; ?>
            <div class="pa-sheet__scroll" role="region" aria-label="Données reçues chaque jour" tabindex="0">
                <table class="pa-sheet__grid pu-data-grid">
                    <thead>
                        <tr>
                            <th scope="col">Jour (UTC)</th>
                            <th scope="col" class="pa-sheet__num">Données reçues</th>
                            <th scope="col">Part du jour le plus chargé</th>
                            <th scope="col" class="pa-sheet__num">dont photos</th>
                            <th scope="col" class="pa-sheet__num">Messages reçus</th>
                            <th scope="col" class="pa-sheet__num">Communautés actives</th>
                            <th scope="col" class="pa-sheet__num">Photos enregistrées</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($totals, true) as $day => $t): $day = (string) $day; ?>
                            <?php $b = (int) ($t['bytes'] ?? 0); $pct = $maxDay > 0 ? (int) round($b / $maxDay * 100) : 0; ?>
                            <tr>
                                <td><?= $h($dayLabel($day)) ?></td>
                                <td class="pa-sheet__num<?= $b === 0 ? ' is-zero' : '' ?>"><?= $h($bytes($b)) ?></td>
                                <td><span class="pu-bar-cell"><span class="pu-bar-cell__track"><span class="pu-bar-cell__fill" style="width: <?= $h($pct) ?>%"></span></span><span class="pu-muted"><?= $h($pct) ?> %</span></span></td>
                                <td class="pa-sheet__num"><?= $h($bytes((int) ($t['photo_bytes'] ?? 0))) ?></td>
                                <td class="pa-sheet__num"><?= $h($count((int) ($t['requests'] ?? 0))) ?></td>
                                <td class="pa-sheet__num"><?= $h($count((int) ($t['communities'] ?? 0))) ?></td>
                                <td class="pa-sheet__num"><?= $t['photos'] === null ? '<span class="pu-muted" title="Aucune mesure disponible">—</span>' : $h($count((int) $t['photos'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pa-card pu-data-tablecard" aria-labelledby="data-matrix-title">
            <p class="pa-card__kicker"><?= $isAccount ? 'Par personne' : 'Par communauté' ?></p>
            <h2 id="data-matrix-title" class="pa-card__title">Données reçues, <?= $isAccount ? 'personne' : 'communauté' ?> par <?= $isAccount ? 'personne' : 'communauté' ?>, jour par jour</h2>
            <p class="pa-card__help">
                Les <?= $h(PlatformDataVolumeService::MAX_ROWS) ?> plus gros volumes de la période, du plus gros au plus petit. Chaque case est le volume reçu ce jour-là.
                <?php if (!empty($table['truncated'])): ?>
                    Il y en a d’autres : réduisez la période ou choisissez une communauté pour les voir.
                <?php endif; ?>
            </p>
            <?php if (!$hasSource): ?>
                <p class="pu-muted"><?= $isAccount ? 'Le site n’a encore enregistré aucune mesure par joueur.' : 'Le site n’a encore enregistré aucune mesure par communauté.' ?></p>
            <?php elseif ($rows === []): ?>
                <p class="pu-muted">Aucune donnée reçue sur cette période.</p>
            <?php else: ?>
                <div class="pa-sheet__scroll" role="region" aria-label="Volume par jour" tabindex="0">
                    <table class="pa-sheet__grid pu-data-grid">
                        <thead>
                            <tr>
                                <th scope="col"><?= $isAccount ? 'Personne' : 'Communauté' ?></th>
                                <th scope="col" class="pa-sheet__num">Total</th>
                                <?php foreach ($days as $d): ?>
                                    <th scope="col" class="pa-sheet__num"><?= $h($dayLabel($d)) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
                                $peak = 0;
                                foreach ($cells as $v) {
                                    $peak = max($peak, (int) $v);
                                }
                                ?>
                                <tr>
                                    <td class="pu-data-name">
                                        <?php if ($isAccount): ?>
                                            <?php if (!empty($row['linked']) && (string) ($row['email_key'] ?? '') !== ''): ?>
                                                <a class="pu-link" href="<?= $h(url('admin/users/person') . '?email=' . rawurlencode((string) $row['email_key'])) ?>"><?= $h((string) ($row['label'] ?? '')) ?></a>
                                                <?php if ((string) ($row['sub'] ?? '') !== '' && (string) $row['sub'] !== (string) $row['label']): ?>
                                                    <span class="pu-muted"><?= $h((string) $row['sub']) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?= $h((string) ($row['label'] ?? '')) ?> <span class="pa-pill pa-pill--slate" title="Ce joueur n’est relié à aucun compte du site.">non relié</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?= $h((string) ($row['name'] ?? '')) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pa-sheet__num"><strong><?= $h($bytes((int) ($row['total'] ?? 0))) ?></strong></td>
                                    <?php foreach ($days as $d): ?>
                                        <?php $v = (int) ($cells[$d] ?? 0); ?>
                                        <td class="pa-sheet__num<?= $v === 0 ? ' is-zero' : ($v === $peak ? ' is-peak' : '') ?>"><?= $v === 0 ? '·' : $h($bytes($v)) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="pu-data-note">Un point (·) signifie qu’aucune donnée n’a été reçue ce jour-là. Le jour le plus chargé de chaque ligne est en vert.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
