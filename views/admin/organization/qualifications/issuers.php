<?php
declare(strict_types=1);

/**
 * Référentiels > Qualifications > Émetteurs : qui délivre les qualifications (école, centre de formation, unité,
 * instructeur, organisme externe). Bande de chiffres, explication, tableau triable et filtrable (pays, type, origine,
 * statut, recherche, pagination), actions par ligne, et deux assistants pas à pas en fenêtre : « Importer les
 * émetteurs réels » (pays, choix, récapitulatif et plafond de la formule) et « Créer un émetteur » (identité, détails,
 * récapitulatif). Les zones data-qis-region sont rechargées par fetch après une action (qualification-issuers.js).
 * Kit : views/admin/effectifs_workspace/partials/effk.php + effectifs-kit.css ; styles propres : qualification-issuers.css.
 *
 * @var list<array<string, mixed>> $issuers
 * @var array<int, string> $logoUrls
 * @var array<int, array{total: int, recent: int, definitions: int, last_at: string}> $stats
 * @var array<int, array<int, array<string, mixed>>> $definitionsByIssuer
 * @var array<int, list<array<string, mixed>>> $authorizations
 * @var list<array<string, mixed>> $definitions
 * @var list<array{id: int, name: string}> $units
 * @var array<int, array{origin: string, country: string, country_label: string}> $issuerMeta
 * @var array<string, array{fr: string, en: string}> $countries
 */
require_once base_path('views/admin/effectifs_workspace/partials/effk.php');

$issuers = is_array($issuers ?? null) ? $issuers : [];
$logoUrls = is_array($logoUrls ?? null) ? $logoUrls : [];
$stats = is_array($stats ?? null) ? $stats : [];
$definitionsByIssuer = is_array($definitionsByIssuer ?? null) ? $definitionsByIssuer : [];
$authorizations = is_array($authorizations ?? null) ? $authorizations : [];
$definitions = is_array($definitions ?? null) ? $definitions : [];
$units = is_array($units ?? null) ? $units : [];
$issuerMeta = is_array($issuerMeta ?? null) ? $issuerMeta : [];
$countries = is_array($countries ?? null) ? $countries : [];
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
$lang = \App\Support\QualificationCatalog\QualificationCatalog::lang(function_exists('locale') ? (string) locale() : 'fr');
$kindLabels = [
    'school' => 'École',
    'training_center' => 'Centre de formation',
    'unit' => 'Unité',
    'instructor' => 'Instructeur',
    'external' => 'Organisme externe',
];
$kindHints = [
    'school' => 'Un cursus complet, par exemple « 160th SOAR Flight School ».',
    'training_center' => 'Stages, recyclages, examens : « Centre d’instruction CAS ».',
    'unit' => 'Une compagnie, un escadron qui qualifie ses propres membres.',
    'instructor' => 'Une personne ou une cellule habilitée : « Instructeur JTAC ».',
    'external' => 'Une autre communauté ou un partenaire dont vous reconnaissez les brevets.',
];

$active = 0;
$archived = 0;
$attached = 0;
$recent = 0;
$total = 0;
$habilites = 0;
$usedCountries = [];
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
    $c = (string) ($issuerMeta[$iid]['country'] ?? '');
    if ($c !== '') {
        $usedCountries[$c] = (string) $issuerMeta[$iid]['country_label'];
    }
}
$withoutIssuer = 0;
if ($linkReady) {
    foreach ($definitions as $d) {
        if ((int) ($d['default_issuer_id'] ?? 0) <= 0) {
            $withoutIssuer++;
        }
    }
}

// Émetteurs réels : totaux par pays, plafond de la formule.
$realTotal = 0;
$realPresent = 0;
$realTypes = [];
foreach ($realIssuers as $rg) {
    foreach ($rg['issuers'] as $ri) {
        $realTotal++;
        $realPresent += isset($realMatched[$ri['key']]) ? 1 : 0;
        $realTypes[(string) $ri['type']] = (string) $ri['type_label'];
    }
}
$realLimited = $realQuota !== null && !$realQuota['unlimited'];
$realRemaining = $realLimited ? max(0, (int) $realQuota['remaining']) : null;
$canImport = $realReady && !$realFull && $realIssuers !== [];

