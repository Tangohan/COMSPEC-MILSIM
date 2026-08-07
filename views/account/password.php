<?php
declare(strict_types=1);

$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;

$accountNavKey = 'password';
$accountTitle = 'Mot de passe';
$accountLead = 'Changez le secret utilisé pour vous connecter. Minimum 8 caractères.';
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Sécurité</p>
        <h2 class="account-hub__panel-title">Changer le mot de passe</h2>
        <p class="account-hub__panel-desc">Saisissez votre mot de passe actuel, puis le nouveau à deux reprises pour confirmer.</p>
    </div>
    <div class="account-hub__panel-body">
        <form method="post" action="<?= htmlspecialchars(url('account/password'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="account-hub__label" for="current_password">Mot de passe actuel</label>
                <input type="password" name="current_password" id="current_password" required autocomplete="current-password">
                <?php if (!empty($errors['current_password'])): foreach ($errors['current_password'] as $e): ?>
                <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; endif; ?>
            </div>
            <div>
                <label class="account-hub__label" for="new_password">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new_password" required minlength="8" autocomplete="new-password">
                <p class="account-hub__hint">Au moins 8 caractères. Évitez de réutiliser un mot de passe déjà employé ailleurs.</p>
                <?php if (!empty($errors['new_password'])): foreach ($errors['new_password'] as $e): ?>
                <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; endif; ?>
            </div>
            <div>
                <label class="account-hub__label" for="new_password_confirmation">Confirmer le nouveau mot de passe</label>
                <input type="password" name="new_password_confirmation" id="new_password_confirmation" required minlength="8" autocomplete="new-password">
                <?php if (!empty($errors['new_password_confirmation'])): foreach ($errors['new_password_confirmation'] as $e): ?>
                <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; endif; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-top:.25rem">
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer le nouveau mot de passe</button>
                <a href="<?= htmlspecialchars(url('forgot-password'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.8125rem;font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px">Mot de passe oublié ?</a>
            </div>
        </form>
    </div>
</section>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/security'), ENT_QUOTES, 'UTF-8') ?>">Double vérification</a>
    ·
    <a href="<?= htmlspecialchars(url('account/mail'), ENT_QUOTES, 'UTF-8') ?>">Adresse e-mail</a>
    ·
    <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>">Vue d’ensemble</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
