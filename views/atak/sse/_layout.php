<?php
declare(strict_types=1);
/**
 * Bureau SSE — coque commune à toutes les pages du portail : barre du haut, bandeau d'habilitation,
 * navigation rangée par usage (App\Support\SsePortalNav), fil d'Ariane et onglets de section.
 * Navigation instantanée : aucun effet de transition entre les pages.
 * @var string $title
 * @var string $activeNav
 * @var bool $canManage
 * @var bool $canGrant
 * @var bool $isGuest
 * @var int $clearanceUntil
 * @var string $guestLabel
 * @var string $sseContent
 */
use App\Support\SsePortalNav;

$activeNav = (string) ($activeNav ?? 'operations');
$canManage = (bool) ($canManage ?? false);
$canGrant = (bool) ($canGrant ?? false);
$isGuest = (bool) ($isGuest ?? false);
$clearanceUntil = (int) ($clearanceUntil ?? 0);
$guestLabel = (string) ($guestLabel ?? '');
$sseTheme = sse_ui_theme_normalize((string) ($sseTheme ?? sse_ui_theme()));
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseContent = $sseContent ?? '';
$pageTitle = (string) ($title ?? 'Bureau SSE');
$currentPath = (string) ($_SERVER['REQUEST_URI'] ?? url('atak/sse/operations'));
$nowTs = time();
$zulu = gmdate('H:i:s') . 'Z';
$expiresLabel = $clearanceUntil > 0 ? date('H:i', $clearanceUntil) : '';
$sessionRemainingLabel = '';
if ($clearanceUntil > $nowTs) {
    $remainSec = $clearanceUntil - $nowTs;
    $remainH = intdiv($remainSec, 3600);
    $remainM = intdiv($remainSec % 3600, 60);
    $sessionRemainingLabel = $remainH > 0
        ? sprintf('%dh %02d', $remainH, $remainM)
        : sprintf('%d min', max(1, $remainM));
} elseif ($clearanceUntil > 0) {
    $sessionRemainingLabel = 'expirée';
}
$sessionKindLabel = $isGuest ? 'Session invitée' : 'Session authentifiée';
$classifBanner = function_exists('sse_ui_classification_label')
    ? sse_ui_classification_label()
    : 'Confidentiel';

// Navigation unique (barre latérale, accueil, fil d'Ariane, palette Ctrl+K), filtrée selon les droits.
$sseNavData ??= SsePortalNav::forSession($canManage, $canGrant, $isGuest, isset($canMemberPapers) ? (bool) $canMemberPapers : null);
$sseNav = $sseNavData['sections'];
$sseNavActive = SsePortalNav::activeId($sseNav, $currentPath, $activeNav);
$sseNavOpenSection = SsePortalNav::sectionOf($sseNav, $sseNavActive);
$sseUrl = static fn (string $p): string => (string) url($p);
$ssePalette = SsePortalNav::paletteEntries($sseNav, $sseUrl, $canManage, $isGuest);
$sseCrumbs = SsePortalNav::breadcrumb($sseNav, $sseNavActive, $currentPath, $pageTitle, $sseUrl);
$sseSectionTabs = [];
foreach ($sseNav as $section) {
    if ($section['id'] === $sseNavOpenSection) {
        $sseSectionTabs = $section['items'];
    }
}

