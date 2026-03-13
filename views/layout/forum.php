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
    <?php if (is_file(base_path('public/assets/css/forum.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/forum.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="bg-[#080809] text-white min-h-screen overflow-x-hidden font-sans antialiased" style="font-family: 'Inter', sans-serif;">
    <header class="sticky top-0 z-50 w-full bg-[#0a0a0b]/95 backdrop-blur border-b border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
            <a href="<?= $baseUrl ?>/" class="text-sm font-black tracking-widest uppercase text-white/90 hover:text-white">Athena</a>
            <nav class="flex items-center gap-4 sm:gap-6">
                <a href="<?= $baseUrl ?>/" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">Accueil</a>
                <a href="<?= $baseUrl ?>/forum" class="text-xs font-semibold uppercase text-orange-400 hover:text-orange-300"><?= htmlspecialchars($forumConfig['name'] ?? 'Salle de brief') ?></a>
                <a href="<?= $baseUrl ?>/dashboard" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">Dashboard</a>
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/personnel/me" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">Ma fiche</a>
                <a href="<?= $baseUrl ?>/account" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">Mon compte</a>
                <a href="<?= $baseUrl ?>/orbat" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">ORBAT</a>
                <a href="<?= $baseUrl ?>/atak" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">ATAK</a>
                <?php if (function_exists('can') && can('forum.moderate')): ?>
                <a href="<?= $baseUrl ?>/forum/moderation" class="text-xs font-semibold uppercase text-rose-400 hover:text-rose-300">Terminal de Contrôle</a>
                <?php endif; ?>
                <a href="<?= $baseUrl ?>/admin" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">Admin</a>
                <form method="post" action="<?= $baseUrl ?>/logout" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white cursor-pointer">Déconnexion</button></form>
                <?php else: ?>
                <a href="<?= $baseUrl ?>/login" class="text-xs font-semibold uppercase text-neutral-400 hover:text-white">Connexion</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="min-h-[80vh]">
        <?php
        $contentPath = str_replace('.', '/', $content);
        $innerPath = base_path('views/' . $contentPath . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="max-w-7xl mx-auto px-4 py-12 text-neutral-400"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </main>
    <footer class="border-t border-white/5 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-[9px] font-black uppercase tracking-[0.3em] text-neutral-800">
            <?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?> — <?= htmlspecialchars($forumConfig['subtitle'] ?? 'Athena') ?>
        </div>
    </footer>
</body>
</html>
