<?php

declare(strict_types=1);

/**
 * Tableur rapide des effectifs — dashboard communauté.
 *
 * @var list<array<string, mixed>> $dashboard_effectifs_rows
 * @var bool $can_view_personnel_directory
 * @var bool $can_open_effectifs_workspace
 * @var bool $can_see_inactive_effectifs
 * @var string|null $dashboard_tenant_label
 */

if (empty($can_view_personnel_directory)) {
    return;
}

$rows = is_array($dashboard_effectifs_rows ?? null) ? $dashboard_effectifs_rows : [];
$unitLabel = trim((string) ($dashboard_tenant_label ?? '')) !== ''
    ? (string) $dashboard_tenant_label
    : 'Votre communauté';
$canOpenWorkspace = !empty($can_open_effectifs_workspace);
$canSeeInactive = !empty($can_see_inactive_effectifs);
$directoryUrl = url('personnel');
$workspaceUrl = function_exists('effectifs_workspace_url')
    ? effectifs_workspace_url()
    : url('back-office/ressources/effectifs');

$initialsOf = static function (string $name): string {
    if (function_exists('user_display_initials')) {
        return user_display_initials($name, 2);
    }
    $clean = preg_replace('/\s+/u', ' ', trim($name)) ?: '?';
    $parts = preg_split('/\s+/u', $clean) ?: [];
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }

    return mb_strtoupper(mb_substr($clean, 0, 2));
};

$gradeLabelFor = static function (array $row): string {
    if (function_exists('personnel_assigned_grade_label')) {
        return personnel_assigned_grade_label($row);
    }
    $long = trim((string) ($row['grade_long'] ?? ''));
    if ($long !== '') {
        return $long;
    }

    return trim((string) ($row['grade_short'] ?? ''));
};

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'pending_verification' => 'E-mail à confirmer',
        default => $raw !== '' ? $raw : 'Non renseigné',
    };
};

$statusBadge = static function (string $raw): string {
    return match ($raw) {
        'active' => 'das-badge--emerald',
        'pending_verification' => 'das-badge--amber',
        'inactive' => 'das-badge--slate',
        default => 'das-badge--muted',
    };
};

