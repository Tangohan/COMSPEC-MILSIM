<?php
declare(strict_types=1);

$user = $user ?? [];
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$avatarUrl = function_exists('user_media_public_url')
    ? user_media_public_url($user['avatar_url'] ?? null)
    : (!empty($user['avatar_url']) ? (url('') . '/' . ltrim((string) $user['avatar_url'], '/')) : null);

$accountNavKey = 'image';
$accountTitle = 'Photo de compte';
$accountLead = 'Visible dans la navigation, le menu session, le forum et les listes de membres. JPG, PNG ou WebP — 2 Mo maximum.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Apparence</p>
        <h2 class="account-hub__panel-title">Mettre à jour la photo</h2>
        <p class="account-hub__panel-desc">Cette photo représente votre compte civil sur le portail — distincte du portrait opérateur.</p>
    </div>
    <div class="account-hub__panel-body">
        <div style="display:flex;flex-direction:column;gap:1.5rem">
            <div style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:flex-start">
                <div style="display:grid;gap:.65rem;justify-items:center">
                    <div class="account-hub__media-preview account-hub__media-preview--avatar">
                        <?php if ($avatarUrl): ?>
                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Photo de compte actuelle">
                        <?php else: ?>
                        <span style="font-size:1.75rem;font-weight:900;color:#fff"><?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($user['display_name'] ?? $user['email'] ?? '?'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($avatarUrl)): ?>
                    <p class="account-hub__hint" style="text-align:center;max-width:10rem">Aucune photo pour l’instant — l’initiale est affichée.</p>
                    <?php endif; ?>
                    <?php if (!empty($user['id'])): ?>
                    <button type="button" data-community-report data-cr-type="profile_picture" data-cr-id="<?= (int) $user['id'] ?>" data-cr-summary="Signalement concernant votre photo de compte." class="account-hub__btn" style="padding:.4rem .65rem;font-size:.625rem;background:#fff;color:#be123c;border:1px solid #fecdd3">Signaler cette photo</button>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= htmlspecialchars(url('account/image'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="account-hub__form-grid" style="flex:1;min-width:min(100%,16rem)">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label class="account-hub__label" for="avatar">Choisir une image</label>
                        <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($errors['avatar'])): foreach ($errors['avatar'] as $e): ?>
                        <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endforeach; endif; ?>
                    </div>
                    <div>
                        <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer la photo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/banner'), ENT_QUOTES, 'UTF-8') ?>">Couverture du menu</a>
    ·
    <a href="<?= htmlspecialchars(url('account/portrait'), ENT_QUOTES, 'UTF-8') ?>">Portrait opérateur</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
