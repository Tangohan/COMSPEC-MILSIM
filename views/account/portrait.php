<?php
declare(strict_types=1);

$user = $user ?? [];
$personnelProfile = $personnelProfile ?? null;
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$portraitLocked = !empty($personnelProfile['character_portrait_locked']);
$portraitUrl = null;
if (!empty($personnelProfile['character_portrait_path'])) {
    $portraitUrl = url('') . '/' . ltrim((string) $personnelProfile['character_portrait_path'], '/');
}

$accountNavKey = 'portrait';
$accountTitle = 'Portrait';
$accountLead = 'Une seule photo, visible sur le portail, la fiche et l’organigramme. JPG, PNG ou WebP — 2 Mo maximum.';
$accountUser = $user;
require base_path('views/partials/account/shell_open.php');
?>

<section class="account-hub__panel">
    <div class="account-hub__panel-head">
        <p class="account-hub__panel-kicker">Apparence</p>
        <h2 class="account-hub__panel-title">Votre photo</h2>
        <p class="account-hub__panel-desc">C’est celle que les autres membres voient sur le portail et sur la fiche.</p>
    </div>
    <div class="account-hub__panel-body">
        <div style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:flex-start">
            <div style="display:grid;gap:.65rem;justify-items:center">
                <div class="account-hub__media-preview account-hub__media-preview--portrait">
                    <?php if ($portraitUrl): ?>
                        <img src="<?= htmlspecialchars($portraitUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Portrait actuel">
                    <?php else: ?>
                    <img src="<?= htmlspecialchars(url('assets/images/inconnu.svg'), ENT_QUOTES, 'UTF-8') ?>" alt="Aucun portrait">
                    <?php endif; ?>
                </div>
                <?php if (!$portraitUrl): ?>
                <p class="account-hub__hint" style="text-align:center;max-width:10rem">Aucune photo pour l’instant.</p>
                <?php endif; ?>
                <?php if (!empty($user['id'])): ?>
                <button type="button" data-community-report data-cr-type="operator_visual" data-cr-id="<?= (int) $user['id'] ?>" data-cr-summary="Signalement concernant votre photo de profil." class="account-hub__btn" style="padding:.4rem .65rem;font-size:.625rem;background:#fff;color:#be123c;border:1px solid #fecdd3">Signaler cette photo</button>
                <?php endif; ?>
            </div>
            <?php if ($portraitLocked): ?>
            <div class="account-hub__flash account-hub__flash--warn" role="status">La modification de cette photo a été verrouillée par un administrateur de la communauté.</div>
            <?php else: ?>
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
                    <button type="submit" class="account-hub__btn account-hub__btn--ink">Enregistrer la photo</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <p class="account-hub__hint" style="margin-top:1.25rem">
            Pour le reste du dossier (affectation, formations, etc.), ouvrez
            <a href="<?= htmlspecialchars(url('personnel/me/edit'), ENT_QUOTES, 'UTF-8') ?>" style="font-weight:700;color:#047857;text-decoration:underline">votre fiche personnelle</a>.
        </p>
    </div>
</section>

<p class="account-hub__footer-note"><a href="<?= htmlspecialchars(url('account/banner'), ENT_QUOTES, 'UTF-8') ?>">Couverture du menu</a></p>

<?php require base_path('views/partials/account/shell_close.php'); ?>
