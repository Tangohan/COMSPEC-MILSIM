<?php
declare(strict_types=1);
/**
 * SSE Intelligence Workspace — coque plein écran.
 * @var string $title
 * @var string $activeNav
 * @var bool $canManage
 * @var bool $canGrant
 * @var bool $isGuest
 * @var int $clearanceUntil
 * @var string $guestLabel
 * @var string $sseContent
 */
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
$pageTitle = (string) ($title ?? 'SSE Intelligence Workspace');
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

// Navigation unique (barre latérale + palette Ctrl+K), rangée selon le cycle du renseignement.
$sseNav = \App\Support\SsePortalNav::sections($canManage, $canGrant, $isGuest);
$sseNavActive = \App\Support\SsePortalNav::activeId($sseNav, $currentPath, $activeNav);
$sseNavOpenSection = \App\Support\SsePortalNav::sectionOf($sseNav, $sseNavActive);
$ssePalette = \App\Support\SsePortalNav::paletteEntries($sseNav, static fn (string $p): string => (string) url($p), $canManage, $isGuest);

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= $h($pageTitle) ?> — SSE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608280200">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_workspace.css')) ?>?v=202608280152">
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
<script>
/* Arrivée par une transition : le rideau est posé avant le premier affichage, puis se lève (sse-shell.js). */
(function () {
  try {
    var t = parseInt(window.sessionStorage.getItem('sse.curtain') || '0', 10);
    if (!t || Date.now() - t > 8000 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var n = Math.max(6, Math.min(14, Math.round(window.innerWidth / 120)));
    var w = document.createElement('div');
    w.className = 'sx-curtains is-held is-active';
    w.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < n; i++) { var c = document.createElement('span'); c.className = 'sx-curtain'; c.style.setProperty('--i', String(i)); w.appendChild(c); }
    document.body.appendChild(w);
  } catch (e) {}
})();
</script>

