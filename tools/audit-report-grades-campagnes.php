<?php

declare(strict_types=1);

/**
 * RD-04 — Grades publiés par une campagne d'avancement mais non appliqués à la fiche du membre.
 *
 * La publication d'une campagne écrit `personnel_grade_history` (grade d'avancement en vigueur : ends_at IS NULL) sans
 * toucher `users.grade_id`, qui est ce que le site affiche. Ce rapport liste, pour chaque campagne publiée, les membres
 * dont le grade d'avancement ne correspond pas au grade de leur fiche. La correspondance est celle du code de
 * l'application (AdvancementWorkflowService::matchFicheToDefinition) : le code du grade, sinon son libellé.
 *
 * LECTURE SEULE : aucune correction n'est proposée ni appliquée (lot 3.3 : rapport avant correction).
 *
 *   php tools/audit-report-grades-campagnes.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

function audit_report_grades_campagnes(AuditReportKit $kit, AuditReadOnlyDb $db): int
{
    $tenant = $kit->options['tenant'];

    foreach (['advancement_campaigns', 'advancement_candidacies', 'personnel_grade_history', 'grade_definitions', 'users', 'grades'] as $table) {
        if (!$db->hasTable($table)) {
            fwrite(STDERR, "Table absente : $table (module d'avancement non installé ?)\n");
            return 1;
        }
    }

    // Le référentiel `grades` porte code + libellés (label_long, label_short) ; l'ancienne table, name + short_name.
    $referential = $db->hasColumn('grades', 'label_long');
    $gradeCode = $db->hasColumn('grades', 'code') ? 'g.code' : 'NULL';
    $gradeLong = $referential ? 'g.label_long' : 'g.name';
    $gradeShort = $referential ? 'g.label_short' : 'g.short_name';

    $sql = "SELECT ca.tenant_id, ca.id AS campaign_id, ca.year, ca.published_at,
                   gd.code AS published_code, gd.label AS published_label, gd.short_label AS published_short,
                   u.id AS user_id, u.display_name, u.status AS user_status,
                   h.obtained_at, h.obtained_via,
                   u.grade_id AS current_grade_id, $gradeCode AS current_code, $gradeLong AS current_label, $gradeShort AS current_short
            FROM advancement_campaigns ca
            INNER JOIN advancement_candidacies c ON c.campaign_id = ca.id
            INNER JOIN personnel_grade_history h ON h.candidacy_id = c.id AND h.ends_at IS NULL
            INNER JOIN grade_definitions gd ON gd.id = h.grade_id AND gd.tenant_id = ca.tenant_id
            INNER JOIN users u ON u.id = h.personnel_id AND u.tenant_id = ca.tenant_id
            LEFT JOIN grades g ON g.id = u.grade_id
            WHERE ca.status = 'publiee'" . ($tenant > 0 ? ' AND ca.tenant_id = ?' : '') . '
            ORDER BY ca.tenant_id, ca.published_at DESC, ca.id, u.display_name';
    $rows = $db->rows($sql, $tenant > 0 ? [$tenant] : []);

    $norm = static fn (mixed $v): string => mb_strtolower(trim((string) $v), 'UTF-8');
    $applied = 0;
    $gaps = [];
    foreach ($rows as $row) {
        $code = strtoupper(trim((string) $row['published_code']));
        $sameCode = $code !== '' && $code === strtoupper(trim((string) $row['current_code']));
        $labels = array_filter([$norm($row['published_label']), $norm($row['published_short'])], static fn (string $s): bool => $s !== '');
        $current = array_filter([$norm($row['current_label']), $norm($row['current_short'])], static fn (string $s): bool => $s !== '');
        $sameLabel = array_intersect($labels, $current) !== [];
        if ($sameCode || $sameLabel) {
            ++$applied;
            continue;
        }
        $row['problem'] = (int) $row['current_grade_id'] < 1 ? 'fiche sans grade' : 'grade de la fiche différent';
        $row['published'] = trim($row['published_label'] . ' (' . $row['published_code'] . ')');
        $row['shown'] = (int) $row['current_grade_id'] < 1 ? '—' : trim($row['current_label'] . ' (' . $row['current_code'] . ')');
        $gaps[] = $row;
    }

    $kit->say('Campagnes publiées : ' . count($rows) . ' grade(s) d’avancement en vigueur lus ; ' . $applied . ' appliqué(s) à la fiche ; ' . count($gaps) . ' écart(s).');
    $kit->table(
        'Grades publiés par campagne et non appliqués (RD-04)',
        ['tenant_id' => 'communauté', 'campaign_id' => 'campagne', 'published_at' => 'publiée le', 'user_id' => 'membre', 'display_name' => 'nom', 'user_status' => 'compte',
            'published' => 'grade publié', 'shown' => 'grade affiché sur la fiche', 'problem' => 'écart'],
        $gaps,
        'Aucune correction n’est appliquée : décider, par communauté, de la valeur à retenir avant le lot 3.3.'
    );
    return $gaps === [] ? 0 : 3;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-grades-campagnes',
        'grades publiés par campagne mais non appliqués à la fiche du membre',
        'docs/audit-backoffice RD-04 ; plan lot 3.3 et 7.8'
    );
    exit(audit_report_grades_campagnes($kit, $kit->start($argv)));
}
