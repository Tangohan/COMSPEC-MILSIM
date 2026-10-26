<?php
declare(strict_types=1);

// Rétrospective annuelle plein écran (stories). Page autonome : pas d'en-tête du portail ni de fenêtres par-dessus.
// Attend : $yrYear, $yrSlides (YearReviewStory::slides), $yrCloseUrl, $yrPreview, $yrYears, $yrTenantName.

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$yrSlides = is_array($yrSlides ?? null) ? $yrSlides : [];
$yrYears = is_array($yrYears ?? null) ? $yrYears : [];
$yrYear = (int) ($yrYear ?? 0);
$lang = function_exists('html_lang') ? html_lang() : 'fr';
?>
<!DOCTYPE html>
<html lang="<?= $h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#0b0f0d">
    <title>Rétrospective <?= $yrYear ?> — ATHENA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link href="<?= $h(asset_url('assets/css/year-review.css')) ?>" rel="stylesheet">
</head>
<body class="yr-body">
<?php require base_path('views/year_review/story.php'); ?>
<script src="<?= $h(asset_url('assets/js/year-review.js')) ?>" defer></script>
</body>
</html>
