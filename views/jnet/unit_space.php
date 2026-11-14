<?php
declare(strict_types=1);

/**
 * JNET — espace d'une unité, interface propre (JnetPortalController::unitSpace et customizeSpace). Page autonome : ni la
 * coque ni le thème du back-office. Le thème de l'unité (JnetUnitSpaceSettings::look) fixe le fond, les panneaux, le
 * texte et la couleur ; jnet_unit_shell.css en tire tous les jetons, y compris ceux des composants d'échange existants.
 *
 * Rubriques (?vue=) : accueil, echanges, effectif, operations, sous-unites ; « personnaliser » pour le réglage de l'espace.
 *
 * @var array<string, mixed> $space
 * @var array<string, mixed> $spaceLook
 * @var string $usView
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

$tabs = [
    'accueil' => ['Accueil', 0],
    'echanges' => ['Ordres et échanges', count($received) + count($internal) + count($sentUp)],
    'effectif' => ['Effectif', count($members)],
    'operations' => ['Opérations', (int) ($spaceOpsTotal ?? count($spaceOps))],
    'sous-unites' => ['Sous-unités', count($subTree)],
];
if ($subTree === []) {
    unset($tabs['sous-unites']);
}
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
    return '<header class="ja-sh"><h2 class="ja-disp ja-disp--sm">' . $h($title) . '</h2>' . ($moreHref !== '' ? '<a class="ja-sh__link" href="' . $h($moreHref) . '">' . $h($moreLabel) . ' →</a>' : '') . '</header>';
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
</head>
<body class="ja us us--<?= $h($look['theme']) ?>" style="<?= $h(S::cssVars($look)) ?>">
<a class="ja-skip" href="#us-main">Aller au contenu</a>

<header class="ja-hd ja-hd--rows">
    <a class="ja-unit" href="<?= $h($tabUrl('accueil')) ?>" title="<?= $h($label) ?>">
        <?= $emblemHtml('ja-crest ja-crest--sm') ?>
        <b><?= $h($label) ?></b>
    </a>
    <nav class="ja-tabs" aria-label="Rubriques de l’espace">
        <?php $i = 0; foreach ($tabs as $key => [$tabLabel, $count]): ?>
            <a class="ja-tab<?= $view === $key ? ' is-on' : '' ?>" href="<?= $h($tabUrl($key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><span class="ja-mono"><?= $pad2($i++) ?></span><?= $h($tabLabel) ?><?php if ($key === 'echanges' && $toRead > 0): ?><em><?= min(99, $toRead) ?></em><?php elseif ($count > 0): ?><small class="us-count"><?= min(99, (int) $count) ?></small><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="ja-hd__r">
        <?php if (count($rail) > 1): ?>
        <details class="us-switch">
            <summary class="ja-ib">Changer d’espace</summary>
            <ul>
                <?php foreach ($rail as $r): ?>
                    <li><a href="<?= $h((string) $r['href']) ?>"<?= !empty($r['active']) ? ' aria-current="page"' : '' ?> style="--d: <?= min(5, max(0, (int) ($r['depth'] ?? 0))) ?>"><?= $h((string) $r['label']) ?><?= !empty($r['mine']) ? ' <small>votre unité</small>' : '' ?></a></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
        <span class="us-access"><?= $h((string) $look['access_label']) ?></span>
        <?php if ($operator !== ''): ?><span class="ja-who" translate="no"><?= $h($operator) ?></span><?php endif; ?>
        <?php if (!empty($canCustomize)): ?>
            <a class="ja-ib<?= $view === 'personnaliser' ? ' is-on' : '' ?>" href="<?= $h((string) ($customizeUrl ?? '')) ?>">Personnaliser</a>
        <?php endif; ?>
        <a class="ja-ib" href="<?= $h((string) ($usHome ?? url('jnet?commun=1'))) ?>" title="Retour : <?= $h((string) ($usHomeLabel ?? 'Espace commun')) ?>"><?= $h((string) ($usHomeLabel ?? 'Espace commun')) ?></a>
    </div>
</header>
<main class="ja-main us-main jnet-embed" id="us-main">
    <?php if ($flashErr): ?><p class="us-flash is-bad" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="us-flash" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>

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
        <div class="jn-flows">
            <?php
            $columns = [
                ['key' => 'down', 'icon' => '↓', 'title' => 'Reçu du commandement', 'lead' => 'Ordres et consignes descendus' . ($hasParentUnit ? ' de ' . $parentLabel . ' et au-dessus' : ''), 'items' => $received, 'empty' => 'Rien de reçu pour l’instant.', 'variant' => 'card', 'perspective' => 'down'],
                ['key' => 'internal', 'icon' => '≡', 'title' => 'Dans l’unité', 'lead' => 'Le fil de ' . $label . ' et de ses sous-unités', 'items' => $internal, 'empty' => 'Aucun échange interne.', 'variant' => 'chat', 'perspective' => ''],
                ['key' => 'up', 'icon' => '↑', 'title' => 'Remonté vers ' . ($hasParentUnit ? $parentLabel : 'l’échelon supérieur'), 'lead' => 'Comptes rendus et renseignement transmis', 'items' => $sentUp, 'empty' => 'Rien de remonté pour l’instant.', 'variant' => 'card', 'perspective' => 'up'],
            ];
            ?>
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
                    <?php endif; ?>
                    <?php if ($col['items'] === []): ?><p class="jn-flow__empty"><?= $h($col['empty']) ?></p><?php endif; ?>
                    <?php $exVariant = $col['variant']; $exPerspective = $col['perspective']; ?>
                    <?php foreach ($col['items'] as $ex): ?>
                        <?php require base_path('views/jnet/_exchange.php'); ?>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'effectif'): ?>
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
    <section class="us-hero" aria-labelledby="us-title">
        <div class="us-hero__bg" style="background-image: url('<?= $h($heroPhoto) ?>')" aria-hidden="true"></div>
        <div class="us-hero__in">
            <?= $emblemHtml('ja-crest ja-crest--lg us-hero__crest') ?>
            <h1 class="ja-disp ja-disp--lg" id="us-title"><?= $h($label) ?></h1>
            <?php if ((string) $look['tagline'] !== ''): ?><p class="us-hero__tagline">« <?= $h((string) $look['tagline']) ?> »</p><?php endif; ?>
            <p class="us-hero__lead">
                <?php if ($kindLine !== ''): ?><span class="us-hero__kind"><?= $h($kindLine) ?></span><?php endif; ?>
                <span>Chef : <b><?= $h((string) ($space['leader'] ?? '') !== '' ? (string) $space['leader'] : 'non désigné') ?></b> · Adjoint : <b><?= $h((string) ($space['deputy'] ?? '') !== '' ? (string) $space['deputy'] : 'non désigné') ?></b></span>
            </p>
            <?php if (count($chain) > 1): ?>
            <nav class="us-hero__chain" aria-label="Échelons">
                <?php foreach ($chain as $c): ?>
                    <?php if ((int) $c['id'] === $unitId): ?><b><?= $h((string) $c['label']) ?></b><?php else: ?><a href="<?= $h((string) $c['href']) ?>"><?= $h((string) $c['label']) ?></a><span aria-hidden="true">›</span><?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
            <div class="us-hero__acts">
                <a class="ja-b1" href="<?= $h($tabUrl('echanges')) ?>">Ordres et échanges <svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                <a class="ja-b2" href="<?= $h($tabUrl('effectif')) ?>">Effectif</a>
            </div>
        </div>
        <div class="ja-stats" style="--n: 4">
            <a class="ja-stat" href="<?= $h($tabUrl('effectif')) ?>"><b><?= (int) $strength['available'] ?><small> / <?= (int) $strength['total'] ?></small></b><span>disponibles</span></a>
            <a class="ja-stat" href="<?= $h($tabUrl('echanges')) ?>"><b><?= $toRead ?></b><span>à lire</span></a>
            <a class="ja-stat" href="<?= $h($tabUrl('operations')) ?>"><b><?= (int) ($spaceOpsTotal ?? count($spaceOps)) ?></b><span>opération<?= (int) ($spaceOpsTotal ?? count($spaceOps)) > 1 ? 's' : '' ?></span></a>
            <a class="ja-stat" href="<?= $h($tabUrl($subTree !== [] ? 'sous-unites' : 'accueil')) ?>"><b><?= count($subTree) ?></b><span>sous-unité<?= count($subTree) > 1 ? 's' : '' ?></span></a>
        </div>
    </section>

    <?php if ((string) $look['welcome'] !== ''): ?>
        <section class="us-welcome" aria-label="Mot du chef d’unité">
            <h2>Mot d’accueil</h2>
            <p><?= nl2br($h((string) $look['welcome'])) ?></p>
        </section>
    <?php endif; ?>

    <div class="us-grid">
        <div class="us-col">
            <section class="us-sec">
                <?= $section('Reçu du commandement', $tabUrl('echanges'), 'Tout voir') ?>
                <?php if (empty($exchangesReady)): ?>
                    <p class="us-empty">Les échanges ne sont pas encore activés.</p>
                <?php elseif ($received === []): ?>
                    <p class="us-empty">Aucun ordre ni consigne pour l’instant.</p>
                <?php else: ?>
                    <?php $exVariant = 'card'; $exPerspective = 'down'; foreach (array_slice($received, 0, 3) as $ex) { require base_path('views/jnet/_exchange.php'); } ?>
                <?php endif; ?>
            </section>
            <section class="us-sec">
                <?= $section('Fil de l’unité', $tabUrl('echanges'), 'Ouvrir le fil') ?>
                <?php if (!empty($exchangesReady) && !empty($canPost)): ?>
                    <?php require base_path('views/jnet/_exchange_composer.php'); ?>
                <?php endif; ?>
                <?php if ($internal === []): ?>
                    <p class="us-empty">Aucun échange interne. <?= !empty($canPost) ? 'Publiez le premier.' : '' ?></p>
                <?php else: ?>
                    <?php $exVariant = 'chat'; $exPerspective = ''; foreach (array_slice($internal, 0, 4) as $ex) { require base_path('views/jnet/_exchange.php'); } ?>
                <?php endif; ?>
            </section>
        </div>
        <aside class="us-col us-col--side">
            <section class="us-sec">
                <?= $section('Opérations', $tabUrl('operations'), $spaceOps !== [] ? 'Toutes' : '') ?>
                <?php if ($spaceOps === []): ?>
                    <p class="us-empty">Aucune opération engagée.</p>
                <?php else: ?>
                    <ul class="us-ops us-ops--compact">
                        <?php foreach (array_slice($spaceOps, 0, 3) as $op): ?>
                            <li><a class="us-opcard" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                                <span class="us-opcard__state<?= in_array($op['state_key'] ?? '', ['active', 'in_progress'], true) ? ' is-live' : '' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                                <b><?= $h((string) ($op['title'] ?? '')) ?></b>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <section class="us-sec">
                <?= $section('Effectif', $tabUrl('effectif'), 'Voir tous') ?>
                <?php if ($members === []): ?>
                    <p class="us-empty">Aucun membre affecté directement.</p>
                <?php else: ?>
                    <ul class="us-faces">
                        <?php foreach (array_slice($members, 0, 10) as $p): ?>
                            <li><a class="us-member__face<?= ($p['duty'] ?? '') === 'off' ? ' is-off' : '' ?>" href="<?= $h((string) ($p['href'] ?? '#')) ?>" title="<?= $h((string) ($p['name'] ?? '')) ?>"><?= $face($p) ?></a></li>
                        <?php endforeach; ?>
                        <?php if (count($members) > 10): ?><li class="us-faces__more">+<?= count($members) - 10 ?></li><?php endif; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php if ($subTree !== []): ?>
            <section class="us-sec">
                <?= $section('Sous-unités', $tabUrl('sous-unites'), 'Organigramme') ?>
                <ul class="us-subs">
                    <?php foreach (array_slice($subTree, 0, 6) as $sub): ?>
                        <li><a href="<?= $h((string) $sub['href']) ?>"<?= (string) ($sub['accent'] ?? '') !== '' ? ' style="--sub: ' . $h((string) $sub['accent']) . '"' : '' ?>>
                            <b><?= $h((string) $sub['label']) ?></b>
                            <small><?= (int) ($sub['strength']['available'] ?? 0) ?>/<?= (int) ($sub['strength']['total'] ?? 0) ?> disponibles<?= (string) ($sub['leader'] ?? '') !== '' ? ' · ' . $h((string) $sub['leader']) : '' ?></small>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
        </aside>
    </div>
<?php endif; ?>
</main>

<footer class="ja-foot us-foot">
    <span>JNET<?= (string) ($usCommunity ?? '') !== '' ? ' · ' . $h((string) $usCommunity) : '' ?></span>
    <a href="<?= $h((string) ($usHome ?? url('jnet?commun=1'))) ?>"><?= $h((string) ($usHomeLabel ?? 'Espace commun')) ?></a>
    <a href="<?= $h(url('jnet/courrier')) ?>">Messagerie</a>
    <a href="<?= $h(url('dashboard')) ?>">Quitter JNET</a>
</footer>
<script src="<?= $h(asset_url('assets/js/jnet_spaces.js')) ?>" defer></script>
</body>
</html>
