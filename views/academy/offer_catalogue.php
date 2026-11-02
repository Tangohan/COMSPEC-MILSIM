<?php
/**
 * Catalogue Academy : point d'entrée de tout ce qu'on peut suivre et obtenir (onglets, recherche, filtres dans l'adresse).
 */
use App\Services\Academy\AcademyOffer;

require __DIR__ . '/offer_init.php';
$offerDetail = (string) ($offerDetail ?? '');
$f = $offerFilters;
$tab = $f['onglet'];
$totals = $offer['totals'] ?? [];
$searchItems = AcademyOffer::filter($offerItems, ['q' => $f['q']], true);
$tabCounts = array_fill_keys(AcademyOffer::TABS, 0);
foreach ($searchItems as $si) {
    foreach ($si['tabs'] as $siTab) {
        $tabCounts[$siTab]++;
    }
}
$tabCounts['tout'] = count($searchItems);
$visibleTabs = array_filter(AcademyOffer::TABS, static fn (string $t): bool => in_array($t, ['tout', 'formations'], true) || ($totals[$t] ?? 0) > 0 || $t === $tab);
$facetItems = AcademyOffer::filter($offerItems, ['onglet' => $tab, 'q' => $f['q']]);
$facets = AcademyOffer::facets($facetItems);
$results = AcademyOffer::filter($offerItems, $f);
$activeFilters = array_filter(array_intersect_key($f, array_flip(['categorie', 'emetteur', 'niveau', 'mode', 'etat', 'source'])), static fn (string $v): bool => $v !== '');
$issuerLabel = $facets['issuers'][$f['emetteur']] ?? '';
?>
<div class="acx">
<?php if ($offerDetail !== ''): ?>
    <?php $it = AcademyOffer::find($offerItems, $offerDetail); ?>
    <?php if ($it === null): ?>
        <p class="acx-back"><a class="text-link" href="<?= $h($link('catalogue')) ?>">← <span>Retour au catalogue</span></a></p>
        <div class="acx-empty" role="status"><h1>Cette fiche n’est pas disponible</h1><p>Elle a peut-être été retirée ou n’est pas visible pour votre compte.</p><a class="button small" href="<?= $h($link('catalogue')) ?>"><span>Parcourir le catalogue</span></a></div>
    <?php else: require __DIR__ . '/offer_detail.php'; endif ?>
<?php else: ?>
<header class="acx-heading">
    <p class="eyebrow">ATHENA ACADEMY · <span>Catalogue</span></p>
    <h1>Catalogue</h1>
    <p><?= $h($oxTabHelp[$tab]) ?></p>
</header>

<form class="acx-search" method="get" action="<?= $h($link('catalogue')) ?>" role="search">
    <?php if ($tab !== 'tout'): ?><input type="hidden" name="onglet" value="<?= $h($tab) ?>"><?php endif ?>
    <?php foreach ($activeFilters as $fk => $fv): ?><input type="hidden" name="<?= $h($fk) ?>" value="<?= $h($fv) ?>"><?php endforeach ?>
    <?php if ($f['tri'] !== 'pertinence'): ?><input type="hidden" name="tri" value="<?= $h($f['tri']) ?>"><?php endif ?>
    <label class="acx-search__field"><?= $oxSymbol('search') ?><span class="acx-sr">Rechercher dans tout le catalogue</span>
        <input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="Rechercher une formation, un brevet, une qualification…" autocomplete="off" maxlength="120">
    </label>
    <button class="button small" type="submit"><span>Rechercher</span></button>
</form>

<nav class="acx-tabs" aria-label="Rubriques du catalogue">
    <ul>
    <?php foreach ($visibleTabs as $tabKey): ?>
        <li><a href="<?= $h($oxUrl(['onglet' => $tabKey, 'categorie' => '', 'emetteur' => '', 'niveau' => '', 'mode' => '', 'etat' => ''])) ?>"<?= $tabKey === $tab ? ' aria-current="page"' : '' ?>><span><?= $h($oxTabs[$tabKey]) ?></span> <b class="acx-count"><?= (int) $tabCounts[$tabKey] ?></b></a></li>
    <?php endforeach ?>
    </ul>
</nav>

<?php if ($tab !== 'tout'): ?>
<details class="acx-filters"<?= $activeFilters !== [] || $f['tri'] !== 'pertinence' ? ' open' : '' ?>>
    <summary><?= $oxSymbol('filter') ?><span>Filtres et tri</span><?php if ($activeFilters !== []): ?> <b class="acx-count"><?= count($activeFilters) ?></b><?php endif ?></summary>
    <form method="get" action="<?= $h($link('catalogue')) ?>" class="acx-filters__form" data-autosubmit>
        <input type="hidden" name="onglet" value="<?= $h($tab) ?>">
        <?php if ($f['q'] !== ''): ?><input type="hidden" name="q" value="<?= $h($f['q']) ?>"><?php endif ?>
        <?php if (count($facets['categories']) > 0): ?>
        <label><span>Catégorie</span><select name="categorie"><option value="">Toutes</option><?php foreach ($facets['categories'] as $c): ?><option value="<?= $h($c) ?>"<?= $f['categorie'] === $c ? ' selected' : '' ?> translate="no"><?= $h($c) ?></option><?php endforeach ?></select></label>
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
        <?php if (count($facets['sources']) > 1): ?>
        <label><span>Origine</span><select name="source"><option value="">Toutes</option><?php foreach ($oxSources as $sk => $sl): ?><option value="<?= $h($sk) ?>"<?= $f['source'] === $sk ? ' selected' : '' ?>><?= $h($sl) ?></option><?php endforeach ?></select></label>
        <?php endif ?>
        <label><span>Trier par</span><select name="tri"><?php foreach ($oxSorts as $sk => $sl): ?><option value="<?= $h($sk) ?>"<?= $f['tri'] === $sk ? ' selected' : '' ?>><?= $h($sl) ?></option><?php endforeach ?></select></label>
        <div class="acx-filters__buttons">
            <button class="button small" type="submit"><span>Appliquer</span></button>
            <?php if ($activeFilters !== [] || $f['tri'] !== 'pertinence'): ?><a class="text-link" href="<?= $h($oxUrl(['categorie' => '', 'emetteur' => '', 'niveau' => '', 'mode' => '', 'etat' => '', 'source' => '', 'tri' => 'pertinence'])) ?>"><span>Effacer les filtres</span></a><?php endif ?>
        </div>
    </form>
