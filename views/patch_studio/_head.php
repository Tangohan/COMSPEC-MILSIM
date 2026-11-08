<?php
$version = \App\Support\BuildVersion::id();
$studioAsset = static function (string $file) use ($version): string {
    $mtime = @filemtime(base_path('public/assets/' . $file));
    return asset_url('assets/' . $file) . '?v=' . rawurlencode($version . '.' . ($mtime ?: '1'));
};
?>
<meta name="theme-color" content="#050505">
<meta name="color-scheme" content="dark">
<link rel="stylesheet" href="<?= $e($studioAsset('css/atak-nine-line.css')) ?>">
<link rel="stylesheet" href="<?= $e($studioAsset('css/patch-studio.css')) ?>">
