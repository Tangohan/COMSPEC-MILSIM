<?php
declare(strict_types=1);
/**
 * Formation « Visionneuse » : l’adresse configurée dans le Studio s’affiche dans Athena.
 *
 * @var array<string, mixed> $course
 * @var string $deliveryUrl
 */
$base = url('');
$course = $course ?? [];
$deliveryUrl = (string) ($deliveryUrl ?? '');
$slug = (string) ($course['slug'] ?? '');
$theme = function_exists('training_lms_parse_theme') ? training_lms_parse_theme((string) ($course['theme_json'] ?? '')) : [];
$lmsTitle = (string) ($course['title'] ?? 'Formation');
$lmsBase = $base;
$lmsThemeVars = function_exists('training_lms_theme_css_vars') ? training_lms_theme_css_vars($theme) : '';
$lmsExtraHead = '<link href="' . htmlspecialchars(asset_url('assets/css/training-parcours-deck.css'), ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">' . "\n";
$shortDesc = trim((string) ($course['short_description'] ?? ''));
$ficheUrl = $slug !== '' ? url('formations/' . rawurlencode($slug)) . '?fiche=1' : url('formations');
ob_start();
require base_path('views/training/partials/lms_head.php');
$headHtml = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="fr" class="module-shell">
<head>
<?= $headHtml ?>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
            <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-black uppercase tracking-wide text-slate-700 hover:text-emerald-700">← Retour au catalogue</a>
            <a href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-semibold text-slate-500 hover:text-emerald-700">Fiche de la formation</a>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-4 sm:px-6 py-6 space-y-4">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700">Formation</p>
            <h1 class="mt-1 text-2xl font-black text-slate-900"><?= htmlspecialchars($lmsTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($shortDesc !== ''): ?>
            <p class="mt-1 text-sm text-slate-600"><?= htmlspecialchars($shortDesc, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <?php
        $viewerUrl = $deliveryUrl;
        $viewerTitle = $lmsTitle;
        $viewerForceFrame = true;
        $viewerCompact = false;
        require base_path('views/training/partials/url_viewer.php');
        ?>
    </main>
</body>
</html>
