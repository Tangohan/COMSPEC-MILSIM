<?php
declare(strict_types=1);

$jobsVue = (string) ($jobsVue ?? 'catalogue');
$canManage = !empty($canManageAssignments);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$roles = is_array($jobRoles ?? null) ? $jobRoles : [];
$categories = is_array($jobCategories ?? null) ? $jobCategories : [];
$selected = is_array($selectedJobRole ?? null) ? $selectedJobRole : null;
$creating = !empty($creatingJobRole);
$holdersByRole = is_array($jobHoldersByRole ?? null) ? $jobHoldersByRole : [];
$selectedId = (int) ($selected['id'] ?? 0);
$catalogUrl = effectifs_workspace_url('fonctions');
$assignUrl = $catalogUrl . '?vue=attributions';
?>
<div class="eff-roles-page">
<section class="eff-page-head">
    <p class="eff-page-kicker">Emplois du dossier</p>
    <h1 class="eff-page-title">Emplois</h1>
    <p class="eff-page-lead">
        Un emploi décrit la fonction sur le dossier (radio, médic, chef d’équipe…). Ce ne sont pas des droits d’accès.
        Créer une unité dans l’organigramme (ORBAT) prépare déjà un emploi du même nom. Vous pouvez en ajouter d’autres ici, puis les attribuer aux membres.
    </p>
</section>

<div class="eff-jobs-tabs" role="tablist" aria-label="Vues des emplois">
    <a class="eff-jobs-tabs__item<?= $jobsVue !== 'attributions' ? ' is-current' : '' ?>" href="<?= htmlspecialchars($catalogUrl, ENT_QUOTES, 'UTF-8') ?>">Catalogue</a>
    <a class="eff-jobs-tabs__item<?= $jobsVue === 'attributions' ? ' is-current' : '' ?>" href="<?= htmlspecialchars($assignUrl, ENT_QUOTES, 'UTF-8') ?>">Qui tient quel emploi</a>
</div>

<?php if ($jobsVue === 'attributions'): ?>
    <?php require base_path('views/admin/organization/personnel_job_roles/assignments.php'); ?>
<?php else: ?>
<div class="eff-access-layout">
    <aside class="eff-access-rail" aria-label="Liste des emplois">
        <ul class="eff-access-rail__list">
            <?php foreach ($roles as $row):
                $rid = (int) ($row['id'] ?? 0);
                $isOn = $rid === $selectedId && !$creating;
                $href = $catalogUrl . ($rid > 0 ? '?emploi=' . $rid : '');
                $nHolders = count($holdersByRole[$rid] ?? []);
                $cat = trim((string) ($row['category_name'] ?? ''));
                ?>
            <li>
                <a class="eff-access-rail__item<?= $isOn ? ' is-current' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= $cat !== '' ? htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . ' · ' : '' ?><?= $nHolders ?> personne<?= $nHolders > 1 ? 's' : '' ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($canManage): ?>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('fonctions/nouveau'), ENT_QUOTES, 'UTF-8') ?>" class="eff-access-create">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-access-create__title">Nouvel emploi</p>
            <label>
                Nom
                <input type="text" name="name" required maxlength="120" placeholder="Ex. Radio">
            </label>
            <label>
                Catégorie
                <select name="category_id">
                    <?php if ($categories === []): ?>
                    <option value="0">Organisation (créée automatiquement)</option>
                    <?php else: ?>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>
            <button type="submit" class="eff-btn eff-btn--primary">Créer</button>
        </form>
        <details class="eff-jobs-category">
            <summary>Nouvelle catégorie</summary>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('fonctions/categorie'), ENT_QUOTES, 'UTF-8') ?>" class="eff-access-create">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label>
                    Nom
                    <input type="text" name="category_name" required maxlength="120" placeholder="Ex. Santé">
                </label>
                <?php if ($categories !== []): ?>
                <label>
                    Rattachée à (facultatif)
                    <select name="parent_id">
                        <option value="">Aucune</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <button type="submit" class="eff-btn">Créer la catégorie</button>
            </form>
        </details>
        <?php endif; ?>
    </aside>

    <div class="eff-access-main">
        <?php if ($roles === [] && !$canManage): ?>
            <p class="eff-page-lead">Aucun emploi pour l’instant. Les responsables peuvent en créer ici, ou depuis l’organigramme.</p>
        <?php elseif ($selected === null): ?>
            <p class="eff-page-lead">
                Aucun emploi pour l’instant. Créez-en un à gauche, ou ajoutez une unité dans l’organigramme : un emploi du même nom est proposé.
            </p>
        <?php elseif ($canManage): ?>
            <form method="post" action="<?= htmlspecialchars($catalogUrl, ENT_QUOTES, 'UTF-8') ?>" class="eff-access-editor">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= $selectedId ?>">
                <div class="eff-access-editor__identity">
                    <label>
                        Nom de l’emploi
                        <input type="text" name="name" required maxlength="120" value="<?= htmlspecialchars((string) ($selected['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        Catégorie
                        <select name="category_id" required>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= (int) ($selected['category_id'] ?? 0) === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($c['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Description
                        <textarea name="description" rows="2" maxlength="500"><?= htmlspecialchars((string) ($selected['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                </div>
                <p class="eff-access-editor__hint">Ce libellé apparaît sur le dossier. Il n’ouvre aucun droit sur le portail. Pour l’attribuer, ouvrez l’onglet « Qui tient quel emploi » ou la fiche du membre.</p>
                <?php
                $holders = $holdersByRole[$selectedId] ?? [];
                ?>
                <?php if ($holders !== []): ?>
                <section class="eff-jobs-holders">
                    <h2>Personnes qui tiennent cet emploi</h2>
                    <ul>
                        <?php foreach ($holders as $holder):
                            $uid = (int) ($holder['user_id'] ?? 0);
                            $uname = trim((string) ($holder['display_name'] ?? ''));
                            ?>
                        <li>
                            <?php if ($uid > 0): ?>
                            <a href="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $uid), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($uname !== '' ? $uname : 'Membre', ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                            <?= htmlspecialchars($uname !== '' ? $uname : 'Membre', ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
                <div class="eff-access-editor__save">
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer cet emploi</button>
                    <a class="eff-act" href="<?= htmlspecialchars($assignUrl . '&job_role_id=' . $selectedId, ENT_QUOTES, 'UTF-8') ?>">Voir les titulaires</a>
                </div>
            </form>
            <?php if (empty($selected['is_system'])): ?>
            <div class="eff-access-side-actions">
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('fonctions/supprimer'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Retirer cet emploi du catalogue ? Les dossiers concernés n’afficheront plus ce libellé.');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= $selectedId ?>">
                    <button type="submit" class="eff-btn">Retirer cet emploi</button>
                </form>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <article class="eff-role-card">
                <h2 class="eff-role-card__name"><?= htmlspecialchars((string) ($selected['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="eff-role-card__desc"><?= htmlspecialchars((string) ($selected['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="eff-page-lead">Vous pouvez consulter les emplois. La modification est réservée aux responsables habilités.</p>
            </article>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>
