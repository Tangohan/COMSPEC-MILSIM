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
// member | manager | hideable | custom (calculé par le contrôleur)
$selectedKind = (string) ($selectedAccessKind ?? 'custom');
$isHideablePreset = $isPreset && $selectedKind === 'hideable';
$isSelectedHidden = $selected !== null && \App\Repositories\RoleRepository::isHiddenAccessRow($selected);
$lockedIds = is_array($lockedPermissionIds ?? null) ? $lockedPermissionIds : [];
$lockedMap = [];
foreach ($lockedIds as $lid) {
    $lockedMap[(int) $lid] = true;
}
?>
<div class="eff-roles-page">
<section class="eff-page-head">
    <p class="eff-page-kicker">Accès à la communauté</p>
    <h1 class="eff-page-title">Niveaux d’accès</h1>
    <p class="eff-page-lead">
        Comme un rôle Discord : vous choisissez le nom, vous cochez ce que ce niveau a le droit de faire, puis vous l’attribuez à une personne.
        Membre, Ressources humaines et Gestionnaire sont les trois modèles de départ. Vous pouvez les renommer, changer leurs droits, et en créer d’autres.
        Ressources humaines peut être masqué. Membre (le socle que tout le monde porte) et Gestionnaire (les clés de la communauté) restent toujours.
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
                $rowHidden = \App\Repositories\RoleRepository::isHiddenAccessRow($row);
                ?>
            <li>
                <a class="eff-access-rail__item<?= $isOn ? ' is-current' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                    <strong><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= $rowHidden ? 'Masqué' : $nMembers . ' personne' . ($nMembers > 1 ? 's' : '') ?></span>
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
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles'), ENT_QUOTES, 'UTF-8') ?>" class="eff-access-editor" data-perm-form>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <div class="eff-access-editor__identity">
                    <label>
                        Nom du niveau
                        <input type="text" name="name" required maxlength="160" value="<?= htmlspecialchars($selectedName, ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>
                        À quoi il sert
                        <textarea name="description" rows="3" maxlength="500" placeholder="Ex. Accès lecture documents pour les nouveaux intégrés."><?= htmlspecialchars((string) ($selected['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                </div>
                <p class="eff-access-editor__hint">
                    La description aide les responsables à comprendre le niveau. Cochez uniquement ce que les titulaires peuvent faire.
                    Les emplois du dossier (radio, médic…) restent à part — ils ne donnent aucun droit ici.
                    <?php if ($selectedKind === 'manager'): ?>
                    Les clés de la communauté (administration de l’organisation et gestion des niveaux d’accès) restent toujours cochées : les retirer verrouillerait les comptes Gestionnaire.
                    <?php endif; ?>
                </p>
                <?php foreach ($lockedMap as $lockedId => $_): ?>
                <input type="hidden" name="permission_ids[]" value="<?= (int) $lockedId ?>">
                <?php endforeach; ?>
                <label class="eff-access-all">
                    <input type="checkbox" data-perm-all>
                    <span>Tout cocher (puis décochez ce qui ne convient pas)</span>
                </label>
                <?php foreach ($groups as $group): ?>
                <section class="eff-access-group" data-perm-scope>
                    <h2>
                        <label class="eff-access-group__all">
                            <input type="checkbox" data-perm-group aria-label="Tout cocher dans ce groupe">
                            <span><?= htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    </h2>
                    <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?>
                    <p class="eff-access-group__desc"><?= htmlspecialchars((string) $group['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <ul>
                        <?php foreach ($group['items'] as $item):
                            $pid = (int) ($item['id'] ?? 0);
                            $isLocked = isset($lockedMap[$pid]);
                            $isChecked = $isLocked || isset($checkedMap[$pid]);
                            ?>
                        <li>
                            <label>
                                <input type="checkbox"
                                       name="permission_ids[]"
                                       value="<?= $pid ?>"
                                       <?= $isChecked ? 'checked' : '' ?>
                                       <?= $isLocked ? 'disabled data-perm-locked' : '' ?>>
                                <span><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= $isLocked ? ' (toujours requis)' : '' ?></span>
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
                <?php if ($isHideablePreset && $isSelectedHidden): ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles/reafficher'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                    <button type="submit" class="eff-btn eff-btn--primary">Réafficher ce niveau</button>
                </form>
                <?php elseif ($isHideablePreset): ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('roles/supprimer'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Masquer ce niveau ? Il ne sera plus proposé et les personnes concernées reviendront à Membre.');">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                    <button type="submit" class="eff-btn">Masquer ce niveau</button>
                </form>
                <?php else: ?>
                <p class="eff-access-editor__hint">
                    <?= $selectedKind === 'member'
                        ? 'Ce niveau ne peut pas être supprimé : c’est le socle que tout le monde porte. Renommez-le ou réduisez ses droits si besoin.'
                        : 'Ce niveau ne peut pas être supprimé : il tient les clés de la communauté (paramètres, niveaux d’accès). Renommez-le si besoin.' ?>
                </p>
                <?php endif; ?>
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
<script src="<?= htmlspecialchars(asset_url('assets/js/perm-check-all.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
