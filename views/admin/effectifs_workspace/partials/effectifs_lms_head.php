<?php
declare(strict_types=1);
$effectifsLmsTitle = trim((string) ($effectifsLmsTitle ?? $title ?? 'Bureau effectifs'));
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($effectifsLmsTitle, ENT_QUOTES, 'UTF-8') ?> — Bureau effectifs — Athena</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?php require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
<?php if (is_file(base_path('public/assets/css/training_lms.css'))): ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/training_lms.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php endif; ?>
<?php if (is_file(base_path('public/assets/css/effectifs_lms.css'))): ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/effectifs_lms.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php endif; ?>
<?php if (is_file(base_path('public/assets/css/img-fallback.css'))): ?>
<link href="<?= htmlspecialchars(asset_url('assets/css/img-fallback.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<?php endif; ?>
<?php if (is_file(base_path('public/assets/js/img-fallback.js'))): ?>
<script src="<?= htmlspecialchars(asset_url('assets/js/img-fallback.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
