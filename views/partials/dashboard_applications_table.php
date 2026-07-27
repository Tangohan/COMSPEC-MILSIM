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

$stepLabel = static function (string $st, bool $hasReviewer, bool $memberLinked): string {
    return match ($st) {
        'submitted' => $hasReviewer ? 'Instruction en cours' : 'Réception du dossier',
        'reviewed' => $memberLinked ? 'Terminé et validé' : 'Adhésion en cours',
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

$renderRowCells = static function (
    array $row,
    bool $crossTenant,
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
): array {
    $fn = (string) ($row['first_name'] ?? '');
    $ln = (string) ($row['last_name'] ?? '');
    $full = trim($fn . ' ' . $ln) !== '' ? trim($fn . ' ' . $ln) : 'Candidat';
    $email = trim((string) ($row['email'] ?? ''));
    $callsign = trim((string) ($row['callsign'] ?? ''));
    $st = (string) ($row['status'] ?? '');
    $meta = $statusMeta($st);
    $hasReviewer = (int) ($row['reviewed_by'] ?? 0) > 0;
    $memberLinked = (int) ($row['submitter_user_id'] ?? 0) > 0;
    $step = $stepLabel($st, $hasReviewer, $memberLinked);
    $community = $crossTenant
        ? trim((string) ($row['tenant_name'] ?? '')) ?: 'Communauté'
        : $unitLabel;
    [$createdDate, $createdTime] = $fmtDateParts((string) ($row['created_at'] ?? ''));
    $createdTs = strtotime((string) ($row['created_at'] ?? ''));
    $isRecent = $createdTs !== false && $createdTs >= strtotime('-7 days');
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
    $assignUnit = trim((string) ($row['assignment_unit_label'] ?? ''));
    $assignRole = trim((string) ($row['assignment_role_label'] ?? ''));
    $instructor = trim((string) ($row['instructor_label'] ?? ''));
    $sla = $slaInfo($row);
    $retro = $retroInfo($row);
    $consent = $consentInfo($row);
    $manualTxt = $portalManualText($row);
    $ini = $initials($fn, $ln);

    return compact(
        'fn', 'ln', 'full', 'email', 'callsign', 'st', 'meta', 'memberLinked', 'step', 'community',
        'createdDate', 'createdTime', 'isRecent', 'lastActionDate', 'lastActionTime',
        'openHref', 'openLabel', 'opening', 'canal', 'comment',
        'assignUnit', 'assignRole', 'instructor', 'sla', 'retro', 'consent', 'manualTxt', 'ini', 'row'
    );
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
) use ($renderRowCells, $bandBadgeClass): void {
    $count = count($rows);
    $sheetClass = $crossTenant ? 'das-sheet das-sheet--mine' : 'das-sheet das-sheet--staff';
    ?>
    <section id="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8') ?>" class="das-card<?= $crossTenant ? ' das-card--mine' : ' das-card--staff' ?>">
        <div class="das-card__head">
            <div class="das-card__head-main">
                <p class="das-kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="das-card__title-row">
                    <h2 class="das-card__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
                    <?php if ($count > 0): ?>
                        <span class="das-card__count" aria-label="<?= (int) $count ?> candidature<?= $count > 1 ? 's' : '' ?>">
                            <?= (int) $count ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($viewAllHref !== null && $rows !== []): ?>
                <a href="<?= htmlspecialchars($viewAllHref, ENT_QUOTES, 'UTF-8') ?>" class="das-card__cta">
                    <?= htmlspecialchars($viewAllLabel ?? 'Tout voir', ENT_QUOTES, 'UTF-8') ?>
                    <span aria-hidden="true">→</span>
                </a>
            <?php endif; ?>
        </div>
        <?php if ($rows === []): ?>
            <div class="das-empty">
                <p><?= htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php else: ?>
            <p class="das-scroll-hint">Faites défiler horizontalement pour les colonnes secondaires.</p>

            <!-- Mobile : cartes empilées -->
            <ul class="das-cards" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $d = $renderRowCells($row, $crossTenant, $unitLabel);
                    $cardMeta = [];
                    if ($crossTenant) {
                        $cardMeta[] = $d['community'];
                    }
                    $cardMeta[] = $d['opening'] !== '—' ? $d['opening'] : null;
                    $cardMeta[] = $d['createdDate'] !== '—' ? ('Déposée le ' . $d['createdDate']) : null;
                    $cardMeta = array_values(array_filter($cardMeta));
                    ?>
                    <li class="das-cards__item">
                        <div class="das-cards__top">
                            <div class="das-sheet__who">
                                <span class="das-sheet__avatar"><?= htmlspecialchars($d['ini'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="das-sheet__who-text">
                                    <span class="das-sheet__name">
                                        <?= htmlspecialchars($d['full'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($d['isRecent']): ?><span class="das-badge das-badge--sky das-badge--new">Nouveau</span><?php endif; ?>
                                    </span>
                                    <?php if ($cardMeta !== []): ?>
                                        <span class="das-sheet__mail"><?= htmlspecialchars(implode(' · ', $cardMeta), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="das-badge <?= htmlspecialchars($d['meta']['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['meta']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <p class="das-cards__step"><?= htmlspecialchars($d['step'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!$crossTenant && $d['sla']['label'] !== '—'): ?>
                            <p class="das-cards__sla">
                                <span class="das-badge <?= htmlspecialchars($d['sla']['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['sla']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($d['sla']['meta'] !== ''): ?>
                                    <span class="das-sheet__meta das-sheet__meta--inline"><?= htmlspecialchars($d['sla']['meta'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($d['openHref'] !== null): ?>
                            <a href="<?= htmlspecialchars($d['openHref'], ENT_QUOTES, 'UTF-8') ?>" class="das-btn das-btn--block"><?= htmlspecialchars($d['openLabel'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="<?= htmlspecialchars($sheetClass, ENT_QUOTES, 'UTF-8') ?>">
                <table class="das-sheet__table">
                    <colgroup>
                        <col class="das-col-w-who">
                        <col class="das-col-w-status">
                        <col class="das-col-w-step das-hide-sm">
                        <col class="das-col-w-link das-hide-lg">
                        <col class="das-col-w-post">
                        <?php if ($crossTenant): ?>
                            <col class="das-col-w-comm">
                            <col class="das-col-w-canal das-hide-md">
                            <col class="das-col-w-share das-hide-lg">
                            <col class="das-col-w-follow das-hide-lg">
                            <col class="das-col-w-comment das-hide-md">
                        <?php else: ?>
                            <col class="das-col-w-assign das-hide-sm">
                            <col class="das-col-w-inst das-hide-md">
                            <col class="das-col-w-canal das-hide-lg">
                            <col class="das-col-w-sla">
                            <col class="das-col-w-retro das-hide-md">
                            <col class="das-col-w-comment das-hide-lg">
                        <?php endif; ?>
                        <col class="das-col-w-date das-hide-sm">
                        <col class="das-col-w-date das-hide-lg">
                        <col class="das-col-w-act">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="das-sticky-col">Candidat</th>
                            <th>Statut</th>
                            <th class="das-hide-sm">Étape</th>
                            <th class="das-hide-lg">Rattachement</th>
                            <th>Poste visé</th>
                            <?php if ($crossTenant): ?>
                                <th>Communauté</th>
                                <th class="das-hide-md">Canal</th>
                                <th class="das-hide-lg">Partage</th>
                                <th class="das-hide-lg">Suivi</th>
                                <th class="das-hide-md">Retour</th>
                            <?php else: ?>
                                <th class="das-hide-sm">Affectation</th>
                                <th class="das-hide-md">Instructeur</th>
                                <th class="das-hide-lg">Canal</th>
                                <th>Délai</th>
                                <th class="das-hide-md">Bilan</th>
                                <th class="das-hide-lg">Motif</th>
                            <?php endif; ?>
                            <th class="das-hide-sm">Déposée le</th>
                            <th class="das-hide-lg">Dernière action</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $d = $renderRowCells($row, $crossTenant, $unitLabel); ?>
                            <tr>
                                <td class="das-sticky-col">
                                    <div class="das-sheet__who">
                                        <span class="das-sheet__avatar"><?= htmlspecialchars($d['ini'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="das-sheet__who-text">
                                            <span class="das-sheet__name">
                                                <?= htmlspecialchars($d['full'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($d['isRecent']): ?><span class="das-badge das-badge--sky das-badge--new" title="Déposée il y a moins de 7 jours">Nouveau</span><?php endif; ?>
                                            </span>
                                            <?php if ($d['email'] !== ''): ?>
                                                <span class="das-sheet__mail"><?= htmlspecialchars($d['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if ($d['callsign'] !== ''): ?>
                                                <span class="das-sheet__mail">Indicatif : <?= htmlspecialchars($d['callsign'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><span class="das-badge <?= htmlspecialchars($d['meta']['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['meta']['label'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="das-sheet__muted das-hide-sm"><?= htmlspecialchars($d['step'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="das-hide-lg">
                                    <?php if ($d['memberLinked']): ?>
                                        <span class="das-badge das-badge--emerald" title="Compte membre rattaché à la candidature">Rattaché</span>
                                    <?php else: ?>
                                        <span class="das-badge das-badge--rose" title="Aucun compte membre rattaché">À rattacher</span>
                                    <?php endif; ?>
                                </td>
                                <td class="das-sheet__cell-strong das-sheet__truncate" title="<?= htmlspecialchars($d['opening'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['opening'], ENT_QUOTES, 'UTF-8') ?></td>
                                <?php if ($crossTenant): ?>
                                    <td class="das-sheet__cell-strong"><?= htmlspecialchars($d['community'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="das-sheet__muted das-hide-md"><?= htmlspecialchars($d['canal'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="das-hide-lg">
                                        <span class="das-badge <?= htmlspecialchars($d['consent']['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['consent']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="das-sheet__truncate das-hide-lg">
                                        <?php if ($d['manualTxt'] !== null): ?>
                                            <span class="das-badge <?= htmlspecialchars($bandBadgeClass((string) ($d['row']['candidate_portal_status_manual_band'] ?? 'amber')), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($d['manualTxt'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['manualTxt'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="das-sheet__muted">Aligné sur les étapes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-sheet__muted das-sheet__truncate das-hide-md" title="<?= htmlspecialchars($d['comment'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $d['comment'] !== '' ? htmlspecialchars($d['comment'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                    </td>
                                <?php else: ?>
                                    <td class="das-sheet__truncate das-hide-sm">
                                        <?php if ($d['assignUnit'] !== '' || $d['assignRole'] !== ''): ?>
                                            <span class="das-sheet__strong"><?= htmlspecialchars($d['assignUnit'] !== '' ? $d['assignUnit'] : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($d['assignRole'] !== ''): ?>
                                                <span class="das-sheet__meta"><?= htmlspecialchars($d['assignRole'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="das-sheet__muted">Non définie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-sheet__muted das-sheet__truncate das-hide-md">
                                        <?= $d['instructor'] !== '' ? htmlspecialchars($d['instructor'], ENT_QUOTES, 'UTF-8') : 'Non désigné' ?>
                                    </td>
                                    <td class="das-sheet__muted das-hide-lg"><?= htmlspecialchars($d['canal'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="das-badge <?= htmlspecialchars($d['sla']['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['sla']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($d['sla']['meta'] !== ''): ?>
                                            <span class="das-sheet__meta"><?= htmlspecialchars($d['sla']['meta'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-hide-md">
                                        <span class="das-badge <?= htmlspecialchars($d['retro']['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['retro']['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($d['retro']['meta'] !== ''): ?>
                                            <span class="das-sheet__meta"><?= htmlspecialchars($d['retro']['meta'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="das-sheet__muted das-sheet__truncate das-hide-lg" title="<?= htmlspecialchars($d['comment'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= $d['comment'] !== '' ? htmlspecialchars($d['comment'], ENT_QUOTES, 'UTF-8') : '—' ?>
                                    </td>
                                <?php endif; ?>
                                <td class="das-sheet__muted das-sheet__nowrap das-hide-sm">
                                    <?= htmlspecialchars($d['createdDate'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($d['createdTime'] !== ''): ?><span class="das-sheet__meta"><?= htmlspecialchars($d['createdTime'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </td>
                                <td class="das-sheet__muted das-sheet__nowrap das-hide-lg">
                                    <?= htmlspecialchars($d['lastActionDate'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($d['lastActionTime'] !== ''): ?><span class="das-sheet__meta"><?= htmlspecialchars($d['lastActionTime'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </td>
                                <td class="text-right das-sheet__nowrap">
                                    <?php if ($d['openHref'] !== null): ?>
                                        <a href="<?= htmlspecialchars($d['openHref'], ENT_QUOTES, 'UTF-8') ?>" class="das-btn"><?= htmlspecialchars($d['openLabel'], ENT_QUOTES, 'UTF-8') ?></a>
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
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #047857;
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

/* Mobile cards */
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
.das-cards__sla {
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.5rem;
}

.das-sheet {
    border-top: 1px solid #e2e8f0;
    margin-top: 0.55rem;
    overflow: auto;
    max-height: min(32rem, 68vh);
    -webkit-overflow-scrolling: touch;
}
.das-sheet__table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
    line-height: 1.35;
    table-layout: auto;
}
.das-sheet--staff .das-sheet__table { min-width: 42rem; }
.das-sheet--mine .das-sheet__table { min-width: 36rem; }
@media (min-width: 1100px) {
    .das-sheet--staff .das-sheet__table { min-width: 56rem; }
    .das-sheet--mine .das-sheet__table { min-width: 48rem; }
}
@media (min-width: 1400px) {
    .das-sheet--staff .das-sheet__table { min-width: 72rem; }
    .das-sheet--mine .das-sheet__table { min-width: 62rem; }
}

.das-sheet__table th,
.das-sheet__table td {
    border-bottom: 1px solid #e2e8f0;
    padding: 0.55rem 0.65rem;
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
    padding: 0.6rem 0.65rem;
}
.das-sheet__table tbody tr:nth-child(even) td { background: #f8fafc; }
.das-sheet__table tbody tr:hover td { background: #ecfdf5; }
.das-sheet__table td.text-right,
.das-sheet__table th.text-right { text-align: right; }

/* Sticky first column */
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

.das-sheet__nowrap { white-space: nowrap; }
.das-sheet__muted { color: #334155; font-weight: 550; }
.das-sheet__cell-strong { color: #0f172a; font-weight: 650; }
.das-sheet__strong { display: block; font-weight: 700; color: #0f172a; white-space: nowrap; }
.das-sheet__truncate { max-width: 11rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.das-sheet__meta {
    display: block;
    margin-top: 0.12rem;
    font-size: 0.6875rem;
    color: #475569;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.das-sheet__meta--inline { display: inline; margin-top: 0; }
.das-sheet__who { display: flex; align-items: center; gap: 0.55rem; min-width: 0; }
.das-sheet__avatar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.45rem;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    font-size: 0.625rem;
    font-weight: 800;
    color: #1e293b;
}
.das-sheet__who-text { display: flex; flex-direction: column; min-width: 0; gap: 0.05rem; }
.das-sheet__name { font-weight: 800; color: #0f172a; white-space: nowrap; }
.das-sheet__mail {
    font-size: 0.7rem;
    color: #475569;
    font-weight: 550;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 14rem;
}

.das-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    white-space: nowrap;
    max-width: 11rem;
    overflow: hidden;
    text-overflow: ellipsis;
}
.das-badge--amber { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.das-badge--emerald { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.das-badge--rose { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
.das-badge--slate { background: #f1f5f9; border-color: #cbd5e1; color: #334155; }
.das-badge--sky { background: #f0f9ff; border-color: #bae6fd; color: #075985; }
.das-badge--muted { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.das-badge--new { margin-left: 0.3rem; padding: 0.08rem 0.35rem; font-size: 0.5625rem; vertical-align: middle; }

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
.das-btn--block { width: 100%; margin-top: 0.15rem; }

/* Progressive column hiding */
@media (max-width: 1099.98px) {
    .das-hide-md { display: none !important; }
}
@media (max-width: 1399.98px) {
    .das-hide-lg { display: none !important; }
}
@media (max-width: 899.98px) {
    .das-hide-sm { display: none !important; }
    .das-sheet--staff .das-sheet__table,
    .das-sheet--mine .das-sheet__table { min-width: 28rem; }
}

/* Mobile: cards on, table off */
@media (max-width: 719.98px) {
    .das-cards { display: flex; }
    .das-sheet,
    .das-scroll-hint { display: none !important; }
    .das-card__head { padding: 0.9rem 1rem; }
    .das-card__title { font-size: 0.95rem; }
    .das-card__cta { width: 100%; justify-content: center; }
}
</style>
