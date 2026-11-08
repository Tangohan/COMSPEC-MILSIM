<?php
declare(strict_types=1);
/**
 * Page /equipment : un bandeau compact, des onglets (un espace visible à la fois, chacun avec son adresse
 * ?espace=…), puis l'espace demandé : Mes tenues, Favoris, Collections, Tenues de la communauté, Kits de départ,
 * Matériel. Recherche, filtres et pagination sont côté serveur (liens et formulaires GET : tout marche sans
 * JavaScript, le script ne fait que fermer les allers-retours : fenêtres, favori en un clic).
 *
 * Tout texte venu du jeu ou des membres (noms de tenues, d'objets, d'unités) est échappé par $h() et marqué
 * translate="no". Les fenêtres (aperçu et modification d'une tenue, collections, fiches, dotation, aide) sont dans
 * _dialogs.php. Thème du portail, aucune image externe, images chargées à la demande.
 */
$wardrobes = $wardrobes ?? [];
$mineWardrobes = $mineWardrobes ?? [];
$gameWardrobes = is_array($gameWardrobes ?? null) ? $gameWardrobes : [];
$collections = $collections ?? [];
$equipmentClasses = $equipmentClasses ?? [];
$dotationItems = $dotationItems ?? [];
$kits = is_array($kits ?? null) ? $kits : [];
$kitsAllowed = !empty($kitsAllowed);
$canManageKits = !empty($canManageKits);
$kitUnitOptions = is_array($kitUnitOptions ?? null) ? $kitUnitOptions : [];
$kitRoleOptions = is_array($kitRoleOptions ?? null) ? $kitRoleOptions : [];
$kitUnitsReady = !empty($kitUnitsReady);
$catalogCount = (int) ($catalogCount ?? 0);
$upgradeUrl = (string) ($upgradeUrl ?? url('platform/upgrade'));
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
$eqNav = is_array($eqNav ?? null) ? $eqNav : [];
$space = \App\Support\EquipmentSpaces::normalize($eqNav['espace'] ?? 'mes-tenues');
$counts = is_array($eqNav['counts'] ?? null) ? $eqNav['counts'] : [];
$detail = is_array($eqNav['detail'] ?? null) ? $eqNav['detail'] : null;
// Adresse de l'espace en cours (retour des formulaires : favori, filtres).
$spaceQuery = [
    'espace' => $space, 'collection' => (int) ($eqNav['collection'] ?? 0), 'q' => (string) ($eqNav['q'] ?? ''), 'type' => (string) ($eqNav['type'] ?? ''),
    'tri' => (string) ($eqNav['sort'] ?? 'name'), 'tab' => $space === 'materiel' ? (string) ($eqNav['tab'] ?? 'fiches') : '', 'page' => (int) ($eqNav['paged']['page'] ?? 1),
];
$returnUrl = \App\Support\EquipmentSpaces::url($spaceQuery);

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
            'is_favorite' => !empty($w['is_favorite_me']),
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
$tabLabels = \App\Support\EquipmentSpaces::SPACES;
?>
<div class="eq-hub eqx" id="eq-catalog" data-eq-catalog data-eq-space="<?= $h($space) ?>">
    <header class="eqx-hero eqx-hero--bar">
        <div class="eqx-hero__inner">
            <div class="eqx-hero__copy">
                <p class="eqx-hero__kicker">Communauté</p>
                <h1>Équipement</h1>
            </div>
            <p class="eqx-hero__lead">Vos tenues, celles de la communauté et les kits de départ, prêts à enfiler en jeu.</p>
            <div class="eqx-hero__actions">
                <a class="eqx-btn eqx-btn--primary" href="<?= $h(url('equipment/assistant')) ?>" data-ath-modal>
                    Assistant de création<?= $kitsAllowed ? '' : ' <span class="eqx-pill">Pro</span>' ?>
                </a>
                <?php if ($myGameKit !== null): ?>
                <form method="post" action="<?= $h(url('equipment/mon-kit/tenue')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="eqx-btn"><?= $myGameKitWardrobeId > 0 ? 'Mettre à jour mon kit en jeu' : 'Enregistrer mon kit en jeu' ?></button>
                </form>
                <?php endif; ?>
                <a class="eqx-btn eqx-btn--quiet" href="<?= $h(url('equipment/ace-export')) ?>" data-eq-ace-export download>Exporter pour ACE</a>
                <button type="button" class="eqx-btn eqx-btn--quiet" data-eq-open="tenue-help">Ajouter une tenue ?</button>
            </div>
        </div>
    </header>

    <nav class="eqx-tabs" aria-label="Espaces de la page Équipement">
        <ul>
            <?php foreach ($tabLabels as $key => $label): ?>
            <?php
                $count = $counts[$key] ?? null;
                $active = $key === $space;
            ?>
            <li>
                <a href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => $key])) ?>"<?= $active ? ' aria-current="page" class="is-active"' : '' ?>>
                    <?= $h($label) ?>
                    <?php if ($key === 'kits' && !$kitsAllowed): ?><span class="eqx-pill eqx-pill--soft">Pro</span>
                    <?php elseif ($count !== null): ?><span data-eq-badge="<?= $h($key) ?>"><?= (int) $count ?></span><?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="eq-hub__body eqx-body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
        <p class="eq-hub__copy-status" data-eq-copy-status role="status" hidden></p>

        <?php if ($migrationMissing): ?>
        <p class="eq-hub__empty">Cette page n’est pas encore prête sur cette instance. Demandez à l’administration d’appliquer la mise à jour, puis rechargez.</p>
        <?php else: ?>

        <?php if ($detail !== null): ?>
        <section class="eqx-detail" data-eq-detail-fallback aria-labelledby="eqx-detail-title">
            <div class="eqx-detail__head">
                <div>
                    <h2 id="eqx-detail-title" translate="no"><?= $h((string) $detail['name']) ?></h2>
                    <p class="eq-hub__hint">Tenue de <span translate="no"><?= $h((string) $detail['owner']) ?></span></p>
                </div>
                <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h($returnUrl) ?>">Fermer</a>
            </div>
            <?php if (trim((string) $detail['description']) !== ''): ?><p translate="no"><?= $h((string) $detail['description']) ?></p><?php endif; ?>
            <div class="eqx-preview">
                <?php foreach ($detail['sections'] as $sec): ?>
                <div>
                    <h3><?= $h((string) ($sec['title'] ?? '')) ?></h3>
                    <ul>
                        <?php foreach (($sec['items'] ?? []) as $it): ?>
                        <li><span translate="no"><?= $h((string) ($it['name'] ?? '')) ?></span><?php if ((int) ($it['qty'] ?? 1) > 1): ?> <em>× <?= (int) $it['qty'] ?></em><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($detail['ace'])): ?>
            <p><a class="eq-hub__btn" href="<?= $h(url('equipment/ace-export') . '?tenue=' . (int) $detail['id']) ?>" download>Télécharger pour ACE (.txt)</a></p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="eqx-space" id="espace" aria-label="<?= $h($tabLabels[$space]) ?>">
            <?php
            switch ($space) {
                case 'collections':
                    require __DIR__ . '/_space_collections.php';
                    break;
                case 'kits':
                    require __DIR__ . '/_space_kits.php';
                    break;
                case 'materiel':
                    require __DIR__ . '/_space_materiel.php';
                    break;
                default:
                    require __DIR__ . '/_space_wardrobes.php';
            }
            ?>
        </section>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/_dialogs.php'; ?>

    <script type="application/json" id="eq-catalog-data"><?= json_encode($catalogPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script type="application/json" id="eq-mine-data"><?= json_encode($mineCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div>
