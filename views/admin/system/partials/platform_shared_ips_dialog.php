<?php declare(strict_types=1); ?>
<dialog class="pu-dialog pu-dialog--wide pa-network-dialog" data-network-dialog aria-labelledby="network-dialog-title">
    <div class="pu-dialog__form">
        <div class="pa-network-dialog__header">
            <h2 class="pu-dialog__title" id="network-dialog-title">Adresses IP partagées</h2>
            <form method="dialog"><button type="submit" class="pa-btn pa-btn--line pa-btn--sm" autofocus>Fermer</button></form>
        </div>
        <p class="pu-muted" data-network-status role="status"></p>
        <div data-network-content></div>
    </div>
</dialog>
<script src="<?= htmlspecialchars(asset_url('assets/js/platform-network-dialog.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
