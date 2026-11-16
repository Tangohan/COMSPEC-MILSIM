<?php
declare(strict_types=1);

use App\Services\Rbac\AccessRightsCatalog;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$roles = is_array($orgRoles ?? null) ? $orgRoles : [];
$selected = is_array($selectedAccessRole ?? null) ? $selectedAccessRole : null;
$groups = is_array($accessPermissionGroups ?? null) ? $accessPermissionGroups : [];
$checkedMap = [];
foreach (is_array($checkedPermissionIds ?? null) ? $checkedPermissionIds : [] as $cid) {
    $checkedMap[(int) $cid] = true;
}
$cards = is_array($accessRoleCards ?? null) ? $accessRoleCards : [];
$holders = is_array($accessHolders ?? null) ? $accessHolders : [];
$holdersTotal = (int) ($accessHoldersTotal ?? 0);
$compare = is_array($accessCompare ?? null) ? $accessCompare : null;
$spaces = is_array($accessSpaces ?? null) ? $accessSpaces : [];
$templates = is_array($accessTemplates ?? null) ? $accessTemplates : [];
$missingRights = is_array($accessMissingRights ?? null) ? $accessMissingRights : [];
$gaps = is_array($accessRequirementGaps ?? null) ? $accessRequirementGaps : [];
$history = is_array($accessHistory ?? null) ? $accessHistory : [];
$nameByCode = is_array($accessRightNames ?? null) ? $accessRightNames : [];
$colorPresets = is_array($accessColorPresets ?? null) ? $accessColorPresets : [];
$selectedColor = (string) ($accessSelectedColor ?? '');
$canManage = !empty($canManageRoles);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$selectedId = (int) ($selected['id'] ?? 0);
$selectedName = (string) ($selected['name'] ?? '');
$selectedKind = (string) ($selectedAccessKind ?? 'custom');
$isPreset = $selected !== null && !empty($selected['is_system']);
$isHideablePreset = $isPreset && $selectedKind === 'hideable';
$isSelectedHidden = $selected !== null && \App\Repositories\RoleRepository::isHiddenAccessRow($selected);
$selCard = $cards[$selectedId] ?? ['members' => 0, 'rights' => 0, 'scope' => 'none', 'critical' => 0, 'kind' => 'custom', 'hidden' => false, 'color' => null];
$lockedMap = [];
foreach (is_array($lockedPermissionIds ?? null) ? $lockedPermissionIds : [] as $lid) {
    $lockedMap[(int) $lid] = true;
}
$kindLabels = ['member' => 'Base', 'manager' => 'Base', 'hideable' => 'Base', 'custom' => 'Personnalisé'];
$scopeLabels = ['none' => 'Aucun droit', 'read' => 'Lecture seule', 'write' => 'Lecture et écriture', 'admin' => 'Administration'];
$roleUrl = static fn (int $id): string => effectifs_workspace_url('roles') . '?role=' . $id;
$totalRights = 0;
foreach ($groups as $g) {
    $totalRights += count($g['items']);
}
$units = [];
foreach ($holders as $p) {
    if (($p['unit'] ?? '') !== '') {
        $units[$p['unit']] = true;
    }
}
ksort($units);
$chevron = '<span class="rl-chev" aria-hidden="true"></span>';
$rolReady = !empty($rolReady);
$rolTagsReady = !empty($rolTagsReady);
$rolCards = is_array($rolCards ?? null) ? $rolCards : [];
$rolVisibleCount = (int) ($rolVisibleCount ?? count($rolCards));
$kpis = (is_array($rolKpis ?? null) ? $rolKpis : []) + ['levels' => count($roles), 'covered' => 0, 'to_complete' => 0, 'no_description' => 0, 'no_visual' => 0, 'no_qualification' => 0, 'archived' => 0];
$filters = (is_array($rolFilters ?? null) ? $rolFilters : []) + ['q' => '', 'tag' => 0, 'qualif' => '', 'archives' => false, 'incomplete' => false];
$rolTags = is_array($rolTags ?? null) ? $rolTags : [];
$rolQualifications = is_array($rolQualifications ?? null) ? $rolQualifications : [];
$rolJobs = is_array($rolJobs ?? null) ? $rolJobs : [];
$rolGradeOptions = is_array($rolGradeOptions ?? null) ? $rolGradeOptions : [];
$rolOld = is_array($rolOld ?? null) ? $rolOld : null;
$rolOpen = (string) ($rolOpen ?? '');
$selCardView = null;
foreach ($rolCards as $rc) {
    if ((int) $rc['id'] === $selectedId) {
        $selCardView = $rc;
    }
}
$selProfile = $selCardView['profile'] ?? \App\Services\Rbac\AccessRoleProfileService::emptyProfile();
?>
<div class="eff-roles-page rl rol" data-rl-page data-rol-page>
<header class="bo-band bo-band--hero bo-band--sky rol-hero" aria-labelledby="rol-title">
    <div class="bo-band__media bo-band__media--fallback" aria-hidden="true"></div>
    <div class="bo-band__glow" aria-hidden="true"></div>
    <div class="bo-band__veil" aria-hidden="true"></div>
    <div class="bo-band__body">
        <div class="bo-band__who">
            <h2 class="bo-band__title" id="rol-title">Niveaux d’accès</h2>
            <p class="bo-band__line">Un niveau regroupe des droits. Vous le nommez, vous cochez ce qu’il permet, puis vous l’attribuez à des membres. Tout le monde porte Membre.</p>
            <div class="bo-band__actions">
                <?php if ($canManage): ?>
                <button type="button" class="bo-band__btn bo-band__btn--solid" data-bo-dialog-open="rol-new">Créer un niveau</button>
                <?php endif; ?>
                <a class="bo-band__btn" href="<?= $h(effectifs_workspace_url('registre-droits')) ?>">Registre des droits</a>
                <?php if (can('atak.sse.access') || can('atak.sse.grant')): ?>
                <a class="bo-band__btn" href="<?= $h(url('atak/sse/droits')) ?>">Accès SSE</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="bo-band__side">
            <div class="rol-figs">
                <p class="bo-figure"><strong><?= (int) $kpis['levels'] ?></strong><span><?= (int) $kpis['levels'] > 1 ? 'niveaux' : 'niveau' ?></span></p>
                <p class="bo-figure"><strong><?= (int) $kpis['covered'] ?></strong><span>membres couverts</span></p>
                <?php if ($rolReady): ?>
                <p class="bo-figure<?= (int) $kpis['to_complete'] > 0 ? ' is-hot' : '' ?>"><strong><?= (int) $kpis['to_complete'] ?></strong><span>fiches à compléter</span></p>
                <?php endif; ?>
            </div>
            <?php if ($rolReady): ?>
            <p class="bo-band__meta"><span>À compléter</span> Sans description : <?= (int) $kpis['no_description'] ?> · Sans icône ni logo : <?= (int) $kpis['no_visual'] ?> · Sans qualification : <?= (int) $kpis['no_qualification'] ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if ($canManage && $missingRights !== []): ?>
