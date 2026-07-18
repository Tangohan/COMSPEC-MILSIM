<?php
declare(strict_types=1);
/**
 * Coque catalogue LMS (/formations) : même aside sombre, sans en-tête portail Athena.
 * Utilisée pour le pilotage communautaire /formation (vue d’ensemble et sous-pages).
 */
$content = $content ?? 'admin.training.dashboard_body';
$title = $title ?? 'Pilotage des formations';
$lmsBase = url('');
$lmsTitle = $title;
$totalModules = (int) ($totalModules ?? 0);
$trainingAdminNav = (string) ($trainingAdminNav ?? 'dashboard');
$activeNav = $activeNav ?? '';
$lmsSidebarContext = 'staff';
$lmsSidebarShowPilotageLinks = true;
$lmsThemeVars = '';
$lmsExtraHead = '<link rel="stylesheet" href="' . htmlspecialchars(url('assets/css/training_admin_command.css')) . '">';
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 overflow-x-hidden">
    <?php
    $lmsBootMessage = 'Chargement du pilotage…';
    require base_path('views/training/partials/lms_page_boot_overlay.php');
    ?>
    <div class="lms-grain"></div>

    <div class="min-h-screen relative z-10">
        <div class="grid lg:grid-cols-[290px_1fr] min-h-screen">
            <?php
            require base_path('views/training/partials/lms_command_sidebar.php');
            ?>

            <main class="p-5 md:p-8 lg:p-10 space-y-8">
                <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
                <div class="lms-infobanner" role="note">
                    <span class="lms-infobanner__icon" aria-hidden="true">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <p><strong>Pilotage.</strong> Espace réservé à l’encadrement formation de la communauté. Utilisez le menu sombre pour naviguer, ou les raccourcis groupés ci-dessous. Le <a href="<?= htmlspecialchars($lmsBase) ?>/formations" class="text-emerald-700 font-semibold hover:underline">catalogue public</a> reste accessible aux membres.</p>
                </div>
                <?php
                $contentPath = str_replace('.', '/', (string) $content);
                $innerPath = base_path('views/' . $contentPath . '.php');
                if (is_file($innerPath)) {
                    require $innerPath;
                } else {
                    echo '<p class="text-slate-600">Contenu introuvable.</p>';
                }
                ?>
            </main>
        </div>
    </div>
    <?php require base_path('views/partials/community_report_modal.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
