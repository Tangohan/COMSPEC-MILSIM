<?php
declare(strict_types=1);
/**
 * Espace Collections : la liste des collections (cartes), ou, avec ?collection=ID, le contenu d'une collection.
 * Création et modification dans des fenêtres (_dialogs.php) ; adresses ?collection=ID conservées.
 */
if (is_array($nav['collection_row'] ?? null)) {
    require __DIR__ . '/_space_wardrobes.php';

    return;
}
$paged = is_array($nav['paged'] ?? null) ? $nav['paged'] : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$filtersActive = (string) ($nav['q'] ?? '') !== '';
?>
<div class="eqx-spacehead">
    <p class="eqx-intro">Rangez vos tenues en collections pour les retrouver et les partager par thème.</p>
    <a class="eq-hub__btn" href="<?= $h(url('equipment') . '?espace=collections&creer=collection') ?>" data-eq-open="collection-new">Nouvelle collection</a>
</div>
<?php
$toolbarKinds = false;
$toolbarSort = false;
$toolbarPlaceholder = 'Rechercher une collection…';
require __DIR__ . '/_space_toolbar.php';
?>
<?php if ($paged['items'] === []): ?>
<div class="eqx-empty">
    <?php if ($filtersActive): ?>
    <p><strong>Aucune collection ne correspond à cette recherche.</strong></p>
    <?php else: ?>
    <p><strong>Aucune collection pour le moment.</strong></p>
    <p>Créez-en une pour ranger vos tenues par thème, puis choisissez qui peut s’en servir.</p>
    <?php endif; ?>
</div>
<?php else: ?>
<ul class="eqx-collgrid">
    <?php foreach ($paged['items'] as $c): ?>
    <?php
        $mosaic = is_array($c['mosaic'] ?? null) ? $c['mosaic'] : [];
        $count = (int) ($c['wardrobe_count'] ?? 0);
        $href = \App\Support\EquipmentSpaces::url(['espace' => 'collections', 'collection' => (int) $c['id']]);
    ?>
    <li class="eqx-collcard">
        <a class="eqx-collcard__link" href="<?= $h($href) ?>">
            <span class="eq-hub__media eq-hub__media--coll">
                <?php if (!empty($c['cover_url'])): ?>
                <img class="eq-hub__img" src="<?= $h((string) $c['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                <?php elseif ($mosaic !== []): ?>
                <span class="eq-hub__mosaic" aria-hidden="true">
                    <?php foreach (array_slice($mosaic, 0, 4) as $mUrl): ?>
                    <img src="<?= $h((string) $mUrl) ?>" alt="" loading="lazy" decoding="async">
                    <?php endforeach; ?>
                </span>
                <?php else: ?>
                <span class="eq-hub__ph eq-hub__ph--coll" aria-hidden="true">
                    <svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="8" width="44" height="56" rx="4" stroke="currentColor" stroke-width="2"/><path d="M22 28h20M22 38h14M22 48h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </span>
                <?php endif; ?>
                <span class="eq-hub__skeleton" aria-hidden="true"></span>
            </span>
            <span class="eqx-collcard__body">
                <strong translate="no"><?= $h((string) ($c['name'] ?? '')) ?></strong>
                <span><?= $h($visibilityLabel((string) ($c['visibility'] ?? 'personal'))) ?> · <?= $count ?> tenue<?= $count > 1 ? 's' : '' ?></span>
            </span>
        </a>
        <?php if (!empty($c['mine'])): ?>
        <a class="eqx-collcard__edit eq-hub__btn eq-hub__btn--sm eq-hub__btn--outline" href="<?= $h(url('equipment') . '?collection=' . (int) $c['id'] . '&edit_collection=1') ?>" data-eq-collection-edit="<?= (int) $c['id'] ?>">Modifier</a>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/_pagination.php'; ?>
<?php endif; ?>
