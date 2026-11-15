<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Migrations\SchemaInspector;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\SseTriageSqlite;

/**
 * Étape 4740 : tables d'état, de règles et de journal de « À trier » et colonnes de déduplication des signaux. Enregistrée, numérotée une
 * seule fois, idempotente, sans ALTER … ADD UNIQUE (l'unicité est dans le CREATE TABLE), retour arrière fourni, aucune donnée modifiée.
 */
final class SseTriageAutomatismsStepTest extends TestCase
{
    protected function tearDown(): void
    {
        SseTriageSqlite::close();
    }

    public function testTheStepIsRegisteredOnceWithItsFilesAndTheNextFreeNumber(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = require $root . '/database/steps/registry.php';
        $mine = array_values(array_filter($registry, static fn (array $s): bool => $s['id'] === 'sse_triage_automatisms'));

        self::assertCount(1, $mine);
        self::assertSame('schema', $mine[0]['group']);
        self::assertTrue($mine[0]['rerun']);
        self::assertSame('4740_sse_triage_automatisms.php', $mine[0]['file']);
        self::assertFileExists($root . '/database/steps/' . $mine[0]['file']);
        self::assertFileExists($root . '/migrations/2026_11_18_100_sse_triage_automatisms.sql');
        self::assertFileExists($root . '/migrations/rollback/sse_triage_automatisms_down.sql');
        self::assertStringContainsString('migrations/rollback/sse_triage_automatisms_down.sql', $mine[0]['label']);
        $numbers = array_map(static fn (array $s): string => substr((string) $s['file'], 0, 4), $registry);
        self::assertSame(1, count(array_filter($numbers, static fn (string $n): bool => $n === '4740')), 'numéro 4740 pris une seule fois');
        self::assertSame('4740', max(array_filter($numbers, static fn (string $n): bool => ctype_digit($n))), 'le plus grand numéro de la file');
        self::assertCount(count($registry), array_unique(array_column($registry, 'id')), 'identifiants uniques');
    }

    public function testTheStepCreatesTheTablesAndColumnsThenChangesNothingTheSecondTime(): void
    {
        $pdo = SseTriageSqlite::open(false);
        $before = (int) $pdo->changes;
        $first = SseTriageSqlite::runStep($pdo);
        $afterFirst = (int) $pdo->changes;
        $second = SseTriageSqlite::runStep($pdo);

        self::assertGreaterThan($before, $afterFirst);
        self::assertStringContainsString('Tables créées : sse_triage_state, sse_triage_rules, sse_triage_events', $first);
        self::assertStringContainsString('dedup_key', $first);
        self::assertSame($afterFirst, (int) $pdo->changes, 'rejouée : aucune modification du schéma');
        self::assertStringContainsString('déjà présentes', $second);
        $schema = new SchemaInspector($pdo);
        foreach (['sse_triage_state', 'sse_triage_rules', 'sse_triage_events'] as $t) {
            self::assertTrue($schema->tableExists($t), $t);
        }
        foreach (['dedup_key', 'last_seen_at', 'seen_count', 'changed_at', 'resolved_at', 'resolved_by', 'merged_into'] as $c) {
            self::assertTrue($schema->columnExists('sse_engine_signals', $c), $c);
        }
        self::assertTrue($schema->indexExists('sse_engine_signals', 'idx_sse_signal_dedup'));
    }

    public function testNoExistingRowIsTouched(): void
    {
        $pdo = SseTriageSqlite::open(false);
        $pdo->exec("INSERT INTO sse_engine_signals (id, tenant_id, case_id, signal_type, severity, title, status, rule_key, created_at, updated_at) VALUES (1, 1, 5, 'gap_auto', 'medium', 'Lacune / besoin proposé : Photographie manquante', 'open', 'auto_gap', '2026-10-01 00:00:00', '2026-10-01 00:00:00')");
        $row = $pdo->query('SELECT id, status, title, created_at, updated_at FROM sse_engine_signals')->fetch(\PDO::FETCH_ASSOC);
        SseTriageSqlite::runStep($pdo);

        self::assertSame($row, $pdo->query('SELECT id, status, title, created_at, updated_at FROM sse_engine_signals')->fetch(\PDO::FETCH_ASSOC));
        self::assertNull($pdo->query('SELECT dedup_key FROM sse_engine_signals')->fetchColumn() ?: null, 'les nouvelles colonnes restent vides : rien n’est rempli par l’étape');
    }

