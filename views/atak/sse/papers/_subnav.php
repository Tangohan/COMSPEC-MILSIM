<?php
declare(strict_types=1);
/**
 * Registre des papiers : mention unique « papiers fictifs » et feuille de style. Les pages de la section sont dans
 * la navigation du bureau (App\Support\SsePortalNav, sections « Identités et papiers » et « Fichiers et contrôles »).
 */
$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '">';
?>
<?php if (empty($hideSimNote)): ?>
<div class="idp-sim" role="note">
    <span>Papiers fictifs pour le jeu de rôle, sans valeur réelle.</span>
</div>
<?php endif; ?>
