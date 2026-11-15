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
$canManage = !empty($canManageRoles);
$csrf = (string) ($csrfToken ?? \App\Core\Csrf::token());
$selectedId = (int) ($selected['id'] ?? 0);
$selectedName = (string) ($selected['name'] ?? '');
$selectedKind = (string) ($selectedAccessKind ?? 'custom');
$isPreset = $selected !== null && !empty($selected['is_system']);
$isHideablePreset = $isPreset && $selectedKind === 'hideable';
$isSelectedHidden = $selected !== null && \App\Repositories\RoleRepository::isHiddenAccessRow($selected);
$selCard = $cards[$selectedId] ?? ['members' => 0, 'rights' => 0, 'scope' => 'none', 'critical' => 0, 'kind' => 'custom', 'hidden' => false];
$lockedMap = [];
foreach (is_array($lockedPermissionIds ?? null) ? $lockedPermissionIds : [] as $lid) {
    $lockedMap[(int) $lid] = true;
}
$kindLabels = ['member' => 'Socle de tout le monde', 'manager' => 'Clés de la communauté', 'hideable' => 'Modèle de départ', 'custom' => 'Personnalisé'];
$scopeLabels = ['none' => 'Aucun droit', 'read' => 'Lecture seule', 'write' => 'Lecture et écriture', 'admin' => 'Administration'];
$roleUrl = static fn (int $id, string $extra = ''): string => effectifs_workspace_url('roles') . '?role=' . $id . $extra;
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);
$totalRights = 0;
foreach ($groups as $g) {
    $totalRights += count($g['items']);
}
?>
<div class="eff-roles-page rl">
<section class="eff-page-head">
    <h1 class="eff-page-title">Niveaux d’accès</h1>
    <p class="eff-page-lead">
        Un niveau d’accès regroupe des droits : vous le nommez, vous cochez ce qu’il permet, puis vous l’attribuez à des membres.
        Tout le monde porte Membre ; les autres niveaux s’y ajoutent. Les emplois du dossier (radio, médic…) décrivent une fonction, pas des droits.
    </p>
    <div class="eff-pg__actions">
        <a class="eff-btn" href="<?= $h(effectifs_workspace_url('registre-droits')) ?>">Registre des droits : qui, depuis quand, jusqu’à quand</a>
        <?php if (can('atak.sse.access') || can('atak.sse.grant')): ?>
        <a class="eff-btn" href="<?= $h(url('atak/sse/droits')) ?>">Accès SSE section par section</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($canManage && $missingRights !== []): ?>
<p class="rl-note rl-note--warn" role="status">
    Droits du catalogue pas encore créés pour votre communauté : <?= count($missingRights) ?>. Ils apparaîtront ici après la prochaine mise à jour de la base.
</p>
<?php endif; ?>

