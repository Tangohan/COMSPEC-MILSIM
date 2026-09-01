<?php
/**
 * Onglet Tableau administratif — grille dense type tableur.
 *
 * Variables attendues depuis file.php (déjà résolues).
 */
$sheetRows = [];

$pushRow = static function (array &$rows, string $section, string $field, string $value, string $detail = '', string $updated = ''): void {
    $missing = 'Donnée manquante';
    $valueOut = trim((string) $value);
    if ($valueOut === '' || $valueOut === '—' || $valueOut === '-') {
        $valueOut = $missing;
    }
    $rows[] = [
        'section' => $section,
        'field' => $field,
        'value' => $valueOut,
        'detail' => $detail,
        'updated' => $updated,
    ];
};

$missingLabel = $missingLabel ?? 'Donnée manquante';
$tableauAdminStandalone = !empty($tableauAdminStandalone ?? false);
$personnelProfile = is_array($personnelProfile ?? null) ? $personnelProfile : [];
$userProfile = is_array($userProfile ?? null) ? $userProfile : [];

$tblFirstName = trim((string) ($civilIdentity['first_name'] ?? ''));
$tblLastName = trim((string) ($civilIdentity['last_name'] ?? ''));
if ($tblFirstName === '' && $tblLastName === '') {
    $charName = trim((string) ($personnelProfile['character_name'] ?? ''));
    if ($charName !== '') {
        $nameParts = preg_split('/\s+/u', $charName, 2) ?: [];
        $tblFirstName = trim((string) ($nameParts[0] ?? $charName));
        $tblLastName = trim((string) ($nameParts[1] ?? ''));
    }
}

$pushRow($sheetRows, 'Identité', 'Prénom', $tblFirstName);
$pushRow($sheetRows, 'Identité', 'Nom', $tblLastName);
if ($tblFirstName === '' && $tblLastName === '') {
    $displayOnly = trim((string) ($targetUser['display_name'] ?? ''));
    if ($displayOnly !== '') {
        $pushRow($sheetRows, 'Identité', 'Libellé du compte', $displayOnly);
    }
}
if (!empty($showMatriculePublic)) {
    $pushRow($sheetRows, 'Identité', 'Matricule', $matricule ? (string) $matricule : '');
}
$pushRow($sheetRows, 'Identité', 'Indicatif radio', $callsign ? (string) $callsign : '');
if (!empty($showEmailInContact) && $athenaIdentifier !== '') {
    $pushRow($sheetRows, 'Identité', 'Identifiant Athena', $athenaIdentifier);
}
if (!empty($canViewCivilSection)) {
    if (!empty($showEmailInContact)) {
        $pushRow($sheetRows, 'Compte', 'E-mail', (string) ($targetUser['email'] ?? ''));
    } else {
        $pushRow($sheetRows, 'Compte', 'E-mail', 'Masqué — réservé à l’administration');
    }
}

$looksLikeEmail = static function (string $value): bool {
    return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', trim($value));
};
$fieldLooksEmail = static function (string $key): bool {
    $k = mb_strtolower($key);

    return str_contains($k, 'email') || str_contains($k, 'e-mail') || str_contains($k, 'mail');
};

// Grade : valeur = libellé attribué ; détail = code court / OTAN s’il diffère.
$gradeValue = $effectiveRankDisplay !== '' ? (string) $effectiveRankDisplay : '';
$gradeDetail = '';
$gradeCodeBesideLocal = trim((string) ($gradeCodeBeside ?? ''));
if ($gradeCodeBesideLocal !== '' && strcasecmp($gradeCodeBesideLocal, $gradeValue) !== 0) {
    $gradeDetail = $gradeCodeBesideLocal;
    if (!empty($gradeOtanCode) && strcasecmp((string) $gradeOtanCode, $gradeValue) !== 0
        && strcasecmp((string) $gradeOtanCode, $gradeCodeBesideLocal) !== 0) {
        $gradeDetail .= ' · ' . (string) $gradeOtanCode;
    }
} elseif (!empty($gradeOtanCode) && $gradeValue !== '' && strcasecmp((string) $gradeOtanCode, $gradeValue) !== 0) {
    $gradeDetail = 'Code OTAN : ' . (string) $gradeOtanCode;
} elseif (!empty($gradeReferenceLabel) && $gradeValue !== '' && strcasecmp((string) $gradeReferenceLabel, $gradeValue) !== 0) {
    $gradeDetail = (string) $gradeReferenceLabel;
}
$pushRow($sheetRows, 'Affectation', 'Grade / rang', $gradeValue, $gradeDetail);
$pushRow($sheetRows, 'Affectation', 'Unité principale', $unitName ? (string) $unitName : '');
if (!empty($commander)) {
    $cmdLabel = trim((string) ($commander['display_name'] ?? '')) ?: trim((string) ($commander['callsign'] ?? ''));
    $pushRow($sheetRows, 'Affectation', 'Chef d’équipe', $cmdLabel !== '' ? $cmdLabel : '');
}
if ($communityRoleLabel !== null) {
    $pushRow($sheetRows, 'Affectation', 'Rôle communauté', (string) $communityRoleLabel);
}
foreach ($personnelJobRoleAssignments as $jra) {
    $jrName = trim((string) ($jra['role_name'] ?? $jra['name'] ?? ''));
    if ($jrName === '') {
        continue;
    }
    $pushRow(
        $sheetRows,
        'Rôles métier',
        $jrName,
        !empty($jra['is_primary']) ? 'Rôle principal' : 'Rôle actif',
        trim((string) ($jra['unit_name'] ?? ''))
    );
}

