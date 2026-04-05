<?php
$title = $title ?? (config('forum.name') ?? 'Forum');
$content = $content ?? 'forum.index';
$baseUrl = url('');
$forumConfig = $forumConfig ?? config('forum') ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($forumConfig['subtitle'] ?? 'Athena') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/portal-nav.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/portal-nav.css" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/forum.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/forum.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="forum-mode-day bg-slate-50 text-slate-900 min-h-screen overflow-x-hidden font-sans antialiased" style="font-family: 'Inter', sans-serif;">
    <?php require base_path('views/partials/header_portal.php'); ?>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/navigation.js"></script>
    <?php require base_path('views/partials/alert_banners.php'); ?>
    <main class="min-h-[80vh] bg-[#f8fafc]">
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
    <footer class="border-t border-slate-200 py-6 mt-12 bg-slate-50">
        <div class="w-full px-4 sm:px-6 lg:px-8 text-center text-[9px] font-black uppercase tracking-[0.3em] text-slate-500">
            <?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?> — <?= htmlspecialchars($forumConfig['subtitle'] ?? 'Athena') ?>
        </div>
    </footer>
    <script src="<?= $baseUrl ?>/assets/js/forum/forum-app.js" defer></script>
</body>
</html>
