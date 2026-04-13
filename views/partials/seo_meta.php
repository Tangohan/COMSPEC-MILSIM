<?php
declare(strict_types=1);
/**
 * Métadonnées SEO et Open Graph (résumé + partage).
 *
 * Définir avant inclusion (optionnel) :
 * - $seo_og_title : titre complet identique à la balise <title> (og:title).
 * - $meta_description : texte pour meta name="description" et og:description (recommandé par page).
 * - $og_description : surcharge uniquement pour og:description si besoin.
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

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$canonicalUrl = $host !== '' ? $scheme . '://' . $host . $requestUri : '';

?>
    <meta name="description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDesc, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($canonicalUrl !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