<section class="rl-panel" aria-labelledby="rl-overview-title">
    <div class="rl-panel__head">
        <h2 id="rl-overview-title">Vue d’ensemble</h2>
        <p>Niveaux : <?= count($roles) ?> · Droits disponibles : <?= (int) $totalRights ?></p>
    </div>
    <?php if ($roles === []): ?>
    <p class="rl-empty">Aucun niveau d’accès n’est encore disponible dans cette communauté. Les trois modèles de départ (Membre, Ressources humaines, Gestionnaire) sont créés avec la communauté : si vous n’en voyez aucun, contactez le support.</p>
    <?php else: ?>
    <table class="rl-table">
        <thead>
            <tr><th scope="col">Niveau</th><th scope="col">Type</th><th scope="col">État</th><th scope="col">Membres</th><th scope="col">Droits</th><th scope="col">Portée</th><th scope="col"><span class="rl-sr">Ouvrir</span></th></tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $row):
            $rid = (int) ($row['id'] ?? 0);
            $c = $cards[$rid] ?? ['kind' => 'custom', 'hidden' => false, 'members' => 0, 'rights' => 0, 'scope' => 'none', 'critical' => 0];
            ?>
            <tr class="<?= $rid === $selectedId ? 'is-current' : '' ?><?= !empty($c['hidden']) ? ' is-hidden-role' : '' ?>">
                <th scope="row" data-label="Niveau">
                    <a href="<?= $h($roleUrl($rid)) ?>#rl-detail"><?= $h($row['name'] ?? '') ?></a>
                    <?php if (trim((string) ($row['description'] ?? '')) !== ''): ?><small><?= $h($row['description']) ?></small><?php endif; ?>
                </th>
                <td data-label="Type"><?= $h($kindLabels[$c['kind']] ?? 'Personnalisé') ?></td>
                <td data-label="État"><span class="rl-chip <?= !empty($c['hidden']) ? 'rl-chip--off' : 'rl-chip--on' ?>"><?= !empty($c['hidden']) ? 'Masqué' : 'Visible' ?></span></td>
                <td data-label="Membres"><?= (int) $c['members'] ?></td>
                <td data-label="Droits"><?= (int) $c['rights'] ?><?php if ((int) $c['critical'] > 0): ?> <span class="rl-chip rl-chip--crit"><?= (int) $c['critical'] ?> sensible<?= (int) $c['critical'] > 1 ? 's' : '' ?></span><?php endif; ?></td>
                <td data-label="Portée"><?= $h($scopeLabels[$c['scope']] ?? '') ?></td>
                <td class="rl-table__go"><a class="eff-btn" href="<?= $h($roleUrl($rid)) ?>#rl-detail" aria-label="Ouvrir le niveau <?= $h($row['name'] ?? '') ?>"><?= $rid === $selectedId ? 'Ouvert' : 'Ouvrir' ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <details class="rl-create">
        <summary>Créer un niveau</summary>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles/nouveau')) ?>" class="rl-create__form">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <label>Nom du niveau
                <input type="text" name="name" maxlength="160" placeholder="Ex. Recrutement" aria-describedby="rl-create-hint">
            </label>
            <label>Partir d’un modèle prêt à l’emploi
                <select name="template">
                    <option value="">Aucun : copier un niveau existant</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?= $h($tpl['key']) ?>"><?= $h($tpl['name']) ?> (Droits : <?= count($tpl['rights']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Ou copier les droits de
                <select name="copy_from">
                    <?php foreach ($roles as $row): ?>
                    <option value="<?= (int) ($row['id'] ?? 0) ?>"><?= $h($row['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p id="rl-create-hint" class="rl-hint">Si vous choisissez un modèle, il remplace la copie. Vous ne pouvez créer que des niveaux dont vous détenez déjà tous les droits. Le nom peut rester vide avec un modèle : il prend le nom du modèle.</p>
            <ul class="rl-templates">
                <?php foreach ($templates as $tpl): ?>
                <li><strong><?= $h($tpl['name']) ?></strong> <?= $h($tpl['description']) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="submit" class="eff-btn eff-btn--primary">Créer le niveau</button>
        </form>
    </details>
    <?php endif; ?>
</section>

<?php if ($selected !== null): ?>
<section class="rl-panel" id="rl-detail" aria-labelledby="rl-detail-title">
    <div class="rl-panel__head">
        <h2 id="rl-detail-title"><?= $h($selectedName) ?></h2>
        <p>
            <?= $h($kindLabels[$selectedKind] ?? '') ?> ·
            <?= $isSelectedHidden ? 'masqué' : 'visible' ?> ·
            Membres : <?= (int) $selCard['members'] ?> ·
            <?= $h($scopeLabels[$selCard['scope']] ?? '') ?>
        </p>
    </div>

    <?php if ($gaps !== []): ?>
    <p class="rl-note rl-note--warn" role="status">
        Droits cochés sans le droit qu’ils supposent : <?= count($gaps) ?> (par exemple « <?= $h($nameByCode[array_key_first($gaps)] ?? array_key_first($gaps)) ?> » suppose « <?= $h($nameByCode[$gaps[array_key_first($gaps)][0]] ?? $gaps[array_key_first($gaps)][0]) ?> »). Voir les mentions « Suppose » ci-dessous.
    </p>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <form method="post" action="<?= $h(effectifs_workspace_url('roles')) ?>" class="rl-editor" id="rl-editor" data-perm-form data-rl-form
          data-preview-url="<?= $h(effectifs_workspace_url('roles/apercu')) ?>" data-csrf="<?= $h($csrf) ?>" data-role="<?= $selectedId ?>"
          data-ui-confirm-title="Confirmer ce changement de droits ?" data-ui-confirm-body="">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
        <input type="hidden" name="role_id" value="<?= $selectedId ?>">
        <div class="rl-identity">
            <label>Nom du niveau
                <input type="text" name="name" required maxlength="160" value="<?= $h($selectedName) ?>">
            </label>
            <label>À quoi il sert
                <textarea name="description" rows="2" maxlength="500" placeholder="Ex. Accès lecture aux documents pour les nouveaux intégrés."><?= $h($selected['description'] ?? '') ?></textarea>
            </label>
        </div>
        <?php if ($selectedKind === 'manager'): ?>
        <p class="rl-note">Les clés de la communauté (administration de l’organisation et gestion des niveaux d’accès) restent toujours cochées : les retirer verrouillerait les comptes Gestionnaire.</p>
        <?php endif; ?>
        <?php foreach ($lockedMap as $lockedId => $_): ?>
        <input type="hidden" name="permission_ids[]" value="<?= (int) $lockedId ?>">
        <?php endforeach; ?>

        <div class="rl-toolbar" role="search">
            <label class="rl-search">
                <span class="rl-sr">Chercher un droit</span>
                <input type="search" id="rl-search" placeholder="Chercher un droit (ex. export, recrutement, TOC)" data-rl-search autocomplete="off">
            </label>
            <label class="rl-check"><input type="checkbox" data-rl-only-checked> Seulement les droits accordés</label>
            <label class="rl-check"><input type="checkbox" data-rl-only-sensitive> Seulement les droits sensibles</label>
            <label class="eff-access-all rl-check"><input type="checkbox" data-perm-all> Tout cocher</label>
        </div>
        <p class="rl-count" role="status" aria-live="polite" data-rl-count></p>
    <?php else: ?>
    <div class="rl-editor rl-editor--readonly" data-rl-form data-readonly="1">
        <p class="rl-note">Vous pouvez consulter ce niveau. Seules les personnes habilitées à gérer les accès peuvent le modifier.</p>
        <div class="rl-toolbar" role="search">
            <label class="rl-search"><span class="rl-sr">Chercher un droit</span><input type="search" placeholder="Chercher un droit" data-rl-search autocomplete="off"></label>
            <label class="rl-check"><input type="checkbox" data-rl-only-checked> Seulement les droits accordés</label>
            <label class="rl-check"><input type="checkbox" data-rl-only-sensitive> Seulement les droits sensibles</label>
        </div>
        <p class="rl-count" role="status" aria-live="polite" data-rl-count></p>
    <?php endif; ?>

        <?php if ($groups === []): ?>
        <p class="rl-empty">Aucun droit n’est disponible pour cette communauté. Contactez le support : le catalogue des droits n’a pas été créé.</p>
        <?php endif; ?>
        <?php foreach ($groups as $group): ?>
        <section class="rl-group" data-perm-scope data-rl-group>
            <header class="rl-group__head">
                <h3>
                    <?php if ($canManage): ?>
                    <label class="rl-group__all"><input type="checkbox" data-perm-group aria-label="Tout cocher dans le groupe <?= $h($group['label']) ?>"><span><?= $h($group['label']) ?></span></label>
                    <?php else: ?>
                    <?= $h($group['label']) ?>
                    <?php endif; ?>
                </h3>
                <span class="rl-group__count" data-rl-group-count></span>
            </header>
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
                            <strong><?= $h($item['name']) ?><?= $isLocked ? ' (toujours requis)' : '' ?></strong>
                            <small><?= $h($item['explanation']) ?></small>
                            <?php if ($needs !== []): ?><small class="rl-right__req">Suppose : <?= $h(implode(', ', $needs)) ?></small><?php endif; ?>
                        </span>
                    </label>
                    <?php if ($item['sensitivity'] === AccessRightsCatalog::SENSITIVITY_CRITICAL): ?>
                    <span class="rl-chip rl-chip--crit" title="Ce droit expose des données personnelles ou la sécurité de la communauté">Sensible</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>
        <p class="rl-empty" hidden data-rl-nomatch>Aucun droit ne correspond à cette recherche. Effacez le texte ou les filtres pour tout revoir.</p>

    <?php if ($canManage): ?>
        <div class="rl-savebar">
            <div class="rl-impact" data-rl-impact aria-live="polite">
                <p class="rl-hint">Cochez ou décochez des droits : l’effet sur les membres s’affiche ici avant l’enregistrement.</p>
            </div>
            <div class="rl-savebar__actions">
                <button type="submit" class="eff-btn eff-btn--primary" data-rl-save>Enregistrer ce niveau</button>
                <a class="eff-btn" href="<?= $h($roleUrl($selectedId)) ?>">Annuler les changements</a>
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
            <button type="submit" class="eff-btn">Dupliquer ce niveau</button>
        </form>
        <?php if ($isPreset): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles/retablir')) ?>" data-ui-confirm="1" data-ui-confirm-title="Rétablir le modèle de départ ?" data-ui-confirm-body="Le nom, la description et les droits de ce niveau reviennent à ceux du modèle d’origine. Vos réglages actuels sont perdus (ils restent dans l’historique).">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="role_id" value="<?= $selectedId ?>">
            <button type="submit" class="eff-btn">Rétablir le modèle de départ</button>
        </form>
        <?php if ($isHideablePreset && $isSelectedHidden): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles/reafficher')) ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="role_id" value="<?= $selectedId ?>">
            <button type="submit" class="eff-btn eff-btn--primary">Réafficher ce niveau</button>
        </form>
        <?php elseif ($isHideablePreset): ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles/supprimer')) ?>" data-ui-confirm="1" data-ui-confirm-title="Masquer ce niveau ?" data-ui-confirm-body="<?= $h('Il ne sera plus proposé. Personnes concernées qui reviendront au niveau Membre : ' . (int) $selCard['members'] . '. Vous pourrez le réafficher à tout moment.') ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="role_id" value="<?= $selectedId ?>">
            <button type="submit" class="eff-btn eff-btn--danger">Masquer ce niveau</button>
        </form>
        <?php else: ?>
        <p class="rl-hint">
            <?= $selectedKind === 'member'
                ? 'Ce niveau ne peut pas être supprimé : c’est le socle que tout le monde porte. Renommez-le ou réduisez ses droits si besoin.'
                : 'Ce niveau ne peut pas être supprimé : il tient les clés de la communauté (paramètres, niveaux d’accès). Renommez-le si besoin.' ?>
        </p>
        <?php endif; ?>
        <?php else: ?>
        <form method="post" action="<?= $h(effectifs_workspace_url('roles/supprimer')) ?>" data-ui-confirm="1" data-ui-confirm-title="Supprimer ce niveau ?" data-ui-confirm-body="<?= $h('Il sera supprimé définitivement. Personnes concernées qui reviendront au niveau Membre : ' . (int) $selCard['members'] . '. Cette action ne peut pas être annulée.') ?>">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
            <input type="hidden" name="role_id" value="<?= $selectedId ?>">
            <button type="submit" class="eff-btn eff-btn--danger">Supprimer ce niveau</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<section class="rl-panel" aria-labelledby="rl-holders-title">
    <div class="rl-panel__head">
        <h2 id="rl-holders-title">Qui a ce niveau</h2>
        <p>Personnes : <?= (int) $holdersTotal ?></p>
    </div>
    <?php if ($holders === []): ?>
    <p class="rl-empty">Personne ne porte ce niveau pour l’instant. Attribuez-le depuis la fiche d’un membre (bloc Accès) ou depuis le registre des droits.</p>
    <?php else: ?>
    <ul class="rl-holders">
        <?php foreach ($holders as $p): ?>
        <li><a href="<?= $h(effectifs_member_url((int) $p['id'])) ?>"><?= $h($p['name']) ?><?php if ($p['callsign'] !== '' && $p['callsign'] !== $p['name']): ?> <small><?= $h($p['callsign']) ?></small><?php endif; ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($holdersTotal > count($holders)): ?>
    <p class="rl-hint">Personnes affichées : <?= count($holders) ?> sur <?= (int) $holdersTotal ?>. Le registre des droits liste tout le monde.</p>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="rl-panel" aria-labelledby="rl-compare-title">
    <div class="rl-panel__head">
        <h2 id="rl-compare-title">Comparer avec un autre niveau</h2>
    </div>
    <?php if (count($roles) < 2): ?>
    <p class="rl-empty">Il faut au moins deux niveaux pour comparer.</p>
    <?php else: ?>
    <form method="get" action="<?= $h(effectifs_workspace_url('roles')) ?>#rl-compare" class="rl-compare-form">
        <input type="hidden" name="role" value="<?= $selectedId ?>">
        <label>Comparer <strong><?= $h($selectedName) ?></strong> avec
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
    <div class="rl-compare" id="rl-compare">
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
        <p class="rl-hint">Droits communs aux deux niveaux : <?= count($diff['common']) ?>.</p>
    </div>
    <?php endif; ?>
</section>

<section class="rl-panel" aria-labelledby="rl-rules-title">
    <div class="rl-panel__head">
        <h2 id="rl-rules-title">Règles qui s’appliquent à ce niveau</h2>
    </div>
    <dl class="rl-rules">
        <dt>Portée</dt>
        <dd><?= $h($scopeLabels[$selCard['scope']] ?? '') ?>. « Lecture seule » : aucun droit de modifier. Pour limiter un niveau à une unité, attribuez-le « par unité » depuis le registre des droits.</dd>
        <dt>Qui peut l’attribuer</dt>
        <dd>Les personnes qui détiennent « Gérer les rôles » (ou la clé de la communauté). Elles ne peuvent donner ou ajouter que des droits qu’elles détiennent elles-mêmes : pas d’escalade.</dd>
        <dt>Dernier administrateur</dt>
        <dd>Un changement qui retirerait « Gérer les rôles » à la dernière personne capable de gérer les accès est refusé. Membre et Gestionnaire ne peuvent pas être supprimés.</dd>
        <dt>Durée</dt>
        <dd>Un niveau peut être donné pour une durée limitée depuis le registre des droits (date de fin, alerte avant l’échéance). Sans date de fin, il reste jusqu’à retrait.</dd>
        <dt>Suppression ou masquage</dt>
        <dd>Les personnes concernées reviennent au niveau Membre. Chaque changement de droits est écrit dans l’historique ci-dessous.</dd>
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
            <summary><?= $h($section) ?> <small><?= count(array_filter($items, static fn (array $i): bool => $i['open'])) ?> / <?= count($items) ?> ouverts</small></summary>
            <ul>
                <?php foreach ($items as $sp): ?>
                <li class="<?= $sp['open'] ? 'is-open' : 'is-closed' ?>">
                    <span class="rl-chip <?= $sp['open'] ? 'rl-chip--on' : 'rl-chip--off' ?>"><?= $sp['open'] ? 'Ouvert' : 'Fermé' ?></span>
                    <span><?= $h($sp['label']) ?></span>
                    <small>Droit demandé : <?= $h(implode(' ou ', array_map(static fn (string $s): string => $nameByCode[$s] ?? $s, $sp['any_of']))) ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="rl-panel" aria-labelledby="rl-history-title">
    <div class="rl-panel__head">
        <h2 id="rl-history-title">Historique des changements</h2>
    </div>
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
</section>
<?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/perm-check-all.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/effectifs-roles.js')) ?>" defer></script>