<p class="rl-note rl-note--warn" role="status">Droits du catalogue pas encore créés pour votre communauté : <?= count($missingRights) ?>. Ils apparaîtront ici après la prochaine mise à jour de la base.</p>
<?php endif; ?>
<?php if (!$rolReady): ?>
<p class="rl-note rl-note--warn" role="status"><?= $h(\App\Services\Rbac\AccessRoleProfileService::NOT_READY) ?></p>
<?php endif; ?>

<section class="bo-section rol-list" id="rol-list" aria-labelledby="rol-list-title">
    <header class="bo-section__head">
        <span class="bo-section__index" aria-hidden="true">/ 01</span>
        <h2 class="bo-section__title" id="rol-list-title">Niveaux</h2>
        <div class="bo-section__aside">
            <span class="bo-chip">Niveaux <b><?= count($roles) ?></b></span>
            <span class="bo-chip">Droits disponibles <b><?= (int) $totalRights ?></b></span>
        </div>
        <p class="bo-section__desc">Ouvrez un niveau pour régler ses droits et ses membres ; sa fiche le décrit sans rien changer à ses droits.</p>
    </header>
    <?php if ($roles === []): ?>
    <p class="rl-empty">Aucun niveau d’accès n’est encore disponible dans cette communauté. Les trois modèles de départ (Membre, Ressources humaines, Gestionnaire) sont créés avec la communauté : si vous n’en voyez aucun, contactez le support.</p>
    <?php else: ?>
    <form class="rol-filters" method="get" action="<?= $h(effectifs_workspace_url('roles')) ?>" role="search" aria-label="Filtrer les niveaux" data-rol-filters>
        <?php if ($selectedId > 0): ?><input type="hidden" name="role" value="<?= $selectedId ?>"><?php endif; ?>
        <label class="rol-field rol-filters__q">
            <span class="rol-field__label">Chercher</span>
            <input type="search" name="q" value="<?= $h($filters['q']) ?>" placeholder="Nom, abrégé, étiquette" autocomplete="off" data-rol-f-q>
        </label>
        <?php if ($rolTagsReady && $rolTags !== []): ?>
        <label class="rol-field">
            <span class="rol-field__label">Étiquette</span>
            <select name="etiquette" data-rol-f-tag>
                <option value="0">Toutes</option>
                <?php foreach ($rolTags as $tag): ?>
                <option value="<?= (int) $tag['id'] ?>" <?= $filters['tag'] === (int) $tag['id'] ? 'selected' : '' ?> translate="no"><?= $h($tag['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <?php if ($rolReady): ?>
        <label class="rol-field">
            <span class="rol-field__label">Qualifications</span>
            <select name="qualif" data-rol-f-qualif>
                <option value="">Toutes</option>
                <option value="avec" <?= $filters['qualif'] === 'avec' ? 'selected' : '' ?>>Avec qualification</option>
                <option value="sans" <?= $filters['qualif'] === 'sans' ? 'selected' : '' ?>>Sans qualification</option>
            </select>
        </label>
        <label class="rol-check"><input type="checkbox" name="incomplet" value="1" <?= $filters['incomplete'] ? 'checked' : '' ?> data-rol-f-incomplete> Fiches à compléter</label>
        <label class="rol-check"><input type="checkbox" name="archives" value="1" <?= $filters['archives'] ? 'checked' : '' ?> data-rol-f-archives> Afficher les archivés (<?= (int) $kpis['archived'] ?>)</label>
        <?php endif; ?>
        <button type="submit" class="eff-btn rol-filters__go">Filtrer</button>
    </form>
    <p class="rol-count" role="status" aria-live="polite" data-rol-count><?= (int) $rolVisibleCount ?> <?= (int) $rolVisibleCount > 1 ? 'niveaux affichés' : 'niveau affiché' ?></p>
    <p hidden><span data-rol-count-one>niveau affiché</span><span data-rol-count-many>niveaux affichés</span></p>
    <ul class="rol-cards" data-rol-cards>
        <?php foreach ($rolCards as $c):
            $cp = $c['profile'];
            $rid = (int) $c['id'];
            $isCur = $rid === $selectedId;
            $done = (int) $c['completeness']['done'];
            $total = (int) $c['completeness']['total'];
            ?>
        <li class="rol-card<?= $isCur ? ' is-current' : '' ?><?= !empty($cp['archived']) ? ' is-archived' : '' ?>"<?= $c['color'] !== null ? ' style="--rol-accent:' . $h($c['color']) . '"' : '' ?>
            data-rol-card data-text="<?= $h(\App\Services\Rbac\AccessRoleProfileView::searchText($c)) ?>" data-tags="<?= $h(implode(' ', array_map(static fn (array $t): string => (string) $t['id'], $cp['tags']))) ?>"
            data-quals="<?= (int) $c['qualification_count'] ?>" data-archived="<?= !empty($cp['archived']) ? '1' : '0' ?>" data-incomplete="<?= $done < $total ? '1' : '0' ?>"<?= $c['visible'] ? '' : ' hidden' ?>>
            <article class="rol-card__in" aria-labelledby="rol-card-<?= $rid ?>">
                <div class="rol-card__head">
                    <span class="rol-mark" aria-hidden="true"><?php if ($c['logo_url'] !== null): ?><img src="<?= $h($c['logo_url']) ?>" alt="" width="44" height="44" loading="lazy"><?php elseif (!empty($cp['icon'])): ?><?= \App\Support\JobRoleIconLibrary::svg((string) $cp['icon'], 'rol-mark__svg') ?><?php else: ?><span class="rol-mark__initial"><?= $h(mb_strtoupper(mb_substr($c['name'] !== '' ? $c['name'] : '?', 0, 1))) ?></span><?php endif; ?></span>
                    <div class="rol-card__titles">
                        <h3 class="rol-card__name" id="rol-card-<?= $rid ?>"><a href="<?= $h($roleUrl($rid)) ?>#rl-detail" translate="no"><?= $h($c['name']) ?></a><?php if (!empty($cp['abbreviation'])): ?> <span class="rol-abbr" translate="no"><?= $h($cp['abbreviation']) ?></span><?php endif; ?></h3>
                        <p class="rol-card__badges">
                            <span class="rl-badge rl-badge--<?= $c['kind'] === 'custom' ? 'custom' : 'base' ?>"><?= $h($kindLabels[$c['kind']] ?? 'Personnalisé') ?></span>
                            <?php if (!empty($c['hidden'])): ?><span class="rl-badge rl-badge--hidden">Masqué</span><?php endif; ?>
                            <?php if (!empty($cp['archived'])): ?><span class="rl-badge rl-badge--hidden">Archivé</span><?php endif; ?>
                            <?php if ((int) ($cards[$rid]['critical'] ?? 0) > 0): ?><span class="rl-badge rl-badge--crit">Sensible <?= (int) $cards[$rid]['critical'] ?></span><?php endif; ?>
                        </p>
                    </div>
                    <p class="rol-card__members"><b><?= (int) $c['members'] ?></b> <?= (int) $c['members'] > 1 ? 'membres' : 'membre' ?></p>
                </div>
                <?php if ($c['description'] !== ''): ?><p class="rol-card__desc" translate="no"><?= $h($c['description']) ?></p><?php else: ?><p class="rol-card__desc"><span class="rol-muted">Sans description</span></p><?php endif; ?>
                <?php if ($cp['tags'] !== []): ?><p class="rol-card__tags"><?= \App\Support\JobRoleBadge::tags($cp['tags']) ?></p><?php endif; ?>
                <?php if ($c['qualification_labels'] !== [] || $c['job_labels'] !== [] || $c['grade_label'] !== ''): ?>
                <dl class="rol-card__facts">
                    <?php foreach (\App\Services\Rbac\AccessRoleProfileService::KINDS as $kind => $klabel): if (empty($c['qualification_labels'][$kind])) { continue; } ?>
                    <div><dt><?= $h($kind === 'required' ? 'Requises' : ($kind === 'recommended' ? 'Recommandées' : 'Délivrées')) ?></dt><dd translate="no"><?= $h(implode(', ', $c['qualification_labels'][$kind])) ?></dd></div>
                    <?php endforeach; ?>
                    <?php if ($c['job_labels'] !== []): ?><div><dt>Emplois</dt><dd translate="no"><?= $h(implode(', ', array_slice($c['job_labels'], 0, 4)) . (count($c['job_labels']) > 4 ? ' +' . (count($c['job_labels']) - 4) : '')) ?></dd></div><?php endif; ?>
                    <?php if ($c['grade_label'] !== ''): ?><div><dt>Grades</dt><dd><?= $h($c['grade_label']) ?></dd></div><?php endif; ?>
                </dl>
                <?php endif; ?>
                <div class="rol-card__foot">
                    <?php if ($rolReady): ?>
                    <div class="rol-meter" title="<?= $h($c['completeness']['missing'] !== [] ? 'À renseigner : ' . implode(', ', $c['completeness']['missing']) : 'Fiche complète') ?>">
                        <span class="rol-meter__bar" aria-hidden="true"><span class="rol-meter__fill" style="--rol-p:<?= (int) round($done * 100 / max(1, $total)) ?>%"></span></span>
                        <span class="rol-meter__txt"><?= $done ?>/<?= $total ?> champs renseignés</span>
                    </div>
                    <?php endif; ?>
                    <div class="rol-card__acts">
                        <a class="eff-btn" href="<?= $h($roleUrl($rid)) ?>#rl-detail" aria-label="Ouvrir le niveau <?= $h($c['name']) ?>"><?= $isCur ? 'Ouvert' : 'Ouvrir' ?></a>
                        <?php if ($canManage): ?>
                        <?php if ($isCur): ?>
                        <button type="button" class="eff-btn eff-btn--primary" data-bo-dialog-open="rol-edit" aria-label="Modifier la fiche de <?= $h($c['name']) ?>">Modifier la fiche</button>
                        <?php else: ?>
                        <a class="eff-btn eff-btn--primary" href="<?= $h($roleUrl($rid) . '&fiche=1#rol-edit') ?>" aria-label="Modifier la fiche de <?= $h($c['name']) ?>">Modifier la fiche</a>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="rl-empty" data-rol-nomatch<?= (int) $rolVisibleCount > 0 ? ' hidden' : '' ?>>Aucun niveau ne correspond à ces filtres. Effacez la recherche ou cochez « Afficher les archivés ».</p>
    <?php endif; ?>
</section>


<?php if ($selected !== null): ?>
<header class="bo-section__head rol-detail-head" id="rol-detail-head">
    <span class="bo-section__index" aria-hidden="true">/ 02</span>
    <h2 class="bo-section__title" translate="no"><?= $h($selectedName) ?></h2>
    <div class="bo-section__aside">
        <button type="button" class="eff-btn" data-rl-fold-all="close">Tout plier</button>
        <button type="button" class="eff-btn" data-rl-fold-all="open">Tout déplier</button>
    </div>
    <p class="bo-section__desc">Le niveau ouvert : sa fiche, ses droits, ses membres, la comparaison et l’historique.</p>
</header>
<section class="rl-panel rl-panel--detail" id="rl-detail" data-rl-fold="detail" data-default-open="1" <?= !empty($selCard['color']) ? 'style="--rl-dot:' . $h($selCard['color']) . '"' : '' ?>>
    <header class="rl-panel__head">
        <h2><button type="button" class="rl-fold" aria-expanded="true" aria-controls="rl-detail-body"><?= $chevron ?><span class="rl-dot" aria-hidden="true"></span><span><?= $h($selectedName) ?></span></button></h2>
        <span class="rl-badges">
            <span class="rl-badge rl-badge--<?= $selectedKind === 'custom' ? 'custom' : 'base' ?>"><?= $h($kindLabels[$selectedKind] ?? '') ?></span>
            <?php if ($isSelectedHidden): ?><span class="rl-badge rl-badge--hidden">Masqué</span><?php endif; ?>
            <?php if (!empty($selProfile['archived'])): ?><span class="rl-badge rl-badge--hidden">Archivé</span><?php endif; ?>
            <?php if ((int) $selCard['critical'] > 0): ?><span class="rl-badge rl-badge--crit">Sensible <?= (int) $selCard['critical'] ?></span><?php endif; ?>
        </span>
    </header>
    <div class="rl-panel__body" id="rl-detail-body">
        <p class="rl-meta">Membres : <?= (int) $selCard['members'] ?> · Droits : <?= (int) $selCard['rights'] ?> · <?= $h($scopeLabels[$selCard['scope']] ?? '') ?></p>
        <?php require __DIR__ . '/partials/access_role_profile_sheet.php'; ?>

        <?php if ($gaps !== []): ?>
        <p class="rl-note rl-note--warn" role="status">Droits cochés sans le droit qu’ils supposent : <?= count($gaps) ?> (par exemple « <?= $h($nameByCode[array_key_first($gaps)] ?? array_key_first($gaps)) ?> » suppose « <?= $h($nameByCode[$gaps[array_key_first($gaps)][0]] ?? $gaps[array_key_first($gaps)][0]) ?> »). Voir les mentions « Suppose » ci-dessous.</p>
        <?php endif; ?>

        <?php if ($canManage): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles')) ?>" class="rl-editor" id="rl-editor" data-perm-form data-rl-form
              data-preview-url="<?= $h(effectifs_workspace_url('roles/apercu')) ?>" data-csrf="<?= $h($csrf) ?>" data-role="<?= $selectedId ?>"
              data-ui-confirm-title="Confirmer ce changement de droits ?" data-ui-confirm-body="">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="role_id" value="<?= $selectedId ?>">
            <?php /* Nom, description et couleur se modifient dans la fenêtre « Modifier la fiche » : repris tels quels ici. */ ?>
            <input type="hidden" name="name" value="<?= $h($selectedName) ?>">
            <input type="hidden" name="description" value="<?= $h($selected['description'] ?? '') ?>">
            <input type="hidden" name="role_color" value="<?= $h($selectedColor) ?>">
            <?php if ($selectedKind === 'manager'): ?>
            <p class="rl-note">Les clés de la communauté (administration de l’organisation et gestion des niveaux d’accès) restent toujours cochées : les retirer verrouillerait les comptes Gestionnaire.</p>
            <?php endif; ?>
            <?php foreach ($lockedMap as $lockedId => $_): ?>
            <input type="hidden" name="permission_ids[]" value="<?= (int) $lockedId ?>">
            <?php endforeach; ?>
        <?php else: ?>
        <div class="rl-editor rl-editor--readonly" data-rl-form data-readonly="1">
            <p class="rl-note">Vous pouvez consulter ce niveau. Seules les personnes habilitées à gérer les accès peuvent le modifier.</p>
        <?php endif; ?>

            <div class="rl-toolbar" role="search">
                <label class="rl-search">
                    <span class="rl-sr">Chercher un droit</span>
                    <input type="search" id="rl-search" placeholder="Chercher un droit (ex. export, recrutement, TOC)" data-rl-search autocomplete="off">
                </label>
                <label class="rl-check"><input type="checkbox" data-rl-only-checked> Accordés</label>
                <label class="rl-check"><input type="checkbox" data-rl-only-sensitive> Sensibles</label>
                <?php if ($canManage): ?><label class="rl-check eff-access-all"><input type="checkbox" data-perm-all> Tout cocher</label><?php endif; ?>
                <span class="rl-toolbar__sp"></span>
                <button type="button" class="rl-link" data-rl-groups="close">Plier les domaines</button>
                <button type="button" class="rl-link" data-rl-groups="open">Déplier les domaines</button>
            </div>
            <p class="rl-count" role="status" aria-live="polite" data-rl-count></p>

            <?php if ($groups === []): ?>
            <p class="rl-empty">Aucun droit n’est disponible pour cette communauté. Contactez le support : le catalogue des droits n’a pas été créé.</p>
            <?php endif; ?>
            <?php foreach ($groups as $group):
                $activeInGroup = 0;
                foreach ($group['items'] as $it) {
                    if (isset($checkedMap[(int) $it['id']]) || isset($lockedMap[(int) $it['id']])) {
                        $activeInGroup++;
                    }
                }
                $gid = 'rl-g-' . $h($group['key']);
                ?>
            <section class="rl-group" data-perm-scope data-rl-group data-rl-fold="g:<?= $h($group['key']) ?>" data-default-open="<?= $activeInGroup > 0 ? '1' : '0' ?>">
                <header class="rl-group__head">
                    <h3><button type="button" class="rl-fold" aria-expanded="true" aria-controls="<?= $gid ?>"><?= $chevron ?><span><?= $h($group['label']) ?></span></button></h3>
                    <span class="rl-group__count" data-rl-group-count><?= $activeInGroup ?> / <?= count($group['items']) ?></span>
                    <?php if ($canManage): ?>
                    <label class="rl-group__all"><input type="checkbox" data-perm-group aria-label="Tout cocher dans le groupe <?= $h($group['label']) ?>"><span>Tout</span></label>
                    <?php endif; ?>
                </header>
                <div class="rl-group__body" id="<?= $gid ?>">
                    <?php if (trim((string) ($group['description'] ?? '')) !== ''): ?><p class="rl-group__desc"><?= $h($group['description']) ?></p><?php endif; ?>
                    <ul class="rl-rights">
                    <?php foreach ($group['items'] as $item):
                        $pid = (int) $item['id'];
                        $isLocked = isset($lockedMap[$pid]);
                        $isChecked = $isLocked || isset($checkedMap[$pid]);
                        $needs = array_map(static fn (string $s): string => $nameByCode[$s] ?? $s, $item['requires']);
                        ?>
                        <li class="rl-right" data-rl-right data-code="<?= $h($item['code']) ?>" data-sens="<?= $h($item['sensitivity']) ?>" data-requires="<?= $h(implode(' ', $item['requires'])) ?>" data-text="<?= $h(mb_strtolower($item['name'] . ' ' . $item['explanation'])) ?>">
                            <label>
                                <input type="checkbox" name="permission_ids[]" value="<?= $pid ?>" data-code="<?= $h($item['code']) ?>"
                                       <?= $isChecked ? 'checked' : '' ?> <?= $isLocked ? 'disabled data-perm-locked' : '' ?> <?= $canManage ? '' : 'disabled data-perm-locked' ?>>
                                <span class="rl-right__text">
                                    <strong><?= $h($item['name']) ?></strong>
                                    <?php if (!empty($item['curated']) || $needs !== []): ?><small><?php if (!empty($item['curated'])): ?><?= $h($item['explanation']) ?><?php endif; ?><?php if ($needs !== []): ?> <span class="rl-right__req">Suppose : <?= $h(implode(', ', $needs)) ?>.</span><?php endif; ?></small><?php endif; ?>
                                </span>
                            </label>
                            <span class="rl-right__badges">
                                <?php if ($isLocked): ?><span class="rl-badge rl-badge--base">Requis</span><?php endif; ?>
                                <?php if ($item['sensitivity'] === AccessRightsCatalog::SENSITIVITY_CRITICAL): ?><span class="rl-badge rl-badge--crit" title="Ce droit expose des données personnelles ou la sécurité de la communauté">Sensible</span><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </section>
            <?php endforeach; ?>
            <p class="rl-empty" hidden data-rl-nomatch>Aucun droit ne correspond à cette recherche. Effacez le texte ou les filtres pour tout revoir.</p>

        <?php if ($canManage): ?>
            <div class="rl-savebar">
                <div class="rl-impact" data-rl-impact aria-live="polite">
                    <p class="rl-hint">Cochez ou décochez des droits : l’effet sur les membres s’affiche ici avant l’enregistrement.</p>
                </div>
                <div class="rl-savebar__actions">
                    <a class="eff-btn" href="<?= $h($roleUrl($selectedId)) ?>">Annuler les changements</a>
                    <button type="submit" class="eff-btn eff-btn--primary" data-rl-save>Enregistrer ce niveau</button>
                </div>
            </div>
        </form>
        <?php else: ?>
        </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
        <div class="rl-actions">
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/nouveau')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="copy_from" value="<?= $selectedId ?>">
                <input type="hidden" name="name" value="<?= $h(mb_substr($selectedName, 0, 150) . ' (copie)') ?>">
                <input type="hidden" name="description" value="<?= $h($selected['description'] ?? '') ?>">
                <input type="hidden" name="role_color" value="<?= $h($selectedColor) ?>">
                <input type="hidden" name="copy_profile" value="1">
                <button type="submit" class="eff-btn">Dupliquer</button>
            </form>
            <button type="button" class="eff-btn eff-btn--primary" data-bo-dialog-open="rol-edit">Modifier la fiche</button>
            <?php if ($rolReady && $selectedKind !== 'member'): ?>
            <?php if (empty($selProfile['archived'])): ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/archiver')) ?>" data-ui-confirm="1" data-ui-confirm-title="Archiver ce niveau ?" data-ui-confirm-body="Il sera rangé hors de la liste des niveaux. Ses droits et ses titulaires ne changent pas ; vous pourrez le désarchiver à tout moment.">
            <?php else: ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/archiver')) ?>">
            <?php endif; ?>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <input type="hidden" name="archive" value="<?= empty($selProfile['archived']) ? '1' : '0' ?>">
                <button type="submit" class="eff-btn"><?= empty($selProfile['archived']) ? 'Archiver' : 'Désarchiver' ?></button>
            </form>
            <?php endif; ?>
            <?php if ($isPreset): ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/retablir')) ?>" data-ui-confirm="1" data-ui-confirm-title="Rétablir le modèle de départ ?" data-ui-confirm-body="Le nom, la description et les droits de ce niveau reviennent à ceux du modèle d’origine. Vos réglages actuels sont perdus (ils restent dans l’historique).">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <button type="submit" class="eff-btn">Rétablir le modèle</button>
            </form>
            <?php if ($isHideablePreset && $isSelectedHidden): ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/reafficher')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <button type="submit" class="eff-btn eff-btn--primary">Réafficher</button>
            </form>
            <?php elseif ($isHideablePreset): ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/supprimer')) ?>" data-ui-confirm="1" data-ui-confirm-title="Masquer ce niveau ?" data-ui-confirm-body="<?= $h('Il ne sera plus proposé. Personnes concernées qui reviendront au niveau Membre : ' . (int) $selCard['members'] . '. Vous pourrez le réafficher à tout moment.') ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <button type="submit" class="eff-btn eff-btn--danger">Masquer</button>
            </form>
            <?php else: ?>
            <p class="rl-hint"><?= $selectedKind === 'member'
                ? 'Membre ne peut pas être supprimé : c’est le socle que tout le monde porte.'
                : 'Gestionnaire ne peut pas être supprimé : il tient les clés de la communauté.' ?></p>
            <?php endif; ?>
            <?php else: ?>
            <?php if ((int) $selCard['members'] > 0): ?>
            <button type="button" class="eff-btn eff-btn--danger" disabled aria-describedby="rol-del-why">Supprimer</button>
            <p class="rl-hint" id="rol-del-why">Suppression possible seulement sans titulaire : déplacez d’abord les <?= (int) $selCard['members'] ?> membres vers un autre niveau (bloc « Membres de ce niveau »), ou archivez-le.</p>
            <?php else: ?>
            <form method="post" action="<?= $h(effectifs_workspace_url('roles/supprimer')) ?>" data-ui-confirm="1" data-ui-confirm-title="Supprimer ce niveau ?" data-ui-confirm-body="Il n’a aucun titulaire et sera supprimé définitivement, avec sa fiche. Cette action ne peut pas être annulée.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="role_id" value="<?= $selectedId ?>">
                <button type="submit" class="eff-btn eff-btn--danger">Supprimer</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="rl-panel" id="rl-members" data-rl-fold="members" data-default-open="<?= $holdersTotal > 0 ? '1' : '0' ?>">
    <header class="rl-panel__head">
        <h2><button type="button" class="rl-fold" aria-expanded="true" aria-controls="rl-members-body"><?= $chevron ?><span>Membres de ce niveau</span></button></h2>
        <span class="rl-panel__meta">Personnes : <?= (int) $holdersTotal ?></span>
    </header>
    <div class="rl-panel__body" id="rl-members-body">
    <?php if ($holders === []): ?>
        <p class="rl-empty">Personne ne porte ce niveau pour l’instant. Attribuez-le depuis la fiche d’un membre (bloc Accès), depuis le registre des droits, ou copiez-y des membres d’un autre niveau depuis la page de ce niveau.</p>
    <?php else: ?>
        <div class="rl-toolbar" role="search" data-rl-members>
            <label class="rl-search"><span class="rl-sr">Chercher un membre</span><input type="search" placeholder="Chercher un nom, un indicatif" data-rl-m-search autocomplete="off"></label>
            <?php if ($units !== []): ?>
            <label class="rl-select"><span class="rl-sr">Unité</span>
                <select data-rl-m-unit>
                    <option value="">Toutes les unités</option>
                    <?php foreach (array_keys($units) as $u): ?><option value="<?= $h(mb_strtolower($u)) ?>"><?= $h($u) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($canManage): ?><label class="rl-check"><input type="checkbox" data-rl-m-all> Sélectionner les visibles</label><?php endif; ?>
        </div>
        <ul class="rl-people" data-rl-people>
            <?php foreach ($holders as $p): ?>
            <li class="rl-person" data-text="<?= $h(mb_strtolower($p['name'] . ' ' . $p['callsign'])) ?>" data-unit="<?= $h(mb_strtolower((string) ($p['unit'] ?? ''))) ?>">
                <?php if ($canManage): ?><input type="checkbox" name="user_ids[]" value="<?= (int) $p['id'] ?>" form="rl-transfer" aria-label="Sélectionner <?= $h($p['name']) ?>"><?php endif; ?>
                <span class="rl-person__name"><strong><?= $h($p['name']) ?></strong><?php if ($p['callsign'] !== '' && $p['callsign'] !== $p['name']): ?><small><?= $h($p['callsign']) ?></small><?php endif; ?></span>
                <span class="rl-person__unit"><?= $h($p['unit'] ?? '') ?></span>
                <a class="rl-link" href="<?= $h(effectifs_member_url((int) $p['id'])) ?>">Fiche</a>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="rl-empty" hidden data-rl-m-nomatch>Aucun membre ne correspond à ce filtre.</p>
        <?php if ($holdersTotal > count($holders)): ?>
        <p class="rl-hint">Personnes affichées : <?= count($holders) ?> sur <?= (int) $holdersTotal ?>. Le registre des droits liste tout le monde.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($canManage && count($roles) > 1): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles/membres')) ?>" class="rl-transfer" id="rl-transfer" data-rl-transfer
              data-preview-url="<?= $h(effectifs_workspace_url('roles/membres/apercu')) ?>" data-csrf="<?= $h($csrf) ?>"
              data-ui-confirm-title="Confirmer l’attribution ?" data-ui-confirm-body="">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="source_id" value="<?= $selectedId ?>">
            <input type="hidden" name="op" value="copy_sel" data-rl-op>
            <label class="rl-select">Vers le niveau
                <select name="target_id" data-rl-target>
                    <?php foreach ($roles as $row): if ((int) ($row['id'] ?? 0) === $selectedId) { continue; } ?>
                    <option value="<?= (int) ($row['id'] ?? 0) ?>"><?= $h($row['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="rl-transfer__btns">
                <button type="submit" class="eff-btn" data-op="copy_sel">Copier la sélection</button>
                <?php if ($selectedKind !== 'member'): ?><button type="submit" class="eff-btn" data-op="move_sel">Déplacer la sélection</button><?php endif; ?>
                <button type="submit" class="eff-btn" data-op="copy_all" <?= $holdersTotal === 0 ? 'disabled' : '' ?>>Copier tous les membres</button>
            </div>
            <p class="rl-hint rl-transfer__preview" data-rl-xfer-preview aria-live="polite">Cochez des membres, choisissez le niveau de destination : le nombre de personnes concernées s’affiche ici.</p>
        </form>
    <?php endif; ?>
    </div>
</section>

<section class="rl-panel" data-rl-fold="compare" data-default-open="<?= $compare !== null ? '1' : '0' ?>" id="rl-compare-panel">
    <header class="rl-panel__head">
        <h2><button type="button" class="rl-fold" aria-expanded="false" aria-controls="rl-compare-body"><?= $chevron ?><span>Comparer avec un autre niveau</span></button></h2>
    </header>
    <div class="rl-panel__body" id="rl-compare-body">
    <?php if (count($roles) < 2): ?>
        <p class="rl-empty">Il faut au moins deux niveaux pour comparer.</p>
    <?php else: ?>
        <form method="get" action="<?= $h(effectifs_workspace_url('roles')) ?>#rl-compare-panel" class="rl-inline">
            <input type="hidden" name="role" value="<?= $selectedId ?>">
            <label class="rl-select">Comparer « <?= $h($selectedName) ?> » avec
                <select name="compare">
                    <?php foreach ($roles as $row): if ((int) ($row['id'] ?? 0) === $selectedId) { continue; } ?>
                    <option value="<?= (int) ($row['id'] ?? 0) ?>" <?= $compare !== null && (int) $compare['id'] === (int) ($row['id'] ?? 0) ? 'selected' : '' ?>><?= $h($row['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="eff-btn">Comparer</button>
        </form>
    <?php endif; ?>
    <?php if ($compare !== null):
        $diff = $compare['diff']; ?>
        <div class="rl-compare">
            <div>
                <h3>Seulement dans « <?= $h($selectedName) ?> » (<?= count($diff['only_a']) ?>)</h3>
                <?php if ($diff['only_a'] === []): ?><p class="rl-empty">Aucun droit en propre.</p><?php else: ?>
                <ul><?php foreach ($diff['only_a'] as $s): ?><li><?= $h($nameByCode[$s] ?? $s) ?></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <div>
                <h3>Seulement dans « <?= $h($compare['name']) ?> » (<?= count($diff['only_b']) ?>)</h3>
                <?php if ($diff['only_b'] === []): ?><p class="rl-empty">Aucun droit en propre.</p><?php else: ?>
                <ul><?php foreach ($diff['only_b'] as $s): ?><li><?= $h($nameByCode[$s] ?? $s) ?></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <p class="rl-hint rl-span">Droits communs aux deux niveaux : <?= count($diff['common']) ?>.</p>
        </div>
    <?php endif; ?>
    </div>
</section>

<section class="rl-panel" data-rl-fold="rules" data-default-open="0">
    <header class="rl-panel__head">
        <h2><button type="button" class="rl-fold" aria-expanded="false" aria-controls="rl-rules-body"><?= $chevron ?><span>Règles et espaces ouverts</span></button></h2>
    </header>
    <div class="rl-panel__body" id="rl-rules-body">
        <dl class="rl-rules">
            <dt>Portée</dt>
            <dd><?= $h($scopeLabels[$selCard['scope']] ?? '') ?>. Pour limiter un niveau à une unité, attribuez-le « par unité » depuis le registre des droits.</dd>
            <dt>Qui peut l’attribuer</dt>
            <dd>Les personnes qui détiennent « Gérer les rôles ». Elles ne peuvent donner que des droits qu’elles détiennent elles-mêmes : pas d’escalade.</dd>
            <dt>Dernier administrateur</dt>
            <dd>Un changement qui retirerait « Gérer les rôles » à la dernière personne capable de gérer les accès est refusé.</dd>
            <dt>Durée</dt>
            <dd>Un niveau peut être donné pour une durée limitée depuis le registre des droits (date de fin, alerte avant l’échéance).</dd>
            <dt>Suppression ou masquage</dt>
            <dd>Les personnes concernées reviennent au niveau Membre. Chaque changement est écrit dans l’historique.</dd>
        </dl>
        <h3 class="rl-sub">Espaces du portail et droits demandés</h3>
        <?php if ($spaces === []): ?>
        <p class="rl-empty">Aucun espace n’est listé pour l’instant.</p>
        <?php else:
            $bySection = [];
            foreach ($spaces as $sp) {
                $bySection[$sp['section'] !== '' ? $sp['section'] : 'Autres'][] = $sp;
            } ?>
        <div class="rl-spaces">
            <?php foreach ($bySection as $section => $items): ?>
            <details>
                <summary><span><?= $h($section) ?></span> <small><?= count(array_filter($items, static fn (array $i): bool => $i['open'])) ?> / <?= count($items) ?> ouverts</small></summary>
                <ul>
                    <?php foreach ($items as $sp): ?>
                    <li class="<?= $sp['open'] ? 'is-open' : 'is-closed' ?>">
                        <span class="rl-badge <?= $sp['open'] ? 'rl-badge--on' : 'rl-badge--hidden' ?>"><?= $sp['open'] ? 'Ouvert' : 'Fermé' ?></span>
                        <span class="rl-space__label"><?= $h($sp['label']) ?></span>
                        <small>Droit demandé : <?= $h(implode(' ou ', array_map(static fn (string $s): string => $nameByCode[$s] ?? $s, $sp['any_of']))) ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="rl-panel" data-rl-fold="history" data-default-open="0">
    <header class="rl-panel__head">
        <h2><button type="button" class="rl-fold" aria-expanded="false" aria-controls="rl-history-body"><?= $chevron ?><span>Historique des changements</span></button></h2>
        <span class="rl-panel__meta"><?= count($history) ?></span>
    </header>
    <div class="rl-panel__body" id="rl-history-body">
    <?php if ($history === []): ?>
        <p class="rl-empty">Aucun changement enregistré pour ce niveau. Les prochains (droits ajoutés ou retirés, rétablissement, masquage) apparaîtront ici avec leur auteur.</p>
    <?php else: ?>
        <ol class="rl-history">
            <?php foreach ($history as $ev): ?>
            <li>
                <p><strong><?= $h($ev['what']) ?></strong> · <?= $h($ev['who']) ?> · <time><?= $h($ev['when']) ?></time></p>
                <?php if ($ev['added'] !== []): ?><p class="rl-history__add">Ajoutés : <?= $h(implode(', ', array_map(static fn (string $s): string => $nameByCode[$s] ?? $s, $ev['added']))) ?></p><?php endif; ?>
                <?php if ($ev['removed'] !== []): ?><p class="rl-history__del">Retirés : <?= $h(implode(', ', array_map(static fn (string $s): string => $nameByCode[$s] ?? $s, $ev['removed']))) ?></p><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    </div>
</section>
<?php endif; ?>
</div>
<?php if ($canManage):
    $rolMode = 'new';
    $rolCard = null;
    require __DIR__ . '/partials/access_role_profile_dialog.php';
    if ($selCardView !== null) {
        $rolMode = 'edit';
        $rolCard = $selCardView;
        require __DIR__ . '/partials/access_role_profile_dialog.php';
    }
endif; ?>
<script src="<?= $h(asset_url('assets/js/perm-check-all.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/back-office-bands.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/effectifs-roles-profile.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/effectifs-roles.js')) ?>" defer></script>
