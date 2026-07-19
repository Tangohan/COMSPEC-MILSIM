<?php
declare(strict_types=1);

$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$bannerUrl = function_exists('user_media_public_url')
    ? user_media_public_url($user['profile_banner_url'] ?? null)
    : null;

$accountNavKey = 'banner';
$accountTitle = 'Couverture du menu session';
$accountLead = 'Image affichée en haut du menu profil. Format large recommandé. JPG, PNG ou WebP — 2 Mo maximum.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Apparence</p>
        <h2 class="account-hub__panel-title">Bandeau du menu profil</h2>
        <p class="account-hub__panel-desc">Personnalisez le haut du menu « Session active » lorsque vous ouvrez votre profil.</p>
    </div>
    <div class="account-hub__panel-body">
        <div class="account-hub__media-preview account-hub__media-preview--banner" style="margin-bottom:1.25rem">
            <?php if ($bannerUrl): ?>
            <img src="<?= htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Aperçu de la couverture">
            <?php else: ?>
            <span style="font-size:.625rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.45)">Couverture par défaut</span>
            <?php endif; ?>
        </div>
        <?php if (!$bannerUrl): ?>
        <div class="account-hub__empty" style="margin-bottom:1.25rem;padding:1.25rem">
            <p class="account-hub__empty-title">Aucune couverture personnalisée</p>
            <p class="account-hub__empty-desc">Le bandeau par défaut de la communauté s’affiche tant que vous n’en avez pas ajouté une.</p>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(url('account/banner'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="account-hub__form-grid">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <label class="account-hub__label" for="banner">Choisir une image</label>
                <input type="file" name="banner" id="banner" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($errors['banner'])): foreach ($errors['banner'] as $e): ?>
                <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; endif; ?>
            </div>
            <div>
                <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer la couverture</button>
            </div>
        </form>
        <?php if ($bannerUrl): ?>
        <form method="post" action="<?= htmlspecialchars(url('account/banner'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:1rem" onsubmit="return confirm('Retirer la couverture personnalisée et revenir au bandeau par défaut ?');">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="remove_banner" value="1">
            <button type="submit" class="account-hub__btn" style="background:#fff;color:#be123c;border:1px solid #fecdd3">Retirer la couverture</button>
        </form>
        <?php endif; ?>
    </div>
</section>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/image'), ENT_QUOTES, 'UTF-8') ?>">Photo de compte</a>
    ·
    <a href="<?= htmlspecialchars(url('account/portrait'), ENT_QUOTES, 'UTF-8') ?>">Portrait opérateur</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
