<?php
/**
 * Catalogue Academy : point d'entrée de tout ce qu'on peut suivre et obtenir (onglets, recherche, filtres dans l'adresse).
 * Onglets Qualifications et Brevets : qualifications de la communauté et catalogue de référence (pays, OTAN), avec
 * leur origine (déjà dans mon référentiel, créations de la communauté, catalogue de référence).
 */
use App\Services\Academy\AcademyOffer;

require __DIR__ . '/offer_init.php';
$offerDetail = (string) ($offerDetail ?? '');
$f = $offerFilters;
$tab = $f['onglet'];
$totals = $offer['totals'] ?? [];
$referenceLoaded = !empty($offer['reference_loaded']);
$referenceTotal = (int) ($offer['references'] ?? 0);
// Catalogue non chargé (onglets sans qualifications) : sa taille seulement, pour l’en-tête et le lien.
$referenceShown = $referenceLoaded ? $referenceTotal : count(\App\Support\QualificationCatalog\QualificationCatalog::rawEntries());
if (!$referenceLoaded) {
    $referenceTotal = $referenceShown;
}
$searchItems = AcademyOffer::filter($offerItems, ['q' => $f['q']], true);
$tabCounts = array_fill_keys(AcademyOffer::TABS, 0);
foreach ($searchItems as $si) {
    foreach ($si['tabs'] as $siTab) {
        $tabCounts[$siTab]++;
    }
    $tabCounts['tout'] += $si['kind'] === 'reference' ? 0 : 1;
}
$visibleTabs = array_filter(AcademyOffer::TABS, static fn (string $t): bool => in_array($t, ['tout', 'formations', 'qualifications'], true) || ($totals[$t] ?? 0) > 0 || $t === $tab);
$qualTab = in_array($tab, ['qualifications', 'brevets'], true);
$facetItems = AcademyOffer::filter($offerItems, ['onglet' => $tab, 'q' => $f['q']]);
$facets = AcademyOffer::facets($facetItems);
// Compteurs par origine et par pays : avec les autres filtres, sans celui qu'ils changent.
$originCounts = AcademyOffer::facets(AcademyOffer::filter($offerItems, ['origine' => ''] + $f))['origins'];
$countryCounts = [];
foreach (AcademyOffer::filter($offerItems, ['pays' => ''] + $f) as $ci) {
    if (($ci['country'] ?? '') !== '') {
        $countryCounts[$ci['country']] = ($countryCounts[$ci['country']] ?? 0) + 1;
    }
}
$results = AcademyOffer::filter($offerItems, $f);
$filterKeys = ['categorie', 'emetteur', 'niveau', 'mode', 'etat', 'source', 'pays', 'nature', 'origine'];
$activeFilters = array_filter(array_intersect_key($f, array_flip($filterKeys)), static fn (string $v): bool => $v !== '');
$cleared = array_fill_keys($filterKeys, '');
$issuerLabel = $facets['issuers'][$f['emetteur']] ?? '';
if ($issuerLabel === '' && $f['emetteur'] !== '') {
    foreach ($offerItems as $oi) {
        if ($oi['kind'] !== 'issuer' && (string) (int) ($oi['issuer_id'] ?? 0) === $f['emetteur']) {
            $issuerLabel = (string) $oi['issuer'];
            break;
        }
    }
}
$pageNo = max(1, (int) $f['page']);
$pageCount = max(1, (int) ceil(count($results) / AcademyOffer::PER_PAGE));
$pageNo = min($pageNo, $pageCount);
$pageResults = $tab === 'tout' ? $results : array_slice($results, ($pageNo - 1) * AcademyOffer::PER_PAGE, AcademyOffer::PER_PAGE);
$chipLabel = static function (string $key, string $value) use ($oxNatures, $oxOrigins, $oxModes, $oxStateGroups, $oxSources, $oxLevel, $facets, $issuerLabel): string {
    return match ($key) {
        'nature' => $oxNatures[$value] ?? $value,
        'origine' => $oxOrigins[$value] ?? $value,
        'mode' => $oxModes[$value] ?? $value,
        'etat' => $oxStateGroups[$value] ?? $value,
        'source' => $oxSources[$value] ?? $value,
        'niveau' => strip_tags($oxLevel($value)),
        'pays' => $facets['countries'][$value] ?? $value,
        'emetteur' => $issuerLabel !== '' ? $issuerLabel : $value,
        default => $value,
    };
};
$chipNames = ['categorie' => 'Domaine', 'emetteur' => 'Émetteur', 'niveau' => 'Niveau', 'mode' => 'Obtention', 'etat' => 'État', 'source' => 'Origine', 'pays' => 'Pays', 'nature' => 'Catégorie', 'origine' => 'Origine'];
?>
<div class="acx acx--catalogue">
<?php if ($offerDetail !== ''): ?>
    <?php
    $it = AcademyOffer::find($offerItems, $offerDetail);
    // Entrée du catalogue déjà importée : sa fiche dans le référentiel de la communauté.
    if ($it === null && str_starts_with($offerDetail, 'r-')) {
        foreach ($offerItems as $oi) {
            if (($oi['ref_id'] ?? '') === substr($offerDetail, 2)) {
                $it = $oi;
                break;
            }
        }
    }
    ?>
    <?php if ($it === null): ?>
        <p class="acx-back"><a class="text-link" href="<?= $h($link('catalogue')) ?>">← <span>Retour au catalogue</span></a></p>
        <div class="acx-empty" role="status"><h1>Cette fiche n’est pas disponible</h1><p>Elle a peut-être été retirée ou n’est pas visible pour votre compte.</p><a class="button small" href="<?= $h($link('catalogue')) ?>"><span>Parcourir le catalogue</span></a></div>
    <?php else: require __DIR__ . '/offer_detail.php'; endif ?>
