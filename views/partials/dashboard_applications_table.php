<?php
declare(strict_types=1);

/**
 * Tableau plein page « Demandes de candidature » — dashboard membre/staff.
 * Deux tableaux possibles selon le rôle, même densité de colonnes :
 *  - Staff RH/recrutement : toutes les candidatures de la communauté (tous statuts),
 *    enrichies des signaux du bureau recrutement (délai, instructeur, affectation, bilan).
 *  - Tout utilisateur : ses propres candidatures déposées (toutes communautés, tous statuts),
 *    enrichies des informations utiles au suivi (poste visé, canal, partage, retour).
 *
 * @var list<array<string,mixed>> $my_applications_all
 * @var list<array<string,mixed>> $staff_applications_all
 * @var bool $show_staff_enlistments
 * @var string|null $dashboard_tenant_label
 */

$myApps = $my_applications_all ?? [];
$staffApps = $staff_applications_all ?? [];
$showStaff = !empty($show_staff_enlistments);
$unitLabel = trim((string) ($dashboard_tenant_label ?? '')) !== '' ? (string) $dashboard_tenant_label : 'Votre communauté';

if ($myApps === [] && ($staffApps === [] || !$showStaff)) {
    return;
}

$initials = static function (string $first, string $last): string {
    $a = mb_strtoupper(mb_substr(trim($first), 0, 1));
    $b = mb_strtoupper(mb_substr(trim($last), 0, 1));
    if ($a === '' && $b === '') {
        return '?';
    }

    return $a . $b;
};

$statusMeta = static function (string $st): array {
    return match ($st) {
        'submitted' => ['label' => 'À traiter', 'class' => 'das-badge--amber'],
        'reviewed' => ['label' => 'Acceptée', 'class' => 'das-badge--emerald'],
        'rejected' => ['label' => 'Refusée', 'class' => 'das-badge--rose'],
        'blocked' => ['label' => 'Non admis', 'class' => 'das-badge--slate'],
        default => ['label' => 'À vérifier', 'class' => 'das-badge--muted'],
    };
};

$stepLabel = static function (string $st, bool $hasReviewer): string {
    return match ($st) {
        'submitted' => $hasReviewer ? 'Instruction en cours' : 'Réception du dossier',
        'reviewed' => 'Adhésion en cours',
        'rejected', 'blocked' => 'Dossier clos',
        default => '—',
    };
};

$fmtDateParts = static function (?string $raw): array {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return ['—', ''];
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return ['—', ''];
    }

    return [date('d/m/Y', $ts), date('H:i', $ts)];
};

$fmtHoursShort = static function (int $hours): string {
    if ($hours < 24) {
        return $hours . ' h';
    }
    $days = intdiv($hours, 24);
    $rem = $hours % 24;

    return $rem > 0 ? $days . ' j ' . $rem . ' h' : $days . ' j';
};

$submittedViaLabel = static function (string $raw): string {
    return match (strtolower(trim($raw))) {
        'guest' => 'Invité (sans compte)',
        'account' => 'Compte connecté',
        'preset' => 'Profil enregistré',
        '' => '—',
        default => 'Autre canal',
    };
};

$openingLabel = static function (array $row, bool $crossTenant): string {
    $title = trim((string) ($crossTenant ? ($row['opening_title'] ?? '') : ($row['assignment_opening_title'] ?? '')));
    if ($title !== '') {
        return $title;
    }
    $sp = trim((string) ($row['specialty'] ?? ''));

    return $sp !== '' ? $sp : '—';
};

$slaInfo = static function (array $row) use ($fmtHoursShort): array {
    $status = (string) ($row['status'] ?? '');
    if ($status !== 'submitted') {
        return ['label' => 'Dossier instruit', 'class' => 'das-badge--muted', 'meta' => ''];
    }
    $ageHours = isset($row['submitted_age_hours']) && $row['submitted_age_hours'] !== null ? (int) $row['submitted_age_hours'] : null;
    if ($ageHours === null) {
        return ['label' => '—', 'class' => 'das-badge--muted', 'meta' => ''];
    }
    $slaHours = isset($row['enlistment_sla_hours']) ? max(1, (int) $row['enlistment_sla_hours']) : 72;
    if (!empty($row['submitted_sla_breached'])) {
        $over = max(0, $ageHours - $slaHours);

        return ['label' => 'En retard', 'class' => 'das-badge--rose', 'meta' => $fmtHoursShort($ageHours) . ' · seuil ' . $slaHours . ' h (+' . $fmtHoursShort($over) . ')'];
    }
    $watchFrom = max(0, $slaHours - max(1, (int) ceil($slaHours / 4)));
    if ($ageHours >= $watchFrom) {
        return ['label' => 'À surveiller', 'class' => 'das-badge--amber', 'meta' => $fmtHoursShort($ageHours) . ' / ' . $slaHours . ' h'];
    }

    return ['label' => 'Dans les délais', 'class' => 'das-badge--emerald', 'meta' => $fmtHoursShort($ageHours) . ' / ' . $slaHours . ' h'];
};