<div class="iw-app sx-app" data-sse-shell>
    <a class="sx-skip" href="#sx-main">Aller au contenu</a>
    <header class="iw-topbar sx-topbar" role="banner">
        <button type="button" class="sx-burger" aria-controls="sx-nav" aria-expanded="false" data-sx-nav-toggle>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <span class="sr-only">Menu</span>
        </button>
        <a class="iw-brand sx-brand" href="<?= $h(url('atak/sse/operations')) ?>">
            <strong data-scramble>SSE</strong>
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
            <span>Aller à…</span><kbd>Ctrl</kbd><kbd>K</kbd>
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
            <a class="iw-btn iw-btn--ghost" href="<?= $h(url('atak/sse/quitter')) ?>" data-no-curtain>Quitter</a>
        </div>
    </header>

    <div class="iw-classbar sx-classbar sx-classbar--<?= $h($sseClearanceTone) ?>" role="status" title="<?= $h($sseClearanceOrigin) ?>">
        <strong><span class="sx-classbar__k">Habilitation</span> <span data-scramble><?= $h(mb_strtoupper($sseClearanceText)) ?></span></strong>
        <span>Diffusion restreinte · personnel habilité · journalisation active</span>
        <?php if ($isGuest && $guestLabel !== ''): ?>
            <em><?= $h($guestLabel) ?></em>
        <?php endif; ?>
    </div>

    <div class="iw-shell sx-shell">
        <aside class="iw-nav sx-nav" id="sx-nav" aria-label="Navigation du bureau SSE">
            <?php
            $iwIcon = static function (string $name): string {
                $common = ' class="iw-nav-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
                return match ($name) {
                    'ops' => '<svg' . $common . '><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
                    'inbox' => '<svg' . $common . '><path d="M3.5 13.5 6 5h12l2.5 8.5V19a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1z"/><path d="M3.5 13.5H8l1.5 2.5h5l1.5-2.5h4.5"/></svg>',
                    'mesh' => '<svg' . $common . '><circle cx="6" cy="7" r="2.2"/><circle cx="18" cy="7" r="2.2"/><circle cx="12" cy="17" r="2.2"/><path d="M8 8.2 10.5 15M16 8.2 13.5 15M8.2 7h7.6"/></svg>',
                    'interest' => '<svg' . $common . '><path d="M12 3.5 14.2 9l5.8.5-4.4 3.8 1.4 5.7L12 16.2 6.9 19l1.4-5.7L4 9.5 9.8 9z"/></svg>',
                    'folder' => '<svg' . $common . '><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l1.5 2H19.5A1.5 1.5 0 0 1 21 9.5v8A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>',
                    'id' => '<svg' . $common . '><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c1.6-3.2 4-4.8 6.5-4.8S16.9 15.8 18.5 19"/></svg>',
                    'org' => '<svg' . $common . '><path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/><path d="M9 10h.01M15 10h.01"/></svg>',
                    'site' => '<svg' . $common . '><path d="M12 21s-7-5.2-7-11a7 7 0 1 1 14 0c0 5.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>',
                    'vehicle' => '<svg' . $common . '><path d="M4 15h16l-1.2-5.2A2 2 0 0 0 16.9 8H7.1a2 2 0 0 0-1.9 1.8L4 15z"/><path d="M6.5 15v2.5M17.5 15v2.5M7.5 11h9"/><circle cx="7.5" cy="17.5" r="1.2"/><circle cx="16.5" cy="17.5" r="1.2"/></svg>',
                    'gear' => '<svg' . $common . '><path d="M12 8.2a3.8 3.8 0 1 0 0 7.6 3.8 3.8 0 0 0 0-7.6z"/><path d="M12 3.2v2M12 18.8v2M4.8 6.4l1.5 1.5M17.7 16.1l1.5 1.5M3.2 12h2M18.8 12h2M4.8 17.6l1.5-1.5M17.7 7.9l1.5-1.5"/></svg>',
                    'doc' => '<svg' . $common . '><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4M9 12h6M9 15.5h6"/></svg>',
                    'time' => '<svg' . $common . '><circle cx="12" cy="12" r="8.2"/><path d="M12 7.5V12l3.2 2"/></svg>',
                    'map' => '<svg' . $common . '><path d="M3.5 6.5 9 4.5l6 2 5.5-2v13l-5.5 2-6-2-5.5 2z"/><path d="M9 4.5v13M15 6.5v13"/></svg>',
                    'cross' => '<svg' . $common . '><circle cx="8" cy="8" r="3"/><circle cx="16" cy="16" r="3"/><path d="M10.2 10.2 13.8 13.8"/></svg>',
                    'engine' => '<svg' . $common . '><path d="M4 13h3l1.5-3h3L13 13h3l2-3h2"/><path d="M5 16h14"/><circle cx="8" cy="19" r="1.2"/><circle cx="16" cy="19" r="1.2"/></svg>',
                    'alert' => '<svg' . $common . '><path d="M12 4 21 19H3z"/><path d="M12 10v4M12 16.5h.01"/></svg>',
                    'lab' => '<svg' . $common . '><path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"/><path d="M8.2 14h7.6"/></svg>',
                    'prepare' => '<svg' . $common . '><path d="M4 19h16M7 16 12 5l5 11"/><path d="M9.2 12h5.6"/></svg>',
                    'collect' => '<svg' . $common . '><path d="M4 7h16v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7"/><path d="M12 11v4M10 13h4"/></svg>',
                    'target' => '<svg' . $common . '><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>',
                    'queue' => '<svg' . $common . '><path d="M5 7h14M5 12h14M5 17h9"/><path d="M17 15.5 19.5 18 22 14"/></svg>',
                    'report' => '<svg' . $common . '><path d="M5 4h10l4 4v12H5z"/><path d="M15 4v4h4M8 13h8M8 16.5h5"/></svg>',
                    'write' => '<svg' . $common . '><path d="M4 19.5 5.5 14 16 3.5 20 7.5 9.5 18z"/><path d="M13.5 6 17.5 10"/></svg>',
                    'paper' => '<svg' . $common . '><rect x="5" y="3.5" width="14" height="17" rx="1"/><path d="M5 7.5h14M5 16.5h14"/></svg>',
                    'note' => '<svg' . $common . '><path d="M6 3.5h9l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19V5a1.5 1.5 0 0 1 1-1.5z"/><path d="M15 3.5V8h4"/><path d="M8.5 12h7M8.5 15.5h4.5"/></svg>',
                    'library' => '<svg' . $common . '><path d="M5 4h4v16H5zM10.5 4H15v16h-4.5zM16.5 6.5 21 5v14.5L16.5 21z"/></svg>',
                    'flag' => '<svg' . $common . '><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>',
                    'dice' => '<svg' . $common . '><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 8.5h.01M15.5 15.5h.01M12 12h.01M15.5 8.5h.01M8.5 15.5h.01"/></svg>',
                    'admin' => '<svg' . $common . '><path d="M12 3.5 19 7v5.2c0 4.2-2.8 7.4-7 8.8-4.2-1.4-7-4.6-7-8.8V7z"/><path d="M9.5 12.2 11.2 14l3.5-3.8"/></svg>',
                    'help' => '<svg' . $common . '><circle cx="12" cy="12" r="8.2"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2.9-1.2 1.7V14"/><path d="M12 17h.01"/></svg>',
                    default => '<svg' . $common . '><circle cx="12" cy="12" r="7"/></svg>',
                };
            };
            ?>
            <?php foreach ($sseNav as $section): ?>
                <?php $open = $section['id'] === $sseNavOpenSection || in_array($section['id'], ['pilotage', 'dossiers'], true); ?>
                <details class="sx-nav__group" data-sx-group="<?= $h($section['id']) ?>"<?= $open ? ' open' : '' ?>>
                    <summary class="iw-nav-section sx-nav__head"><?= $h($section['label']) ?><span class="sx-nav__count"><?= count($section['items']) ?></span></summary>
                    <ul class="sx-nav__list">
                        <?php foreach ($section['items'] as $item): ?>
                            <?php $isActive = $item['id'] === $sseNavActive; ?>
                            <li>
                                <a href="<?= $h(url($item['path'])) ?>" class="iw-nav-link sx-nav__link<?= $isActive ? ' is-active' : '' ?>" title="<?= $h($item['hint']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?><?= !empty($item['external']) ? ' data-no-curtain' : '' ?>>
                                    <?= $iwIcon((string) $item['icon']) ?>
                                    <span class="sx-nav__label"><?= $h($item['label']) ?></span>
                                    <?php if (!empty($item['external'])): ?><span class="sx-nav__ext" aria-label="ouvre l’ATAK">↗</span><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endforeach; ?>

            <div class="iw-nav-foot sx-nav__foot">
                <span><?= $h($sessionKindLabel) ?></span>
                <strong><?= $h($sseClearanceText) ?></strong>
                <span class="sx-nav__keys"><kbd>Ctrl</kbd> <kbd>K</kbd> pour aller à une page</span>
            </div>
        </aside>
        <div class="sx-scrim" data-sx-nav-close hidden></div>

        <main class="iw-main sx-main" id="sx-main" tabindex="-1">
            <?php if ($error): ?><div class="flash flash--error"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="flash flash--ok"><?= $h($success) ?></div><?php endif; ?>
            <?= $sseContent ?>
        </main>
    </div>
