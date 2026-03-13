<?php
$title = $title ?? 'Athena';
$content = $content ?? 'home';
$baseUrl = url('');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">
    <header class="sticky top-0 z-50 w-full bg-slate-50/95 backdrop-blur border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
            <a href="<?= $baseUrl ?>/" class="text-sm font-black tracking-widest uppercase">Athena</a>
            <nav class="flex items-center gap-6">
                <a href="<?= $baseUrl ?>/" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Accueil</a>
                <a href="<?= $baseUrl ?>/dashboard" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Dashboard</a>
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/forum" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Salle de brief</a>
                <a href="<?= $baseUrl ?>/personnel/me" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Ma fiche</a>
                <a href="<?= $baseUrl ?>/account" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Mon compte</a>
                <a href="<?= $baseUrl ?>/orbat" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">ORBAT</a>
                <a href="<?= $baseUrl ?>/atak" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">ATAK</a>
                <?php if (function_exists('can') && can('forum.moderate')): ?>
                <a href="<?= $baseUrl ?>/forum/moderation" class="text-xs font-semibold uppercase text-rose-600 hover:text-rose-800">Terminal de Contrôle</a>
                <?php endif; ?>
                <a href="<?= $baseUrl ?>/admin" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Admin</a>
                <form method="post" action="<?= $baseUrl ?>/logout" class="inline"><?= \App\Core\Csrf::field() ?><button type="submit" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900 cursor-pointer">Déconnexion</button></form>
                <?php else: ?>
                <a href="<?= $baseUrl ?>/login" class="text-xs font-semibold uppercase text-slate-600 hover:text-slate-900">Connexion</a>
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
            echo '<div class="max-w-5xl mx-auto px-6 py-12"><p>Vue non trouvée.</p></div>';
        }
        ?>
    </main>
    <footer class="border-t border-slate-200 py-6 mt-12">
        <div class="max-w-5xl mx-auto px-6 text-center text-xs text-slate-500">
            Athena — SaaS RH tactique MILSIM Arma 3
        </div>
    </footer>
</body>
</html>
