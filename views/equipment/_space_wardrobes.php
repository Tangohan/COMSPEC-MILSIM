<?php
declare(strict_types=1);
/**
 * Espaces de tenues : Mes tenues, Favoris, Tenues de la communauté, et le contenu d'une collection.
 * Variables de hub.php : $space, $eqNav, $h, $csrfToken, $returnUrl, $spaceQuery, $myGameKit, $collections…
 */
$paged = is_array($eqNav['paged'] ?? null) ? $eqNav['paged'] : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$collectionRow = is_array($eqNav['collection_row'] ?? null) ? $eqNav['collection_row'] : null;
$inCollection = $space === 'collections' && $collectionRow !== null;
$intro = match ($space) {
    'favoris' => 'Les tenues que vous avez marquées d’une étoile, les vôtres comme celles des autres.',
    'communaute' => 'Les tenues partagées par les autres membres. Mettez en favori celles que vous voulez garder sous la main.',
    default => 'Les tenues que vous avez envoyées depuis l’arsenal, enregistrées depuis votre kit en jeu ou créées avec l’assistant.',
};
$filtersActive = (string) ($eqNav['q'] ?? '') !== '' || (string) ($eqNav['type'] ?? '') !== '';
?>
<?php if ($inCollection): ?>
<header class="eqx-collhead">
    <div>
        <p class="eqx-collhead__back"><a href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'collections'])) ?>">← Toutes les collections</a></p>
        <h2 translate="no"><?= $h((string) ($collectionRow['name'] ?? '')) ?></h2>
        <p class="eq-hub__hint"><?= $h($visibilityLabel((string) ($collectionRow['visibility'] ?? 'personal'))) ?> · <?= (int) ($collectionRow['wardrobe_count'] ?? 0) ?> tenue<?= (int) ($collectionRow['wardrobe_count'] ?? 0) > 1 ? 's' : '' ?><?php if (trim((string) ($collectionRow['description'] ?? '')) !== ''): ?> — <span translate="no"><?= $h((string) $collectionRow['description']) ?></span><?php endif; ?></p>
    </div>
    <div class="eqx-collhead__actions">
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/ace-export') . '?collection=' . (int) $collectionRow['id']) ?>" download>Exporter pour ACE</a>
        <?php if (!empty($collectionRow['mine'])): ?>
        <a class="eq-hub__btn" href="<?= $h(url('equipment') . '?collection=' . (int) $collectionRow['id'] . '&edit_collection=1') ?>" data-eq-collection-edit="<?= (int) $collectionRow['id'] ?>">Modifier la collection</a>
        <?php endif; ?>
    </div>
</header>
<?php else: ?>
<p class="eqx-intro"><?= $h($intro) ?></p>
<?php endif; ?>

<?php if ($space === 'mes-tenues' && $myGameKit !== null): ?>
<?php $mkWhen = strtotime((string) ($myGameKit['last_seen_at'] ?? '')) ?: null; ?>
<details class="eqx-mykit" data-eq-details-modal>
    <summary>Mon kit en jeu<?= $mkWhen !== null ? ' — porté le ' . $h(date('d/m/Y à H:i', $mkWhen)) : '' ?></summary>
    <p class="eq-hub__hint">Dernier équipement que vous avez porté en jeu<?= (string) ($myGameKit['role'] ?? '') !== '' ? ' (<span translate="no">' . $h((string) $myGameKit['role']) . '</span>)' : '' ?>. Remonté automatiquement par le mod, rien à saisir.</p>
    <div class="eq-mykit__sections">
        <?php foreach (($myGameKit['sections'] ?? []) as $sec): ?>
        <div class="eq-mykit__sec">
            <h3><?= $h((string) ($sec['title'] ?? '')) ?></h3>
            <ul>
                <?php foreach (($sec['items'] ?? []) as $it): ?>
                <li><span translate="no"><?= $h((string) ($it['name'] ?? '')) ?></span><?php if ((int) ($it['qty'] ?? 1) > 1): ?> <em>×<?= (int) $it['qty'] ?></em><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
    <form method="post" action="<?= $h(url('equipment/mon-kit/tenue')) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <button type="submit" class="eq-hub__btn"><?= $myGameKitWardrobeId > 0 ? 'Mettre à jour ma tenue' : 'Enregistrer dans mes tenues' ?></button>
        <?php if ($myGameKitWardrobeId > 0): ?>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment') . '?tenue=' . $myGameKitWardrobeId) ?>">Voir la tenue</a>
        <?php endif; ?>
    </form>
</details>
<?php endif; ?>

<?php
$toolbarKinds = true;
$toolbarSort = true;
$toolbarPlaceholder = 'Rechercher une tenue, un objet, un membre…';
require __DIR__ . '/_space_toolbar.php';
?>

<?php if ($paged['items'] === []): ?>
<div class="eqx-empty">
    <?php if ($filtersActive): ?>
    <p><strong>Aucune tenue ne correspond à ces filtres.</strong></p>
    <p><a href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => $space, 'collection' => (int) ($eqNav['collection'] ?? 0)])) ?>">Effacer les filtres</a></p>
    <?php elseif ($space === 'favoris'): ?>
    <p><strong>Aucun favori pour le moment.</strong></p>
    <p>Cliquez sur l’étoile d’une tenue pour la retrouver ici en un clic.</p>
    <?php elseif ($space === 'communaute'): ?>
    <p><strong>Aucune tenue partagée pour le moment.</strong></p>
    <p>Quand un membre envoie une tenue depuis l’arsenal et la laisse visible par la communauté, elle apparaît ici.</p>
    <?php elseif ($inCollection): ?>
    <p><strong>Cette collection ne contient aucune tenue pour le moment.</strong></p>
    <?php else: ?>
    <p><strong>Vous n’avez pas encore de tenue.</strong></p>
    <p>En jeu, ouvrez l’arsenal, puis le bandeau Athena en haut de l’écran, et envoyez vos tenues : elles apparaissent ici. Vous pouvez aussi enregistrer le kit que vous portez, ou partir d’un kit de départ.</p>
    <p><a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/assistant')) ?>" data-ath-modal>Essayer l’assistant</a></p>
    <?php endif; ?>
</div>
<?php else: ?>
<ul class="eq-hub__grid eqx-grid" data-eq-grid>
    <?php foreach ($paged['items'] as $w): ?>
    <?php $cardSection = $space; require __DIR__ . '/_wardrobe_card.php'; ?>
    <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/_pagination.php'; ?>
<?php endif; ?>
