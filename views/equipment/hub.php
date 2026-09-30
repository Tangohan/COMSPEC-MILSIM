<?php
declare(strict_types=1);
$wardrobes = $wardrobes ?? [];
$mineWardrobes = $mineWardrobes ?? [];
$collections = $collections ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$equipmentKindLabels = is_array($equipmentKindLabels ?? null) ? $equipmentKindLabels : \App\Support\ArsenalLoadoutItems::kindLabels();
$migrationMissing = !empty($migrationMissing);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$coverHint = (string) ($coverHint ?? \App\Support\EquipmentCoverStorage::hintText());
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lower = static function (string $v): string {
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
};
$visibilityLabel = static function (string $v): string {
    return match ($v) {
        'unit' => 'Unité',
        'tenant' => 'Communauté',
        default => 'Personnel',
    };
};
$tenueCount = count($wardrobes);
$catalogPayload = [
    'wardrobes' => array_map(static function (array $w) use ($visibilityLabel): array {
        return [
            'id' => (int) ($w['id'] ?? 0),
            'name' => (string) ($w['name'] ?? ''),
            'display_name' => (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? ''))),
            'cover_url' => $w['cover_url'] ?? null,
            'collection_id' => $w['collection_id'] ?? null,
            'collection_name' => $w['collection_name'] ?? null,
            'owner_label' => (string) (($w['owner_label'] ?? '') !== '' ? $w['owner_label'] : 'Membre'),
            'mine' => !empty($w['mine']),
            'is_favorite' => !empty($w['is_favorite']),
            'kinds' => array_values(is_array($w['kinds'] ?? null) ? $w['kinds'] : []),
            'created_at' => (string) ($w['created_at'] ?? ''),
            'updated_at' => (string) ($w['updated_at'] ?? ''),
        ];
    }, $wardrobes),
    'collections' => array_map(static function (array $c) use ($visibilityLabel): array {
        return [
            'id' => (int) ($c['id'] ?? 0),
            'name' => (string) ($c['name'] ?? ''),
            'description' => (string) ($c['description'] ?? ''),
            'cover_url' => $c['cover_url'] ?? null,
            'mosaic' => array_values(is_array($c['mosaic'] ?? null) ? $c['mosaic'] : []),
            'visibility' => (string) ($c['visibility'] ?? 'personal'),
            'visibility_label' => $visibilityLabel((string) ($c['visibility'] ?? 'personal')),
            'wardrobe_count' => (int) ($c['wardrobe_count'] ?? 0),
            'mine' => !empty($c['mine']),
        ];
    }, $collections),
    'kinds' => $equipmentKindLabels,
    'urls' => [
        'tenue' => url('equipment/tenues/'),
        'hub' => url('equipment'),
        'storeCollection' => url('equipment/collections'),
    ],
    'csrf' => $csrfToken,
    'coverHint' => $coverHint,
];
$mineCatalog = array_map(static function (array $w): array {
    return [
        'id' => (int) ($w['id'] ?? 0),
        'name' => (string) ($w['name'] ?? ''),
        'display_name' => (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? ''))),
        'cover_url' => $w['cover_url'] ?? null,
        'kinds' => array_values(is_array($w['kinds'] ?? null) ? $w['kinds'] : []),
        'collection_id' => $w['collection_id'] ?? null,
    ];
}, $mineWardrobes);
?>
<div class="eq-hub" id="eq-catalog" data-eq-catalog>
    <header class="eq-hub__banner">
        <div class="eq-hub__banner-copy">
            <p class="eq-hub__kicker">Communauté</p>
            <h1>Équipement</h1>
            <p class="eq-hub__count" data-eq-count><?= (int) $tenueCount ?> tenue<?= $tenueCount > 1 ? 's' : '' ?></p>
        </div>
        <div class="eq-hub__banner-actions">
            <button type="button" class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-open="tenue-help">Nouvelle tenue</button>
            <button type="button" class="eq-hub__btn" data-eq-open="collection-new">Nouvelle collection</button>
        </div>
    </header>

    <div class="eq-hub__body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

        <?php if ($migrationMissing): ?>
        <p class="eq-hub__empty">Cette page n’est pas encore prête sur cette instance. Demandez à l’administration d’appliquer la mise à jour, puis rechargez.</p>
        <?php else: ?>

        <section class="eq-hub__collections" aria-labelledby="eq-collections-heading">
            <div class="eq-hub__section-head">
                <h2 id="eq-collections-heading">Collections</h2>
            </div>
            <?php if ($collections === []): ?>
            <p class="eq-hub__empty">Aucune collection pour le moment. Créez-en une pour ranger vos tenues.</p>
            <?php else: ?>
            <ul class="eq-hub__coll-rail" data-eq-collections>
                <?php foreach ($collections as $c): ?>
                <?php
                    $mosaic = is_array($c['mosaic'] ?? null) ? $c['mosaic'] : [];
                    $hasCover = !empty($c['cover_url']);
                    $count = (int) ($c['wardrobe_count'] ?? 0);
                    $vis = $visibilityLabel((string) ($c['visibility'] ?? 'personal'));
                ?>
                <li>
                    <button
                        type="button"
                        class="eq-hub__coll-card"
                        data-eq-filter-collection="<?= (int) $c['id'] ?>"
                        aria-pressed="false"
                    >
                        <span class="eq-hub__media eq-hub__media--coll">
                            <?php if ($hasCover): ?>
                            <img class="eq-hub__img" src="<?= $h($c['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                            <?php elseif ($mosaic !== []): ?>
                            <span class="eq-hub__mosaic" aria-hidden="true">
                                <?php foreach (array_slice($mosaic, 0, 4) as $mUrl): ?>
                                <img src="<?= $h($mUrl) ?>" alt="" loading="lazy" decoding="async">
                                <?php endforeach; ?>
                            </span>
                            <?php else: ?>
                            <span class="eq-hub__ph eq-hub__ph--coll" aria-hidden="true">
                                <svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="8" width="44" height="56" rx="4" stroke="currentColor" stroke-width="2"/><path d="M22 28h20M22 38h14M22 48h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </span>
                            <?php endif; ?>
                            <span class="eq-hub__skeleton" aria-hidden="true"></span>
                        </span>
                        <span class="eq-hub__coll-meta">
                            <strong><?= $h($c['name'] ?? '') ?></strong>
                            <span><?= $h($vis) ?> · <?= $count ?> tenue<?= $count > 1 ? 's' : '' ?></span>
                        </span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <div class="eq-hub__layout">
            <aside class="eq-hub__filters" aria-label="Filtres du catalogue">
                <div class="eq-hub__filter-block">
                    <h3>Collection</h3>
                    <label class="eq-hub__check">
                        <input type="checkbox" data-eq-avail="none"> Sans collection
                    </label>
                    <label class="eq-hub__check">
                        <input type="checkbox" data-eq-avail="in"> En collection
                    </label>
                    <div class="eq-hub__filter-scroll" data-eq-filter-collections>
                        <?php foreach ($collections as $c): ?>
                        <label class="eq-hub__check">
                            <input type="checkbox" value="<?= (int) $c['id'] ?>" data-eq-collection-cb>
                            <?= $h($c['name'] ?? '') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="eq-hub__filter-block">
                    <h3>Type d’équipement</h3>
                    <?php foreach ($equipmentKindLabels as $kind => $label): ?>
                    <label class="eq-hub__check">
                        <input type="checkbox" value="<?= $h($kind) ?>" data-eq-kind-cb>
                        <?= $h($label) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="eq-hub__link-btn" data-eq-clear-filters hidden>Effacer les filtres</button>
            </aside>

            <div class="eq-hub__main">
                <div class="eq-hub__toolbar">
                    <label class="eq-hub__search">
                        <span class="visually-hidden">Rechercher une tenue</span>
                        <input type="search" placeholder="Rechercher une tenue…" data-eq-search autocomplete="off">
                    </label>
                    <label class="eq-hub__sort">
                        <span class="visually-hidden">Trier</span>
                        <select data-eq-sort class="bo-select">
                            <option value="name">Alphabétique</option>
                            <option value="date">Date d’ajout</option>
                            <option value="collection">Collection</option>
                        </select>
                    </label>
                </div>

                <div class="eq-hub__active-filters" data-eq-active-filters hidden></div>
                <p class="eq-hub__hint" data-eq-edit-collection-wrap hidden>
                    <a href="#" data-eq-edit-collection class="eq-hub__link-btn">Modifier cette collection</a>
                </p>

                <h2 class="eq-hub__grid-title">Toutes les tenues</h2>
                <?php if ($wardrobes === []): ?>
                <p class="eq-hub__empty">Aucune tenue n’a encore été envoyée depuis l’arsenal.</p>
                <?php else: ?>
                <ul class="eq-hub__grid" data-eq-grid>
                    <?php foreach ($wardrobes as $w): ?>
                    <?php
                        $display = (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? '')));
                        $kinds = is_array($w['kinds'] ?? null) ? $w['kinds'] : [];
                        $cid = (int) ($w['collection_id'] ?? 0);
                    ?>
                    <li
                        data-eq-card
                        data-id="<?= (int) $w['id'] ?>"
                        data-name="<?= $h($lower((string) ($w['name'] ?? ''))) ?>"
                        data-display="<?= $h($lower($display)) ?>"
                        data-collection="<?= $cid ?>"
                        data-collection-name="<?= $h($lower((string) ($w['collection_name'] ?? ''))) ?>"
                        data-kinds="<?= $h(implode(',', $kinds)) ?>"
                        data-created="<?= $h($w['created_at'] ?? '') ?>"
                        data-owner="<?= $h($lower((string) ($w['owner_label'] ?? ''))) ?>"
                    >
                        <button type="button" class="eq-hub__card" data-eq-quickview="<?= (int) $w['id'] ?>">
                            <span class="eq-hub__media eq-hub__media--portrait">
                                <?php if (!empty($w['cover_url'])): ?>
                                <img class="eq-hub__img" src="<?= $h($w['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                <span class="eq-hub__ph" aria-hidden="true">
                                    <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/>
                                        <path d="M28 54h24M32 64h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>
                                <?php endif; ?>
                                <span class="eq-hub__skeleton" aria-hidden="true"></span>
                                <span class="eq-hub__quick">Aperçu rapide</span>
                            </span>
                            <span class="eq-hub__card-body">
                                <strong><?= $h($display) ?><?php if (!empty($w['is_favorite'])): ?> ★<?php endif; ?></strong>
                                <span><?= $h(($w['owner_label'] ?? '') !== '' ? $w['owner_label'] : 'Membre') ?>
                                    · <?= $h($w['collection_name'] ?? 'Sans collection') ?></span>
                            </span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="eq-hub__empty" data-eq-no-results hidden>Aucune tenue ne correspond à ces filtres.</p>
                <?php endif; ?>

                <?php if ($equipmentClasses !== []): ?>
                <section class="eq-hub__docs-block">
                    <h2>Fiches matériel</h2>
                    <p class="eq-hub__hint">Référentiels et documents associés, en complément des tenues.</p>
                    <ul class="eq-hub__docs">
                        <?php foreach ($equipmentClasses as $c): ?>
                        <li><a href="<?= $h(url('equipment/' . ($c['slug'] ?? ''))) ?>"><?= $h($c['name'] ?? '') ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick-view modal -->
    <dialog class="eq-hub__dialog" id="eq-quickview" aria-labelledby="eq-qv-title">
        <div class="eq-hub__dialog-shell" data-eq-qv-shell>
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <div class="eq-hub__qv" data-eq-qv-view>
                <div class="eq-hub__qv-media">
                    <div class="eq-hub__qv-stage" data-eq-qv-stage>
                        <span class="eq-hub__ph eq-hub__ph--lg" aria-hidden="true">
                            <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/>
                            </svg>
                        </span>
                    </div>
                    <div class="eq-hub__qv-thumbs" data-eq-qv-thumbs hidden></div>
                </div>
                <div class="eq-hub__qv-panel">
                    <h2 id="eq-qv-title" data-eq-qv-title>Tenue</h2>
                    <button type="button" class="eq-hub__badge" data-eq-qv-collection hidden></button>
                    <p class="eq-hub__qv-hint" data-eq-qv-hint></p>
                    <div class="eq-hub__qv-items" data-eq-qv-items></div>
                    <div class="eq-hub__qv-actions">
                        <p class="eq-hub__btn eq-hub__btn--primary-static" title="Action disponible depuis l’arsenal en jeu">Récupérer en jeu</p>
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-qv-edit hidden>Modifier</button>
                    </div>
                </div>
            </div>
            <div class="eq-hub__qv-edit" data-eq-qv-edit-panel hidden>
                <h2>Modifier la tenue</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-qv-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="_return" value="hub">
                    <label>Photo de présentation
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <label>Collection
                        <select name="collection_id" class="bo-select" data-eq-qv-collection-select>
                            <option value="0">Sans collection</option>
                        </select>
                    </label>
                    <label>Note
                        <textarea name="notes" rows="2" maxlength="255" data-eq-qv-notes></textarea>
                    </label>
                    <div class="eq-hub__form-actions">
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-qv-back>Retour à l’aperçu</button>
                        <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    </div>
                </form>
                <form method="post" class="eq-hub__danger" data-eq-qv-delete data-ui-confirm="1" data-ui-confirm-title="Retirer la tenue" data-ui-confirm-body="Retirer cette tenue d’Athena ?">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la tenue</button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Nouvelle collection -->
    <dialog class="eq-hub__dialog" id="eq-collection-new" aria-labelledby="eq-col-new-title">
        <div class="eq-hub__dialog-shell eq-hub__dialog-shell--wide">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-col-new-title">Nouvelle collection</h2>
            <form method="post" action="<?= $h(url('equipment/collections')) ?>" enctype="multipart/form-data" class="eq-hub__form eq-hub__form--collection" data-eq-collection-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="eq-hub__form-grid">
                    <label>Nom
                        <input type="text" name="name" required maxlength="120" placeholder="Assaut nocturne">
                    </label>
                    <label>Qui peut s’en servir
                        <select name="visibility" class="bo-select">
                            <option value="personal">Moi seulement</option>
                            <option value="unit">Mon unité</option>
                            <option value="tenant">Toute la communauté</option>
                        </select>
                    </label>
                </div>
                <label>Présentation
                    <textarea name="description" rows="2" maxlength="500" placeholder="Quand porter ce kit, pour qui, contraintes."></textarea>
                </label>
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?> Sans photo, une mosaïque des tenues sélectionnées sera utilisée.</span>
                </label>

                <fieldset class="eq-hub__picker">
                    <legend>
                        Tenues à inclure
                        <span class="eq-hub__picker-count" data-eq-pick-count>0 sélectionnée</span>
                    </legend>
                    <div class="eq-hub__picker-toolbar">
                        <input type="search" placeholder="Rechercher une tenue…" data-eq-pick-search autocomplete="off">
                        <select data-eq-pick-kind class="bo-select">
                            <option value="">Tous les types</option>
                            <?php foreach ($equipmentKindLabels as $kind => $label): ?>
                            <option value="<?= $h($kind) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($mineWardrobes === []): ?>
                    <p class="eq-hub__hint">En jeu, ouvrez l’arsenal, puis le bandeau Athena en haut de l’écran, et envoyez vos tenues. Elles apparaîtront ici pour les ranger dans une collection.</p>
                    <?php else: ?>
                    <div class="eq-hub__picker-grid" data-eq-pick-grid>
                        <?php foreach ($mineWardrobes as $w): ?>
                        <?php
                            $display = (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? '')));
                            $kinds = is_array($w['kinds'] ?? null) ? $w['kinds'] : [];
                        ?>
                        <label
                            class="eq-hub__pick"
                            data-eq-pick
                            data-name="<?= $h($lower($display . ' ' . (string) ($w['name'] ?? ''))) ?>"
                            data-kinds="<?= $h(implode(',', $kinds)) ?>"
                        >
                            <input type="checkbox" name="wardrobe_ids[]" value="<?= (int) $w['id'] ?>" hidden>
                            <span class="eq-hub__media eq-hub__media--pick">
                                <?php if (!empty($w['cover_url'])): ?>
                                <img class="eq-hub__img" src="<?= $h($w['cover_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                <span class="eq-hub__ph" aria-hidden="true">
                                    <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/>
                                    </svg>
                                </span>
                                <?php endif; ?>
                                <span class="eq-hub__pick-mark" aria-hidden="true">✓</span>
                            </span>
                            <span class="eq-hub__pick-name"><?= $h($display) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </fieldset>
                <button type="submit" class="eq-hub__btn">Créer la collection</button>
            </form>
        </div>
    </dialog>

    <!-- Aide nouvelle tenue -->
    <dialog class="eq-hub__dialog" id="eq-tenue-help" aria-labelledby="eq-tenue-help-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-tenue-help-title">Nouvelle tenue</h2>
            <p class="eq-hub__lead-plain">Les tenues se créent depuis l’arsenal en jeu : ouvrez l’équipement, bandeau Athena en haut, puis <strong>Envoyer cette</strong> ou <strong>Envoyer toutes</strong>. Elles apparaissent ensuite dans ce catalogue.</p>
            <button type="button" class="eq-hub__btn" data-eq-close>Compris</button>
        </div>
    </dialog>

    <script type="application/json" id="eq-catalog-data"><?= json_encode($catalogPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
    <script type="application/json" id="eq-mine-data"><?= json_encode($mineCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
</div>
