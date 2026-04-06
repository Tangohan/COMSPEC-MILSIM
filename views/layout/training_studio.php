<?php
$title = $title ?? 'Studio LMS';
$content = $content ?? 'home';
$baseUrl = url('');
$trainingStudioMode = $trainingStudioMode ?? 'index';
$trainingStudioCourseCount = $trainingStudioCourseCount ?? 0;
$trainingStudioCourse = $trainingStudioCourse ?? null;
$portalHomeUrl = url('dashboard');
$trainingStudioShowIntro = $trainingStudioShowIntro ?? true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Studio LMS</title>
<?php
    $tailwindBaseUrl = $baseUrl;
    require base_path('views/partials/tailwind_cdn_or_build.php');
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400;1,8..60,600&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/styles.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/styles.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= $baseUrl ?>/assets/css/training_studio_shell.css" rel="stylesheet">
    <?php
    $alpineLocal = base_path('public/assets/js/alpine.min.js');
    $alpineSrc = is_file($alpineLocal) ? $baseUrl . '/assets/js/alpine.min.js' : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
?>
    <script defer src="<?= htmlspecialchars($alpineSrc) ?>"></script>
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>

    <div class="training-studio-app training-studio-app--sidebar-open"
         x-data="{ navOpen: true }"
         :class="{ 'training-studio-app--sidebar-open': navOpen }"
         @keydown.escape.window="navOpen = false">

        <header class="training-studio-topbar">
            <div class="training-studio-topbar__inner">
                <button type="button"
                        class="training-studio-topbar__toggle"
                        @click="navOpen = !navOpen"
                        aria-controls="training-studio-sidebar"
                        x-bind:aria-expanded="navOpen"
                        title="Afficher ou masquer le menu Studio">
                    <span aria-hidden="true">☰</span>
                    <span class="sr-only">Menu Studio</span>
                </button>
                <div class="training-studio-topbar__titles">
                    <span class="training-studio-topbar__kicker">Athena</span>
                    <span class="training-studio-topbar__title"><?= htmlspecialchars($title) ?></span>
                </div>
                <a href="<?= htmlspecialchars($portalHomeUrl) ?>" class="training-studio-topbar__portal">
                    Retour au portail
                </a>
            </div>
        </header>

        <button type="button"
                class="training-studio-drawer-toggle training-studio-drawer-toggle--fab"
                @click="navOpen = true"
                x-show="!navOpen"
                x-cloak
                aria-controls="training-studio-sidebar"
                title="Ouvrir le menu">☰</button>

        <div class="training-studio-backdrop lg:hidden"
             x-show="navOpen"
             x-transition.opacity
             x-cloak
             @click="navOpen = false"></div>

        <div class="training-studio-app__grid">
            <div class="training-studio-main min-w-0 order-1 lg:order-2 lg:col-start-2 lg:row-start-1">
                <a href="#studio-main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-[4.5rem] focus:z-[60] focus:bg-white focus:px-3 focus:py-2 focus:rounded-lg focus:shadow">Aller au contenu</a>
                <div id="studio-main-content" class="training-studio-page-inner">
        <?php if (!empty($trainingStudioShowIntro)) {
            require base_path('views/partials/training_studio_intro.php');
        } ?>
        <?php
        $contentPath = str_replace('.', '/', $content);
        $innerPath = base_path('views/' . $contentPath . '.php');
        if (is_file($innerPath)) {
            require $innerPath;
        } else {
            echo '<div class="training-studio-panel p-8"><p>Vue non trouvée.</p></div>';
        }
        ?>
                </div>
            </div>
            <div class="training-studio-sidebar-host order-2 lg:order-1 lg:col-start-1 lg:row-start-1 min-w-0">
                <?php require base_path('views/partials/training_studio_sidebar.php'); ?>
            </div>
        </div>
    </div>

    <style>[x-cloak]{display:none !important;}</style>
    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
