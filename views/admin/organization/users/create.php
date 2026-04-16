<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
?>
<div class="max-w-7xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Nouvel utilisateur</h1>
    <p class="text-sm text-slate-600 mb-6">Aucun mot de passe n’est saisi ici : un <strong>e-mail</strong> est envoyé à la personne avec un lien sécurisé pour définir son mot de passe et activer le compte (comme une invitation de la communauté).</p>

    <form method="post" action="<?= url('back-office/users/store') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <?php
        $fieldIdPrefix = '';
        $matrixRootId = 'role-matrix-wrap';
        require base_path('views/admin/organization/partials/user_invite_form_fields.php');
        ?>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Créer et envoyer l’e-mail</button>
            <a href="<?= url('back-office/users') ?>" class="px-4 py-2 text-slate-600 text-sm hover:underline">Annuler</a>
        </div>
    </form>
</div>
