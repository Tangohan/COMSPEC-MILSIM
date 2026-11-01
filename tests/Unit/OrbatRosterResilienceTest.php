<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UnitCodeRepository;
use App\Repositories\UnitRepository;
use App\Services\Effectifs\UnitCodeService;
use App\Support\OrbatRosterPayload;
use App\Support\OrgVisibilityCapabilities;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlInformationSchemaPdo;

/**
 * L’ORBAT ne doit jamais tomber en erreur 500 : données incohérentes (boucle de rattachement, parent supprimé),
 * colonnes absentes ou requête annexe en échec ne retirent que l’enrichissement concerné.
 */
final class OrbatRosterResilienceTest extends TestCase
{
    private function sqliteUnits(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, parent_id INTEGER NULL, name TEXT, type TEXT, display_order INTEGER DEFAULT 0, commander_user_id INTEGER NULL)');

        return $pdo;
    }

    /** @param list<array<string, mixed>> $tree @return list<int> */
    private function ids(array $tree): array
    {
        $out = [];
        foreach (UnitRepository::flattenTree($tree) as $u) {
            $out[] = (int) $u['id'];
        }
        sort($out);

        return $out;
    }

    public function testTreeSurvivesSelfParentLoopAndMissingParent(): void
    {
        $pdo = $this->sqliteUnits();
        $pdo->exec("INSERT INTO units (id, tenant_id, parent_id, name, type) VALUES
            (1, 7, NULL, 'Commandement', 'command'),
            (2, 7, 1, 'Section A', 'group'),
            (3, 7, 3, 'Parent de lui-même', 'group'),
            (4, 7, 99, 'Parent supprimé', 'team'),
            (5, 7, 6, 'Boucle A', 'team'),
            (6, 7, 5, 'Boucle B', 'team'),
            (8, 9, NULL, 'Autre communauté', 'command')");

        $tree = (new UnitRepository($pdo))->getTree(7);

        // Toutes les unités du tenant apparaissent une seule fois, sans récursion infinie.
        self::assertSame([1, 2, 3, 4, 5, 6], $this->ids($tree));
        $roots = array_map(static fn (array $u): int => (int) $u['id'], $tree);
        self::assertContains(3, $roots);
        self::assertContains(4, $roots);
    }

    public function testPayloadKeepsTreeWhenEveryEnrichmentFails(): void
    {
        $repo = new class () extends UnitRepository {
            public function getTree(int $tenantId): array
            {
                return [[
                    'id' => 1, 'name' => 'Commandement', 'type' => 'command', 'parent_id' => null, 'commander_user_id' => 5,
                    'children' => [['id' => 2, 'name' => 'Section', 'type' => 'group', 'parent_id' => 1, 'code' => null, 'public_blurb' => null, 'children' => []]],
                ]];
            }
            public function countDistinctMembersByUnitForTenant(int $tenantId): array { throw new \PDOException('Unknown column uu.ended_at'); }
            public function commanderLabelByUnitForTenant(int $tenantId, array $unitsFlat): array { throw new \PDOException('boom'); }
            public function rosterMembersByUnitForTenant(int $tenantId, int $maxPerUnit = 40): array { throw new \PDOException('boom'); }
            public function personnelVisibilityByUserIdsForTenant(int $tenantId, array $userIds): array { throw new \PDOException('boom'); }
            public function readinessByUsersForTenant(int $tenantId, array $userIds): array { throw new \PDOException('boom'); }
            public function unitIdsForUser(int $tenantId, int $userId): array { throw new \PDOException('boom'); }
            public function gradeShortNamesForUsers(int $tenantId, array $userIds): array { throw new \PDOException('Unknown column g.short_name'); }
        };
        $codes = new UnitCodeService(new class () extends UnitCodeRepository {
            public function ensureTables(): bool { return false; }
        });
        $caps = new OrgVisibilityCapabilities();
        $caps->bypassAll = true;

        $logged = ini_get('error_log');
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try {
            $root = OrbatRosterPayload::buildForTenant($repo, 777001, 5, true, $caps, null, $codes);
        } finally {
            ini_set('error_log', (string) $logged);
        }

        self::assertIsArray($root);
        self::assertSame('Commandement', $root['label']);
        self::assertSame('—', $root['leader']);
        self::assertSame([], $root['members']);
        self::assertCount(1, $root['children']);
        self::assertSame('Section', $root['children'][0]['label']);
        self::assertSame('', $root['children'][0]['unitCode']);
    }

    public function testEmptyTenantGivesNullNotError(): void
    {
        $repo = new UnitRepository($this->sqliteUnits());
        self::assertNull(OrbatRosterPayload::buildForTenant($repo, 7, 0, true, new OrgVisibilityCapabilities()));
    }

    public function testGradeAbbreviationReadsCurrentAndLegacyColumns(): void
    {
        foreach (['label_short', 'short_name'] as $column) {
            $pdo = new MysqlInformationSchemaPdo();
            $pdo->givenTable('users', ['id', 'tenant_id', 'grade_id'])->givenTable('grades', ['id', $column]);
            $pdo->query('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, grade_id INTEGER NULL)');
            $pdo->query('CREATE TABLE grades (id INTEGER PRIMARY KEY, ' . $column . ' TEXT)');
            $pdo->query("INSERT INTO grades (id, {$column}) VALUES (1, 'SGT'), (2, '')");
            $pdo->query('INSERT INTO users (id, tenant_id, grade_id) VALUES (10, 7, 1), (11, 7, 2), (12, 7, NULL), (13, 8, 1)');

            $grades = (new UnitRepository($pdo))->gradeShortNamesForUsers(7, [10, 11, 12, 13, 0]);

            self::assertSame([10 => 'SGT'], $grades, 'colonne ' . $column);
        }
    }

    public function testGradeAbbreviationWithoutKnownColumnIsEmpty(): void
    {
        $pdo = new MysqlInformationSchemaPdo();
        $pdo->givenTable('users', ['id', 'tenant_id', 'grade_id'])->givenTable('grades', ['id', 'name']);

        self::assertSame([], (new UnitRepository($pdo))->gradeShortNamesForUsers(7, [10]));
    }
}
