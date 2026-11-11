<?php
declare(strict_types=1);
/**
 * Passage aux fichiers : bandeau SIMULATION et feuilles de style. Les quatre pages (consultation, journal,
 * mentions, fiches SEEK II) sont dans la navigation du bureau, section « Fichiers et contrôles ».
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseExtraStyles = ($sseExtraStyles ?? '')
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-files.css')) . '">';
?>
<div class="fq-sim" role="note">
    <strong>SIMULATION</strong>
    <span>Fichiers fictifs : seules les données du registre de votre communauté sont consultées. Aucune base réelle n’est interrogée.</span>
</div>
