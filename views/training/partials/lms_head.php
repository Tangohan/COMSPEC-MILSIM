<?php
declare(strict_types=1);
/** @var string $lmsTitle */
/** @var string $lmsBase */
$lmsTitle = $lmsTitle ?? 'Athena';
$lmsBase = $lmsBase ?? url('');
$lmsThemeVars = $lmsThemeVars ?? '';
$lmsExtraHead = $lmsExtraHead ?? '';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($lmsTitle) ?> — Athena</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<?php
$tailwindBaseUrl = $lmsBase;
require base_path('views/partials/tailwind_cdn_or_build.php');
?>
<link href="<?= htmlspecialchars(rtrim($lmsBase, '/')) ?>/assets/css/training_lms.css" rel="stylesheet">
<?php if ($lmsThemeVars !== ''): ?>
<style>:root { <?= $lmsThemeVars ?> }</style>
<?php endif; ?>
<?= $lmsExtraHead ?>
