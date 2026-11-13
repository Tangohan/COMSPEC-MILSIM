<?php
declare(strict_types=1);
/**
 * Espace Matériel : trois onglets.
 * - Catalogue des objets (par défaut) : tout ce que la communauté peut porter en jeu (jeu de base d'Arma 3, configuration
 *   importée du jeu, objets vus en jeu). Recherche instantanée, familles, source (mod), tri, favoris, tendances, grille
 *   ou liste, fiche détaillée (poids, capacité, compatibilités, configurateur, pré-kit). Dessiné par
 *   public/assets/js/equipment-materiel.js à partir du catalogue du configurateur (JSON mis en cache par le navigateur) ;
 *   la page ne porte que la structure, les états et la configuration ($materielBoot).
 * - Fiches matériel et Dotation : contenus rédigés par la communauté (fenêtres de _dialogs.php).
 * Anciennes adresses ?tab=fiches|dotation, ?fiche=ID, ?dotation=ID conservées ; ?objet=CLASSE ouvre la fiche d'un objet.
 */
$tabNow = (string) ($eqNav['tab'] ?? 'catalogue');
$tabNow = isset(\App\Support\EquipmentMateriel::TABS[$tabNow]) ? $tabNow : 'catalogue';
$canSharePrekits = !empty($canSharePrekits);
$materielBoot = \App\Support\EquipmentMateriel::boot(
    [
        'catalogue' => url('equipment/configurateur/catalogue'),
        'social' => url('equipment/configurateur/membre'),
        'favorite' => url('equipment/configurateur/favori'),
        'prekits' => url('equipment/configurateur/pre-kits'),
        'icons' => url('equipment/icones/'),
        'configurator' => url('equipment/configurateur'),
        'base' => url('equipment/configurateur/catalogue/base'),
    ],
    $csrfToken,
    ['sharePrekits' => $canSharePrekits, 'catalog' => $canManageCatalog],
    $eqBase
);
$tabCounts = ['fiches' => count($equipmentClasses), 'dotation' => count($dotationItems)];
?>
<link href="<?= $h(asset_url('assets/css/equipment-materiel.css')) ?>" rel="stylesheet">
<div class="eqx-spacehead">
    <div>
        <h2><?= $h($tabLabels[$space] ?? '') ?></h2>
        <p class="eqx-intro">Tous les objets que votre communauté peut porter en jeu, avec leur poids, leur capacité et leurs compatibilités. Trouvez un objet, puis envoyez-le au configurateur ou dans un pré-kit.</p>
    </div>
    <div class="eqx-collhead__actions">
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/configurateur')) ?>">Ouvrir le configurateur</a>
        <?php if ($canManageCatalog): ?>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'materiel', 'tab' => 'fiches'])) ?>" data-eq-open="fiche-new">Nouvelle fiche</a>
        <?php endif; ?>
        <?php if ($canManageDotation): ?>
        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'materiel', 'tab' => 'dotation'])) ?>" data-eq-open="dotation-new">Nouvel article</a>
        <?php endif; ?>
    </div>
</div>

