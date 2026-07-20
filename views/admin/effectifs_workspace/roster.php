<?php
declare(strict_types=1);

$rows = is_array($rosterRows ?? null) ? $rosterRows : [];
$filters = is_array($rosterFilters ?? null) ? $rosterFilters : [];
$sortOptions = is_array($rosterSortOptions ?? null) ? $rosterSortOptions : [];
$roles = is_array($orgRoles ?? null) ? $orgRoles : [];
$units = is_array($orgUnits ?? null) ? $orgUnits : [];
$total = (int) ($rosterTotal ?? 0);
$page = (int) ($rosterPage ?? 1);
$totalPages = (int) ($rosterTotalPages ?? 1);
$counts = is_array($rosterCounts ?? null) ? $rosterCounts : [];
$canEditProfiles = (bool) ($canEditProfiles ?? false);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$canRequestElevation = (bool) ($canRequestElevation ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$communityName = trim((string) ($communityName ?? 'Communauté'));
$currentSort = (string) ($filters['tri'] ?? 'nom');
$elevationCatalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];

$filterQuery = static function (array $overrides = []) use ($filters, $page): array {
    $q = [
        'q' => $filters['q'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
        'sans_role' => !empty($filters['sans_role']) ? '1' : null,
        'tri' => !empty($filters['tri']) && ($filters['tri'] ?? 'nom') !== 'nom' ? $filters['tri'] : null,
        'page' => $page > 1 ? $page : null,
    ];
    foreach ($overrides as $k => $v) {
        $q[$k] = $v;
    }

    return array_filter($q, static fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== 0);
};

$returnUrl = effectifs_workspace_url() . (
    ($filters !== [] && array_filter($filters, static fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== 0))
        ? '?' . http_build_query($filterQuery())
        : ''
);

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'pending_verification' => 'E-mail à vérifier',
        default => $raw !== '' ? $raw : '—',
    };
};
$statusClass = static function (string $raw): string {
    return match ($raw) {
        'active' => 'eff-sheets__badge--ok',
        'inactive' => 'eff-sheets__badge--danger',
        'pending_verification' => 'eff-sheets__badge--watch',
        default => 'eff-sheets__badge--muted',
    };
};
$initials = static function (string $displayName, string $email): string {
    $displayName = trim($displayName);
    if ($displayName !== '') {
        $parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts !== false && count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1], 0, 1, 'UTF-8'), 'UTF-8');
        }

        return mb_strtoupper(mb_substr($displayName, 0, 2, 'UTF-8'), 'UTF-8');
    }
    $local = preg_replace('/@.*$/', '', $email) ?: '?';

    return mb_strtoupper(mb_substr($local, 0, 2, 'UTF-8'), 'UTF-8');
};
$splitRoles = static function (string $rolesDisplay): array {
    $parts = preg_split('/\s*,\s*/u', $rolesDisplay, -1, PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [];
    }

    return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
};

$queryUrl = static function (int $p) use ($filterQuery): string {
    $q = $filterQuery(['page' => $p > 1 ? $p : null]);

    return effectifs_workspace_url() . ($q ? '?' . http_build_query($q) : '');
};