foreach ($assignments as $asg) {
    $asgUnit = trim((string) ($asg['unit_name'] ?? 'Affectation'));
    $asgRole = trim((string) ($asg['role_name'] ?? $asg['role_label'] ?? ''));
    $asgStart = !empty($asg['started_at']) ? date('d/m/Y', strtotime((string) $asg['started_at']) ?: time()) : '';
    $asgEnd = !empty($asg['ended_at']) ? date('d/m/Y', strtotime((string) $asg['ended_at']) ?: time()) : 'En cours';
    $asgStatus = $personnelAssignmentStatusFr((string) ($asg['status'] ?? ''));
    $pushRow(
        $sheetRows,
        'Affectations actives',
        $asgUnit,
        $asgRole !== '' ? $asgRole : ($asgStatus !== '' ? $asgStatus : 'Active'),
        $asgStatus,
        trim($asgStart . ($asgStart !== '' || $asgEnd !== '' ? ' → ' : '') . $asgEnd)
    );
}

foreach (array_slice($personnelAssignmentHistory, 0, 40) as $hx) {
    $hxUnit = trim((string) ($hx['unit_name'] ?? 'Unité'));
    $hxRole = trim((string) ($hx['role_name'] ?? $hx['role_label'] ?? ''));
    $hxStart = !empty($hx['started_at']) ? date('d/m/Y', strtotime((string) $hx['started_at']) ?: time()) : '';
    $hxEnd = !empty($hx['ended_at']) ? date('d/m/Y', strtotime((string) $hx['ended_at']) ?: time()) : '—';
    $pushRow(
        $sheetRows,
        'Historique d’affectations',
        $hxUnit,
        $hxRole !== '' ? $hxRole : '—',
        '',
        trim($hxStart . ' → ' . $hxEnd)
    );
}

$pushRow($sheetRows, 'Dates', 'Date d’enrôlement', $enlistmentFormatted ? (string) $enlistmentFormatted : '');
if ($accountCreatedDisplay !== null) {
    $pushRow($sheetRows, 'Dates', 'Membre depuis', (string) $accountCreatedDisplay);
}
if ($seniorityGlobal !== null) {
    $pushRow(
        $sheetRows,
        'Dates',
        'Ancienneté globale',
        (string) ($seniorityGlobal['formatted'] ?? ''),
        (string) ($seniorityGlobal['basis_label'] ?? '')
    );
}
if ($privatePersonnelIdentity) {
    $pushRow($sheetRows, 'Compte', 'Statut du compte', $accountStatusFr((string) ($targetUser['status'] ?? '')));
}
$pushRow($sheetRows, 'Opérationnel', 'Habilitation', $clearanceLevel !== '' ? (string) $clearanceLevel : '');
$pushRow($sheetRows, 'Opérationnel', 'Préparation', $readiness !== null ? $readiness . ' %' : '');
$pushRow($sheetRows, 'Opérationnel', 'Déployable', $isDeployableFile ? 'Oui' : 'Non');
$pushRow($sheetRows, 'Opérationnel', 'Complétude du dossier', $completenessScore . ' %');

if ($rpStage !== '') {
    $pushRow($sheetRows, 'Suivi', 'Étape actuelle', $rpStage);
}
if ($rpProgress !== null) {
    $pushRow($sheetRows, 'Suivi', 'Progression', $rpProgress . ' %');
}
if ($rpStatus !== '') {
    $pushRow($sheetRows, 'Suivi', 'Statut de suivi', $rpStatus);
}
if ($rpTutorLabel) {
    $pushRow($sheetRows, 'Suivi', 'Tuteur', (string) $rpTutorLabel);
}