<?php else: ?>
<header class="acd-hero acx-hero" style="--img:url('<?= $h(asset_url('assets/images/academy/cover-' . ($qualTab ? 'fog' : 'hero') . '.webp')) ?>')">
    <div class="acd-hero__in">
        <div class="acd-hero__text">
            <p class="acd-hero__kk"><i aria-hidden="true"></i><span>ATHENA ACADEMY · <span>Catalogue</span></span></p>
            <h1><?= $h($tab === 'tout' ? 'Catalogue' : $oxTabs[$tab]) ?></h1>
            <p class="acd-hero__lead"><?= $h($oxTabHelp[$tab]) ?></p>
        </div>
        <form class="acx-search acx-search--hero" method="get" action="<?= $h($link('catalogue')) ?>" role="search">
            <?php if ($tab !== 'tout'): ?><input type="hidden" name="onglet" value="<?= $h($tab) ?>"><?php endif ?>
            <?php foreach ($activeFilters as $fk => $fv): ?><input type="hidden" name="<?= $h($fk) ?>" value="<?= $h($fv) ?>"><?php endforeach ?>
            <?php if ($f['tri'] !== 'pertinence'): ?><input type="hidden" name="tri" value="<?= $h($f['tri']) ?>"><?php endif ?>
            <label class="acx-search__field"><?= $oxSymbol('search') ?><span class="acx-sr">Rechercher dans le catalogue</span>
                <input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="<?= $h($qualTab ? 'JTAC, brevet parachutiste, Ranger, ETAP…' : 'Rechercher une formation, un brevet, une qualification…') ?>" autocomplete="off" maxlength="120">
            </label>
            <button class="button small" type="submit"><span>Rechercher</span></button>
        </form>
    </div>
    <dl class="acd-hero__stats">
        <div><dt>Formations</dt><dd><?= (int) ($totals['formations'] ?? 0) ?></dd></div>
        <div><dt>Brevets et licences</dt><dd><?= (int) (($totals['brevets'] ?? 0) - count(array_filter($offerItems, static fn (array $i): bool => $i['kind'] === 'reference' && in_array('brevets', $i['tabs'], true)))) ?></dd></div>
        <div><dt>Qualifications</dt><dd><?= (int) (($totals['qualifications'] ?? 0) - ($referenceLoaded ? $referenceTotal : 0)) ?></dd></div>
        <div><dt>Catalogue de référence</dt><dd class="is-ok"><?= $referenceLoaded ? $referenceTotal + (int) ($originCounts['referentiel'] ?? 0) : $referenceShown ?></dd></div>
    </dl>
