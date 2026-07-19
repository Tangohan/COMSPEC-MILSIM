<?php
declare(strict_types=1);
$title = $title ?? 'Recrutement';
$content = $content ?? 'home';
$lmsBase = url('');
$recruitmentLmsTitle = $recruitmentLmsTitle ?? $title;
ob_start();
require base_path('views/admin/recruitment_workspace/partials/recruitment_lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900">
    <?php
    $lmsBootMessage = 'Chargement du bureau recrutement…';
    require base_path('views/training/partials/lms_page_boot_overlay.php');
    ?>
    <div class="lms-grain"></div>

    <div class="min-h-screen relative z-10">
        <div class="grid lg:grid-cols-[290px_1fr] min-h-screen">
            <?php require base_path('views/admin/recruitment_workspace/partials/recruitment_lms_sidebar.php'); ?>

            <main class="recruitment-lms-main min-w-0 p-5 md:p-8 lg:p-10 space-y-8">
                <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
                <?php
                $contentPath = str_replace('.', '/', (string) $content);
                $innerPath = base_path('views/' . $contentPath . '.php');
                if (is_file($innerPath)) {
                    require $innerPath;
                } else {
                    echo '<div class="lms-panel rounded-2xl p-8"><p class="text-slate-600">Vue non trouvée.</p></div>';
                }
                ?>
            </main>
        </div>
    </div>
    <?php
    $lmsModuleEntryAuto = 'recrutement';
    require base_path('views/partials/lms_module_entry_modal.php');
    ?>
</body>
</html>
