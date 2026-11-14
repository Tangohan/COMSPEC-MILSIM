<?php
declare(strict_types=1);

/**
 * JNET — espace d'une unité, interface propre (JnetPortalController::unitSpace et customizeSpace). Page autonome : ni la
 * coque ni le thème du back-office. Le thème de l'unité (JnetUnitSpaceSettings::look) fixe le fond, les panneaux, le
 * texte et la couleur ; jnet_unit_shell.css en tire tous les jetons, y compris ceux des composants d'échange existants.
 *
 * Rubriques (?vue=) : accueil, echanges, effectif (avec le suivi des membres), taches, pages (page : une page ouverte), photos,
 * documents, operations, sous-unites ; « personnaliser » pour le réglage de l'espace. Les données des outils arrivent dans $usw
 * (JnetPortalController::workspaceData) ; les rubriques sont des partiels de views/jnet/unit/.
 *
 * @var array<string, mixed> $space
 * @var array<string, mixed> $spaceLook
 * @var string $usView
 * @var array<string, mixed> $usw
 */
use App\Services\Jnet\JnetUnitSpaceSettings as S;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$space = is_array($space ?? null) ? $space : [];
$look = is_array($spaceLook ?? null) ? $spaceLook : S::look(S::defaults(), $space);
$view = (string) ($usView ?? 'accueil');
$unitId = (int) ($space['id'] ?? 0);
$label = (string) ($space['label'] ?? 'Unité');
$base = url('jnet/u/' . $unitId);
$chain = is_array($space['chain'] ?? null) ? $space['chain'] : [];
$parent = is_array($space['parent'] ?? null) ? $space['parent'] : [];
$hasParentUnit = (int) ($parent['id'] ?? -1) > 0;
$parentLabel = (string) ($parent['label'] ?? '');
$strength = is_array($space['strength'] ?? null) ? $space['strength'] : ['total' => 0, 'available' => 0];
$received = is_array($received ?? null) ? $received : [];
$internal = is_array($internal ?? null) ? $internal : [];
$sentUp = is_array($sentUp ?? null) ? $sentUp : [];
$members = is_array($members ?? null) ? $members : [];
$spaceOps = is_array($spaceOps ?? null) ? $spaceOps : [];
$subTree = is_array($subTree ?? null) ? $subTree : [];
$rail = is_array($spacesRail ?? null) ? $spacesRail : [];
$emblem = is_string($look['emblem'] ?? null) ? (string) $look['emblem'] : '';
$banner = is_string($look['banner'] ?? null) ? (string) $look['banner'] : '';
$operator = trim((string) ($usOperator ?? ''));
$exBack = $base . ($view !== 'accueil' ? '?vue=' . $view . '&direct=1' : '?direct=1');
$composeLabel = 'Partager avec ' . $label;
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');

