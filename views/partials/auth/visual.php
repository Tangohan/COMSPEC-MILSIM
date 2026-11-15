<?php
declare(strict_types=1);
/** Volet photo à droite des pages d’accès. Attend : $authAside (légende). */
?>
    <aside class="ds-visual" aria-hidden="true">
        <picture>
            <?php /* Variantes WebP ; le JPEG d'origine reste le repli. Le volet est plus haut que large et l'image le couvre :
                       la largeur utile suit la hauteur de l'écran (environ 2,2 fois), d'où sizes en vh. */ ?>
            <source type="image/webp"
                    srcset="<?= htmlspecialchars(asset_url('assets/images/opt/fog-team-960.webp'), ENT_QUOTES, 'UTF-8') ?> 960w, <?= htmlspecialchars(asset_url('assets/images/opt/fog-team-2048.webp'), ENT_QUOTES, 'UTF-8') ?> 2048w"
                    sizes="(min-width: 960px) 220vh, 100vw">
            <img class="ds-visual__img"
                 src="<?= htmlspecialchars(asset_url('assets/images/fog-team.jpg'), ENT_QUOTES, 'UTF-8') ?>"
                 alt=""
                 width="1600"
                 height="1067"
                 decoding="async">
        </picture>
        <div class="ds-visual__veil"></div>
        <p class="ds-visual__caption"><?= htmlspecialchars((string) $authAside, ENT_QUOTES, 'UTF-8') ?></p>
    </aside>
