<?php
declare(strict_types=1);
/**
 * Métadonnées SEO et Open Graph (résumé + partage).
 *
 * Définir avant inclusion (optionnel) :
 * - $seo_og_title : titre complet identique à la balise <title> (og:title).
 * - $meta_description : texte pour meta name="description" et og:description (recommandé par page).
 * - $og_description : surcharge uniquement pour og:description si besoin.
 * - $og_image : URL absolue ou chemin sous la base du site pour l’image de partage.
 * - $og_image_alt : texte alternatif de l’image OG.
 * - $seo_robots : contenu de meta robots (défaut index,follow…).
 * - $seo_og_type : og:type (défaut website).
 * - $seo_json_ld : graphe(s) schema.org supplémentaires (array associatif ou liste de graphes).
 * - $seo_canonical : URL canonique forcée (sinon dérivée de la requête, sans paramètre de suivi ni de filtre).
 *
 * Pages publiques indexables : liens hreflang fr / en / x-default (?lang=…, voir App\Support\Seo).
 * Chemins privés (Seo::PRIVATE_PREFIXES) : toujours noindex.
 *
 * Si $meta_description est vide, une phrase par défaut du portail est utilisée.
 */
$defaultDesc = 'Portail Athena pour les communautés MILSIM : formations, espace d’échanges, outils d’unité et ressources de votre organisation.';
$desc = isset($meta_description) && trim((string) $meta_description) !== ''
    ? trim((string) $meta_description)
    : $defaultDesc;
$ogDesc = isset($og_description) && trim((string) $og_description) !== ''
    ? trim((string) $og_description)
    : $desc;
// Les layouts passent le titre déjà échappé : on le remet en texte brut pour ne pas l'échapper deux fois.
$ogTitle = isset($seo_og_title) && trim((string) $seo_og_title) !== ''
    ? html_entity_decode(trim((string) $seo_og_title), ENT_QUOTES | ENT_HTML5, 'UTF-8')
    : 'Athena';
$robots = isset($seo_robots) && trim((string) $seo_robots) !== ''
    ? trim((string) $seo_robots)
    : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
$seoPath = \App\Support\Seo::requestPath();
if (\App\Support\Seo::isPrivatePath($seoPath)) {
    $robots = 'noindex,nofollow';
}
$seoIndexable = !str_contains(strtolower($robots), 'noindex');
$ogType = isset($seo_og_type) && trim((string) $seo_og_type) !== ''
    ? trim((string) $seo_og_type)
    : 'website';
$ogImageAlt = isset($og_image_alt) && trim((string) $og_image_alt) !== ''
    ? trim((string) $og_image_alt)
    : 'Athena Comspec — portail MILSIM';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
// Canonique sans paramètre de suivi, de filtre ni de langue ; la version d'une langue précise (?lang=en) pointe sur elle-même.
$canonicalBase = isset($seo_canonical) && trim((string) $seo_canonical) !== ''
    ? \App\Support\Seo::onCurrentHost(\App\Support\Seo::withoutLang(trim((string) $seo_canonical)))
    : \App\Support\Seo::canonicalUrl();
$seoLangParam = \App\Services\I18n\LocaleService::fromQuery();
$canonicalUrl = ($canonicalBase !== '' && $seoLangParam !== null)
    ? \App\Support\Seo::withLang($canonicalBase, $seoLangParam)
    : $canonicalBase;
$seoAlternates = ($seoIndexable && $canonicalBase !== '') ? \App\Support\Seo::alternates($canonicalBase) : [];
// Adresses déclarées sur le domaine actuel, même quand la page est servie par l'ancien.
$siteUrl = \App\Support\Seo::baseUrl();
if ($siteUrl === '' && $host !== '') {
    $siteUrl = $scheme . '://' . $host;
}

