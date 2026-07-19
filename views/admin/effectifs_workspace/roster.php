<?php
declare(strict_types=1);

$rows = is_array($rosterRows ?? null) ? $rosterRows : [];
$filters = is_array($rosterFilters ?? null) ? $rosterFilters : [];
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
$returnUrl = effectifs_workspace_url() . (
    ($filters !== [] && array_filter($filters, static fn ($v) => $v !== null && $v !== '' && $v !== false && $v !== 0))
        ? '?' . http_build_query(array_filter([
            'q' => $filters['q'] ?? null,
            'status' => !empty($filters['status']) ? $filters['status'] : null,
            'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
            'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
            'sans_role' => !empty($filters['sans_role']) ? '1' : null,
            'page' => $page > 1 ? $page : null,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 0))
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
        'active' => 'eff-badge--active',
        'inactive' => 'eff-badge--inactive',
        'pending_verification' => 'eff-badge--pending',
        default => 'eff-badge--muted',
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

$queryUrl = static function (int $p) use ($filters): string {
    $q = [
        'q' => $filters['q'] ?? null,
        'status' => !empty($filters['status']) ? $filters['status'] : null,
        'role_id' => !empty($filters['role_id']) ? (int) $filters['role_id'] : null,
        'sans_affectation' => !empty($filters['sans_affectation']) ? '1' : null,
        'sans_role' => !empty($filters['sans_role']) ? '1' : null,
        'page' => $p > 1 ? $p : null,
    ];
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '' && $v !== 0);

    return effectifs_workspace_url() . ($q ? '?' . http_build_query($q) : '');
};

$iconSearch = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M20 20l-3.5-3.5"/></svg>';
$iconUsers = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';
$iconEye = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
$iconFolder = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>';
$iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>';
$iconUnit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6"/></svg>';
$iconElevate = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-5 5m5-5l5 5"/></svg>';

$hasActiveFilters = ($filters['q'] ?? '') !== ''
    || ($filters['status'] ?? '') !== ''
    || !empty($filters['role_id'])
    || !empty($filters['sans_affectation'])
    || !empty($filters['sans_role']);
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Ressources humaines</p>
    <h1 class="eff-page-title">Tableur des effectifs</h1>
    <p class="eff-page-lead">
        Vue opérationnelle des membres de <strong class="eff-text-accent"><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></strong> :
        identité, grade, fonction, unité, rôles et statut. Affectez une unité ou demandez une élévation sans quitter le tableur.
    </p>
</section>

<div class="eff-metrics" aria-label="Indicateurs effectifs">
    <div class="eff-metric">
        <p class="eff-metric__k">Membres</p>
        <p class="eff-metric__v"><?= (int) ($counts['total'] ?? $total) ?></p>
    </div>
    <a class="eff-metric eff-metric--link<?= (($filters['status'] ?? '') === 'active') ? ' is-active' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?status=active', ENT_QUOTES, 'UTF-8') ?>">
        <p class="eff-metric__k">Actifs</p>
        <p class="eff-metric__v"><?= (int) ($counts['active'] ?? 0) ?></p>
    </a>
    <a class="eff-metric eff-metric--link<?= !empty($filters['sans_affectation']) ? ' is-active is-amber' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_affectation=1', ENT_QUOTES, 'UTF-8') ?>">
        <p class="eff-metric__k">Sans unité</p>
        <p class="eff-metric__v"><?= (int) ($counts['no_unit'] ?? 0) ?></p>
    </a>
    <a class="eff-metric eff-metric--link<?= !empty($filters['sans_role']) ? ' is-active is-amber' : '' ?>" href="<?= htmlspecialchars(effectifs_workspace_url() . '?sans_role=1', ENT_QUOTES, 'UTF-8') ?>">
        <p class="eff-metric__k">Sans rôle</p>
        <p class="eff-metric__v"><?= (int) ($counts['no_role'] ?? 0) ?></p>
    </a>
</div>

<form method="get" action="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="eff-panel eff-filters-panel">
    <div class="eff-filters-toolbar">
        <div class="eff-filters-toolbar__label">
            <span class="eff-filters-icon" aria-hidden="true"><?= $iconSearch ?></span>
            <div>
                <p class="eff-filters-title">Filtrer le tableur</p>
                <p class="eff-filters-sub">Recherche, statut, rôle et manques d’affectation.</p>
            </div>
        </div>
        <?php if ($hasActiveFilters): ?>
            <a class="eff-btn eff-btn--ghost eff-btn--sm" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Réinitialiser</a>
        <?php endif; ?>
    </div>
    <div class="eff-filters">
        <div class="eff-field">
            <label for="eff-q">Recherche</label>
            <input id="eff-q" type="search" name="q" value="<?= htmlspecialchars((string) ($filters['q'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, e-mail…">
        </div>
        <div class="eff-field">
            <label for="eff-status">Statut du compte</label>
            <select id="eff-status" name="status">
                <option value="">Tous les statuts</option>
                <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Compte actif</option>
                <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Compte inactif</option>
                <option value="pending_verification" <?= (($filters['status'] ?? '') === 'pending_verification') ? 'selected' : '' ?>>E-mail à vérifier</option>
            </select>
        </div>
        <div class="eff-field">
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
        <div class="eff-field eff-field--action">
            <label>&nbsp;</label>
            <button type="submit" class="eff-btn eff-btn--primary">Appliquer</button>
        </div>
    </div>
    <div class="eff-checks">
        <label class="eff-check<?= !empty($filters['sans_affectation']) ? ' is-on' : '' ?>">
            <input type="checkbox" name="sans_affectation" value="1" <?= !empty($filters['sans_affectation']) ? 'checked' : '' ?>>
            <span>Sans unité</span>
        </label>
        <label class="eff-check<?= !empty($filters['sans_role']) ? ' is-on' : '' ?>">
            <input type="checkbox" name="sans_role" value="1" <?= !empty($filters['sans_role']) ? 'checked' : '' ?>>
            <span>Sans rôle</span>
        </label>
    </div>
</form>

<?php if ($rows === []): ?>
    <div class="eff-empty">
        <div class="eff-empty__icon" aria-hidden="true"><?= $iconUsers ?></div>
        <h2 class="eff-empty__title">Aucun membre ne correspond</h2>
        <p class="eff-empty__text">Aucun membre ne correspond à ces critères. Élargissez la recherche ou retirez un filtre.</p>
        <?php if ($hasActiveFilters): ?>
            <div class="eff-empty__actions">
                <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">Voir tous les effectifs</a>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="eff-list-meta">
        <p class="eff-list-count">
            <strong><?= $total ?></strong>
            membre<?= $total > 1 ? 's' : ''
            ?> — page <?= $page ?> / <?= $totalPages ?>
        </p>
    </div>
    <div class="eff-table-wrap" role="region" aria-label="Tableur des effectifs" tabindex="0">
        <table class="eff-table">
            <thead>
                <tr>
                    <th>Identité</th>
                    <th>Grade</th>
                    <th>Fonction</th>
                    <th>Unité</th>
                    <th>Communauté</th>
                    <th>Rôles</th>
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
                $unitId = (int) ($row['unit_id'] ?? 0);
                $rowCommunity = trim((string) ($row['community_name'] ?? $communityName));
                $rolesDisplay = trim((string) ($row['roles_display'] ?? ($row['role_name'] ?? '')));
                $roleParts = $splitRoles($rolesDisplay);
                $roleVisible = array_slice($roleParts, 0, 2);
                $roleExtra = max(0, count($roleParts) - 2);
                $ficheUrl = effectifs_workspace_url('membres/' . $id);
                $editUrl = url('back-office/users/' . $id . '/edit');
                $personnelUrl = url('personnel/' . $id);
                $personnelEditUrl = url('personnel/' . $id . '/edit');
                $metaLine = $callsign !== '' ? $callsign : $email;
                if ($callsign !== '' && $email !== '' && strcasecmp($callsign, $email) !== 0) {
                    $metaLine = $callsign;
                }
                $avatarUrl = function_exists('user_media_public_url')
                    ? (user_media_public_url($row['avatar_url'] ?? null) ?? '')
                    : trim((string) ($row['avatar_url'] ?? ''));
                ?>
                <tr>
                    <td>
                        <div class="eff-identity">
                            <span class="eff-avatar" aria-hidden="true">
                                <?php if ($avatarUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials($name, $email), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </span>
                            <div class="eff-identity__text">
                                <strong class="eff-identity__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong>
                                <span class="eff-identity__meta"><?= htmlspecialchars($metaLine, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($callsign !== '' && $email !== '' && strcasecmp($callsign, $email) !== 0): ?>
                                    <span class="eff-identity__meta eff-identity__meta--dim"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($grade !== ''): ?>
                            <span class="eff-tag eff-tag--grade"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span class="eff-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($fonction !== ''): ?>
                            <span class="eff-cell-primary"><?= htmlspecialchars($fonction, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span class="eff-tag eff-tag--warn">Fonction manquante</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="eff-unit-cell">
                            <?php if ($unit !== ''): ?>
                                <span class="eff-tag eff-tag--unit" title="<?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="eff-tag__ico" aria-hidden="true"><?= $iconUnit ?></span>
                                    <?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php else: ?>
                                <span class="eff-tag eff-tag--warn">Sans unité</span>
                            <?php endif; ?>
                            <?php if ($canManageAssignments): ?>
                                <details class="eff-pop">
                                    <summary class="eff-pop__sum"><?= $unit !== '' ? 'Modifier' : 'Affecter' ?></summary>
                                    <div class="eff-pop__panel">
                                        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-pop__form">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <label for="eff-unit-<?= $id ?>">Unité de rattachement</label>
                                            <select id="eff-unit-<?= $id ?>" name="unit_id">
                                                <option value="0">Retirer l’affectation</option>
                                                <?php foreach ($units as $u): ?>
                                                    <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= $unitId === (int) ($u['id'] ?? 0) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="eff-btn eff-btn--primary eff-btn--sm">Enregistrer</button>
                                            <a class="eff-pop__link" href="<?= htmlspecialchars($personnelEditUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir le dossier</a>
                                        </form>
                                    </div>
                                </details>
                            <?php elseif ($unit === ''): ?>
                                <a class="eff-pop__link" href="<?= htmlspecialchars($personnelUrl, ENT_QUOTES, 'UTF-8') ?>">Voir le dossier</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="eff-tag eff-tag--community"><?= htmlspecialchars($rowCommunity !== '' ? $rowCommunity : 'Communauté', ENT_QUOTES, 'UTF-8') ?></span>
                    </td>
                    <td>
                        <?php if ($roleParts === []): ?>
                            <span class="eff-tag eff-tag--warn">Sans rôle</span>
                        <?php else: ?>
                            <div class="eff-tags" title="<?= htmlspecialchars($rolesDisplay, ENT_QUOTES, 'UTF-8') ?>">
                                <?php foreach ($roleVisible as $rn): ?>
                                    <span class="eff-tag"><?= htmlspecialchars($rn, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                                <?php if ($roleExtra > 0): ?>
                                    <span class="eff-tag eff-tag--count">+<?= $roleExtra ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="eff-badge <?= htmlspecialchars($statusClass($status), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="eff-badge__dot" aria-hidden="true"></span>
                            <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td>
                        <div class="eff-actions">
                            <a class="eff-act eff-act--primary" href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <span aria-hidden="true"><?= $iconEye ?></span>
                                Fiche
                            </a>
                            <a class="eff-act" href="<?= htmlspecialchars($personnelUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <span aria-hidden="true"><?= $iconFolder ?></span>
                                Dossier
                            </a>
                            <?php if ($canEditProfiles): ?>
                                <a class="eff-act" href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <span aria-hidden="true"><?= $iconEdit ?></span>
                                    Compte
                                </a>
                            <?php endif; ?>
                            <?php if ($canRequestElevation): ?>
                                <details class="eff-pop eff-pop--elevate">
                                    <summary class="eff-act eff-act--amber">
                                        <span aria-hidden="true"><?= $iconElevate ?></span>
                                        Élévation
                                    </summary>
                                    <div class="eff-pop__panel">
                                        <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-pop__form">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                                            <label for="eff-elev-kind-<?= $id ?>">Type de demande</label>
                                            <select id="eff-elev-kind-<?= $id ?>" name="elevation_kind">
                                                <option value="grade">Grade</option>
                                                <option value="role">Rôle</option>
                                                <option value="droits">Droits d’accès</option>
                                                <option value="general">Situation RH</option>
                                            </select>
                                            <label for="eff-elev-note-<?= $id ?>">Message (optionnel)</label>
                                            <textarea id="eff-elev-note-<?= $id ?>" name="elevation_note" rows="2" maxlength="500" placeholder="Précisez le besoin…"></textarea>
                                            <button type="submit" class="eff-btn eff-btn--primary eff-btn--sm">Envoyer la demande</button>
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

    <div class="eff-pager">
        <p><?= $total ?> membre<?= $total > 1 ? 's' : '' ?> — page <?= $page ?> / <?= $totalPages ?></p>
        <div class="eff-pager__links">
            <?php if ($page > 1): ?>
                <a class="eff-btn eff-btn--ghost eff-btn--sm" href="<?= htmlspecialchars($queryUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">Page précédente</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="eff-btn eff-btn--ghost eff-btn--sm" href="<?= htmlspecialchars($queryUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Page suivante</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
