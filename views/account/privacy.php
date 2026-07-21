<?php
declare(strict_types=1);

$accountNavKey = 'donnees';
$accountTitle = 'Mes données';
$accountLead = 'Exportez ou supprimez les données que vous avez fournies ou générées sur cette communauté.';
require base_path('views/partials/account/shell_open.php');

$deletionRequestedAt = $deletionRequestedAt ?? null;
$deletionScheduledAt = $deletionScheduledAt ?? null;
?>

<div class="account-hub__stack">
    <?php if ($deletionRequestedAt): ?>
    <section class="account-hub__panel" aria-labelledby="privacy-deletion-pending-heading" style="border-color:#c0392b">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">RGPD</p>
            <h2 id="privacy-deletion-pending-heading" class="account-hub__panel-title">Suppression de compte programmée</h2>
            <p class="account-hub__panel-desc">
                Votre compte a été désactivé le
                <?= htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $deletionRequestedAt)), ENT_QUOTES, 'UTF-8') ?>
                et sera anonymisé définitivement le
                <?= $deletionScheduledAt ? htmlspecialchars(date('d/m/Y', strtotime((string) $deletionScheduledAt)), ENT_QUOTES, 'UTF-8') : '—' ?>.
                Tant que le compte n’est pas anonymisé, vous pouvez annuler la suppression en vous reconnectant.
            </p>
        </div>
        <div class="account-hub__panel-body">
            <form method="post" action="<?= htmlspecialchars(url('account/donnees/annuler-suppression'), ENT_QUOTES, 'UTF-8') ?>">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Annuler la suppression</button>
            </form>
        </div>
    </section>
    <?php endif; ?>

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

    <?php if (!$deletionRequestedAt): ?>
    <section class="account-hub__panel" aria-labelledby="privacy-delete-heading" style="border-color:#c0392b">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Zone sensible</p>
            <h2 id="privacy-delete-heading" class="account-hub__panel-title">Supprimer mon compte</h2>
            <p class="account-hub__panel-desc">
                Votre compte sera immédiatement désactivé, puis anonymisé définitivement après
                un délai de rétractation de <?= (int) \App\Services\Account\AccountDeletionService::GRACE_PERIOD_DAYS ?> jours.
                Vous pouvez annuler à tout moment pendant ce délai en vous reconnectant.
            </p>
        </div>
        <div class="account-hub__panel-body">
            <form method="post" action="<?= htmlspecialchars(url('account/donnees/supprimer'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid" onsubmit="return confirm('Confirmer la suppression de votre compte ?');">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label class="account-hub__label" for="delete-current-password">Mot de passe actuel</label>
                    <input type="password" id="delete-current-password" name="current_password" required autocomplete="current-password">
                </div>
                <div>
                    <label class="account-hub__label" for="delete-confirmation">Tapez <strong>SUPPRIMER</strong> pour confirmer</label>
                    <input type="text" id="delete-confirmation" name="confirmation" required placeholder="SUPPRIMER" autocomplete="off">
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--ink" style="background:#c0392b;border-color:#c0392b">Supprimer mon compte</button>
                </div>
            </form>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require base_path('views/partials/account/shell_close.php'); ?>
