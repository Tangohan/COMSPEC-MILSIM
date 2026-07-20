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
$elevationCooldownByUserId = is_array($elevationCooldownByUserId ?? null) ? $elevationCooldownByUserId : [];
$cooldownLabel = static function (int $seconds): string {
    $hours = max(1, (int) ceil($seconds / 3600));
    if ($hours < 24) {
        return $hours . ' h';
    }

    return max(1, (int) ceil($hours / 24)) . ' j';
};

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

$exportQuery = array_filter([
    'q' => $filters['q'] ?? null,
    'status' => !empty($filters['status']) ? $filters['status'] : null,
    'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
    'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
    'sans_role' => !empty($filters['sans_role']) ? '1' : null,
], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
$exportUrl = effectifs_workspace_url('export') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
?>
<div class="eff-catalog">
    <div class="eff-catalog__head">
        <div class="min-w-0">
            <p class="eff-catalog__kicker">Ressources humaines</p>
            <h1 class="eff-catalog__title">Tableur des effectifs</h1>
            <p class="eff-catalog__lead">
                Vue opérationnelle des membres de <?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?> :
                identité, grade, fonction, affectation, rôles et indicateurs. Affectez une unité ou demandez une élévation sans quitter le tableur.
                Pour l’organigramme et les référentiels (non nominatif), voir <a href="<?= htmlspecialchars(url('back-office/organisation-effectifs'), ENT_QUOTES, 'UTF-8') ?>" class="underline">Structure &amp; grades</a>.
            </p>
        </div>
        <div class="eff-catalog__tools">
            <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations'), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Demandes d’élévation</a>
            <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Exporter en CSV</a>
            <?php if ($hasActiveFilters): ?>
                <a href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-catalog__btn">Réinitialiser</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="eff-catalog-filters" style="grid-template-columns: repeat(5, minmax(0, 1fr)); border-bottom: 0; padding-bottom: 0.35rem;">
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
        <div>
            <p class="eff-catalog__kicker" style="letter-spacing:0.14em">Habilitation à revoir</p>
            <p style="margin:0.15rem 0 0;font-size:1.35rem;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums"><?= (int) ($counts['clearance_review_due'] ?? 0) ?></p>
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
        <?php if ($canManageStatus): ?>
        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('bulk/statut'), ENT_QUOTES, 'UTF-8') ?>" id="eff-bulk-form" data-eff-bulk-bar style="display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:.6rem;background:#f8fafc">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
            <span data-eff-bulk-count style="font-size:12px;font-weight:700;color:#475569">0 sélectionné(s)</span>
            <select name="status" style="border:1px solid #cbd5e1;border-radius:.4rem;padding:.35rem .5rem;font-size:12px">
                <option value="active">Passer actif</option>
                <option value="inactive">Passer inactif</option>
                <option value="pending_verification">E-mail à vérifier</option>
            </select>
            <button type="submit" class="eff-catalog__btn eff-catalog__btn--primary" data-eff-bulk-submit disabled>Appliquer</button>
        </form>
        <?php endif; ?>
        <div class="eff-sheets" role="region" aria-label="Tableur des effectifs" tabindex="0">
            <table class="eff-sheets__table">
                <thead>
                    <tr>
                        <?php if ($canManageStatus): ?>
                        <th style="width:2rem"><input type="checkbox" data-eff-bulk-all aria-label="Tout sélectionner"></th>
                        <?php endif; ?>
                        <th>Identité</th>
                        <th>Grade</th>
                        <th>Fonction</th>
                        <th>Affectation</th>
                        <th>Rôles</th>
                        <th>Indicateurs</th>
                        <th>Statut</th>
                        <th>Actions</th>
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
                    $metaLine = $callsign !== '' ? $callsign : $email;
                    $avatarUrl = function_exists('user_media_public_url')
                        ? (user_media_public_url($row['avatar_url'] ?? null) ?? '')
                        : trim((string) ($row['avatar_url'] ?? ''));
                    $seniorityLabel = trim((string) ($row['seniority_label'] ?? '—'));
                    $availabilityScore = (int) ($row['availability_score'] ?? 0);
                    $presenceScore = (int) ($row['presence_score'] ?? 0);
                    $completionScore = (int) ($row['completion_score'] ?? 0);
                    $clearanceOverdue = \App\Support\ClearanceReviewPolicy::isOverdue(
                        $row['clearance_level'] ?? null,
                        $row['clearance_reviewed_at'] ?? null
                    );
                    ?>
                    <tr>
                        <?php if ($canManageStatus): ?>
                        <td><input type="checkbox" class="eff-bulk-check" name="user_ids[]" value="<?= $id ?>" form="eff-bulk-form" aria-label="Sélectionner <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"></td>
                        <?php endif; ?>
                        <td>
                            <div class="eff-sheets__identity">
                                <span class="eff-sheets__avatar" aria-hidden="true">
                                    <?php if ($avatarUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initials($name, $email), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <strong class="eff-sheets__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="eff-sheets__meta"><?= htmlspecialchars($metaLine, ENT_QUOTES, 'UTF-8') ?></span>
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
                                <span style="font-weight:600;color:#334155"><?= htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch">Fonction manquante</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($assignmentPath !== ''): ?>
                                <span class="eff-sheets__path" title="<?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($assignmentPath, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php else: ?>
                                <span class="eff-sheets__badge eff-sheets__badge--watch">Sans unité</span>
                            <?php endif; ?>
                            <?php if ($canManageAssignments): ?>
                                <details class="eff-sheets__pop" style="margin-top:0.35rem">
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
                                <?php if ($clearanceOverdue): ?>
                                    <span class="eff-sheets__badge eff-sheets__badge--watch" title="Habilitation accordée sans revue récente (&gt; <?= \App\Support\ClearanceReviewPolicy::REVIEW_INTERVAL_DAYS ?> jours)">Habilitation à revoir</span>
                                <?php endif; ?>
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
                                    <?php $cooldownSec = (int) ($elevationCooldownByUserId[$id] ?? 0); ?>
                                    <?php if ($cooldownSec > 0): ?>
                                        <span class="eff-sheets__chip" style="opacity:.55;cursor:default" title="Une demande a déjà été envoyée récemment pour ce membre — patientez avant d’en renvoyer une.">Élévation (patientez <?= htmlspecialchars($cooldownLabel($cooldownSec), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <?php else: ?>
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
    <?php endif; ?>
</div>
<?php if ($canManageStatus && $rows !== []): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/eff-bulk-actions.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