$ogImage = isset($og_image) ? trim((string) $og_image) : '';
if ($ogImage === '') {
    $ogImage = $siteUrl . '/assets/images/fog-team.jpg';
} elseif (!preg_match('#^https?://#i', $ogImage)) {
    $ogImage = $siteUrl . '/' . ltrim($ogImage, '/');
}
$ogImage = \App\Support\Seo::onCurrentHost($ogImage);
// Dimensions de l'image de partage quand elle est servie par le site (aperçus plus rapides et mieux cadrés).
$ogImageSize = null;
$ogImagePath = (string) (parse_url($ogImage, PHP_URL_PATH) ?: '');
$ogImageHost = strtolower((string) (parse_url($ogImage, PHP_URL_HOST) ?: ''));
$siteHost = strtolower((string) (parse_url($siteUrl, PHP_URL_HOST) ?: ''));
if ($ogImagePath !== '' && $ogImageHost === $siteHost) {
    $sitePrefix = rtrim((string) (parse_url($siteUrl, PHP_URL_PATH) ?: ''), '/');
    $relative = $sitePrefix !== '' && str_starts_with($ogImagePath, $sitePrefix . '/') ? substr($ogImagePath, strlen($sitePrefix)) : $ogImagePath;
    $ogFile = base_path('public/' . ltrim(rawurldecode($relative), '/'));
    if (!str_contains($relative, '..') && is_file($ogFile)) {
        $dims = @getimagesize($ogFile);
        if (is_array($dims) && $dims[0] > 0 && $dims[1] > 0) {
            $ogImageSize = ['w' => (int) $dims[0], 'h' => (int) $dims[1], 'mime' => (string) ($dims['mime'] ?? '')];
        }
    }
}

$localeTag = function_exists('html_lang') ? (string) html_lang() : 'fr';
$ogLocale = str_starts_with(strtolower($localeTag), 'en') ? 'en_US' : 'fr_FR';

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

?>
    <meta name="description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="author" content="Athena Comspec">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="Athena Comspec">
    <meta property="og:locale" content="<?= htmlspecialchars($ogLocale, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($ogImageSize !== null): ?>
    <meta property="og:image:width" content="<?= $ogImageSize['w'] ?>">
    <meta property="og:image:height" content="<?= $ogImageSize['h'] ?>">
<?php if ($ogImageSize['mime'] !== ''): ?>
    <meta property="og:image:type" content="<?= htmlspecialchars($ogImageSize['mime'], ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php endif; ?>
<?php if ($canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php foreach ($seoAlternates as $hrefLang => $hrefUrl): ?>
    <link rel="alternate" hreflang="<?= htmlspecialchars($hrefLang, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($hrefUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
    <meta property="og:locale:alternate" content="<?= $ogLocale === 'en_US' ? 'fr_FR' : 'en_US' ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($ogImageAlt, ENT_QUOTES, 'UTF-8') ?>">
<?php
// Données structurées : seulement sur les pages indexables (une page « noindex » n'en déclare aucune).
if ($seoIndexable && $siteUrl !== ''):
    $orgId = $siteUrl . '/#organization';
    $siteGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $orgId,
                'name' => 'Athena Comspec',
                'alternateName' => ['ATHENA', 'COMSPEC'],
                'url' => $siteUrl . '/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $siteUrl . '/assets/icons/athena-512.png',
                    'width' => 512,
                    'height' => 512,
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'url' => $siteUrl . '/contact',
                    'availableLanguage' => ['fr', 'en'],
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $siteUrl . '/#website',
                'name' => 'Athena Comspec',
                'url' => $siteUrl . '/',
                'inLanguage' => ['fr', 'en'],
                'publisher' => ['@id' => $orgId],
                // Recherche de l'annuaire des communautés (/communities?q=…).
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => $siteUrl . '/communities?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ];
?>
    <script type="application/ld+json"><?= json_encode($siteGraph, $jsonFlags) ?></script>
<?php endif; ?>
<?php
$extraLd = $seoIndexable ? ($seo_json_ld ?? null) : null;
if (is_array($extraLd) && $extraLd !== []) {
    $graphs = array_is_list($extraLd) && isset($extraLd[0]) && is_array($extraLd[0])
        ? $extraLd
        : [$extraLd];
    foreach ($graphs as $graph) {
        if (!is_array($graph) || $graph === []) {
            continue;
        }
        if (!isset($graph['@context'])) {
            $graph = ['@context' => 'https://schema.org'] + $graph;
        }
        $encoded = json_encode($graph, $jsonFlags);
        if ($encoded === false) {
            continue;
        }
        echo "    <script type=\"application/ld+json\">{$encoded}</script>\n";
    }
}
?>
