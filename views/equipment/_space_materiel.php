<?php
declare(strict_types=1);
/**
 * Espace Matériel : fiches matériel et dotation de la communauté (onglets internes, recherche instantanée).
 * Anciennes adresses ?tab=fiches|dotation, ?fiche=ID, ?dotation=ID conservées (fenêtres ouvertes par le script).
 */
$tabNow = (string) ($eqNav['tab'] ?? 'fiches') === 'dotation' ? 'dotation' : 'fiches';
?>
<div class="eqx-spacehead">
    <p class="eqx-intro">Les fiches matériel et la dotation de la communauté.</p>
    <div class="eqx-collhead__actions">
        <?php if ($canManageCatalog): ?>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment') . '?espace=materiel') ?>" data-eq-open="fiche-new">Nouvelle fiche</a>
        <?php endif; ?>
        <?php if ($canManageDotation): ?>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment') . '?espace=materiel&tab=dotation') ?>" data-eq-open="dotation-new">Nouvel article</a>
        <?php endif; ?>
    </div>
</div>

<nav class="eq-hub__tabs" aria-label="Fiches matériel et dotation" data-eq-tabs>
    <a class="eq-hub__tab<?= $tabNow === 'fiches' ? ' is-active' : '' ?>" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'materiel'])) ?>" data-eq-tab="fiches" aria-selected="<?= $tabNow === 'fiches' ? 'true' : 'false' ?>">Fiches matériel <em><?= count($equipmentClasses) ?></em></a>
    <a class="eq-hub__tab<?= $tabNow === 'dotation' ? ' is-active' : '' ?>" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'materiel', 'tab' => 'dotation'])) ?>" data-eq-tab="dotation" aria-selected="<?= $tabNow === 'dotation' ? 'true' : 'false' ?>">Dotation <em><?= count($dotationItems) ?></em></a>
</nav>

<div data-eq-panel="fiches"<?= $tabNow === 'fiches' ? '' : ' hidden' ?>>
    <div class="eq-hub__toolbar">
        <label class="eq-hub__search">
            <span class="visually-hidden">Rechercher une fiche</span>
            <input type="search" placeholder="Rechercher une fiche…" data-eq-fiche-search autocomplete="off">
        </label>
    </div>
    <?php if ($equipmentClasses === []): ?>
    <p class="eq-hub__empty">Aucune fiche matériel pour le moment.<?= $canManageCatalog ? ' Créez-en une avec « Nouvelle fiche ».' : '' ?></p>
    <?php else: ?>
    <ul class="eq-hub__grid eqx-grid" data-eq-fiche-grid>
        <?php foreach ($equipmentClasses as $c): ?>
        <li data-eq-fiche-card data-name="<?= $h($lower(($c['name'] ?? '') . ' ' . ($c['category'] ?? '') . ' ' . ($c['description'] ?? ''))) ?>">
            <button type="button" class="eq-hub__card" data-eq-fiche-view="<?= (int) $c['id'] ?>">
                <span class="eq-hub__media eq-hub__media--portrait">
                    <?php if (!empty($c['cover_url'])): ?>
                    <img class="eq-hub__img" src="<?= $h((string) $c['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                    <span class="eq-hub__ph" aria-hidden="true">
                        <svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="10" width="40" height="52" rx="3" stroke="currentColor" stroke-width="2"/><path d="M22 28h20M22 38h16M22 48h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <?php endif; ?>
                    <span class="eq-hub__skeleton" aria-hidden="true"></span>
                    <span class="eq-hub__quick">Voir la fiche</span>
                </span>
                <span class="eq-hub__card-body">
                    <strong translate="no"><?= $h($c['name'] ?? '') ?></strong>
                    <span translate="no"><?= $h(($c['category'] ?? '') !== '' ? $c['category'] : 'Fiche matériel') ?></span>
                </span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<div data-eq-panel="dotation"<?= $tabNow === 'dotation' ? '' : ' hidden' ?>>
    <div class="eq-hub__toolbar">
        <label class="eq-hub__search">
            <span class="visually-hidden">Rechercher un article</span>
            <input type="search" placeholder="Rechercher un article de dotation…" data-eq-dotation-search autocomplete="off">
        </label>
    </div>
    <?php if ($dotationItems === []): ?>
    <p class="eq-hub__empty">Aucun article de dotation.<?= $canManageDotation ? ' Créez-en un avec « Nouvel article ».' : '' ?></p>
    <?php else: ?>
    <ul class="eq-hub__grid eqx-grid" data-eq-dotation-grid>
        <?php foreach ($dotationItems as $d): ?>
        <li data-eq-dotation-card data-name="<?= $h($lower(($d['name'] ?? '') . ' ' . ($d['code'] ?? '') . ' ' . ($d['category'] ?? '') . ' ' . ($d['description'] ?? ''))) ?>">
            <button type="button" class="eq-hub__card" data-eq-dotation-view="<?= (int) $d['id'] ?>">
                <span class="eq-hub__media eq-hub__media--portrait">
                    <?php if (!empty($d['cover_url'])): ?>
                    <img class="eq-hub__img" src="<?= $h((string) $d['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                    <span class="eq-hub__ph" aria-hidden="true">
                        <svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 22h24v36H20z" stroke="currentColor" stroke-width="2"/><path d="M26 30h12M26 40h12M26 50h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    </span>
                    <?php endif; ?>
                    <span class="eq-hub__skeleton" aria-hidden="true"></span>
                    <span class="eq-hub__quick">Voir l’article</span>
                </span>
                <span class="eq-hub__card-body">
                    <strong translate="no"><?= $h($d['name'] ?? '') ?></strong>
                    <span><span translate="no"><?= $h(($d['code'] ?? '') !== '' ? $d['code'] : 'Article') ?></span> · <?= (int) ($d['issued_count'] ?? 0) ?> en dotation</span>
                </span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
