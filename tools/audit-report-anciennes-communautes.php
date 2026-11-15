<?php

declare(strict_types=1);

/**
 * Très anciennes communautés : ce que les écrans récents risquent d'afficher autrement, communauté par communauté.
 *
 * Les grosses vagues de changements poussées en production (nom affiché, JNET, bilan 30 jours, tâches d'entretien,
 * migrations, rôles, formules…) ont été conçues sur des données récentes. Ce rapport mesure, pour chaque communauté, ce qui
 * est resté d'avant : membres sans prénom ni nom, sans profil de personnel, sans unité, grade ou portrait ; niveaux d'accès
 * encore nommés « Opérateur » ; candidatures acceptées sans bilan et leur ancienneté (dont combien dépassent la fenêtre de
 * sécurité de la clôture automatique, donc ne seront jamais touchées) ; étapes de migration non passées ; tables et colonnes
 * attendues par les écrans récents qui manquent sur ce serveur.
 *
 * LECTURE SEULE (socle tools/lib/audit-report-kit.php) : aucune option d'écriture, session MySQL en lecture seule,
 * uniquement des SELECT. Les nombres de membres viennent de LegacyDataCounter, le même calcul que l'avis « Données
 * anciennes à vérifier » du tableau de bord.
 *
 *   php tools/audit-report-anciennes-communautes.php [--tenant=ID] [--format=text|csv|json] [--limit=N]
 *
 * Voir docs/anciennes-communautes.md. Code de sortie : 0 rien à signaler, 3 au moins un point à vérifier.
 */

require_once __DIR__ . '/lib/audit-report-kit.php';

/** Tables que les écrans récents attendent (étapes de migration de novembre 2026). @var list<string> */
const ANCIENNES_COMMUNAUTES_TABLES = [
    'user_legal_identities', 'personnel_profiles', 'personnel_assignments', 'enlistment_retro_feedbacks',
    'workflow_instances', 'workflow_events', 'workflow_circuits', 'tenant_administrative_positions',
    'jnet_unit_spaces', 'jnet_unit_tasks', 'jnet_unit_pages', 'jnet_unit_space_access', 'jnet_unit_space_managers',
    'jnet_unit_links', 'jnet_links', 'jnet_pins',
    'contact_mail_threads', 'contact_mail_messages',
    'photo_library_tags', 'photo_library_masks', 'photo_library_labels',
    'legacy_identity_import_log', 'schema_migration_runs',
];

/** Colonnes que les écrans récents attendent : table => colonnes. @var array<string, list<string>> */
const ANCIENNES_COMMUNAUTES_COLUMNS = [
    'users' => ['grade_id', 'status', 'deleted_at', 'is_service_account'],
    'personnel_profiles' => ['character_name', 'character_portrait_path', 'primary_unit_id', 'deployable', 'extra_callsigns_json'],
    'enlistment_retro_feedbacks' => ['auto_closed_at'],
    'jnet_unit_spaces' => ['hidden_modules'],
    'recon_images' => ['author_user_id'],
];