// Habilitation réelle de la session (plafond de lecture), affichée dans le bandeau.
$sseClearanceKey = 'interne';
$sseClearanceText = 'Diffusion interne';
$sseClearanceOrigin = '';
try {
    $sseClearanceSvc = new \App\Services\Sse\SseClearanceService();
    $sseClearanceKey = $sseClearanceSvc->maxLevel();
    $sseClearanceText = \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS[$sseClearanceKey] ?? $classifBanner;
    $sseClearanceOrigin = $sseClearanceSvc->origin();
} catch (\Throwable) {
    $sseClearanceText = $classifBanner;
    $sseClearanceKey = array_search($classifBanner, \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS, true) ?: 'confidentiel';
}
$sseClearanceTone = match ($sseClearanceKey) {
    'tres_restreint' => 'restricted',
    'confidentiel' => 'confidential',
    'encadrement' => 'command',
    default => 'internal',
};
// Polices servies par le site et préchargées : le texte ne change pas de police (ni de taille) après l'affichage.
$sseFontSans = asset_url('assets/fonts/nine-line/inter-latin.woff2');
$sseFontMono = asset_url('assets/fonts/nine-line/jetbrains-mono-latin.woff2');
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="dark">
    <title><?= $h($pageTitle) ?> — SSE</title>
    <link rel="preload" href="<?= $h($sseFontSans) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= $h($sseFontMono) ?>" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: "Inter"; font-style: normal; font-weight: 400 900; font-display: swap; src: url("<?= $h($sseFontSans) ?>") format("woff2"); }
        @font-face { font-family: "JetBrains Mono"; font-style: normal; font-weight: 400 800; font-display: swap; src: url("<?= $h($sseFontMono) ?>") format("woff2"); }
    </style>
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_workspace.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_field_note.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_shell.css')) ?>">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/app-update-modal.css')) ?>">
    <?php if (!empty($sseNeedLeaflet)): ?>
        <link rel="stylesheet" href="<?= $h(asset_url('assets/vendor/leaflet-1.9.4/leaflet.css')) ?>">
    <?php endif; ?>
    <?php if (!empty($sseExtraStyles)): ?>
        <?= $sseExtraStyles ?>
    <?php endif; ?>
