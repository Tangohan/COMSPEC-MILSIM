<?php
declare(strict_types=1);

/**
 * Injecte les styles vitrine en <style> (contourne le 404 LiteSpeed sur les .css statiques).
 * Retourne true si le CSS a été écrit.
 */
$communityVitrineCssPaths = [
    base_path('storage/css-fallback/community-vitrine.css'),
    base_path('public/assets/css/community-landing.css'),
    base_path('public/assets/css/community-vitrine.css'),
    base_path('public/assets/css/athena-community-vitrine.css'),
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'css-fallback' . DIRECTORY_SEPARATOR . 'community-vitrine.css',
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'community-landing.css',
];

foreach ($communityVitrineCssPaths as $communityVitrineCssPath) {
    if (!is_readable($communityVitrineCssPath)) {
        continue;
    }
    $communityVitrineCssBody = (string) file_get_contents($communityVitrineCssPath);
    if ($communityVitrineCssBody === '') {
        continue;
    }
    echo "\n    <!-- cv-css:inline-20260728c -->\n";
    echo "    <style id=\"community-vitrine-fallback\">\n" . $communityVitrineCssBody . "\n    </style>\n";
    unset($communityVitrineCssBody, $communityVitrineCssPath, $communityVitrineCssPaths);

    return true;
}

unset($communityVitrineCssPath, $communityVitrineCssPaths);

return false;
