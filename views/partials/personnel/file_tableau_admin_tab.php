<?php
/**
 * Onglet Tableau administratif — grille dense type tableur.
 *
 * Variables attendues depuis file.php (déjà résolues).
 */
$sheetRows = [];

$pushRow = static function (array &$rows, string $section, string $field, string $value, string $detail = '', string $updated = ''): void {
    $rows[] = [
        'section' => $section,
        'field' => $field,
        'value' => $value !== '' ? $value : '—',
        'detail' => $detail,
        'updated' => $updated,
    ];
};

$pushRow($sheetRows, 'Identité', 'Prénom', (string) (($civilIdentity['first_name'] ?? '') !== '' ? $civilIdentity['first_name'] : '—'));
$pushRow($sheetRows, 'Identité', 'Nom', (string) (($civilIdentity['last_name'] ?? '') !== '' ? $civilIdentity['last_name'] : '—'));
if (!empty($showMatriculePublic)) {
    $pushRow($sheetRows, 'Identité', 'Matricule', $matricule ? (string) $matricule : 'Non attribué');
}
$pushRow($sheetRows, 'Identité', 'Indicatif radio', $callsign ? (string) $callsign : '—');
if (!empty($showEmailInContact) && $athenaIdentifier !== '') {
    $pushRow($sheetRows, 'Identité', 'Identifiant Athena', $athenaIdentifier);
}
if (!empty($canViewCivilSection)) {
    if (!empty($showEmailInContact)) {
        $pushRow($sheetRows, 'Compte', 'E-mail', (string) ($targetUser['email'] ?? '—'));
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

$pushRow($sheetRows, 'Affectation', 'Grade / rang', $effectiveRankDisplay !== '' ? (string) $effectiveRankDisplay : '—');
$pushRow($sheetRows, 'Affectation', 'Unité principale', $unitName ? (string) $unitName : '—');
if (!empty($commander)) {
    $cmdLabel = trim((string) ($commander['display_name'] ?? '')) ?: trim((string) ($commander['callsign'] ?? ''));
    $pushRow($sheetRows, 'Affectation', 'Chef d’équipe', $cmdLabel !== '' ? $cmdLabel : '—');
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

$pushRow($sheetRows, 'Dates', 'Date d’enrôlement', $enlistmentFormatted ? (string) $enlistmentFormatted : '—');
if ($accountCreatedDisplay !== null) {
    $pushRow($sheetRows, 'Dates', 'Membre depuis', (string) $accountCreatedDisplay);
}
if ($seniorityGlobal !== null) {
    $pushRow(
        $sheetRows,
        'Dates',
        'Ancienneté globale',
        (string) ($seniorityGlobal['formatted'] ?? '—'),
        (string) ($seniorityGlobal['basis_label'] ?? '')
    );
}
if ($privatePersonnelIdentity) {
    $pushRow($sheetRows, 'Compte', 'Statut du compte', $accountStatusFr((string) ($targetUser['status'] ?? '')));
}
$pushRow($sheetRows, 'Opérationnel', 'Habilitation', $clearanceLevel !== '' ? (string) $clearanceLevel : '—');
$pushRow($sheetRows, 'Opérationnel', 'Préparation', $readiness !== null ? $readiness . ' %' : '—');
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
$tableauAdminStandalone = !empty($tableauAdminStandalone ?? false);
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
