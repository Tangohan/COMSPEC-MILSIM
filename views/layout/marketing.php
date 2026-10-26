<?php
declare(strict_types=1);

$title = $title ?? 'Athena';
$content = $content ?? 'site.about';
$base = url('');
$loggedIn = (bool) \App\Core\Session::get('user_id');
$meta_description = $meta_description ?? '';
$marketingActive = (string) ($marketingActive ?? '');
$og_image = $og_image ?? (rtrim($base, '/') . '/assets/images/fog-team.jpg');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(html_lang(), ENT_QUOTES, 'UTF-8') ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    require base_path('views/partials/seo_meta.php');
?>
    <meta name="theme-color" content="#050505">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/icons/athena-180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/icons/athena-192.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/design-system.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/styles.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/home-impact.css?v=hero-av-5" rel="stylesheet">
    <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/site-marketing.css?v=2" rel="stylesheet">
    <link href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/assets/css/public-site-footer.css?v=2" rel="stylesheet">
<?php
    $marketingStyles = is_array($marketingStyles ?? null) ? $marketingStyles : [];
    foreach ($marketingStyles as $href):
        $href = trim((string) $href);
        if ($href === '') {
            continue;
        }
?>
    <link href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php
    endforeach;
?>
<?php
    $crumbName = match ($marketingActive) {
        'contact' => __('site.contact'),
        'changelog' => __('site.changelog'),
        'sse' => __('site.sse'),
        'atak-natif' => __('site.atak_native'),
        'traduire-atak' => __('site.atakt_crumb'),
        'contribuer' => __('site.contrib_crumb'),
        'traduire-site' => __('site.sitetr_crumb'),
        'soutenir' => __('site.support_crumb'),
        default => __('site.about'),
    };
    $crumbPath = match ($marketingActive) {
        'contact' => '/contact',
        'changelog' => '/nouveautes',
        'sse' => '/sse',
        'atak-natif' => '/atak-natif',
        'traduire-atak' => '/traduire-atak',
        'contribuer' => '/contribuer',
        'traduire-site' => '/traduire-site',
        'soutenir' => '/soutenir-atak',
        default => '/a-propos',
    };
    $siteRoot = rtrim($base, '/');
?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Athena',
                'item' => $siteRoot !== '' ? $siteRoot . '/' : '/',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $crumbName,
                'item' => ($siteRoot !== '' ? $siteRoot : '') . $crumbPath,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
    $jsonLdExtra = is_array($jsonLdExtra ?? null) ? $jsonLdExtra : [];
    foreach ($jsonLdExtra as $jsonLdBlock):
        if (!is_array($jsonLdBlock)) {
            continue;
        }
?>
    <script type="application/ld+json"><?= json_encode($jsonLdBlock, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php
    endforeach;
?>
</head>
<body class="home-impact site-marketing layout-light bg-[var(--hi-void)] text-[var(--hi-ink)] antialiased selection:bg-emerald-500 selection:text-slate-950 overflow-x-hidden<?= $marketingActive !== '' ? ' site-marketing--' . htmlspecialchars(preg_replace('/[^a-z0-9\-]/i', '', $marketingActive) ?? '', ENT_QUOTES, 'UTF-8') : '' ?>">

    <div id="bodyOverlay" class="overlay fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm" onclick="toggleMenu()"></div>
    <div id="navDrawer" class="drawer-translate fixed top-0 left-0 z-[120] flex h-full w-[min(100%,320px)] flex-col overflow-hidden border-r border-white/10 bg-[#0a0a0a] shadow-2xl">
        <?php require base_path('views/partials/home_nav_drawer.php'); ?>
    </div>

    <header class="site-marketing__topbar fixed inset-x-0 top-0 z-[100] border-b border-white/5 bg-black/70 backdrop-blur-md">
        <div class="mx-auto flex h-14 max-w-[100rem] items-center justify-between px-5 md:px-8">
            <button type="button" onclick="toggleMenu()" class="group flex h-6 w-6 flex-col justify-center gap-1.5 outline-none" aria-label="<?= htmlspecialchars(__('common.open_menu'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="h-px w-full bg-white/80 transition group-hover:bg-white"></span>
                <span class="ml-auto h-px w-1/2 bg-white/50 transition group-hover:w-full group-hover:bg-white"></span>
            </button>
            <a href="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/" class="site-marketing__brand absolute left-1/2 -translate-x-1/2 text-center">
                <span class="site-marketing__brand-mark" aria-hidden="true"></span>
                <span class="block text-[11px] font-black uppercase tracking-[0.32em] text-white">Athena</span>
            </a>
            <div class="flex items-center gap-4">
                <?php require base_path('views/partials/language_switcher.php'); ?>
                <?php if (!$loggedIn): ?>
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="site-marketing__enter text-[10px] font-bold uppercase tracking-[0.2em] text-white/70 transition hover:text-white"><?= htmlspecialchars(__('common.enter'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="site-marketing__ops text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 transition hover:text-emerald-300"><?= htmlspecialchars(__('common.ops'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="site-marketing__main pt-14">
        <?php require base_path('views/' . str_replace('.', '/', (string) $content) . '.php'); ?>
    </main>

    <?php require base_path('views/partials/public_site_footer.php'); ?>

    <?php require base_path('views/partials/cookie_banner.php'); ?>
    <?php if (!empty(\App\Core\Session::get('user_id'))): ?>
        <?php require base_path('views/partials/platform_review_modal.php'); ?>
    <?php endif; ?>
<?php
    $marketingScripts = is_array($marketingScripts ?? null) ? $marketingScripts : [];
    foreach ($marketingScripts as $src):
        $src = trim((string) $src);
        if ($src === '') {
            continue;
        }
?>
    <script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php
    endforeach;
?>
    <script>
        function toggleMenu() {
            document.body.classList.toggle('drawer-open');
            document.body.style.overflow = document.body.classList.contains('drawer-open') ? 'hidden' : '';
        }
    </script>
    <?php require base_path('views/partials/mirror_trap_link.php'); ?>
<?php require base_path('views/partials/donor_marks_assets.php'); ?>
</body>
</html>
