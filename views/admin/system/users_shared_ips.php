<?php declare(strict_types=1); ?>
<div class="pa pu">
    <div class="pa__frame">
        <header class="pa-hero">
            <h1 class="pa-hero__title">Adresses IP partagées</h1>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--ghost" href="<?= htmlspecialchars(url('admin/users'), ENT_QUOTES, 'UTF-8') ?>">Retour à l’annuaire</a>
            </div>
        </header>
        <section class="pa-card" aria-label="Connexions par adresse IP">
            <?php require base_path('views/admin/system/partials/platform_shared_ips.php'); ?>
        </section>
    </div>
</div>
