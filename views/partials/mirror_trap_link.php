<?php
/**
 * Lien honeypot anti-miroir : hors tabulation / lecteurs d’écran.
 * Les scrapers qui suivent tous les liens atteignent AntiScraperMiddleware::TRAP_PATH.
 * Sans libellé visible (évite qu’une automation clique un faux « Archive hors ligne »).
 */
$mirrorTrapHref = htmlspecialchars(url(\App\Middleware\AntiScraperMiddleware::TRAP_PATH), ENT_QUOTES, 'UTF-8');
?>
<a href="<?= $mirrorTrapHref ?>" tabindex="-1" aria-hidden="true" rel="nofollow" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"></a>
