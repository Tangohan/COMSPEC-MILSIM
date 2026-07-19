<?php
declare(strict_types=1);

$user = $user ?? [];
$personnelProfile = $personnelProfile ?? null;
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$portraitUrl = null;
if (!empty($personnelProfile['character_portrait_path'])) {
    $portraitUrl = url('') . '/' . ltrim((string) $personnelProfile['character_portrait_path'], '/');
}

$accountNavKey = 'portrait';
$accountTitle = 'Portrait opérateur';
$accountLead = 'Image « in-universe » pour la fiche personnelle, l’organigramme et les briefings. Portrait vertical ou carré conseillé. JPG, PNG ou WebP — 2 Mo maximum.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Apparence</p>
        <h2 class="account-hub__panel-title">Portrait du personnage</h2>
        <p class="account-hub__panel-desc">Distinct de la photo de compte : ce visuel représente votre opérateur dans l’univers MILSIM.</p>
    </div>
    <div class="account-hub__panel-body">
        <div style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:flex-start">
            <div style="display:grid;gap:.65rem;justify-items:center">
                <div class="account-hub__media-preview account-hub__media-preview--portrait">
                    <?php if ($portraitUrl): ?>
                    <img src="<?= htmlspecialchars($portraitUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Portrait opérateur actuel">
                    <?php else: ?>
                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <?php endif; ?>
                </div>
                <?php if (!$portraitUrl): ?>
                <p class="account-hub__hint" style="text-align:center;max-width:10rem">Aucun portrait pour l’instant.</p>
                <?php endif; ?>
                <?php if (!empty($user['id'])): ?>
                <button type="button" data-community-report data-cr-type="operator_visual" data-cr-id="<?= (int) $user['id'] ?>" data-cr-summary="Signalement concernant votre portrait opérateur." class="account-hub__btn" style="padding:.4rem .65rem;font-size:.625rem;background:#fff;color:#be123c;border:1px solid #fecdd3">Signaler ce portrait</button>
                <?php endif; ?>
            </div>
            <form method="post" action="<?= htmlspecialchars(url('account/portrait'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="account-hub__form-grid" style="flex:1;min-width:min(100%,16rem)">
                <?= \App\Core\Csrf::field() ?>
                <div>
                    <label class="account-hub__label" for="portrait">Choisir une image</label>
                    <input type="file" name="portrait" id="portrait" accept="image/jpeg,image/png,image/webp">
                    <?php if (!empty($errors['portrait'])): foreach ($errors['portrait'] as $e): ?>
                    <p class="account-hub__field-error"><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; endif; ?>
                </div>
                <div>
                    <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer le portrait</button>
                </div>
            </form>
        </div>
        <p class="account-hub__hint" style="margin-top:1.25rem">
            Pour le reste du dossier (affectation, clearance, etc.), ouvrez
            <a href="<?= htmlspecialchars(url('personnel/me/edit'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">votre fiche personnelle</a>.
        </p>
    </div>
</section>

<p class="account-hub__footer-note">
    <a href="<?= htmlspecialchars(url('account/image'), ENT_QUOTES, 'UTF-8') ?>">Photo de compte</a>
    ·
    <a href="<?= htmlspecialchars(url('account/banner'), ENT_QUOTES, 'UTF-8') ?>">Couverture du menu</a>
</p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
