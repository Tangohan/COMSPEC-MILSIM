<?php
declare(strict_types=1);

$title = $title ?? __('legal.hub_title');
$content = $content ?? 'legal.site';
$baseUrl = url('');
$brand = email_brand_name();
$legalActivePage = $legalActivePage ?? 'site';
$legalActiveSection = $legalActiveSection ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>">
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
</head>
<body class="legal-body">

    <div class="legal-shell">
        <div class="legal-wrap">
            <?php require base_path('views/partials/legal_nav.php'); ?>
            <main class="legal-main">
                <?php require base_path('views/' . str_replace('.', '/', (string) $content) . '.php'); ?>
            </main>
        </div>

        <footer class="legal-footer">
            <div class="legal-footer-inner">
                <nav class="legal-footer-nav" aria-label="<?= htmlspecialchars(__('legal.footer_aria'), ENT_QUOTES, 'UTF-8') ?>">
                    <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.home'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($legalActivePage === 'site' ? '#' : url('legal/site'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.legal'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars($legalActivePage === 'site' ? '#cgu' : url('legal/site') . '#cgu', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.cgu_cgv'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.your_rights'), ENT_QUOTES, 'UTF-8') ?></a>
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('common.login'), ENT_QUOTES, 'UTF-8') ?></a>
                </nav>
                <div class="legal-footer-copy" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem">
                    <p style="margin:0">© <?= date('Y') ?> <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(__('legal.footer_copy'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php $localeSwitcherVariant = 'light'; require base_path('views/partials/language_switcher.php'); ?>
                </div>
            </div>
        </footer>
    </div>

    <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php require base_path('views/partials/demo_nda_session_widget.php'); ?>
    <?php if (is_file(base_path('public/assets/js/cookie_consent.js'))): ?>
    <script defer src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/cookie_consent.js"></script>
    <?php endif; ?>
    <script>
    (function () {
      function syncFromHash() {
        var hash = window.location.hash.replace(/^#/, '');
        document.querySelectorAll('.legal-topic').forEach(function (a) {
          var href = a.getAttribute('href') || '';
          a.classList.toggle('is-active', hash !== '' && href.indexOf('#' + hash) !== -1);
        });
        if (!hash) return;
        var el = document.getElementById(hash);
        if (el) {
          window.setTimeout(function () {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }, 80);
        }
      }
      syncFromHash();
      window.addEventListener('hashchange', syncFromHash);
      window.addEventListener('popstate', syncFromHash);
    })();
    </script>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
</body>
</html>