</head>
<body class="sse-theme-bureau sse-iw">
<div class="iw-app sx-app" data-sse-shell>
    <a class="sx-skip" href="#sx-main">Aller au contenu</a>
    <header class="iw-topbar sx-topbar" role="banner">
        <button type="button" class="sx-burger" aria-controls="sx-nav" aria-expanded="false" data-sx-nav-toggle>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <span>Menu</span>
        </button>
        <a class="iw-brand sx-brand" href="<?= $h(url(SsePortalNav::HOME)) ?>">
            <strong>SSE</strong>
            <span>Bureau de renseignement</span>
        </a>

        <form class="iw-search sx-search" method="get" action="<?= $h(url('atak/sse/recherche')) ?>" role="search" id="iw-search-form" data-suggest-url="<?= $h(url('atak/sse/recherche/suggestions')) ?>">
            <label class="sr-only" for="iw-q">Recherche globale</label>
            <input id="iw-q" name="q" type="search" placeholder="Rechercher identités, sites, matériels, documents…"
                   value="<?= $h((string) ($_GET['q'] ?? '')) ?>" autocomplete="off" enterkeyhint="search">
            <button type="submit" aria-label="Lancer la recherche">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
            <div class="iw-search-suggest" id="iw-search-suggest" hidden role="listbox" aria-label="Suggestions"></div>
        </form>
        <button type="button" class="sx-cmd" data-sx-palette-open aria-haspopup="dialog" title="Aller à une page ou lancer une action">
            <span>Aller à une page</span><kbd>Ctrl</kbd><kbd>K</kbd>
        </button>

        <div class="sx-session" title="<?= $h($sessionKindLabel . ($expiresLabel !== '' ? ' · expire à ' . $expiresLabel : '')) ?>">
            <span class="sx-session__zulu" data-sx-zulu><?= $h($zulu) ?></span>
            <span class="sx-session__left<?= $clearanceUntil > 0 && $clearanceUntil - $nowTs < 900 ? ' is-low' : '' ?>" data-sx-countdown="<?= $clearanceUntil ?>">
                <?= $h($sessionRemainingLabel !== '' ? $sessionRemainingLabel : '—') ?>
            </span>
        </div>

        <div class="iw-actions sx-actions">
            <?php if (!$isGuest): ?>
                <a class="iw-btn iw-btn--solid" href="<?= $h(url('atak/sse/fiches/nouvelle')) ?>">Rédiger une fiche</a>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <details class="sx-new">
                    <summary class="iw-btn">Nouveau <span aria-hidden="true">▾</span></summary>
                    <div class="sx-new__menu" role="menu">
                        <a role="menuitem" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Investigation</a>
                        <a role="menuitem" href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>">Dossier</a>
                        <a role="menuitem" href="<?= $h(url('atak/sse/interet/nouveau')) ?>">Dossier d’intérêt</a>
                        <a role="menuitem" href="<?= $h(url('atak/sse/objets/nouveau')) ?>">Objet (identité, site, véhicule…)</a>
                        <a role="menuitem" href="<?= $h(url('atak/sse/documents/nouveau')) ?>">Document</a>
                    </div>
                </details>
            <?php endif; ?>
            <a class="iw-btn iw-btn--ghost" href="<?= $h(url('atak/sse/quitter')) ?>">Quitter</a>
        </div>
    </header>

    <div class="iw-classbar sx-classbar sx-classbar--<?= $h($sseClearanceTone) ?>" role="status" title="<?= $h($sseClearanceOrigin) ?>">
        <strong>Habilitation <?= $h(mb_strtoupper($sseClearanceText)) ?></strong>
        <span>Diffusion restreinte · personnel habilité · journalisation active</span>
        <?php if ($isGuest && $guestLabel !== ''): ?>
            <em><?= $h($guestLabel) ?></em>
        <?php endif; ?>
    </div>

    <div class="iw-shell sx-shell">
        <aside class="iw-nav sx-nav" id="sx-nav" aria-label="Navigation du bureau SSE">
            <button type="button" class="sx-nav__close" data-sx-nav-close>Fermer le menu</button>
            <nav aria-label="Espaces du bureau SSE">
                <?php foreach ($sseNav as $section): ?>
                    <details class="sx-nav__group" data-sx-group="<?= $h($section['id']) ?>" open>
                        <summary class="sx-nav__head"><?= $h($section['label']) ?></summary>
                        <ul class="sx-nav__list">
                            <?php foreach ($section['items'] as $item): ?>
                                <?php $isActive = $item['id'] === $sseNavActive; ?>
                                <li>
                                    <a href="<?= $h(url($item['path'])) ?>" class="iw-nav-link sx-nav__link<?= $isActive ? ' is-active' : '' ?>" title="<?= $h($item['hint']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                                        <?= SsePortalNav::icon((string) $item['icon']) ?>
                                        <span class="sx-nav__label"><?= $h($item['label']) ?></span>
                                        <?php if (!empty($item['external'])): ?><span class="sx-nav__ext" aria-label="ouvre l’ATAK">↗</span><?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            </nav>

            <div class="iw-nav-foot sx-nav__foot">
                <span><?= $h($sessionKindLabel) ?> · <?= $h($sseClearanceText) ?></span>
                <span class="sx-nav__keys"><kbd>Ctrl</kbd> <kbd>K</kbd> pour aller à une page</span>
            </div>
        </aside>
        <script>
        /* Avant le premier affichage : sections repliées par l'utilisateur et défilement de la barre latérale
           restaurés, pour que la navigation ne bouge pas d'une page à l'autre. */
        (function () {
          try {
            var nav = document.getElementById('sx-nav');
            var saved = JSON.parse(window.localStorage.getItem('sse.nav.groups') || '{}') || {};
            Array.prototype.forEach.call(nav.querySelectorAll('[data-sx-group]'), function (g) {
              var id = g.getAttribute('data-sx-group');
              if (saved[id] === false && !g.querySelector('.is-active')) g.open = false;
            });
            var top = parseInt(window.sessionStorage.getItem('sse.nav.scroll') || '0', 10) || 0;
            nav.scrollTop = top;
            var a = nav.querySelector('.is-active');
            if (a) {
              var r = a.getBoundingClientRect(), n = nav.getBoundingClientRect();
              if (r.top < n.top || r.bottom > n.bottom) nav.scrollTop += r.top - n.top - n.height / 3;
            }
          } catch (e) {}
        })();
        </script>
        <div class="sx-scrim" data-sx-nav-close hidden></div>

        <main class="iw-main sx-main" id="sx-main" tabindex="-1">
            <?php if (count($sseCrumbs) > 1): ?>
            <nav class="sx-crumbs" aria-label="Fil d’Ariane">
                <ol>
                    <?php foreach ($sseCrumbs as $i => $crumb): ?>
                        <li>
                            <?php if ($crumb['href'] !== null): ?>
                                <a href="<?= $h($crumb['href']) ?>"><?= $h($crumb['label']) ?></a>
                            <?php else: ?>
                                <span aria-current="page"><?= $h($crumb['label']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
            <?php if (count($sseSectionTabs) > 1): ?>
                <nav class="sx-tabs" aria-label="Pages de la section">
                    <?php foreach ($sseSectionTabs as $item): ?>
                        <?php $isActive = $item['id'] === $sseNavActive; ?>
                        <a href="<?= $h(url($item['path'])) ?>" class="<?= $isActive ? 'is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= $h($item['label']) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <?php if ($error): ?><div class="flash flash--error" role="alert"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="flash flash--ok" role="status"><?= $h($success) ?></div><?php endif; ?>
            <?= $sseContent ?>
        </main>
    </div>
</div>

<script>
window.SSE_CTX = {
  csrf: <?= json_encode(\App\Core\Csrf::token(), $jsonFlags) ?>,
  canManage: <?= $canManage ? 'true' : 'false' ?>,
  pageTitle: 'Bureau SSE',
  pageActions: [
    { label: 'Rédiger une fiche de renseignement', href: <?= json_encode(url('atak/sse/fiches/nouvelle'), $jsonFlags) ?> },
    { label: 'Accueil du bureau', href: <?= json_encode(url(SsePortalNav::HOME), $jsonFlags) ?> },
    { label: 'Vue opérationnelle', href: <?= json_encode(url('atak/sse/operations'), $jsonFlags) ?> },
    { label: 'Transmissions terrain', href: <?= json_encode(url('atak/sse/transmissions'), $jsonFlags) ?> },
    { label: 'Investigations', href: <?= json_encode(url('atak/sse/toiles'), $jsonFlags) ?> },
    { label: 'Recherche', href: <?= json_encode(url('atak/sse/recherche'), $jsonFlags) ?> }
    <?php if ($canManage): ?>
    , { separator: true }
    , { label: 'Ouvrir une investigation', href: <?= json_encode(url('atak/sse/toiles/nouveau'), $jsonFlags) ?> }
    , { label: 'Créer un objet', href: <?= json_encode(url('atak/sse/objets/nouveau'), $jsonFlags) ?> }
    <?php endif; ?>
  ]
};
</script>
<dialog class="sx-palette" id="sx-palette" aria-label="Aller à une page">
    <div class="sx-palette__box">
        <label class="sr-only" for="sx-palette-q">Page ou action</label>
        <input id="sx-palette-q" type="search" placeholder="Aller à… (dossiers, papiers, fichiers, nouvelle fiche…)" autocomplete="off" spellcheck="false">
        <ul class="sx-palette__list" id="sx-palette-list" role="listbox" aria-label="Résultats"></ul>
        <p class="sx-palette__foot"><kbd>↑</kbd><kbd>↓</kbd> choisir · <kbd>Entrée</kbd> ouvrir · <kbd>Échap</kbd> fermer</p>
    </div>
</dialog>
<script type="application/json" id="sx-palette-data"><?= json_encode($ssePalette, $jsonFlags | JSON_HEX_QUOT) ?></script>
<script src="<?= $h(asset_url('assets/js/sse-context-menu.js')) ?>"></script>
<script src="<?= $h(asset_url('assets/js/sse-shell.js')) ?>"></script>
<script src="<?= $h(asset_url('assets/js/sse-global-search.js')) ?>"></script>
<script>
  window.APP_VERSION = <?= json_encode(platform_app_version(), JSON_UNESCAPED_UNICODE) ?>;
  window.APP_BASE_URL = <?= json_encode(rtrim((string) url(''), '/'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script defer src="<?= $h(asset_url('assets/js/app-version-check.js')) ?>"></script>
<?php if (!empty($sseNeedLeaflet)): ?>
<script src="<?= $h(asset_url('assets/vendor/leaflet-1.9.4/leaflet.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($sseExtraScripts)): ?>
<?= $sseExtraScripts ?>
<?php endif; ?>
<script defer src="<?= $h(asset_url('assets/js/identity-country-picker.js')) ?>"></script>
</body>
</html>
