<?php
declare(strict_types=1);
$recruitmentLmsTitle = trim((string) ($recruitmentLmsTitle ?? $title ?? 'Recrutement'));
$lmsBase = $lmsBase ?? url('');
$lmsThemeVars = (string) ($lmsThemeVars ?? '--lms-accent: #0ea5e9; --lms-accent-rgb: 14, 165, 233;');
$lmsExtraHead = (string) ($lmsExtraHead ?? '');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($recruitmentLmsTitle, ENT_QUOTES, 'UTF-8') ?> — Bureau recrutement — Athena</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
<?php
$tailwindBaseUrl = $lmsBase;
require base_path('views/partials/tailwind_cdn_or_build.php');
?>
<link href="<?= htmlspecialchars(rtrim($lmsBase, '/'), ENT_QUOTES, 'UTF-8') ?>/assets/css/training_lms.css" rel="stylesheet">
<link href="<?= htmlspecialchars(rtrim($lmsBase, '/'), ENT_QUOTES, 'UTF-8') ?>/assets/css/recruitment_lms_overrides.css" rel="stylesheet">
<?php if ($lmsThemeVars !== ''): ?>
<style>:root { <?= $lmsThemeVars ?> }</style>
<?php endif; ?>
<?= $lmsExtraHead ?>
