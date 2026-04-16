<?php
declare(strict_types=1);

$structureHubOpen = $structureHubOpen ?? '';
$groupParents = $groupParents ?? [];
$teamParents = $teamParents ?? [];
$usersForCommander = $usersForCommander ?? [];
$roles = $roles ?? [];
$roleMatrix = $roleMatrix ?? ['roles' => [], 'permissions' => [], 'byRole' => []];
$grades = $grades ?? [];
$gradeCategories = $gradeCategories ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? \App\Support\OrganizationRoleLabels::MODE_FR;

$okOpen = in_array($structureHubOpen, ['membre', 'groupe', 'equipe'], true);
$hubFlashError = \App\Core\Session::getFlash('error');
$hubFlashSuccess = \App\Core\Session::getFlash('success');
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-[1800px] mx-auto px-4 sm:px-6 py-6 space-y-4">
        <?php if ($hubFlashError): ?>
            <?php $flash_variant = 'error'; $flash_message = (string) $hubFlashError; $flash_margin_class = 'mb-0'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>
        <?php if ($hubFlashSuccess): ?>
            <?php $flash_variant = 'success'; $flash_message = (string) $hubFlashSuccess; $flash_margin_class = 'mb-0'; require base_path('views/partials/flash_message.php'); ?>
        <?php endif; ?>

        <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mb-1">Back-office communauté</p>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Structure & recrutement</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                    Organigramme interactif : créez des regroupements, des équipes ou invitez un membre depuis la barre d’actions ou par clic droit sur une carte du type correspondant.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="hub-btn-membre" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white shadow hover:bg-slate-800">Inviter un membre</button>
                <button type="button" id="hub-btn-groupe" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50">Nouveau regroupement</button>
                <button type="button" id="hub-btn-equipe" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50">Nouvelle équipe</button>
                <a href="<?= url('back-office/organisation-effectifs') ?>" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Organisation des effectifs</a>
            </div>
        </header>

        <script>
        (function () {
            var initialOpen = <?= json_encode($okOpen ? $structureHubOpen : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
            function showDlg(id) {
                var el = document.getElementById(id);
                if (el && typeof el.showModal === 'function') el.showModal();
            }
            window.orbatHubOpenRecruitmentModal = function (kind, parentUnitId) {
                parentUnitId = parentUnitId || 0;
                if (kind === 'groupe') {
                    var sg = document.getElementById('hub_grp_parent_id');
                    if (sg) {
                        var v = parentUnitId > 0 ? String(parentUnitId) : '';
                        if (v && !sg.querySelector('option[value="' + v + '"]')) {
                            sg.value = '';
                        } else {
                            sg.value = v;
                        }
                    }
                    showDlg('hub-dlg-groupe');
                } else if (kind === 'equipe') {
                    var st = document.getElementById('hub_team_parent_id');
                    if (st) {
                        var v2 = parentUnitId > 0 ? String(parentUnitId) : '';
                        if (v2 && !st.querySelector('option[value="' + v2 + '"]')) {
                            st.value = '';
                        } else {
                            st.value = v2;
                        }
                    }
                    showDlg('hub-dlg-equipe');
                } else if (kind === 'membre') {
                    showDlg('hub-dlg-membre');
                }
            };
            document.getElementById('hub-btn-membre') && document.getElementById('hub-btn-membre').addEventListener('click', function () {
                window.orbatHubOpenRecruitmentModal('membre', 0);
            });
            document.getElementById('hub-btn-groupe') && document.getElementById('hub-btn-groupe').addEventListener('click', function () {
                window.orbatHubOpenRecruitmentModal('groupe', 0);
            });
            document.getElementById('hub-btn-equipe') && document.getElementById('hub-btn-equipe').addEventListener('click', function () {
                window.orbatHubOpenRecruitmentModal('equipe', 0);
            });
            document.addEventListener('DOMContentLoaded', function () {
                if (initialOpen === 'membre') window.orbatHubOpenRecruitmentModal('membre', 0);
                if (initialOpen === 'groupe') window.orbatHubOpenRecruitmentModal('groupe', 0);
                if (initialOpen === 'equipe') window.orbatHubOpenRecruitmentModal('equipe', 0);
            });
        })();
        </script>

        <?php
        require base_path('views/partials/orbat/orbat_canvas.php');
        ?>

        <dialog id="hub-dlg-membre" class="max-w-4xl w-[calc(100%-1.5rem)] sm:w-[calc(100%-2rem)] rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-900/40">
            <form method="post" action="<?= url('back-office/users/store') ?>" class="flex max-h-[90vh] flex-col">
                <div class="border-b border-slate-100 px-5 py-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">Inviter un membre</h2>
                        <p class="text-xs text-slate-500 mt-1">Un e-mail permettra à la personne de définir son mot de passe.</p>
                    </div>
                    <button type="button" value="cancel" class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-hub-close="hub-dlg-membre" aria-label="Fermer">✕</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <?php
                    $fieldIdPrefix = 'hub-user-';
                    $matrixRootId = 'hub-role-matrix-wrap';
                    require base_path('views/admin/organization/partials/user_invite_form_fields.php');
                    ?>
                </div>
                <div class="border-t border-slate-100 px-5 py-3 flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-hub-close="hub-dlg-membre">Annuler</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Créer et envoyer l’e-mail</button>
                </div>
            </form>
        </dialog>

        <dialog id="hub-dlg-groupe" class="max-w-lg w-[calc(100%-1.5rem)] rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-900/40">
            <form method="post" action="<?= url('back-office/groups/store') ?>" class="flex max-h-[90vh] flex-col">
                <div class="border-b border-slate-100 px-5 py-4 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-900">Nouveau regroupement</h2>
                    <button type="button" class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-hub-close="hub-dlg-groupe" aria-label="Fermer">✕</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-3">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="hub_grp_name" class="block text-sm font-medium text-slate-700">Nom *</label>
                        <input type="text" id="hub_grp_name" name="name" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                    <div>
                        <label for="hub_grp_slug" class="block text-sm font-medium text-slate-700">Adresse courte dans l’URL</label>
                        <input type="text" id="hub_grp_slug" name="slug" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                    <div>
                        <label for="hub_grp_code" class="block text-sm font-medium text-slate-700">Code</label>
                        <input type="text" id="hub_grp_code" name="code" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                    <div>
                        <label for="hub_grp_parent_id" class="block text-sm font-medium text-slate-700">Rattaché sous</label>
                        <select id="hub_grp_parent_id" name="parent_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">— Racine —</option>
                            <?php foreach ($groupParents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) ($p['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="hub_grp_commander" class="block text-sm font-medium text-slate-700">Responsable</label>
                        <select id="hub_grp_commander" name="commander_user_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">—</option>
                            <?php foreach ($usersForCommander as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="hub_grp_order" class="block text-sm font-medium text-slate-700">Ordre d’affichage</label>
                        <input type="number" id="hub_grp_order" name="display_order" value="0" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                </div>
                <div class="border-t border-slate-100 px-5 py-3 flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-hub-close="hub-dlg-groupe">Annuler</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Créer</button>
                </div>
            </form>
        </dialog>

        <dialog id="hub-dlg-equipe" class="max-w-lg w-[calc(100%-1.5rem)] rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-900/40">
            <form method="post" action="<?= url('back-office/teams/store') ?>" class="flex max-h-[90vh] flex-col">
                <div class="border-b border-slate-100 px-5 py-4 flex items-start justify-between gap-3">
                    <h2 class="text-lg font-black text-slate-900">Nouvelle équipe</h2>
                    <button type="button" class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-hub-close="hub-dlg-equipe" aria-label="Fermer">✕</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 space-y-3">
                    <?= \App\Core\Csrf::field() ?>
                    <div>
                        <label for="hub_team_name" class="block text-sm font-medium text-slate-700">Nom *</label>
                        <input type="text" id="hub_team_name" name="name" required class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                    <div>
                        <label for="hub_team_slug" class="block text-sm font-medium text-slate-700">Adresse courte dans l’URL</label>
                        <input type="text" id="hub_team_slug" name="slug" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                    <div>
                        <label for="hub_team_code" class="block text-sm font-medium text-slate-700">Code</label>
                        <input type="text" id="hub_team_code" name="code" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                    <div>
                        <label for="hub_team_parent_id" class="block text-sm font-medium text-slate-700">Rattaché sous</label>
                        <select id="hub_team_parent_id" name="parent_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">— Racine —</option>
                            <?php foreach ($teamParents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars((string) ($p['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="hub_team_commander" class="block text-sm font-medium text-slate-700">Responsable</label>
                        <select id="hub_team_commander" name="commander_user_id" class="<?= htmlspecialchars(bo_select_class('mt-1'), ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">—</option>
                            <?php foreach ($usersForCommander as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars((string) ($u['display_name'] ?? $u['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="hub_team_order" class="block text-sm font-medium text-slate-700">Ordre d’affichage</label>
                        <input type="number" id="hub_team_order" name="display_order" value="0" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 shadow-sm">
                    </div>
                </div>
                <div class="border-t border-slate-100 px-5 py-3 flex flex-wrap justify-end gap-2">
                    <button type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-hub-close="hub-dlg-equipe">Annuler</button>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Créer</button>
                </div>
            </form>
        </dialog>

        <script>
        document.querySelectorAll('[data-hub-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-hub-close');
                var d = id ? document.getElementById(id) : null;
                if (d && typeof d.close === 'function') d.close();
            });
        });
        </script>
    </div>
</div>
