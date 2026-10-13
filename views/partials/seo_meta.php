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
    ? \App\Support\Seo::withoutLang(trim((string) $seo_canonical))
    : \App\Support\Seo::canonicalUrl();
$seoLangParam = \App\Services\I18n\LocaleService::fromQuery();
$canonicalUrl = ($canonicalBase !== '' && $seoLangParam !== null)
    ? \App\Support\Seo::withLang($canonicalBase, $seoLangParam)
    : $canonicalBase;
$seoAlternates = ($seoIndexable && $canonicalBase !== '') ? \App\Support\Seo::alternates($canonicalBase) : [];
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
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Athena Comspec',
        'url' => $siteUrl !== '' ? $siteUrl : ($canonicalUrl !== '' ? $canonicalUrl : '/'),
        'description' => $desc,
        'inLanguage' => $ogLocale === 'en_US' ? 'en' : 'fr',
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Athena Comspec',
            'url' => $siteUrl !== '' ? $siteUrl : '/',
            'logo' => $siteUrl . '/assets/icons/athena-512.png',
        ],
    ], $jsonFlags) ?></script>
<?php
$extraLd = $seo_json_ld ?? null;
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