$usw = is_array($usw ?? null) ? $usw : [];
$wShown = is_array($usw['shown'] ?? null) ? $usw['shown'] : [];
$wCanPost = !empty($usw['canPost']);
$tabs = [
    'accueil' => ['Accueil', 0],
    'echanges' => ['Ordres et échanges', count($received) + count($internal) + count($sentUp)],
    'effectif' => ['Effectif', count($members)],
    'taches' => ['Tâches', 0],
    'pages' => ['Pages', 0],
    'photos' => ['Photos', 0],
    'documents' => ['Documents', 0],
    'operations' => ['Opérations', (int) ($spaceOpsTotal ?? count($spaceOps))],
    'sous-unites' => ['Sous-unités', count($subTree)],
];
foreach (['taches', 'pages', 'photos', 'documents'] as $moduleKey) {
    if (empty($wShown[$moduleKey])) {
        unset($tabs[$moduleKey]);
    }
}
if ($subTree === []) {
    unset($tabs['sous-unites']);
}
$activeTab = $view === 'page' ? 'pages' : ($view === 'partager' ? 'echanges' : $view);
$shareTargets = is_array($usw['shareTargets'] ?? null) ? $usw['shareTargets'] : [];
$tabUrl = static fn (string $key): string => $base . ($key === 'accueil' ? '?direct=1' : '?vue=' . $key . '&direct=1');
$face = static function (array $p) use ($h): string {
    $photo = $p['photo'] ?? null;

    return is_string($photo) && $photo !== ''
        ? '<img src="' . $h($photo) . '" alt="">'
        : '<span>' . $h((string) ($p['initials'] ?? '?')) . '</span>';
};
$emblemHtml = static fn (string $class): string => JnetUi::crest((string) $look['monogram'], $emblem !== '' ? $emblem : null, $class);
$toRead = count(array_filter($received, static fn (array $e): bool => !empty($e['requiresAck']) && empty($e['readByMe'])));
$heroPhoto = $banner !== '' ? $banner : asset_url(JnetUi::genericPhoto($unitId));
$kindLine = implode(' · ', array_filter([JnetUi::echelonLabel((string) ($space['echelon'] ?? '')), JnetUi::functionLabel((string) ($space['function'] ?? ''))]));
$pad2 = static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
$section = static function (string $title, string $moreHref = '', string $moreLabel = '') use ($h): string {
    return '<header class="ja-sh"><h2 class="ja-disp ja-disp--sm">' . $h($title) . '</h2>' . ($moreHref !== '' && $moreLabel !== '' ? '<a class="ja-sh__link" href="' . $h($moreHref) . '">' . $h($moreLabel) . ' →</a>' : '') . '</header>';
};
?>
<!DOCTYPE html>
<html lang="<?= $h(function_exists('html_lang') ? html_lang() : 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="<?= $h($look['bg']) ?>">
    <title><?= $h($label) ?> — JNET</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700;1,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_athena.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_spaces.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_unit_space.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_unit_shell.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_unit_workspace.css?r=202611142100')) ?>">
</head>
<body class="ja us us--<?= $h($look['theme']) ?>" style="<?= $h(S::cssVars($look)) ?>" data-uw-unit="<?= $unitId ?>" data-uw-label="<?= $h($label) ?>" data-uw-view="<?= $h($view) ?>">
<a class="ja-skip" href="#us-main">Aller au contenu</a>

<header class="ja-hd ja-hd--rows">
    <a class="ja-unit" href="<?= $h($tabUrl('accueil')) ?>" title="<?= $h($label) ?>">
        <?= $emblemHtml('ja-crest ja-crest--sm') ?>
        <b><?= $h($label) ?></b>
    </a>
    <nav class="ja-tabs" aria-label="Rubriques de l’espace">
        <?php $i = 0; foreach ($tabs as $key => [$tabLabel, $count]): ?>
            <a class="ja-tab<?= $activeTab === $key ? ' is-on' : '' ?>" href="<?= $h($tabUrl($key)) ?>"<?= $activeTab === $key ? ' aria-current="page"' : '' ?>><span class="ja-mono"><?= $pad2($i++) ?></span><?= $h($tabLabel) ?><?php if ($key === 'echanges' && $toRead > 0): ?><em><?= min(99, $toRead) ?></em><?php elseif ($count > 0): ?><small class="us-count"><?= min(99, (int) $count) ?></small><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="ja-hd__r">
        <?php if (count($rail) > 1): ?>
        <details class="us-switch">
            <summary class="ja-ib"><?= JnetUi::icon('switch') ?><span class="ja-ib__t">Changer d’espace</span></summary>
            <ul>
                <?php foreach ($rail as $r): ?>
                    <li><a href="<?= $h((string) $r['href']) ?>"<?= !empty($r['active']) ? ' aria-current="page"' : '' ?> style="--d: <?= min(5, max(0, (int) ($r['depth'] ?? 0))) ?>"><?= $h((string) $r['label']) ?><?= !empty($r['mine']) ? ' <small>votre unité</small>' : '' ?></a></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
        <button class="ja-ib" type="button" data-uw-search-open aria-controls="uw-search" aria-expanded="false" title="Rechercher dans l’espace (touche /)"><svg class="ja-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span class="ja-ib__t">Rechercher</span></button>
        <span class="us-access"><?= $h((string) $look['access_label']) ?></span>
        <?php if (!empty($canCustomize)): ?>
            <a class="ja-ib<?= $view === 'personnaliser' ? ' is-on' : '' ?>" href="<?= $h((string) ($customizeUrl ?? '')) ?>"><?= JnetUi::icon('edit') ?><span class="ja-ib__t">Personnaliser</span></a>
        <?php endif; ?>
        <a class="ja-ib" href="<?= $h((string) ($usHome ?? url('jnet?commun=1'))) ?>" title="Retour : <?= $h((string) ($usHomeLabel ?? 'Espace commun')) ?>"><?= JnetUi::icon('extranet') ?><span class="ja-ib__t"><?= $h((string) ($usHomeLabel ?? 'Espace commun')) ?></span></a>
        <?= JnetUi::account($operator) ?>
    </div>