</header>

<nav class="acx-tabs" aria-label="Rubriques du catalogue">
    <ul>
    <?php foreach ($visibleTabs as $tabKey): ?>
        <li><a href="<?= $h($oxUrl(['onglet' => $tabKey] + $cleared)) ?>"<?= $tabKey === $tab ? ' aria-current="page"' : '' ?>><span><?= $h($oxTabs[$tabKey]) ?></span> <b class="acx-count"><?= (int) $tabCounts[$tabKey] ?></b></a></li>
    <?php endforeach ?>
    </ul>
</nav>

<?php if ($qualTab): ?>
<nav class="acx-origins" aria-label="Origine des qualifications">
    <a href="<?= $h($oxUrl(['origine' => ''])) ?>"<?= $f['origine'] === '' ? ' aria-current="true"' : '' ?>><b>Toutes</b> <span class="acx-count"><?= array_sum($originCounts) ?></span></a>
    <?php foreach ($oxOrigins as $ok => $ol): ?>
    <a href="<?= $h($oxUrl(['origine' => $ok])) ?>" class="acx-origins__<?= $h($ok) ?>"<?= $f['origine'] === $ok ? ' aria-current="true"' : '' ?>>
        <b><?= $h($ol) ?></b> <span class="acx-count"><?= (int) ($originCounts[$ok] ?? 0) ?></span>
        <small><?= $h($oxOriginHelp[$ok]) ?></small>
    </a>
    <?php endforeach ?>
</nav>
<?php if ($countryCounts !== [] || $f['pays'] !== ''): ?>
<nav class="acx-countries" aria-label="Pays">
    <a href="<?= $h($oxUrl(['pays' => ''])) ?>"<?= $f['pays'] === '' ? ' aria-current="true"' : '' ?>><span>Tous les pays</span></a>
    <?php foreach (\App\Support\QualificationCatalog\QualificationCatalog::COUNTRIES as $cc => $_): if (!isset($countryCounts[$cc]) && $f['pays'] !== $cc) continue; ?>
    <a href="<?= $h($oxUrl(['pays' => $f['pays'] === $cc ? '' : $cc])) ?>"<?= $f['pays'] === $cc ? ' aria-current="true"' : '' ?>><?= $oxCountry($cc, \App\Support\QualificationCatalog\QualificationCatalog::countryLabel($cc, (string) locale()), true) ?> <span class="acx-count"><?= (int) ($countryCounts[$cc] ?? 0) ?></span></a>
    <?php endforeach ?>
</nav>
<?php endif ?>
<?php if ($oxCanImport) { require __DIR__ . '/import_wizard.php'; } /* Assistant d'importation du catalogue de référence. */ ?>
<?php endif ?>

