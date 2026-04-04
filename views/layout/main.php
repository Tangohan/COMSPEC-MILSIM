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
<?php
    $isProduction = (function_exists('env') ? env('APP_ENV', '') : (getenv('APP_ENV') ?: '')) === 'production';
    $tailwindBuilt = is_file(base_path('public/assets/css/tailwind.css'));
    if ($isProduction && $tailwindBuilt): ?>
    <link href="<?= $baseUrl ?>/assets/css/tailwind.css" rel="stylesheet">
<?php else: ?>
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
<?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400;1,8..60,600&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <?php
    $alpineLocal = base_path('public/assets/js/alpine.min.js');
    $alpineSrc = is_file($alpineLocal) ? $baseUrl . '/assets/js/alpine.min.js' : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
?>
    <script defer src="<?= htmlspecialchars($alpineSrc) ?>"></script>
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>
    <header class="sticky top-0 z-[100] w-full bg-slate-50/80 backdrop-blur-xl border-b border-slate-200/60 transition-all duration-500">
        <div class="max-w-[1800px] mx-auto px-6 h-14 flex items-center justify-between">

            <div class="flex items-center gap-8">
                <a href="<?= $baseUrl ?>/" class="group flex items-center gap-4 focus:outline-none">
                    <div class="flex flex-col items-start leading-none">
                        <span class="text-[12px] font-black tracking-[0.6em] text-slate-900 group-hover:text-emerald-600 transition-colors uppercase italic">
                            Athena
                        </span>
                        <span class="text-[7px] font-bold text-slate-400 tracking-[0.3em] uppercase mt-1.5 flex items-center gap-1.5">
                            <span class="w-1 h-1 bg-emerald-500 rounded-full animate-pulse"></span>
                            Système opérationnel
                        </span>
                    </div>
                </a>

                <div class="hidden lg:block h-6 w-px bg-slate-200/80"></div>
            </div>

            <nav class="hidden xl:flex items-center gap-1">
                <a href="<?= $baseUrl ?>/" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Accueil</a>
                <a href="<?= $baseUrl ?>/dashboard" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Dashboard</a>
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/hub" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Hub</a>
                <a href="<?= $baseUrl ?>/forum" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Briefing</a>
                <a href="<?= $baseUrl ?>/personnel/me" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Fiche</a>
                <a href="<?= $baseUrl ?>/orbat" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">ORBAT</a>
                <a href="<?= $baseUrl ?>/atak" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">ATAK</a>
                <?php if (\App\Core\Gate::getInstance()->allows('courrier.view') || \App\Core\Gate::getInstance()->allows('admin.access')): ?>
                <a href="<?= $baseUrl ?>/courrier" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Bureau Courrier</a>
                <?php endif; ?>
                <?php if (\App\Core\Gate::getInstance()->allows('documents.view')): ?>
                <a href="<?= $baseUrl ?>/documents" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Documents</a>
                <?php endif; ?>
                <?php if (\App\Core\Gate::getInstance()->allows('documents.upload')): ?>
                <a href="<?= $baseUrl ?>/documents/gestion" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Gestion documents</a>
                <?php endif; ?>
                <?php if (\App\Core\Gate::getInstance()->allows('training.manage') || \App\Core\Gate::getInstance()->allows('training.assign')): ?>
                <a href="<?= $baseUrl ?>/admin/training" class="px-4 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 hover:bg-slate-200/50 rounded-full transition-all duration-300 italic">Formations</a>
                <?php endif; ?>
                <?php if (\App\Core\Gate::getInstance()->allows('admin.system') || \App\Core\Gate::getInstance()->allows('admin.organization') || \App\Core\Gate::getInstance()->allows('admin.access')): ?>
                <a href="<?= $baseUrl ?>/admin" class="ml-4 flex items-center gap-2 px-4 py-1.5 bg-slate-900 text-white text-[9px] font-black uppercase tracking-[0.2em] rounded-full hover:bg-emerald-600 transition-all shadow-lg shadow-slate-900/10 hover:-translate-y-0.5 group">
                    <span class="w-1 h-1 bg-emerald-400 rounded-full group-hover:animate-ping"></span>
                    Administration
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="flex items-center gap-4">
                <?php if (\App\Core\Session::get('user_id')): ?>
                <a href="<?= $baseUrl ?>/account" class="group flex items-center gap-4 p-1 rounded-full hover:bg-white transition-all border border-transparent hover:border-slate-200">
                    <div class="text-right hidden sm:block">
                        <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Compte</p>
                        <p class="text-[9px] font-black text-slate-900 uppercase tracking-tighter italic">Paramètres</p>
                    </div>
                    <div class="w-8 h-8 rounded-full border-2 border-slate-100 flex items-center justify-center bg-slate-50 overflow-hidden group-hover:border-emerald-500/30 transition-colors">
                        <svg class="w-4 h-4 text-slate-600 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                </a>

                <div class="h-4 w-px bg-slate-200"></div>

                <form method="post" action="<?= $baseUrl ?>/logout" class="flex items-center">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="p-2 text-slate-300 hover:text-rose-500 transition-all hover:rotate-90 duration-500 group">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </form>
                <?php else: ?>
                <a href="<?= $baseUrl ?>/login" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-slate-900 italic">Connexion</a>
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
