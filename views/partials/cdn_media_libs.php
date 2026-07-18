<?php

declare(strict_types=1);

/**
 * Injection CDN média — phase head (CSS) ou body (JS).
 *
 * Variables :
 * - $cdnLibs : list|string|null — packs demandés (null = defaults du layout)
 * - $cdnPhase : 'head'|'body' (défaut head)
 * - $cdnPreset : 'portal'|'forum'|null
 * - $cdnSkipLocal : bool — ne pas charger athena_media.css/js locaux
 */

$cdnPhase = $cdnPhase ?? 'head';
$cdnPreset = $cdnPreset ?? null;
$cdnLibs = $cdnLibs ?? null;
$cdnSkipLocal = !empty($cdnSkipLocal);

if (!function_exists('cdn_resolve_packs')) {
    require_once base_path('app/Support/cdn_media.php');
}

$resolvedPacks = cdn_resolve_packs(
    is_array($cdnLibs) || is_string($cdnLibs) || is_bool($cdnLibs) ? $cdnLibs : null,
    $cdnPreset
);

$assets = cdn_collect_assets($resolvedPacks, $cdnPhase);
$baseUrlCdn = rtrim((string) ($baseUrl ?? url('')), '/');
$hasPacks = $resolvedPacks !== [];

if ($cdnPhase === 'head'):
    if ($hasPacks):
    // Preconnect vers les CDN utilisés
    ?>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://flagcdn.com" crossorigin>
    <link rel="dns-prefetch" href="https://tenor.googleapis.com">
    <link rel="dns-prefetch" href="https://media.tenor.com">
    <?php if (!$cdnSkipLocal && is_file(base_path('public/assets/css/athena_media.css'))): ?>
    <link href="<?= htmlspecialchars($baseUrlCdn, ENT_QUOTES, 'UTF-8') ?>/assets/css/athena_media.css" rel="stylesheet">
    <?php endif; ?>
    <?php
    endif;
    foreach ($assets as $asset):
        if (($asset['type'] ?? '') !== 'css' || empty($asset['href'])) {
            continue;
        }
        $attrs = is_array($asset['attrs'] ?? null) ? $asset['attrs'] : [];
        ?>
    <link rel="stylesheet" href="<?= htmlspecialchars((string) $asset['href'], ENT_QUOTES, 'UTF-8') ?>"<?= cdn_render_attr($attrs) ?>>
        <?php
    endforeach;
    if ($hasPacks):
    // Métadonnées pour le runtime JS
    $packsJson = htmlspecialchars(json_encode($resolvedPacks, JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
    ?>
    <meta name="athena-cdn-packs" content="<?= $packsJson ?>">
    <?php
    // Clé Tenor optionnelle (config/env) — jamais affichée en clair dans l’UI métier
    $tenorKey = '';
    if (function_exists('env')) {
        $tenorKey = (string) (env('TENOR_API_KEY') ?? env('ATHENA_TENOR_KEY') ?? '');
    }
    if ($tenorKey === '' && is_file(base_path('config/cdn_libraries.local.php'))) {
        $local = require base_path('config/cdn_libraries.local.php');
        if (is_array($local) && !empty($local['tenor_api_key'])) {
            $tenorKey = (string) $local['tenor_api_key'];
        }
    }
    if ($tenorKey !== '' && in_array('gif', $resolvedPacks, true)):
        ?>
    <meta name="athena-tenor-key" content="<?= htmlspecialchars($tenorKey, ENT_QUOTES, 'UTF-8') ?>">
        <?php
    endif;
    endif;

else: // body
    foreach ($assets as $asset):
        if (($asset['type'] ?? '') !== 'js' || empty($asset['src'])) {
            continue;
        }
        $attrs = is_array($asset['attrs'] ?? null) ? $asset['attrs'] : [];
        if (!array_key_exists('defer', $attrs) && empty($attrs['type'])) {
            $attrs['defer'] = true;
        }
        ?>
    <script src="<?= htmlspecialchars((string) $asset['src'], ENT_QUOTES, 'UTF-8') ?>"<?= cdn_render_attr($attrs) ?>></script>
        <?php
    endforeach;

    if ($hasPacks && !$cdnSkipLocal && is_file(base_path('public/assets/js/athena_media.js'))):
        ?>
    <script defer src="<?= htmlspecialchars($baseUrlCdn, ENT_QUOTES, 'UTF-8') ?>/assets/js/athena_media.js"></script>
        <?php
    endif;
endif;
