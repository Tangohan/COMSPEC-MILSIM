<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$steamWebConfigured = !empty($steamWebConfigured);
$listUrl = url('back-office/users');
$inviteUrl = url('back-office/invitations');
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<div class="bo-user-edit">
    <header class="bo-user-edit__hero">
        <div class="bo-user-edit__hero-inner">
            <div class="min-w-0">
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__back">← Liste des membres</a>
                <p class="bo-user-edit__eyebrow">Membres · Création</p>
                <h1 class="bo-user-edit__title">Nouvel utilisateur</h1>
                <p class="bo-user-edit__lead">
                    Aucun mot de passe n’est saisi ici : un e-mail est envoyé avec un lien sécurisé pour définir le mot de passe et activer le compte.
                    Vous pouvez aussi rattacher l’identifiant Steam pour la carte.
                </p>
            </div>
            <div class="bo-user-edit__hero-actions">
                <a href="<?= htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost">Préférer une invitation</a>
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--solid">Annuler</a>
            </div>
        </div>
    </header>

    <div class="bo-user-edit__deck">
        <?php if ($flashErr): ?>
            <div class="bo-user-edit__flash bo-user-edit__flash--err" role="alert"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($flashOk): ?>
            <div class="bo-user-edit__flash bo-user-edit__flash--ok" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars(url('back-office/users/store'), ENT_QUOTES, 'UTF-8') ?>" id="user-admin-create-form" class="bo-user-edit__create">
            <?= \App\Core\Csrf::field() ?>
            <section class="bo-user-edit__panel" aria-labelledby="create-account-heading">
                <h2 id="create-account-heading" class="bo-user-edit__panel-title">Identité &amp; accès</h2>
                <p class="bo-user-edit__panel-lead">Renseignez l’e-mail de contact, le nom affiché, l’indicatif et les rôles à attribuer dès la création.</p>
                <div class="bo-user-edit__create-fields space-y-4">
                    <?php
                    $fieldIdPrefix = '';
                    $matrixRootId = 'role-matrix-wrap';
                    require base_path('views/admin/organization/partials/user_invite_form_fields.php');
                    ?>
                </div>
            </section>

            <div class="bo-user-edit__actions-bar">
                <button type="submit" class="bo-user-edit__btn bo-user-edit__btn--dark">Créer et envoyer l’e-mail</button>
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost bo-user-edit__btn--light">Annuler</a>
            </div>
        </form>
    </div>
</div>