if ($tableauAdminStandalone) {
    $charNameRow = trim((string) ($personnelProfile['character_name'] ?? ''));
    if ($charNameRow !== '') {
        $pushRow($sheetRows, 'Personnage', 'Nom du personnage', $charNameRow);
    }
    $nicknameRow = trim((string) ($personnelProfile['nickname_primary'] ?? ''));
    if ($nicknameRow !== '') {
        $pushRow($sheetRows, 'Personnage', 'Surnom', $nicknameRow);
    }
    $bioRow = trim((string) ($userProfile['bio'] ?? ''));
    if ($bioRow !== '') {
        $pushRow($sheetRows, 'Personnage', 'Présentation', $bioRow);
    }
    $natRow = trim((string) ($personnelProfile['nationality'] ?? ''));
    if ($natRow !== '') {
        $pushRow($sheetRows, 'Personnage', 'Nationalité', $natRow);
    }
    $rpBlood = trim((string) (($personnelProfile['rp_blood_type_confirmed'] ?? '') !== ''
        ? $personnelProfile['rp_blood_type_confirmed']
        : ($personnelProfile['blood_type'] ?? '')));
    if ($rpBlood !== '') {
        $pushRow($sheetRows, 'Personnage', 'Groupe sanguin', $rpBlood);
    }
    $rpLangs = trim((string) ($personnelProfile['languages'] ?? ''));
    if ($rpLangs !== '') {
        $pushRow($sheetRows, 'Personnage', 'Langues', $rpLangs);
    }
    if ($orgPositionDisplayLabel !== null) {
        $pushRow(
            $sheetRows,
            'Organisation',
            ($orgPositionDisplayKind ?? '') === 'position' ? 'Poste organisationnel' : 'Profil communauté',
            (string) $orgPositionDisplayLabel
        );
    }
    if (!empty($steamId)) {
        $pushRow($sheetRows, 'Compte', 'Identifiant Steam', (string) $steamId);
    }
    if (!empty($targetUser['last_login_at'])) {
        $lastLoginTs = strtotime((string) $targetUser['last_login_at']);
        $pushRow(
            $sheetRows,
            'Compte',
            'Dernière connexion',
            $lastLoginTs ? date('d/m/Y H:i', $lastLoginTs) : (string) $targetUser['last_login_at']
        );
    }
    if (!empty($armaPlaytime['hours_label'])) {
        $pushRow($sheetRows, 'Compte', 'Temps de jeu Arma', (string) $armaPlaytime['hours_label']);
    }
    if (!empty($canViewCommandNotes) && !empty($adminNotes)) {
        $pushRow($sheetRows, 'Encadrement', 'Notes de commandement', (string) $adminNotes);
    }
    if (is_array($seniorityDetailLines ?? null)) {
        foreach ($seniorityDetailLines as $senLine) {
            if (!is_array($senLine)) {
                continue;
            }
            $senUnit = trim((string) ($senLine['unit_name'] ?? 'Unité'));
            $senVal = trim((string) ($senLine['formatted'] ?? ''));
            $senBasis = trim((string) ($senLine['basis_label'] ?? ''));
            if ($senVal !== '') {
                $pushRow($sheetRows, 'Ancienneté', $senUnit, $senVal, $senBasis);
            }
        }
    }
    if (!empty($canViewAbsences) && is_array($personnelAbsences ?? null)) {
        foreach (array_slice($personnelAbsences, 0, 20) as $absRow) {
            if (!is_array($absRow)) {
                continue;
            }
            $absStart = (string) ($absRow['starts_on'] ?? '');
            $absEnd = $absRow['ends_on'] ?? null;
            $absStartTs = $absStart !== '' ? strtotime($absStart) : false;
            $absStartFr = $absStartTs !== false ? date('d/m/Y', $absStartTs) : $absStart;
            if ($absEnd === null || $absEnd === '') {
                $absPeriod = $absStartFr !== '' ? ('À partir du ' . $absStartFr) : 'Durée non précisée';
            } else {
                $absEndTs = strtotime((string) $absEnd);
                $absEndFr = $absEndTs !== false ? date('d/m/Y', $absEndTs) : (string) $absEnd;
                $absPeriod = $absStartFr . ' → ' . $absEndFr;
            }
            $absReasonKey = (string) ($absRow['reason'] ?? 'autre');
            $absReasonLab = (string) ($personnelAbsenceReasonLabels[$absReasonKey] ?? 'Autre');
            $absNote = trim((string) ($absRow['note'] ?? ''));
            $pushRow($sheetRows, 'Absences', $absPeriod, $absReasonLab, $absNote);
        }
    }
    if (is_array($qualifications ?? null)) {
        foreach ($qualifications as $q) {
            if (!is_array($q)) {
                continue;
            }
            $qName = trim((string) ($q['name'] ?? $q['qualification_name'] ?? 'Qualification'));
            $qStatus = isset($qualificationStatusFr) && is_callable($qualificationStatusFr)
                ? $qualificationStatusFr((string) ($q['status'] ?? ''))
                : (string) ($q['status'] ?? '');
            $qExpires = !empty($q['expires_at']) ? date('d/m/Y', strtotime((string) $q['expires_at']) ?: time()) : '';
            $pushRow($sheetRows, 'Qualifications', $qName, $qStatus, $qExpires);
        }
    }
    if (is_array($lmsEnrollmentsForPersonnel ?? null)) {
        foreach (array_slice($lmsEnrollmentsForPersonnel, 0, 25) as $enr) {
            if (!is_array($enr)) {
                continue;
            }
            $enrTitle = trim((string) ($enr['course_title'] ?? $enr['title'] ?? 'Parcours'));
            $enrStatus = isset($lmsEnrollmentStatusFr) && is_callable($lmsEnrollmentStatusFr)
                ? $lmsEnrollmentStatusFr((string) ($enr['status'] ?? ''))
                : (string) ($enr['status'] ?? '');
            $enrProgress = isset($enr['progress_percent']) ? ((int) $enr['progress_percent'] . ' %') : '';
            $pushRow($sheetRows, 'Formations', $enrTitle, $enrStatus, $enrProgress);
        }
    }
    if (is_array($trainingCertificates ?? null)) {
        foreach (array_slice($trainingCertificates, 0, 20) as $cert) {
            if (!is_array($cert)) {
                continue;
            }
            $certTitle = trim((string) ($cert['course_title'] ?? $cert['title'] ?? 'Attestation'));
            $certStatus = isset($lmsCertificateStatusFr) && is_callable($lmsCertificateStatusFr)
                ? $lmsCertificateStatusFr((string) ($cert['status'] ?? 'valid'))
                : (string) ($cert['status'] ?? '');
            $certExpires = !empty($cert['expires_at']) ? date('d/m/Y', strtotime((string) $cert['expires_at']) ?: time()) : '';
            $pushRow($sheetRows, 'Attestations', $certTitle, $certStatus, $certExpires);
        }
    }
    if (is_array($personnelStageBilans ?? null)) {
        foreach (array_slice($personnelStageBilans, 0, 15) as $bilan) {
            if (!is_array($bilan)) {
                continue;
            }
            $bStage = trim((string) ($bilan['stage_label'] ?? $bilan['stage'] ?? 'Bilan'));
            $bDate = !empty($bilan['created_at']) ? date('d/m/Y', strtotime((string) $bilan['created_at']) ?: time()) : '';
            $bSummary = trim((string) ($bilan['summary'] ?? $bilan['notes'] ?? ''));
            $pushRow($sheetRows, 'Bilans', $bStage, $bDate, $bSummary);
        }
    }
    if (is_array($serviceHistory ?? null)) {
        foreach (array_slice($serviceHistory, 0, 20) as $evt) {
            if (!is_array($evt)) {
                continue;
            }
            $evtType = isset($serviceHistoryEventTypeFr) && is_callable($serviceHistoryEventTypeFr)
                ? $serviceHistoryEventTypeFr((string) ($evt['event_type'] ?? ''))
                : (string) ($evt['event_type'] ?? 'Événement');
            $evtTitle = trim((string) ($evt['title'] ?? $evt['label'] ?? ''));
            $evtDate = !empty($evt['occurred_at']) ? date('d/m/Y', strtotime((string) $evt['occurred_at']) ?: time()) : '';
            $pushRow($sheetRows, 'Historique', $evtType, $evtTitle !== '' ? $evtTitle : '—', $evtDate);
        }
    }
}

