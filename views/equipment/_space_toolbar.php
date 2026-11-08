<?php
declare(strict_types=1);
/**
 * Recherche, filtre par type et tri en haut d'un espace (formulaire GET : l'adresse reflète les filtres, le bouton
 * retour du navigateur les retrouve). Variables : $space, $nav, $toolbarKinds (bool), $toolbarSort (bool),
 * $toolbarPlaceholder, $equipmentKindLabels, $h.
 */
$toolbarKinds = !empty($toolbarKinds);
$toolbarSort = !empty($toolbarSort);
$q = (string) ($nav['q'] ?? '');
$type = (string) ($nav['type'] ?? '');
$sortNow = (string) ($nav['sort'] ?? 'name');
$collectionNow = (int) ($nav['collection'] ?? 0);
$filtering = $q !== '' || $type !== '' || ($toolbarSort && $sortNow !== 'name');
$resetUrl = \App\Support\EquipmentSpaces::url(['espace' => $space, 'collection' => $collectionNow]);
$pagedNow = is_array($nav['paged'] ?? null) ? $nav['paged'] : null;
?>
<form method="get" action="<?= $h(url('equipment')) ?>" class="eqx-toolbar" role="search" data-eq-autosubmit>
    <input type="hidden" name="espace" value="<?= $h($space) ?>">
    <?php if ($collectionNow > 0): ?><input type="hidden" name="collection" value="<?= $collectionNow ?>"><?php endif; ?>
    <label class="eqx-toolbar__search">
        <span class="visually-hidden">Rechercher</span>
        <input type="search" name="q" value="<?= $h($q) ?>" placeholder="<?= $h((string) ($toolbarPlaceholder ?? 'Rechercher…')) ?>" autocomplete="off">
    </label>
    <?php if ($toolbarKinds): ?>
    <label>
        <span class="visually-hidden">Type d’équipement</span>
        <select name="type" class="bo-select">
            <option value="">Tous les types</option>
            <?php foreach ($equipmentKindLabels as $kind => $label): ?>
            <option value="<?= $h($kind) ?>"<?= $type === (string) $kind ? ' selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <?php if ($toolbarSort): ?>
    <label>
        <span class="visually-hidden">Trier</span>
        <select name="tri" class="bo-select">
            <option value="name"<?= $sortNow === 'name' ? ' selected' : '' ?>>Alphabétique</option>
            <option value="date"<?= $sortNow === 'date' ? ' selected' : '' ?>>Date d’ajout</option>
            <option value="collection"<?= $sortNow === 'collection' ? ' selected' : '' ?>>Collection</option>
        </select>
    </label>
    <?php endif; ?>
    <button type="submit" class="eq-hub__btn">Filtrer</button>
    <?php if ($filtering): ?><a class="eq-hub__link-btn" href="<?= $h($resetUrl) ?>">Effacer les filtres</a><?php endif; ?>
    <?php if ($pagedNow !== null): ?>
    <p class="eqx-toolbar__count" role="status"><?= (int) $pagedNow['total'] ?> résultat<?= (int) $pagedNow['total'] > 1 ? 's' : '' ?></p>
    <?php endif; ?>
</form>
