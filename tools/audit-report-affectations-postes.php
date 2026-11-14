<?php

declare(strict_types=1);

/**
 * RD-21 — « Qui occupe quoi » : affectations sans poste ORBAT, et postes occupés sans affectation.
 *
 * L'affectation (`personnel_assignments`) est un rattachement administratif ; le poste (`orbat_billet_holders`) est une
 * place de l'organigramme. Rien ne les synchronise aujourd'hui. Ce rapport liste :
 *   A. les membres actifs dont l'affectation principale active n'a aucun poste ORBAT occupé dans la même unité ;
 *   B. les titulaires de poste (principal ou intérim) actifs qui n'ont aucune affectation active dans l'unité du poste,
 *      avec l'indication « affecté ailleurs » ou « aucune affectation ».
 *
 * LECTURE SEULE : le lot 3.4 demande ce rapport avant toute bascule ; rien n'est corrigé ici.
 *
 *   php tools/audit-report-affectations-postes.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

function audit_report_affectations_postes(AuditReportKit $kit, AuditReadOnlyDb $db): int
{
    $tenant = $kit->options['tenant'];
    $today = date('Y-m-d');

    foreach (['personnel_assignments', 'orbat_billets', 'orbat_billet_holders', 'users', 'units'] as $table) {
        if (!$db->hasTable($table)) {
            fwrite(STDERR, "Table absente : $table\n");
            return 1;
        }
    }

    // Un poste est actif comme dans OrbatBilletRepository::listActiveForTenant (colonnes ajoutées par migration : on les teste).
    $billetActive = 'b.is_active = 1';
    if ($db->hasColumn('orbat_billets', 'status')) {
        $billetActive .= " AND COALESCE(b.status, 'active') = 'active'";
    }
    if ($db->hasColumn('orbat_billets', 'archived_at')) {
        $billetActive .= ' AND b.archived_at IS NULL';
    }
    $holderRole = $db->hasColumn('orbat_billet_holders', 'holder_role') ? 'h.holder_role' : "'PRIMARY'";
    $tenantUser = $tenant > 0 ? ' AND u.tenant_id = ?' : '';
    $tenantParam = $tenant > 0 ? [$tenant] : [];

    // A. Affectation principale active sans poste occupé dans la même unité.
    $sqlA = "SELECT u.tenant_id, u.id AS user_id, u.display_name, un.id AS unit_id, un.name AS unit_name, pa.role_name, pa.started_at
             FROM personnel_assignments pa
             INNER JOIN users u ON u.id = pa.user_id
             INNER JOIN units un ON un.id = pa.unit_id AND un.tenant_id = u.tenant_id
             WHERE pa.status = 'active' AND pa.is_primary = 1 AND (pa.ended_at IS NULL OR pa.ended_at >= ?)
               AND u.status = 'active'$tenantUser
               AND NOT EXISTS (
                   SELECT 1 FROM orbat_billet_holders h
                   INNER JOIN orbat_billets b ON b.id = h.billet_id AND b.tenant_id = h.tenant_id
                   WHERE h.user_id = u.id AND h.tenant_id = u.tenant_id AND b.unit_id = pa.unit_id AND $billetActive
                     AND h.starts_at <= ? AND (h.ends_at IS NULL OR h.ends_at >= ?)
               )
             ORDER BY u.tenant_id, un.name, u.display_name";
    $a = $db->rows($sqlA, array_merge([$today], $tenantParam, [$today, $today]));

    // B. Titulaire actif d'un poste sans affectation active dans l'unité du poste.
    $sqlB = "SELECT b.tenant_id, b.id AS billet_id, b.code AS billet_code, b.title AS billet_title, b.unit_id, un.name AS unit_name,
                    h.user_id, u.display_name, $holderRole AS holder_role, h.starts_at,
                    (SELECT COUNT(*) FROM personnel_assignments pa2
                      WHERE pa2.user_id = h.user_id AND pa2.status = 'active' AND (pa2.ended_at IS NULL OR pa2.ended_at >= ?)) AS active_assignments
             FROM orbat_billet_holders h
             INNER JOIN orbat_billets b ON b.id = h.billet_id AND b.tenant_id = h.tenant_id
             INNER JOIN users u ON u.id = h.user_id AND u.tenant_id = h.tenant_id
             LEFT JOIN units un ON un.id = b.unit_id
             WHERE $billetActive AND h.starts_at <= ? AND (h.ends_at IS NULL OR h.ends_at >= ?)
               AND u.status = 'active'$tenantUser
               AND NOT EXISTS (
                   SELECT 1 FROM personnel_assignments pa
                   WHERE pa.user_id = h.user_id AND pa.unit_id = b.unit_id AND pa.status = 'active'
                     AND (pa.ended_at IS NULL OR pa.ended_at >= ?)
               )
             ORDER BY b.tenant_id, un.name, b.title, u.display_name";
    $b = $db->rows($sqlB, array_merge([$today, $today, $today], $tenantParam, [$today]));
    foreach ($b as &$row) {
        $row['situation'] = (int) $row['active_assignments'] > 0 ? 'affecté dans une autre unité' : 'aucune affectation active';
    }
    unset($row);

    $kit->say('Date de référence : ' . $today . ' (membres au statut « active » ; postes actifs).');
    $kit->table(
        'A. Affectations principales sans poste ORBAT dans la même unité (RD-21)',
        ['tenant_id' => 'communauté', 'user_id' => 'membre', 'display_name' => 'nom', 'unit_name' => 'unité', 'role_name' => 'emploi de l’affectation', 'started_at' => 'depuis'],
        $a,
        'Le membre est rattaché à l’unité mais ne tient aucun poste de son organigramme.'
    );
    $kit->table(
        'B. Postes occupés sans affectation dans l’unité du poste (RD-21)',
        ['tenant_id' => 'communauté', 'billet_code' => 'poste', 'billet_title' => 'intitulé', 'unit_name' => 'unité du poste', 'user_id' => 'membre', 'display_name' => 'titulaire',
            'holder_role' => 'rôle', 'situation' => 'situation'],
        $b,
        'Le poste affiche un titulaire que l’effectif ne rattache pas à cette unité.'
    );
    return ($a === [] && $b === []) ? 0 : 3;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-affectations-postes',
        'affectations sans poste ORBAT et postes occupés sans affectation',
        'docs/audit-backoffice RD-21 ; plan lot 3.4 et 7.8'
    );
    exit(audit_report_affectations_postes($kit, $kit->start($argv)));
}