    public function testTheUniqueKeyIsInTheCreateTableAndNoAlterAddUniqueIsUsed(): void
    {
        $root = dirname(__DIR__, 2);
        $sql = (string) file_get_contents($root . '/migrations/2026_11_18_100_sse_triage_automatisms.sql');
        $step = (string) file_get_contents($root . '/database/steps/4740_sse_triage_automatisms.php');

        self::assertMatchesRegularExpression('/CREATE TABLE IF NOT EXISTS sse_triage_state \(.*?UNIQUE KEY uq_sse_triage_item \(tenant_id, item_type, item_key\).*?\)\s*ENGINE/s', $sql);
        foreach ([$sql, $step] as $source) {
            self::assertStringNotContainsString('ADD UNIQUE', strtoupper($source));
        }
        self::assertStringContainsString("'sse_triage_state', 'sse_triage_rules', 'sse_triage_events'", $step);
        self::assertStringNotContainsString('DROP ', strtoupper($sql . $step), 'l’étape ne supprime rien');
    }

    public function testTheTablesCarryEveryColumnTheSpecificationNames(): void
    {
        $pdo = SseTriageSqlite::open(true);
        $schema = new SchemaInspector($pdo);
        foreach (['tenant_id', 'item_type', 'item_key', 'status', 'snoozed_until', 'acknowledged_by', 'acknowledged_at', 'assigned_to', 'note', 'rule_id', 'updated_at'] as $c) {
            self::assertTrue($schema->columnExists('sse_triage_state', $c), 'sse_triage_state.' . $c);
        }
        foreach (['tenant_id', 'name', 'source_key', 'type_key', 'min_age_days', 'min_severity', 'max_severity', 'scope', 'action', 'action_value', 'enabled', 'last_fired_at', 'fired_count'] as $c) {
            self::assertTrue($schema->columnExists('sse_triage_rules', $c), 'sse_triage_rules.' . $c);
        }
        foreach (['tenant_id', 'batch_id', 'item_type', 'item_key', 'action', 'actor_user_id', 'actor_label', 'rule_id', 'before_json', 'after_json', 'undone_at', 'created_at'] as $c) {
            self::assertTrue($schema->columnExists('sse_triage_events', $c), 'sse_triage_events.' . $c);
        }
    }

    public function testTheRollbackDropsOnlyWhatTheStepCreated(): void
    {
        $down = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/rollback/sse_triage_automatisms_down.sql');

        foreach (['sse_triage_events', 'sse_triage_rules', 'sse_triage_state'] as $t) {
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $t, $down);
        }
        self::assertStringContainsString('DROP INDEX idx_sse_signal_dedup', $down);
        foreach (['dedup_key', 'last_seen_at', 'seen_count', 'changed_at', 'resolved_at', 'resolved_by', 'merged_into'] as $c) {
            self::assertStringContainsString('DROP COLUMN', $down);
            self::assertStringContainsString($c, $down);
        }
        self::assertStringNotContainsString('DROP TABLE IF EXISTS sse_engine_signals', $down);
        self::assertStringNotContainsString('DELETE FROM', strtoupper($down));
    }

    public function testASeparateDatabaseWithoutSignalsTableStillGetsTheTriageTables(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $out = SseTriageSqlite::runStep($pdo);

        self::assertStringContainsString('sse_engine_signals absente', $out);
        self::assertTrue((new SchemaInspector($pdo))->tableExists('sse_triage_state'));
    }
}