$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || !empty($filters['role_id'])
    || !empty($filters['sans_affectation'])
    || !empty($filters['sans_role'])
    || (($filters['tri'] ?? 'nom') !== 'nom');
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Ressources humaines</p>
            <h1 class="eff-catalog__title">Tableur des effectifs</h1>
            <p class="eff-catalog__lead">
                Vue opérationnelle des membres de <?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?> :
                identité, grade, fonction, affectation, rôles et indicateurs. Affectez une unité ou demandez une élévation sans quitter le tableur.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Demandes d’élévation</a>
            <?php if ($hasActiveFilters): ?>
                <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="eff-catalog-filters" style="grid-template-columns: repeat(4, minmax(0, 1fr)); border-bottom: 0; padding-bottom: 0.35rem;">
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Membres</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= (int) ($counts['total'] ?? $total) ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Actifs</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= (int) ($counts['active'] ?? 0) ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Sans unité</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= (int) ($counts['no_unit'] ?? 0) ?></p>
        </div>
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Sans rôle</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= (int) ($counts['no_role'] ?? 0) ?></p>
        </div>
    </div>

    <form method="get" action="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="eff-catalog-filters">
            <div>
                <label for="eff-q">Recherche</label>
                <input id="eff-q" type="search" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, e-mail…">
            </div>
            <div>
                <label for="eff-status">Statut du compte</label>
                <select id="eff-status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Compte actif</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Compte inactif</option>
                    <option value="pending_verification" <?= (($filters['status'] ?? '') === 'pending_verification') ? 'selected' : '' ?>>E-mail à vérifier</option>
                </select>
            </div>
            <div>
                <label for="eff-role">Rôle</label>
                <select id="eff-role" name="role_id">
                    <option value="">Tous les rôles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) ($role['id'] ?? 0) ?>" <?= ((int) ($filters['role_id'] ?? 0) === (int) ($role['id'] ?? 0)) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($role['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="eff-tri">Trier par</label>
                <select id="eff-tri" name="tri">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= $currentSort === (string) $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="width:100%;height:2.25rem">Appliquer</button>
            </div>
        </div>
        <div class="eff-catalog-checks">
            <label class="eff-catalog-check<?= !empty($filters['sans_affectation']) ? ' is-on' : '' ?>">
                <input type="checkbox" name="sans_affectation" value="1" <?= !empty($filters['sans_affectation']) ? 'checked' : '' ?>>
                <span>Sans unité</span>
            </label>
            <label class="eff-catalog-check<?= !empty($filters['sans_role']) ? ' is-on' : '' ?>">
                <input type="checkbox" name="sans_role" value="1" <?= !empty($filters['sans_role']) ? 'checked' : '' ?>>
                <span>Sans rôle</span>
            </label>
        </div>
    </form>

    <?php if ($rows === []): ?>
        <div class="eff-catalog__empty">
            <strong>Aucun membre ne correspond</strong>
            Élargissez la recherche ou retirez un filtre.
            <?php if ($hasActiveFilters): ?>
                <div style="margin-top:1rem">
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Voir tous les effectifs</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des effectifs" tabindex="0">
            <table class="eff-sheets__table" id="eff-roster-table" data-cols-storage="eff-roster-col-widths-v1">
                <colgroup>
                    <col data-col="identity" style="width:14rem">
                    <col data-col="grade" style="width:6.5rem">
                    <col data-col="fonction" style="width:9rem">
                    <col data-col="affectation" style="width:14rem">
                    <col data-col="roles" style="width:11rem">
                    <col data-col="indicateurs" style="width:14rem">
                    <col data-col="statut" style="width:7.5rem">
                    <col data-col="actions" style="width:13rem">
                </colgroup>
                <thead>
                    <tr>
                        <th data-col="identity">Identité<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Identité" tabindex="0"></span></th>
                        <th data-col="grade">Grade<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Grade" tabindex="0"></span></th>
                        <th data-col="fonction">Fonction<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Fonction" tabindex="0"></span></th>
                        <th data-col="affectation">Affectation<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Affectation" tabindex="0"></span></th>
                        <th data-col="roles">Rôles<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Rôles" tabindex="0"></span></th>
                        <th data-col="indicateurs">Indicateurs<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Indicateurs" tabindex="0"></span></th>
                        <th data-col="statut">Statut<span class="eff-sheets__col-resizer" role="separator" aria-orientation="vertical" aria-label="Redimensionner la colonne Statut" tabindex="0"></span></th>
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $id = (int) ($row['id'] ?? 0);
                    $display = trim((string) ($row['display_name'] ?? ''));
                    $callsign = trim((string) ($row['callsign'] ?? ''));
                    $email = (string) ($row['email'] ?? '');
                    $name = $display !== '' ? $display : ($callsign !== '' ? $callsign : $email);
                    $status = (string) ($row['status'] ?? '');
                    $grade = trim((string) ($row['grade_short'] ?? ''));
                    if ($grade === '') {
                        $grade = trim((string) ($row['grade_long'] ?? ''));
                    }
                    $fonction = trim((string) ($row['personnel_job_role_name'] ?? ''));
                    if ($fonction === '') {
                        $fonction = trim((string) ($row['primary_role'] ?? ''));
                    }
                    if ($fonction === '') {
                        $fonction = trim((string) ($row['role_sub_label'] ?? ''));
                    }
                    $unit = trim((string) ($row['unit_name'] ?? ''));
                    $assignmentPath = trim((string) ($row['assignment_path'] ?? ''));
                    if ($assignmentPath === '' && $unit !== '') {
                        $assignmentPath = $unit;
                    }
                    $unitId = (int) ($row['unit_id'] ?? 0);
                    $rolesDisplay = trim((string) ($row['roles_display'] ?? ($row['role_name'] ?? '')));
                    $roleParts = $splitRoles($rolesDisplay);
                    $roleVisible = array_slice($roleParts, 0, 2);
                    $roleExtra = max(0, count($roleParts) - 2);
                    $ficheUrl = effectifs_workspace_url('membres/' . $id);
                    $editUrl = url('back-office/users/' . $id . '/edit');
                    $personnelUrl = url('personnel/' . $id);
                    $personnelEditUrl = url('personnel/' . $id . '/edit');
                    $avatarUrl = function_exists('user_media_public_url')
                        ? (user_media_public_url($row['avatar_url'] ?? null) ?? '')
                        : trim((string) ($row['avatar_url'] ?? ''));
                    $seniorityLabel = trim((string) ($row['seniority_label'] ?? '—'));
                    $availabilityScore = (int) ($row['availability_score'] ?? 0);
                    $presenceScore = (int) ($row['presence_score'] ?? 0);
                    $completionScore = (int) ($row['completion_score'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <div class="eff-sheets__identity">
                                <span class="eff-sheets__avatar" aria-hidden="true">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initials($name, $email), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                                <div class="eff-sheets__id-text">
                                    <strong class="eff-sheets__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($callsign !== '' && strcasecmp($callsign, $name) !== 0): ?>
                                        <span class="eff-sheets__meta"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($grade !== ''): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--grade"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__path-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($fonction !== ''): ?>
                                <span class="eff-sheets__cell-text"><?= htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch">Fonction manquante</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="eff-sheets__assign">
                                <?php if ($assignmentPath !== ''): ?>
                                    <span class="eff-sheets__path" title="<?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch">Sans unité</span>
                                <?php endif; ?>
                                <?php if ($canManageAssignments): ?>
                                    <details class="eff-sheets__pop">
                                        <summary class="eff-sheets__chip" style="height:1.4rem"><?= $assignmentPath !== '' ? 'Modifier' : 'Affecter' ?></summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <label for="eff-unit-<?= $id ?>">Unité de rattachement</label>
                                                <select id="eff-unit-<?= $id ?>" name="unit_id">
                                                    <option value="0">Retirer l’affectation</option>
                                                    <?php foreach ($units as $u): ?>
                                                        <?php
                                                        $optId = (int) ($u['id'] ?? 0);
                                                        $optLabel = trim((string) ($u['assignment_path'] ?? $u['name'] ?? ''));
                                                        ?>
                                                        <option value="<?= $optId ?>" <?= $unitId === $optId ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="height:1.85rem">Enregistrer</button>
                                                <a class="eff-sheets__pop-link" href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir le dossier</a>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($roleParts === []): ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch">Sans rôle</span>
                            <?php else: ?>
                                <div class="eff-sheets__tags" title="<?= htmlspecialchars($rolesDisplay, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($roleVisible as $rn): ?>
                                        <span class="eff-sheets__badge eff-sheets__badge--muted"><?= htmlspecialchars($rn, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($roleExtra > 0): ?>
                                        <span class="eff-sheets__badge eff-sheets__badge--info">+<?= $roleExtra ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="eff-sheets__metrics">
                                <span class="eff-sheets__metric" title="Ancienneté">Anc. <?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="eff-sheets__metric" title="Disponibilité">Disp. <?= $availabilityScore ?>%</span>
                                <span class="eff-sheets__metric" title="Présence">Prés. <?= $presenceScore ?>%</span>
                                <span class="eff-sheets__metric" title="Complétion du dossier">Doss. <?= $completionScore ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="eff-sheets__badge <?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <div class="eff-sheets__actions">
                                <a class="is-primary" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
                                <a href="<?= htmlspecialchars($personnelUrl, ENT_QUOTES, 'UTF-8') ?>">Dossier</a>
                                <?php if ($canEditProfiles): ?>
                                    <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>">Compte</a>
                                <?php endif; ?>
                                <?php if ($canRequestElevation): ?>
                                    <details class="eff-sheets__pop eff-sheets__pop--end">
                                        <summary class="eff-sheets__chip">Élévation</summary>
                                        <div class="eff-sheets__pop-panel">
                                            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-sheets__pop-form">
                                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php
                                                $fieldIdPrefix = 'eff-elev-' . $id;
                                                $selectedKind = 'grade';
                                                $includeUnit = true;
                                                require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                                                ?>
                                                <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" style="height:1.85rem">Envoyer la demande</button>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="eff-catalog-foot">
            <p style="margin:0">
                <strong style="color:#0f172a"><?= $total ?></strong>
                membre<?= $total > 1 ? 's' : '' ?> — page <?= $page ?> / <?= $totalPages ?>
            </p>
            <div class="eff-catalog-foot__links">
                <?php if ($page > 1): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($queryUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">Page précédente</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="eff-catalog__btn" href="<?= htmlspecialchars($queryUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Page suivante</a>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function () {
            var table = document.getElementById('eff-roster-table');
            if (!table) return;
            var storageKey = table.getAttribute('data-cols-storage') || 'eff-roster-col-widths-v1';
            var cols = table.querySelectorAll('colgroup col[data-col]');
            var minWidth = 56;

            function applyWidths(map) {
                cols.forEach(function (col) {
                    var key = col.getAttribute('data-col');
                    if (!key || !map[key]) return;
                    var w = parseInt(map[key], 10);
                    if (!isFinite(w) || w < minWidth) return;
                    col.style.width = w + 'px';
                });
            }

            function readStored() {
                try {
                    var raw = localStorage.getItem(storageKey);
                    if (!raw) return {};
                    var parsed = JSON.parse(raw);
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function writeStored(map) {
                try {
                    localStorage.setItem(storageKey, JSON.stringify(map));
                } catch (e) { /* ignore quota / private mode */ }
            }

            function currentMap() {
                var map = {};
                cols.forEach(function (col) {
                    var key = col.getAttribute('data-col');
                    if (!key) return;
                    var w = parseInt(col.style.width, 10);
                    if (!isFinite(w) || w < minWidth) {
                        w = Math.round(col.getBoundingClientRect().width);
                    }
                    if (isFinite(w) && w >= minWidth) map[key] = w;
                });
                return map;
            }

            applyWidths(readStored());

            table.querySelectorAll('thead th .eff-sheets__col-resizer').forEach(function (handle) {
                var th = handle.closest('th');
                if (!th) return;
                var colKey = th.getAttribute('data-col');
                if (!colKey) return;
                var col = table.querySelector('colgroup col[data-col="' + colKey + '"]');
                if (!col) return;

                function startResize(clientX) {
                    var startX = clientX;
                    var startW = col.getBoundingClientRect().width;
                    document.body.classList.add('eff-sheets--resizing');

                    function onMove(ev) {
                        var next = Math.max(minWidth, Math.round(startW + (ev.clientX - startX)));
                        col.style.width = next + 'px';
                    }

                    function onUp() {
                        document.body.classList.remove('eff-sheets--resizing');
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        writeStored(currentMap());
                    }

                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                }

                handle.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    startResize(ev.clientX);
                });

                handle.addEventListener('keydown', function (ev) {
                    var step = ev.shiftKey ? 24 : 8;
                    var w = Math.round(col.getBoundingClientRect().width);
                    if (ev.key === 'ArrowLeft') {
                        ev.preventDefault();
                        col.style.width = Math.max(minWidth, w - step) + 'px';
                        writeStored(currentMap());
                    } else if (ev.key === 'ArrowRight') {
                        ev.preventDefault();
                        col.style.width = (w + step) + 'px';
                        writeStored(currentMap());
                    }
                });
            });
        })();
        </script>
    <?php endif; ?>
</div>