<nav class="eq-hub__tabs eqm-tabs" aria-label="Catalogue, fiches matériel et dotation" data-eq-tabs>
    <?php foreach (\App\Support\EquipmentMateriel::TABS as $tabKey => $tabLabel): ?>
    <a class="eq-hub__tab<?= $tabNow === $tabKey ? ' is-active' : '' ?>" href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => 'materiel', 'tab' => $tabKey])) ?>" data-eq-tab="<?= $h($tabKey) ?>" aria-selected="<?= $tabNow === $tabKey ? 'true' : 'false' ?>"><?= $h($tabLabel) ?><?php if (isset($tabCounts[$tabKey])): ?> <em><?= (int) $tabCounts[$tabKey] ?></em><?php else: ?> <em data-eqm-total hidden></em><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<div data-eq-panel="catalogue"<?= $tabNow === 'catalogue' ? '' : ' hidden' ?>>
    <div class="eqm" data-eqm>
        <noscript><p class="eqm-state">La recherche et les filtres du catalogue ont besoin de JavaScript. Activez-le, ou ouvrez le configurateur.</p></noscript>
        <form class="eqm-bar" role="search" method="get" action="<?= $h(url('equipment')) ?>" data-eqm-form>
            <input type="hidden" name="espace" value="materiel">
            <label class="eqm-search">
                <span class="visually-hidden">Rechercher un objet</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 4a6.5 6.5 0 1 1 0 13 6.5 6.5 0 0 1 0-13zM15.5 15.5 20 20"/></svg>
                <input type="search" name="q" placeholder="Nom, classe ou mod : MX, 6.5, carryall, ACE…" autocomplete="off" spellcheck="false" value="<?= $h((string) ($eqNav['q'] ?? '')) ?>" data-eqm-q>
            </label>
            <label class="eqm-field">
                <span>Source</span>
                <select name="source" data-eqm-source>
                    <option value="">Toutes les sources</option>
                </select>
            </label>
            <label class="eqm-field">
                <span>Trier par</span>
                <select name="tri" data-eqm-sort>
                    <?php foreach (\App\Support\EquipmentMateriel::SORTS as $sortKey => $sortLabel): ?>
                    <option value="<?= $h($sortKey) ?>"><?= $h($sortLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="eqm-toggles">
                <button type="button" class="eqm-toggle" aria-pressed="false" data-eqm-only="fav">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>
                    Mes favoris <span class="eqm-toggle__n" data-eqm-fav-count>0</span>
                </button>
                <button type="button" class="eqm-toggle" aria-pressed="false" data-eqm-only="trend" title="Objets portés en jeu, ajoutés aux tenues ou mis en favori par plusieurs membres ces 30 derniers jours">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg>
                    Tendances
                </button>
            </div>
            <div class="eqm-view" role="group" aria-label="Affichage des objets">
                <button type="button" aria-pressed="true" data-eqm-view="grid" title="Grille">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg><span class="visually-hidden">Grille</span>
                </button>
                <button type="button" aria-pressed="false" data-eqm-view="list" title="Liste">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16"/></svg><span class="visually-hidden">Liste</span>
                </button>
            </div>
        </form>

        <div class="eqm-families" role="group" aria-label="Familles d’objets" data-eqm-families></div>
        <div class="eqm-subcats" role="group" aria-label="Catégories de la famille" data-eqm-cats hidden></div>
        <div class="eqm-active" data-eqm-active hidden></div>

        <p class="eqm-count" role="status" data-eqm-count>Chargement du catalogue…</p>

        <div class="eqm-base" data-eqm-base hidden>
            <span data-eqm-base-text>Préparation du catalogue de base d’Arma 3…</span>
            <span class="eqm-base__bar" role="progressbar" aria-label="Catalogue de base" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i data-eqm-base-bar></i></span>
        </div>

        <ul class="eqm-results eqm-results--grid" data-eqm-results aria-busy="true">
            <?php for ($sk = 0; $sk < 8; $sk++): ?>
            <li class="eqm-card eqm-card--skeleton" aria-hidden="true"><span class="eqm-card__ico"></span><span class="eqm-card__body"><i></i><i></i><i></i></span></li>
            <?php endfor; ?>
        </ul>
        <div class="eqm-state" data-eqm-state hidden></div>
        <div class="eqm-more" data-eqm-more hidden>
            <p data-eqm-more-text></p>
            <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eqm-more-btn>Afficher plus d’objets</button>
        </div>
        <span class="eqm-sentinel" data-eqm-sentinel aria-hidden="true"></span>
        <p class="visually-hidden" aria-live="polite" data-eqm-live></p>
    </div>

    <dialog class="eqm-sheet" id="eqm-sheet" aria-labelledby="eqm-sheet-title">
        <div class="eqm-sheet__shell">
            <div class="eqm-sheet__top">
                <button type="button" class="eqm-sheet__back" data-eqm-back hidden>Retour</button>
                <button type="button" class="eqm-sheet__close" data-eqm-close aria-label="Fermer la fiche">×</button>
            </div>
            <div class="eqm-sheet__body" data-eqm-sheet-body>
                <h2 id="eqm-sheet-title">Fiche de l’objet</h2>
            </div>
        </div>
    </dialog>
    <script type="application/json" id="eqm-boot"><?= json_encode($materielBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div>

<div data-eq-panel="fiches"<?= $tabNow === 'fiches' ? '' : ' hidden' ?>>
    <p class="eq-hub__hint">Fiches rédigées par la communauté : présentation d’un équipement, règles d’emploi, photos.</p>
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
    <p class="eq-hub__hint">Articles remis aux membres par la communauté, avec le nombre en circulation.</p>
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
<script src="<?= $h(asset_url('assets/js/equipment-base-catalog.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/equipment-materiel.js')) ?>" defer></script>
