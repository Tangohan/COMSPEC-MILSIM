<?php
declare(strict_types=1);
/**
 * Espace Kits de départ : présentation de l'offre sans la formule, sinon les kits proposés au membre (tous ceux de la
 * communauté pour un responsable). Création dans une fenêtre ; détails et gestion d'un kit : <details> ouverts en
 * fenêtre par le script. Noms de kits, d'unités et d'objets échappés et marqués translate="no".
 */
$paged = is_array($nav['paged'] ?? null) ? $nav['paged'] : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$creating = $canManageKits && !empty($nav['creer']);
?>
<?php if (!$kitsAllowed): ?>
<div class="eqx-offer">
    <p class="eqx-offer__tag">Formule Pro et plus</p>
    <h2>Donnez à chaque recrue une tenue de départ</h2>
    <p>Les responsables créent un kit à partir d’une tenue remontée du jeu ou avec l’assistant, puis le proposent à une unité ou à un rôle. Chaque membre le retrouve ici et le copie dans ses tenues en un clic.</p>
    <ul class="eqx-offer__list">
        <li>Des kits par unité ou par rôle, toujours limités à votre communauté.</li>
        <li>Un assistant qui propose des objets que vos joueurs ont réellement utilisés en jeu, emplacement par emplacement, et signale ce qui manque.</li>
        <li>Des tenues au format de l’arsenal ACE, prêtes à enfiler.</li>
    </ul>
    <p class="eqx-offer__free">Reste gratuit pour tous : consulter les tenues, créer et modifier les siennes, les favoris et l’export ACE.</p>
    <div class="eqx-actions">
        <a class="eq-hub__btn" href="<?= $h($upgradeUrl) ?>">Voir les formules</a>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/assistant')) ?>" data-ath-modal>Découvrir l’assistant</a>
    </div>
    <?php if (!$canManageKits): ?>
    <p class="eq-hub__hint">Pour activer cette offre, parlez-en à un responsable de votre communauté.</p>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="eqx-spacehead">
    <p class="eqx-intro">Des tenues prêtes à l’emploi, proposées par votre communauté à une unité, à un rôle ou à tous. Copiez-en une dans vos tenues pour la modifier.</p>
    <?php if ($canManageKits): ?>
    <a class="eq-hub__btn" href="<?= $h(url('equipment') . '?espace=kits&creer=kit') ?>" data-eq-open="kit-new">Créer un kit</a>
    <?php endif; ?>
</div>
<?php if ($creating): ?>
<section class="eqx-create" data-eq-detail-fallback aria-label="Créer un kit de départ">
    <?php require __DIR__ . '/_kit_create.php'; ?>
</section>
<?php endif; ?>
<?php
$toolbarKinds = false;
$toolbarSort = false;
$toolbarPlaceholder = 'Rechercher un kit, une unité, un rôle…';
require __DIR__ . '/_space_toolbar.php';
?>
<?php if ($paged['items'] === []): ?>
<div class="eqx-empty">
    <?php if ((string) ($nav['q'] ?? '') !== ''): ?>
    <p><strong>Aucun kit ne correspond à cette recherche.</strong></p>
    <?php else: ?>
    <p><strong><?= $canManageKits ? 'Aucun kit de départ pour le moment.' : 'Aucun kit ne vous est proposé pour le moment.' ?></strong></p>
    <p><?= $canManageKits
        ? 'Créez un premier kit : à partir d’une tenue remontée du jeu, ou de zéro avec l’assistant.'
        : 'Quand un responsable en propose un à votre unité ou à votre rôle, il apparaît ici.' ?></p>
    <?php endif; ?>
</div>
<?php else: ?>
<ul class="eqx-kits">
    <?php foreach ($paged['items'] as $kit): ?>
    <?php require __DIR__ . '/_kit_card.php'; ?>
    <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/_pagination.php'; ?>
<?php endif; ?>
<?php endif; ?>