</details>
<?php endif ?>

<p class="acx-result-count" role="status">
    <b><?= count($results) ?></b> <span><?= count($results) === 1 ? 'résultat' : 'résultats' ?></span>
    <?php if ($f['q'] !== ''): ?><span>pour</span> « <span translate="no"><?= $h($f['q']) ?></span> »<?php endif ?>
    <?php if ($issuerLabel !== ''): ?>· <span>émetteur</span> <span translate="no"><?= $h($issuerLabel) ?></span><?php endif ?>
</p>

<?php if ($results === []): ?>
    <div class="acx-empty">
        <?php if ($f['q'] !== '' || $activeFilters !== []): ?>
            <h2>Aucun résultat</h2>
            <p>Essayez un autre mot, ou retirez des filtres.</p>
            <div class="buttons">
                <?php if ($activeFilters !== []): ?><a class="button small" href="<?= $h($oxUrl(['categorie' => '', 'emetteur' => '', 'niveau' => '', 'mode' => '', 'etat' => '', 'source' => ''])) ?>"><span>Effacer les filtres</span></a><?php endif ?>
                <?php if ($f['q'] !== '' && $tab !== 'tout' && $tabCounts['tout'] > 0): ?><a class="button small outline" href="<?= $h($oxUrl(['onglet' => 'tout', 'categorie' => '', 'emetteur' => '', 'niveau' => '', 'mode' => '', 'etat' => '', 'source' => ''])) ?>"><span>Chercher dans tout le catalogue</span> (<?= (int) $tabCounts['tout'] ?>)</a><?php endif ?>
                <?php if ($f['q'] !== ''): ?><a class="text-link" href="<?= $h($oxUrl(['q' => ''])) ?>"><span>Effacer la recherche</span></a><?php endif ?>
            </div>
        <?php else: ?>
            <h2>Rien pour le moment</h2>
            <?php if ($tab === 'qualifications' || $tab === 'brevets'): ?><p>Votre communauté n’a pas encore publié de qualification ici. Les responsables les créent dans le référentiel des qualifications.</p>
            <?php elseif ($tab === 'parcours'): ?><p>Aucun parcours enregistré. Vous pouvez en générer un à partir d’une compétence ou d’une fonction.</p><a class="button small" href="<?= $h(url('academy/formations/competences') . '#generateur') ?>"><span>Générer un parcours</span></a>
            <?php elseif ($tab === 'competences'): ?><p>Le référentiel des compétences de votre communauté est encore vide.</p>
            <?php else: ?><p>Aucune formation publiée pour le moment.</p><?php endif ?>
            <?php if (!empty($staff) && in_array($tab, ['qualifications', 'brevets', 'emetteurs'], true)): ?><a class="button small outline" href="<?= $h(url('back-office/referentiels/qualifications')) ?>"><span>Ouvrir le référentiel des qualifications</span></a><?php endif ?>
        <?php endif ?>
    </div>
<?php elseif ($tab === 'tout'): ?>
    <?php foreach (array_slice(AcademyOffer::TABS, 1) as $section):
        $sectionItems = array_values(array_filter($results, static fn (array $r): bool => $r['tabs'][0] === $section));
        if ($sectionItems === []) continue; ?>
    <section class="acx-section" aria-labelledby="acx-sec-<?= $h($section) ?>">
        <header class="acx-section__head">
            <h2 id="acx-sec-<?= $h($section) ?>"><?= $h($oxTabs[$section]) ?> <b class="acx-count"><?= count($sectionItems) ?></b></h2>
            <?php if (count($sectionItems) > 6): ?><a class="text-link" href="<?= $h($oxUrl(['onglet' => $section])) ?>"><span>Tout voir</span> (<?= count($sectionItems) ?>) →</a><?php endif ?>
        </header>
        <div class="acx-grid"><?php foreach (array_slice($sectionItems, 0, 6) as $it) { require __DIR__ . '/offer_card.php'; } ?></div>
    </section>
    <?php endforeach ?>
<?php else: ?>
    <div class="acx-grid"><?php foreach ($results as $it) { require __DIR__ . '/offer_card.php'; } ?></div>
<?php endif ?>
<?php endif ?>
</div>
