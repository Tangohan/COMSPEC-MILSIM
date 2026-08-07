<?php
declare(strict_types=1);

$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$hasOtpColumn = !empty($hasOtpColumn ?? false);
$hasTotpColumns = !empty($hasTotpColumns ?? false);
$loginOtpForcedByRole = !empty($loginOtpForcedByRole ?? false);
$emailLoginOtpEnabled = !empty($emailLoginOtpEnabled ?? false);
$totpEnabled = !empty($totpEnabled ?? false);
$secondFactorActive = $loginOtpForcedByRole || $emailLoginOtpEnabled || $totpEnabled;

$accountNavKey = 'mail';
$accountTitle = 'Adresse e-mail';
$accountLead = 'Adresse utilisée pour vous connecter et pour recevoir les codes de double vérification par e-mail.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<div class="account-hub__stack">
    <section class="account-hub__panel" aria-labelledby="mail-change-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Connexion</p>
            <h2 id="mail-change-heading" class="account-hub__panel-title">Modifier l’adresse</h2>
            <p class="account-hub__panel-desc">Une confirmation par votre mot de passe actuel est obligatoire.</p>
        </div>
        <div class="account-hub__panel-body">
            <form method="post" action="<?= htmlspecialchars(url('account/mail'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="account_mail_section" value="email_change">
                <div>
                    <label class="account-hub__label" for="email">Nouvelle adresse e-mail</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
                    <?php if (!empty($errors['email'])): foreach ($errors['email'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="email_confirmation">Confirmer l’adresse e-mail</label>
                    <input type="email" name="email_confirmation" id="email_confirmation" value="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
                    <?php if (!empty($errors['email_confirmation'])): foreach ($errors['email_confirmation'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <label class="account-hub__label" for="password">Mot de passe actuel</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password">
                    <?php if (!empty($errors['password'])): foreach ($errors['password'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--ink">Mettre à jour l’adresse</button>
                </div>
            </form>
        </div>
    </section>

    <section class="account-hub__panel" aria-labelledby="mail-2fa-link-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Double vérification</p>
            <h2 id="mail-2fa-link-heading" class="account-hub__panel-title">Protéger la connexion</h2>
            <p class="account-hub__panel-desc">Code par e-mail ou application d’authentification — le détail se gère sur une page dédiée.</p>
            <p style="margin:.85rem 0 0">
                <?php if ($secondFactorActive): ?>
                <span class="account-hub__badge account-hub__badge--ok">Protection active</span>
                <?php else: ?>
                <span class="account-hub__badge account-hub__badge--off">Non activée</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="account-hub__panel-body">
            <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.55;color:#475569">
                <?php if ($totpEnabled && ($emailLoginOtpEnabled || $loginOtpForcedByRole)): ?>
                Application d’authentification et code par e-mail sont disponibles sur votre compte.
                <?php elseif ($totpEnabled): ?>
                L’application d’authentification est activée.
                <?php elseif ($emailLoginOtpEnabled || $loginOtpForcedByRole): ?>
                Un code par e-mail est demandé à la connexion.
                <?php elseif ($hasOtpColumn || $hasTotpColumns): ?>
                Renforcez votre compte en ajoutant une étape après le mot de passe.
                <?php else: ?>
                La double vérification optionnelle sera disponible après la prochaine mise à jour de la base de données sur ce serveur.
                <?php endif; ?>
            </p>
            <?php if ($hasOtpColumn || $hasTotpColumns): ?>
            <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__btn account-hub__btn--primary">Gérer la double vérification</a>
            <?php endif; ?>
        </div>
    </section>
</div>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>">Double vérification</a>
    ·
    <a href="<?= htmlspecialchars(url('account/password'), ENT_QUOTES, 'UTF-8') ?>">Mot de passe</a>
    ·
    <a href="<?= htmlspecialchars(url('account/preferences') . '#connexion-verification', ENT_QUOTES, 'UTF-8') ?>">Tester l’envoi d’un code</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
