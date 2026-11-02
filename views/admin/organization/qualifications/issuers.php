<?php
declare(strict_types=1);

/**
 * Référentiels > Qualifications > Émetteurs : qui délivre les qualifications (école, centre de formation, unité,
 * instructeur, organisme externe). Bande de chiffres, explication, liste en cartes filtrable, création guidée.
 * Kit : views/admin/effectifs_workspace/partials/effk.php + effectifs-kit.css ; styles propres : qualification-issuers.css.
 *
 * @var list<array<string, mixed>> $issuers
 * @var array<int, string> $logoUrls
 * @var array<int, array{total: int, recent: int, definitions: int, last_at: string}> $stats
 * @var array<int, array<int, array<string, mixed>>> $definitionsByIssuer
 * @var array<int, list<array<string, mixed>>> $authorizations
 * @var list<array<string, mixed>> $definitions
 * @var list<array{id: int, name: string}> $units
 */
require_once base_path('views/admin/effectifs_workspace/partials/effk.php');

$issuers = is_array($issuers ?? null) ? $issuers : [];
$logoUrls = is_array($logoUrls ?? null) ? $logoUrls : [];
$stats = is_array($stats ?? null) ? $stats : [];
$definitionsByIssuer = is_array($definitionsByIssuer ?? null) ? $definitionsByIssuer : [];
$authorizations = is_array($authorizations ?? null) ? $authorizations : [];
$definitions = is_array($definitions ?? null) ? $definitions : [];
$units = is_array($units ?? null) ? $units : [];
$enriched = !empty($enriched);
$linkReady = !empty($linkReady);
$realIssuers = is_array($realIssuers ?? null) ? $realIssuers : [];
$realMatched = is_array($realMatched ?? null) ? $realMatched : [];
$realReady = !empty($realReady);
$realQuota = is_array($realQuota ?? null) ? $realQuota : null;
$realFull = $realQuota !== null && !$realQuota['can_add'];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$base = 'back-office/referentiels/qualifications/emetteurs';
$kindLabels = [
    'school' => 'École',
    'training_center' => 'Centre de formation',
    'unit' => 'Unité',
    'instructor' => 'Instructeur',
    'external' => 'Organisme externe',
];

$active = 0;
$archived = 0;
$attached = 0;
$recent = 0;
$total = 0;
$habilites = 0;
foreach ($issuers as $i) {
    $iid = (int) $i['id'];
    if (!empty($i['archived_at'])) {
        $archived++;
    } else {
        $active++;
    }
    foreach ($definitionsByIssuer[$iid] ?? [] as $d) {
        if (!empty($d['attached'])) {
            $attached++;
        }
    }
    $recent += (int) ($stats[$iid]['recent'] ?? 0);
    $total += (int) ($stats[$iid]['total'] ?? 0);
    $habilites += count($authorizations[$iid] ?? []);
}
$withoutIssuer = 0;
if ($linkReady) {
    foreach ($definitions as $d) {
        if ((int) ($d['default_issuer_id'] ?? 0) <= 0) {
            $withoutIssuer++;
        }
    }
}
$who = static function (array $a): string {
    $cs = trim((string) ($a['callsign'] ?? ''));
    $dn = trim((string) ($a['display_name'] ?? ''));

    return $cs !== '' ? $cs : ($dn !== '' ? $dn : '#' . (int) ($a['user_id'] ?? 0));
};
$headActions = '<a class="effk-btn" href="' . effk_e(url('back-office/referentiels/qualifications')) . '">' . effk_e('Qualifications') . '</a>'
    . '<a class="effk-btn" href="#emetteurs-reels">' . effk_e('Importer les émetteurs réels') . '</a>'
    . '<a class="effk-btn is-dark" href="#nouvel-emetteur">' . effk_icon('plus') . '<span>' . effk_e('Nouvel émetteur') . '</span></a>';
