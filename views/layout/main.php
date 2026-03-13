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
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'system-ui', 'sans-serif'],
              serif: ['"Source Serif 4"', 'Georgia', 'serif'],
            },
            letterSpacing: {
              architect: '0.3em',
              blueprint: '0.5em',
            },
          },
        },
      };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400;1,8..60,600&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>
    <header class="sticky top-0 z-[100] w-full bg-slate-50/90 backdrop-blur-md border-b border-slate-900/[0.03]">
        <div class="max-w-7xl mx-auto px-8 h-16 flex items-center justify-between">

            <div class="flex items-center gap-6">
                <a href="<?= $baseUrl ?>/" class="group flex flex-col items-start leading-none">
                    <span class="text-[11px] font-black tracking-[0.6em] text-slate-900 group-hover:text-emerald-600 transition-colors uppercase">
                        Athena
                    </span>
                    <span class="text-[6px] font-bold text-slate-400 tracking-[0.4em] uppercase mt-1">
                        Système de Commandement
                    </span>
                </a>
                <div class="hidden md:block h-4 w-[1px] bg-slate-200"></div>
                <div class="hidden md:flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Opérationnel</span>
                </div>
            </div>

            <nav class="hidden xl:flex items-center gap-8">
                <a href="<?= $baseUrl ?>/" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">Accueil</a>
                <a href="<?= $baseUrl ?>/dashboard" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">Dashboard</a>
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/forum" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">Briefing</a>
                <a href="<?= $baseUrl ?>/personnel/me" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">Fiche</a>
                <a href="<?= $baseUrl ?>/orbat" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">ORBAT</a>
                <a href="<?= $baseUrl ?>/atak" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">ATAK</a>
                <?php if (\App\Core\Gate::getInstance()->allows('admin.access')): ?>
                <a href="<?= $baseUrl ?>/admin" class="flex items-center gap-2 px-3 py-1 bg-slate-900 text-white text-[9px] font-black uppercase tracking-[0.2em] rounded-3xl hover:bg-emerald-600 transition-all shadow-sm hover:shadow-2xl hover:-translate-y-0.5">
                    <span class="w-1 h-1 bg-white rounded-full"></span> Admin
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="flex items-center gap-6">
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/account" class="group flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none">Compte</p>
                        <p class="text-[10px] font-bold text-slate-900 uppercase">Paramètres</p>
                    </div>
                    <div class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center bg-white group-hover:border-slate-900 transition-colors">
                        <svg class="w-3.5 h-3.5 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </a>

                <div class="h-4 w-[1px] bg-slate-200"></div>

                <form method="post" action="<?= $baseUrl ?>/logout" class="inline">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="group">
                        <svg class="w-5 h-5 text-slate-400 group-hover:text-rose-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
                <?php else: ?>
                <a href="<?= $baseUrl ?>/login" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 transition-colors italic">Connexion</a>
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
