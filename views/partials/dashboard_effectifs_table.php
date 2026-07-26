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
    $override = trim((string) ($row['rank_display_override'] ?? ''));
    if ($override !== '') {
        return $override;
    }
    $rp = trim((string) ($row['rank_display'] ?? ''));
    if ($rp !== '') {
        return $rp;
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
    <article class="das-card" aria-labelledby="dash-effectifs-title">
        <div class="das-card__head">
            <div>
                <p class="das-kicker">Effectifs</p>
                <h2 id="dash-effectifs-title" class="das-card__title">Tableau rapide — <?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <?php if ($canOpenWorkspace): ?>
                    <a href="<?= htmlspecialchars($workspaceUrl, ENT_QUOTES, 'UTF-8') ?>" class="das-card__link">Bureau effectifs</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($directoryUrl, ENT_QUOTES, 'UTF-8') ?>" class="das-card__link">Annuaire complet</a>
            </div>
        </div>

        <?php if ($rowCount === 0): ?>
            <div class="das-empty">
                <p>Aucun membre à afficher pour le moment dans cette communauté.</p>
            </div>
        <?php else: ?>
            <p class="das-scroll-hint">Faites défiler horizontalement pour voir toutes les colonnes.</p>
            <div class="das-sheet">
                <table class="das-sheet__table">
                    <thead>
                        <tr>
                            <th scope="col">Membre</th>
                            <th scope="col">Indicatif</th>
                            <th scope="col">Grade</th>
                            <th scope="col">Affectation</th>
                            <th scope="col">Fonction</th>
                            <th scope="col">Temps en mission</th>
                            <?php if ($canSeeInactive): ?>
                            <th scope="col">Statut</th>
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
                            $character = trim((string) ($row['character_name'] ?? ''));
                            $slug = trim((string) ($row['profile_slug'] ?? ''));
                            $target = $slug !== '' ? $slug : (string) $uid;
                            $ficheUrl = url('personnel/' . $target);
                            $avatarRaw = trim((string) ($row['avatar_url'] ?? ''));
                            $avatar = $avatarRaw !== '' && function_exists('user_media_public_url')
                                ? (string) (user_media_public_url($avatarRaw) ?? '')
                                : $avatarRaw;
                            $gradeLabel = $gradeLabelFor($row);
                            $unitName = trim((string) ($row['unit_name'] ?? ''));
                            $unitCode = trim((string) ($row['unit_code'] ?? ''));
                            $assignment = $unitName !== '' ? $unitName : ($unitCode !== '' ? $unitCode : '');
                            $primaryRole = trim((string) ($row['primary_role'] ?? ''));
                            $status = trim((string) ($row['status'] ?? ''));
                            $playtimeLabel = trim((string) ($row['arma_playtime_label'] ?? ''));
                            $playtimeSeconds = (int) ($row['arma_playtime_seconds'] ?? 0);
                            if ($playtimeLabel === '' && $playtimeSeconds > 0 && function_exists('format_arma_playtime_french')) {
                                $playtimeLabel = format_arma_playtime_french($playtimeSeconds);
                            }
                            ?>
                            <tr>
                                <td>
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
                                                <span class="dash-eff-id__meta">RP · <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($callsign !== ''): ?>
                                        <span class="dash-eff-callsign"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="das-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($gradeLabel !== ''): ?>
                                        <?= htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="das-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($assignment !== ''): ?>
                                        <?= htmlspecialchars($assignment, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="das-muted">Non affecté</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($primaryRole !== ''): ?>
                                        <?= htmlspecialchars($primaryRole, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="das-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($playtimeLabel !== ''): ?>
                                        <span class="dash-eff-playtime" title="Temps de mission transmis par la liaison terrain"><?= htmlspecialchars($playtimeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="das-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canSeeInactive): ?>
                                <td>
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
.das-stack { display: flex; flex-direction: column; gap: 1.5rem; width: 100%; }
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
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    padding: 0.95rem 1.1rem;
    border-bottom: 1px solid #f1f5f9;
}
.das-kicker {
    margin: 0;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #059669;
}
.das-card__title {
    margin: 0.15rem 0 0;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #0f172a;
    line-height: 1.2;
}
.das-card__link {
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #059669;
    text-decoration: none;
    white-space: nowrap;
}
.das-card__link:hover { color: #065f46; text-decoration: underline; }
.das-card__foot {
    margin: 0;
    padding: 0.65rem 1.1rem 0.9rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #94a3b8;
    border-top: 1px solid #f8fafc;
}
.das-empty {
    margin: 1rem;
    border-radius: 0.75rem;
    border: 1px dashed #cbd5e1;
    background: #f8fafc;
    padding: 1.25rem 1rem;
    text-align: center;
}
.das-empty p { margin: 0; font-size: 0.8125rem; font-weight: 550; color: #334155; }
.das-scroll-hint {
    margin: 0.6rem 1.1rem 0;
    font-size: 0.6875rem;
    font-weight: 600;
    color: #94a3b8;
}
@media (min-width: 1280px) {
    .das-scroll-hint { display: none; }
}
.das-sheet { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.das-sheet__table {
    width: 100%;
    min-width: <?= $canSeeInactive ? '58rem' : '50rem' ?>;
    border-collapse: collapse;
    font-size: 0.8125rem;
}
.das-sheet__table th {
    padding: 0.65rem 1rem;
    text-align: left;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.das-sheet__table td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #0f172a;
    font-weight: 550;
}
.das-sheet__table tbody tr:hover { background: #f8fafc; }
.das-sheet__table .text-right { text-align: right; }
.das-muted { color: #94a3b8; font-weight: 500; font-style: italic; }
.das-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.55rem;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    white-space: nowrap;
}
.das-badge--emerald { background: #ecfdf5; color: #047857; }
.das-badge--amber { background: #fffbeb; color: #b45309; }
.das-badge--slate { background: #f1f5f9; color: #475569; }
.das-badge--muted { background: #f8fafc; color: #64748b; }
.das-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.7rem;
    border-radius: 0.55rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #0f172a;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-decoration: none;
    white-space: nowrap;
}
.das-btn:hover { border-color: #059669; color: #047857; background: #ecfdf5; }
.dash-eff-id { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
.dash-eff-id__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 0.55rem;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
    overflow: hidden;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.625rem;
    font-weight: 800;
    color: #64748b;
}
.dash-eff-id__avatar img { width: 100%; height: 100%; object-fit: cover; }
.dash-eff-id__text { min-width: 0; display: flex; flex-direction: column; gap: 0.1rem; }
.dash-eff-id__name {
    font-weight: 800;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 14rem;
}
.dash-eff-id__meta {
    font-size: 0.6875rem;
    font-weight: 550;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 14rem;
}
.dash-eff-callsign {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: #334155;
}
.dash-eff-playtime {
    font-variant-numeric: tabular-nums;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f766e;
    white-space: nowrap;
}
</style>