</header>
<form class="uw-search" id="uw-search" role="search" hidden data-uw-search data-url="<?= $h($base . '/recherche') ?>">
    <label class="ja-sr" for="uw-search-q">Rechercher dans l’espace</label>
    <div class="uw-search__bar">
        <svg class="ja-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="uw-search-q" type="search" name="q" autocomplete="off" placeholder="Consigne, membre, tâche, page, document…" aria-controls="uw-search-results">
        <button class="ja-b2 sm" type="button" data-uw-search-close>Fermer</button>
    </div>
    <ul class="uw-search__list" id="uw-search-results" aria-live="polite"></ul>
</form>
<main class="ja-main us-main jnet-embed" id="us-main">
    <?php if ($flashErr): ?><p class="us-flash is-bad" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="us-flash" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
    <?php if (!empty($usGate) && $view !== 'personnaliser'): ?><?php require base_path('views/jnet/unit/_gate.php'); ?><?php endif; ?>

<?php if ($view === 'personnaliser'): ?>
    <?php require base_path('views/jnet/customize.php'); ?>

<?php elseif ($view === 'echanges'): ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Ordres et échanges</h1>
        <p>Ce que le commandement envoie à <?= $h($label) ?>, ce que l’unité se dit, et ce qu’elle fait remonter.</p>
    </header>
    <?php if (empty($exchangesReady)): ?>
        <div class="jn-empty"><p>Les échanges entre espaces ne sont pas encore activés. Un administrateur doit appliquer les migrations.</p></div>
    <?php else: ?>
        <?php if (empty($canPost)): ?><p class="us-note">Vous consultez cet espace sans en être membre : la publication est réservée à l’unité et à son encadrement.</p><?php endif; ?>
        <?php
        $subTargets = array_values(array_filter(is_array($postTargets ?? null) ? $postTargets : [], static fn (array $t): bool => ($t['relation'] ?? '') === 'Subordonné'));
        $parentTarget = array_values(array_filter(is_array($postTargets ?? null) ? $postTargets : [], static fn (array $t): bool => ($t['relation'] ?? '') === 'Échelon supérieur'))[0] ?? null;
        $columns = [
            ['key' => 'down', 'icon' => '↓', 'title' => 'Reçu du commandement', 'lead' => 'Ordres et consignes descendus' . ($hasParentUnit ? ' de ' . $parentLabel . ' et au-dessus' : ''), 'items' => $received, 'empty' => 'Rien de reçu pour l’instant.', 'variant' => 'card', 'perspective' => 'down'],
            ['key' => 'internal', 'icon' => '≡', 'title' => 'Dans l’unité', 'lead' => 'Le fil de ' . $label . ' et de ses sous-unités', 'items' => $internal, 'empty' => 'Aucun échange interne.', 'variant' => 'chat', 'perspective' => ''],
            ['key' => 'up', 'icon' => '↑', 'title' => 'Remonté vers ' . ($hasParentUnit ? $parentLabel : 'l’échelon supérieur'), 'lead' => 'Comptes rendus et renseignement transmis', 'items' => $sentUp, 'empty' => 'Rien de remonté pour l’instant.', 'variant' => 'card', 'perspective' => 'up'],
        ];
        ?>
        <div class="jn-flows">
            <?php foreach ($columns as $col): ?>
                <section class="jn-flow jn-flow--<?= $h($col['key']) ?>" aria-labelledby="us-col-<?= $h($col['key']) ?>">
                    <header class="jn-flow__head">
                        <span class="jn-flow__icon" aria-hidden="true"><?= $col['icon'] ?></span>
                        <div>
                            <h3 id="us-col-<?= $h($col['key']) ?>"><?= $h($col['title']) ?> <span class="jn-flow__count"><?= count($col['items']) ?></span></h3>
                            <p><?= $h($col['lead']) ?></p>
                        </div>
                    </header>
                    <?php if ($col['key'] === 'internal' && !empty($canPost)): ?>
                        <?php require base_path('views/jnet/_exchange_composer.php'); ?>
                    <?php elseif ($col['key'] === 'up' && !empty($canPost) && $parentTarget !== null): ?>
                        <?php $composeLabel = 'Faire remonter vers ' . (string) $parentTarget['label']; $composeKind = 'compte_rendu'; $composeTarget = (int) $parentTarget['id']; $composeSuffix = '-up'; require base_path('views/jnet/_exchange_composer.php'); unset($composeKind, $composeTarget, $composeSuffix); $composeLabel = 'Partager avec ' . $label; ?>
                    <?php endif; ?>
                    <?php if ($col['items'] === []): ?><p class="jn-flow__empty"><?= $h($col['empty']) ?></p><?php endif; ?>
                    <?php $exVariant = $col['variant']; $exPerspective = $col['perspective']; ?>
                    <?php foreach ($col['items'] as $ex): ?>
                        <?php require base_path('views/jnet/_exchange.php'); ?>
                        <?php if ($shareTargets !== []): ?>
                            <p class="uw-exchange-tools"><a class="uw-link" href="<?= $h($base . '?vue=partager&kind=exchange&item=' . (int) $ex['id'] . '&titre=' . rawurlencode(mb_substr((string) $ex['title'], 0, 120)) . '&direct=1') ?>" data-uw-share data-kind="exchange" data-item="<?= (int) $ex['id'] ?>" data-title="<?= $h((string) $ex['title']) ?>" data-back="<?= $h($exBack . '#ex-' . (int) $ex['id']) ?>">Partager avec une autre unité</a></p>
                        <?php endif; ?>
                        <?php if ($col['key'] === 'down' && !empty($canPost) && $subTargets !== []): ?>
                            <details class="uw-relay">
                                <summary>Relayer aux sous-unités</summary>
                                <form method="post" action="<?= $h($base . '/echanges/' . (int) $ex['id'] . '/relayer') ?>">
                                    <?= \App\Core\Csrf::field() ?>
                                    <fieldset>
                                        <legend>Sous-unités destinataires</legend>
                                        <?php foreach ($subTargets as $t): ?>
                                            <label class="jn-check"><input type="checkbox" name="subunits[]" value="<?= (int) $t['id'] ?>" checked><span><b><?= $h((string) $t['label']) ?></b></span></label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <label class="uw-field" for="uw-relay-note-<?= (int) $ex['id'] ?>">Mot du chef <small>(facultatif)</small></label>
                                    <textarea id="uw-relay-note-<?= (int) $ex['id'] ?>" name="note" rows="2" maxlength="400" placeholder="Ce que la sous-unité doit retenir."></textarea>
                                    <p class="uw-note">L’ordre d’origine est transmis sans modification, avec votre mot en tête.</p>
                                    <button class="ja-b1 sm" type="submit">Relayer</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'effectif'): ?>
    <?php if ($wCanPost): ?>
        <?php require base_path('views/jnet/unit/_tracking.php'); ?>
    <?php else: ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Effectif</h1>
        <p><?= (int) $strength['available'] ?> disponible<?= (int) $strength['available'] > 1 ? 's' : '' ?> sur <?= (int) $strength['total'] ?>, sous-unités comprises. <?= count($members) ?> membre<?= count($members) > 1 ? 's' : '' ?> affecté<?= count($members) > 1 ? 's' : '' ?> directement à <?= $h($label) ?>.</p>
        <a class="us-btn" href="<?= $h(url('jnet/personnel?filtre=' . rawurlencode($label))) ?>">Annuaire de l’organisation</a>
    </header>
    <?php if ($members === []): ?>
        <div class="jn-empty"><p>Aucun membre affecté directement à cette unité.</p></div>
    <?php else: ?>
        <ul class="us-roster">
            <?php foreach ($members as $p): ?>
                <li>
                    <a class="us-member<?= ($p['duty'] ?? '') === 'off' ? ' is-off' : '' ?>" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                        <span class="us-member__face"><?= $face($p) ?></span>
                        <span class="us-member__text">
                            <b><?= $h((string) ($p['name'] ?? '')) ?></b>
                            <small><?= $h(trim(implode(' · ', array_filter([(string) ($p['grade'] ?? ''), (string) ($p['function'] ?? '')])))) ?></small>
                        </span>
                        <span class="us-member__duty"><?= ($p['duty'] ?? '') === 'off' ? 'Indisponible' : 'Disponible' ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php endif; ?>

