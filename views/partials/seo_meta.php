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
 * - $seo_robots : contenu de meta robots (défaut index,follow…).
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
$ogTitle = isset($seo_og_title) && trim((string) $seo_og_title) !== ''
    ? trim((string) $seo_og_title)
    : 'Athena';
$robots = isset($seo_robots) && trim((string) $seo_robots) !== ''
    ? trim((string) $seo_robots)
    : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$canonicalUrl = $host !== '' ? $scheme . '://' . $host . $requestUri : '';
$canonicalUrl = preg_replace('/([?&])(utm_[^&]+|fbclid|gclid)=[^&]*/i', '$1', (string) $canonicalUrl) ?: $canonicalUrl;
$canonicalUrl = rtrim((string) preg_replace('/[?&]+$/', '', (string) $canonicalUrl), '?');
$siteUrl = rtrim((string) url(''), '/');
if ($siteUrl === '' && $host !== '') {
    $siteUrl = $scheme . '://' . $host;
}

$ogImage = isset($og_image) ? trim((string) $og_image) : '';
if ($ogImage === '') {
    $ogImage = $siteUrl . '/assets/images/fog-team.jpg';
} elseif (!preg_match('#^https?://#i', $ogImage)) {
    $ogImage = $siteUrl . '/' . ltrim($ogImage, '/');
}

$localeTag = function_exists('html_lang') ? (string) html_lang() : 'fr';
$ogLocale = str_starts_with(strtolower($localeTag), 'en') ? 'en_US' : 'fr_FR';

?>
    <meta name="description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="author" content="Athena Compsec">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Athena Compsec">
    <meta property="og:locale" content="<?= htmlspecialchars($ogLocale, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="Athena Compsec — portail MILSIM">
<?php if ($canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>">
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Athena Compsec',
        'url' => $siteUrl !== '' ? $siteUrl : ($canonicalUrl !== '' ? $canonicalUrl : '/'),
        'description' => $desc,
        'inLanguage' => $ogLocale === 'en_US' ? 'en' : 'fr',
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Athena Compsec',
            'url' => $siteUrl !== '' ? $siteUrl : '/',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
