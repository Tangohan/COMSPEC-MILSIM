<?php
declare(strict_types=1);
/**
 * Registre des papiers : bandeau « simulation » et feuille de style. Les pages de la section sont dans
 * la navigation du bureau (App\Support\SsePortalNav, sections « Identités et papiers » et « Fichiers et contrôles »).
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">';
?>
<div class="idp-sim" role="note">
    <strong>SIMULATION</strong>
    <span>Papiers fictifs pour le jeu, émis par des pays imaginaires (ou des pays réels stylisés dans un univers réaliste-simulation). Aucune valeur réelle.</span>
</div>
