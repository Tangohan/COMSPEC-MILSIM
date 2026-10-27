<?php
declare(strict_types=1);
/** Volet photo à droite des pages d’accès. Attend : $authAside (légende). */
?>
    <aside class="ds-visual" aria-hidden="true">
        <img class="ds-visual__img"
             src="<?= htmlspecialchars(asset_url('assets/images/fog-team.jpg'), ENT_QUOTES, 'UTF-8') ?>"
             alt=""
             width="1600"
             height="1067"
             decoding="async">
        <div class="ds-visual__veil"></div>
        <p class="ds-visual__caption"><?= htmlspecialchars((string) $authAside, ENT_QUOTES, 'UTF-8') ?></p>
    </aside>