</div>

<script>
window.SSE_CTX = {
  csrf: <?= json_encode(\App\Core\Csrf::token(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  canManage: <?= $canManage ? 'true' : 'false' ?>,
  pageTitle: 'Bureau SSE',
  pageActions: [
    { label: 'Rédiger une fiche de renseignement', href: <?= json_encode(url('atak/sse/fiches/nouvelle'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> },
    { label: 'Vue opérationnelle', href: <?= json_encode(url('atak/sse/operations'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> },
    { label: 'Transmissions terrain', href: <?= json_encode(url('atak/sse/transmissions'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> },
    { label: 'Investigations', href: <?= json_encode(url('atak/sse/toiles'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> },
    { label: 'Recherche', href: <?= json_encode(url('atak/sse/recherche'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> }
    <?php if ($canManage): ?>
    , { separator: true }
    , { label: 'Ouvrir une investigation', href: <?= json_encode(url('atak/sse/toiles/nouveau'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> }
    , { label: 'Créer un objet', href: <?= json_encode(url('atak/sse/objets/nouveau'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> }
    <?php endif; ?>
  ]
};
</script>
<dialog class="sx-palette" id="sx-palette" aria-label="Aller à une page">
    <div class="sx-palette__box">
        <label class="sr-only" for="sx-palette-q">Page ou action</label>
        <input id="sx-palette-q" type="search" placeholder="Aller à… (dossiers, identités, rédaction, nouvelle fiche…)" autocomplete="off" spellcheck="false">
        <ul class="sx-palette__list" id="sx-palette-list" role="listbox" aria-label="Résultats"></ul>
        <p class="sx-palette__foot"><kbd>↑</kbd><kbd>↓</kbd> choisir · <kbd>Entrée</kbd> ouvrir · <kbd>Échap</kbd> fermer</p>
    </div>
</dialog>
<script type="application/json" id="sx-palette-data"><?= json_encode($ssePalette, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= $h(asset_url('assets/js/sse-context-menu.js')) ?>?v=202608072010"></script>
<script src="<?= $h(asset_url('assets/js/sse-shell.js')) ?>"></script>
<script src="<?= $h(asset_url('assets/js/sse-global-search.js')) ?>?v=202608161745"></script>
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
</body>
</html>
