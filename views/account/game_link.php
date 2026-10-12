<?php
declare(strict_types=1);

$code = (string) ($code ?? '');
$linked = (bool) ($linked ?? false);
$success = $success ?? null;
$error = $error ?? null;

$accountNavKey = 'devices';
$accountTitle = 'Lier le jeu';
$accountLead = 'Connectez le mod COMSPEC à votre compte Athena avec le code affiché dans Arma. Aucune clé ni identifiant de communauté à saisir.';
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Arma 3 · COMSPEC ATAK</p>
        <?php if ($linked): ?>
            <h2 class="account-hub__panel-title">Votre jeu est lié</h2>
            <p class="account-hub__panel-desc">Le téléphone récupère votre profil, votre communauté et vos réglages. Aux prochaines parties, la connexion se fera seule avec votre compte Steam.</p>
        <?php else: ?>
            <h2 class="account-hub__panel-title">Saisissez le code du jeu</h2>
            <p class="account-hub__panel-desc">Dans Arma, ouvrez l’app Athena du téléphone : un code de 8 caractères s’affiche (par exemple <strong>ABCD-2345</strong>). Il reste valable 10 minutes.</p>
        <?php endif; ?>
    </div>
    <?php if (!$linked): ?>
    <div class="account-hub__panel-body">
        <form method="post" action="<?= htmlspecialchars(url('lier'), ENT_QUOTES, 'UTF-8') ?>" class="account-hub__form-grid">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="account-hub__label" for="code">Code affiché dans Arma</label>
                <input type="text" name="code" id="code" required maxlength="9" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
                       value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" placeholder="XXXX-XXXX"
                       style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:1.6rem;letter-spacing:.2em;text-transform:uppercase;max-width:16rem">
                <p class="account-hub__hint">Le jeu sera rattaché au compte et à la communauté avec lesquels vous êtes connecté ici.</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin-top:.25rem">
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Autoriser ce jeu</button>
                <a href="<?= htmlspecialchars(url('account/security/devices'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:.8125rem;font-weight:700;color:#047857;text-decoration:underline;text-underline-offset:2px">Voir mes appareils liés</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</section>

<p class="account-hub__footer-note">
    Vous n’avez pas demandé ce code ? Ne validez rien : sans votre accord, le jeu reste déconnecté.
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
