<?php
declare(strict_types=1);

$title = $title ?? 'Politique et conditions';
$content = $content ?? 'legal.site';
$baseUrl = url('');
$brand = email_brand_name();
$legalActivePage = $legalActivePage ?? 'site';
$legalActiveSection = $legalActiveSection ?? '';
$hideHaloLoader = !empty($hideHaloLoader);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    require base_path('views/partials/seo_meta.php');
?>
    <link rel="manifest" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/manifest.webmanifest">
    <meta name="theme-color" content="#0a1218">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/icons/athena-192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<?php
    $tailwindBaseUrl = $baseUrl;
    require base_path('views/partials/tailwind_cdn_or_build.php');
?>
    <link href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/css/legal-docs.css" rel="stylesheet">
    <?php if (!$hideHaloLoader): ?>
    <link href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/css/halo-loader.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="legal-body">
<?php if (!$hideHaloLoader): ?>
    <div id="halo-loader" class="halo-loader" role="status" aria-live="polite" aria-busy="true" aria-label="Chargement">
        <div class="halo-loader__reticle" aria-hidden="true">
            <div class="halo-loader__ring halo-loader__ring--outer"></div>
            <div class="halo-loader__ring halo-loader__ring--mid"></div>
            <div class="halo-loader__ticks" data-halo-ticks></div>
            <svg class="halo-loader__progress-ring" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="track" cx="60" cy="60" r="50"></circle>
                <circle class="value" data-halo-ring-value cx="60" cy="60" r="50"></circle>
            </svg>
            <div class="halo-loader__core">
                <span class="halo-loader__pct"><span data-halo-pct>0</span>%</span>
            </div>
        </div>
        <div class="halo-loader__brand">
            <p class="halo-loader__brand-name"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="halo-loader__brand-status" data-halo-status>Initialisation</p>
        </div>
        <div class="halo-loader__bar-wrap">
            <div class="halo-loader__bar-meta">
                <span>Système</span>
                <span>Documents juridiques</span>
            </div>
            <div class="halo-loader__track">
                <div class="halo-loader__fill" data-halo-fill></div>
            </div>
            <p class="halo-loader__hint">Préparation de l’espace documentation…</p>
        </div>
    </div>
    <script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/halo-loader.js"></script>
<?php endif; ?>

    <div class="legal-shell">
        <div class="legal-wrap">
            <?php require base_path('views/partials/legal_nav.php'); ?>
            <main class="legal-main">
                <?php require base_path('views/' . str_replace('.', '/', (string) $content) . '.php'); ?>
            </main>
        </div>

        <footer class="legal-footer">
            <div class="legal-footer-inner">
                <nav class="legal-footer-nav" aria-label="Liens utiles">
                    <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>">Accueil</a>
                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>">Légal</a>
                    <a href="<?= htmlspecialchars(url('legal/site'), ENT_QUOTES, 'UTF-8') ?>#cgu">CGU / CGV</a>
                    <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>">Vos droits</a>
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>">Connexion</a>
                </nav>
                <p class="legal-footer-copy">© <?= date('Y') ?> <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> · Documentation juridique</p>
            </div>
        </footer>
    </div>

    <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php if (is_file(base_path('public/assets/js/cookie_consent.js'))): ?>
    <script defer src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/cookie_consent.js"></script>
    <?php endif; ?>
    <script>
    (function () {
      var hash = window.location.hash.replace(/^#/, '');
      if (!hash) return;
      var el = document.getElementById(hash);
      if (el) {
        window.setTimeout(function () {
          el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 80);
      }
      document.querySelectorAll('.legal-topic[href*="#' + hash + '"]').forEach(function (a) {
        a.classList.add('is-active');
      });
    })();
    </script>
</body>
</html>
