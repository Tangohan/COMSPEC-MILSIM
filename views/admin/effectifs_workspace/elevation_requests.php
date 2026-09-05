<?php
declare(strict_types=1);

/**
 * Tableur des demandes d’élévation — style catalogue clair.
 *
 * @var list<array<string,mixed>> $elevationRequests
 * @var bool $elevationShowAll
 * @var int $elevationPage
 * @var int $elevationPerPage
 * @var int $elevationTotal
 * @var int $elevationTotalPages
 * @var array<string,string> $elevationKindLabels
 * @var array{grades?:list,roles?:list,job_roles?:list,units?:list,clearance_levels?:array<string,string>,permissions?:list} $elevationCatalog
 * @var array{roles?:list,permissions?:list,byRole?:array} $elevationRoleMatrix
 */

use App\Services\Effectifs\ElevationApprovalService;
use App\Support\OrganizationRoleLabels;

$requests = is_array($elevationRequests ?? null) ? $elevationRequests : [];
$showAll = (bool) ($elevationShowAll ?? false);
$elevPage = (int) ($elevationPage ?? 1);
$elevTotalPages = (int) ($elevationTotalPages ?? 1);
$elevTotal = (int) ($elevationTotal ?? count($requests));
$elevPageUrl = static function (int $p) use ($showAll): string {
    $q = http_build_query(array_filter([
        'all' => $showAll ? '1' : null,
        'page' => $p > 1 ? $p : null,
    ], static fn ($v) => $v !== null));

    return effectifs_workspace_url('elevations') . ($q !== '' ? '?' . $q : '');
};
$kindLabels = is_array($elevationKindLabels ?? null) ? $elevationKindLabels : [];
$catalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$grades = is_array($catalog['grades'] ?? null) ? $catalog['grades'] : [];
$roles = is_array($catalog['roles'] ?? null) ? $catalog['roles'] : [];
$jobRoles = is_array($catalog['job_roles'] ?? null) ? $catalog['job_roles'] : [];
$units = is_array($catalog['units'] ?? null) ? $catalog['units'] : [];
$clearanceLevels = is_array($catalog['clearance_levels'] ?? null) ? $catalog['clearance_levels'] : [];
$permissions = is_array($catalog['permissions'] ?? null) ? $catalog['permissions'] : [];
$permissionLabels = [];
foreach ($permissions as $permission) {
    $permissionLabels[(int) ($permission['id'] ?? 0)] = (string) ($permission['name'] ?? $permission['slug'] ?? '');
}
$roleMatrix = is_array($elevationRoleMatrix ?? null) ? $elevationRoleMatrix : ['roles' => [], 'permissions' => [], 'byRole' => []];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$openCount = 0;
foreach ($requests as $rq) {
    if (in_array((string) ($rq['status'] ?? ''), ['pending', 'in_review'], true)) {
        $openCount++;
    }
}

