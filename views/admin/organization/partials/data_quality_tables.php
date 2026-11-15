<?php
declare(strict_types=1);

/**
 * Qualité des données : tableaux par personne, par unité, vérifications d’anomalies et remplissage par champ.
 * Inclus par views/admin/organization/data_quality.php (variables $h, $icon, $fmtDate, $tables).
 * Tri, filtre et pages se font côté serveur par des paramètres d’adresse (pe_, un_, ch_, an_, da_) : aucun script requis.
 *
 * @var array<string, mixed> $tables DataQualityTables::build
 */

use App\Services\DataQuality\DataQualityCatalog;

$tables = is_array($tables ?? null) ? $tables : [];
$dqReady = !empty($tables['ready']);
$dqBase = url('back-office/organisation/qualite-donnees');
/** @var array<string, string> $dqQuery */
$dqQuery = is_array($tables['query'] ?? null) ? $tables['query'] : [];

/** Adresse de la page avec des paramètres changés (null : retiré) et un ancrage. */
$dqUrl = static function (array $set, string $anchor) use ($dqBase, $dqQuery): string {
    $q = $set + $dqQuery;
    $q = array_filter($q, static fn ($v): bool => $v !== null && $v !== '');

    return $dqBase . ($q !== [] ? '?' . http_build_query($q) : '') . '#dq-' . $anchor;
};
$dqBar = static function (int $percent) use ($h): string {
    $tone = $percent >= 90 ? 'ok' : ($percent >= 60 ? 'warn' : 'danger');

    return '<span class="bo-dq-rate"><span class="bo-dq-meter bo-dq-meter--' . $tone . '" aria-hidden="true"><span style="width: ' . max(0, min(100, $percent)) . '%"></span></span><span class="bo-dq-rate__value">' . $h($percent) . ' %</span></span>';
};
$dqSort = static function (array $t, string $col, string $label, string $anchor) use ($dqUrl, $h): string {
    $active = $t['sort'] === $col;
    $next = $active && $t['dir'] === 'asc' ? 'desc' : 'asc';
    $arrow = $active ? ($t['dir'] === 'asc' ? '↑' : '↓') : '';
    $aria = $active ? ($t['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    $p = $t['prefix'];

    return '<th scope="col" aria-sort="' . $aria . '"><a class="bo-dq-sort" href="' . $h($dqUrl([$p . '_s' => $col, $p . '_d' => $next, $p . '_p' => null], $anchor)) . '">' . $h($label) . ' <span class="bo-dq-sort__arrow" aria-hidden="true">' . $arrow . '</span></a></th>';
};
$dqPager = static function (array $t, string $anchor) use ($dqUrl, $h): void {
    if ($t['pages'] < 2) {
        return;
    }
    $p = $t['prefix'];
    echo '<nav class="bo-dq-pager" aria-label="Pages du tableau">';
    if ($t['page'] > 1) {
        echo '<a class="ath-btn" href="' . $h($dqUrl([$p . '_p' => $t['page'] - 1], $anchor)) . '">Page précédente</a>';
    }
    echo '<span class="bo-dq-pager__pos">' . $h($t['page']) . ' / ' . $h($t['pages']) . '</span>';
    if ($t['page'] < $t['pages']) {
        echo '<a class="ath-btn" href="' . $h($dqUrl([$p . '_p' => $t['page'] + 1], $anchor)) . '">Page suivante</a>';
    }
    echo '</nav>';
};
/** Barre de recherche et de filtre d’un tableau (formulaire GET qui garde l’état des autres tableaux). */
$dqToolbar = static function (array $t, string $anchor, string $placeholder, array $options, string $filterLabel) use ($dqQuery, $dqBase, $dqUrl, $h, $icon): void {
    $p = $t['prefix'];
    echo '<form class="bo-dq-filters" method="get" action="' . $h($dqBase) . '#dq-' . $h($anchor) . '">';
    foreach ($dqQuery as $k => $v) {
        if (!in_array($k, [$p . '_q', $p . '_f', $p . '_p'], true) && !($p === 'an' && in_array($k, ['an_u', 'an_v'], true))) {
            echo '<input type="hidden" name="' . $h($k) . '" value="' . $h($v) . '">';
        }
    }
    if ($p === 'an') {
        foreach (['an_u', 'an_v'] as $k) {
            if (($dqQuery[$k] ?? '') !== '') {
                echo '<input type="hidden" name="' . $h($k) . '" value="' . $h($dqQuery[$k]) . '">';
            }
        }
    }
    if ($options !== []) {
        echo '<label class="bo-dq-select"><span class="bo-dq-sr">' . $h($filterLabel) . '</span><select name="' . $h($p) . '_f">';
        foreach ($options as $value => $label) {
            echo '<option value="' . $h($value) . '"' . ($t['filter'] === (string) $value ? ' selected' : '') . '>' . $h($label) . '</option>';
        }
        echo '</select></label>';
    }
    echo '<label class="bo-dq-search">' . $icon('search') . '<span class="bo-dq-sr">Rechercher dans le tableau</span>'
        . '<input type="search" class="bo-dq-search__input" name="' . $h($p) . '_q" value="' . $h($t['q']) . '" placeholder="' . $h($placeholder) . '" autocomplete="off"></label>';
    echo '<button type="submit" class="ath-btn">Filtrer</button>';
    if ($t['q'] !== '' || $t['filter'] !== '') {
        ?><a class="ath-btn" href="<?= $h($dqUrl([$p . '_q' => null, $p . '_f' => null, $p . '_p' => null], $anchor)) ?>">Réinitialiser</a><?php
    }
    echo '</form>';
};

$dqCampaignLabels = ['confirmed' => 'Confirmée', 'flagged' => 'Signalée', 'none' => 'Sans réponse', 'out' => 'Hors campagne'];
$dqCampaignTone = ['confirmed' => 'ok', 'flagged' => 'high', 'none' => 'medium', 'out' => 'neutral'];
$dqSevTone = static fn (int $rank): string => $rank <= 0 ? 'high' : ($rank === 1 ? 'medium' : 'low');
$dqMember = static fn (int $id, string $name): string => $name !== '' ? $name : 'Membre n° ' . $id;
$dqWhen = static fn (string $raw): string => $raw === '' ? '—' : $fmtDate($raw, false);
?>

<?php if (!$dqReady): ?>
<?php foreach (['personnes' => 'Personnes', 'unites' => 'Unités', 'verifications' => 'Vérifications', 'champs' => 'Données'] as $dqId => $dqTitle): ?>
<section class="bo-dq-section" id="dq-<?= $h($dqId) ?>" data-dq-section="<?= $h($dqId) ?>">
    <header class="bo-dq-section__head">
        <span class="bo-dq-section__icon"><?= $icon('alert') ?></span>
        <div>
            <h2 class="bo-dq-section__title"><?= $h($dqTitle) ?></h2>
            <p class="bo-dq-section__lead">Ce tableau n’a pas pu être préparé pour le moment. Réessayez dans un instant.</p>
        </div>
    </header>
</section>
<?php endforeach; ?>
<?php return; endif; ?>

<?php
$dqPeople = $tables['people'];
$dqUnits = $tables['units'];
$dqChecks = $tables['checks'];
$dqFindings = $tables['findings'];
$dqFields = $tables['fields'];
$dqCampaign = is_array($tables['campaign'] ?? null) ? $tables['campaign'] : null;
?>

<!-- Personnes -->
<section class="bo-dq-section" id="dq-personnes" data-dq-section="personnes">
    <header class="bo-dq-section__head">
        <span class="bo-dq-section__icon"><?= $icon('users') ?></span>
        <div>
            <h2 class="bo-dq-section__title">Fiches par personne</h2>
            <p class="bo-dq-section__lead">Taux de remplissage de chaque fiche, date de dernière mise à jour et de dernière confirmation, statut dans la campagne de fiabilisation et nombre d’anomalies.</p>
        </div>
    </header>
    <?php if (!empty($tables['truncated'])): ?>
    <p class="bo-dq-section__lead">Les 5 000 premiers membres seulement sont analysés.</p>
    <?php endif; ?>
    <?php if ($dqCampaign !== null): ?>
    <p class="bo-dq-section__lead">Campagne suivie : <strong><?= $h($dqCampaign['title']) ?></strong> <a class="bo-dq-link" href="<?= $h(url('back-office/organisation/fiabilisation/' . $dqCampaign['id'])) ?>">Ouvrir le suivi</a></p>
    <?php else: ?>
    <p class="bo-dq-section__lead">Aucune campagne de fiabilisation n’a encore été ouverte : le statut indique « Hors campagne ».</p>
    <?php endif; ?>

    <?php $dqToolbar($dqPeople, 'personnes', 'Nom ou unité…', [
        '' => 'Toutes les fiches', 'incomplete' => 'Fiches incomplètes', 'complete' => 'Fiches complètes', 'stale' => 'Fiches anciennes',
        'anomaly' => 'Avec anomalies', 'confirmed' => 'Campagne : confirmées', 'flagged' => 'Campagne : signalées', 'none' => 'Campagne : sans réponse', 'out' => 'Hors campagne',
    ], 'Filtrer les fiches'); ?>

    <?php if ($dqPeople['rows'] === []): ?>
    <div class="bo-dq-empty bo-dq-empty--quiet"><p class="bo-dq-empty__text">Aucune fiche ne correspond à ces filtres.</p></div>
    <?php else: ?>
    <div class="bo-dq-table-wrap">
        <table class="bo-dq-table">
            <thead>
                <tr>
                    <?= $dqSort($dqPeople, 'name', 'Membre', 'personnes') ?>
                    <?= $dqSort($dqPeople, 'unit', 'Unité', 'personnes') ?>
                    <?= $dqSort($dqPeople, 'percent', 'Fiche remplie', 'personnes') ?>
                    <th scope="col">À compléter</th>
                    <?= $dqSort($dqPeople, 'updated_at', 'Mise à jour', 'personnes') ?>
                    <?= $dqSort($dqPeople, 'confirmed_at', 'Confirmation', 'personnes') ?>
                    <?= $dqSort($dqPeople, 'campaign', 'Campagne', 'personnes') ?>
                    <?= $dqSort($dqPeople, 'anomalies', 'Anomalies', 'personnes') ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dqPeople['rows'] as $r): ?>
                <tr>
                    <td><a class="bo-dq-person" translate="no" href="<?= $h(effectifs_workspace_url('membres/' . $r['id'])) ?>"><?= $h($dqMember($r['id'], $r['name'])) ?></a></td>
                    <td><?= $r['unit'] !== '' ? $h($r['unit']) : '<span class="bo-dq-muted">Sans unité</span>' ?></td>
                    <td><?= $dqBar($r['percent']) ?></td>
                    <td>
                        <?php if ($r['missing_blocks'] === []): ?>
                        <span class="bo-dq-muted">—</span>
                        <?php else: ?>
                        <?php foreach ($r['missing_blocks'] as $b): ?><span class="bo-dq-chip"><?= $h(DataQualityCatalog::BLOCKS[$b] ?? $b) ?></span> <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td class="bo-dq-table__date"><?= $h($dqWhen($r['updated_at'])) ?><?= $r['stale'] ? ' <span class="bo-dq-pill bo-dq-pill--medium">Ancienne</span>' : '' ?></td>
                    <td class="bo-dq-table__date"><?= $h($dqWhen($r['confirmed_at'])) ?></td>
                    <td><span class="bo-dq-pill bo-dq-pill--<?= $h($dqCampaignTone[$r['campaign']] ?? 'neutral') ?>"><?= $h($dqCampaignLabels[$r['campaign']] ?? '') ?></span><?= $r['open_flags'] > 0 ? ' <span class="bo-dq-muted">' . $h($r['open_flags']) . ' ouvert(s)</span>' : '' ?></td>
                    <td>
                        <?php if ($r['anomalies'] > 0): ?>
                        <a class="bo-dq-pill bo-dq-pill--<?= $h($dqSevTone($r['worst'])) ?>" href="<?= $h($dqUrl(['an_u' => (string) $r['id'], 'an_p' => null], 'verifications')) ?>"><?= $h($r['anomalies']) ?></a>
                        <?php else: ?>
                        <span class="bo-dq-muted">0</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="bo-dq-section__lead"><?= $h($dqPeople['total']) ?> fiche(s) sur <?= $h($dqPeople['all']) ?>.</p>
    <?php $dqPager($dqPeople, 'personnes'); ?>
    <?php endif; ?>
</section>

<!-- Unités -->
<section class="bo-dq-section" id="dq-unites" data-dq-section="unites">
    <header class="bo-dq-section__head">
        <span class="bo-dq-section__icon"><?= $icon('flag') ?></span>
        <div>
            <h2 class="bo-dq-section__title">Fiches par unité</h2>
            <p class="bo-dq-section__lead">Les mêmes taux regroupés par unité principale : fiches remplies, avancement de la campagne, anomalies et fiches anciennes.</p>
        </div>
    </header>
    <?php $dqToolbar($dqUnits, 'unites', 'Nom de l’unité…', [], ''); ?>
    <?php if ($dqUnits['rows'] === []): ?>
    <div class="bo-dq-empty bo-dq-empty--quiet"><p class="bo-dq-empty__text">Aucune unité ne correspond.</p></div>
    <?php else: ?>
    <div class="bo-dq-table-wrap">
        <table class="bo-dq-table">
            <thead>
                <tr>
                    <?= $dqSort($dqUnits, 'label', 'Unité', 'unites') ?>
                    <?= $dqSort($dqUnits, 'members', 'Membres', 'unites') ?>
                    <?= $dqSort($dqUnits, 'percent', 'Fiches remplies', 'unites') ?>
                    <?= $dqSort($dqUnits, 'confirmed_percent', 'Confirmées', 'unites') ?>
                    <?= $dqSort($dqUnits, 'flagged', 'Signalées', 'unites') ?>
                    <?= $dqSort($dqUnits, 'none', 'Sans réponse', 'unites') ?>
                    <?= $dqSort($dqUnits, 'anomalies', 'Anomalies', 'unites') ?>
                    <?= $dqSort($dqUnits, 'stale', 'Fiches anciennes', 'unites') ?>
                    <?= $dqSort($dqUnits, 'updated_at', 'Dernière mise à jour', 'unites') ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dqUnits['rows'] as $r): ?>
                <tr>
                    <td>
                        <?php if ($r['unit_id'] > 0 && $r['label'] !== ''): ?>
                        <a class="bo-dq-person" href="<?= $h(url('back-office/units/' . $r['unit_id'] . '/edit')) ?>"><?= $h($r['label']) ?></a>
                        <?php else: ?>
                        <span class="bo-dq-muted">Sans unité</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $h($r['members']) ?></td>
                    <td><?= $dqBar($r['percent']) ?></td>
                    <td><?= $r['confirmed_percent'] < 0 ? '<span class="bo-dq-muted">—</span>' : $dqBar($r['confirmed_percent']) ?></td>
                    <td><?= $h($r['flagged']) ?></td>
                    <td><?= $h($r['none']) ?></td>
                    <td><?= $h($r['anomalies']) ?></td>
                    <td><?= $h($r['stale']) ?></td>
                    <td class="bo-dq-table__date"><?= $h($dqWhen($r['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $dqPager($dqUnits, 'unites'); ?>
    <?php endif; ?>
</section>

<!-- Vérifications d’anomalies -->
<section class="bo-dq-section" id="dq-verifications" data-dq-section="verifications">
    <header class="bo-dq-section__head">
        <span class="bo-dq-section__icon"><?= $icon('check') ?></span>
        <div>
            <h2 class="bo-dq-section__title">Vérifications d’anomalies</h2>
            <p class="bo-dq-section__lead">Ce qu’Athena contrôle sur les fiches : doublons, identité, grades et affectations, dates impossibles, comptes sans fiche. Chaque constat renvoie à la correction.</p>
        </div>
    </header>

    <?php
    $dqGroupOptions = ['' => 'Toutes les vérifications', 'hit' => 'À traiter', 'ok' => 'Sans anomalie'];
    foreach (DataQualityCatalog::GROUPS as $gk => $gl) {
        $dqGroupOptions[$gk] = $gl;
    }
    $dqToolbar($dqChecks, 'verifications', 'Vérification…', $dqGroupOptions, 'Filtrer les vérifications');
    ?>
    <div class="bo-dq-table-wrap">
        <table class="bo-dq-table">
            <thead>
                <tr>
                    <?= $dqSort($dqChecks, 'label', 'Vérification', 'verifications') ?>
                    <?= $dqSort($dqChecks, 'group', 'Famille', 'verifications') ?>
                    <?= $dqSort($dqChecks, 'severity', 'Gravité', 'verifications') ?>
                    <?= $dqSort($dqChecks, 'count', 'Constats', 'verifications') ?>
                    <?= $dqSort($dqChecks, 'members', 'Membres', 'verifications') ?>
                    <th scope="col">Détail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dqChecks['rows'] as $r): ?>
                <tr>
                    <td><strong><?= $h($r['label']) ?></strong></td>
                    <td><?= $h(DataQualityCatalog::GROUPS[$r['group']] ?? $r['group']) ?></td>
                    <td><span class="bo-dq-pill bo-dq-pill--<?= $h($r['severity']) ?>"><?= $h(DataQualityCatalog::SEVERITY_LABELS[$r['severity']] ?? '') ?></span></td>
                    <td>
                        <?php if ($r['count'] > 0): ?>
                        <a class="bo-dq-link" href="<?= $h($dqUrl(['an_f' => $r['code'], 'an_u' => null, 'an_v' => null, 'an_p' => null], 'verifications')) ?>"><?= $h($r['count']) ?></a>
                        <?php else: ?>
                        <span class="bo-dq-pill bo-dq-pill--ok">0</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $h($r['members']) ?></td>
                    <td><span class="bo-dq-table__summary"><?= $h($r['help']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <header class="bo-dq-section__head bo-dq-section__head--sub">
        <div>
            <h3 class="bo-dq-section__title">Constats à corriger</h3>
            <p class="bo-dq-section__lead">Un constat par membre et par vérification, du plus grave au moins grave.</p>
        </div>
    </header>
    <?php
    $dqFindingOptions = ['' => 'Toutes les vérifications'];
    foreach (DataQualityCatalog::CHECKS as $code => $c) {
        $dqFindingOptions[$code] = $c['label'];
    }
    $dqToolbar($dqFindings, 'verifications', 'Membre ou vérification…', $dqFindingOptions, 'Filtrer les constats');
    $dqUserFilter = (int) ($dqQuery['an_u'] ?? 0);
    ?>
    <div class="bo-dq-filters">
        <div class="bo-dq-seg" role="group" aria-label="Filtrer par gravité">
            <a class="bo-dq-seg__btn<?= ($dqQuery['an_v'] ?? '') === '' ? ' is-active' : '' ?>" href="<?= $h($dqUrl(['an_v' => null, 'an_p' => null], 'verifications')) ?>">Toutes</a>
            <?php foreach (DataQualityCatalog::SEVERITY_LABELS as $sv => $sl): ?>
            <a class="bo-dq-seg__btn<?= ($dqQuery['an_v'] ?? '') === $sv ? ' is-active' : '' ?>" href="<?= $h($dqUrl(['an_v' => $sv, 'an_p' => null], 'verifications')) ?>"><span class="bo-dq-dot bo-dq-dot--<?= $h($sv) ?>"></span><?= $h($sl) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($dqUserFilter > 0): ?>
        <a class="ath-btn" href="<?= $h($dqUrl(['an_u' => null, 'an_p' => null], 'verifications')) ?>">Retirer le filtre sur le membre n° <?= $h($dqUserFilter) ?></a>
        <?php endif; ?>
    </div>

    <?php if ($dqFindings['rows'] === []): ?>
    <div class="bo-dq-empty">
        <span class="bo-dq-empty__icon"><?= $icon('check') ?></span>
        <p class="bo-dq-empty__title">Rien à signaler</p>
        <p class="bo-dq-empty__text">Aucun constat ne correspond à ces filtres.</p>
    </div>
    <?php else: ?>
    <div class="bo-dq-table-wrap">
        <table class="bo-dq-table">
            <thead>
                <tr>
                    <?= $dqSort($dqFindings, 'severity', 'Gravité', 'verifications') ?>
                    <?= $dqSort($dqFindings, 'label', 'Vérification', 'verifications') ?>
                    <?= $dqSort($dqFindings, 'name', 'Membre', 'verifications') ?>
                    <th scope="col">Autres membres</th>
                    <th scope="col">Correction</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dqFindings['rows'] as $r): ?>
                <tr>
                    <td><span class="bo-dq-pill bo-dq-pill--<?= $h($r['severity']) ?>"><?= $h(DataQualityCatalog::SEVERITY_LABELS[$r['severity']] ?? '') ?></span></td>
                    <td><span class="bo-dq-table__summary"><?= $h($r['label']) ?></span><span class="bo-dq-table__by"><?= $h($r['help']) ?></span></td>
                    <td><a class="bo-dq-person" translate="no" href="<?= $h(effectifs_workspace_url('membres/' . $r['user_id'])) ?>"><?= $h($dqMember($r['user_id'], $r['name'])) ?></a></td>
                    <td>
                        <?php foreach ($r['others'] as $oid): ?>
                        <a class="bo-dq-link" href="<?= $h(effectifs_workspace_url('membres/' . $oid)) ?>">n° <?= $h($oid) ?></a>
                        <?php endforeach; ?>
                        <?= $r['others'] === [] ? '<span class="bo-dq-muted">—</span>' : '' ?>
                    </td>
                    <td><a class="ath-btn" href="<?= $h(url('personnel/' . $r['user_id'] . '/edit')) ?>">Corriger la fiche</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="bo-dq-section__lead"><?= $h($dqFindings['total']) ?> constat(s) sur <?= $h($dqFindings['all']) ?>.</p>
    <?php $dqPager($dqFindings, 'verifications'); ?>
    <?php endif; ?>
</section>

<!-- Données : remplissage par champ -->
<section class="bo-dq-section" id="dq-champs" data-dq-section="champs">
    <header class="bo-dq-section__head">
        <span class="bo-dq-section__icon"><?= $icon('overview') ?></span>
        <div>
            <h2 class="bo-dq-section__title">Remplissage par donnée</h2>
            <p class="bo-dq-section__lead">Pour chaque donnée de la fiche, combien de membres l’ont renseignée, regroupées par bloc : identité, contact, matricules, affectation.</p>
        </div>
    </header>
    <div class="bo-dq-types">
        <?php foreach ($tables['blocks'] as $b): ?>
        <a class="bo-dq-type<?= $b['percent'] < 100 ? ' is-hit' : '' ?>" href="<?= $h($dqUrl(['da_f' => $b['key'], 'da_p' => null], 'champs')) ?>">
            <span class="bo-dq-type__head"><span class="bo-dq-type__value"><?= $h($b['percent']) ?> %</span></span>
            <span class="bo-dq-type__label"><?= $h($b['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php
    $dqBlockOptions = ['' => 'Tous les blocs'] + DataQualityCatalog::BLOCKS;
    $dqToolbar($dqFields, 'champs', 'Donnée…', $dqBlockOptions, 'Filtrer par bloc');
    ?>
    <div class="bo-dq-table-wrap">
        <table class="bo-dq-table">
            <thead>
                <tr>
                    <?= $dqSort($dqFields, 'label', 'Donnée', 'champs') ?>
                    <?= $dqSort($dqFields, 'block', 'Bloc', 'champs') ?>
                    <?= $dqSort($dqFields, 'percent', 'Renseignée', 'champs') ?>
                    <th scope="col">Membres</th>
                    <?= $dqSort($dqFields, 'missing', 'Manquantes', 'champs') ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dqFields['rows'] as $r): ?>
                <tr>
                    <td><strong><?= $h($r['label']) ?></strong></td>
                    <td><?= $h(DataQualityCatalog::BLOCKS[$r['block']] ?? $r['block']) ?></td>
                    <td><?= $dqBar($r['percent']) ?></td>
                    <td><?= $h($r['filled']) ?> / <?= $h($r['total']) ?></td>
                    <td><?= $h($r['missing']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($dqFields['rows'] === []): ?>
    <div class="bo-dq-empty bo-dq-empty--quiet"><p class="bo-dq-empty__text">Aucune donnée ne correspond.</p></div>
    <?php endif; ?>
    <p class="bo-dq-section__lead">Les valeurs ne sont jamais affichées ici, seulement leur présence. Les papiers et identités d’emprunt du jeu ne sont pas analysés.</p>
</section>
