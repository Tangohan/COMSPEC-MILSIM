<?php
/**
 * En-tête commun des pages du poste TOC web (console, liste et éditeur de plans) : polices, Leaflet du site,
 * milsymbol, feuille du TOC, données de démarrage (window.TOC_BOOT). Console sombre seulement.
 *
 * @var string $title
 * @var list<array<string, mixed>> $tocTerrains
 * @var int $tocDefaultMap
 * @var string $tocOperator
 * @var bool $tocCanManage
 * @var string $tocCsrf
 * @var array<string, mixed> $tocCatalog
 * @var array<string, mixed> $tocExtraBoot
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$a = static fn (string $p): string => function_exists('asset_url') ? asset_url($p) : url($p);
$tocBoot = [
    'base' => rtrim(url(''), '/'),
    'terrains' => $tocTerrains ?? [],
    'defaultMap' => (int) ($tocDefaultMap ?? 1),
    'operator' => (string) ($tocOperator ?? ''),
    'canManage' => !empty($tocCanManage),
    'csrf' => (string) ($tocCsrf ?? ''),
    'catalog' => $tocCatalog ?? [],
] + (is_array($tocExtraBoot ?? null) ? $tocExtraBoot : []);
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#070a0d">
<meta name="color-scheme" content="dark">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= $h($tocCsrf ?? '') ?>">
<title><?= $h($title ?? 'Poste TOC') ?> · Athena</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $h($a('assets/vendor/leaflet-1.9.4/leaflet.css')) ?>">
<link rel="stylesheet" href="<?= $h($a('assets/css/atak-toc.css')) ?>">
<script>window.TOC_BOOT = <?= json_encode($tocBoot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= $h($a('assets/vendor/leaflet-1.9.4/leaflet.js')) ?>" defer></script>
<script src="<?= $h($a('assets/vendor/milsymbol/milsymbol.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/atak-map-crs.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/toc-geo.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/toc-map.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/toc-plan-render.js')) ?>" defer></script>
<script src="<?= $h($a('assets/js/toc/toc-layers.js')) ?>" defer></script>
