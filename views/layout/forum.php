<?php
$title = $title ?? (config('forum.name') ?? 'Forum');
$content = $content ?? 'forum.index';
$baseUrl = url('');
$forumConfig = $forumConfig ?? config('forum') ?? [];
$forumContextMenuEnabled = !empty($forumContextMenuEnabled);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($forumConfig['subtitle'] ?? 'Athena') ?></title>
<?php
    $forumBrand = htmlspecialchars($forumConfig['subtitle'] ?? 'Athena', ENT_QUOTES, 'UTF-8');
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' — ' . $forumBrand;
    $meta_description = $meta_description ?? 'Forum de la communauté : annonces, canaux de discussion et sujets récents sur Athena.';
    require base_path('views/partials/seo_meta.php');
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/portal-nav.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/portal-nav.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/athena-header.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/athena-header.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/navbar-info-banners.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/navbar-info-banners.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/forum.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/forum.css" rel="stylesheet">
    <?php endif; ?>
<?php
    $cdnPhase = 'head';
    $cdnPreset = 'forum';
    // Forum : emoji, gif, flags (voir config/cdn_libraries.php)
    require base_path('views/partials/cdn_media_libs.php');
?>
</head>
<body class="forum-mode-day bg-slate-50 text-slate-900 min-h-screen overflow-x-hidden font-sans antialiased" style="font-family: 'Inter', sans-serif;">
    <?php require base_path('views/partials/header_portal.php'); ?>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/portal-alerts.js"></script>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/navigation.js"></script>
    <?php if (is_file(base_path('public/assets/js/athena-header.js'))): ?>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/athena-header.js"></script>
    <?php endif; ?>
    <?php require base_path('views/partials/navbar_info_banners.php'); ?>
    <?php require base_path('views/partials/alert_banners.php'); ?>
    <?php require base_path('views/partials/forum_moderation_alerts.php'); ?>
    <div class="forum-layout">
        <?php require base_path('views/partials/forum_channel_rail.php'); ?>
        <main>
            <?php
            $contentPath = str_replace('.', '/', $content);
            $innerPath = base_path('views/' . $contentPath . '.php');
            if (is_file($innerPath)) {
                require $innerPath;
            } else {
                echo '<div class="w-full px-4 sm:px-6 lg:px-8 py-12 text-neutral-400"><p>Vue non trouvée.</p></div>';
            }
            ?>
        </main>
    </div>
    <footer class="border-t border-slate-200 py-6 mt-12 bg-slate-50">
        <div class="w-full px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-center gap-3 text-center text-[9px] font-black uppercase tracking-[0.3em] text-slate-500">
            <span><?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?> — <?= htmlspecialchars($forumConfig['subtitle'] ?? 'Athena') ?></span>
            <span class="hidden sm:inline text-slate-300 normal-case tracking-normal font-normal text-xs" aria-hidden="true">|</span>
            <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 normal-case tracking-normal font-semibold text-[11px] max-w-full">
                <?php
                $legal_link_class = 'text-slate-600 hover:text-emerald-700';
                require base_path('views/partials/legal_site_links.php');
                ?>
            </span>
        </div>
    </footer>
    <script src="<?= $baseUrl ?>/assets/js/forum/forum-app.js" defer></script>
    <?php if ($forumContextMenuEnabled): ?>
    <script src="<?= htmlspecialchars($baseUrl) ?>/assets/js/forum/forum_category_context.js" defer></script>
    <?php endif; ?>
    <?php require base_path('views/partials/portal_help_modal.php'); ?>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
<?php
    $cdnPhase = 'body';
    $cdnPreset = 'forum';
    require base_path('views/partials/cdn_media_libs.php');
?>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
</body>
</html>
