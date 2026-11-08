<?php
declare(strict_types=1);
/**
 * Page /equipment : un bandeau d'accueil, puis des sections — mes tenues, tenues de la communauté, kits de départ,
 * favoris, matériel (collections, fiches, dotation). Thème du portail ; aucune image externe ; images des tenues et
 * icônes d'objets chargées à la demande (loading="lazy") ; contenu d'une tenue chargé à l'ouverture de l'aperçu.
 *
 * Tout texte venu du jeu ou des membres (noms de tenues, d'objets, d'unités) est échappé par $h() et marqué
 * translate="no". Les fenêtres (aperçu, collections, fiches, dotation) sont dans _dialogs.php.
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

// Trois listes pour trois sections ; une tenue peut figurer dans « Mes tenues » ou « Communauté », et en plus dans « Favoris ».
$mine = array_values(array_filter($wardrobes, static fn (array $w): bool => !empty($w['mine'])));
$community = array_values(array_filter(
    $wardrobes,
    static fn (array $w): bool => empty($w['mine']) && !($kitsAllowed && (string) ($w['source'] ?? '') === 'role_kit')
));
$favorites = array_values(array_filter($wardrobes, static fn (array $w): bool => !empty($w['is_favorite_me'])));
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);

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
?>
<div class="eq-hub eqx" id="eq-catalog" data-eq-catalog>
    <header class="eqx-hero">
        <div class="eqx-hero__inner">
            <div class="eqx-hero__copy">
                <p class="eqx-hero__kicker">Communauté · Équipement</p>
                <h1>Équipement</h1>
                <p class="eqx-hero__lead">Vos tenues, celles de la communauté et les kits de départ. Tout ce que vous voyez ici peut être enfilé en jeu depuis l’arsenal.</p>
                <div class="eqx-hero__actions">
                    <a class="eqx-btn eqx-btn--primary" href="<?= $h(url('equipment/assistant')) ?>">
                        Créer une tenue avec l’assistant<?= $kitsAllowed ? '' : ' <span class="eqx-pill">Pro</span>' ?>
                    </a>
                    <?php if ($myGameKit !== null): ?>
                    <form method="post" action="<?= $h(url('equipment/mon-kit/tenue')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <button type="submit" class="eqx-btn"><?= $myGameKitWardrobeId > 0 ? 'Mettre à jour mon kit en jeu' : 'Enregistrer mon kit en jeu' ?></button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="eqx-btn eqx-btn--quiet" data-eq-open="tenue-help">Comment ajouter une tenue ?</button>
                </div>
            </div>
            <dl class="eqx-hero__stats" aria-label="Chiffres de la page">
                <div><dt>Mes tenues</dt><dd data-eq-section-count="mine"><?= count($mine) ?></dd></div>
                <div><dt>Communauté</dt><dd data-eq-section-count="community"><?= count($community) ?></dd></div>
                <div><dt>Kits de départ</dt><dd><?= $kitsAllowed ? count($kits) : '—' ?></dd></div>
                <div><dt>Objets remontés du jeu</dt><dd><?= $catalogCount ?></dd></div>
            </dl>
        </div>
    </header>

    <nav class="eqx-nav" aria-label="Sections de la page">
        <ul>
            <li><a href="#mes-tenues">Mes tenues <span><?= count($mine) ?></span></a></li>
            <li><a href="#communaute">Communauté <span><?= count($community) ?></span></a></li>
            <li><a href="#kits">Kits de départ <span><?= $kitsAllowed ? count($kits) : 'Pro' ?></span></a></li>
            <li><a href="#favoris">Favoris <span><?= count($favorites) ?></span></a></li>
            <li><a href="#materiel">Matériel et collections</a></li>
        </ul>
    </nav>

    <div class="eq-hub__body eqx-body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>

        <?php if ($migrationMissing): ?>
        <p class="eq-hub__empty">Cette page n’est pas encore prête sur cette instance. Demandez à l’administration d’appliquer la mise à jour, puis rechargez.</p>
        <?php else: ?>

        <div class="eqx-tools" role="search">
            <label class="eq-hub__search">
                <span class="visually-hidden">Rechercher une tenue</span>
                <input type="search" placeholder="Rechercher une tenue, un objet, un membre…" data-eq-search autocomplete="off">
            </label>
            <label class="eq-hub__sort">
                <span class="visually-hidden">Type d’équipement</span>
                <select data-eq-kind-select class="bo-select">
                    <option value="">Tous les types</option>
                    <?php foreach ($equipmentKindLabels as $kind => $label): ?>
                    <option value="<?= $h($kind) ?>"><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="eq-hub__sort">
                <span class="visually-hidden">Trier</span>
                <select data-eq-sort class="bo-select">
                    <option value="name">Alphabétique</option>
                    <option value="date">Date d’ajout</option>
                    <option value="collection">Collection</option>
                </select>
            </label>
            <button type="button" class="eq-hub__link-btn" data-eq-clear-filters hidden>Effacer les filtres</button>
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
                <span class="eq-hub__ace-one">L’import de l’arsenal ACE ne prend qu’une tenue à la fois : utilisez le bouton « Copier pour ACE » de la tenue voulue, puis collez-la avec le bouton Importer de l’arsenal.</span>
            </p>
            <a class="eq-hub__btn" href="<?= $h(url('equipment/ace-export')) ?>" data-eq-ace-export download>Exporter pour ACE</a>
            <button type="button" class="eq-hub__link-btn" data-eq-ace-help-toggle aria-expanded="false" aria-controls="eq-ace-help">Comment importer ?</button>
            <ol class="eq-hub__ace-help" id="eq-ace-help" hidden>
                <li>Sous la tenue voulue, cliquez sur « Copier pour ACE » : son texte est copié.</li>
                <li>En jeu, ouvrez l’arsenal ACE et cliquez sur Importer (écran principal) : la tenue s’enfile directement. Enregistrez-la ensuite sous le même nom que sur Athena pour qu’elle soit reconnue comme déjà transmise.</li>
                <li>Pour une autre tenue, recommencez : l’import ne prend qu’une tenue à la fois.</li>
                <li>Le bandeau « Athena · Tenues » de l’arsenal fait la même chose sans copier-coller : « Tout basculer dans l’arsenal ACE ».</li>
            </ol>
        </div>
        <p class="eq-hub__copy-status" data-eq-copy-status role="status" hidden></p>

        <!-- 1. Mes tenues -->
        <section class="eqx-section" id="mes-tenues" aria-labelledby="eqx-h-mine">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">01</span>
                <div>
                    <h2 id="eqx-h-mine">Mes tenues</h2>
                    <p>Les tenues que vous avez envoyées depuis l’arsenal, enregistrées depuis votre kit en jeu ou créées avec l’assistant.</p>
                </div>
            </header>

            <?php if ($myGameKit !== null): ?>
            <?php $mkWhen = strtotime((string) ($myGameKit['last_seen_at'] ?? '')) ?: null; ?>
            <div class="eq-mykit__card eqx-mykit">
                <div class="eq-mykit__top">
                    <p class="eq-mykit__lead">
                        <strong>Mon kit en jeu.</strong>
                        Dernier équipement que vous avez porté en jeu<?= $mkWhen !== null ? ', le ' . $h(date('d/m/Y à H:i', $mkWhen)) : '' ?><?= (string) ($myGameKit['role'] ?? '') !== '' ? ' (<span translate="no">' . $h((string) $myGameKit['role']) . '</span>)' : '' ?>.
                        Remonté automatiquement par le mod, rien à saisir.
                    </p>
                    <div class="eq-mykit__actions">
                        <form method="post" action="<?= $h(url('equipment/mon-kit/tenue')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <button type="submit" class="eq-hub__btn"><?= $myGameKitWardrobeId > 0 ? 'Mettre à jour ma tenue' : 'Enregistrer dans mes tenues' ?></button>
                        </form>
                        <?php if ($myGameKitWardrobeId > 0): ?>
                        <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment') . '?tenue=' . $myGameKitWardrobeId) ?>">Voir la tenue</a>
                        <?php endif; ?>
                    </div>
                </div>
                <details class="eqx-kit__details">
                    <summary>Voir ce que je porte</summary>
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
                </details>
            </div>
            <?php endif; ?>

            <?php if ($mine === []): ?>
            <div class="eqx-empty">
                <p><strong>Vous n’avez pas encore de tenue.</strong></p>
                <p>En jeu, ouvrez l’arsenal, puis le bandeau Athena en haut de l’écran, et envoyez vos tenues : elles apparaissent ici. Vous pouvez aussi enregistrer le kit que vous portez, ou partir d’un kit de départ.</p>
                <p><a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/assistant')) ?>">Essayer l’assistant</a></p>
            </div>
            <?php else: ?>
            <ul class="eq-hub__grid eqx-grid" data-eq-grid>
                <?php foreach ($mine as $w): ?>
                <?php $cardSection = 'mine'; require __DIR__ . '/_wardrobe_card.php'; ?>
                <?php endforeach; ?>
            </ul>
            <p class="eq-hub__empty" data-eq-no-results hidden>Aucune tenue ne correspond à ces filtres.</p>
            <?php endif; ?>
        </section>

        <!-- 2. Tenues de la communauté -->
        <section class="eqx-section" id="communaute" aria-labelledby="eqx-h-community">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">02</span>
                <div>
                    <h2 id="eqx-h-community">Tenues de la communauté</h2>
                    <p>Les tenues partagées par les autres membres. Mettez en favori celles que vous voulez garder sous la main.</p>
                </div>
            </header>
            <?php if ($community === []): ?>
            <div class="eqx-empty">
                <p><strong>Aucune tenue partagée pour le moment.</strong></p>
                <p>Quand un membre envoie une tenue depuis l’arsenal et la laisse visible par la communauté, elle apparaît ici.</p>
            </div>
            <?php else: ?>
            <ul class="eq-hub__grid eqx-grid" data-eq-grid>
                <?php foreach ($community as $w): ?>
                <?php $cardSection = 'community'; require __DIR__ . '/_wardrobe_card.php'; ?>
                <?php endforeach; ?>
            </ul>
            <p class="eq-hub__empty" data-eq-no-results hidden>Aucune tenue ne correspond à ces filtres.</p>
            <?php endif; ?>
        </section>

        <!-- 3. Kits de départ -->
        <section class="eqx-section" id="kits" aria-labelledby="eqx-h-kits">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">03</span>
                <div>
                    <h2 id="eqx-h-kits">Kits de départ</h2>
                    <p>Des tenues prêtes à l’emploi, proposées par votre communauté à une unité, à un rôle ou à tous. Copiez-en une dans vos tenues pour la modifier.</p>
                </div>
            </header>

            <?php if (!$kitsAllowed): ?>
            <div class="eqx-offer">
                <p class="eqx-offer__tag">Formule Pro et plus</p>
                <h3>Donnez à chaque recrue une tenue de départ</h3>
                <p>Les responsables créent un kit à partir d’une tenue remontée du jeu ou avec l’assistant, puis le proposent à une unité ou à un rôle. Chaque membre le retrouve ici et le copie dans ses tenues en un clic.</p>
                <ul class="eqx-offer__list">
                    <li>Des kits par unité ou par rôle, toujours limités à votre communauté.</li>
                    <li>Un assistant qui propose des objets que vos joueurs ont réellement utilisés en jeu, emplacement par emplacement, et signale ce qui manque.</li>
                    <li>Des tenues au format de l’arsenal ACE, prêtes à enfiler.</li>
                </ul>
                <p class="eqx-offer__free">Reste gratuit pour tous : consulter les tenues, créer et modifier les siennes, les favoris et l’export ACE.</p>
                <div class="eqx-actions">
                    <a class="eq-hub__btn" href="<?= $h($upgradeUrl) ?>">Voir les formules</a>
                    <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/assistant')) ?>">Découvrir l’assistant</a>
                </div>
                <?php if (!$canManageKits): ?>
                <p class="eq-hub__hint">Pour activer cette offre, parlez-en à un responsable de votre communauté.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <?php if ($kits === []): ?>
                <div class="eqx-empty">
                    <p><strong><?= $canManageKits ? 'Aucun kit de départ pour le moment.' : 'Aucun kit ne vous est proposé pour le moment.' ?></strong></p>
                    <p><?= $canManageKits
                        ? 'Créez un premier kit ci-dessous : à partir d’une tenue remontée du jeu, ou de zéro avec l’assistant.'
                        : 'Quand un responsable en propose un à votre unité ou à votre rôle, il apparaît ici.' ?></p>
                </div>
                <?php else: ?>
                <ul class="eqx-kits">
                    <?php foreach ($kits as $kit): ?>
                    <?php require __DIR__ . '/_kit_card.php'; ?>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($canManageKits): ?>
                <div class="eqx-create" id="creer-un-kit">
                    <h3>Créer un kit de départ</h3>
                    <div class="eqx-create__grid">
                        <div class="eqx-create__card">
                            <h4>À partir d’une tenue remontée du jeu</h4>
                            <?php if ($gameWardrobes === []): ?>
                            <p class="eq-hub__hint">Aucune tenue remontée du jeu pour le moment. Envoyez une tenue depuis l’arsenal en jeu, ou enregistrez un kit porté depuis « Équipement des joueurs » dans la gestion de la communauté.</p>
                            <?php else: ?>
                            <form method="post" action="<?= $h(url('equipment/kits/depuis-tenue')) ?>" class="eqx-form">
                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                <label>Tenue à transformer
                                    <select name="wardrobe_id" class="bo-select" required>
                                        <?php foreach ($gameWardrobes as $gw): ?>
                                        <option value="<?= (int) ($gw['id'] ?? 0) ?>" translate="no"><?= $h((string) ($gw['display_name'] ?? $gw['name'] ?? '')) ?> — <?= $h((string) (($gw['owner_label'] ?? '') !== '' ? $gw['owner_label'] : 'Membre')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Nom du kit <small>(facultatif : reprend le nom de la tenue)</small>
                                    <input type="text" name="name" maxlength="90" autocomplete="off">
                                </label>
                                <label>Description <small>(facultatif)</small>
                                    <textarea name="description" rows="2" maxlength="1000" placeholder="Quand l’utiliser, pour qui, particularités…"></textarea>
                                </label>
                                <label>À qui proposer ce kit
                                    <select name="audience" class="bo-select">
                                        <?php $audience = 'all'; require __DIR__ . '/_audience_options.php'; ?>
                                    </select>
                                </label>
                                <button type="submit" class="eq-hub__btn">Créer le kit</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="eqx-create__card">
                            <h4>De zéro, avec l’assistant</h4>
                            <p>Choisissez un emploi : l’assistant propose des objets que vos joueurs ont déjà utilisés en jeu, emplacement par emplacement, et vous dit ce qui manque.</p>
                            <p><a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment/assistant') . '?cible=kit') ?>">Créer un kit avec l’assistant</a></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- 4. Favoris -->
        <section class="eqx-section" id="favoris" aria-labelledby="eqx-h-fav">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">04</span>
                <div>
                    <h2 id="eqx-h-fav">Favoris</h2>
                    <p>Les tenues que vous avez marquées d’une étoile, les vôtres comme celles des autres.</p>
                </div>
            </header>
            <?php if ($favorites === []): ?>
            <div class="eqx-empty">
                <p><strong>Aucun favori pour le moment.</strong></p>
                <p>Cliquez sur l’étoile d’une tenue pour la retrouver ici en un clic.</p>
            </div>
            <?php else: ?>
            <ul class="eq-hub__grid eqx-grid" data-eq-grid>
                <?php foreach ($favorites as $w): ?>
                <?php $cardSection = 'fav'; require __DIR__ . '/_wardrobe_card.php'; ?>
                <?php endforeach; ?>
            </ul>
            <p class="eq-hub__empty" data-eq-no-results hidden>Aucune tenue ne correspond à ces filtres.</p>
            <?php endif; ?>
        </section>

        <!-- 5. Matériel, collections, dotation -->
        <section class="eqx-section" id="materiel" aria-labelledby="eqx-h-mat">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">05</span>
                <div>
                    <h2 id="eqx-h-mat">Matériel et collections</h2>
                    <p>Rangez vos tenues en collections, consultez les fiches matériel et la dotation de la communauté.</p>
                </div>
                <div class="eqx-section__actions">
                    <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eq-open="collection-new">Nouvelle collection</button>
                    <?php if ($canManageCatalog): ?>
                    <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eq-open="fiche-new">Nouvelle fiche</button>
                    <?php endif; ?>
                    <?php if ($canManageDotation): ?>
                    <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eq-open="dotation-new">Nouvel article</button>
                    <?php endif; ?>
                </div>
            </header>

            <h3 class="eqx-sub">Collections</h3>
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
                    <button type="button" class="eq-hub__coll-card" data-eq-filter-collection="<?= (int) $c['id'] ?>" aria-pressed="false">
                        <span class="eq-hub__media eq-hub__media--coll">
                            <?php if ($hasCover): ?>
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
                        <span class="eq-hub__coll-meta">
                            <strong translate="no"><?= $h($c['name'] ?? '') ?></strong>
                            <span><?= $h($vis) ?> · <?= $count ?> tenue<?= $count > 1 ? 's' : '' ?></span>
                        </span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="eq-hub__hint">Cliquer sur une collection filtre les tenues de la page.</p>
            <?php endif; ?>

            <nav class="eq-hub__tabs" aria-label="Fiches matériel et dotation" data-eq-tabs>
                <button type="button" class="eq-hub__tab is-active" data-eq-tab="fiches" aria-selected="true">Fiches matériel <em><?= count($equipmentClasses) ?></em></button>
                <button type="button" class="eq-hub__tab" data-eq-tab="dotation" aria-selected="false">Dotation <em><?= count($dotationItems) ?></em></button>
            </nav>

            <div data-eq-panel="fiches">
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
                                <span><span translate="no"><?= $h(($d['code'] ?? '') !== '' ? $d['code'] : 'Article') ?></span>
                                    · <?= (int) ($d['issued_count'] ?? 0) ?> en dotation</span>
                            </span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/_dialogs.php'; ?>

    <script type="application/json" id="eq-catalog-data"><?= json_encode($catalogPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <script type="application/json" id="eq-mine-data"><?= json_encode($mineCatalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div>
