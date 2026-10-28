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
// Liste rangée par catégorie ; les postes générés par l'organigramme (ORBAT) sont regroupés à part, en dernier.
$orbatGroup = 'Postes de l’ORBAT';
$railGroups = [];
foreach ($roles as $row) {
    $group = (int) ($row['source_unit_id'] ?? 0) > 0
        ? $orbatGroup
        : (trim((string) ($row['category_name'] ?? '')) ?: 'Sans catégorie');
    $railGroups[$group][] = $row;
}
if (isset($railGroups[$orbatGroup])) {
    $orbatRows = $railGroups[$orbatGroup];
    unset($railGroups[$orbatGroup]);
    $railGroups[$orbatGroup] = $orbatRows;
}
?>
<div class="eff-roles-page">
<section class="eff-page-head">
    <p class="eff-page-kicker">Emplois du dossier</p>
    <h1 class="eff-page-title">Emplois</h1>
    <p class="eff-page-lead">
        Un emploi (ou fonction) décrit le poste tenu par un membre : radio, médecin, chef de groupe… Ce n’est pas un droit d’accès.
    </p>
    <ul class="eff-jobs-explain">
        <li><strong>Catégorie</strong> Un simple rangement par métier (Santé, Transmissions…). Elle ne change rien aux droits.</li>
        <li><strong>Référentiel militaire</strong> Environ 150 fonctions classées par domaine sont proposées directement dans la fiche membre ; dès qu’un membre en reçoit une, elle apparaît ici.</li>
        <li><strong>Postes de l’ORBAT</strong> Chaque unité créée dans l’organigramme génère un poste du même nom, rangé à part en bas de la liste.</li>
    </ul>
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
        <input type="search" class="eff-jobs-search" placeholder="Rechercher un emploi…" aria-label="Rechercher un emploi" data-filter-input>
        <div data-filter-scope>
        <?php foreach ($railGroups as $groupName => $groupRows): ?>
        <details class="eff-jobs-group" data-filter-group open>
            <summary><?= htmlspecialchars((string) $groupName, ENT_QUOTES, 'UTF-8') ?> <small><?= count($groupRows) ?></small></summary>
            <ul class="eff-access-rail__list">
                <?php foreach ($groupRows as $row):
                    $rid = (int) ($row['id'] ?? 0);
                    $isOn = $rid === $selectedId && !$creating;
                    $href = $catalogUrl . ($rid > 0 ? '?emploi=' . $rid : '');
                    $nHolders = count($holdersByRole[$rid] ?? []);
                    ?>
                <li data-filter-item data-filter-text="<?= htmlspecialchars(mb_strtolower((string) ($row['name'] ?? '') . ' ' . $groupName, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                    <a class="eff-access-rail__item<?= $isOn ? ' is-current' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                        <strong><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= $nHolders ?> personne<?= $nHolders > 1 ? 's' : '' ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endforeach; ?>
        </div>
        <p class="eff-card__hint" data-filter-empty hidden>Aucun emploi ne correspond.</p>
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
            <p class="eff-page-lead">Aucun emploi pour l’instant. Les responsables peuvent en créer ici, en attribuer depuis la fiche d’un membre, ou ajouter des unités dans l’organigramme.</p>
        <?php elseif ($selected === null): ?>
            <p class="eff-page-lead">
                Aucun emploi pour l’instant. Créez-en un à gauche, attribuez une fonction du référentiel depuis la fiche d’un membre, ou ajoutez une unité dans l’organigramme.
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
                        Description de la fonction
                        <textarea name="description" rows="3" maxlength="500" placeholder="Ex. Opérateur radio — assure les liaisons net et le suivi des fréquences."><?= htmlspecialchars((string) ($selected['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                </div>
                <p class="eff-access-editor__hint">
                    Nom + description apparaissent sur le dossier et dans la recherche portail (trouver un « radio », un « médic »…).
                    Cela n’ouvre aucun droit d’accès. Pour attribuer l’emploi, utilisez l’onglet « Qui tient quel emploi » ou la fiche du membre.
                </p>
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
<script src="<?= htmlspecialchars(asset_url('assets/js/eff-member.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
