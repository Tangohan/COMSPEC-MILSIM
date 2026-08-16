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
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_portal.css')) ?>?v=202608160510">
    <link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse_workspace.css')) ?>?v=202608071940">
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

        <form class="iw-search" method="get" action="<?= $h(url('atak/sse/recherche')) ?>" role="search">
            <label class="sr-only" for="iw-q">Recherche globale</label>
            <input id="iw-q" name="q" type="search" placeholder="Rechercher identités, sites, matériels, véhicules, documents, événements…"
                   value="<?= $h((string) ($_GET['q'] ?? '')) ?>" autocomplete="off">
            <button type="submit" aria-label="Lancer la recherche">⌕</button>
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

            <p class="iw-nav-section">Pilotage</p>
            <a href="<?= $h(url('atak/sse/operations')) ?>" class="<?= $h($navActive('operations')) ?>"><b>01</b><span>Vue opérationnelle</span></a>
            <a href="<?= $h(url('atak/sse/toiles')) ?>" class="<?= $h($navActive('graphe')) ?>"><b>02</b><span>Investigations</span></a>
            <a href="<?= $h(url('atak/sse/interet')) ?>" class="<?= $h($navActive('pressee')) ?>"><b>03</b><span>Dossiers d’intérêt</span></a>
            <a href="<?= $h(url('atak/sse/dossiers')) ?>" class="<?= $h($navActive('validated')) ?>"><b>04</b><span>Dossiers validés</span></a>

            <p class="iw-nav-section">Objets</p>
            <a href="<?= $h(url('atak/sse/identites')) ?>" class="<?= $h($navActive('identites')) ?>"><b>05</b><span>Identités</span></a>
            <a href="<?= $h(url('atak/sse/objets/organisations')) ?>" class=""><b>06</b><span>Organisations</span></a>
            <a href="<?= $h(url('atak/sse/sites')) ?>" class="<?= $activeNav === 'sites' ? 'is-active' : '' ?>"><b>07</b><span>Sites</span></a>
            <a href="<?= $h(url('atak/sse/objets/vehicules')) ?>" class=""><b>08</b><span>Véhicules</span></a>
            <a href="<?= $h(url('atak/sse/objets/materiels')) ?>" class=""><b>09</b><span>Matériels</span></a>
            <a href="<?= $h(url('atak/sse/objets/documents')) ?>" class=""><b>10</b><span>Pièces documentaires</span></a>

            <p class="iw-nav-section">Analyse</p>
            <a href="<?= $h(url('atak/sse/toiles')) ?>" class="<?= $h($navActive('toiles')) ?>"><b>11</b><span>Graphe relationnel</span></a>
            <a href="<?= $h(url('atak/sse/chronologie')) ?>" class="<?= $activeNav === 'chronologie' ? 'is-active' : '' ?>"><b>12</b><span>Chronologie</span></a>
            <a href="<?= $h(url('atak')) ?>" class=""><b>13</b><span>Carte</span></a>
            <a href="<?= $h(url('atak/sse/croisements')) ?>" class="<?= $activeNav === 'croisements' ? 'is-active' : '' ?>"><b>14</b><span>Croisements</span></a>
            <a href="<?= $h(url('atak/sse/anomalies')) ?>" class="<?= $activeNav === 'anomalies' ? 'is-active' : '' ?>"><b>15</b><span>Anomalies</span></a>

            <p class="iw-nav-section">Exploitation</p>
            <a href="<?= $h(url('atak/sse/exploitation-numerique')) ?>" class="<?= $activeNav === 'labnum' ? 'is-active' : '' ?>"><b>16</b><span>Exploitation numérique</span></a>
            <a href="<?= $h(url('atak/sse/dev')) ?>" class="<?= $activeNav === 'dev' ? 'is-active' : '' ?>"><b>23</b><span>Atelier de préparation</span></a>
            <a href="<?= $h(url('atak/sse/collecte')) ?>" class="<?= $activeNav === 'collecte' ? 'is-active' : '' ?>"><b>17</b><span>Collecte terrain</span></a>
            <a href="<?= $h(url('atak/sse/validation')) ?>" class="<?= $activeNav === 'validation' ? 'is-active' : '' ?>"><b>18</b><span>Files de validation</span></a>
            <a href="<?= $h(url('atak/sse/rapports')) ?>" class="<?= $activeNav === 'rapports' ? 'is-active' : '' ?>"><b>19</b><span>Rapports</span></a>
            <a href="<?= $h(url('atak/sse/documents')) ?>" class="<?= $activeNav === 'documents' ? 'is-active' : '' ?>"><b>20</b><span>Rédaction</span></a>
            <?php if ($canGrant): ?>
                <a href="<?= $h(url('atak/sse/acces')) ?>" class="<?= $activeNav === 'acces' ? 'is-active' : '' ?>"><b>21</b><span>Administration</span></a>
            <?php endif; ?>

            <p class="iw-nav-section">Aide</p>
            <a href="<?= $h(url('atak/sse/guide')) ?>" class="<?= $activeNav === 'guide' ? 'is-active' : '' ?>"><b>22</b><span>Documentation</span></a>

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
    { label: 'Vue opérationnelle', href: <?= json_encode(url('atak/sse/operations'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?> },
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
<?php if (!empty($sseNeedLeaflet)): ?>
<script src="<?= $h(asset_url('assets/vendor/leaflet-1.9.4/leaflet.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($sseExtraScripts)): ?>
<?= $sseExtraScripts ?>
<?php endif; ?>
</body>
</html>
