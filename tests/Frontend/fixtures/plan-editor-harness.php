<?php
/**
 * Banc de rendu de l'éditeur de plans, sans base ni session : sert la vraie vue (views/atak/toc/plan_editor.php) avec
 * un terrain d'essai, un plan d'exemple et un relief synthétique (plan-dem-fixture.js). Les appels au site sont
 * remplacés dans le navigateur (plan-editor-harness.js). Sert aux captures d'écran et aux essais à la main.
 *
 *   php -S 127.0.0.1:8790 -t <racine du dépôt>
 *   http://127.0.0.1:8790/tests/Frontend/fixtures/plan-editor-harness.php?shot=edit|select|timeline|3d|los|ro[&lang=en]
 *
 * N'est jamais servi par le site (hors de public/).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
function url(string $p = ''): string
{
    return '/public/' . ltrim($p, '/');
}
function asset_url(string $p): string
{
    return '/public/' . ltrim($p, '/') . '?v=' . time();
}
$root = dirname(__DIR__, 3);
require $root . '/app/Services/AtakToc/PlanValidator.php';
require $root . '/app/Services/AtakToc/PlanCatalog.php';

$title = 'Opération Trident';
$tocPlanId = 1;
$tocReadOnlyLink = false;
$tocSharedDoc = null;
$tocTerrains = [[
    'id' => 1, 'slug' => 'essai', 'label' => 'Terrain d’essai', 'world' => 'essai',
    'config' => [
        'worldSize' => 8192, 'offsetX' => 0, 'offsetY' => 0, 'center' => [4096, 4096], 'defaultZoom' => 4, 'minZoom' => 0, 'maxZoom' => 6,
        'tileSize' => 212, 'tilePattern' => '/tests/Frontend/fixtures/none/{z}/{x}/{y}.png', 'crs' => ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
        'fondLayers' => [], 'attribution' => 'Relief synthétique',
    ],
]];
$tocDefaultMap = 1;
$tocOperator = 'TTRD';
$tocCanManage = true;
$tocCsrf = 'essai';
$tocCatalog = \App\Services\AtakToc\PlanCatalog::forClient();

ob_start();
require $root . '/views/atak/toc/plan_editor.php';
$html = (string) ob_get_clean();
$inject = '<script src="/tests/Frontend/fixtures/plan-dem-fixture.js"></script><script src="/tests/Frontend/fixtures/plan-editor-harness.js"></script>';
$html = preg_replace('~<script>window\.TOC_BOOT~', $inject . '<script>window.TOC_BOOT', $html, 1) ?? $html;
if (($_GET['lang'] ?? '') === 'en') {
    $html = str_replace('<html lang="fr"', '<html lang="en"', $html);
    $html = str_replace('</head>', '<script src="/public/assets/js/i18n_en_dict.js" defer></script><script src="/public/assets/js/i18n_dom.js" defer></script></head>', $html);
}
header('Content-Type: text/html; charset=utf-8');
echo $html;
