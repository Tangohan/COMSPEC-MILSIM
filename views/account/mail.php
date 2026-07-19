<?php
declare(strict_types=1);

$user = $user ?? [];
$errors = $errors ?? [];
$otpErrors = $otpErrors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$hasOtpColumn = !empty($hasOtpColumn ?? false);
$loginOtpForcedByRole = !empty($loginOtpForcedByRole ?? false);
$emailLoginOtpEnabled = !empty($emailLoginOtpEnabled ?? false);

$accountNavKey = 'mail';
$accountTitle = 'Adresse e-mail';
$accountLead = 'Adresse utilisée pour vous connecter et pour recevoir le code de double vérification, si vous l’activez.';
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

    <?php if ($hasOtpColumn): ?>
    <section class="account-hub__panel" aria-labelledby="mail-otp-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Double vérification</p>
            <h2 id="mail-otp-heading" class="account-hub__panel-title">Code par e-mail à la connexion</h2>
            <p class="account-hub__panel-desc">Après votre mot de passe, le portail envoie un code à six chiffres sur cette adresse.</p>
            <?php if ($loginOtpForcedByRole): ?>
            <p style="margin:.85rem 0 0"><span class="account-hub__badge account-hub__badge--ok">Déjà obligatoire pour votre rôle</span></p>
            <?php endif; ?>
        </div>
        <div class="account-hub__panel-body">
            <?php if (!empty($otpErrors)): ?>
            <div class="account-hub__flash account-hub__flash--err" style="margin-bottom:1rem">
                <?php foreach ($otpErrors as $msgs): ?>
                    <?php if (is_array($msgs)): foreach ($msgs as $m): ?>
                    <p style="margin:0"><?= htmlspecialchars((string) $m, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($loginOtpForcedByRole): ?>
            <p style="margin:0;font-size:.875rem;line-height:1.55;color:#475569">Vous n’avez pas besoin d’activer quoi que ce soit ici : votre rôle applique déjà cette protection sur toutes vos connexions.</p>
            <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(url('account/mail'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="account_mail_section" value="email_login_otp">
                <label class="account-hub__check" style="cursor:pointer">
                    <input type="checkbox" name="email_login_otp_enabled" value="1" <?= $emailLoginOtpEnabled ? 'checked' : '' ?>>
                    <span style="font-size:.875rem;line-height:1.5">
                        <strong>Demander un code par e-mail</strong> après chaque saisie du mot de passe (sur tous vos appareils).
                    </span>
                </label>
                <div>
                    <label class="account-hub__label" for="otp_toggle_password">Mot de passe actuel (pour confirmer le choix)</label>
                    <input type="password" name="otp_toggle_password" id="otp_toggle_password" required autocomplete="current-password" style="max-width:24rem">
                    <?php if (!empty($otpErrors['otp_toggle_password'])): foreach ($otpErrors['otp_toggle_password'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--primary">Enregistrer la double vérification</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>
    <?php else: ?>
    <div class="account-hub__flash account-hub__flash--err" style="background:#fffbeb;border-color:#fde68a;color:#92400e">
        La double vérification optionnelle sera disponible après la prochaine mise à jour de la base de données sur ce serveur.
    </div>
    <?php endif; ?>
</div>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/password'), ENT_QUOTES, 'UTF-8') ?>">Mot de passe</a>
    ·
    <a href="<?= htmlspecialchars(url('account/preferences') . '#connexion-verification', ENT_QUOTES, 'UTF-8') ?>">Tester l’envoi d’un code</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
