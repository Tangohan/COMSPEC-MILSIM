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
    /** Fichier compilé prioritaire : évite le CDN en prod (avertissement console + perf). Sinon CDN pour le dev sans build. */
    $tailwindBuilt = is_file(base_path('public/assets/css/tailwind.css'));
    if ($tailwindBuilt): ?>
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
    <?php if (is_file(base_path('public/assets/css/portal-nav.css'))): ?>
    <link href="<?= $baseUrl ?>/assets/css/portal-nav.css" rel="stylesheet">
    <?php endif; ?>
    <?php
    $alpineLocal = base_path('public/assets/js/alpine.min.js');
    $alpineSrc = is_file($alpineLocal) ? $baseUrl . '/assets/js/alpine.min.js' : 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js';
?>
    <script defer src="<?= htmlspecialchars($alpineSrc) ?>"></script>
</head>
<body class="layout-light bg-slate-50 text-slate-900 font-sans antialiased min-h-screen">
    <div class="grain" aria-hidden="true"></div>
    <?php require base_path('views/partials/header_portal.php'); ?>
    <script defer src="<?= htmlspecialchars($baseUrl) ?>/assets/js/navigation.js"></script>
    <?php require base_path('views/partials/alert_banners.php'); ?>
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