$retroInfo = static function (array $row): array {
    $st = (string) ($row['staff_retro_status'] ?? '');
    $doneAt = trim((string) ($row['staff_retro_done_at'] ?? ''));
    $ageDays = isset($row['enlistment_age_days']) && $row['enlistment_age_days'] !== null ? (int) $row['enlistment_age_days'] : null;

    return match ($st) {
        'done' => ['label' => 'Fait', 'class' => 'das-badge--emerald', 'meta' => $doneAt !== '' ? ('Le ' . date('d/m/Y', strtotime($doneAt) ?: time())) : ''],
        'due' => ['label' => 'À faire', 'class' => 'das-badge--rose', 'meta' => ($ageDays !== null ? $ageDays . ' j' : '30 j+') . ' sans bilan'],
        'not_applicable' => ['label' => 'Non concerné', 'class' => 'das-badge--muted', 'meta' => ''],
        'unavailable' => ['label' => 'Indisponible', 'class' => 'das-badge--muted', 'meta' => ''],
        'waiting' => ['label' => 'Pas encore', 'class' => 'das-badge--muted', 'meta' => $ageDays !== null ? ('Dans ' . max(0, 30 - $ageDays) . ' j') : ''],
        default => ['label' => '—', 'class' => 'das-badge--muted', 'meta' => ''],
    };
};

$consentInfo = static function (array $row): array {
    $raw = trim((string) ($row['consent_sharing_at'] ?? ''));

    return $raw !== ''
        ? ['label' => 'Partage autorisé', 'class' => 'das-badge--emerald']
        : ['label' => 'Non renseigné', 'class' => 'das-badge--muted'];
};

$portalManualText = static function (array $row): ?string {
    $mode = (string) ($row['candidate_portal_status_mode'] ?? 'steps');
    if ($mode !== 'manual') {
        return null;
    }
    $txt = trim((string) ($row['candidate_portal_status_manual_text'] ?? ''));

    return $txt !== '' ? $txt : null;
};

$bandBadgeClass = static function (string $band): string {
    return match (strtolower(trim($band))) {
        'emerald' => 'das-badge--emerald',
        'rose' => 'das-badge--rose',
        'slate' => 'das-badge--slate',
        'sky' => 'das-badge--sky',
        default => 'das-badge--amber',
    };
};

