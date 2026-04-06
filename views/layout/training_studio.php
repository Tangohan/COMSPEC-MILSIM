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
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>

    <div id="training-studio-bootsplash" class="training-studio-bootsplash" aria-hidden="true">
        <div class="training-studio-bootsplash__shine" aria-hidden="true"></div>
        <div class="training-studio-bootsplash__inner">
            <p class="training-studio-bootsplash__brand">ATHENA</p>
            <p class="training-studio-bootsplash__sub">Studio de formations</p>
            <div class="training-studio-bootsplash__track" aria-hidden="true">
                <span class="training-studio-bootsplash__slider"></span>
            </div>
        </div>
    </div>
    <?php /* Fermeture du voile : placée ici pour s’exécuter avant le gros HTML du studio (évite de dépendre uniquement du script en fin de document). */ ?>
    <script>
    (function () {
        var el = document.getElementById('training-studio-bootsplash');
        if (!el) {
            return;
        }
        var dismissed = false;
        function dismiss() {
            if (dismissed) {
                return;
            }
            dismissed = true;
            el.setAttribute('aria-hidden', 'true');
            el.style.animation = 'none';
            el.style.pointerEvents = 'none';
            el.style.visibility = 'hidden';
            el.style.opacity = '0';
            el.classList.add('training-studio-bootsplash--exit');
            var finalized = false;
            function done() {
                if (finalized) {
                    return;
                }
                finalized = true;
                el.removeEventListener('transitionend', onEnd);
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }
            function onEnd(e) {
                if (e.target !== el) {
                    return;
                }
                var p = e.propertyName || '';
                if (p === 'transform' || p === 'opacity' || p === 'webkitTransform') {
                    done();
                }
            }
            el.addEventListener('transitionend', onEnd);
            window.setTimeout(done, 1200);
        }
        function dismissAfterPaint() {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(dismiss);
            });
        }
        function arm() {
            window.setTimeout(dismissAfterPaint, 0);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    window.setTimeout(dismissAfterPaint, 80);
                });
            } else {
                window.setTimeout(dismissAfterPaint, 80);
            }
            document.addEventListener('readystatechange', function onRs() {
                if (document.readyState !== 'loading') {
                    document.removeEventListener('readystatechange', onRs);
                    window.setTimeout(dismissAfterPaint, 40);
                }
            });
            window.addEventListener('load', function () {
                window.setTimeout(dismiss, 60);
            });
            window.setTimeout(dismiss, 6500);
        }
        arm();
    })();
    </script>

    <div class="training-studio-app training-studio-app--sidebar-open">
        <div class="training-studio-banner">
            <span class="training-studio-banner__label">ATHENA — Studio de formations</span>
            <a href="<?= htmlspecialchars($portalHomeUrl) ?>" class="training-studio-banner__portal">Retour au portail</a>
        </div>

        <div class="training-studio-app__grid">
            <div class="training-studio-sidebar-host min-w-0 order-1 lg:order-1 lg:col-start-1 lg:row-start-1">
                <?php require base_path('views/partials/training_studio_sidebar.php'); ?>
            </div>
            <div class="training-studio-main min-w-0 order-2 lg:order-2 lg:col-start-2 lg:row-start-1">
                <a href="#studio-main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-[3.75rem] focus:z-[60] focus:bg-white focus:px-3 focus:py-2 focus:rounded-lg focus:shadow">Aller au contenu</a>
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
        </div>
    </div>

    <?php require base_path('views/partials/cookie_banner.php'); ?>
</body>
</html>
