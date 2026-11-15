<?php
declare(strict_types=1);
ob_start();
require __DIR__ . '/partials/mj_page.php';
$sseContent = ob_get_clean();
$sseExtraStyles = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/css/mj.css'), ENT_QUOTES, 'UTF-8') . '">';
$sseExtraScripts = '<script defer src="' . htmlspecialchars(asset_url('assets/js/mj-page.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
require __DIR__ . '/_layout.php';
