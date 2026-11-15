<?php
declare(strict_types=1);
/**
 * Personnes : vue de travail (tableau ou cartes compactes, onglets à compteurs, filtres, tiroir d'aperçu, actions).
 * Le contenu est dans persons/_content.php (testé seul) ; ici seulement la coque du bureau SSE.
 */
$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
require __DIR__ . '/persons/_content.php';
$sseContent = ob_get_clean();
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-persons.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-persons.js')) . '" defer></script>';
require __DIR__ . '/_layout.php';