function audit_report_anciennes_communautes(AuditReportKit $kit, AuditReadOnlyDb $db, ?PDO $pdo = null): int
{
    $tenantFilter = $kit->options['tenant'];
    $now = time();
    $flags = 0;

    // 1. Étapes de migration non passées (la base est commune à toutes les communautés).
    $steps = [];
    $registry = require dirname(__DIR__) . '/database/steps/registry.php';
    $ran = [];
    if ($db->hasTable('schema_migration_runs')) {
        foreach ($db->rows('SELECT r.step_id, r.status FROM schema_migration_runs r JOIN (SELECT step_id, MAX(id) AS id FROM schema_migration_runs GROUP BY step_id) m ON m.id = r.id') as $r) {
            $ran[(string) $r['step_id']] = (string) $r['status'];
        }
    }
    foreach (is_array($registry) ? $registry : [] as $step) {
        $id = (string) ($step['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $status = $ran[$id] ?? '';
        if ($status === '' || !in_array(strtolower($status), ['ok', 'done', 'success', 'passed'], true)) {
            $steps[] = ['step' => $id, 'last_status' => $status !== '' ? $status : 'jamais passée', 'file' => (string) ($step['file'] ?? '')];
        }
    }
    $flags += $kit->table(
        '1. Étapes de migration non passées sur ce serveur (ou dont le dernier passage a échoué)',
        ['step' => 'étape', 'last_status' => 'dernier résultat', 'file' => 'fichier'],
        $steps,
        'Une étape manquante laisse l\'écran qui en dépend sans sa table ou sa colonne : les données restent, l\'affichage est limité.'
    );

    // 2. Tables et colonnes attendues par les écrans récents.
    $missing = [];
    foreach (ANCIENNES_COMMUNAUTES_TABLES as $table) {
        if (!$db->hasTable($table)) {
            $missing[] = ['kind' => 'table', 'object' => $table];
        }
    }
    foreach (ANCIENNES_COMMUNAUTES_COLUMNS as $table => $columns) {
        if (!$db->hasTable($table)) {
            $missing[] = ['kind' => 'table', 'object' => $table];
            continue;
        }
        foreach ($columns as $column) {
            if (!$db->hasColumn($table, $column)) {
                $missing[] = ['kind' => 'colonne', 'object' => $table . '.' . $column];
            }
        }
    }
    $flags += $kit->table('2. Tables et colonnes attendues par les écrans récents et absentes', ['kind' => 'type', 'object' => 'objet'], $missing, 'À corriger par les étapes de migration de la section 1, jamais à la main.');

    // 3. Communauté par communauté.
    if (!$db->hasTable('tenants') || !$db->hasTable('users')) {
        fwrite(STDERR, "Table absente : tenants ou users\n");

        return 1;
    }
    $hasCreated = $db->hasColumn('tenants', 'created_at');
    $tenants = $db->rows(
        'SELECT id, name' . ($hasCreated ? ', created_at' : '') . ' FROM tenants WHERE id > 1' . ($tenantFilter > 0 ? ' AND id = ?' : '') . ' ORDER BY id',
        $tenantFilter > 0 ? [$tenantFilter] : []
    );
    // Même connexion que le rapport : déjà en lecture seule (voir AuditReportKit::connect). Tests : la base en mémoire.
    $counter = new \App\Services\LegacyData\LegacyDataCounter($pdo);
    $window = \App\Support\EnlistmentRetroAutoClose::AUTO_CLOSE_MAX_LATE_DAYS;
    $rows = [];
    $bilanRows = [];
    foreach ($tenants as $t) {
        $tid = (int) $t['id'];
        $c = $counter->count($tid, $now);
        $members = max(0, $c['members']);
        $pct = static fn (int $n): string => $members > 0 ? round($n * 100 / $members) . ' %' : '—';
        $rows[] = [
            'tenant_id' => $tid,
            'name' => (string) $t['name'],
            'created_at' => $hasCreated ? substr((string) ($t['created_at'] ?? ''), 0, 10) : '?',
            'members' => $members,
            'no_civil_name' => $c['no_civil_name'] . ' (' . $pct($c['no_civil_name']) . ')',
            'importable' => $c['importable_names'],
            'no_profile' => $c['no_profile'] . ' (' . $pct($c['no_profile']) . ')',
            'no_unit' => $c['no_unit'] . ' (' . $pct($c['no_unit']) . ')',
            'no_grade' => $c['no_grade'] . ' (' . $pct($c['no_grade']) . ')',
            'no_portrait' => $c['no_portrait'] . ' (' . $pct($c['no_portrait']) . ')',
            'operateur' => $c['legacy_role_labels'],
        ];
        if ($c['no_civil_name'] + $c['stale_dossiers'] + $c['legacy_role_labels'] > 0) {
            ++$flags;
        }
        $bilanRows[] = audit_anciennes_communautes_bilans($db, $tid, $now, $window) + ['tenant_id' => $tid, 'name' => (string) $t['name']];
    }
    $kit->table(
        '3. Membres : ce qui est incomplet, par communauté',
        [
            'tenant_id' => 'communauté', 'name' => 'nom', 'created_at' => 'créée le', 'members' => 'membres actifs',
            'no_civil_name' => 'sans prénom ni nom', 'importable' => 'dont reprenables (nom de personnage)', 'no_profile' => 'sans profil de personnel',
            'no_unit' => 'sans unité', 'no_grade' => 'sans grade', 'no_portrait' => 'sans portrait', 'operateur' => 'niveaux « Opérateur » restants',
        ],
        $rows,
        'Ces membres restent listés ; leur nom s\'affiche sous l\'indicatif. Reprise des anciens noms : Qualité des données (simulation d\'abord).'
    );
    $kit->table(
        '4. Candidatures acceptées sans bilan 30 jours, par ancienneté',
        [
            'tenant_id' => 'communauté', 'name' => 'nom', 'total' => 'sans bilan', 'due' => 'dans la fenêtre (seront closes)',
            'stale' => 'au-delà de ' . $window . ' j de retard (jamais touchées)', 'oldest_days' => 'plus ancienne (jours)',
        ],
        $bilanRows,
        'La clôture automatique n\'écrit rien au-delà de la fenêtre de sécurité ; ces dossiers sont signalés dans l\'avis du tableau de bord.'
    );

    return $flags > 0 ? 3 : 0;
}

/**
 * Candidatures acceptées sans bilan d'équipe d'une communauté : total, dans la fenêtre de clôture automatique, au-delà.
 *
 * @return array{total: int, due: int, stale: int, oldest_days: int|string}
 */
function audit_anciennes_communautes_bilans(AuditReadOnlyDb $db, int $tenantId, int $now, int $windowDays): array
{
    $out = ['total' => 0, 'due' => 0, 'stale' => 0, 'oldest_days' => '—'];
    if (!$db->hasTable('enlistments') || !$db->hasTable('enlistment_retro_feedbacks')) {
        return $out;
    }
    $rows = $db->rows(
        "SELECT COALESCE(e.reviewed_at, e.created_at) AS anchor FROM enlistments e
         WHERE e.tenant_id = ? AND e.status = 'reviewed'
           AND NOT EXISTS (
               SELECT 1 FROM enlistment_retro_feedbacks r
               WHERE r.tenant_id = e.tenant_id AND r.enlistment_id = e.id
                 AND (r.feedback_scope = 'staff_one_month' OR r.feedback_scope = 'staff' OR r.feedback_scope LIKE 'staff!_%' ESCAPE '!')
           )",
        [$tenantId]
    );
    $oldest = 0;
    foreach ($rows as $r) {
        $ts = strtotime((string) ($r['anchor'] ?? ''));
        if ($ts === false) {
            continue;
        }
        $late = (int) floor(($now - $ts) / 86400) - \App\Support\EnlistmentStatusCatalog::RETRO_DELAY_DAYS;
        ++$out['total'];
        $oldest = max($oldest, $late + \App\Support\EnlistmentStatusCatalog::RETRO_DELAY_DAYS);
        if ($late > $windowDays) {
            ++$out['stale'];
        } elseif ($late >= \App\Support\EnlistmentRetroAutoClose::AUTO_CLOSE_LATE_DAYS) {
            ++$out['due'];
        }
    }
    $out['oldest_days'] = $out['total'] > 0 ? $oldest : '—';

    return $out;
}

if (isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $kit = new AuditReportKit(
        'audit-report-anciennes-communautes',
        'très anciennes communautés : données incomplètes, bilans anciens, étapes de migration et tables manquantes',
        'docs/anciennes-communautes.md ; avis « Données anciennes à vérifier » (App\\Services\\LegacyData)'
    );
    exit(audit_report_anciennes_communautes($kit, $kit->start($argv)));
}
