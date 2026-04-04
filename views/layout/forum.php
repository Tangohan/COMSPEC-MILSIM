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
    <style>.nav-dropdown.open .nav-dropdown-panel{display:block;}</style>
</head>
<body class="bg-[#080809] text-white min-h-screen overflow-x-hidden font-sans antialiased" style="font-family: 'Inter', sans-serif;">
    <header class="sticky top-0 z-50 w-full bg-[#0a0a0b]/95 backdrop-blur border-b border-white/5">
        <div class="w-full px-6 lg:px-10 h-14 flex items-center justify-between">
            <!-- Brand -->
            <a href="<?= $baseUrl ?>/" class="text-sm font-black tracking-[0.25em] uppercase text-white">
                Athena
            </a>

            <!-- Navigation -->
            <nav class="flex items-center gap-8 text-xs font-semibold uppercase">
                <!-- Principal -->
                <div class="flex items-center gap-6">
                    <a href="<?= $baseUrl ?>/" class="text-neutral-400 hover:text-white transition-colors">Accueil</a>
                    <a href="<?= $baseUrl ?>/dashboard" class="text-neutral-400 hover:text-white transition-colors">Dashboard</a>
                    <a href="<?= $baseUrl ?>/forum" class="text-orange-400 hover:text-orange-300 transition-colors"><?= htmlspecialchars($forumConfig['name'] ?? 'Salle de brief') ?></a>
                </div>

                <?php if (\App\Core\Session::get('user_id')): ?>
                <!-- Opérations -->
                <div class="relative group nav-dropdown">
                    <button type="button" class="nav-dropdown-trigger text-neutral-400 hover:text-white flex items-center gap-1 transition-colors" aria-expanded="false" aria-haspopup="true">
                        Opérations
                        <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-panel absolute hidden top-full mt-3 w-44 bg-[#0d0d0e] border border-white/5 rounded-lg shadow-xl py-1 z-50 group-[.open]:block">
                        <a href="<?= $baseUrl ?>/orbat" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">ORBAT</a>
                        <a href="<?= $baseUrl ?>/atak" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">ATAK</a>
                    </div>
                </div>

                <!-- Ressources -->
                <div class="relative group nav-dropdown">
                    <button type="button" class="nav-dropdown-trigger text-neutral-400 hover:text-white flex items-center gap-1 transition-colors" aria-expanded="false" aria-haspopup="true">
                        Ressources
                        <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-panel absolute hidden top-full mt-3 w-48 bg-[#0d0d0e] border border-white/5 rounded-lg shadow-xl py-1 z-50 group-[.open]:block">
                        <a href="<?= $baseUrl ?>/formations" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">Formations</a>
                        <?php if (\App\Core\Gate::getInstance()->allows('documents.view')): ?>
                        <a href="<?= $baseUrl ?>/documents" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">Documents</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Personnel -->
                <div class="relative group nav-dropdown">
                    <button type="button" class="nav-dropdown-trigger text-neutral-400 hover:text-white flex items-center gap-1 transition-colors" aria-expanded="false" aria-haspopup="true">
                        Personnel
                        <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-panel absolute hidden top-full mt-3 w-44 bg-[#0d0d0e] border border-white/5 rounded-lg shadow-xl py-1 z-50 group-[.open]:block">
                        <a href="<?= $baseUrl ?>/personnel/me" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">Ma fiche</a>
                    </div>
                </div>
                <?php endif; ?>
            </nav>

            <!-- Compte / Admin -->
            <div class="flex items-center gap-6 text-xs font-semibold uppercase">
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/account" class="text-neutral-400 hover:text-white transition-colors">
                    Paramètres
                </a>

                <?php
                $canModerate = function_exists('can') && can('forum.moderate');
                $canAdmin = \App\Core\Gate::getInstance()->allows('admin.access') || \App\Core\Gate::getInstance()->allows('admin.system') || \App\Core\Gate::getInstance()->allows('admin.organization');
                if ($canModerate || $canAdmin):
                ?>
                <div class="relative group nav-dropdown">
                    <button type="button" class="nav-dropdown-trigger text-rose-400 hover:text-rose-300 flex items-center gap-1 transition-colors" aria-expanded="false" aria-haspopup="true">
                        Admin
                        <svg class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="nav-dropdown-panel absolute right-0 hidden top-full mt-3 w-56 bg-[#0d0d0e] border border-white/5 rounded-lg shadow-xl py-1 z-50 group-[.open]:block">
                        <?php if ($canModerate): ?>
                        <a href="<?= $baseUrl ?>/forum/moderation" class="block px-4 py-2 hover:bg-white/5 text-rose-400 transition-colors">Terminal de contrôle</a>
                        <?php endif; ?>
                        <?php if ($canAdmin): ?>
                        <a href="<?= $baseUrl ?>/admin/forum-config" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">Configuration forum</a>
                        <a href="<?= $baseUrl ?>/admin" class="block px-4 py-2 hover:bg-white/5 text-neutral-300 transition-colors">Administration</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form method="post" action="<?= $baseUrl ?>/logout" class="inline">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="text-neutral-400 hover:text-white transition-colors cursor-pointer">
                        Déconnexion
                    </button>
                </form>
                <?php else: ?>
                <a href="<?= $baseUrl ?>/login" class="text-neutral-400 hover:text-white transition-colors">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main class="min-h-[80vh]">
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
    <footer class="border-t border-white/5 py-6 mt-12">
        <div class="w-full px-4 sm:px-6 lg:px-8 text-center text-[9px] font-black uppercase tracking-[0.3em] text-neutral-800">
            <?= htmlspecialchars($forumConfig['name'] ?? 'Forum') ?> — <?= htmlspecialchars($forumConfig['subtitle'] ?? 'Athena') ?>
        </div>
    </footer>
    <script>
    (function() {
        function closeAllDropdowns() {
            document.querySelectorAll('.nav-dropdown.open').forEach(function(el) { el.classList.remove('open'); });
            document.querySelectorAll('.nav-dropdown-trigger[aria-expanded="true"]').forEach(function(el) { el.setAttribute('aria-expanded', 'false'); });
        }
        document.querySelectorAll('.nav-dropdown-trigger').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var group = btn.closest('.nav-dropdown');
                var isOpen = group.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) {
                    group.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });
        document.querySelectorAll('.nav-dropdown-panel a').forEach(function(link) {
            link.addEventListener('click', function() { closeAllDropdowns(); });
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-dropdown')) closeAllDropdowns();
        });
    })();
    </script>
</body>
</html>