<?php if ($tab !== 'tout'): ?>
<details class="acx-filters"<?= array_diff_key($activeFilters, ['origine' => 1, 'pays' => 1]) !== [] || $f['tri'] !== 'pertinence' ? ' open' : '' ?>>
    <summary><?= $oxSymbol('filter') ?><span>Filtres et tri</span><?php if ($activeFilters !== []): ?> <b class="acx-count"><?= count($activeFilters) ?></b><?php endif ?></summary>
    <form method="get" action="<?= $h($link('catalogue')) ?>" class="acx-filters__form" data-autosubmit>
        <input type="hidden" name="onglet" value="<?= $h($tab) ?>">
        <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= $h($f['q']) ?>"><?php endif ?>
        <?php if ($f['origine'] !== ''): ?><input type="hidden" name="origine" value="<?= $h($f['origine']) ?>"><?php endif ?>
        <?php if (count($facets['countries']) > 0): ?>
        <label><span>Pays</span><select name="pays"><option value="">Tous</option><?php foreach ($facets['countries'] as $cc => $cl): ?><option value="<?= $h($cc) ?>"<?= $f['pays'] === $cc ? ' selected' : '' ?> translate="no"><?= $h($cl) ?><?php $cn = \App\Support\QualificationCatalog\CatalogCountry::native($cc); if ($cn !== '' && $cn !== $cl): ?> · <?= $h($cn) ?><?php endif ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (count($facets['natures']) > 1 || $f['nature'] !== ''): ?>
        <label><span>Catégorie</span><select name="nature"><option value="">Toutes</option><?php foreach ($oxNatures as $nk => $nl): if (!in_array($nk, $facets['natures'], true) && $f['nature'] !== $nk) continue; ?><option value="<?= $h($nk) ?>"<?= $f['nature'] === $nk ? ' selected' : '' ?>><?= $h($nl) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (count($facets['categories']) > 0): ?>
        <label><span><?= $qualTab ? 'Domaine' : 'Catégorie' ?></span><select name="categorie"><option value="">Tous</option><?php foreach ($facets['categories'] as $c): ?><option value="<?= $h($c) ?>"<?= $f['categorie'] === $c ? ' selected' : '' ?> translate="no"><?= $h($c) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (count($facets['issuers']) > 0): ?>
        <label><span>Émetteur</span><select name="emetteur"><option value="">Tous</option><?php foreach ($facets['issuers'] as $ik => $in): ?><option value="<?= $h($ik) ?>"<?= $f['emetteur'] === (string) $ik ? ' selected' : '' ?> translate="no"><?= $h($in) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (count($facets['levels']) > 0): ?>
        <label><span>Niveau</span><select name="niveau"><option value="">Tous</option><?php foreach ($facets['levels'] as $lv): ?><option value="<?= $h($lv) ?>"<?= $f['niveau'] === $lv ? ' selected' : '' ?>><?= strip_tags($oxLevel($lv)) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (count($facets['modes']) > 1): ?>
        <label><span>Mode d’obtention</span><select name="mode"><option value="">Tous</option><?php foreach ($oxModes as $mk => $ml): if (!in_array($mk, $facets['modes'], true)) continue; ?><option value="<?= $h($mk) ?>"<?= $f['mode'] === $mk ? ' selected' : '' ?>><?= $h($ml) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (!in_array($tab, ['emetteurs'], true)): ?>
        <label><span>État pour moi</span><select name="etat"><option value="">Tous</option><?php foreach ($oxStateGroups as $sk => $sl): ?><option value="<?= $h($sk) ?>"<?= $f['etat'] === $sk ? ' selected' : '' ?>><?= $h($sl) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <?php if (!$qualTab && count($facets['sources']) > 1): ?>
        <label><span>Origine</span><select name="source"><option value="">Toutes</option><?php foreach ($oxSources as $sk => $sl): if (!in_array($sk, $facets['sources'], true)) continue; ?><option value="<?= $h($sk) ?>"<?= $f['source'] === $sk ? ' selected' : '' ?>><?= $h($sl) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <label><span>Trier par</span><select name="tri"><?php foreach ($oxSorts as $sk => $sl): if ($sk === 'pays' && !$qualTab) continue; ?><option value="<?= $h($sk) ?>"<?= $f['tri'] === $sk ? ' selected' : '' ?>><?= $h($sl) ?></option><?php endforeach ?></select></label>
        <div class="acx-filters__buttons">
            <button class="button small" type="submit"><span>Appliquer</span></button>
            <?php if ($activeFilters !== [] || $f['tri'] !== 'pertinence'): ?><a class="text-link" href="<?= $h($oxUrl($cleared + ['tri' => 'pertinence'])) ?>"><span>Effacer les filtres</span></a><?php endif ?>
        </div>
    </form>