$rowCount = count($rows);
?>
<div class="das-stack">
    <article class="das-card das-card--effectifs" aria-labelledby="dash-effectifs-title">
        <div class="das-card__head">
            <div class="das-card__head-main">
                <p class="das-kicker">Effectifs</p>
                <div class="das-card__title-row">
                    <h2 id="dash-effectifs-title" class="das-card__title">Tableau rapide — <?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($rowCount > 0): ?>
                        <span class="das-card__count" aria-label="<?= (int) $rowCount ?> membre<?= $rowCount > 1 ? 's' : '' ?>"><?= (int) $rowCount ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="das-card__actions">
                <?php if ($canOpenWorkspace): ?>
                    <a href="<?= htmlspecialchars($workspaceUrl, ENT_QUOTES, 'UTF-8') ?>" class="das-card__cta">
                        Bureau effectifs
                        <span aria-hidden="true">→</span>
                    </a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($directoryUrl, ENT_QUOTES, 'UTF-8') ?>" class="das-card__link">Annuaire complet</a>
            </div>
        </div>

        <?php if ($rowCount === 0): ?>
            <div class="das-empty">
                <p>Aucun membre à afficher pour le moment dans cette communauté.</p>
            </div>
        <?php else: ?>
            <p class="das-scroll-hint">Faites défiler horizontalement pour les colonnes secondaires.</p>

            <ul class="das-cards das-cards--effectifs" aria-label="Effectifs">
                <?php foreach ($rows as $row): ?>
                    <?php
                    if (!is_array($row)) {
                        continue;
                    }
                    $uid = (int) ($row['id'] ?? 0);
                    if ($uid < 1) {
                        continue;
                    }
                    $displayName = trim((string) ($row['display_name'] ?? ''));
                    if ($displayName === '') {
                        $displayName = 'Membre';
                    }
                    $callsign = trim((string) ($row['callsign'] ?? ''));
                    $character = \App\Support\PersonnelDirectoryHints::distinctCharacterLabel($displayName, (string) ($row['character_name'] ?? ''));
                    $slug = trim((string) ($row['profile_slug'] ?? ''));
                    $target = $slug !== '' ? $slug : (string) $uid;
                    $ficheUrl = url('personnel/' . $target);
                    $avatarRaw = function_exists('personnel_operator_portrait_url')
                        ? (string) (personnel_operator_portrait_url($row) ?? '')
                        : trim((string) ($row['avatar_url'] ?? ''));
                    $avatar = $avatarRaw;
                    $gradeLabel = $gradeLabelFor($row);
                    $unitName = trim((string) ($row['unit_name'] ?? ''));
                    $unitCode = trim((string) ($row['unit_code'] ?? ''));
                    $assignment = $unitName !== '' ? $unitName : ($unitCode !== '' ? $unitCode : '');
                    $unitTooltip = trim((string) ($row['unit_tooltip'] ?? ''));
                    if ($unitTooltip === '' && $assignment !== '') {
                        $unitTooltip = 'Unité : ' . $assignment;
                    }
                    $primaryRole = trim((string) ($row['primary_role'] ?? ''));
                    $status = trim((string) ($row['status'] ?? ''));
                    $playtimeLabel = trim((string) ($row['arma_playtime_label'] ?? ''));
                    $playtimeSeconds = (int) ($row['arma_playtime_seconds'] ?? 0);
                    if ($playtimeLabel === '' && $playtimeSeconds > 0 && function_exists('format_arma_playtime_french')) {
                        $playtimeLabel = format_arma_playtime_french($playtimeSeconds);
                    }
                    $seniorityLabel = trim((string) ($row['seniority_label'] ?? ''));
                    $cardBits = array_values(array_filter([
                        $gradeLabel !== '' ? $gradeLabel : null,
                        $assignment !== '' ? $assignment : null,
                        $callsign !== '' ? $callsign : null,
                        $seniorityLabel !== '' ? $seniorityLabel : null,
                    ]));
                    ?>
                    <li class="das-cards__item">
                        <div class="das-cards__top">
                            <div class="dash-eff-id">
                                <div class="dash-eff-id__avatar" aria-hidden="true">
                                    <?php if ($avatar !== ''): ?>
                                        <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($initialsOf($displayName), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dash-eff-id__text">
                                    <span class="dash-eff-id__name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($cardBits !== []): ?>
                                        <span class="dash-eff-id__meta"><?= htmlspecialchars(implode(' · ', $cardBits), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif ($character !== ''): ?>
                                        <span class="dash-eff-id__meta">Personnage · <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($canSeeInactive): ?>
                                <span class="das-badge <?= htmlspecialchars($statusBadge($status), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($primaryRole !== '' || $playtimeLabel !== '' || $assignment !== '' || $seniorityLabel !== ''): ?>
                            <p class="das-cards__step">
                                <?php if ($assignment !== ''): ?>
                                    <span class="dash-eff-unit" tabindex="0"
                                          title="<?= htmlspecialchars($unitTooltip, ENT_QUOTES, 'UTF-8') ?>"
                                          aria-label="<?= htmlspecialchars($unitTooltip !== '' ? $unitTooltip : $assignment, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($assignment, ENT_QUOTES, 'UTF-8') ?>
                                        <span class="dash-eff-unit__hint" aria-hidden="true">i</span>
                                    </span>
                                <?php endif; ?>
                                <?php if ($assignment !== '' && ($primaryRole !== '' || $playtimeLabel !== '' || $seniorityLabel !== '')): ?> · <?php endif; ?>
                                <?= $primaryRole !== '' ? htmlspecialchars($primaryRole, ENT_QUOTES, 'UTF-8') : '' ?>
                                <?php if ($primaryRole !== '' && ($playtimeLabel !== '' || $seniorityLabel !== '')): ?> · <?php endif; ?>
                                <?php if ($seniorityLabel !== ''): ?>
                                    <span class="dash-eff-seniority"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if ($seniorityLabel !== '' && $playtimeLabel !== ''): ?> · <?php endif; ?>
                                <?php if ($playtimeLabel !== ''): ?>
                                    <span class="dash-eff-playtime"><?= htmlspecialchars($playtimeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>" class="das-btn das-btn--block">Ouvrir la fiche</a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="das-sheet das-sheet--effectifs">
                <table class="das-sheet__table">
                    <thead>
                        <tr>
                            <th scope="col" class="das-sticky-col">Membre</th>
                            <th scope="col" class="das-hide-sm">Indicatif</th>
                            <th scope="col">Grade</th>
                            <th scope="col">Affectation</th>
                            <th scope="col" class="das-hide-md">Fonction</th>
                            <th scope="col">Ancienneté</th>
                            <th scope="col" class="das-hide-lg">Temps en mission</th>
                            <?php if ($canSeeInactive): ?>
                            <th scope="col" class="das-hide-md">Statut</th>
                            <?php endif; ?>
                            <th scope="col" class="text-right">Fiche</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            if (!is_array($row)) {
                                continue;
                            }
                            $uid = (int) ($row['id'] ?? 0);
                            if ($uid < 1) {
                                continue;
                            }
                            $displayName = trim((string) ($row['display_name'] ?? ''));
                            if ($displayName === '') {
                                $displayName = 'Membre';
                            }
                            $callsign = trim((string) ($row['callsign'] ?? ''));
                            $character = \App\Support\PersonnelDirectoryHints::distinctCharacterLabel($displayName, (string) ($row['character_name'] ?? ''));
                            $slug = trim((string) ($row['profile_slug'] ?? ''));
                            $target = $slug !== '' ? $slug : (string) $uid;
                            $ficheUrl = url('personnel/' . $target);
                            $avatarRaw = function_exists('personnel_operator_portrait_url')
                                ? (string) (personnel_operator_portrait_url($row) ?? '')
                                : trim((string) ($row['avatar_url'] ?? ''));
                            $avatar = $avatarRaw;
                            $gradeLabel = $gradeLabelFor($row);
                            $unitName = trim((string) ($row['unit_name'] ?? ''));
                            $unitCode = trim((string) ($row['unit_code'] ?? ''));
                            $assignment = $unitName !== '' ? $unitName : ($unitCode !== '' ? $unitCode : '');
                            $unitTooltip = trim((string) ($row['unit_tooltip'] ?? ''));
                            if ($unitTooltip === '' && $assignment !== '') {
                                $unitTooltip = 'Unité : ' . $assignment;
                            }
                            $primaryRole = trim((string) ($row['primary_role'] ?? ''));
                            $status = trim((string) ($row['status'] ?? ''));
                            $playtimeLabel = trim((string) ($row['arma_playtime_label'] ?? ''));
                            $playtimeSeconds = (int) ($row['arma_playtime_seconds'] ?? 0);
                            if ($playtimeLabel === '' && $playtimeSeconds > 0 && function_exists('format_arma_playtime_french')) {
                                $playtimeLabel = format_arma_playtime_french($playtimeSeconds);
                            }
                            $seniorityLabel = trim((string) ($row['seniority_label'] ?? ''));
                            ?>
                            <tr>
                                <td class="das-sticky-col">
                                    <div class="dash-eff-id">
                                        <div class="dash-eff-id__avatar" aria-hidden="true">
                                            <?php if ($avatar !== ''): ?>
                                                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <span><?= htmlspecialchars($initialsOf($displayName), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dash-eff-id__text">
                                            <span class="dash-eff-id__name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($character !== ''): ?>
                                                <span class="dash-eff-id__meta">Personnage · <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="das-hide-sm">
                                    <?php if ($callsign !== ''): ?>
                                        <span class="dash-eff-callsign"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="das-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="das-sheet__cell-strong">
                                    <?php if ($gradeLabel !== ''): ?>
                                        <?= htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="das-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($assignment !== ''): ?>
                                        <span class="dash-eff-unit" tabindex="0"
                                              title="<?= htmlspecialchars($unitTooltip, ENT_QUOTES, 'UTF-8') ?>"
                                              aria-label="<?= htmlspecialchars($unitTooltip !== '' ? $unitTooltip : $assignment, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($assignment, ENT_QUOTES, 'UTF-8') ?>
                                            <span class="dash-eff-unit__hint" aria-hidden="true">i</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="das-muted">Non affecté</span>
                                    <?php endif; ?>
                                </td>
                                <td class="das-hide-md">
                                    <?php if ($primaryRole !== ''): ?>
                                        <?= htmlspecialchars($primaryRole, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="das-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($seniorityLabel !== ''): ?>
                                        <span class="dash-eff-seniority" title="Ancienneté dans la communauté"><?= htmlspecialchars($seniorityLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="das-muted">Non renseignée</span>
                                    <?php endif; ?>
                                </td>
                                <td class="das-hide-lg">
                                    <?php if ($playtimeLabel !== ''): ?>
                                        <span class="dash-eff-playtime" title="Temps de mission transmis par la liaison terrain"><?= htmlspecialchars($playtimeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="das-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canSeeInactive): ?>
                                <td class="das-hide-md">
                                    <span class="das-badge <?= htmlspecialchars($statusBadge($status), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <?php endif; ?>
                                <td class="text-right">
                                    <a href="<?= htmlspecialchars($ficheUrl, ENT_QUOTES, 'UTF-8') ?>" class="das-btn">Ouvrir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="das-card__foot">
                Affichage de <?= (int) $rowCount ?> membre<?= $rowCount > 1 ? 's' : '' ?>
                <?= $rowCount >= 40 ? ' (aperçu limité — ouvrir l’annuaire pour la liste complète)' : '' ?>.
            </p>
        <?php endif; ?>
    </article>
</div>
<style>
.das-stack { display: flex; flex-direction: column; gap: 1.25rem; width: 100%; }
.das-card {
    width: 100%;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 0.85rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.das-card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1.25rem;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}
.das-card__head-main { min-width: 0; flex: 1 1 12rem; }
.das-kicker {
    margin: 0;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #059669;
}
.das-card__title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem 0.65rem;
    margin-top: 0.2rem;
}
.das-card__title {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 900;
    letter-spacing: -0.025em;
    color: #0f172a;
    line-height: 1.2;
}
.das-card__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.55rem;
    height: 1.55rem;
    padding: 0 0.4rem;
    border-radius: 999px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
    font-size: 0.75rem;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}
.das-card__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.55rem 0.85rem;
}
.das-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.9rem;
    border-radius: 0.55rem;
    border: 1px solid #059669;
    background: #059669;
    color: #fff;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 6px 14px -8px rgba(5, 150, 105, 0.7);
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}
.das-card__cta:hover {
    background: #047857;
    border-color: #047857;
    color: #fff;
    transform: translateY(-1px);
}
.das-card__link {
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #047857;
    text-decoration: none;
    white-space: nowrap;
}
.das-card__link:hover { color: #065f46; text-decoration: underline; }
.das-card__foot {
    margin: 0;
    padding: 0.65rem 1.15rem 0.9rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    border-top: 1px solid #f1f5f9;
}
.das-empty {
    margin: 1rem;
    border-radius: 0.75rem;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    padding: 1.25rem 1rem;
    text-align: center;
}
.das-empty p { margin: 0; font-size: 0.875rem; font-weight: 550; color: #334155; }
.das-scroll-hint {
    margin: 0.55rem 1.15rem 0;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}
@media (min-width: 1100px) {
    .das-scroll-hint { display: none; }
}

.das-cards {
    list-style: none;
    margin: 0;
    padding: 0.75rem;
    display: none;
    flex-direction: column;
    gap: 0.65rem;
}
.das-cards__item {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0.85rem 0.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #f8fafc;
}
.das-cards__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.65rem;
}
.das-cards__step {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #334155;
}

.das-sheet {
    width: 100%;
    border-top: 1px solid #e2e8f0;
    margin-top: 0.55rem;
    overflow: auto;
    max-height: min(32rem, 68vh);
    -webkit-overflow-scrolling: touch;
}
.das-sheet--effectifs .das-sheet__table {
    width: 100%;
    min-width: 28rem;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
}
@media (min-width: 1100px) {
    .das-sheet--effectifs .das-sheet__table { min-width: 40rem; }
}
@media (min-width: 1400px) {
    .das-sheet--effectifs .das-sheet__table { min-width: <?= $canSeeInactive ? '58rem' : '52rem' ?>; }
}
.das-sheet__table th,
.das-sheet__table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 0.55rem 0.7rem;
    vertical-align: middle;
    text-align: left;
}
.das-sheet__table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid #1e293b;
}
.das-sheet__table tbody tr:nth-child(even) td { background: #f8fafc; }
.das-sheet__table tbody tr:hover td { background: #ecfdf5; }
.das-sheet__table .text-right { text-align: right; }
.das-sheet__cell-strong { color: #0f172a; font-weight: 700; }

.das-sticky-col {
    position: sticky;
    left: 0;
    z-index: 1;
    background: #fff;
    box-shadow: 1px 0 0 #e2e8f0;
    min-width: 11rem;
    max-width: 16rem;
}
.das-sheet__table thead .das-sticky-col {
    z-index: 4;
    background: #0f172a;
    box-shadow: 1px 0 0 #1e293b;
}
.das-sheet__table tbody tr:nth-child(even) .das-sticky-col { background: #f8fafc; }
.das-sheet__table tbody tr:hover .das-sticky-col { background: #ecfdf5; }

.das-muted { color: #64748b; font-weight: 550; }
.das-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.5rem;
    border: 1px solid transparent;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    white-space: nowrap;
}
.das-badge--emerald { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
.das-badge--amber { background: #fffbeb; border-color: #fde68a; color: #b45309; }
.das-badge--slate { background: #f1f5f9; border-color: #cbd5e1; color: #475569; }
.das-badge--muted { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.das-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.7rem;
    border-radius: 0.5rem;
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.das-btn:hover { background: #059669; border-color: #059669; }
.das-btn--block { width: 100%; }

.dash-eff-id { display: flex; align-items: center; gap: 0.55rem; min-width: 0; }
.dash-eff-id__avatar {
    width: 2.85rem;
    height: 3.5rem;
    border-radius: 0.4rem;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: 800;
    color: #1e293b;
}
.dash-eff-id__avatar img { width: 100%; height: 100%; object-fit: cover; }
.dash-eff-id__text { min-width: 0; display: flex; flex-direction: column; gap: 0.08rem; }
.dash-eff-id__name {
    font-weight: 800;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 13rem;
}
.dash-eff-id__meta {
    font-size: 0.7rem;
    font-weight: 550;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 13rem;
}
.dash-eff-callsign {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #1e293b;
}
.dash-eff-unit {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    max-width: 100%;
    cursor: help;
    border-bottom: 1px dotted #94a3b8;
}
.dash-eff-unit:focus {
    outline: 2px solid #059669;
    outline-offset: 2px;
    border-radius: 0.2rem;
}
.dash-eff-unit__hint {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 0.95rem;
    height: 0.95rem;
    border-radius: 999px;
    border: 1px solid #94a3b8;
    color: #475569;
    font-size: 0.625rem;
    font-weight: 800;
    font-style: normal;
    line-height: 1;
    flex-shrink: 0;
}
.dash-eff-playtime {
    font-variant-numeric: tabular-nums;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f766e;
    white-space: nowrap;
}
.dash-eff-seniority {
    font-variant-numeric: tabular-nums;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #1e3a5f;
    white-space: nowrap;
}

@media (max-width: 1099.98px) {
    .das-hide-md { display: none !important; }
}
@media (max-width: 1399.98px) {
    .das-hide-lg { display: none !important; }
}
@media (max-width: 899.98px) {
    .das-hide-sm { display: none !important; }
    .das-sheet--effectifs .das-sheet__table { min-width: 24rem; }
}
@media (max-width: 719.98px) {
    .das-cards { display: flex; }
    .das-sheet,
    .das-scroll-hint { display: none !important; }
    .das-card__actions { width: 100%; }
    .das-card__cta { flex: 1 1 auto; justify-content: center; }
}
</style>
