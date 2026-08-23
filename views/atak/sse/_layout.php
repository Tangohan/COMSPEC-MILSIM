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

$navActive = static function (string $id) use ($activeNav): string {
    $aliases = [
        'dossiers' => ['dossiers', 'validated'],
        'interet' => ['interet', 'pressee'],
        'personnes' => ['personnes', 'identites', 'objets'],
        'toiles' => ['toiles', 'graphe'],
        'operations' => ['operations'],
    ];
    foreach ($aliases as $key => $list) {
        if ($activeNav === $id || (in_array($activeNav, $list, true) && $id === $key)) {
            // fallthrough
        }
    }
    if ($activeNav === $id) {
        return 'is-active';
    }
    if ($id === 'identites' && in_array($activeNav, ['personnes', 'identites'], true)) {
        return 'is-active';
    }
    if ($id === 'pressee' && $activeNav === 'interet') {
        return 'is-active';
    }
    if ($id === 'validated' && $activeNav === 'dossiers') {
        return 'is-active';
    }
    if ($id === 'graphe' && $activeNav === 'toiles') {
        return 'is-active';
    }
    if ($id === 'operations' && $activeNav === 'operations') {
        return 'is-active';
    }

    return '';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608222340">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_workspace.css')) ?>?v=202608230521">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_field_note.css')) ?>">
    <?php if (!empty($sseNeedLeaflet)): ?>
        <link rel="stylesheet" href="<?= $h(asset_url('assets/vendor/leaflet-1.9.4/leaflet.css')) ?>">
    <?php endif; ?>
</head>
<body class="sse-theme-bureau sse-iw">

<div class="iw-app">
    <header class="iw-topbar" role="banner">
        <div class="iw-brand">
            <strong>SSE</strong>
            <span>Intelligence Workspace</span>
        </div>

        <form class="iw-search" method="get" action="<?= $h(url('atak/sse/recherche')) ?>" role="search" id="iw-search-form" data-suggest-url="<?= $h(url('atak/sse/recherche/suggestions')) ?>">
            <label class="sr-only" for="iw-q">Recherche globale</label>
            <input id="iw-q" name="q" type="search" placeholder="Rechercher identités, sites, matériels, véhicules, documents, événements…"
                   value="<?= $h((string) ($_GET['q'] ?? '')) ?>" autocomplete="off" enterkeyhint="search">
            <button type="submit" aria-label="Lancer la recherche">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
            <div class="iw-search-suggest" id="iw-search-suggest" hidden role="listbox" aria-label="Suggestions"></div>
        </form>

        <div class="iw-status" aria-label="État de synchronisation">
            <span class="iw-pill iw-pill--ok" title="Liaison Athena">Athena</span>
            <span class="iw-pill iw-pill--ok" title="Clients Arma / Overwatch">Arma</span>
            <span class="iw-pill" title="Sources serveur">3 src</span>
            <span class="iw-zulu" title="Heure Zulu"><?= $h($zulu) ?></span>
            <span class="iw-session" title="<?= $h($sessionKindLabel) ?>">
                <?= $h($sessionRemainingLabel !== '' ? $sessionRemainingLabel : '—') ?>
            </span>
        </div>

        <div class="iw-actions">
            <?php if (!$isGuest): ?>
                <a class="iw-btn" href="<?= $h(url('atak/sse/fiches/nouvelle')) ?>">Rédiger une fiche</a>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <a class="iw-btn" href="<?= $h(url('atak/sse/objets/nouveau')) ?>">Créer un objet</a>
                <a class="iw-btn iw-btn--solid" href="<?= $h(url('atak/sse/toiles/nouveau')) ?>">Ouvrir une investigation</a>
            <?php endif; ?>
            <a class="iw-btn iw-btn--ghost" href="<?= $h(url('atak/sse/quitter')) ?>">Quitter</a>
        </div>
    </header>

    <div class="iw-classbar" role="status">
        <strong><?= $h(mb_strtoupper($classifBanner)) ?></strong>
        <span>Diffusion restreinte — personnel habilité — journalisation active</span>
        <?php if ($isGuest && $guestLabel !== ''): ?>
            <em><?= $h($guestLabel) ?></em>
        <?php endif; ?>
    </div>

    <div class="iw-shell">
        <aside class="iw-nav" aria-label="CENTRE SSE">
            <p class="iw-nav-kicker">Centre SSE</p>

            <?php
            $iwIcon = static function (string $name): string {
                $common = ' class="iw-nav-ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
                return match ($name) {
                    'ops' => '<svg' . $common . '><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
                    'mesh' => '<svg' . $common . '><circle cx="6" cy="7" r="2.2"/><circle cx="18" cy="7" r="2.2"/><circle cx="12" cy="17" r="2.2"/><path d="M8 8.2 10.5 15M16 8.2 13.5 15M8.2 7h7.6"/></svg>',
                    'interest' => '<svg' . $common . '><path d="M12 3.5 14.2 9l5.8.5-4.4 3.8 1.4 5.7L12 16.2 6.9 19l1.4-5.7L4 9.5 9.8 9z"/></svg>',
                    'folder' => '<svg' . $common . '><path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l1.5 2H19.5A1.5 1.5 0 0 1 21 9.5v8A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5z"/></svg>',
                    'id' => '<svg' . $common . '><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19c1.6-3.2 4-4.8 6.5-4.8S16.9 15.8 18.5 19"/></svg>',
                    'org' => '<svg' . $common . '><path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/><path d="M9 10h.01M15 10h.01"/></svg>',
                    'site' => '<svg' . $common . '><path d="M12 21s-7-5.2-7-11a7 7 0 1 1 14 0c0 5.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>',
                    'vehicle' => '<svg' . $common . '><path d="M4 15h16l-1.2-5.2A2 2 0 0 0 16.9 8H7.1a2 2 0 0 0-1.9 1.8L4 15z"/><path d="M6.5 15v2.5M17.5 15v2.5M7.5 11h9"/><circle cx="7.5" cy="17.5" r="1.2"/><circle cx="16.5" cy="17.5" r="1.2"/></svg>',
                    'gear' => '<svg' . $common . '><path d="M12 8.2a3.8 3.8 0 1 0 0 7.6 3.8 3.8 0 0 0 0-7.6z"/><path d="M12 3.2v2M12 18.8v2M4.8 6.4l1.5 1.5M17.7 16.1l1.5 1.5M3.2 12h2M18.8 12h2M4.8 17.6l1.5-1.5M17.7 7.9l1.5-1.5"/></svg>',
                    'doc' => '<svg' . $common . '><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M14 3.5V8h4M9 12h6M9 15.5h6"/></svg>',
                    'graph' => '<svg' . $common . '><circle cx="5" cy="12" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="12" cy="19" r="2"/><path d="M6.7 10.7 10.3 6.7M13.7 6.7 17.3 10.7M17.3 13.3 13.7 17.3M10.3 17.3 6.7 13.3"/></svg>',
                    'time' => '<svg' . $common . '><circle cx="12" cy="12" r="8.2"/><path d="M12 7.5V12l3.2 2"/></svg>',
                    'map' => '<svg' . $common . '><path d="M3.5 6.5 9 4.5l6 2 5.5-2v13l-5.5 2-6-2-5.5 2z"/><path d="M9 4.5v13M15 6.5v13"/></svg>',
                    'cross' => '<svg' . $common . '><circle cx="8" cy="8" r="3"/><circle cx="16" cy="16" r="3"/><path d="M10.2 10.2 13.8 13.8"/></svg>',
                    'engine' => '<svg' . $common . '><path d="M4 13h3l1.5-3h3L13 13h3l2-3h2"/><path d="M5 16h14"/><circle cx="8" cy="19" r="1.2"/><circle cx="16" cy="19" r="1.2"/></svg>',
                    'alert' => '<svg' . $common . '><path d="M12 4 21 19H3z"/><path d="M12 10v4M12 16.5h.01"/></svg>',
                    'lab' => '<svg' . $common . '><path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"/><path d="M8.2 14h7.6"/></svg>',
                    'prepare' => '<svg' . $common . '><path d="M4 19h16M7 16 12 5l5 11"/><path d="M9.2 12h5.6"/></svg>',
                    'collect' => '<svg' . $common . '><path d="M4 7h16v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7"/><path d="M12 11v4M10 13h4"/></svg>',
                    'queue' => '<svg' . $common . '><path d="M5 7h14M5 12h14M5 17h9"/><path d="M17 15.5 19.5 18 22 14"/></svg>',
                    'report' => '<svg' . $common . '><path d="M5 4h10l4 4v12H5z"/><path d="M15 4v4h4M8 13h8M8 16.5h5"/></svg>',
                    'write' => '<svg' . $common . '><path d="M4 19.5 5.5 14 16 3.5 20 7.5 9.5 18z"/><path d="M13.5 6 17.5 10"/></svg>',
                    'note' => '<svg' . $common . '><path d="M6 3.5h9l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 19V5a1.5 1.5 0 0 1 1-1.5z"/><path d="M15 3.5V8h4"/><path d="M8.5 12h7M8.5 15.5h4.5"/></svg>',
                    'library' => '<svg' . $common . '><path d="M5 4h4v16H5zM10.5 4H15v16h-4.5zM16.5 6.5 21 5v14.5L16.5 21z"/></svg>',
                    'admin' => '<svg' . $common . '><path d="M12 3.5 19 7v5.2c0 4.2-2.8 7.4-7 8.8-4.2-1.4-7-4.6-7-8.8V7z"/><path d="M9.5 12.2 11.2 14l3.5-3.8"/></svg>',
                    'help' => '<svg' . $common . '><circle cx="12" cy="12" r="8.2"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.6 2.2c-.8.4-1.2.9-1.2 1.7V14"/><path d="M12 17h.01"/></svg>',
                    default => '<svg' . $common . '><circle cx="12" cy="12" r="7"/></svg>',
                };
            };
            $iwLink = static function (
                string $href,
                string $class,
                string $num,
                string $label,
                string $hint,
                string $icon
            ) use ($h, $iwIcon): string {
                $cls = trim('iw-nav-link ' . $class);

                return '<a href="' . $h($href) . '" class="' . $h($cls) . '">'
                    . '<b>' . $h($num) . '</b>'
                    . $iwIcon($icon)
                    . '<span class="iw-nav-copy"><strong>' . $h($label) . '</strong><em>' . $h($hint) . '</em></span>'
                    . '</a>';
            };
            ?>

            <p class="iw-nav-section">Pilotage</p>
            <?= $iwLink(url('atak/sse/workspace'), $activeNav === 'workspace' ? 'is-active' : '', '00', 'Intelligence Workspace', 'Inbox, chronologie et relations — surface d’exploitation unifiée.', 'ops') ?>
            <?= $iwLink(url('atak/sse/operations'), $navActive('operations'), '01', 'Vue opérationnelle', 'Tableau de bord du jour : priorités, file et activité récente.', 'ops') ?>
            <?= $iwLink(url('atak/sse/toiles'), $navActive('graphe'), '02', 'Investigations', 'Toiles relationnelles pour cartographier les liens entre éléments.', 'mesh') ?>
            <?= $iwLink(url('atak/sse/interet'), $navActive('pressee'), '03', 'Dossiers d’intérêt', 'Signalements à qualifier — hypothèses, pas encore d’identité certaine.', 'interest') ?>
            <?= $iwLink(url('atak/sse/dossiers'), $navActive('validated'), '04', 'Dossiers validés', 'Affaires structurées : preuves, notes, personnel et comptes rendus.', 'folder') ?>
            <?= $iwLink(url('atak/sse/transmissions'), $activeNav === 'transmissions' ? 'is-active' : '', '05', 'Transmissions terrain', 'Journal des envois Arma 3 : fiches, biométrie, sites et relevés.', 'collect') ?>
            <?php if ($canManage): ?>
                <?= $iwLink(url('atak/sse/maitre-jeu'), $activeNav === 'maitre-jeu' ? 'is-active' : '', '26', 'Maître du jeu', 'Pilotage des identités, listes et histoires lues par le terminal SEEK.', 'prepare') ?>
            <?php endif; ?>
            <?= $iwLink(url('atak/sse/fiches'), $activeNav === 'fiches' ? 'is-active' : '', '27', 'Fiches de renseignement', 'Notes libres remontées du terrain : texte, lieu, thèmes et pièces jointes.', 'note') ?>

            <p class="iw-nav-section">Objets</p>
            <?= $iwLink(url('atak/sse/identites'), $navActive('identites'), '06', 'Identités', 'Fiches personnes et indices biométriques.', 'id') ?>
            <?= $iwLink(url('atak/sse/objets/organisations'), '', '07', 'Organisations', 'Groupes, cellules et structures affiliées.', 'org') ?>
            <?= $iwLink(url('atak/sse/sites'), $activeNav === 'sites' ? 'is-active' : '', '08', 'Sites', 'Lieux d’intérêt et d’exploitation.', 'site') ?>
            <?= $iwLink(url('atak/sse/objets/vehicules'), '', '09', 'Véhicules', 'Moyens mobiles rattachés aux dossiers.', 'vehicle') ?>
            <?= $iwLink(url('atak/sse/objets/materiels'), '', '10', 'Matériels', 'Équipements et objets saisis.', 'gear') ?>
            <?= $iwLink(url('atak/sse/objets/documents'), '', '11', 'Pièces documentaires', 'Documents physiques ou numérisés au dossier.', 'doc') ?>

            <p class="iw-nav-section">Analyse</p>
            <?= $iwLink(url('atak/sse/toiles'), $navActive('toiles'), '12', 'Graphe relationnel', 'Vue réseau des entités et de leurs liens.', 'graph') ?>
            <?= $iwLink(url('atak/sse/chronologie'), $activeNav === 'chronologie' ? 'is-active' : '', '13', 'Chronologie', 'Séquence temporelle des faits et événements.', 'time') ?>
            <?= $iwLink(url('atak'), '', '14', 'Carte', 'Projection géographique via ATAK.', 'map') ?>
            <?= $iwLink(url('atak/sse/croisements'), $activeNav === 'croisements' ? 'is-active' : '', '15', 'Croisements', 'Rapprochements proposés à valider humainement.', 'cross') ?>
            <?= $iwLink(url('atak/sse/rapprochements'), $activeNav === 'rapprochements' ? 'is-active' : '', '25', 'Rapprochements moteur', 'Suggestions automatiques à arbitrer.', 'engine') ?>
            <?= $iwLink(url('atak/sse/anomalies'), $activeNav === 'anomalies' ? 'is-active' : '', '16', 'Anomalies', 'Écarts et alertes à examiner.', 'alert') ?>

            <p class="iw-nav-section">Exploitation</p>
            <?= $iwLink(url('atak/sse/exploitation-numerique'), $activeNav === 'labnum' ? 'is-active' : '', '17', 'Exploitation numérique', 'Supports saisis, acquisitions et analyses.', 'lab') ?>
            <?= $iwLink(url('atak/sse/dev'), $activeNav === 'dev' ? 'is-active' : '', '23', 'Modèles de mission', 'Créer et emporter des modèles pour Arma.', 'prepare') ?>
            <?= $iwLink(url('atak/sse/collecte'), $activeNav === 'collecte' ? 'is-active' : '', '18', 'Collecte terrain', 'Demandes et retours de collecte.', 'collect') ?>
            <?= $iwLink(url('atak/sse/validation'), $activeNav === 'validation' ? 'is-active' : '', '19', 'Files de validation', 'Décisions en attente d’arbitrage.', 'queue') ?>
            <?= $iwLink(url('atak/sse/rapports'), $activeNav === 'rapports' ? 'is-active' : '', '20', 'Rapports', 'Productions et bilans du bureau.', 'report') ?>
            <?= $iwLink(url('atak/sse/documents'), $activeNav === 'documents' ? 'is-active' : '', '21', 'Rédaction', 'Notes, flash et documents officiels.', 'write') ?>
            <?= $iwLink(url('atak/sse/bibliotheque'), $activeNav === 'bibliotheque' ? 'is-active' : '', '24', 'Mentions officielles', 'Bibliothèque de formulations validées.', 'library') ?>
            <?php if ($canGrant): ?>
                <?= $iwLink(url('atak/sse/acces'), $activeNav === 'acces' ? 'is-active' : '', '22', 'Administration', 'Habilitations et codes d’accès temporaires.', 'admin') ?>
            <?php endif; ?>

            <p class="iw-nav-section">Aide</p>
            <?= $iwLink(url('atak/sse/guide'), $activeNav === 'guide' ? 'is-active' : '', '26', 'Documentation', 'Mode d’emploi du bureau SSE.', 'help') ?>

            <div class="iw-nav-foot">
                <span>Apparence</span>
                <strong>Bureau SSE</strong>
            </div>
        </aside>

        <main class="iw-main">
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
<script src="<?= $h(asset_url('assets/js/sse-context-menu.js')) ?>?v=202608072010"></script>
<script src="<?= $h(asset_url('assets/js/sse-global-search.js')) ?>?v=202608161745"></script>
<?php if (!empty($sseNeedLeaflet)): ?>
<script src="<?= $h(asset_url('assets/vendor/leaflet-1.9.4/leaflet.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($sseExtraScripts)): ?>
<?= $sseExtraScripts ?>
<?php endif; ?>
</body>
</html>
