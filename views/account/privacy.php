<?php
declare(strict_types=1);

$accountNavKey = 'donnees';
$accountTitle = 'Mes données';
$accountLead = 'Exportez les données que vous avez fournies ou générées sur cette communauté.';
require base_path('views/partials/account/shell_open.php');
?>

<div class="account-hub__stack">
    <section class="account-hub__panel" aria-labelledby="privacy-export-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">RGPD</p>
            <h2 id="privacy-export-heading" class="account-hub__panel-title">Exporter mes données</h2>
            <p class="account-hub__panel-desc">
                Génère une archive ZIP contenant votre profil, votre dossier personnel, vos
                formations et attestations, vos participations aux événements, vos messages du
                forum et vos messages internes envoyés sur cette communauté.
            </p>
        </div>
        <div class="account-hub__panel-body">
            <form method="post" action="<?= htmlspecialchars(url('account/donnees/export'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Télécharger mes données (ZIP)</button>
            </form>
            <p class="account-hub__panel-desc" style="margin-top:.85rem">Limité à 3 exports par heure.</p>
        </div>
    </section>

    <section class="account-hub__panel" aria-labelledby="privacy-more-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Autres demandes</p>
            <h2 id="privacy-more-heading" class="account-hub__panel-title">Rectification, effacement, opposition</h2>
            <p class="account-hub__panel-desc">
                Pour toute autre demande relative à vos données personnelles (rectification,
                effacement complet, opposition…), utilisez le formulaire dédié depuis les
                mentions légales.
            </p>
        </div>
        <div class="account-hub__panel-body">
            <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--ghost">Formulaire de demande RGPD</a>
        </div>
    </section>
</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
