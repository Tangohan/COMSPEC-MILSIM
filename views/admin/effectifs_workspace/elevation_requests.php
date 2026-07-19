<?php
declare(strict_types=1);

/**
 * Traitement des demandes d’élévation — style catalogue clair.
 *
 * @var list<array<string,mixed>> $elevationRequests
 * @var bool $elevationShowAll
 * @var array<string,string> $elevationKindLabels
 * @var array{grades?:list,roles?:list,job_roles?:list,units?:list} $elevationCatalog
 * @var array{roles?:list,permissions?:list,byRole?:array} $elevationRoleMatrix
 */

use App\Support\OrganizationRoleLabels;

$requests = is_array($elevationRequests ?? null) ? $elevationRequests : [];
$showAll = (bool) ($elevationShowAll ?? false);
$kindLabels = is_array($elevationKindLabels ?? null) ? $elevationKindLabels : [];
$catalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$grades = is_array($catalog['grades'] ?? null) ? $catalog['grades'] : [];
$roles = is_array($catalog['roles'] ?? null) ? $catalog['roles'] : [];
$jobRoles = is_array($catalog['job_roles'] ?? null) ? $catalog['job_roles'] : [];
$units = is_array($catalog['units'] ?? null) ? $catalog['units'] : [];
$roleMatrix = is_array($elevationRoleMatrix ?? null) ? $elevationRoleMatrix : ['roles' => [], 'permissions' => [], 'byRole' => []];
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());

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
        'approved' => 'eff-elev-badge--ok',
        'rejected' => 'eff-elev-badge--off',
        'in_review' => 'eff-elev-badge--warn',
        default => 'eff-elev-badge--warn',
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
?>
<div class="eff-catalog eff-elev-page">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Gouvernance</p>
            <h1 class="eff-catalog__title">Demandes d’élévation</h1>
            <p class="eff-catalog__lead">
                Traitez les demandes d’évolution de grade, rôle, fonction ou affectation.
                Avant d’accepter, choisissez les changements à appliquer et consultez l’aperçu des droits issus du catalogue du rôle.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">← Tableur</a>
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations') . ($showAll ? '' : '?all=1'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn <?= $showAll ? '' : 'eff-catalog__btn--primary' ?>">
                <?= $showAll ? 'Demandes ouvertes' : 'Tout l’historique' ?>
            </a>
        </div>
    </div>

    <?php if ($requests === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucune demande <?= $showAll ? '' : 'ouverte ' ?>pour le moment.</strong>
            Les demandes envoyées depuis le tableur apparaîtront ici pour traitement.
        </div>
    <?php else: ?>
        <div class="eff-elev-list">
            <?php foreach ($requests as $r): ?>
                <?php
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
                $currentRoleIds = is_array($r['_current_role_ids'] ?? null) ? $r['_current_role_ids'] : [];
                $diff = is_array($r['_permission_diff'] ?? null) ? $r['_permission_diff'] : ['gained' => [], 'lost' => [], 'unchanged_count' => 0];
                $proposalLabels = is_array($r['_proposal_labels'] ?? null) ? $r['_proposal_labels'] : [];
                $formId = 'eff-elev-form-' . $id;
                ?>
            <article class="eff-elev-card" data-elev-card="<?= $id ?>" data-current-roles="<?= htmlspecialchars(json_encode(array_values(array_map('intval', $currentRoleIds))), ENT_QUOTES, 'UTF-8') ?>">
                <header class="eff-elev-card__head">
                    <div class="min-w-0">
                        <p class="eff-catalog__kicker"><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></p>
                        <h2 class="eff-elev-card__title"><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="eff-elev-card__meta">
                            Demandée par <?= htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <span class="eff-elev-badge <?= $statusBadgeClass($status) ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span>
                </header>

                <?php if ($note !== '' || array_filter($proposalLabels)): ?>
                <div class="eff-elev-card__context">
                    <?php if (array_filter($proposalLabels)): ?>
                    <p class="eff-elev-card__proposal">
                        Proposition initiale :
                        <?php
                        $bits = [];
                        if (!empty($proposalLabels['grade'])) {
                            $bits[] = 'grade « ' . $proposalLabels['grade'] . ' »';
                        }
                        if (!empty($proposalLabels['role'])) {
                            $bits[] = 'rôle « ' . $proposalLabels['role'] . ' »';
                        }
                        if (!empty($proposalLabels['job_role'])) {
                            $bits[] = 'fonction « ' . $proposalLabels['job_role'] . ' »';
                        }
                        if (!empty($proposalLabels['unit'])) {
                            $bits[] = 'affectation « ' . $proposalLabels['unit'] . ' »';
                        }
                        echo htmlspecialchars(implode(', ', $bits), ENT_QUOTES, 'UTF-8');
                        ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($note !== ''): ?>
                    <p class="eff-elev-card__note"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($isOpen): ?>
                <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="eff-elev-form" id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="confirm_apply" value="0" class="eff-elev-confirm-flag">

                    <div class="eff-elev-form__grid">
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
                            <select id="elev-role-<?= $id ?>" name="proposed_role_id" class="eff-elev-select eff-elev-role-select" data-elev-role>
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
                    </div>

                    <div class="eff-elev-diff" data-elev-diff aria-live="polite">
                        <p class="eff-elev-diff__title">Aperçu des droits d’accès</p>
                        <p class="eff-elev-diff__lead">Calculé à partir du catalogue de permissions du rôle choisi (union actuelle → rôle proposé).</p>
                        <div class="eff-elev-diff__cols">
                            <div>
                                <p class="eff-elev-diff__col-title eff-elev-diff__col-title--gain">Accès ajoutés <span data-elev-gained-count><?= count($diff['gained'] ?? []) ?></span></p>
                                <ul class="eff-elev-diff__list" data-elev-gained>
                                    <?php foreach (($diff['gained'] ?? []) as $p): ?>
                                        <li><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                    <?php if (($diff['gained'] ?? []) === []): ?>
                                        <li class="eff-elev-diff__empty">Aucun</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div>
                                <p class="eff-elev-diff__col-title eff-elev-diff__col-title--loss">Accès retirés <span data-elev-lost-count><?= count($diff['lost'] ?? []) ?></span></p>
                                <ul class="eff-elev-diff__list" data-elev-lost>
                                    <?php foreach (($diff['lost'] ?? []) as $p): ?>
                                        <li><?= htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                    <?php if (($diff['lost'] ?? []) === []): ?>
                                        <li class="eff-elev-diff__empty">Aucun</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <p class="eff-elev-diff__unchanged"><span data-elev-unchanged><?= (int) ($diff['unchanged_count'] ?? 0) ?></span> droit(s) inchangé(s)</p>
                    </div>

                    <div class="eff-elev-form__note">
                        <label for="elev-note-<?= $id ?>">Note de traitement (optionnel)</label>
                        <input type="text" id="elev-note-<?= $id ?>" name="resolution_note" maxlength="500" placeholder="Visible par le demandeur et la personne concernée" class="eff-elev-select">
                    </div>

                    <div class="eff-elev-form__actions">
                        <?php if ($status !== 'in_review'): ?>
                        <button type="submit" name="status" value="in_review" class="eff-catalog__btn">Marquer en cours</button>
                        <?php endif; ?>
                        <button type="button" class="eff-catalog__btn eff-catalog__btn--primary" data-elev-open-confirm>Accepter et appliquer…</button>
                        <button type="submit" name="status" value="rejected" class="eff-catalog__btn eff-elev-btn--danger">Refuser</button>
                    </div>
                </form>
                <?php else: ?>
                <p class="eff-elev-card__closed">Demande traitée<?= trim((string) ($r['resolution_note'] ?? '')) !== '' ? ' — ' . htmlspecialchars((string) $r['resolution_note'], ENT_QUOTES, 'UTF-8') : '' ?>.</p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

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

    function diffFor(beforeIds, afterRoleId) {
        var before = rolePermIds(beforeIds);
        var afterIds = afterRoleId ? [afterRoleId] : beforeIds;
        var after = rolePermIds(afterIds);
        var gained = [];
        var lost = [];
        var unchanged = 0;
        var allIds = {};
        Object.keys(before).forEach(function (k) { allIds[k] = true; });
        Object.keys(after).forEach(function (k) { allIds[k] = true; });
        Object.keys(allIds).forEach(function (pid) {
            var had = !!before[pid];
            var will = !!after[pid];
            var row = permById[pid];
            if (!row) return;
            if (had && will) unchanged++;
            else if (!had && will) gained.push(row);
            else if (had && !will) lost.push(row);
        });
        gained.sort(function (a, b) { return String(a.name || '').localeCompare(String(b.name || ''), 'fr'); });
        lost.sort(function (a, b) { return String(a.name || '').localeCompare(String(b.name || ''), 'fr'); });
        return { gained: gained, lost: lost, unchanged: unchanged };
    }

    function renderList(ul, rows) {
        ul.innerHTML = '';
        if (!rows.length) {
            var empty = document.createElement('li');
            empty.className = 'eff-elev-diff__empty';
            empty.textContent = 'Aucun';
            ul.appendChild(empty);
            return;
        }
        rows.forEach(function (p) {
            var li = document.createElement('li');
            li.textContent = p.name || '';
            ul.appendChild(li);
        });
    }

    function refreshCard(card) {
        var select = card.querySelector('[data-elev-role]');
        var diffBox = card.querySelector('[data-elev-diff]');
        if (!select || !diffBox) return;
        var current;
        try {
            current = JSON.parse(card.getAttribute('data-current-roles') || '[]');
        } catch (e) {
            current = [];
        }
        var afterId = parseInt(select.value, 10) || 0;
        var d = diffFor(current, afterId || null);
        diffBox.querySelector('[data-elev-gained-count]').textContent = String(d.gained.length);
        diffBox.querySelector('[data-elev-lost-count]').textContent = String(d.lost.length);
        diffBox.querySelector('[data-elev-unchanged]').textContent = String(d.unchanged);
        renderList(diffBox.querySelector('[data-elev-gained]'), d.gained);
        renderList(diffBox.querySelector('[data-elev-lost]'), d.lost);
    }

    document.querySelectorAll('[data-elev-card]').forEach(function (card) {
        var select = card.querySelector('[data-elev-role]');
        if (select) {
            select.addEventListener('change', function () { refreshCard(card); });
        }
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

    document.querySelectorAll('[data-elev-open-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form');
            if (!form || !dialog) return;
            pendingForm = form;
            var grade = selectedLabel(form.querySelector('[name="proposed_grade_id"]'));
            var role = selectedLabel(form.querySelector('[name="proposed_role_id"]'));
            var job = selectedLabel(form.querySelector('[name="proposed_job_role_id"]'));
            var unit = selectedLabel(form.querySelector('[name="proposed_unit_id"]'));
            var card = form.closest('[data-elev-card]');
            var name = card ? (card.querySelector('.eff-elev-card__title') || {}).textContent || 'ce membre' : 'ce membre';
            var lines = ['<p><strong>Membre :</strong> ' + name.replace(/</g, '&lt;') + '</p>', '<ul>'];
            if (grade) lines.push('<li>Grade → ' + grade.replace(/</g, '&lt;') + '</li>');
            if (role) lines.push('<li>Rôle → ' + role.replace(/</g, '&lt;') + ' <em>(remplace les rôles communauté actuels)</em></li>');
            if (job) lines.push('<li>Fonction → ' + job.replace(/</g, '&lt;') + '</li>');
            if (unit) lines.push('<li>Affectation → ' + unit.replace(/</g, '&lt;') + '</li>');
            if (!grade && !role && !job && !unit) {
                lines.push('<li>Aucun changement de grade, rôle, fonction ou affectation — seule l’acceptation sera enregistrée.</li>');
            }
            lines.push('</ul>');
            var gainedCount = form.querySelector('[data-elev-gained-count]');
            var lostCount = form.querySelector('[data-elev-lost-count]');
            if (role && gainedCount && lostCount) {
                lines.push('<p class="eff-elev-dialog__perms">Droits : +' + gainedCount.textContent + ' / −' + lostCount.textContent + ' (catalogue du rôle).</p>');
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
})();
</script>
