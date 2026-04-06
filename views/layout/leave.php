<?php
$title = $title ?? 'Lien externe';
$content = $content ?? 'forum.leave';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        @keyframes leave-warn-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.45); }
            50% { box-shadow: 0 0 0 12px rgba(248, 113, 113, 0); }
        }
        .leave-warn-pulse {
            animation: leave-warn-pulse 2.2s ease-in-out infinite;
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden font-sans antialiased bg-[#0a0a0c] text-slate-100" style="font-family: 'Inter', sans-serif;">
    <main class="min-h-screen flex flex-col">
        <?php
        $contentPath = str_replace('.', '/', $content);
        $innerPath = base_path('views/' . $contentPath . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="w-full px-4 py-12 text-neutral-400"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </main>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