$renderTable = static function (
    string $sectionId,
    string $kicker,
    string $title,
    string $emptyText,
    array $rows,
    bool $crossTenant,
    ?string $viewAllHref,
    ?string $viewAllLabel,
    string $unitLabel,
) use (
    $initials,
    $statusMeta,
    $stepLabel,
    $fmtDateParts,
    $submittedViaLabel,
    $openingLabel,
    $slaInfo,
    $retroInfo,
    $consentInfo,
    $portalManualText,
    $bandBadgeClass,
): void {
    ?>
    <section id="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8') ?>" class="das-card">
        <div class="das-card__head">
            <div>
                <p class="das-kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></p>
                <h2 class="das-card__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
            </div>
            <?php if ($viewAllHref !== null && $rows !== []): ?>
                <a href="<?= htmlspecialchars($viewAllHref, ENT_QUOTES, 'UTF-8') ?>" class="das-card__link"><?= htmlspecialchars($viewAllLabel ?? 'Tout voir', ENT_QUOTES, 'UTF-8') ?> →</a>
            <?php endif; ?>
        </div>
        <?php if ($rows === []): ?>
            <div class="das-empty">
                <p><?= htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php else: ?>
            <p class="das-scroll-hint">Faites défiler horizontalement pour voir toutes les colonnes.</p>
            <div class="das-sheet">
                <table class="das-sheet__table">
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Statut</th>
                            <th>Étape</th>
                            <th>Poste visé</th>
                            <?php if ($crossTenant): ?>
                                <th>Communauté</th>
                                <th>Canal</th>
                                <th>Partage des données</th>
                                <th>Suivi personnalisé</th>
                                <th>Retour du recruteur</th>
                            <?php else: ?>
                                <th>Affectation prévue</th>
                                <th>Instructeur</th>
                                <th>Canal</th>
                                <th>Délai / alerte</th>
                                <th>Bilan d’équipe</th>
                                <th>Motif / commentaire</th>
                            <?php endif; ?>
                            <th>Déposée le</th>
                            <th>Dernière action</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $fn = (string) ($row['first_name'] ?? '');
                            $ln = (string) ($row['last_name'] ?? '');
                            $full = trim($fn . ' ' . $ln) !== '' ? trim($fn . ' ' . $ln) : 'Candidat';
                            $email = trim((string) ($row['email'] ?? ''));
                            $callsign = trim((string) ($row['callsign'] ?? ''));
                            $st = (string) ($row['status'] ?? '');
                            $meta = $statusMeta($st);
                            $hasReviewer = (int) ($row['reviewed_by'] ?? 0) > 0;
                            $step = $stepLabel($st, $hasReviewer);
                            $community = $crossTenant
                                ? trim((string) ($row['tenant_name'] ?? '')) ?: 'Communauté'
                                : $unitLabel;
                            [$createdDate, $createdTime] = $fmtDateParts((string) ($row['created_at'] ?? ''));
                            $lastAction = trim((string) ($row['updated_at'] ?? ''));
                            if ($lastAction === '') {
                                $lastAction = trim((string) ($row['created_at'] ?? ''));
                            }
                            [$lastActionDate, $lastActionTime] = $fmtDateParts($lastAction);
                            $openHref = $crossTenant
                                ? (is_string($row['candidate_portal_href'] ?? null) ? (string) $row['candidate_portal_href'] : null)
                                : url('back-office/recruitments/' . (int) ($row['id'] ?? 0) . '?dossier=1');
                            $openLabel = $crossTenant ? 'Ouvrir mon suivi' : 'Ouvrir le dossier';
                            $opening = $openingLabel($row, $crossTenant);
                            $canal = $submittedViaLabel((string) ($row['submitted_via'] ?? ''));
                            $comment = trim((string) ($row['reviewer_comment'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <div class="das-sheet__who">
                                        <span class="das-sheet__avatar"><?= htmlspecialchars($initials($fn, $ln), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="das-sheet__who-text">
                                            <span class="das-sheet__name"><?= htmlspecialchars($full, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($email !== ''): ?>
                                                <span class="das-sheet__mail"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if ($callsign !== ''): ?>
                                                <span class="das-sheet__mail">Indicatif : <?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="das-badge <?= htmlspecialchars($meta['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="das-sheet__muted"><?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="das-sheet__muted das-sheet__truncate" title="<?= htmlspecialchars($opening, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($opening, ENT_QUOTES, 'UTF-8') ?></td>
                                <?php if ($crossTenant): ?>
                                    <td class="das-sheet__muted"><?= htmlspecialchars($community, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="das-sheet__muted"><?= htmlspecialchars($canal, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php $consent = $consentInfo($row); ?>
                                        <span class="das-badge <?= htmlspecialchars($consent['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($consent['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="das-sheet__truncate">
                                        <?php $manualTxt = $portalManualText($row); ?>
                                        <?php if ($manualTxt !== null): ?>
                                            <span class="das-badge <?= htmlspecialchars($bandBadgeClass((string) ($row['candidate_portal_status_manual_band'] ?? 'amber')), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($manualTxt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($manualTxt, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="das-sheet__muted">Aligné sur les étapes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-sheet__muted das-sheet__truncate" title="<?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $comment !== '' ? htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') : '—' ?>
                                    </td>
                                <?php else: ?>
                                    <td class="das-sheet__truncate">
                                        <?php
                                        $assignUnit = trim((string) ($row['assignment_unit_label'] ?? ''));
                                        $assignRole = trim((string) ($row['assignment_role_label'] ?? ''));
                                        ?>
                                        <?php if ($assignUnit !== '' || $assignRole !== ''): ?>
                                            <span class="das-sheet__strong"><?= htmlspecialchars($assignUnit !== '' ? $assignUnit : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($assignRole !== ''): ?>
                                                <span class="das-sheet__meta"><?= htmlspecialchars($assignRole, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="das-sheet__muted">Non définie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-sheet__muted das-sheet__truncate">
                                        <?php $instructor = trim((string) ($row['instructor_label'] ?? '')); ?>
                                        <?= $instructor !== '' ? htmlspecialchars($instructor, ENT_QUOTES, 'UTF-8') : 'Non désigné' ?>
                                    </td>
                                    <td class="das-sheet__muted"><?= htmlspecialchars($canal, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php $sla = $slaInfo($row); ?>
                                        <span class="das-badge <?= htmlspecialchars($sla['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sla['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($sla['meta'] !== ''): ?>
                                            <span class="das-sheet__meta"><?= htmlspecialchars($sla['meta'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $retro = $retroInfo($row); ?>
                                        <span class="das-badge <?= htmlspecialchars($retro['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($retro['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($retro['meta'] !== ''): ?>
                                            <span class="das-sheet__meta"><?= htmlspecialchars($retro['meta'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-sheet__muted das-sheet__truncate" title="<?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $comment !== '' ? htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td class="das-sheet__muted das-sheet__nowrap">
                                    <?= htmlspecialchars($createdDate, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($createdTime !== ''): ?><span class="das-sheet__meta"><?= htmlspecialchars($createdTime, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </td>
                                <td class="das-sheet__muted das-sheet__nowrap">
                                    <?= htmlspecialchars($lastActionDate, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($lastActionTime !== ''): ?><span class="das-sheet__meta"><?= htmlspecialchars($lastActionTime, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </td>
                                <td class="text-right das-sheet__nowrap">
                                    <?php if ($openHref !== null): ?>
                                        <a href="<?= htmlspecialchars($openHref, ENT_QUOTES, 'UTF-8') ?>" class="das-btn"><?= htmlspecialchars($openLabel, ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php else: ?>
                                        <span class="das-sheet__muted">Indisponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php
};
?>
<div class="das-stack">
    <?php if ($showStaff && $staffApps !== []): ?>
        <?php $renderTable(
            'dashboard-applications-staff',
            'Recrutement',
            'Toutes les candidatures de la communauté',
            'Aucune candidature reçue pour le moment.',
            $staffApps,
            false,
            url('back-office/recruitments'),
            'Ouvrir le bureau recrutement',
            $unitLabel,
        ); ?>
    <?php endif; ?>

    <?php if ($myApps !== []): ?>
        <?php $renderTable(
            'dashboard-applications-mine',
            'Mon parcours',
            'Mes candidatures',
            'Vous n’avez déposé aucune candidature pour le moment.',
            $myApps,
            true,
            null,
            null,
            $unitLabel,
        ); ?>
    <?php endif; ?>
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

.das-sheet {
    border-top: 1px solid #e2e8f0;
    margin-top: 0.6rem;
    overflow: auto;
    max-height: min(32rem, 68vh);
}
.das-sheet__table {
    width: 100%;
    min-width: 84rem;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
    line-height: 1.35;
}
.das-sheet__table th,
.das-sheet__table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 0.5rem 0.75rem;
    vertical-align: middle;
    text-align: left;
}
.das-sheet__table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc;
    color: #475569;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid #cbd5e1;
}
.das-sheet__table tbody tr:nth-child(even) td { background: #fbfdfc; }
.das-sheet__table tbody tr:hover td { background: #f0fdf4; }
.das-sheet__table td.text-right, .das-sheet__table th.text-right { text-align: right; }
.das-sheet__nowrap { white-space: nowrap; }
.das-sheet__muted { color: #475569; }
.das-sheet__strong { display: block; font-weight: 700; color: #0f172a; white-space: nowrap; }
.das-sheet__truncate { max-width: 13rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.das-sheet__meta { display: block; margin-top: 0.1rem; font-size: 0.6875rem; color: #64748b; font-variant-numeric: tabular-nums; white-space: nowrap; }
.das-sheet__who { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
.das-sheet__avatar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    background: #f1f5f9;
    font-size: 0.625rem;
    font-weight: 800;
    color: #334155;
}
.das-sheet__who-text { display: flex; flex-direction: column; min-width: 0; gap: 0.05rem; }
.das-sheet__name { font-weight: 700; color: #0f172a; white-space: nowrap; }
.das-sheet__mail { font-size: 0.7rem; color: #64748b; white-space: nowrap; }

.das-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.22rem 0.6rem;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    white-space: nowrap;
    max-width: 12rem;
    overflow: hidden;
    text-overflow: ellipsis;
}
.das-badge--amber { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.das-badge--emerald { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.das-badge--rose { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
.das-badge--slate { background: #f1f5f9; border-color: #cbd5e1; color: #334155; }
.das-badge--sky { background: #f0f9ff; border-color: #bae6fd; color: #075985; }
.das-badge--muted { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }

.das-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.das-btn:hover { background: #059669; border-color: #059669; }
</style>