$emptyAction = '<p class="qis-empty-act"><a class="effk-btn" href="#emetteurs-reels">' . effk_e('Importer les émetteurs réels') . '</a> <a class="effk-btn is-dark" href="#nouvel-emetteur">' . effk_icon('plus') . '<span>' . effk_e('Créer le premier émetteur') . '</span></a></p>';
?>
<div class="bo-eff-workspace qis-page">
<div class="effk" data-qis>
    <a class="qis-back" href="<?= effk_e(url('back-office/referentiels/qualifications')) ?>">← Référentiel des qualifications</a>

    <?= effk_page_head(
        'Référentiels · Qualifications',
        'Émetteurs',
        'Au nom de qui une qualification est délivrée : l’école, le centre de formation, l’unité ou l’instructeur qui forme, valide et signe le brevet.',
        $headActions
    ) ?>

    <?php if ($flashSuccess): ?><p class="effk-note is-info" role="status"><?= effk_e((string) $flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="effk-note is-warn" role="alert"><?= effk_e((string) $flashError) ?></p><?php endif; ?>
    <?php if (!$enriched): ?>
        <p class="effk-note is-warn">La présentation, le signataire du brevet, le logo, l’archivage, les qualifications rattachées et les habilités arrivent avec la prochaine mise à jour du site. En attendant, vous pouvez créer et lister les émetteurs.</p>
    <?php endif; ?>

    <?= effk_stats([
        ['label' => 'Émetteurs actifs', 'value' => $active, 'hint' => $archived > 0 ? $archived . ' archivé(s)' : 'Aucun archivé'],
        ['label' => 'Qualifications rattachées', 'value' => $attached, 'hint' => $linkReady ? ($withoutIssuer > 0 ? $withoutIssuer . ' sans émetteur habituel' : 'Toutes ont un émetteur') : 'Avec la mise à jour', 'tone' => $linkReady && $withoutIssuer > 0 ? 'warn' : ''],
        ['label' => 'Délivrances sur 90 jours', 'value' => $recent, 'hint' => $total . ' depuis le début', 'tone' => $recent > 0 ? 'ok' : ''],
        ['label' => 'Habilités', 'value' => $habilites, 'hint' => 'Instructeurs et signataires'],
    ], 'Chiffres des émetteurs') ?>

    <details class="effk-panel effk-fold qis-explain"<?= $issuers === [] ? ' open' : '' ?>>
        <summary>
            <span><b>Qu’est-ce qu’un émetteur ?</b><small>À quoi il sert, exemples, lien avec les qualifications et droits de délivrance.</small></span>
            <?= effk_icon('down') ?>
        </summary>
        <div class="effk-fold__body">
            <p class="effk-fold__lead">L’émetteur est l’autorité au nom de laquelle une qualification est délivrée : la qualification dit ce que le membre sait faire, l’émetteur dit qui l’a formé et validé.</p>
            <div class="qis-explain__grid">
                <div>
                    <h3>À quoi il sert</h3>
                    <ul>
                        <li><b>Signature du brevet :</b> son nom, et celui du signataire s’il est renseigné, figurent sur le brevet PDF et sur la fiche du membre.</li>
                        <li><b>Crédibilité :</b> un brevet vaut par l’école qui le délivre.</li>
                        <li><b>Filtrage :</b> retrouvez les qualifications et les brevets délivrés par un émetteur.</li>
                        <li><b>Qui peut délivrer :</b> la fiche liste les instructeurs et signataires habilités.</li>
                    </ul>
                </div>
                <div>
                    <h3>Exemples</h3>
                    <ul>
                        <li><b translate="no">Centre d’instruction CAS</b> — centre de formation : stages de secourisme au combat.</li>
                        <li><b translate="no">160th SOAR Flight School</b> — école : cursus pilote complet.</li>
                        <li><b translate="no">Instructeur JTAC</b> — instructeur : valide les contrôleurs aériens avancés.</li>
                    </ul>
                </div>
                <div>
                    <h3>Lien avec les qualifications</h3>
                    <ul>
                        <li><b>À la main :</b> l’émetteur habituel de la qualification est proposé d’office, modifiable.</li>
                        <li><b>Academy et automatique :</b> l’émetteur habituel signe le brevet sans intervention.</li>
                        <li>Sans émetteur, le brevet porte le nom de la communauté.</li>
                    </ul>
                </div>
                <div>
                    <h3>Droits de délivrance</h3>
                    <ul>
                        <li>Délivrer et retirer une qualification reste réservé aux responsables de la communauté, comme aujourd’hui.</li>
                        <li>Les habilités désignent les instructeurs et signataires de l’émetteur : ils apparaissent sur sa fiche, sans droit supplémentaire sur le site.</li>
                        <li>Archiver un émetteur ne retire rien : les brevets déjà délivrés restent à son nom.</li>
                    </ul>
                </div>
            </div>
        </div>
    </details>

    <?php if ($issuers !== []): ?>
    <div class="effk-bar" role="search">
        <label class="effk-search effk-search--wide">
            <?= effk_icon('search') ?>
            <span class="sr-only">Rechercher un émetteur</span>
            <input type="search" placeholder="Nom, unité, qualification…" data-qis-q>
        </label>
        <label>
            <span class="sr-only">Type d’émetteur</span>
            <select data-qis-kind>
                <option value="">Tous les types</option>
                <?php foreach ($kindLabels as $k => $l): ?><option value="<?= effk_e($k) ?>"><?= effk_e($l) ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php if ($archived > 0): ?>
        <label>
            <span class="sr-only">Statut</span>
            <select data-qis-status>
                <option value="active">Actifs</option>
                <option value="archived">Archivés</option>
                <option value="">Tous</option>
            </select>
        </label>
        <?php endif; ?>
    </div>
    <p class="effk-count" aria-live="polite"><b data-qis-count><?= $active ?></b> <span>émetteur(s) affiché(s)</span></p>

    <ul class="qis-cards" role="list">
        <?php foreach ($issuers as $i):
            $iid = (int) $i['id'];
            $kind = \App\Repositories\QualificationIssuerRepository::normalizeKind((string) ($i['issuer_kind'] ?? 'unit'));
            $isArch = !empty($i['archived_at']);
            $st = $stats[$iid] ?? ['total' => 0, 'recent' => 0, 'definitions' => 0, 'last_at' => ''];
            $defs = array_values($definitionsByIssuer[$iid] ?? []);
            $auths = $authorizations[$iid] ?? [];
            $short = trim((string) ($i['short_name'] ?? ''));
            $search = implode(' ', array_merge(
                [(string) $i['name'], $short, (string) ($i['unit_name'] ?? ''), (string) ($i['parent_name'] ?? ''), $kindLabels[$kind]],
                array_map(static fn (array $d): string => (string) $d['name'], $defs)
            ));
            ?>
        <li class="qis-card<?= $isArch ? ' is-archived' : '' ?>" data-qis-row data-kind="<?= effk_e($kind) ?>" data-status="<?= $isArch ? 'archived' : 'active' ?>" data-search="<?= effk_e(mb_strtolower($search, 'UTF-8')) ?>"<?= $isArch ? ' hidden' : '' ?>>
            <div class="qis-card__head">
                <span class="qis-logo" style="--c:<?= effk_e(effk_unit_color($iid)) ?>">
                    <?php if (isset($logoUrls[$iid])): ?><img src="<?= effk_e($logoUrls[$iid]) ?>" alt=""><?php else: ?><span translate="no"><?= effk_e(effk_initials($short !== '' ? $short : (string) $i['name'])) ?></span><?php endif; ?>
                </span>
                <div class="qis-card__id">
                    <h3><a href="<?= effk_e(url($base . '/' . $iid)) ?>" translate="no"><?= effk_e((string) $i['name']) ?></a></h3>
                    <div class="qis-card__meta">
                        <span class="effk-tag"><?= effk_e($kindLabels[$kind]) ?></span>
                        <?php if ($short !== ''): ?><span class="effk-tag" translate="no"><?= effk_e($short) ?></span><?php endif; ?>
                        <?php if ($isArch): ?><?= effk_pill('Archivé', 'warn') ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <dl class="qis-card__facts">
                <div><dt>Unité</dt><dd><?= trim((string) ($i['unit_name'] ?? '')) !== '' ? '<span translate="no">' . effk_e((string) $i['unit_name']) . '</span>' : '<span class="effk-muted">—</span>' ?></dd></div>
                <?php if (trim((string) ($i['parent_name'] ?? '')) !== ''): ?><div><dt>Dépend de</dt><dd translate="no"><?= effk_e((string) $i['parent_name']) ?></dd></div><?php endif; ?>
                <div><dt>Délivrances</dt><dd><b><?= (int) $st['total'] ?></b> <span class="effk-muted">dont <?= (int) $st['recent'] ?> sur 90 jours</span></dd></div>
                <?php if ($st['last_at'] !== ''): ?><div><dt>Dernière</dt><dd><?= effk_e(effk_short_date((string) $st['last_at'])) ?></dd></div><?php endif; ?>
            </dl>
            <div class="qis-card__block">
                <span class="effk-k">Qualifications</span>
                <?php if ($defs === []): ?>
                    <p class="effk-muted qis-card__none">Aucune pour l’instant.</p>
                <?php else: ?>
                <ul class="qis-chips">
                    <?php foreach (array_slice($defs, 0, 4) as $d): ?><li translate="no"><?= effk_e((string) $d['name']) ?></li><?php endforeach; ?>
                    <?php if (count($defs) > 4): ?><li class="is-more">+<?= count($defs) - 4 ?></li><?php endif; ?>
                </ul>
                <?php endif; ?>
            </div>
            <div class="qis-card__block">
                <span class="effk-k">Habilités</span>
                <?php if ($auths === []): ?>
                    <p class="effk-muted qis-card__none">Personne de désigné.</p>
                <?php else: ?>
                <div class="qis-avs">
                    <?php foreach (array_slice($auths, 0, 5) as $a):
                        if ((int) ($a['user_id'] ?? 0) > 0): ?>
                        <span title="<?= effk_e($who($a)) ?>" translate="no"><?= effk_avatar((int) $a['user_id'], trim((string) ($a['display_name'] ?? '')) ?: $who($a), 'sm') ?></span>
                        <?php else: ?>
                        <span class="effk-tag" translate="no"><?= effk_e((string) ($a['job_role_name'] ?? '')) ?></span>
                        <?php endif;
                    endforeach; ?>
                    <small class="effk-muted"><?= count($auths) ?> habilité(s)</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="qis-card__foot">
                <a class="effk-btn is-sm" href="<?= effk_e(url($base . '/' . $iid)) ?>"><span>Ouvrir la fiche</span><?= effk_icon('arrow') ?></a>
                <?php if ($defs !== []): ?><a class="effk-btn is-sm" href="<?= effk_e(url('back-office/referentiels/qualifications') . '?emetteur=' . $iid) ?>">Ses qualifications</a><?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <div data-qis-none<?= $active > 0 ? ' hidden' : '' ?>><?= effk_empty('Aucun émetteur ne correspond.', 'Modifiez la recherche ou les filtres.') ?></div>
    <?php else: ?>
        <?= effk_empty(
            'Aucun émetteur pour l’instant.',
            'Commencez par l’école ou le centre qui forme le plus, puis rattachez-lui ses qualifications.',
            $emptyAction
        ) ?>
    <?php endif; ?>

    <?php if ($realIssuers !== []):
        $realTotal = 0;
        $realPresent = 0;
        foreach ($realIssuers as $rg) {
            foreach ($rg['issuers'] as $ri) {
                $realTotal++;
                $realPresent += isset($realMatched[$ri['key']]) ? 1 : 0;
            }
        }
        ?>
    <details class="effk-panel effk-fold qis-real" id="emetteurs-reels"<?= $issuers === [] ? ' open' : '' ?>>
        <summary>
            <span><b>Importer les émetteurs réels</b><small><?= $realTotal ?> écoles, centres d’excellence et commandements réels (OTAN, États-Unis, France, Allemagne…) · <?= $realPresent ?> déjà dans votre référentiel.</small></span>
            <?= effk_icon('down') ?>
        </summary>
        <form class="effk-fold__body" method="post" action="<?= effk_e(url($base . '/importer-reels')) ?>" data-qis-real>
            <?= \App\Core\Csrf::field() ?>
            <p class="effk-fold__lead">Cochez les organismes à ajouter : ceux qui manquent sont créés avec leur nom officiel, leur sigle, leur type et une présentation (localisation, site officiel, sources). Un émetteur déjà présent chez vous (même nom, ancien nom ou sigle) n’est jamais modifié. Ce sont des émetteurs de jeu inspirés d’organismes réels, sans valeur réelle. Importer une qualification du catalogue de référence ajoute aussi son émetteur réel.</p>
            <?php if ($realQuota !== null && !$realQuota['unlimited']): ?>
            <p class="effk-note <?= $realFull ? 'is-warn' : 'is-info' ?> qis-real__quota"><b><?= (int) $realQuota['used'] ?> / <?= (int) $realQuota['limit'] ?></b> émetteurs réels sur votre formule<?= $realFull ? ' : plafond atteint, les émetteurs déjà présents restent utilisables' : ' (' . (int) $realQuota['remaining'] . ' restant(s))' ?>.<?php if ($realQuota['offer'] !== null): ?> Formule <b translate="no"><?= effk_e((string) $realQuota['offer']['name']) ?></b> : <?= $realQuota['offer']['value'] === 0 ? 'illimité' : (int) $realQuota['offer']['value'] ?>. <a href="<?= effk_e((string) $realQuota['upgrade_url']) ?>">Voir les formules</a><?php endif; ?> L’émetteur d’une qualification importée du catalogue suit toujours sa qualification.</p>
            <?php endif; ?>
            <?php if (!$realReady): ?><p class="effk-note is-warn">Les émetteurs ne sont pas encore disponibles pour votre communauté : l’import sera possible après la prochaine mise à jour du site.</p><?php endif; ?>
            <div class="qis-real__countries">
            <?php foreach ($realIssuers as $rg):
                $rgMissing = count(array_filter($rg['issuers'], static fn (array $ri): bool => !isset($realMatched[$ri['key']]))); ?>
                <fieldset class="qis-real__country" data-qis-real-group>
                    <legend>
                        <?= \App\Support\QualificationCatalog\CatalogCountry::flag((string) $rg['country']) ?>
                        <b><?= effk_e((string) $rg['label']) ?></b>
                        <?php $native = \App\Support\QualificationCatalog\CatalogCountry::native((string) $rg['country']); if ($native !== '' && $native !== $rg['label']): ?><span class="effk-muted" translate="no"><?= effk_e($native) ?></span><?php endif; ?>
                        <span class="effk-tag"><?= count($rg['issuers']) ?></span>
                    </legend>
                    <?php if ($rgMissing > 0 && !$realFull): ?>
                    <label class="qis-real__all"><input type="checkbox" data-qis-real-all> <span>Tout cocher (<?= $rgMissing ?> à importer)</span></label>
                    <?php else: ?>
                    <p class="effk-muted qis-real__done">Tous déjà dans votre référentiel.</p>
                    <?php endif; ?>
                    <ul class="qis-real__list" role="list">
                    <?php foreach ($rg['issuers'] as $ri):
                        $present = isset($realMatched[$ri['key']]); ?>
                        <li class="<?= $present ? 'is-present' : '' ?>">
                            <label>
                                <input type="checkbox" name="keys[]" value="<?= effk_e((string) $ri['key']) ?>"<?= $present ? ' checked disabled' : ($realFull ? ' disabled' : '') ?>>
                                <span class="qis-real__id">
                                    <b translate="no"><?= effk_e((string) $ri['short_name']) ?></b>
                                    <span translate="no"><?= effk_e((string) $ri['name']) ?></span>
                                    <?php if ($ri['transliteration'] !== ''): ?><small translate="no"><?= effk_e((string) $ri['transliteration']) ?></small><?php endif; ?>
                                    <small><span><?= effk_e((string) $ri['type_label']) ?></span><?php if ($ri['location'] !== ''): ?> · <span translate="no"><?= effk_e((string) $ri['location']) ?></span><?php endif; ?></small>
                                </span>
                            </label>
                            <?php if ($present): ?>
                                <a class="effk-pill is-ok" href="<?= effk_e(url($base . '/' . (int) $realMatched[$ri['key']])) ?>">Déjà présent</a>
                            <?php elseif ($ri['website'] !== ''): ?>
                                <a class="qis-real__site" href="<?= effk_e((string) $ri['website']) ?>" target="_blank" rel="noopener noreferrer">Site officiel</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </fieldset>
            <?php endforeach; ?>
            </div>
            <div class="effk-fold__foot">
                <span>Les émetteurs créés se modifient ensuite librement (logo, signataire, habilités).</span>
                <button type="submit" class="effk-btn is-dark"<?= $realReady && !$realFull ? '' : ' disabled' ?>><?= effk_icon('check') ?><span>Importer les émetteurs cochés</span></button>
            </div>
        </form>
    </details>
    <?php endif; ?>

    <details class="effk-panel effk-fold" id="nouvel-emetteur"<?= $issuers === [] ? ' open' : '' ?>>
        <summary>
            <span><b>Nouvel émetteur</b><small>Identité, rattachement, signature du brevet, qualifications délivrées.</small></span>
            <?= effk_icon('down') ?>
        </summary>
        <form class="effk-fold__body qis-form" method="post" action="<?= effk_e(url($base)) ?>" enctype="multipart/form-data">
            <?= \App\Core\Csrf::field() ?>
            <?php
            $iss = null;
            $fid = 'qi-new';
            $linked = [];
            require base_path('views/admin/organization/qualifications/_issuer_fields.php');
            ?>
            <div class="effk-fold__foot">
                <span>Vous pourrez ensuite désigner les habilités depuis la fiche.</span>
                <button type="submit" class="effk-btn is-dark"><?= effk_icon('check') ?><span>Créer l’émetteur</span></button>
            </div>
        </form>
    </details>
</div>
</div>
<script src="<?= effk_e(asset_url('assets/js/qualification-issuers.js')) ?>" defer></script>
