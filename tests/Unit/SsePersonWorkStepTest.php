<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Étape 4800 « SSE Personnes » : colonnes de classification, verrouillage et clôture, table des ouvertures (unicité dans le
 * CREATE TABLE), rejouable sans effet, enregistrée dans la file, retour arrière présent. Jouée sur une base sqlite, jamais sur la
 * production.
 */
final class SsePersonWorkStepTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<string> */
    private function columns(PDO $pdo, string $table): array
    {
        return array_map(static fn (array $r): string => (string) $r['name'], $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC));
    }

    private function playStep(PDO $pdo): string
    {
        $root = self::ROOT;
        ob_start();
        (static function () use ($pdo, $root): void {
            require $root . '/database/steps/4800_sse_persons_work.php';
        })();

        return (string) ob_get_clean();
    }

    public function testStepAddsTheColumnsAndTheViewsTableThenReplaysWithoutEffect(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $pdo->exec('CREATE TABLE sse_persons (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, last_name TEXT)');
        $pdo->exec("INSERT INTO sse_persons (tenant_id, last_name) VALUES (1, 'Alpha')");

        $first = $this->playStep($pdo);
        self::assertStringContainsString('Ajouté à sse_persons', $first);
        $cols = $this->columns($pdo, 'sse_persons');
        foreach (['classification', 'locked_at', 'locked_by', 'lock_reason', 'lock_until', 'closed_at', 'closed_by', 'close_reason'] as $col) {
            self::assertContains($col, $cols);
        }
        self::assertSame(['id', 'tenant_id', 'user_id', 'person_id', 'last_opened_at', 'open_count'], array_slice($this->columns($pdo, 'sse_person_views'), 0, 6));
        self::assertSame('Alpha', $pdo->query('SELECT last_name FROM sse_persons')->fetchColumn(), 'Aucune donnée modifiée');
        self::assertNull($pdo->query('SELECT classification FROM sse_persons')->fetchColumn());

        $changes = $pdo->changes;
        $second = $this->playStep($pdo);
        self::assertSame($changes, $pdo->changes, 'Rejouée : aucune modification du schéma');
        self::assertStringContainsString('déjà présentes', $second);
        self::assertStringContainsString('déjà présente', $second);

        // L'unicité (communauté, compte, personne) est dans la table : une seconde ligne est refusée.
        $pdo->exec("INSERT INTO sse_person_views (tenant_id, user_id, person_id, last_opened_at) VALUES (1, 7, 1, '2026-11-15 10:00:00')");
        $this->expectException(\PDOException::class);
        $pdo->exec("INSERT INTO sse_person_views (tenant_id, user_id, person_id, last_opened_at) VALUES (1, 7, 1, '2026-11-15 11:00:00')");
    }

    public function testStepWaitsWhenThePersonTableDoesNotExistYet(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $out = $this->playStep($pdo);
        self::assertStringContainsString('sse_persons absente', $out);
        self::assertContains('open_count', $this->columns($pdo, 'sse_person_views'));
    }

    public function testStepIsRegisteredWithItsRollbackAndKeepsTheUniqueKeyInTheCreateTable(): void
    {
        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString("'id' => 'sse_persons_work'", $registry);
        self::assertStringContainsString("'file' => '4800_sse_persons_work.php'", $registry);
        self::assertSame(1, substr_count($registry, "'file' => '4800_"), 'Numéro de l’étape unique');
        $sql = (string) file_get_contents(self::ROOT . '/migrations/2026_11_17_111_sse_person_views.sql');
        self::assertStringContainsString('UNIQUE KEY uq_sse_person_views (tenant_id, user_id, person_id)', $sql);
        self::assertStringNotContainsString('ALTER TABLE sse_person_views', $sql, 'Pas d’ALTER … ADD UNIQUE KEY (sqlite)');
        $down = (string) file_get_contents(self::ROOT . '/migrations/rollback/sse_persons_work_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS sse_person_views', $down);
        foreach (['classification', 'locked_at', 'locked_by', 'lock_reason', 'lock_until', 'closed_at', 'closed_by', 'close_reason'] as $col) {
            self::assertStringContainsString('DROP COLUMN ' . $col, $down);
        }
    }

    public function testNoNewPermissionIsAddedToTheRolesCatalog(): void
    {
        // La page s'appuie sur la section « fichiers » de SseAccessPolicy (voir, écrire, gérer, exporter) : aucun droit nouveau
        // à déclarer dans la page Rôles, donc rien à accorder automatiquement.
        foreach ([
            '/app/Services/Sse/SsePersonWorkService.php', '/app/Services/Sse/SsePersonViewer.php', '/app/Controllers/Web/SsePersonWorkController.php',
        ] as $file) {
            $src = (string) file_get_contents(self::ROOT . $file);
            self::assertDoesNotMatchRegularExpression('/can\(\s*[\'"]atak\.sse\.person/', $src, $file);
        }
        $viewer = (string) file_get_contents(self::ROOT . '/app/Services/Sse/SsePersonViewer.php');
        self::assertStringContainsString("sessionAllows('fichiers'", $viewer);
    }

    public function testGameApiNeverReturnsClosedOrLockedFiles(): void
    {
        $api = (string) file_get_contents(self::ROOT . '/app/Controllers/Api/SseApiController.php');
        self::assertGreaterThanOrEqual(5, substr_count($api, 'withoutGameHidden'), 'Liste, fiche, unité, retransmission, photo');
        self::assertStringContainsString('$this->persons->withoutGameHidden($tenantId, $persons)', $api);
    }
}