</details>
<?php endif ?>

<div class="acx-resultbar">
    <p class="acx-result-count" role="status">
        <b><?= count($results) ?></b> <span><?= count($results) === 1 ? 'résultat' : 'résultats' ?></span>
        <?php if ($f['q'] !== ''): ?><span>pour</span> « <span translate="no"><?= $h($f['q']) ?></span> »<?php endif ?>
        <?php if ($pageCount > 1): ?>· <span>page</span> <?= $pageNo ?> / <?= $pageCount ?><?php endif ?>
    </p>
    <?php if ($activeFilters !== []): ?>
    <ul class="acx-chips" aria-label="Filtres actifs">
        <?php foreach ($activeFilters as $fk => $fv): ?>
        <li><a href="<?= $h($oxUrl([$fk => ''])) ?>" title="Retirer ce filtre"><span><?= $h($chipNames[$fk] ?? $fk) ?> :</span> <b translate="no"><?= $h($chipLabel($fk, $fv)) ?></b> <span aria-hidden="true">×</span><span class="acx-sr">Retirer ce filtre</span></a></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</div>

<?php if ($results === []): ?>
    <div class="acx-empty">
        <span class="acx-empty__art" aria-hidden="true"><?= $oxSymbol($qualTab ? 'qualification' : 'search') ?></span>
        <?php if ($f['q'] !== '' || $activeFilters !== []): ?>
            <h2>Aucun résultat</h2>
            <p>Essayez un autre mot, ou retirez des filtres.</p>
            <div class="buttons">
                <?php if ($activeFilters !== []): ?><a class="button small" href="<?= $h($oxUrl($cleared)) ?>"><span>Effacer les filtres</span></a><?php endif ?>
                <?php if ($qualTab && $f['origine'] !== 'catalogue' && $referenceTotal > 0): ?><a class="button small outline" href="<?= $h($oxUrl(['origine' => 'catalogue'])) ?>"><span>Chercher dans le catalogue de référence</span></a><?php endif ?>
                <?php if ($f['q'] !== '' && $tab !== 'tout' && $tabCounts['tout'] > 0): ?><a class="button small outline" href="<?= $h($oxUrl(['onglet' => 'tout'] + $cleared)) ?>"><span>Chercher dans tout le catalogue</span> (<?= (int) $tabCounts['tout'] ?>)</a><?php endif ?>
                <?php if ($f['q'] !== ''): ?><a class="text-link" href="<?= $h($oxUrl(['q' => ''])) ?>"><span>Effacer la recherche</span></a><?php endif ?>
            </div>
        <?php else: ?>
            <h2>Rien pour le moment</h2>
            <?php if ($qualTab && $f['origine'] === 'referentiel'): ?><p>Aucune qualification du catalogue de référence n’a encore été importée dans votre référentiel.</p><a class="button small" href="<?= $h($oxUrl(['origine' => 'catalogue'])) ?>"><span>Parcourir le catalogue de référence</span></a>
            <?php elseif ($qualTab && $f['origine'] === 'communaute'): ?><p>Votre communauté n’a pas encore créé de qualification. Les responsables les créent dans le Studio de l’Academy ou dans le référentiel des qualifications.</p>
            <?php elseif ($tab === 'qualifications' || $tab === 'brevets'): ?><p>Votre communauté n’a pas encore publié de qualification ici. Les responsables les créent dans le référentiel des qualifications.</p>
            <?php elseif ($tab === 'parcours'): ?><p>Aucun parcours enregistré. Vous pouvez en générer un à partir d’une compétence ou d’une fonction.</p><a class="button small" href="<?= $h(url('academy/formations/competences') . '#generateur') ?>"><span>Générer un parcours</span></a>
            <?php elseif ($tab === 'competences'): ?><p>Le référentiel des compétences de votre communauté est encore vide.</p>
            <?php else: ?><p>Aucune formation publiée pour le moment.</p><?php endif ?>
            <?php if (!empty($staff) && in_array($tab, ['qualifications', 'brevets', 'emetteurs'], true)): ?><a class="button small outline" href="<?= $h(url('back-office/referentiels/qualifications')) ?>"><span>Ouvrir le référentiel des qualifications</span></a><?php endif ?>
        <?php endif ?>
    </div>