$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'in_review' => 'En cours d’examen',
        'approved' => 'Acceptée',
        'rejected' => 'Refusée',
        default => $status,
    };
};
$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'eff-sheets__badge--ok',
        'rejected' => 'eff-sheets__badge--muted',
        'in_review' => 'eff-sheets__badge--watch',
        default => 'eff-sheets__badge--watch',
    };
};
$nameOf = static function (?string $display, ?string $email): string {
    $display = trim((string) $display);
    if ($display !== '') {
        return $display;
    }
    $email = trim((string) $email);

    return $email !== '' ? $email : 'Membre';
};
$gradeOptionLabel = static function (array $g): string {
    $short = trim((string) ($g['label_short'] ?? ''));
    $long = trim((string) ($g['label_long'] ?? ''));
    if ($short !== '' && $long !== '' && $short !== $long) {
        return $short . ' — ' . $long;
    }

    return $short !== '' ? $short : ($long !== '' ? $long : 'Grade');
};
$proposalSummary = static function (array $labels, array $requestedPermissionIds = []) use ($permissionLabels): string {
    $bits = [];
    if (!empty($labels['grade'])) {
        $bits[] = 'Grade « ' . $labels['grade'] . ' »';
    }
    if (!empty($labels['role'])) {
        $bits[] = 'Rôle « ' . $labels['role'] . ' »';
    }
    if (!empty($labels['job_role'])) {
        $bits[] = 'Fonction « ' . $labels['job_role'] . ' »';
    }
    if (!empty($labels['unit'])) {
        $bits[] = 'Affectation « ' . $labels['unit'] . ' »';
    }
    if (!empty($labels['clearance'])) {
        $bits[] = 'Habilitation « ' . $labels['clearance'] . ' »';
    }
    $requestedRights = [];
    foreach ($requestedPermissionIds as $permissionId) {
        if (!empty($permissionLabels[(int) $permissionId])) $requestedRights[] = $permissionLabels[(int) $permissionId];
    }
    if ($requestedRights !== []) $bits[] = 'Accès « ' . implode(' », « ', $requestedRights) . ' »';

    return $bits !== [] ? implode(' · ', $bits) : '—';
};
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Gouvernance</p>
            <h1 class="eff-catalog__title">Demandes d’élévation</h1>
            <p class="eff-catalog__lead">
                Tableur des demandes d’évolution. Ouvrez une ligne pour appliquer grade, rôle, fonction ou affectation,
                choisir si le rôle remplace ou s’ajoute, puis vérifier l’aperçu des droits avant confirmation.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">← Tableur effectifs</a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations') . ($showAll ? '' : '?all=1'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn <?= $showAll ? '' : 'eff-catalog__btn--primary' ?>">
                <?= $showAll ? 'Demandes ouvertes' : 'Tout l’historique' ?>
            </a>
        </div>
    </div>

    <div class="eff-catalog-filters" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Demandes listées</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= count($requests) ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">À traiter</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= $openCount ?></p>
        </div>
    </div>

    <?php if ($requests === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucune demande <?= $showAll ? '' : 'ouverte ' ?>pour le moment.</strong>
            Les demandes envoyées depuis le tableur des effectifs apparaîtront ici.
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des demandes d’élévation" tabindex="0">
            <table class="eff-sheets__table" id="eff-elevations-table" data-cols-storage="eff-elevations-col-widths-v1">
                <colgroup>
                    <col data-col="cible" style="width:14rem">
                    <col data-col="demandeur" style="width:12rem">
                    <col data-col="type" style="width:10rem">
                    <col data-col="proposition" style="width:22rem">
                    <col data-col="statut" style="width:9rem">
                    <col data-col="date" style="width:8rem">
                    <col data-col="actions" style="width:14rem">
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="cible">Personne concernée<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner" tabindex="0"></span></th>
                        <th data-col="demandeur">Demandée par<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner" tabindex="0"></span></th>
                        <th data-col="type">Type<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner" tabindex="0"></span></th>
                        <th data-col="proposition">Proposition<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner" tabindex="0"></span></th>
                        <th data-col="statut">Statut<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner" tabindex="0"></span></th>
                        <th data-col="date">Date<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner" tabindex="0"></span></th>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r):
                    $id = (int) ($r['id'] ?? 0);
                    $status = (string) ($r['status'] ?? 'pending');
                    $kind = (string) ($r['kind'] ?? 'general');
                    $targetName = $nameOf($r['target_display_name'] ?? null, $r['target_email'] ?? null);
                    $requesterName = $nameOf($r['requester_display_name'] ?? null, $r['requester_email'] ?? null);
                    $note = trim((string) ($r['note'] ?? ''));
                    $createdAt = (string) ($r['created_at'] ?? '');
                    $createdFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
                    $isOpen = in_array($status, ['pending', 'in_review'], true);
                    $proposalLabels = is_array($r['_proposal_labels'] ?? null) ? $r['_proposal_labels'] : [];
                    $requestedPermissionIds = is_array($r['_permission_ids'] ?? null) ? $r['_permission_ids'] : [];
                    $summary = $proposalSummary($proposalLabels, $requestedPermissionIds);
                    ?>
                    <tr data-elev-row="<?= $id ?>" class="<?= $isOpen ? '' : 'is-closed' ?>">
                        <td>
                            <span class="eff-sheets__name"><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="eff-sheets__meta"><?= htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="eff-sheets__cell-text" style="white-space:normal;max-width:22rem;display:inline-block" title="<?= htmlspecialchars($summary . ($note !== '' ? ' — ' . $note : ''), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($note !== ''): ?>
                                    <span class="eff-sheets__path-muted"> — <?= htmlspecialchars(mb_strlen($note) > 80 ? mb_substr($note, 0, 77) . '…' : $note, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="eff-sheets__badge <?= $statusBadgeClass($status) ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <span class="eff-sheets__meta"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                                <?php if ($isOpen): ?>
                                    <button type="button" class="is-primary" data-elev-open="<?= $id ?>">Traiter</button>
                                <?php else: ?>
                                    <button type="button" data-elev-open="<?= $id ?>">Détail</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0"><?= count($requests) ?> demande<?= count($requests) > 1 ? 's' : '' ?><?= $showAll ? ' (historique)' : ' ouverte' . ($openCount > 1 ? 's' : '') ?></p>
        </div>

        <?php if ($showAll && $elevTotalPages > 1): ?>
        <div class="eff-catalog-foot">
            <p style="margin:0">
                <strong style="color:#0f172a"><?= $elevTotal ?></strong>
                demande<?= $elevTotal > 1 ? 's' : '' ?> — page <?= $elevPage ?> / <?= $elevTotalPages ?>
            </p>
            <div class="eff-catalog-foot__links">
                <?php if ($elevPage > 1): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($elevPageUrl($elevPage - 1), ENT_QUOTES, 'UTF-8') ?>">Page précédente</a>
                <?php endif; ?>
                <?php if ($elevPage < $elevTotalPages): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($elevPageUrl($elevPage + 1), ENT_QUOTES, 'UTF-8') ?>">Page suivante</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php foreach ($requests as $r):
    $id = (int) ($r['id'] ?? 0);
    $status = (string) ($r['status'] ?? 'pending');
    $kind = (string) ($r['kind'] ?? 'general');
    $targetName = $nameOf($r['target_display_name'] ?? null, $r['target_email'] ?? null);
    $requesterName = $nameOf($r['requester_display_name'] ?? null, $r['requester_email'] ?? null);
    $note = trim((string) ($r['note'] ?? ''));
    $createdAt = (string) ($r['created_at'] ?? '');
    $createdFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
    $isOpen = in_array($status, ['pending', 'in_review'], true);
    $actionUrl = url('back-office/ressources/effectifs/elevations/' . $id . '/statut');
    $proposedGradeId = (int) ($r['proposed_grade_id'] ?? 0);
    $proposedRoleId = (int) ($r['proposed_role_id'] ?? 0);
    $proposedJobId = (int) ($r['proposed_job_role_id'] ?? 0);
    $proposedUnitId = (int) ($r['proposed_unit_id'] ?? 0);
    $proposedClearance = trim((string) ($r['proposed_clearance_level'] ?? ''));
    $proposedPermissionIds = is_array($r['_permission_ids'] ?? null) ? $r['_permission_ids'] : [];
    $currentRoleIds = is_array($r['_current_role_ids'] ?? null) ? $r['_current_role_ids'] : [];
    $diff = is_array($r['_permission_diff'] ?? null) ? $r['_permission_diff'] : ['gained' => [], 'lost' => [], 'unchanged_count' => 0, 'rows' => []];
    $proposalLabels = is_array($r['_proposal_labels'] ?? null) ? $r['_proposal_labels'] : [];
    $formId = 'eff-elev-form-' . $id;
    $resolutionNote = trim((string) ($r['resolution_note'] ?? ''));
    ?>
<aside class="eff-elev-panel" id="eff-elev-panel-<?= $id ?>" data-elev-panel="<?= $id ?>" hidden
       data-current-roles="<?= htmlspecialchars(json_encode(array_values(array_map('intval', $currentRoleIds))), ENT_QUOTES, 'UTF-8') ?>">
    <div class="eff-elev-panel__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker"><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></p>
            <h2 class="eff-elev-panel__title"><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="eff-elev-panel__meta">
                Demandée par <?= htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8') ?>
                · <?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?>
                · <span class="eff-sheets__badge <?= $statusBadgeClass($status) ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
            </p>
        </div>
        <button type="button" class="eff-catalog__btn" data-elev-close>Fermer</button>
    </div>

    <?php if ($note !== '' || array_filter($proposalLabels) || $proposedPermissionIds !== []): ?>
    <div class="eff-elev-panel__context">
        <?php if (array_filter($proposalLabels) || $proposedPermissionIds !== []): ?>
        <p class="eff-elev-panel__proposal">
            Proposition initiale : <?= htmlspecialchars($proposalSummary($proposalLabels, $proposedPermissionIds), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php endif; ?>
        <?php if ($note !== ''): ?>
        <p class="eff-elev-panel__note"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($isOpen): ?>
    <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="eff-elev-panel__form" id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="confirm_apply" value="0" class="eff-elev-confirm-flag">

        <div class="eff-elev-panel__grid">
            <div>
                <label for="elev-grade-<?= $id ?>">Grade à appliquer</label>
                <select id="elev-grade-<?= $id ?>" name="proposed_grade_id" class="eff-elev-select">
                    <option value="">— Ne pas modifier le grade —</option>
                    <?php foreach ($grades as $g): ?>
                        <?php $gid = (int) ($g['id'] ?? 0); if ($gid < 1) continue; ?>
                        <option value="<?= $gid ?>" <?= $proposedGradeId === $gid ? 'selected' : '' ?>><?= htmlspecialchars($gradeOptionLabel($g), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="elev-role-<?= $id ?>">Rôle à appliquer</label>
                <select id="elev-role-<?= $id ?>" name="proposed_role_id" class="eff-elev-select" data-elev-role>
                    <option value="">— Ne pas modifier le rôle —</option>
                    <?php foreach ($roles as $role): ?>
                        <?php
                        $rid = (int) ($role['id'] ?? 0);
                        if ($rid < 1) {
                            continue;
                        }
                        $rLabel = OrganizationRoleLabels::displayName($role, OrganizationRoleLabels::MODE_FR);
                        $layer = (string) ($role['role_layer'] ?? 'community');
                        $layerFr = $layer === 'intra' ? 'Opérationnel' : 'Communauté';
                        ?>
                        <option value="<?= $rid ?>" <?= $proposedRoleId === $rid ? 'selected' : '' ?>><?= htmlspecialchars($rLabel . ' (' . $layerFr . ')', ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="elev-job-<?= $id ?>">Fonction à appliquer</label>
                <select id="elev-job-<?= $id ?>" name="proposed_job_role_id" class="eff-elev-select">
                    <option value="">— Ne pas modifier la fonction —</option>
                    <?php foreach ($jobRoles as $jr): ?>
                        <?php $jid = (int) ($jr['id'] ?? 0); if ($jid < 1) continue; ?>
                        <option value="<?= $jid ?>" <?= $proposedJobId === $jid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($jr['label'] ?? $jr['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="elev-unit-<?= $id ?>">Affectation à appliquer</label>
                <select id="elev-unit-<?= $id ?>" name="proposed_unit_id" class="eff-elev-select">
                    <option value="">— Ne pas modifier l’affectation —</option>
                    <?php foreach ($units as $u): ?>
                        <?php $uid = (int) ($u['id'] ?? 0); if ($uid < 1) continue; ?>
                        <option value="<?= $uid ?>" <?= $proposedUnitId === $uid ? 'selected' : '' ?>><?= htmlspecialchars((string) ($u['assignment_path'] ?? $u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="elev-clearance-<?= $id ?>">Habilitation à appliquer</label>
                <select id="elev-clearance-<?= $id ?>" name="proposed_clearance_level" class="eff-elev-select">
                    <option value="">— Ne pas modifier l’habilitation —</option>
                    <?php foreach ($clearanceLevels as $clValue => $clLabel): ?>
                        <option value="<?= htmlspecialchars((string) $clValue, ENT_QUOTES, 'UTF-8') ?>" <?= $proposedClearance === (string) $clValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $clLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($permissions !== []): ?>
        <fieldset class="eff-elev-mode">
            <legend>Droits d’accès spécifiques à accorder</legend>
            <?php foreach ($permissions as $permission): ?>
                <?php $permissionId = (int) ($permission['id'] ?? 0); if ($permissionId < 1) continue; ?>
                <label class="eff-elev-mode__option">
                    <input type="checkbox" name="proposed_permission_ids[]" value="<?= $permissionId ?>" <?= in_array($permissionId, $proposedPermissionIds, true) ? 'checked' : '' ?>>
                    <span><strong><?= htmlspecialchars((string) ($permission['name'] ?? $permission['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><em><?= htmlspecialchars((string) ($permission['module'] ?? ''), ENT_QUOTES, 'UTF-8') ?></em></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>

        <fieldset class="eff-elev-mode" data-elev-mode-box>
            <legend>Application du rôle</legend>
            <label class="eff-elev-mode__option">
                <input type="radio" name="role_apply_mode" value="<?= ElevationApprovalService::ROLE_APPLY_REPLACE ?>" checked data-elev-mode>
                <span>
                    <strong>Remplacer le rôle</strong>
                    <em>Les rôles communauté actuels sont retirés ; seul le rôle choisi reste.</em>
                </span>
            </label>
            <label class="eff-elev-mode__option">
                <input type="radio" name="role_apply_mode" value="<?= ElevationApprovalService::ROLE_APPLY_ADD ?>" data-elev-mode>
                <span>
                    <strong>Ajouter ce rôle en plus</strong>
                    <em>Conserve les rôles et les accès actuels, puis ajoute le rôle choisi.</em>
                </span>
            </label>
        </fieldset>

        <div class="eff-elev-diff" data-elev-diff aria-live="polite">
            <div class="eff-elev-diff__head">
                <div>
                    <p class="eff-elev-diff__title">Comparaison des droits d’accès</p>
                    <p class="eff-elev-diff__lead" data-elev-diff-lead>Selon le mode choisi : droits actuels du membre par rapport à la situation après application.</p>
                </div>
                <p class="eff-elev-diff__summary">
                    <span class="eff-elev-diff__pill eff-elev-diff__pill--gain">+<span data-elev-gained-count><?= count($diff['gained'] ?? []) ?></span></span>
                    <span class="eff-elev-diff__pill eff-elev-diff__pill--loss">−<span data-elev-lost-count><?= count($diff['lost'] ?? []) ?></span></span>
                    <span class="eff-elev-diff__pill"><span data-elev-unchanged><?= (int) ($diff['unchanged_count'] ?? 0) ?></span> inchangé(s)</span>
                </p>
            </div>
            <div class="eff-sheets eff-elev-diff__sheet" role="region" aria-label="Comparaison des droits">
                <table class="eff-sheets__table eff-elev-diff__table">
                    <thead>
                        <tr>
                            <th>Droit d’accès</th>
                            <th>Actuel</th>
                            <th>Après</th>
                        </tr>
                    </thead>
                    <tbody data-elev-diff-rows>
                        <?php
                        $rows = is_array($diff['rows'] ?? null) ? $diff['rows'] : [];
                        if ($rows === []):
                        ?>
                        <tr><td colspan="3" class="eff-elev-diff__empty-row">Aucun droit concerné pour ce choix.</td></tr>
                        <?php else:
                            foreach ($rows as $p):
                                $change = (string) ($p['change'] ?? 'same');
                        ?>
                        <tr class="eff-elev-diff__row--<?= htmlspecialchars($change, ENT_QUOTES, 'UTF-8') ?>">
                            <td><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($p['before']) ? 'Oui' : 'Non' ?></td>
                            <td><?= !empty($p['after']) ? 'Oui' : 'Non' ?></td>
                        </tr>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="eff-elev-panel__note-field">
            <label for="elev-note-<?= $id ?>">Note de traitement (optionnel)</label>
            <input type="text" id="elev-note-<?= $id ?>" name="resolution_note" maxlength="500" placeholder="Visible par le demandeur et la personne concernée" class="eff-elev-select">
        </div>

        <div class="eff-elev-panel__actions">
            <?php if ($status !== 'in_review'): ?>
            <button type="submit" name="status" value="in_review" class="eff-catalog__btn">Marquer en cours</button>
            <?php endif; ?>
            <button type="button" class="eff-catalog__btn eff-catalog__btn--primary" data-elev-open-confirm>Accepter et appliquer…</button>
            <button type="submit" name="status" value="rejected" class="eff-catalog__btn eff-elev-btn--danger">Refuser</button>
        </div>
    </form>
    <?php else: ?>
    <p class="eff-elev-panel__closed">
        Demande déjà traitée<?= $resolutionNote !== '' ? ' — ' . htmlspecialchars($resolutionNote, ENT_QUOTES, 'UTF-8') : '' ?>.
    </p>
    <?php endif; ?>
</aside>
<?php endforeach; ?>

<dialog id="eff-elev-confirm-dialog" class="eff-elev-dialog">
    <form method="dialog" class="eff-elev-dialog__inner">
        <h3 class="eff-elev-dialog__title">Confirmer l’élévation</h3>
        <p class="eff-elev-dialog__lead">Les changements suivants seront appliqués immédiatement au compte du membre.</p>
        <div id="eff-elev-confirm-body" class="eff-elev-dialog__body"></div>
        <div class="eff-elev-dialog__actions">
            <button type="submit" value="cancel" class="eff-catalog__btn">Annuler</button>
            <button type="button" id="eff-elev-confirm-apply" class="eff-catalog__btn eff-catalog__btn--primary">Confirmer et appliquer</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var matrix = <?= json_encode($roleMatrix, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var byRole = matrix.byRole || {};
    var permissions = matrix.permissions || [];
    var permById = {};
    permissions.forEach(function (p) {
        permById[String(p.id)] = p;
    });

    function rolePermIds(roleIds) {
        var map = {};
        (roleIds || []).forEach(function (rid) {
            var bag = byRole[String(rid)] || byRole[rid] || {};
            Object.keys(bag).forEach(function (pid) {
                if (bag[pid]) map[pid] = true;
            });
        });
        return map;
    }

    function afterRoleIds(beforeIds, afterRoleId, mode) {
        var current = (beforeIds || []).map(function (x) { return parseInt(x, 10); }).filter(function (x) { return x > 0; });
        if (!afterRoleId) return current;
        if (mode === 'add') {
            if (current.indexOf(afterRoleId) === -1) current = current.concat([afterRoleId]);
            return current;
        }
        return [afterRoleId];
    }

    function diffFor(beforeIds, afterRoleId, mode) {
        var before = rolePermIds(beforeIds);
        var after = rolePermIds(afterRoleIds(beforeIds, afterRoleId, mode || 'replace'));
        var gained = [];
        var lost = [];
        var rows = [];
        var unchanged = 0;
        var allIds = {};
        Object.keys(before).forEach(function (k) { allIds[k] = true; });
        Object.keys(after).forEach(function (k) { allIds[k] = true; });
        Object.keys(allIds).forEach(function (pid) {
            var had = !!before[pid];
            var will = !!after[pid];
            var row = permById[pid];
            if (!row) return;
            var change = 'same';
            if (had && will) unchanged++;
            else if (!had && will) { gained.push(row); change = 'gained'; }
            else if (had && !will) { lost.push(row); change = 'lost'; }
            rows.push({
                id: row.id,
                name: row.name || '',
                before: had,
                after: will,
                change: change
            });
        });
        rows.sort(function (a, b) { return String(a.name || '').localeCompare(String(b.name || ''), 'fr'); });
        return { gained: gained, lost: lost, unchanged: unchanged, rows: rows };
    }

    function selectedMode(panel) {
        var checked = panel.querySelector('input[data-elev-mode]:checked');
        return checked ? checked.value : 'replace';
    }

    function refreshPanel(panel) {
        var select = panel.querySelector('[data-elev-role]');
        var diffBox = panel.querySelector('[data-elev-diff]');
        if (!select || !diffBox) return;
        var current;
        try {
            current = JSON.parse(panel.getAttribute('data-current-roles') || '[]');
        } catch (e) {
            current = [];
        }
        var afterId = parseInt(select.value, 10) || 0;
        var mode = selectedMode(panel);
        var d = diffFor(current, afterId || null, mode);
        var lead = diffBox.querySelector('[data-elev-diff-lead]');
        if (lead) {
            lead.textContent = !afterId
                ? 'Aucun changement de rôle : aperçu inchangé.'
                : (mode === 'add'
                    ? 'Mode « Ajouter ce rôle en plus » : les accès actuels sont conservés ; seuls de nouveaux accès peuvent apparaître.'
                    : 'Mode « Remplacer le rôle » : le catalogue du rôle choisi remplace les rôles actuels.');
        }
        diffBox.querySelector('[data-elev-gained-count]').textContent = String(d.gained.length);
        diffBox.querySelector('[data-elev-lost-count]').textContent = String(d.lost.length);
        diffBox.querySelector('[data-elev-unchanged]').textContent = String(d.unchanged);
        var tbody = diffBox.querySelector('[data-elev-diff-rows]');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!d.rows.length) {
            var emptyTr = document.createElement('tr');
            emptyTr.innerHTML = '<td colspan="3" class="eff-elev-diff__empty-row">Aucun droit concerné pour ce choix.</td>';
            tbody.appendChild(emptyTr);
            return;
        }
        d.rows.forEach(function (p) {
            var tr = document.createElement('tr');
            tr.className = 'eff-elev-diff__row--' + p.change;
            tr.innerHTML = '<td></td><td></td><td></td>';
            tr.children[0].textContent = p.name || '';
            tr.children[1].textContent = p.before ? 'Oui' : 'Non';
            tr.children[2].textContent = p.after ? 'Oui' : 'Non';
            tbody.appendChild(tr);
        });
    }

    function closeAllPanels() {
        document.querySelectorAll('[data-elev-panel]').forEach(function (p) {
            p.hidden = true;
        });
        document.querySelectorAll('[data-elev-row]').forEach(function (row) {
            row.classList.remove('is-active');
        });
    }

    function openPanel(id) {
        closeAllPanels();
        var panel = document.getElementById('eff-elev-panel-' + id);
        var row = document.querySelector('[data-elev-row="' + id + '"]');
        if (!panel) return;
        panel.hidden = false;
        if (row) row.classList.add('is-active');
        refreshPanel(panel);
        try { panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
    }

    document.querySelectorAll('[data-elev-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPanel(btn.getAttribute('data-elev-open'));
        });
    });
    document.querySelectorAll('[data-elev-close]').forEach(function (btn) {
        btn.addEventListener('click', closeAllPanels);
    });

    document.querySelectorAll('[data-elev-panel]').forEach(function (panel) {
        var select = panel.querySelector('[data-elev-role]');
        if (select) {
            select.addEventListener('change', function () { refreshPanel(panel); });
        }
        panel.querySelectorAll('[data-elev-mode]').forEach(function (radio) {
            radio.addEventListener('change', function () { refreshPanel(panel); });
        });
    });

    var dialog = document.getElementById('eff-elev-confirm-dialog');
    var dialogBody = document.getElementById('eff-elev-confirm-body');
    var confirmBtn = document.getElementById('eff-elev-confirm-apply');
    var pendingForm = null;

    function selectedLabel(select) {
        if (!select || !select.value) return null;
        var opt = select.options[select.selectedIndex];
        return opt ? opt.textContent.trim() : null;
    }

    function modeLabel(form) {
        var checked = form.querySelector('input[name="role_apply_mode"]:checked');
        if (!checked) return 'Remplacer le rôle';
        return checked.value === 'add' ? 'Ajouter ce rôle en plus' : 'Remplacer le rôle';
    }

    document.querySelectorAll('[data-elev-open-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form');
            if (!form || !dialog) return;
            pendingForm = form;
            var grade = selectedLabel(form.querySelector('[name="proposed_grade_id"]'));
            var role = selectedLabel(form.querySelector('[name="proposed_role_id"]'));
            var job = selectedLabel(form.querySelector('[name="proposed_job_role_id"]'));
            var unit = selectedLabel(form.querySelector('[name="proposed_unit_id"]'));
            var clearance = selectedLabel(form.querySelector('[name="proposed_clearance_level"]'));
            var panel = form.closest('[data-elev-panel]');
            var name = panel ? (panel.querySelector('.eff-elev-panel__title') || {}).textContent || 'ce membre' : 'ce membre';
            var lines = ['<p><strong>Membre :</strong> ' + name.replace(/</g, '&lt;') + '</p>', '<ul>'];
            if (grade) lines.push('<li>Grade → ' + grade.replace(/</g, '&lt;') + '</li>');
            if (role) lines.push('<li>Rôle → ' + role.replace(/</g, '&lt;') + ' <em>(' + modeLabel(form).replace(/</g, '&lt;') + ')</em></li>');
            if (job) lines.push('<li>Fonction → ' + job.replace(/</g, '&lt;') + '</li>');
            if (unit) lines.push('<li>Affectation → ' + unit.replace(/</g, '&lt;') + '</li>');
            if (clearance) lines.push('<li>Habilitation → ' + clearance.replace(/</g, '&lt;') + ' <em>(conditionne l’accès aux documents classifiés)</em></li>');
            if (!grade && !role && !job && !unit && !clearance) {
                lines.push('<li>Aucun changement de grade, rôle, fonction, affectation ou habilitation — seule l’acceptation sera enregistrée.</li>');
            }
            lines.push('</ul>');
            var gainedCount = form.querySelector('[data-elev-gained-count]');
            var lostCount = form.querySelector('[data-elev-lost-count]');
            if (role && gainedCount && lostCount) {
                lines.push('<p class="eff-elev-dialog__perms">Droits : +' + gainedCount.textContent + ' / −' + lostCount.textContent + '.</p>');
            }
            dialogBody.innerHTML = lines.join('');
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else dialog.setAttribute('open', 'open');
        });
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (!pendingForm) return;
            var flag = pendingForm.querySelector('.eff-elev-confirm-flag');
            if (flag) flag.value = '1';
            var statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'approved';
            pendingForm.appendChild(statusInput);
            if (dialog && typeof dialog.close === 'function') dialog.close();
            pendingForm.submit();
        });
    }

    /* Redimensionnement colonnes (même pattern que les autres tableurs effectifs) */
    var table = document.getElementById('eff-elevations-table');
    if (table) {
        var storageKey = table.getAttribute('data-cols-storage') || 'eff-elevations-col-widths-v1';
        var cols = table.querySelectorAll('colgroup col');
        try {
            var saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            cols.forEach(function (col) {
                var key = col.getAttribute('data-col');
                if (key && saved[key]) col.style.width = saved[key];
            });
        } catch (e) {}
        function saveWidths() {
            var map = {};
            cols.forEach(function (col) {
                var key = col.getAttribute('data-col');
                if (key) map[key] = col.style.width || '';
            });
            try { localStorage.setItem(storageKey, JSON.stringify(map)); } catch (e) {}
        }
        table.querySelectorAll('thead th .eff-sheets__col-resizer').forEach(function (handle) {
            handle.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                var th = handle.closest('th');
                if (!th) return;
                var colKey = th.getAttribute('data-col');
                var col = table.querySelector('colgroup col[data-col="' + colKey + '"]');
                if (!col) return;
                var startX = ev.clientX;
                var startW = col.getBoundingClientRect().width;
                document.body.classList.add('eff-sheets--resizing');
                function onMove(e2) {
                    var w = Math.max(72, startW + (e2.clientX - startX));
                    col.style.width = w + 'px';
                }
                function onUp() {
                    document.body.classList.remove('eff-sheets--resizing');
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    saveWidths();
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }
})();
</script>
