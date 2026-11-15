<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/lib/audit-report-kit.php';
require_once __DIR__ . '/../../tools/audit-report-grades-campagnes.php';
require_once __DIR__ . '/../../tools/audit-report-affectations-postes.php';
require_once __DIR__ . '/../../tools/audit-report-logos.php';
require_once __DIR__ . '/../../tools/audit-report-aar-liens.php';
require_once __DIR__ . '/../../tools/audit-report-roles-semes.php';

/**
 * Audit du back-office, lot 6 : les rapports de cohérence (tools/audit-report-*.php) sont en lecture seule, et leurs
 * requêtes tournent contre le schéma des migrations.
 *
 * Le schéma est celui des CREATE TABLE des migrations (migrations/*.sql, bootstrap/*.php), chargés dans une base SQLite
 * en mémoire : un nom de colonne ou de table faux dans une requête échoue ici. Quelques lignes de test vérifient aussi
 * que chaque rapport trouve bien l'écart qu'il doit trouver (et ignore le cas sain).
 */
final class AuditReportQueriesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** Table => fichier de migration qui la crée (le premier CREATE TABLE trouvé). */
    private const SOURCES = [
        'users' => 'migrations/schema.sql',
        'tenants' => 'migrations/schema.sql',
        'units' => 'migrations/schema.sql',
        'roles' => 'migrations/schema.sql',
        'permissions' => 'migrations/schema.sql',
        'role_permissions' => 'migrations/schema.sql',
        'tenant_user_roles' => 'migrations/schema.sql',
        'personnel_assignments' => 'migrations/personnel_dossier.sql',
        'tenant_branding' => 'migrations/schema_v2_tenant_user_prefs.sql',
        'orbat_billets' => 'bootstrap/personnel_capability_axes_migration.php',
        'orbat_billet_holders' => 'bootstrap/personnel_capability_axes_migration.php',
        'grade_definitions' => 'bootstrap/advancement_grade_migration.php',
        'advancement_campaigns' => 'bootstrap/advancement_grade_migration.php',
        'advancement_candidacies' => 'bootstrap/advancement_grade_migration.php',
        'personnel_grade_history' => 'bootstrap/advancement_grade_migration.php',
        'aar_reports' => 'bootstrap/aar_reports_migration.php',
        'community_events' => 'bootstrap/platform_unit_commander_migration.php',
        'theatre_mission_cycles' => 'bootstrap/theatre_mission_cycle_migration.php',
    ];

    /** Colonnes ajoutées par une migration ALTER (non visibles dans le CREATE TABLE d'origine). */
    private const ALTERS = [
        'ALTER TABLE roles ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
        "ALTER TABLE orbat_billets ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'active'",
        'ALTER TABLE orbat_billets ADD COLUMN archived_at DATETIME NULL',
    ];

    private function db(): \AuditReadOnlyDb
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        foreach (self::SOURCES as $table => $file) {
            $pdo->exec($this->createTable($table, $table, $file));
        }
        // Le référentiel des grades est `grades_referentiel`, renommé `grades` par la bascule (database/steps/1040).
        $pdo->exec($this->createTable('grades_referentiel', 'grades', 'migrations/grade_referentiel.sql'));
        foreach (self::ALTERS as $alter) {
            $pdo->exec($alter);
        }

        return new class ($pdo) extends \AuditReadOnlyDb {
            public function hasTable(string $table): bool
            {
                return $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = " . $this->pdo->quote($table))->fetchColumn() !== false;
            }

            public function hasColumn(string $table, string $column): bool
            {
                foreach ($this->pdo->query('PRAGMA table_info("' . $table . '")')->fetchAll(\PDO::FETCH_ASSOC) as $col) {
                    if ($col['name'] === $column) {
                        return true;
                    }
                }

                return false;
            }
        };
    }

    /**
     * CREATE TABLE d'une migration, réduit à ses colonnes (SQLite ne lit que les noms : types et index MySQL sont ignorés).
     * Les noms de colonnes sont donc ceux des migrations, et une colonne inventée dans une requête échoue.
     */
    private function createTable(string $sourceTable, string $asName, string $file): string
    {
        $src = (string) file_get_contents(self::ROOT . '/' . $file);
        $found = preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($sourceTable, '/') . '`?\s*\((.*?)\)\s*ENGINE/is', $src, $m);
        self::assertSame(1, $found, 'CREATE TABLE ' . $sourceTable . ' introuvable dans ' . $file);
        $columns = [];
        $depth = 0;
        $quote = '';
        $buf = '';
        $parts = [];
        $body = stripslashes($m[1]);
        for ($i = 0, $n = strlen($body); $i < $n; $i++) {
            $c = $body[$i];
            if ($quote !== '') {
                $buf .= $c;
                $quote = $c === $quote ? '' : $quote;
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $parts[] = trim($buf);
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT|CHECK)(?![A-Za-z0-9_])/i', $part) === 1) {
                continue;
            }
            if (preg_match('/^`?(\w+)`?/', $part, $c) === 1) {
                $columns[] = '"' . $c[1] . '"' . ($c[1] === 'id' ? ' INTEGER PRIMARY KEY' : '');
            }
        }
        self::assertNotSame([], $columns, $sourceTable);

        return 'CREATE TABLE "' . $asName . '" (' . implode(', ', $columns) . ')';
    }

    /**
     * @param callable(\AuditReportKit, \AuditReadOnlyDb): int $report
     * @return array{code:int, sections:array<string, list<array<string,mixed>>>}
     */
    private function runReport(callable $report, \AuditReadOnlyDb $db, int $tenant = 0): array
    {
        $kit = new \AuditReportKit('test', 'test', 'test');
        $kit->capture = true;
        $kit->options['format'] = 'json';
        $kit->options['tenant'] = $tenant;
        ob_start();
        try {
            $code = $report($kit, $db);
        } finally {
            ob_end_clean();
        }
        $sections = [];
        foreach ($kit->captured as $s) {
            $sections[$s['title']] = $s['rows'];
        }

        return ['code' => $code, 'sections' => $sections];
    }

    private function pdo(\AuditReadOnlyDb $db): \PDO
    {
        return (new \ReflectionProperty(\AuditReadOnlyDb::class, 'pdo'))->getValue($db);
    }

    public function testTheGuardRefusesEveryWrite(): void
    {
        foreach (['UPDATE users SET id = 1', 'DELETE FROM users', 'INSERT INTO users (id) VALUES (1)', 'SELECT 1; DROP TABLE users', 'SELECT 1 INTO OUTFILE \'/x\'', 'SET @a = 1', 'TRUNCATE users', 'WITH x AS (SELECT 1) DELETE FROM users'] as $sql) {
            try {
                \AuditReadOnlyDb::assertReadOnlySql($sql);
                self::fail('acceptée à tort : ' . $sql);
            } catch (\RuntimeException) {
                self::assertTrue(true);
            }
        }
        \AuditReadOnlyDb::assertReadOnlySql("SELECT u.id FROM users u WHERE u.display_name = 'delete; update' AND u.created_at < ?");
        \AuditReadOnlyDb::assertReadOnlySql('WITH x AS (SELECT 1 AS a) SELECT a FROM x');
        // Une écriture passée à rows() n'arrive jamais à la base.
        $db = $this->db();
        try {
            $db->rows("UPDATE users SET status = 'x'");
            self::fail('rows() a accepté une écriture');
        } catch (\RuntimeException) {
            self::assertSame(0, (int) $this->pdo($db)->query('SELECT COUNT(*) FROM users')->fetchColumn());
        }
    }

    public function testEveryReportIsReadOnlyBySource(): void
    {
        foreach ((array) glob(self::ROOT . '/tools/audit-report-*.php') as $file) {
            $src = (string) file_get_contents($file);
            // Aucun accès direct à la base : tout passe par $db->rows() (lecture seule).
            self::assertDoesNotMatchRegularExpression('/->(exec|query|prepare|beginTransaction|commit)\(/', $src, basename($file));
            self::assertDoesNotMatchRegularExpression('/\b(INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|REPLACE\s+INTO|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE)\b/i', $src, basename($file));
            self::assertStringContainsString('start($argv)', $src, basename($file));
        }
        $kit = (string) file_get_contents(self::ROOT . '/tools/lib/audit-report-kit.php');
        self::assertStringContainsString('START TRANSACTION READ ONLY', $kit);
        self::assertStringContainsString('PHP_SAPI !== \'cli\'', $kit);
        self::assertSame(7, count((array) glob(self::ROOT . '/tools/audit-report-*.php')), 'sept rapports : RD-04, RD-21, RD-44/45, RD-19, RD-03, les identités des membres et les anciennes communautés');
    }

    public function testLogosReportFindsDesynchronisedLogos(): void
    {
        $db = $this->db();
        $pdo = $this->pdo($db);
        $pdo->exec("INSERT INTO tenants (id, name, slug, logo_url, updated_at) VALUES
            (1, 'A', 'a', '/a.png', '2026-01-01 10:00:00'),
            (2, 'B', 'b', '/same.png', '2026-01-01 10:00:00'),
            (3, 'C', 'c', '/c.png', '2026-01-01 10:00:00'),
            (4, 'D', 'd', NULL, NULL)");
        $pdo->exec("INSERT INTO tenant_branding (tenant_id, logo_url, updated_at) VALUES (1, '/a-new.png', '2026-03-01 10:00:00'), (2, '/same.png', NULL)");
        $r = $this->runReport('audit_report_logos', $db);
        $rows = $r['sections']['Logos désynchronisés (RD-44)'];
        self::assertSame(3, $r['code']);
        self::assertSame([1, 3], array_map(static fn (array $x): int => (int) $x['tenant_id'], $rows));
        self::assertSame('tenant_branding.logo_url (plus récent)', $rows[0]['suggestion']);
        self::assertSame('logo seulement dans tenants (pas de ligne branding)', $rows[1]['problem']);
        self::assertSame(1, count($this->runReport('audit_report_logos', $db, 3)['sections']['Logos désynchronisés (RD-44)']));
    }

    public function testGradesReportFindsPublishedGradesNotAppliedToTheFiche(): void
    {
        $db = $this->db();
        $pdo = $this->pdo($db);
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'A', 'a')");
        $pdo->exec("INSERT INTO grades (id, grade_system_id, grade_category_id, code, label_short, label_long) VALUES
            (10, 1, 1, 'CPL', 'Cpl', 'Caporal'), (11, 1, 1, 'SGT', 'Sgt', 'Sergent')");
        $pdo->exec("INSERT INTO grade_definitions (id, tenant_id, code, label, short_label, rank_order) VALUES (100, 1, 'SGT', 'Sergent', 'SGT', 4)");
        $pdo->exec("INSERT INTO users (id, tenant_id, email, password_hash, display_name, grade_id, status) VALUES
            (1, 1, 'a@x', 'x', 'Appliqué', 11, 'active'), (2, 1, 'b@x', 'x', 'Non appliqué', 10, 'active'), (3, 1, 'c@x', 'x', 'Sans grade', NULL, 'active')");
        $pdo->exec("INSERT INTO advancement_campaigns (id, tenant_id, grade_id, year, opens_at, closes_at, status, published_at) VALUES (5, 1, 100, 2026, '2026-01-01', '2026-02-01', 'publiee', '2026-03-01')");
        foreach ([1, 2, 3] as $i) {
            $pdo->exec("INSERT INTO advancement_candidacies (id, campaign_id, personnel_id, decision) VALUES ($i, 5, $i, 'inscrit')");
            $pdo->exec("INSERT INTO personnel_grade_history (personnel_id, grade_id, obtained_at, obtained_via, candidacy_id) VALUES ($i, 100, '2026-03-01', 'choix', $i)");
        }
        $r = $this->runReport('audit_report_grades_campagnes', $db);
        $rows = $r['sections']['Grades publiés par campagne et non appliqués (RD-04)'];
        self::assertSame(3, $r['code']);
        self::assertSame(['Non appliqué', 'Sans grade'], array_map(static fn (array $x): string => (string) $x['display_name'], $rows));
        self::assertSame(['grade de la fiche différent', 'fiche sans grade'], array_map(static fn (array $x): string => (string) $x['problem'], $rows));
    }

    public function testAssignmentsReportFindsBothDirections(): void
    {
        $db = $this->db();
        $pdo = $this->pdo($db);
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'A', 'a')");
        $pdo->exec("INSERT INTO units (id, tenant_id, name, slug) VALUES (1, 1, 'Alpha', 'alpha'), (2, 1, 'Bravo', 'bravo')");
        $pdo->exec("INSERT INTO users (id, tenant_id, email, password_hash, display_name, status) VALUES
            (1, 1, 'a@x', 'x', 'Tient un poste', 'active'), (2, 1, 'b@x', 'x', 'Affecté sans poste', 'active'),
            (3, 1, 'c@x', 'x', 'Poste sans affectation', 'active'), (4, 1, 'd@x', 'x', 'Poste, affecté ailleurs', 'active')");
        $pdo->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, status) VALUES
            (1, 1, 'Chef', 1, 'active'), (2, 1, 'Tireur', 1, 'active'), (4, 2, 'Pilote', 1, 'active')");
        $pdo->exec("INSERT INTO orbat_billets (id, tenant_id, unit_id, code, title, is_active) VALUES (1, 1, 1, 'CHEF', 'Chef de section', 1), (2, 1, 1, 'ADJ', 'Adjoint', 1)");
        $pdo->exec("INSERT INTO orbat_billet_holders (tenant_id, billet_id, user_id, holder_role, starts_at) VALUES
            (1, 1, 1, 'PRIMARY', '2025-01-01'), (1, 2, 3, 'PRIMARY', '2025-01-01'), (1, 2, 4, 'ALTERNATE', '2025-01-01')");
        $r = $this->runReport('audit_report_affectations_postes', $db);
        $a = $r['sections']['A. Affectations principales sans poste ORBAT dans la même unité (RD-21)'];
        $b = $r['sections']['B. Postes occupés sans affectation dans l’unité du poste (RD-21)'];
        self::assertSame(['Affecté sans poste', 'Poste, affecté ailleurs'], array_map(static fn (array $x): string => (string) $x['display_name'], $a));
        self::assertSame(['Poste sans affectation', 'Poste, affecté ailleurs'], array_map(static fn (array $x): string => (string) $x['display_name'], $b));
        self::assertSame(['aucune affectation active', 'affecté dans une autre unité'], array_map(static fn (array $x): string => (string) $x['situation'], $b));
    }

    public function testAarReportFindsTitleLinkProblems(): void
    {
        $db = $this->db();
        $pdo = $this->pdo($db);
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'A', 'a')");
        $pdo->exec("INSERT INTO users (id, tenant_id, email, password_hash, display_name) VALUES (1, 1, 'a@x', 'x', 'U')");
        $ev = static fn (int $id, string $title, string $tag = ''): string => "($id, 1, '$title', " . ($tag === '' ? 'NULL' : "'$tag'") . ", '2025-05-01 20:00:00', 1)";
        $pdo->exec('INSERT INTO community_events (id, tenant_id, title, campaign_tag, starts_at, created_by_user_id) VALUES '
            . implode(', ', [$ev(1, 'Opération Aigle'), $ev(2, 'Opération Faucon'), $ev(3, 'Opération Faucon'), $ev(4, 'Opération Éclair'), $ev(5, 'Sans compte rendu')]));
        $pdo->exec("INSERT INTO aar_reports (id, tenant_id, title, operation_label) VALUES
            (1, 1, 'CR Aigle', 'opération  aigle'), (2, 1, 'CR Faucon', 'Opération Faucon'), (3, 1, 'CR Eclair', 'Operation Eclair'), (4, 1, 'CR orphelin', 'Autre')");
        $r = $this->runReport('audit_report_aar_liens', $db)['sections'];
        self::assertSame(['Sans compte rendu'], array_column($r['1. Événements passés sans compte rendu lié'], 'title'));
        self::assertSame(['Opération Faucon', 'Opération Faucon'], array_column($r['2. Liens ambigus (même titre)'], 'title'));
        self::assertSame(['Opération Éclair'], array_column($r['3. Quasi-correspondances : lien cassé par une retouche de titre'], 'title'));
        self::assertSame([4], array_map('intval', array_column($r['4. Comptes rendus qu’aucun événement n’atteint'], 'id')));
    }

    public function testRolesReportFindsWhoLosesWhat(): void
    {
        $db = $this->db();
        $pdo = $this->pdo($db);
        $pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (1, 'A', 'a')");
        $pdo->exec("INSERT INTO roles (id, tenant_id, name, slug, role_layer, is_system) VALUES
            (1, 1, 'Membre', 'member', 'community', 1), (2, 1, 'RH', 'hr', 'community', 1), (3, 1, 'Chef d''escouade', 'squad_leader', 'community', 1)");
        $pdo->exec("INSERT INTO permissions (id, name, slug) VALUES (1, 'Voir', 'personnel.profile.view'), (2, 'Gérer', 'personnel.assignments.manage'), (3, 'Admin', 'admin.roles.manage')");
        $pdo->exec('INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1), (2, 2), (3, 2), (3, 3)');
        $pdo->exec("INSERT INTO users (id, tenant_id, email, password_hash, display_name, status) VALUES
            (1, 1, 'a@x', 'x', 'Chef', 'active'), (2, 1, 'b@x', 'x', 'RH et chef', 'active'), (3, 1, 'c@x', 'x', 'Ancien', 'inactive')");
        $pdo->exec("INSERT INTO tenant_user_roles (tenant_id, user_id, role_id, co_unit_id) VALUES (1, 1, 1, 0), (1, 1, 3, 0), (1, 2, 2, 0), (1, 2, 3, 0), (1, 3, 3, 0)");
        $r = $this->runReport('audit_report_roles_semes', $db);
        self::assertSame(3, $r['code']);
        $hidden = $r['sections']['A. Rôles non visibles qui portent des droits (RD-03)'];
        self::assertSame(['squad_leader'], array_column($hidden, 'slug'));
        self::assertSame(2, (int) $hidden[0]['members'], 'les comptes inactifs ne comptent pas');
        $lost = $r['sections']['B. Qui perd quoi si ces rôles disparaissent (droits qui ne viennent d’aucun niveau visible)'];
        self::assertSame(['Chef', 'RH et chef'], array_column($lost, 'display_name'));
        self::assertSame('admin.roles.manage, personnel.assignments.manage', $lost[0]['lost'], 'Chef n’a que Membre : il perd les deux droits');
        self::assertSame('admin.roles.manage', $lost[1]['lost'], 'RH et chef garde « assignments.manage » par le niveau RH');
        self::assertSame('oui (admin.*)', $lost[1]['sensitive']);
    }
}
