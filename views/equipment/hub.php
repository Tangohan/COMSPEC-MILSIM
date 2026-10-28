<?php
declare(strict_types=1);
$wardrobes = $wardrobes ?? [];
$mineWardrobes = $mineWardrobes ?? [];
$collections = $collections ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$dotationItems = $dotationItems ?? [];
$roleKits = is_array($roleKits ?? null) ? $roleKits : [];
$myGameKit = is_array($myGameKit ?? null) ? $myGameKit : null;
$myGameKitWardrobeId = 0;
foreach ($mineWardrobes as $mw) {
    if ((string) ($mw['slug'] ?? '') === \App\Services\GameEquipment\PlayerKitService::MEMBER_SLUG) {
        $myGameKitWardrobeId = (int) ($mw['id'] ?? 0);
        break;
    }
}
$canManageCatalog = !empty($canManageCatalog);
$canManageDotation = !empty($canManageDotation);
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
    'wardrobes' => array_map(static function (array $w): array {
        return [
            'id' => (int) ($w['id'] ?? 0),
            'name' => (string) ($w['name'] ?? ''),
            'display_name' => (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? ''))),
            'description' => (string) ($w['description'] ?? ''),
            'cover_url' => $w['cover_url'] ?? null,
            'gallery_urls' => array_values(is_array($w['gallery_urls'] ?? null) ? $w['gallery_urls'] : []),
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
    'fiches' => array_map(static function (array $c): array {
        return [
            'id' => (int) ($c['id'] ?? 0),
            'name' => (string) ($c['name'] ?? ''),
            'slug' => (string) ($c['slug'] ?? ''),
            'category' => (string) ($c['category'] ?? ''),
            'description' => (string) ($c['description'] ?? ''),
            'cover_url' => $c['cover_url'] ?? null,
        ];
    }, $equipmentClasses),
    'dotation' => array_map(static function (array $d): array {
        return [
            'id' => (int) ($d['id'] ?? 0),
            'code' => (string) ($d['code'] ?? ''),
            'name' => (string) ($d['name'] ?? ''),
            'category' => (string) ($d['category'] ?? ''),
            'description' => (string) ($d['description'] ?? ''),
            'cover_url' => $d['cover_url'] ?? null,
            'issued_count' => (int) ($d['issued_count'] ?? 0),
        ];
    }, $dotationItems),
    'kinds' => $equipmentKindLabels,
    'canManageCatalog' => $canManageCatalog,
    'canManageDotation' => $canManageDotation,
    'urls' => [
        'tenue' => url('equipment/tenues/'),
        'collection' => url('equipment/collections/'),
        'fiche' => url('equipment/fiches/'),
        'dotation' => url('equipment/dotation/'),
        'hub' => url('equipment'),
        'aceExport' => url('equipment/ace-export'),
        'storeCollection' => url('equipment/collections'),
        'storeFiche' => url('equipment/fiches'),
        'storeDotation' => url('equipment/dotation'),
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
            <?php if ($canManageCatalog): ?>
            <button type="button" class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-open="fiche-new">Nouvelle fiche</button>
            <?php endif; ?>
            <?php if ($canManageDotation): ?>
            <button type="button" class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-open="dotation-new">Nouvel article</button>
            <?php endif; ?>
        </div>
    </header>

    <div class="eq-hub__body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

        <?php if ($migrationMissing): ?>
        <p class="eq-hub__empty">Cette page n’est pas encore prête sur cette instance. Demandez à l’administration d’appliquer la mise à jour, puis rechargez.</p>
        <?php else: ?>

        <?php if ($roleKits !== []): ?>
        <section class="eq-rolekits" aria-labelledby="eq-rolekits-heading">
            <div class="eq-hub__section-head">
                <h2 id="eq-rolekits-heading">Kits proposés pour mon rôle</h2>
            </div>
            <ul class="eq-rolekits__list">
                <?php foreach ($roleKits as $rk): ?>
                <?php $rkUrl = !empty($rk['wardrobe_id']) ? url('equipment/tenues/' . (int) $rk['wardrobe_id']) : ''; ?>
                <li>
                    <<?= $rkUrl !== '' ? 'a href="' . $h($rkUrl) . '"' : 'div' ?> class="eq-rolekits__card">
                        <span class="eq-rolekits__role" translate="no"><?= $h((string) ($rk['role_name'] ?? '') !== '' ? (string) $rk['role_name'] : 'Tous les rôles') ?></span>
                        <span class="eq-rolekits__name" translate="no"><?= $h((string) ($rk['name'] ?? '')) ?></span>
                        <?php if (trim((string) ($rk['description'] ?? '')) !== ''): ?>
                        <span class="eq-rolekits__mods"><?= $h(mb_strimwidth((string) $rk['description'], 0, 140, '…')) ?></span>
                        <?php endif; ?>
                        <span class="eq-rolekits__mods">Mods requis : <?= ($rk['required_mods'] ?? []) === [] ? 'aucun' : '<span translate="no">' . $h(implode(', ', (array) $rk['required_mods'])) . '</span>' ?></span>
                    </<?= $rkUrl !== '' ? 'a' : 'div' ?>>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($myGameKit !== null): ?>
        <?php $mkWhen = strtotime((string) ($myGameKit['last_seen_at'] ?? '')) ?: null; ?>
        <section class="eq-mykit" aria-labelledby="eq-mykit-heading">
            <div class="eq-hub__section-head">
                <h2 id="eq-mykit-heading">Mon kit en jeu</h2>
            </div>
            <div class="eq-mykit__card">
                <div class="eq-mykit__top">
                    <p class="eq-mykit__lead">
                        Dernier équipement que vous avez porté en jeu<?= $mkWhen !== null ? ', le ' . $h(date('d/m/Y à H:i', $mkWhen)) : '' ?><?= (string) ($myGameKit['role'] ?? '') !== '' ? ' (<span translate="no">' . $h((string) $myGameKit['role']) . '</span>)' : '' ?>.
                        Remonté automatiquement par le mod, rien à saisir.
                    </p>
                    <div class="eq-mykit__actions">
                        <form method="post" action="<?= $h(url('equipment/mon-kit/tenue')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <button type="submit" class="eq-hub__btn"><?= $myGameKitWardrobeId > 0 ? 'Mettre à jour ma tenue' : 'Enregistrer dans mes tenues' ?></button>
                        </form>
                        <?php if ($myGameKitWardrobeId > 0): ?>
                        <a class="eq-hub__btn eq-hub__btn--ghost" href="<?= $h(url('equipment') . '?tenue=' . $myGameKitWardrobeId) ?>">Voir la tenue</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="eq-mykit__sections">
                    <?php foreach ($myGameKit['sections'] as $sec): ?>
                    <div class="eq-mykit__sec">
                        <h3><?= $h((string) $sec['title']) ?></h3>
                        <ul>
                            <?php foreach ($sec['items'] as $it): ?>
                            <li><span translate="no"><?= $h((string) $it['name']) ?></span><?php if ((int) $it['qty'] > 1): ?> <em>×<?= (int) $it['qty'] ?></em><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

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
                <nav class="eq-hub__tabs" aria-label="Sections catalogue" data-eq-tabs>
                    <button type="button" class="eq-hub__tab is-active" data-eq-tab="tenues" aria-selected="true">Tenues</button>
                    <button type="button" class="eq-hub__tab" data-eq-tab="fiches" aria-selected="false">Fiches matériel <em><?= count($equipmentClasses) ?></em></button>
                    <button type="button" class="eq-hub__tab" data-eq-tab="dotation" aria-selected="false">Dotation <em><?= count($dotationItems) ?></em></button>
                </nav>

                <div data-eq-panel="tenues">
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
                <section class="eq-hub__coll-head" data-eq-coll-head hidden aria-live="polite">
                    <div class="eq-hub__coll-head-copy">
                        <p class="eq-hub__kicker" data-eq-coll-head-meta></p>
                        <h2 data-eq-coll-head-title></h2>
                        <p class="eq-hub__coll-head-desc" data-eq-coll-head-desc hidden></p>
                    </div>
                    <p class="eq-hub__hint" data-eq-edit-collection-wrap hidden>
                        <button type="button" class="eq-hub__link-btn" data-eq-edit-collection>Modifier cette collection</button>
                    </p>
                </section>
                <div class="eq-hub__ace-bar">
                    <p>
                        <strong>Arsenal ACE</strong>
                        <span data-eq-ace-label>Toutes les tenues, prêtes à importer dans « Mes équipements » d’ACE en jeu.</span>
                    </p>
                    <a class="eq-hub__btn" href="<?= $h(url('equipment/ace-export')) ?>" data-eq-ace-export download>Exporter pour ACE</a>
                    <button type="button" class="eq-hub__link-btn" data-eq-ace-help-toggle aria-expanded="false" aria-controls="eq-ace-help">Comment importer ?</button>
                    <ol class="eq-hub__ace-help" id="eq-ace-help" hidden>
                        <li>Téléchargez le fichier, ouvrez-le et copiez tout son texte (Ctrl+A puis Ctrl+C).</li>
                        <li>En jeu, ouvrez l’arsenal ACE, puis « Mes équipements » (Loadouts), onglet de vos équipements.</li>
                        <li>Cliquez sur Importer : chaque tenue apparaît sous son nom. Une tenue seule (bouton « Copier pour ACE » de son aperçu) s’importe depuis l’écran principal de l’arsenal et s’enfile directement.</li>
                        <li>Le bandeau « Athena · Tenues » de l’arsenal fait la même chose sans copier-coller : « Tout basculer dans l’arsenal ACE ».</li>
                    </ol>
                </div>

                <h2 class="eq-hub__grid-title" data-eq-grid-title>Toutes les tenues</h2>
                <?php if ($wardrobes === []): ?>
                <p class="eq-hub__empty">Aucune tenue n’a encore été envoyée depuis l’arsenal.</p>
                <?php else: ?>
                <ul class="eq-hub__grid" data-eq-grid>
                    <?php foreach ($wardrobes as $w): ?>
                    <?php
                        $display = (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? '')));
                        $kinds = is_array($w['kinds'] ?? null) ? $w['kinds'] : [];
                        $cid = (int) ($w['collection_id'] ?? 0);
                        $preview = is_array($w['preview'] ?? null) ? $w['preview'] : [];
                        $previewNames = implode(' · ', array_slice(array_map(static fn (array $p): string => (string) ($p['name'] ?? ''), $preview), 0, 3));
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
                        data-items="<?= $h($lower($previewNames)) ?>"
                    >
                        <button type="button" class="eq-hub__card" data-eq-quickview="<?= (int) $w['id'] ?>">
                            <span class="eq-hub__media eq-hub__media--portrait">
                                <?php if (!empty($w['cover_url'])): ?>
                                <img class="eq-hub__img" src="<?= $h($w['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                                <?php elseif ($preview !== []): ?>
                                <span class="eq-hub__kit" aria-hidden="true">
                                    <?php foreach (array_slice($preview, 0, 4) as $p): ?>
                                    <span class="eq-hub__kit-cell">
                                        <?php if (!empty($p['icon'])): ?>
                                        <img src="<?= $h($p['icon']) ?>" alt="" loading="lazy" decoding="async">
                                        <?php else: ?>
                                        <span class="eq-hub__kit-ph"><?= $h(strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($p['name'] ?? '')), 0, 1)) ?: '?') ?></span>
                                        <?php endif; ?>
                                        <em translate="no"><?= $h($p['name'] ?? '') ?></em>
                                    </span>
                                    <?php endforeach; ?>
                                </span>
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
                                <?php if ($previewNames !== ''): ?>
                                <small class="eq-hub__card-items" translate="no"><?= $h($previewNames) ?></small>
                                <?php endif; ?>
                            </span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <p class="eq-hub__empty" data-eq-no-results hidden>Aucune tenue ne correspond à ces filtres.</p>
                <?php endif; ?>
                </div>

                <div data-eq-panel="fiches" hidden>
                    <div class="eq-hub__toolbar">
                        <label class="eq-hub__search">
                            <span class="visually-hidden">Rechercher une fiche</span>
                            <input type="search" placeholder="Rechercher une fiche…" data-eq-fiche-search autocomplete="off">
                        </label>
                    </div>
                    <?php if ($equipmentClasses === []): ?>
                    <p class="eq-hub__empty">Aucune fiche matériel pour le moment.<?= $canManageCatalog ? ' Créez-en une avec « Nouvelle fiche ».' : '' ?></p>
                    <?php else: ?>
                    <ul class="eq-hub__grid" data-eq-fiche-grid>
                        <?php foreach ($equipmentClasses as $c): ?>
                        <li data-eq-fiche-card data-name="<?= $h($lower(($c['name'] ?? '') . ' ' . ($c['category'] ?? '') . ' ' . ($c['description'] ?? ''))) ?>">
                            <button type="button" class="eq-hub__card" data-eq-fiche-view="<?= (int) $c['id'] ?>">
                                <span class="eq-hub__media eq-hub__media--portrait">
                                    <?php if (!empty($c['cover_url'])): ?>
                                    <img class="eq-hub__img" src="<?= $h($c['cover_url']) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                    <span class="eq-hub__ph" aria-hidden="true">
                                        <svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="10" width="40" height="52" rx="3" stroke="currentColor" stroke-width="2"/><path d="M22 28h20M22 38h16M22 48h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    </span>
                                    <?php endif; ?>
                                    <span class="eq-hub__skeleton" aria-hidden="true"></span>
                                    <span class="eq-hub__quick">Voir la fiche</span>
                                </span>
                                <span class="eq-hub__card-body">
                                    <strong><?= $h($c['name'] ?? '') ?></strong>
                                    <span><?= $h(($c['category'] ?? '') !== '' ? $c['category'] : 'Fiche matériel') ?></span>
                                </span>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <div data-eq-panel="dotation" hidden>
                    <div class="eq-hub__toolbar">
                        <label class="eq-hub__search">
                            <span class="visually-hidden">Rechercher un article</span>
                            <input type="search" placeholder="Rechercher un article de dotation…" data-eq-dotation-search autocomplete="off">
                        </label>
                    </div>
                    <?php if ($dotationItems === []): ?>
                    <p class="eq-hub__empty">Aucun article de dotation.<?= $canManageDotation ? ' Créez-en un avec « Nouvel article ».' : '' ?></p>
                    <?php else: ?>
                    <ul class="eq-hub__grid" data-eq-dotation-grid>
                        <?php foreach ($dotationItems as $d): ?>
                        <li data-eq-dotation-card data-name="<?= $h($lower(($d['name'] ?? '') . ' ' . ($d['code'] ?? '') . ' ' . ($d['category'] ?? '') . ' ' . ($d['description'] ?? ''))) ?>">
                            <button type="button" class="eq-hub__card" data-eq-dotation-view="<?= (int) $d['id'] ?>">
                                <span class="eq-hub__media eq-hub__media--portrait">
                                    <?php if (!empty($d['cover_url'])): ?>
                                    <img class="eq-hub__img" src="<?= $h($d['cover_url']) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                    <span class="eq-hub__ph" aria-hidden="true">
                                        <svg viewBox="0 0 64 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 22h24v36H20z" stroke="currentColor" stroke-width="2"/><path d="M26 30h12M26 40h12M26 50h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                    </span>
                                    <?php endif; ?>
                                    <span class="eq-hub__skeleton" aria-hidden="true"></span>
                                    <span class="eq-hub__quick">Voir l’article</span>
                                </span>
                                <span class="eq-hub__card-body">
                                    <strong><?= $h($d['name'] ?? '') ?></strong>
                                    <span><?= $h(($d['code'] ?? '') !== '' ? $d['code'] : 'Article') ?>
                                        · <?= (int) ($d['issued_count'] ?? 0) ?> en dotation</span>
                                </span>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
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
                    <p class="eq-hub__qv-desc" data-eq-qv-desc hidden></p>
                    <p class="eq-hub__qv-hint" data-eq-qv-hint></p>
                    <div class="eq-hub__qv-items" data-eq-qv-items></div>
                    <p class="eq-hub__qv-ace-status" data-eq-qv-ace-status role="status" hidden></p>
                    <div class="eq-hub__qv-actions">
                        <button type="button" class="eq-hub__btn" data-eq-qv-ace-copy hidden>Copier pour ACE</button>
                        <a class="eq-hub__btn eq-hub__btn--outline" data-eq-qv-ace-download hidden download>Télécharger (.txt)</a>
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-qv-edit hidden>Modifier</button>
                    </div>
                </div>
            </div>
            <div class="eq-hub__qv-edit" data-eq-qv-edit-panel hidden>
                <h2>Modifier la tenue</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-qv-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="_return" value="hub">
                    <label>Description (visible par tous)
                        <textarea name="description" rows="3" maxlength="1000" data-eq-qv-description placeholder="Quand porter ce kit, contexte d’emploi, particularités…"></textarea>
                    </label>
                    <label>Photo de présentation (principale)
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <label>Photos supplémentaires (galerie, max 5)
                        <input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
                    </label>
                    <div class="eq-hub__gallery-edit" data-eq-qv-gallery-edit hidden></div>
                    <label>Collection
                        <select name="collection_id" class="bo-select" data-eq-qv-collection-select>
                            <option value="0">Sans collection</option>
                        </select>
                    </label>
                    <label>Note interne (vous seul)
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

    <!-- Éditer collection -->
    <dialog class="eq-hub__dialog" id="eq-collection-edit" aria-labelledby="eq-col-edit-title">
        <div class="eq-hub__dialog-shell eq-hub__dialog-shell--wide">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-col-edit-title">Modifier la collection</h2>
            <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-collection-edit-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="eq-hub__form-grid">
                    <label>Nom
                        <input type="text" name="name" required maxlength="120" data-eq-col-edit-name>
                    </label>
                    <label>Qui peut s’en servir
                        <select name="visibility" class="bo-select" data-eq-col-edit-visibility>
                            <option value="personal">Moi seulement</option>
                            <option value="unit">Mon unité</option>
                            <option value="tenant">Toute la communauté</option>
                        </select>
                    </label>
                </div>
                <label>Présentation
                    <textarea name="description" rows="2" maxlength="500" data-eq-col-edit-description></textarea>
                </label>
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                </label>
                <fieldset class="eq-hub__picker">
                    <legend>Tenues à inclure <span class="eq-hub__picker-count" data-eq-col-edit-count>0 sélectionnée</span></legend>
                    <div class="eq-hub__picker-toolbar">
                        <input type="search" placeholder="Rechercher…" data-eq-col-edit-search autocomplete="off">
                    </div>
                    <div class="eq-hub__picker-grid" data-eq-col-edit-grid></div>
                </fieldset>
                <div class="eq-hub__form-actions">
                    <button type="submit" class="eq-hub__btn">Enregistrer</button>
                </div>
            </form>
            <form method="post" class="eq-hub__danger" data-eq-col-edit-delete data-ui-confirm="1" data-ui-confirm-title="Retirer la collection" data-ui-confirm-body="Retirer cette collection ? Les tenues ne sont pas supprimées.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la collection</button>
            </form>
        </div>
    </dialog>

    <!-- Fiche matériel modal -->
    <dialog class="eq-hub__dialog" id="eq-fiche-view" aria-labelledby="eq-fiche-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <div data-eq-fiche-view-panel>
                <div class="eq-hub__qv" style="grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr)">
                    <div class="eq-hub__qv-stage" data-eq-fiche-stage></div>
                    <div>
                        <p class="eq-hub__badge" data-eq-fiche-cat hidden></p>
                        <h2 id="eq-fiche-title" data-eq-fiche-title>Fiche</h2>
                        <p class="eq-hub__qv-desc" data-eq-fiche-desc></p>
                        <div class="eq-hub__qv-actions">
                            <a class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-fiche-page href="#">Page complète</a>
                            <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-fiche-edit hidden>Modifier</button>
                        </div>
                    </div>
                </div>
            </div>
            <div data-eq-fiche-edit-panel hidden>
                <h2>Modifier la fiche</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-fiche-edit-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label>Nom <input type="text" name="name" required maxlength="255" data-eq-fiche-edit-name></label>
                    <label>Catégorie <input type="text" name="category" maxlength="100" data-eq-fiche-edit-category placeholder="Radio, protection…"></label>
                    <label>Description
                        <textarea name="description" rows="4" data-eq-fiche-edit-description placeholder="Usage, contraintes, doctrine associée…"></textarea>
                    </label>
                    <label>Photo
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <div class="eq-hub__form-actions">
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-fiche-back>Retour</button>
                        <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>

    <?php if ($canManageCatalog): ?>
    <dialog class="eq-hub__dialog" id="eq-fiche-new" aria-labelledby="eq-fiche-new-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-fiche-new-title">Nouvelle fiche matériel</h2>
            <form method="post" action="<?= $h(url('equipment/fiches')) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Nom <input type="text" name="name" required maxlength="255" placeholder="Gilet porte-plaques"></label>
                <label>Catégorie <input type="text" name="category" maxlength="100" placeholder="Protection"></label>
                <label>Description
                    <textarea name="description" rows="4" placeholder="Décrivez l’usage, le contexte et les contraintes."></textarea>
                </label>
                <label>Photo
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                </label>
                <button type="submit" class="eq-hub__btn">Créer la fiche</button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <!-- Dotation modal -->
    <dialog class="eq-hub__dialog" id="eq-dotation-view" aria-labelledby="eq-dotation-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <div data-eq-dotation-view-panel>
                <div class="eq-hub__qv" style="grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr)">
                    <div class="eq-hub__qv-stage" data-eq-dotation-stage></div>
                    <div>
                        <p class="eq-hub__badge" data-eq-dotation-code hidden></p>
                        <h2 id="eq-dotation-title" data-eq-dotation-title>Article</h2>
                        <p class="eq-hub__qv-desc" data-eq-dotation-desc></p>
                        <div class="eq-hub__qv-actions">
                            <a class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-dotation-admin href="#" hidden>Carnet de dotation</a>
                            <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-dotation-edit hidden>Modifier</button>
                        </div>
                    </div>
                </div>
            </div>
            <div data-eq-dotation-edit-panel hidden>
                <h2>Modifier l’article</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-dotation-edit-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <div class="eq-hub__form-grid">
                        <label>Code <input type="text" name="code" required maxlength="40" data-eq-dotation-edit-code></label>
                        <label>Nom <input type="text" name="name" required maxlength="180" data-eq-dotation-edit-name></label>
                    </div>
                    <label>Catégorie <input type="text" name="category" maxlength="80" data-eq-dotation-edit-category></label>
                    <label>Description
                        <textarea name="description" rows="4" data-eq-dotation-edit-description placeholder="Description de l’article, consignes d’emploi…"></textarea>
                    </label>
                    <label>Photo
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <div class="eq-hub__form-actions">
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-dotation-back>Retour</button>
                        <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>

    <?php if ($canManageDotation): ?>
    <dialog class="eq-hub__dialog" id="eq-dotation-new" aria-labelledby="eq-dotation-new-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-dotation-new-title">Nouvel article de dotation</h2>
            <form method="post" action="<?= $h(url('equipment/dotation')) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="eq-hub__form-grid">
                    <label>Code <input type="text" name="code" required maxlength="40" placeholder="RAD-001"></label>
                    <label>Nom <input type="text" name="name" required maxlength="180" placeholder="Radio AN/PRC"></label>
                </div>
                <label>Catégorie <input type="text" name="category" maxlength="80" placeholder="Radio"></label>
                <label>Description
                    <textarea name="description" rows="4" placeholder="Décrivez l’article et son emploi."></textarea>
                </label>
                <label>Photo
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                </label>
                <button type="submit" class="eq-hub__btn">Créer l’article</button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

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
