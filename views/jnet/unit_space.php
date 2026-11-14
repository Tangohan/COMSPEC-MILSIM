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
$emblemHtml = static function (string $class) use ($emblem, $look, $h, $label): string {
    if ($emblem !== '') {
        return '<img class="' . $class . ' is-img" src="' . $h($emblem) . '" alt="Insigne de ' . $h($label) . '">';
    }

    return '<span class="' . $class . '" aria-hidden="true"><svg viewBox="0 0 80 92"><path d="M40 3 76 15v27c0 22-15 38-36 47C19 80 4 64 4 42V15z" fill="var(--jn-space-panel)" stroke="var(--jn-unit)" stroke-width="4" stroke-linejoin="round"/></svg><b>' . $h((string) $look['monogram']) . '</b></span>';
};
$section = static function (string $title, string $moreHref = '', string $moreLabel = '') use ($h): string {
    return '<header class="us-block__head"><h2>' . $h($title) . '</h2>' . ($moreHref !== '' ? '<a href="' . $h($moreHref) . '">' . $h($moreLabel) . '</a>' : '') . '</header>';
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
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_spaces.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_unit_space.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/jnet_unit_shell.css')) ?>">
</head>
<body class="us us--<?= $h($look['theme']) ?>" style="<?= $h(S::cssVars($look)) ?>">
<a class="us-skip" href="#us-main">Aller au contenu</a>

<header class="us-top">
    <a class="us-top__net" href="<?= $h(url('jnet?commun=1')) ?>" title="Retour à l’espace commun">
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="m14 6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        JNET
    </a>
    <a class="us-top__unit" href="<?= $h($tabUrl('accueil')) ?>">
        <?= $emblemHtml('us-top__emblem') ?>
        <span><?= $h($label) ?></span>
    </a>
    <?php if (count($rail) > 1): ?>
    <details class="us-switch">
        <summary>Changer d’espace</summary>
        <ul>
            <?php foreach ($rail as $s): ?>
                <li><a href="<?= $h((string) $s['href']) ?>"<?= !empty($s['active']) ? ' aria-current="page"' : '' ?> style="--d: <?= min(5, max(0, (int) ($s['depth'] ?? 0))) ?>"><?= $h((string) $s['label']) ?><?= !empty($s['mine']) ? ' <small>votre unité</small>' : '' ?></a></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
    <span class="us-top__spacer"></span>
    <span class="us-top__access"><?= $h((string) $look['access_label']) ?></span>
    <?php if ($operator !== ''): ?><span class="us-top__who" translate="no"><?= $h($operator) ?></span><?php endif; ?>
    <?php if (!empty($canCustomize)): ?>
        <a class="us-top__btn<?= $view === 'personnaliser' ? ' is-on' : '' ?>" href="<?= $h((string) ($customizeUrl ?? '')) ?>">Personnaliser</a>
    <?php endif; ?>
</header>

<nav class="us-tabs" aria-label="Rubriques de l’espace">
    <?php foreach ($tabs as $key => [$tabLabel, $count]): ?>
        <a class="us-tab<?= $view === $key ? ' is-on' : '' ?>" href="<?= $h($tabUrl($key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= $h($tabLabel) ?><?php if ($count > 0): ?><i><?= min(99, (int) $count) ?></i><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<main class="us-main jnet-embed" id="us-main">
    <?php if ($flashErr): ?><p class="us-flash is-bad" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="us-flash" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>

<?php if ($view === 'personnaliser'): ?>
    <?php require base_path('views/jnet/customize.php'); ?>

<?php elseif ($view === 'echanges'): ?>
    <header class="us-page">
        <h1>Ordres et échanges</h1>
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
        <h1>Effectif</h1>
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
        <h1>Opérations</h1>
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
        <h1>Sous-unités</h1>
        <p>Chaque sous-unité a son propre espace. Ce qu’elle publie pour <?= $h($label) ?> arrive dans « Ordres et échanges ».</p>
    </header>
    <?php $viewNodes = $subTree; $viewId = 'us-sub-units'; $viewGrouped = false; require base_path('views/jnet/_unit_views.php'); ?>

<?php else: ?>
    <section class="us-hero<?= $banner !== '' ? ' has-banner' : '' ?>" aria-labelledby="us-title">
        <?php if ($banner !== ''): ?><div class="us-hero__bg" style="background-image: url('<?= $h($banner) ?>')" aria-hidden="true"></div><?php endif; ?>
        <div class="us-hero__id">
            <?= $emblemHtml('us-hero__emblem') ?>
            <div>
                <?php if (count($chain) > 1): ?>
                <nav class="us-hero__chain" aria-label="Échelons">
                    <?php foreach ($chain as $i => $c): if ((int) $c['id'] === $unitId) { continue; } ?>
                        <a href="<?= $h((string) $c['href']) ?>"><?= $h((string) $c['label']) ?></a><span aria-hidden="true">›</span>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>
                <h1 id="us-title"><?= $h($label) ?></h1>
                <?php if ((string) $look['tagline'] !== ''): ?><p class="us-hero__tagline">« <?= $h((string) $look['tagline']) ?> »</p><?php endif; ?>
                <p class="us-hero__lead">Chef : <b><?= $h((string) ($space['leader'] ?? '') !== '' ? (string) $space['leader'] : 'non désigné') ?></b> · Adjoint : <b><?= $h((string) ($space['deputy'] ?? '') !== '' ? (string) $space['deputy'] : 'non désigné') ?></b></p>
            </div>
        </div>
        <dl class="us-hero__figures">
            <div><dt>Disponibles</dt><dd><?= (int) $strength['available'] ?><small>/<?= (int) $strength['total'] ?></small></dd></div>
            <div><dt>Opérations</dt><dd><?= (int) ($spaceOpsTotal ?? count($spaceOps)) ?></dd></div>
            <div><dt>Sous-unités</dt><dd><?= count($subTree) ?></dd></div>
        </dl>
    </section>

    <?php if ((string) $look['welcome'] !== ''): ?>
        <section class="us-welcome" aria-label="Mot du chef d’unité">
            <h2>Mot d’accueil</h2>
            <p><?= nl2br($h((string) $look['welcome'])) ?></p>
        </section>
    <?php endif; ?>

    <div class="us-grid">
        <div class="us-col">
            <section class="us-block">
                <?= $section('Reçu du commandement', $tabUrl('echanges'), 'Tout voir') ?>
                <?php if (empty($exchangesReady)): ?>
                    <p class="us-empty">Les échanges ne sont pas encore activés.</p>
                <?php elseif ($received === []): ?>
                    <p class="us-empty">Aucun ordre ni consigne pour l’instant.</p>
                <?php else: ?>
                    <?php $exVariant = 'card'; $exPerspective = 'down'; foreach (array_slice($received, 0, 3) as $ex) { require base_path('views/jnet/_exchange.php'); } ?>
                <?php endif; ?>
            </section>
            <section class="us-block">
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
            <section class="us-block">
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
            <section class="us-block">
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
            <section class="us-block">
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

<footer class="us-foot">
    <span>JNET<?= (string) ($usCommunity ?? '') !== '' ? ' · ' . $h((string) $usCommunity) : '' ?></span>
    <a href="<?= $h(url('jnet?commun=1')) ?>">Espace commun</a>
    <a href="<?= $h(url('jnet/courrier')) ?>">Messagerie</a>
    <a href="<?= $h(url('dashboard')) ?>">Quitter JNET</a>
</footer>
<script src="<?= $h(asset_url('assets/js/jnet_spaces.js')) ?>" defer></script>
</body>
</html>
