<?php
declare(strict_types=1);

$roles = is_array($orgRoles ?? null) ? $orgRoles : [];
$selected = is_array($selectedAccessRole ?? null) ? $selectedAccessRole : null;
$groups = is_array($accessPermissionGroups ?? null) ? $accessPermissionGroups : [];
$checked = is_array($checkedPermissionIds ?? null) ? $checkedPermissionIds : [];
$checkedMap = [];
foreach ($checked as $cid) {
    $checkedMap[(int) $cid] = true;
}
$permCounts = is_array($accessPermissionCounts ?? null) ? $accessPermissionCounts : [];
$memberCounts = is_array($accessMemberCounts ?? null) ? $accessMemberCounts : [];
$canManage = !empty($canManageRoles);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$selectedId = (int) ($selected['id'] ?? 0);
$isPreset = $selected !== null && !empty($selected['is_system']);
$selectedName = (string) ($selected['name'] ?? '');
?>
<div class="eff-roles-page">
<section class="eff-page-head">
    <p class="eff-page-kicker">Accès à la communauté</p>
    <h1 class="eff-page-title">Niveaux d’accès</h1>
    <p class="eff-page-lead">
        Comme un rôle Discord : vous choisissez le nom, vous cochez ce que ce niveau a le droit de faire, puis vous l’attribuez à une personne.
        Membre, Ressources humaines et Gestionnaire restent les trois modèles de départ. Vous pouvez les corriger, et en créer d’autres.
        Les emplois du dossier (radio, médic…) décrivent la fonction, pas les droits.
    </p>
</section>

<div class="eff-access-layout">
    <aside class="eff-access-rail" aria-label="Liste des niveaux">
        <ul class="eff-access-rail__list">
            <?php foreach ($roles as $row):
                $rid = (int) ($row['id'] ?? 0);
                $isOn = $rid === $selectedId;
                $href = effectifs_workspace_url('roles') . ($rid > 0 ? '?role=' . $rid : '');
                $nMembers = (int) ($memberCounts[$rid] ?? 0);
                ?>
            <li>
                <a class="eff-access-rail__item<?= $isOn ? ' is-current' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= $nMembers ?> personne<?= $nMembers > 1 ? 's' : '' ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($canManage): ?>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles/nouveau'), ENT_QUOTES, 'UTF-8') ?>" class="eff-access-create">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <p class="eff-access-create__title">Nouveau niveau</p>
            <label>
                Nom
                <input type="text" name="name" required maxlength="160" placeholder="Ex. Recrutement">
            </label>
            <label>
                Partir du modèle
                <select name="copy_from">
                    <?php foreach ($roles as $row): ?>
                    <option value="<?= (int) ($row['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="eff-btn eff-btn--primary">Créer</button>
        </form>
        <?php endif; ?>
    </aside>

    <div class="eff-access-main">
        <?php if ($selected === null): ?>
            <p class="eff-page-lead">Aucun niveau d’accès n’est encore disponible.</p>
        <?php else: ?>
            <?php if ($canManage): ?>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>" class="eff-access-editor">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <div class="eff-access-editor__identity">
                    <label>
                        Nom du niveau
                        <input type="text" name="name" required maxlength="160" value="<?= htmlspecialchars($selectedName, ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        À quoi il sert
                        <textarea name="description" rows="2" maxlength="500"><?= htmlspecialchars((string) ($selected['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                </div>
                <p class="eff-access-editor__hint">Cochez uniquement ce que les titulaires de ce niveau peuvent faire. L’administration du site n’apparaît jamais ici.</p>
                <?php foreach ($groups as $group): ?>
                <section class="eff-access-group">
                    <h2><?= htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                    <ul>
                        <?php foreach ($group['items'] as $item):
                            $pid = (int) ($item['id'] ?? 0);
                            ?>
                        <li>
                            <label>
                                <input type="checkbox" name="permission_ids[]" value="<?= $pid ?>" <?= isset($checkedMap[$pid]) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endforeach; ?>
                <div class="eff-access-editor__save">
                    <button type="submit" class="eff-btn eff-btn--primary">Enregistrer ce niveau</button>
                    <a class="eff-act" href="<?= htmlspecialchars(effectifs_workspace_url() . ($selectedId > 0 ? '?role_id=' . $selectedId : ''), ENT_QUOTES, 'UTF-8') ?>">Voir les titulaires</a>
                </div>
            </form>
            <div class="eff-access-side-actions">
                <?php if ($isPreset): ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles/retablir'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Rétablir le modèle de départ pour ce niveau ?');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                    <button type="submit" class="eff-btn">Rétablir le modèle de départ</button>
                </form>
                <?php else: ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles/supprimer'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Supprimer ce niveau ? Les personnes concernées reviendront à Membre.');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                    <button type="submit" class="eff-btn">Supprimer ce niveau</button>
                </form>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <article class="eff-role-card">
                <h2 class="eff-role-card__name"><?= htmlspecialchars($selectedName, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="eff-role-card__desc"><?= htmlspecialchars((string) ($selected['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="eff-page-lead">Vous pouvez consulter les niveaux. La modification est réservée aux responsables habilités.</p>
            </article>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</div>