foreach ($adminPanels as $panel) {
    $panelId = (int) ($panel['id'] ?? 0);
    $panelName = trim((string) ($panel['name'] ?? 'Bloc administratif'));
    $data = $adminDataByPanel[$panelId] ?? [];
    if (!is_array($data) || $data === []) {
        $pushRow($sheetRows, $panelName, '(vide)', 'Aucune information saisie');
        continue;
    }
    foreach ($data as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $fieldLabel = is_string($key) ? $key : 'Information';
        $valStr = is_scalar($value) ? (string) $value : (is_array($value) ? implode(', ', array_map('strval', $value)) : json_encode($value, JSON_UNESCAPED_UNICODE));
        if (empty($showEmailInContact) && ($fieldLooksEmail($fieldLabel) || $looksLikeEmail($valStr))) {
            $valStr = 'Masqué — réservé à l’administration';
        }
        $pushRow($sheetRows, $panelName, $fieldLabel, $valStr);
    }
}

$sheetCount = count($sheetRows);
?>
<style>
.personnel-sheets {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    overflow: auto;
    max-height: min(78vh, 56rem);
    border-radius: 0.5rem;
    width: 100%;
    min-width: 0;
}
.personnel-sheets__table {
    width: 100%;
    min-width: 48rem;
    table-layout: auto;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8125rem;
    line-height: 1.35;
}
.personnel-sheets__table th,
.personnel-sheets__table td {
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.4rem 0.65rem;
    vertical-align: middle;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.personnel-sheets__table th:last-child,
.personnel-sheets__table td:last-child {
    border-right: 0;
}
.personnel-sheets__table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #ecfdf5;
    color: #065f46;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid #059669;
    box-shadow: 0 1px 0 #059669;
}
.personnel-sheets__table th:nth-child(1),
.personnel-sheets__table td:nth-child(1) {
    min-width: 8.5rem;
}
.personnel-sheets__table th:nth-child(2),
.personnel-sheets__table td:nth-child(2) {
    min-width: 10rem;
}
.personnel-sheets__table th:nth-child(3),
.personnel-sheets__table td:nth-child(3) {
    min-width: 12rem;
}
.personnel-sheets__table th:nth-child(4),
.personnel-sheets__table td:nth-child(4) {
    min-width: 8rem;
}
.personnel-sheets__table th:nth-child(5),
.personnel-sheets__table td:nth-child(5) {
    min-width: 9rem;
}
.personnel-sheets__table tbody tr:nth-child(even) td {
    background: #f8fafc;
}
.personnel-sheets__table tbody tr:hover td {
    background: #ecfdf5;
}
.personnel-sheets__section {
    font-size: 0.6875rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #059669;
    white-space: nowrap;
}
.personnel-sheets__meta {
    display: block;
    margin-top: 0.1rem;
    font-size: 0.6875rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
</style>

<div class="space-y-4" <?= $tableauAdminStandalone ? '' : 'x-show="tab === \'tableau\'" x-cloak' ?>>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xs font-black uppercase tracking-[0.28em] text-slate-800">Tableau administratif</h2>
            <p class="mt-1.5 text-sm text-slate-600">Vue tableur du dossier : identité, affectations, dates et blocs administratifs — <?= (int) $sheetCount ?> ligne<?= $sheetCount === 1 ? '' : 's' ?>.</p>
        </div>
        <?php if (!$tableauAdminStandalone): ?>
        <button type="button" @click="tab = 'administratif'" class="inline-flex min-h-[2.25rem] items-center rounded-lg border border-slate-300 bg-white px-3 text-[10px] font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50">Vue cartes (coordonnées)</button>
        <?php endif; ?>
    </div>
    <div class="personnel-sheets" role="region" aria-label="Tableau administratif du dossier">
        <table class="personnel-sheets__table">
            <thead>
                <tr>
                    <th scope="col">Section</th>
                    <th scope="col">Champ</th>
                    <th scope="col">Valeur</th>
                    <th scope="col">Détail</th>
                    <th scope="col">Période / date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sheetRows === []): ?>
                <tr>
                    <td colspan="5" class="py-8 text-center text-sm text-slate-500">Aucune donnée administrative à afficher pour le moment.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($sheetRows as $row): ?>
                <tr>
                    <td><span class="personnel-sheets__section"><?= htmlspecialchars((string) $row['section'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="font-semibold text-slate-800"><?= htmlspecialchars((string) $row['field'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-slate-900"><?= htmlspecialchars((string) $row['value'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-slate-600">
                        <?php if (($row['detail'] ?? '') !== ''): ?>
                        <?= htmlspecialchars((string) $row['detail'], ENT_QUOTES, 'UTF-8') ?>
                        <?php elseif (($row['field'] ?? '') === 'Grade / rang' && ($row['value'] ?? '') !== 'Donnée manquante'): ?>
                        <span class="text-slate-400">Donnée manquante</span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="tabular-nums text-slate-600">
                        <?php if (($row['updated'] ?? '') !== ''): ?>
                        <span class="personnel-sheets__meta"><?= htmlspecialchars((string) $row['updated'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
