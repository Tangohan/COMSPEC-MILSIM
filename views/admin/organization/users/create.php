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
$rosterWithoutAccounts = !empty($rosterWithoutAccounts);
?>
<div class="bo-user-edit">
    <header class="bo-user-edit__hero">
        <div class="bo-user-edit__hero-inner">
            <div class="min-w-0">
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__back">← Liste des membres</a>
                <p class="bo-user-edit__eyebrow">Membres · Création</p>
                <h1 class="bo-user-edit__title"><?= $rosterWithoutAccounts ? 'Nouvelle fiche' : 'Nouvel utilisateur' ?></h1>
                <?php if ($rosterWithoutAccounts): ?>
                <p class="bo-user-edit__lead">
                    Bureau des effectifs : vous tenez les fiches vous-même, comme dans un tableur. Le membre n’a pas besoin de compte ni d’e-mail.
                    Créez un compte seulement pour quelqu’un qui doit se connecter (état-major, RH).
                </p>
                <?php else: ?>
                <p class="bo-user-edit__lead">
                    Aucun mot de passe n’est saisi ici : un e-mail est envoyé avec un lien sécurisé pour définir le mot de passe et activer le compte.
                    Vous pouvez aussi rattacher l’identifiant Steam pour la carte.
                </p>
                <?php endif; ?>
            </div>
            <div class="bo-user-edit__hero-actions">
                <?php if (!$rosterWithoutAccounts): ?>
                <a href="<?= htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost">Préférer une invitation</a>
                <?php endif; ?>
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

        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('nouveau'), ENT_QUOTES, 'UTF-8') ?>" id="user-admin-create-form" class="bo-user-edit__create">
            <?= \App\Core\Csrf::field() ?>
            <section class="bo-user-edit__panel" aria-labelledby="create-account-heading">
                <h2 id="create-account-heading" class="bo-user-edit__panel-title">Identité &amp; accès</h2>
                <?php if ($rosterWithoutAccounts): ?>
                <fieldset class="bo-roster-kind" data-roster-kind>
                    <legend class="bo-user-edit__panel-lead">Type de fiche</legend>
                    <label class="bo-roster-kind__opt">
                        <input type="radio" name="roster_only" value="1" checked>
                        <span><strong>Fiche sans compte</strong> (recommandé) — le membre n’a rien à faire, il ne reçoit aucun e-mail. L’e-mail est inutile.</span>
                    </label>
                    <label class="bo-roster-kind__opt">
                        <input type="radio" name="roster_only" value="0">
                        <span><strong>Compte avec e-mail</strong> — pour un membre de l’état-major qui doit se connecter et tenir les fiches avec vous.</span>
                    </label>
                </fieldset>
                <?php else: ?>
                <p class="bo-user-edit__panel-lead">Renseignez l’e-mail, le prénom et le nom du personnage, l’indicatif et les rôles à attribuer dès la création.</p>
                <?php endif; ?>
                <div class="bo-user-edit__create-fields space-y-4">
                    <?php
                    $fieldIdPrefix = '';
                    $matrixRootId = 'role-matrix-wrap';
                    require base_path('views/admin/organization/partials/user_invite_form_fields.php');
                    ?>
                </div>
            </section>

            <div class="bo-user-edit__actions-bar">
                <button type="submit" class="bo-user-edit__btn bo-user-edit__btn--dark" data-roster-submit><?= $rosterWithoutAccounts ? 'Créer la fiche' : 'Créer et envoyer l’e-mail' ?></button>
                <a href="<?= htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8') ?>" class="bo-user-edit__btn bo-user-edit__btn--ghost bo-user-edit__btn--light">Annuler</a>
            </div>
        </form>
    </div>
</div>
<?php if ($rosterWithoutAccounts): ?>
<style>
.bo-roster-kind{display:grid;gap:8px;margin:0 0 16px;border:0;padding:0}
.bo-roster-kind__opt{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid var(--ath-line, #d8ded9);border-radius:10px;font-size:13px;line-height:1.45;cursor:pointer}
.bo-roster-kind__opt:has(input:checked){border-color:var(--ath-accent, #0d9f6e);background:color-mix(in srgb, var(--ath-accent, #0d9f6e) 8%, transparent)}
.bo-roster-kind__opt input{margin-top:3px}
</style>
<script>
(function () {
    var form = document.getElementById('user-admin-create-form');
    if (!form) { return; }
    var email = form.querySelector('input[name="email"]');
    var emailRow = email ? email.parentElement : null;
    var submit = form.querySelector('[data-roster-submit]');
    function sync() {
        var noAccount = (form.querySelector('input[name="roster_only"]:checked') || {}).value === '1';
        if (email) { email.required = !noAccount; }
        if (emailRow) { emailRow.hidden = noAccount; }
        if (submit) { submit.textContent = noAccount ? 'Créer la fiche' : 'Créer et envoyer l’e-mail'; }
    }
    form.querySelectorAll('input[name="roster_only"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
<?php endif; ?>