<?php elseif ($view === 'taches' && !empty($wShown['taches'])): ?>
    <?php require base_path('views/jnet/unit/_tasks.php'); ?>

<?php elseif ($view === 'pages' && !empty($wShown['pages'])): ?>
    <?php require base_path('views/jnet/unit/_pages.php'); ?>

<?php elseif ($view === 'page' && !empty($wShown['pages'])): ?>
    <?php require base_path('views/jnet/unit/_page.php'); ?>

<?php elseif (($view === 'photos' || $view === 'documents') && !empty($wShown[$view])): ?>
    <?php $usKind = $view; require base_path('views/jnet/unit/_files.php'); ?>

<?php elseif ($view === 'partager'): ?>
    <?php require base_path('views/jnet/unit/_share.php'); ?>

<?php elseif ($view === 'operations'): ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Opérations</h1>
        <p>Les opérations où <?= $h($label) ?> ou une de ses sous-unités est engagée.</p>
        <a class="us-btn" href="<?= $h(url('jnet/operations')) ?>">Toutes les opérations</a>
    </header>
    <?php if ($spaceOps === []): ?>
        <div class="jn-empty"><p>Aucune opération engagée par cette unité.</p></div>
    <?php else: ?>
        <ul class="us-ops">
            <?php foreach ($spaceOps as $op): ?>
                <li>
                    <a class="us-opcard" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                        <span class="us-opcard__state<?= in_array($op['state_key'] ?? '', ['active', 'in_progress'], true) ? ' is-live' : '' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                        <b><?= $h((string) ($op['title'] ?? '')) ?></b>
                        <span class="us-opcard__go" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php elseif ($view === 'sous-unites'): ?>
    <header class="us-page">
        <h1 class="ja-disp ja-disp--md">Sous-unités</h1>
        <p>Chaque sous-unité a son propre espace. Ce qu’elle publie pour <?= $h($label) ?> arrive dans « Ordres et échanges ».</p>
    </header>
    <?php $viewNodes = $subTree; $viewId = 'us-sub-units'; $viewGrouped = false; require base_path('views/jnet/_unit_views.php'); ?>

<?php else: ?>
    <?php require base_path('views/jnet/unit/_home.php'); ?>
<?php endif; ?>
</main>

<footer class="ja-foot us-foot">
    <span>JNET<?= (string) ($usCommunity ?? '') !== '' ? ' · ' . $h((string) $usCommunity) : '' ?></span>
    <a href="<?= $h((string) ($usHome ?? url('jnet?commun=1'))) ?>"><?= $h((string) ($usHomeLabel ?? 'Espace commun')) ?></a>
    <a href="<?= $h(url('jnet/courrier')) ?>">Messagerie</a>
    <a href="<?= $h(url('dashboard')) ?>">Quitter JNET</a>
</footer>
<?php if ($shareTargets !== [] && $view !== 'partager'): ?>
<template id="uw-share-tpl">
    <?php $shareKind = 'exchange'; $shareId = 0; $shareTitle = ''; $shareUid = 'dlg'; require base_path('views/jnet/unit/_share_form.php'); ?>
</template>
<?php endif; ?>
<div class="uw-toasts" role="status" aria-live="polite" data-uw-toasts></div>
<script src="<?= $h(asset_url('assets/js/jnet_spaces.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/jnet_unit_workspace.js?r=202611142100')) ?>" defer></script>
</body>
</html>
