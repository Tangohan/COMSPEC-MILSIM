<?php

declare(strict_types=1);

// Les messages (retrait d'un appareil, session expirée) sont affichés une seule fois par la coque JNET.
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
?>
<div class="bo-member-situation atk-dev">
    <p class="bo-member-situation__lead">
        <a href="<?= $h(jnet_moi_url('liaison-atak')) ?>">← Ma liaison ATAK</a>
    </p>

    <?php require base_path('views/partials/atak_device_cards.php'); ?>
</div>