$openReal = '<button type="button" class="effk-btn" data-qis-open="emetteurs-reels"' . ($realIssuers === [] ? ' hidden' : '') . '>' . effk_e('Importer les émetteurs réels') . '</button>';
$openNew = '<button type="button" class="effk-btn is-dark" data-qis-open="nouvel-emetteur">' . effk_icon('plus') . '<span>' . effk_e('Créer un émetteur') . '</span></button>';
$headActions = '<a class="effk-btn" href="' . effk_e(url('back-office/referentiels/qualifications')) . '">' . effk_e('Qualifications') . '</a>' . $openReal . $openNew;
$spin = '<span class="qis-spin" aria-hidden="true"></span>';
?>
<div class="bo-eff-workspace qis-page">
<div class="effk" data-qis data-qis-url="<?= effk_e(url($base)) ?>">
    <a class="qis-back" href="<?= effk_e(url('back-office/referentiels/qualifications')) ?>">← Référentiel des qualifications</a>

    <?= effk_page_head(
        'Référentiels · Qualifications',
        'Émetteurs',
        'Au nom de qui une qualification est délivrée : l’école, le centre de formation, l’unité ou l’instructeur qui forme, valide et signe le brevet.',
        $headActions
    ) ?>

    <div class="qis-msgs" data-qis-msg aria-live="polite">
        <?php if ($flashSuccess): ?><p class="effk-note is-info" role="status"><?= effk_e((string) $flashSuccess) ?></p><?php endif; ?>
        <?php if ($flashError): ?><p class="effk-note is-warn" role="alert"><?= effk_e((string) $flashError) ?></p><?php endif; ?>
    </div>
    <?php if (!$enriched): ?>
        <p class="effk-note is-warn">La présentation, le signataire du brevet, le logo, l’archivage, les qualifications rattachées et les habilités arrivent avec la prochaine mise à jour du site. En attendant, vous pouvez créer et lister les émetteurs.</p>
    <?php endif; ?>

    <div data-qis-region="stats">
    <?= effk_stats([
        ['label' => 'Émetteurs actifs', 'value' => $active, 'hint' => $archived > 0 ? $archived . ' archivé(s)' : 'Aucun archivé'],
        ['label' => 'Qualifications rattachées', 'value' => $attached, 'hint' => $linkReady ? ($withoutIssuer > 0 ? $withoutIssuer . ' sans émetteur habituel' : 'Toutes ont un émetteur') : 'Avec la mise à jour', 'tone' => $linkReady && $withoutIssuer > 0 ? 'warn' : ''],
        ['label' => 'Délivrances sur 90 jours', 'value' => $recent, 'hint' => $total . ' depuis le début', 'tone' => $recent > 0 ? 'ok' : ''],
        ['label' => 'Habilités', 'value' => $habilites, 'hint' => 'Instructeurs et signataires'],
    ], 'Chiffres des émetteurs') ?>
    </div>

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

    <section class="qis-list" data-qis-region="list" aria-label="Émetteurs de la communauté">
    <?php if ($issuers !== []): ?>
        <div class="effk-bar qis-list__bar" role="search">
            <label class="effk-search effk-search--wide">
                <?= effk_icon('search') ?>
                <span class="sr-only">Rechercher un émetteur</span>
                <input type="search" placeholder="Nom, sigle, unité, qualification…" data-qis-f="q" autocomplete="off">
            </label>
            <label>
                <span class="sr-only">Pays</span>
                <select data-qis-f="pays">
                    <option value="">Tous les pays</option>
                    <?php foreach ($usedCountries as $code => $label): ?><option value="<?= effk_e($code) ?>"><?= effk_e($label) ?></option><?php endforeach; ?>
                    <option value="-">Sans pays</option>
                </select>
            </label>
            <label>
                <span class="sr-only">Type d’émetteur</span>
                <select data-qis-f="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($kindLabels as $k => $l): ?><option value="<?= effk_e($k) ?>"><?= effk_e($l) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>
                <span class="sr-only">Origine</span>
                <select data-qis-f="origine">
                    <option value="">Toutes les origines</option>
                    <option value="catalog">Importés du catalogue réel</option>
                    <option value="custom">Créés par la communauté</option>
                </select>
            </label>
            <label>
                <span class="sr-only">Statut</span>
                <select data-qis-f="statut" data-default="active">
                    <option value="active">Actifs</option>
                    <option value="archived">Archivés (<?= $archived ?>)</option>
                    <option value="">Tous</option>
                </select>
            </label>
            <button type="button" class="effk-btn is-sm" data-qis-reset hidden>Effacer les filtres</button>
        </div>
        <p class="effk-count" aria-live="polite"><b data-qis-count><?= $active ?></b> <span>émetteur(s) affiché(s)</span> <span class="effk-muted">sur <?= count($issuers) ?></span></p>

        <div class="effk-table-wrap qis-tablewrap">
            <table class="effk-table qis-table">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="ascending"><button type="button" class="qis-sort" data-qis-sort="name">Émetteur</button></th>
                        <th scope="col"><button type="button" class="qis-sort" data-qis-sort="country">Pays</button></th>
                        <th scope="col"><button type="button" class="qis-sort" data-qis-sort="kind">Type</button></th>
                        <th scope="col"><button type="button" class="qis-sort" data-qis-sort="origin">Origine</button></th>
                        <th scope="col" class="is-num"><button type="button" class="qis-sort" data-qis-sort="quals" data-qis-desc>Qualifications</button></th>
                        <th scope="col" class="is-num"><button type="button" class="qis-sort" data-qis-sort="awards" data-qis-desc>Délivrances</button></th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody data-qis-rows>
                <?php foreach ($issuers as $i):
                    $iid = (int) $i['id'];
                    $kind = \App\Repositories\QualificationIssuerRepository::normalizeKind((string) ($i['issuer_kind'] ?? 'unit'));
                    $isArch = !empty($i['archived_at']);
                    $st = $stats[$iid] ?? ['total' => 0, 'recent' => 0, 'definitions' => 0, 'last_at' => ''];
                    $defs = array_values($definitionsByIssuer[$iid] ?? []);
                    $meta = $issuerMeta[$iid] ?? ['origin' => 'custom', 'country' => '', 'country_label' => ''];
                    $short = trim((string) ($i['short_name'] ?? ''));
                    $unitName = trim((string) ($i['unit_name'] ?? ''));
                    $search = implode(' ', array_merge(
                        [(string) $i['name'], $short, $unitName, (string) ($i['parent_name'] ?? ''), $kindLabels[$kind], (string) $meta['country_label']],
                        array_map(static fn (array $d): string => (string) $d['name'], $defs)
                    ));
                    $href = url($base . '/' . $iid);
                    ?>
                    <tr class="<?= $isArch ? 'is-archived' : '' ?>" data-qis-row data-id="<?= $iid ?>" data-name="<?= effk_e(mb_strtolower((string) $i['name'], 'UTF-8')) ?>" data-short="<?= effk_e(mb_strtolower($short, 'UTF-8')) ?>" data-country="<?= effk_e((string) $meta['country']) ?>" data-country-label="<?= effk_e(mb_strtolower((string) $meta['country_label'], 'UTF-8')) ?>" data-kind="<?= effk_e($kind) ?>" data-origin="<?= effk_e((string) $meta['origin']) ?>" data-status="<?= $isArch ? 'archived' : 'active' ?>" data-quals="<?= count($defs) ?>" data-awards="<?= (int) $st['total'] ?>" data-search="<?= effk_e(mb_strtolower($search, 'UTF-8')) ?>"<?= $isArch ? ' hidden' : '' ?>>
                        <td>
                            <div class="qis-who">
                                <span class="qis-logo qis-logo--sm" style="--c:<?= effk_e(effk_unit_color($iid)) ?>">
                                    <?php if (isset($logoUrls[$iid])): ?><img src="<?= effk_e($logoUrls[$iid]) ?>" alt="" loading="lazy"><?php else: ?><span translate="no"><?= effk_e(effk_initials($short !== '' ? $short : (string) $i['name'])) ?></span><?php endif; ?>
                                </span>
                                <span class="qis-who__id">
                                    <a href="<?= effk_e($href) ?>" translate="no"><?= effk_e((string) $i['name']) ?></a>
                                    <small><?php if ($short !== ''): ?><span translate="no"><?= effk_e($short) ?></span><?php endif; ?><?php if ($unitName !== ''): ?><?= $short !== '' ? ' · ' : '' ?><span translate="no"><?= effk_e($unitName) ?></span><?php endif; ?><?php if ($isArch): ?> <?= effk_pill('Archivé', 'warn') ?><?php endif; ?></small>
                                </span>
                            </div>
                        </td>
                        <td><?php if ($meta['country'] !== ''): ?><span class="qis-country"><?= \App\Support\QualificationCatalog\CatalogCountry::flag((string) $meta['country']) ?> <span><?= effk_e((string) $meta['country_label']) ?></span></span><?php else: ?><span class="effk-muted">—</span><?php endif; ?></td>
                        <td><span class="effk-tag"><?= effk_e($kindLabels[$kind]) ?></span></td>
                        <td><?= $meta['origin'] === 'catalog' ? '<span class="qis-origin is-catalog">Catalogue réel</span>' : '<span class="qis-origin">Créé</span>' ?></td>
                        <td class="is-num"><?php if ($defs !== []): ?><a href="<?= effk_e(url('back-office/referentiels/qualifications') . '?emetteur=' . $iid) ?>" title="Voir les qualifications liées"><b><?= count($defs) ?></b></a><?php else: ?><span class="effk-muted">0</span><?php endif; ?></td>
                        <td class="is-num"><b><?= (int) $st['total'] ?></b><?php if ($st['last_at'] !== ''): ?><small class="qis-sub">dernière le <?= effk_e(effk_short_date((string) $st['last_at'])) ?></small><?php endif; ?></td>
                        <td class="qis-acts">
                            <a class="effk-btn is-sm" href="<?= effk_e($href . '#modifier') ?>">Modifier</a>
                            <?php if ($defs !== []): ?><a class="effk-btn is-sm" href="<?= effk_e(url('back-office/referentiels/qualifications') . '?emetteur=' . $iid) ?>">Qualifications</a><?php endif; ?>
                            <?php if ($enriched): ?>
                            <form method="post" action="<?= effk_e(url($base . '/' . $iid . ($isArch ? '/reactiver' : '/archiver'))) ?>" data-qis-ajax data-confirm="<?= effk_e($isArch ? 'Réactiver cet émetteur ?' : 'Archiver cet émetteur ? Il ne sera plus proposé pour les nouvelles délivrances ; les brevets déjà délivrés restent à son nom.') ?>">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="effk-btn is-sm"><?= $spin ?><span><?= $isArch ? 'Réactiver' : 'Archiver' ?></span></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="qis-skel" data-qis-skel hidden aria-hidden="true">
                <?php for ($n = 0; $n < 6; $n++): ?><div class="qis-skel__row"><span class="ds-skeleton ds-skeleton--avatar"></span><span class="ds-skeleton ds-skeleton--text"></span><span class="ds-skeleton ds-skeleton--text"></span><span class="ds-skeleton ds-skeleton--text"></span></div><?php endfor; ?>
            </div>
        </div>
        <div data-qis-none hidden>
            <div class="effk-empty"><b>Aucun émetteur ne correspond.</b>Modifiez la recherche ou les filtres.<p class="qis-empty-act"><button type="button" class="effk-btn is-sm" data-qis-reset>Effacer les filtres</button></p></div>
        </div>
        <nav class="qis-pager" data-qis-pager aria-label="Pages des émetteurs" hidden>
            <button type="button" class="effk-btn is-sm" data-qis-page="prev">← Précédente</button>
            <span>Page <b data-qis-page-n>1</b> sur <b data-qis-page-total>1</b></span>
            <button type="button" class="effk-btn is-sm" data-qis-page="next">Suivante →</button>
            <label class="qis-pager__size"><span>Par page</span>
                <select data-qis-f="parpage" data-default="25"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select>
            </label>
        </nav>
    <?php else: ?>
        <div class="effk-empty qis-empty">
            <b>Aucun émetteur pour l’instant.</b>
            <span>Deux façons de commencer : importer des organismes réels (écoles, centres d’excellence, commandements de l’OTAN, des États-Unis, de France…) déjà présentés et sourcés, ou créer l’école ou le centre qui forme le plus chez vous. Rattachez-lui ensuite ses qualifications.</span>
            <p class="qis-empty-act">
                <?php if ($realIssuers !== []): ?><button type="button" class="effk-btn" data-qis-open="emetteurs-reels">Importer les émetteurs réels (<?= $realTotal ?>)</button><?php endif; ?>
                <button type="button" class="effk-btn is-dark" data-qis-open="nouvel-emetteur"><?= effk_icon('plus') ?><span>Créer le premier émetteur</span></button>
            </p>
        </div>
    <?php endif; ?>
    </section>

    <?php if ($realIssuers !== []): ?>
    <dialog class="effk-md qis-wiz" id="emetteurs-reels" aria-labelledby="qis-real-title" data-qis-region="real">
        <form method="post" action="<?= effk_e(url($base . '/importer-reels')) ?>" data-qis-wiz="real" data-remaining="<?= $realRemaining === null ? '' : (int) $realRemaining ?>" data-limit="<?= $realLimited ? (int) $realQuota['limit'] : '' ?>" data-used="<?= $realLimited ? (int) $realQuota['used'] : '' ?>" novalidate>
            <?= \App\Core\Csrf::field() ?>
            <header class="effk-md__h">
                <div><span class="effk-k">Émetteurs réels · <?= $realTotal ?> organismes</span><h2 id="qis-real-title">Importer les émetteurs réels</h2></div>
                <button type="button" class="effk-md__x" data-qis-close aria-label="Fermer"><?= effk_icon('x') ?></button>
            </header>
            <div class="qis-wiz__steps">
                <?php $steps = [['label' => 'Pays', 'active' => true], ['label' => 'Émetteurs'], ['label' => 'Vérifier et importer']];
                require base_path('views/partials/ui/stepper.php'); ?>
            </div>
            <div class="effk-md__b qis-wiz__b">
                <?php if ($realLimited): ?>
                <p class="effk-note <?= $realFull ? 'is-warn' : 'is-info' ?>"><span><b><?= (int) $realQuota['used'] ?> / <?= (int) $realQuota['limit'] ?></b> émetteurs réels sur votre formule<?= $realFull ? ' : plafond atteint, les émetteurs déjà présents restent utilisables' : ' (' . (int) $realQuota['remaining'] . ' restant(s))' ?>.<?php if ($realQuota['offer'] !== null): ?> Formule <b translate="no"><?= effk_e((string) $realQuota['offer']['name']) ?></b> : <?= $realQuota['offer']['value'] === 0 ? 'illimité' : (int) $realQuota['offer']['value'] ?>. <a href="<?= effk_e((string) $realQuota['upgrade_url']) ?>">Voir les formules</a><?php endif; ?></span></p>
                <?php endif; ?>
                <?php if (!$realReady): ?><p class="effk-note is-warn">Les émetteurs ne sont pas encore disponibles pour votre communauté : l’import sera possible après la prochaine mise à jour du site.</p><?php endif; ?>

                <section data-qis-step="1" aria-label="Étape 1 : pays">
                    <p class="qis-help">Choisissez un ou plusieurs pays. Ce sont des émetteurs de jeu inspirés d’organismes réels, sans valeur réelle ; un émetteur déjà présent chez vous (même nom, ancien nom ou sigle) n’est jamais modifié.</p>
                    <label class="effk-search qis-wiz__search">
                        <?= effk_icon('search') ?>
                        <span class="sr-only">Rechercher un pays ou un émetteur</span>
                        <input type="search" placeholder="Pays, organisme, sigle…" data-qis-q1 autocomplete="off">
                    </label>
                    <div class="qis-countries" role="group" aria-label="Pays">
                    <?php foreach ($realIssuers as $rg):
                        $code = (string) $rg['country'];
                        $n = count($rg['issuers']);
                        $here = count(array_filter($rg['issuers'], static fn (array $ri): bool => isset($realMatched[$ri['key']])));
                        $native = \App\Support\QualificationCatalog\CatalogCountry::native($code);
                        $words = [(string) $rg['label'], $native, $code];
                        foreach ($rg['issuers'] as $ri) {
                            $words[] = $ri['name'] . ' ' . $ri['short_name'] . ' ' . $ri['transliteration'];
                        } ?>
                        <label class="qis-country-card" data-qis-country-card data-search="<?= effk_e(mb_strtolower(implode(' ', $words), 'UTF-8')) ?>">
                            <input type="checkbox" value="<?= effk_e($code) ?>" data-qis-country>
                            <span class="qis-country-card__flag"><?= \App\Support\QualificationCatalog\CatalogCountry::flag($code) ?></span>
                            <span class="qis-country-card__txt">
                                <b><?= effk_e((string) $rg['label']) ?></b>
                                <?php if ($native !== '' && $native !== $rg['label']): ?><small translate="no"><?= effk_e($native) ?></small><?php endif; ?>
                                <small><span><?= $n ?> émetteur(s)</span> · <span><?= $here ?> déjà chez vous</span></small>
                                <small class="qis-country-card__hit" data-qis-hit hidden></small>
                            </span>
                            <span class="qis-country-card__n" title="À importer"><?= $n - $here ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <p class="qis-help" data-qis-q1-none hidden>Aucun pays ni émetteur ne correspond à la recherche.</p>
                    <p class="qis-wiz__err" data-qis-err="1" role="alert" hidden>Choisissez au moins un pays.</p>
                </section>

                <section data-qis-step="2" aria-label="Étape 2 : émetteurs" hidden>
                    <div class="effk-bar qis-wiz__bar">
                        <label class="effk-search effk-search--wide">
                            <?= effk_icon('search') ?>
                            <span class="sr-only">Rechercher un émetteur</span>
                            <input type="search" placeholder="Nom, sigle, ville…" data-qis-q2 autocomplete="off">
                        </label>
                        <label>
                            <span class="sr-only">Type</span>
                            <select data-qis-type2>
                                <option value="">Tous les types</option>
                                <?php foreach ($realTypes as $tk => $tl): ?><option value="<?= effk_e($tk) ?>"><?= effk_e($tl) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="qis-check"><input type="checkbox" data-qis-hide-present> <span>Masquer ceux déjà présents</span></label>
                    </div>
                    <div class="qis-wiz__sel">
                        <label class="qis-check"><input type="checkbox" data-qis-all2> <span>Tout cocher parmi les émetteurs affichés</span> <span class="effk-tag" data-qis-shown2>0</span></label>
                        <span class="qis-wiz__count" aria-live="polite"><b data-qis-nsel>0</b> coché(s)<?php if ($realRemaining !== null): ?> · <span>plafond restant : <b><?= (int) $realRemaining ?></b></span><?php endif; ?></span>
                    </div>
                    <p class="effk-note is-info" data-qis-err="cap" role="status" hidden>Seuls les premiers émetteurs affichés ont été cochés, dans la limite du plafond restant de votre formule.</p>
                    <div class="qis-reallist">
                    <?php foreach ($realIssuers as $rg): ?>
                        <div class="qis-reallist__group" data-qis-group="<?= effk_e((string) $rg['country']) ?>" hidden>
                            <h3><?= \App\Support\QualificationCatalog\CatalogCountry::flag((string) $rg['country']) ?> <span><?= effk_e((string) $rg['label']) ?></span></h3>
                            <ul role="list">
                            <?php foreach ($rg['issuers'] as $ri):
                                $present = isset($realMatched[$ri['key']]);
                                $s = mb_strtolower(implode(' ', [$ri['name'], $ri['short_name'], $ri['transliteration'], $ri['location'], $ri['type_label']]), 'UTF-8'); ?>
                                <li class="<?= $present ? 'is-present' : '' ?>" data-qis-ri data-key="<?= effk_e((string) $ri['key']) ?>" data-type="<?= effk_e((string) $ri['type']) ?>" data-search="<?= effk_e($s) ?>"<?= $present ? ' data-present' : '' ?>>
                                    <label>
                                        <input type="checkbox" name="keys[]" value="<?= effk_e((string) $ri['key']) ?>" data-label="<?= effk_e((string) ($ri['short_name'] !== '' ? $ri['short_name'] : $ri['name'])) ?>"<?= $present ? ' checked disabled' : (!$canImport ? ' disabled' : '') ?>>
                                        <span class="qis-real__id">
                                            <b translate="no"><?= effk_e((string) $ri['short_name']) ?></b>
                                            <span translate="no" data-qis-ri-name><?= effk_e((string) $ri['name']) ?></span>
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
                        </div>
                    <?php endforeach; ?>
                        <p class="qis-help" data-qis-q2-none hidden>Aucun émetteur ne correspond. Modifiez la recherche, le type ou affichez ceux déjà présents.</p>
                    </div>
                    <p class="qis-wiz__err" data-qis-err="2" role="alert" hidden>Cochez au moins un émetteur à importer.</p>
                    <p class="qis-wiz__err" data-qis-err="quota" role="alert" hidden>Votre formule permet encore <?= (int) $realRemaining ?> émetteur(s) réel(s) : décochez-en pour rester dans le plafond.</p>
                </section>

                <section data-qis-step="3" aria-label="Étape 3 : vérifier et importer" hidden>
                    <p class="qis-help">Les émetteurs cochés sont créés avec leur nom officiel, leur sigle, leur type et une présentation (localisation, site officiel, sources). Ils se modifient ensuite librement (logo, signataire, habilités).</p>
                    <?php if ($realLimited): ?>
                    <div class="qis-meter" data-qis-meter>
                        <div class="qis-meter__bar"><span class="qis-meter__used" style="width:<?= $realQuota['limit'] > 0 ? min(100, round(100 * (int) $realQuota['used'] / (int) $realQuota['limit'])) : 0 ?>%"></span><span class="qis-meter__add" data-qis-meter-add></span></div>
                        <p><span>Après l’import</span> <b data-qis-meter-after><?= (int) $realQuota['used'] ?></b> / <b><?= (int) $realQuota['limit'] ?></b> <span>émetteurs réels sur votre formule</span></p>
                    </div>
                    <?php endif; ?>
                    <div data-qis-summary1></div>
                </section>
                <p class="effk-note is-warn" data-qis-wiz-error role="alert" hidden></p>
            </div>
            <footer class="effk-md__f qis-wiz__f">
                <span class="qis-wiz__where" data-qis-where>Étape 1 sur 3</span>
                <button type="button" class="effk-md__btn" data-qis-prev>Précédent</button>
                <button type="button" class="effk-md__btn is-dark" data-qis-next>Suivant</button>
                <button type="submit" class="effk-md__btn is-dark" data-qis-submit hidden<?= $canImport ? '' : ' disabled' ?>><?= $spin ?><span>Importer les émetteurs cochés</span></button>
            </footer>
        </form>
    </dialog>
    <?php endif; ?>

    <dialog class="effk-md qis-wiz" id="nouvel-emetteur" aria-labelledby="qis-new-title" data-qis-region="new">
        <form method="post" action="<?= effk_e(url($base)) ?>" enctype="multipart/form-data" data-qis-wiz="new" novalidate>
            <?= \App\Core\Csrf::field() ?>
            <header class="effk-md__h">
                <div><span class="effk-k">Nouvel émetteur</span><h2 id="qis-new-title">Créer un émetteur</h2></div>
                <button type="button" class="effk-md__x" data-qis-close aria-label="Fermer"><?= effk_icon('x') ?></button>
            </header>
            <div class="qis-wiz__steps">
                <?php $steps = [['label' => 'Identité', 'active' => true], ['label' => 'Détails'], ['label' => 'Vérifier et créer']];
                require base_path('views/partials/ui/stepper.php'); ?>
            </div>
            <div class="effk-md__b qis-wiz__b">
                <section data-qis-step="1" aria-label="Étape 1 : identité">
                    <div class="qis-grid">
                        <label class="qis-f qis-f--wide" for="qi-new-name">
                            <span>Nom <em>obligatoire</em></span>
                            <input id="qi-new-name" name="name" required maxlength="200" placeholder="Centre d’instruction CAS" autocomplete="off" data-qis-name data-sum data-label="Nom">
                        </label>
                        <label class="qis-f" for="qi-new-short">
                            <span>Sigle</span>
                            <input id="qi-new-short" name="short_name" maxlength="80" placeholder="CI-CAS" autocomplete="off" data-sum data-label="Sigle">
                            <small>Affiché dans les listes et sur le logo par défaut.</small>
                        </label>
                        <?php if ($enriched): ?>
                        <label class="qis-f" for="qi-new-country">
                            <span>Pays</span>
                            <select id="qi-new-country" name="country" data-sum data-label="Pays">
                                <option value="">Aucun</option>
                                <?php foreach ($countries as $code => $labels): ?><option value="<?= effk_e((string) $code) ?>"><?= effk_e((string) ($labels[$lang] ?? $code)) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <?php endif; ?>
                    </div>
                    <div class="qis-suggest" data-qis-suggest hidden>
                        <p class="qis-suggest__dup" data-qis-dup hidden></p>
                        <div data-qis-real-hits hidden>
                            <p><b>Déjà dans le catalogue des émetteurs réels :</b> importez-le plutôt, il arrive avec sa présentation et ses sources.</p>
                            <ul role="list" data-qis-real-hit-list></ul>
                        </div>
                    </div>
                    <p class="qis-f qis-kinds__h"><span>Type d’émetteur</span></p>
                    <div class="qis-kinds" role="radiogroup" aria-label="Type d’émetteur">
                        <?php foreach ($kindLabels as $k => $label): ?>
                        <label class="qis-kind">
                            <input type="radio" name="issuer_kind" value="<?= effk_e($k) ?>"<?= $k === 'training_center' ? ' checked' : '' ?> data-sum data-label="<?= effk_e($label) ?>">
                            <span><b><?= effk_e($label) ?></b><small><?= effk_e($kindHints[$k]) ?></small></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="qis-wiz__err" data-qis-err="1" role="alert" hidden>Indiquez le nom de l’émetteur.</p>
                </section>

                <section data-qis-step="2" aria-label="Étape 2 : détails" hidden>
                    <?php if ($enriched): ?>
                    <div class="qis-grid">
                        <label class="qis-f" for="qi-new-loc">
                            <span>Localisation</span>
                            <input id="qi-new-loc" name="location" maxlength="200" placeholder="Camp de Canjuers, Var" data-sum data-label="Localisation">
                        </label>
                        <label class="qis-f" for="qi-new-web">
                            <span>Site officiel</span>
                            <input id="qi-new-web" name="website" type="url" maxlength="300" placeholder="https://www.exemple.fr" inputmode="url" data-sum data-label="Site officiel">
                        </label>
                    </div>
                    <div class="qis-grid">
                        <label class="qis-f" for="qi-new-desc">
                            <span>Présentation en français</span>
                            <textarea id="qi-new-desc" name="description" rows="3" maxlength="1200" placeholder="Ce que l’émetteur forme et valide, ses exigences, son contact…" data-sum data-label="Présentation"></textarea>
                        </label>
                        <label class="qis-f" for="qi-new-desc-en">
                            <span>Présentation en anglais</span>
                            <textarea id="qi-new-desc-en" name="description_en" rows="3" maxlength="600" placeholder="What the issuer trains and validates…" data-sum data-label="Présentation en anglais"></textarea>
                            <small>Facultatif : affichée aux membres qui lisent le site en anglais, à la suite de la présentation.</small>
                        </label>
                    </div>
                    <div class="qis-logo-field">
                        <span class="qis-logo" data-qis-logo-preview style="--c:#475569"><span data-qis-logo-initials>?</span></span>
                        <label class="qis-f" for="qi-new-logo">
                            <span>Logo ou insigne</span>
                            <input id="qi-new-logo" type="file" name="logo" accept="image/png,image/webp,image/svg+xml" data-sum data-label="Logo">
                            <small>PNG, WebP ou SVG, 2 Mo au plus. Sans logo, les initiales s’affichent.</small>
                        </label>
                    </div>
                    <p class="qis-wiz__err" data-qis-err="web" role="alert" hidden>Le site officiel doit être une adresse complète, par exemple https://www.exemple.fr.</p>
                    <p class="qis-wiz__err" data-qis-err="logo" role="alert" hidden>Logo trop lourd ou d’un format non pris en charge : PNG, WebP ou SVG, 2 Mo au plus.</p>
                    <?php endif; ?>

                    <details class="qis-more"<?= $enriched ? '' : ' open' ?>>
                        <summary><?= $enriched ? 'Rattachement et signature du brevet' : 'Rattachement' ?> <small>facultatif</small></summary>
                        <div class="qis-grid">
                            <?php if ($enriched): ?>
                            <label class="qis-f" for="qi-new-unit">
                                <span>Unité de rattachement</span>
                                <select id="qi-new-unit" name="unit_id" data-sum data-label="Unité">
                                    <option value="">Aucune</option>
                                    <?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>"><?= effk_e((string) $u['name']) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <?php endif; ?>
                            <label class="qis-f" for="qi-new-parent">
                                <span>Dépend d’un autre émetteur</span>
                                <select id="qi-new-parent" name="parent_issuer_id" data-sum data-label="Dépend de">
                                    <option value="">Non</option>
                                    <?php foreach ($issuers as $p): ?><option value="<?= (int) $p['id'] ?>"><?= effk_e((string) $p['name']) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <?php if ($enriched): ?>
                            <label class="qis-f" for="qi-new-sig">
                                <span>Signataire du brevet</span>
                                <input id="qi-new-sig" name="signatory_name" maxlength="150" placeholder="Cne Martin" data-sum data-label="Signataire">
                            </label>
                            <label class="qis-f" for="qi-new-sigt">
                                <span>Qualité du signataire</span>
                                <input id="qi-new-sigt" name="signatory_title" maxlength="150" placeholder="Chef du centre d’instruction" data-sum data-label="Qualité">
                            </label>
                            <?php endif; ?>
                        </div>
                    </details>

                    <?php if ($linkReady && $definitions !== []): ?>
                    <div class="qis-defpick">
                        <input type="hidden" name="definitions_form" value="1">
                        <div class="qis-defpick__h">
                            <b>Qualifications délivrées</b>
                            <span class="effk-muted" aria-live="polite"><b data-qis-ndef>0</b> cochée(s)</span>
                        </div>
                        <p class="qis-help">Cochez les qualifications dont c’est l’émetteur habituel. Une qualification n’a qu’un émetteur habituel : la cocher ici la retire de son émetteur précédent.</p>
                        <label class="effk-search qis-wiz__search">
                            <?= effk_icon('search') ?>
                            <span class="sr-only">Rechercher une qualification</span>
                            <input type="search" placeholder="Nom ou code de la qualification…" data-qis-qdef autocomplete="off">
                        </label>
                        <div class="qis-defs">
                        <?php foreach ($definitions as $d):
                            $other = (int) ($d['default_issuer_id'] ?? 0);
                            $otherName = '';
                            foreach ($issuers as $p) {
                                if ($other > 0 && (int) $p['id'] === $other) {
                                    $otherName = (string) $p['name'];
                                }
                            } ?>
                            <label class="qis-def" data-qis-def data-search="<?= effk_e(mb_strtolower((string) ($d['name'] ?? '') . ' ' . (string) ($d['code'] ?? ''), 'UTF-8')) ?>">
                                <input type="checkbox" name="definition_ids[]" value="<?= (int) $d['id'] ?>" data-label="<?= effk_e((string) ($d['name'] ?? '')) ?>">
                                <span><b><?= effk_e((string) ($d['name'] ?? '')) ?></b><small translate="no"><?= effk_e((string) ($d['code'] ?? '')) ?></small><?php if ($otherName !== ''): ?><small class="qis-def__other">Actuellement : <span translate="no"><?= effk_e($otherName) ?></span></small><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                        <p class="qis-help" data-qis-qdef-none hidden>Aucune qualification ne correspond.</p>
                    </div>
                    <?php endif; ?>
                </section>

                <section data-qis-step="3" aria-label="Étape 3 : vérifier et créer" hidden>
                    <div class="qis-recap">
                        <span class="qis-logo qis-logo--lg" data-qis-recap-logo style="--c:#475569"><span>?</span></span>
                        <div data-qis-summary2></div>
                    </div>
                    <p class="qis-help">Vous pourrez ensuite désigner les habilités, modifier la présentation et le logo depuis la fiche.</p>
                </section>
                <p class="effk-note is-warn" data-qis-wiz-error role="alert" hidden></p>
            </div>
            <footer class="effk-md__f qis-wiz__f">
                <span class="qis-wiz__where" data-qis-where>Étape 1 sur 3</span>
                <button type="button" class="effk-md__btn" data-qis-prev>Précédent</button>
                <button type="button" class="effk-md__btn is-dark" data-qis-next>Suivant</button>
                <button type="submit" class="effk-md__btn is-dark" data-qis-submit hidden><?= $spin ?><span>Créer l’émetteur</span></button>
            </footer>
        </form>
    </dialog>
</div>
</div>
<script src="<?= effk_e(asset_url('assets/js/qualification-issuers.js')) ?>" defer></script>