<?php elseif ($tab === 'tout'): ?>
    <?php foreach (array_slice(AcademyOffer::TABS, 1) as $section):
        $sectionItems = array_values(array_filter($results, static fn (array $r): bool => $r['tabs'][0] === $section));
        if ($sectionItems === [] && !($section === 'qualifications' && $referenceTotal > 0)) continue; ?>
    <section class="acx-section" aria-labelledby="acx-sec-<?= $h($section) ?>">
        <header class="acx-section__head">
            <h2 id="acx-sec-<?= $h($section) ?>"><?= $h($oxTabs[$section]) ?> <b class="acx-count"><?= count($sectionItems) ?></b></h2>
            <?php if (count($sectionItems) > 6): ?><a class="text-link" href="<?= $h($oxUrl(['onglet' => $section])) ?>"><span>Tout voir</span> (<?= count($sectionItems) ?>) →</a><?php endif ?>
        </header>
        <?php if ($sectionItems !== []): ?><div class="acx-grid"><?php foreach (array_slice($sectionItems, 0, 6) as $it) { require __DIR__ . '/offer_card.php'; } ?></div><?php endif ?>
        <?php if ($section === 'qualifications' && $referenceTotal > 0): ?>
        <a class="acx-promo" href="<?= $h($oxUrl(['onglet' => 'qualifications', 'origine' => 'catalogue'] + array_diff_key($cleared, ['origine' => 1]))) ?>">
            <span class="acx-promo__flags" aria-hidden="true"><?php foreach (array_keys(\App\Support\QualificationCatalog\QualificationCatalog::COUNTRIES) as $cc) { echo \App\Support\QualificationCatalog\CatalogCountry::flag($cc); } ?></span>
            <span><b>Catalogue de référence</b> <span><?= $referenceTotal ?> <span>qualifications et brevets réels par pays et de l’OTAN, prêts à importer.</span></span></span>
            <?= $oxSymbol('arrow') ?>
        </a>
        <?php endif ?>
    </section>
    <?php endforeach ?>
<?php else: ?>
    <div class="acx-grid"><?php foreach ($pageResults as $it) { require __DIR__ . '/offer_card.php'; } ?></div>
    <?php if ($pageCount > 1): ?>
    <nav class="acx-pager" aria-label="Pages de résultats">
        <?php if ($pageNo > 1): ?><a class="button small outline" href="<?= $h($oxUrl(['page' => $pageNo - 1 > 1 ? (string) ($pageNo - 1) : ''])) ?>" rel="prev">← <span>Précédente</span></a><?php endif ?>
        <ol>
        <?php for ($pn = 1; $pn <= $pageCount; $pn++):
            if ($pageCount > 9 && $pn !== 1 && $pn !== $pageCount && abs($pn - $pageNo) > 2) {
                if ($pn === 2 || $pn === $pageCount - 1) echo '<li aria-hidden="true">…</li>';
                continue;
            } ?>
            <li><a href="<?= $h($oxUrl(['page' => $pn > 1 ? (string) $pn : ''])) ?>"<?= $pn === $pageNo ? ' aria-current="page"' : '' ?>><?= $pn ?></a></li>
        <?php endfor ?>
        </ol>
        <?php if ($pageNo < $pageCount): ?><a class="button small outline" href="<?= $h($oxUrl(['page' => (string) ($pageNo + 1)])) ?>" rel="next"><span>Suivante</span> →</a><?php endif ?>
    </nav>
    <?php endif ?>
<?php endif ?>
<?php endif ?>
</div>
