<?php
declare(strict_types=1);
$title = $title ?? 'Bureau effectifs';
$content = $content ?? 'home';
$lmsBase = url('');
$effectifsLmsTitle = $effectifsLmsTitle ?? $title;
$viewerName = (string) ($viewerName ?? '');
$rosterCounts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$effectifsNav = (string) ($effectifsNav ?? 'roster');
ob_start();
require base_path('views/admin/effectifs_workspace/partials/effectifs_lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<?= $headHtml ?>
</head>
<body class="eff-body antialiased">
<div class="eff-gridlines" aria-hidden="true"></div>
<?php
$lmsBootMessage = 'Chargement du bureau effectifs…';
require base_path('views/training/partials/lms_page_boot_overlay.php');
?>

<?php require base_path('views/admin/effectifs_workspace/partials/effectifs_lms_rail.php'); ?>

<div class="eff-shell">
    <header class="eff-topnav">
        <div class="eff-topnav-brand">
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="eff-brand-link">Athena</a>
            <span class="eff-topnav-sep" aria-hidden="true">/</span>
            <span class="eff-topnav-title">Bureau effectifs</span>
        </div>
        <div class="eff-topnav-actions">
            <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="eff-topnav-ghost">Back-office</a>
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="eff-topnav-cta">Tableau de bord</a>
        </div>
    </header>

    <div class="eff-mobilebar" aria-label="Navigation mobile effectifs">
        <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Tableur</a>
        <a href="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>">Rôles</a>
        <a href="<?= htmlspecialchars(effectifs_workspace_url('droits'), ENT_QUOTES, 'UTF-8') ?>">Droits</a>
        <a href="<?= htmlspecialchars(effectifs_workspace_url('fonctions'), ENT_QUOTES, 'UTF-8') ?>">Fonctions</a>
        <a href="<?= htmlspecialchars(effectifs_workspace_url('affectations'), ENT_QUOTES, 'UTF-8') ?>">Affectations</a>
    </div>

    <main class="eff-main">
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php
        $contentPath = str_replace('.', '/', (string) $content);
        $innerPath = base_path('views/' . $contentPath . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="eff-panel"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </main>
</div>
</body>
</html>
