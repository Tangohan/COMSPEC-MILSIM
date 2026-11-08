<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\OrganizationMemberNumberController;
use App\Repositories\TenantMemberNumberConfigRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\TenantMemberNumberService;
use App\Support\MemberNumberAnalysis;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Matricules : unité et grade dans le modèle, renumérotation avec aperçu, rétablissement depuis
 * l’historique, lecture du fichier d’import pour l’aperçu.
 */
final class MemberNumberToolsTest extends TestCase
{
    private const TENANT = 7;

    private PDO $pdo;

    /** @var array<int, string|null> Matricule de chaque membre (faux dépôt). */
    public array $numbers = [];

    /** @var array<int, array{unit: string, grade: string}> */
    public array $contexts = [];

    private ?bool $savedSchemaReady = null;

    protected function setUp(): void
    {
        // SQLite déguisé en MySQL : information_schema, DATABASE() et NOW() existent.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->exec("INSERT INTO information_schema.TABLES VALUES ('main', 'tenant_member_number_config'), ('main', 'tenant_member_number_audit')");
        $pdo->exec('CREATE TABLE tenant_member_number_config (tenant_id INTEGER PRIMARY KEY, enabled INTEGER, label TEXT, mode TEXT, pattern TEXT, prefix TEXT, next_sequence INTEGER, unique_required INTEGER, required INTEGER, created_at TEXT, updated_at TEXT)');
        $pdo->exec('CREATE TABLE tenant_member_number_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, old_value TEXT, new_value TEXT, reason TEXT, actor_user_id INTEGER, source TEXT, created_at TEXT)');
        $this->pdo = $pdo;

        $ready = new \ReflectionProperty(TenantMemberNumberConfigRepository::class, 'schemaReady');
        $this->savedSchemaReady = $ready->getValue();
        $ready->setValue(null, null);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(TenantMemberNumberConfigRepository::class, 'schemaReady'))->setValue(null, $this->savedSchemaReady);
    }

    private function config(string $pattern, string $prefix, int $next, int $unique = 1): void
    {
        $this->pdo->prepare('INSERT OR REPLACE INTO tenant_member_number_config VALUES (?, 1, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())')
            ->execute([self::TENANT, 'Matricule', 'assisted', $pattern, $prefix, $next, $unique]);
    }

    private function service(): TenantMemberNumberService
    {
        $configClass = new \ReflectionClass(TenantMemberNumberConfigRepository::class);
        $config = $configClass->newInstanceWithoutConstructor();
        $configClass->getProperty('pdo')->setValue($config, $this->pdo);

        $test = $this;
        $users = new class ($test) extends UserRepository {
            public function __construct(private MemberNumberToolsTest $t)
            {
                parent::__construct(null);
            }
            public function hasTenantMemberNumberColumn(): bool { return true; }
            public function findById(int $id, ?int $tenantId = null): ?array
            {
                return array_key_exists($id, $this->t->numbers) ? ['id' => $id, 'tenant_id' => $tenantId] : null;
            }
            public function tenantMemberNumberFor(int $userId, int $tenantId): ?string { return $this->t->numbers[$userId] ?? null; }
            public function updateTenantMemberNumber(int $userId, int $tenantId, ?string $value): bool
            {
                $this->t->numbers[$userId] = $value;

                return true;
            }
            public function tenantMemberNumberExists(int $tenantId, string $number, ?int $excludeUserId = null): bool
            {
                foreach ($this->t->numbers as $id => $v) {
                    if ($id !== $excludeUserId && $v !== null && strtolower($v) === strtolower($number)) {
                        return true;
                    }
                }

                return false;
            }
            public function memberNumberContextsForTenant(int $tenantId, array $userIds): array
            {
                return array_intersect_key($this->t->contexts, array_flip($userIds));
            }
        };
        $tenants = (new \ReflectionClass(TenantRepository::class))->newInstanceWithoutConstructor();

        return new TenantMemberNumberService($config, $users, $tenants, null);
    }

    /** @return list<array{id: int, tenant_member_number: string}> */
    private function members(): array
    {
        $out = [];
        foreach ($this->numbers as $id => $v) {
            $out[] = ['id' => $id, 'tenant_member_number' => (string) $v];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function byId(array $rows): array
    {
        return array_column($rows, null, 'id');
    }

    public function testUnitAndGradeAreCleanedAndRecognised(): void
    {
        self::assertSame('2EESC', TenantMemberNumberService::contextToken('2e esc.'));
        self::assertSame('', TenantMemberNumberService::contextToken(' — '));
        self::assertSame('SGTCHEF', TenantMemberNumberService::contextToken('Sgt-chef'));

        $svc = (new \ReflectionClass(TenantMemberNumberService::class))->newInstanceWithoutConstructor();
        $pattern = '{PREFIX}-{UNIT}-{GRADE}-{NUMBER:3}';
        $regexes = MemberNumberAnalysis::patternRegexes($pattern, 'G');
        self::assertCount(3, $regexes, "unité seule et grade seul donnent la même forme");
        foreach ([['A1', 'SGT'], ['A1', null], [null, 'SGT'], [null, null]] as [$u, $g]) {
            $value = $svc->format($pattern, 'G', 12, null, $u, $g);
            self::assertSame(12, MemberNumberAnalysis::matchSequence($value, $regexes), $value);
        }
        self::assertNull(MemberNumberAnalysis::matchSequence('G-A1-SGT-X', $regexes));
    }

    public function testRenumberPreviewKeepsNumbersFlagsConflictsAndUsesUnit(): void
    {
        $this->config('{PREFIX}-{UNIT}-{NUMBER:4}', 'G', 5);
        $this->numbers = [1 => 'OLD-7', 2 => 'G-0002', 3 => 'X', 4 => 'OLD-2', 5 => null];
        $this->contexts = [1 => ['unit' => 'a1', 'grade' => '']];
        $svc = $this->service();

        $plan = $svc->planRenumber(self::TENANT, $this->members(), 'off', 'keep');
        $rows = $this->byId($plan['rows']);

        self::assertSame([1, 3, 4], array_keys($rows));
        self::assertSame('G-A1-0007', $rows[1]['new']);
        self::assertSame('change', $rows[1]['status']);
        self::assertSame('G-0008', $rows[3]['new']);
        self::assertSame('conflict', $rows[4]['status']);
        self::assertSame(2, $plan['changes']);
        self::assertSame(9, $plan['next_after']);

        $refused = $svc->applyRenumber(self::TENANT, $this->members(), 'off', 'keep', 'mauvaise-empreinte', 3);
        self::assertFalse($refused['ok']);
        self::assertSame('OLD-7', $this->numbers[1]);

        $done = $svc->applyRenumber(self::TENANT, $this->members(), 'off', 'keep', $plan['hash'], 3);
        self::assertTrue($done['ok']);
        self::assertSame(2, $done['changed']);
        self::assertSame(['G-A1-0007', 'G-0002', 'G-0008', 'OLD-2'], [$this->numbers[1], $this->numbers[2], $this->numbers[3], $this->numbers[4]]);
        self::assertSame(9, (int) $this->pdo->query('SELECT next_sequence FROM tenant_member_number_config')->fetchColumn());
        $audit = $this->pdo->query("SELECT user_id, old_value, new_value, source FROM tenant_member_number_audit ORDER BY id")->fetchAll(PDO::FETCH_NUM);
        self::assertSame([[1, 'OLD-7', 'G-A1-0007', 'renumber'], [3, 'X', 'G-0008', 'renumber']], array_map(
            static fn (array $r): array => [(int) $r[0], $r[1], $r[2], $r[3]],
            $audit
        ));
    }

    public function testRenumberCanSwapNumbersAndRefusesAPatternWithoutNumber(): void
    {
        $this->config('{PREFIX}-{NUMBER:4}', 'B', 1);
        $this->numbers = [1 => 'B-0002', 2 => 'B-0001'];
        $svc = $this->service();

        $plan = $svc->planRenumber(self::TENANT, $this->members(), 'all', 'next');
        self::assertSame(2, $plan['changes']);
        $done = $svc->applyRenumber(self::TENANT, $this->members(), 'all', 'next', $plan['hash'], 3);
        self::assertSame(2, $done['changed']);
        self::assertSame([1 => 'B-0001', 2 => 'B-0002'], $this->numbers);

        $this->config('{PREFIX}', 'B', 1);
        self::assertArrayHasKey('error', $svc->planRenumber(self::TENANT, $this->members(), 'all', 'next'));
    }

    public function testRestoreFromHistoryGivesBackTheOldNumber(): void
    {
        $this->config('{PREFIX}-{NUMBER:4}', 'G', 1);
        $this->numbers = [1 => null, 2 => 'G-0009'];
        $this->pdo->exec("INSERT INTO tenant_member_number_audit (tenant_id, user_id, old_value, new_value, source, created_at) VALUES
            (7, 1, 'G-0004', NULL, 'manual', NOW()),
            (7, 2, NULL, 'G-0009', 'manual', NOW()),
            (8, 1, 'G-0005', NULL, 'manual', NOW()),
            (7, 2, 'G-0004', 'G-0009', 'manual', NOW())");
        $svc = $this->service();

        self::assertFalse($svc->restoreFromAudit(self::TENANT, 2, 3)['ok']);
        self::assertFalse($svc->restoreFromAudit(self::TENANT, 3, 3)['ok'], 'ligne d’une autre communauté');

        $ok = $svc->restoreFromAudit(self::TENANT, 1, 3);
        self::assertTrue($ok['ok']);
        self::assertSame('G-0004', $this->numbers[1]);

        $taken = $svc->restoreFromAudit(self::TENANT, 4, 3);
        self::assertFalse($taken['ok'], 'G-0004 appartient maintenant au membre 1');
        self::assertSame('G-0009', $this->numbers[2]);
    }

    public function testPageShowsRenumberAndImportPreviewsAndRestoreButton(): void
    {
        $members = [
            ['id' => 1, 'display_name' => 'Alpha', 'callsign' => '', 'email' => 'a@x.fr', 'athena_identifier' => '', 'tenant_member_number' => ''],
            ['id' => 2, 'display_name' => 'Bravo', 'callsign' => '', 'email' => 'b@x.fr', 'athena_identifier' => '', 'tenant_member_number' => 'OLD-3'],
        ];
        $vars = [
            'memberNumberSchemaReady' => true,
            'memberNumberConfig' => ['enabled' => 1, 'mode' => 'assisted', 'pattern' => '{PREFIX}-{UNIT}-{NUMBER:4}', 'prefix' => 'G', 'next_sequence' => 4],
            'memberNumberPreview' => 'G-0004',
            'memberNumberModes' => TenantMemberNumberService::MODES,
            'memberNumberCsrf' => 'tok',
            'memberNumberMembers' => $members,
            'memberNumberAnalysis' => MemberNumberAnalysis::analyze($members, '{PREFIX}-{UNIT}-{NUMBER:4}', 'G', null, 4),
            'memberNumberAudit' => [['id' => 9, 'user_id' => 1, 'member_name' => 'Alpha', 'old_value' => 'G-0001', 'new_value' => null, 'source' => 'manual', 'created_at' => '2026-10-01 10:00:00']],
            'memberNumberTenantToken' => '',
            'memberNumberRenumber' => ['scope' => 'off', 'numbering' => 'keep', 'rows' => [['id' => 2, 'old' => 'OLD-3', 'new' => 'G-A1-0003', 'status' => 'change', 'reason' => '']], 'changes' => 1, 'conflicts' => 0, 'next_after' => 4, 'hash' => 'abc'],
            'memberNumberImportPreview' => ['file' => 'liste.csv', 'rows' => [
                ['line' => 2, 'email' => 'a@x.fr', 'number' => 'G-0007', 'user_id' => 1, 'name' => 'Alpha', 'current' => '', 'status' => 'ok', 'message' => ''],
                ['line' => 3, 'email' => 'z@x.fr', 'number' => 'G-0008', 'user_id' => 0, 'name' => '', 'current' => '', 'status' => 'error', 'message' => 'Aucun membre de la communauté avec cette adresse.'],
            ]],
            'memberNumberContextSample' => ['unit' => 'A1', 'grade' => 'SGT'],
        ];
        $html = (static function (array $vars): string {
            extract($vars);
            ob_start();
            require dirname(__DIR__, 2) . '/views/admin/organization/member_numbers.php';

            return (string) ob_get_clean();
        })($vars);

        self::assertStringContainsString('Appliquer la renumérotation (1)', $html);
        self::assertStringContainsString('name="empreinte" value="abc"', $html);
        self::assertStringContainsString('G-A1-0003', $html);
        self::assertStringContainsString('Appliquer 1 ligne(s)', $html);
        self::assertStringContainsString('Aucun membre de la communauté avec cette adresse.', $html);
        self::assertStringContainsString('Réattribuer G-0001', $html);
        self::assertStringContainsString('data-unit="A1"', $html);
        self::assertStringContainsString('Code de l’unité du membre', $html);
    }

    public function testRepositoryReadsUnitCodeAndGradeOfEachMember(): void
    {
        $pdo = $this->pdo;
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->exec("INSERT INTO information_schema.TABLES VALUES ('main', 'user_units'), ('main', 'grades')");
        $pdo->exec("INSERT INTO information_schema.COLUMNS VALUES ('main', 'user_units', 'ended_at'), ('main', 'users', 'grade_id'), ('main', 'grades', 'label_short')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, grade_id INTEGER)');
        $pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER, code TEXT)');
        $pdo->exec('CREATE TABLE user_units (user_id INTEGER, unit_id INTEGER, ended_at TEXT)');
        $pdo->exec('CREATE TABLE grades (id INTEGER PRIMARY KEY, label_short TEXT)');
        $pdo->exec("INSERT INTO units VALUES (1, 7, ''), (2, 7, 'A1'), (3, 9, 'AUTRE')");
        $pdo->exec("INSERT INTO user_units VALUES (10, 1, NULL), (10, 2, NULL), (11, 3, NULL), (12, 2, '2026-01-01')");
        $pdo->exec("INSERT INTO grades VALUES (1, 'Sgt')");
        $pdo->exec('INSERT INTO users VALUES (10, 7, 1), (11, 7, NULL), (12, 7, NULL)');

        $props = [];
        foreach (['tableExistsCache', 'columnExistsCache', 'hasMembershipTable'] as $name) {
            $props[$name] = new \ReflectionProperty(UserRepository::class, $name);
            $saved[$name] = $props[$name]->getValue();
            $props[$name]->setValue(null, $name === 'hasMembershipTable' ? false : []);
        }
        try {
            $ctx = (new UserRepository($pdo))->memberNumberContextsForTenant(7, [10, 11, 12]);
        } finally {
            foreach ($props as $name => $prop) {
                $prop->setValue(null, $saved[$name]);
            }
        }

        self::assertSame([
            10 => ['unit' => 'A1', 'grade' => 'Sgt', 'unit_id' => 2],
            11 => ['unit' => '', 'grade' => '', 'unit_id' => 0],
            12 => ['unit' => '', 'grade' => '', 'unit_id' => 0],
        ], $ctx);
    }

    public function testImportFileWithMemberIdIsRead(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mn');
        file_put_contents($file, "nom;indicatif;id_membre;tenant_member_number
Alpha;A;12;G-0001
");
        try {
            $parsed = OrganizationMemberNumberController::parseImportCsv($file);
        } finally {
            unlink($file);
        }

        self::assertSame([['line' => 2, 'member_id' => 12, 'email' => '', 'number' => 'G-0001']], $parsed['rows']);
    }

    public function testImportFileIsReadForThePreview(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mn');
        file_put_contents($file, "\xEF\xBB\xBFnom;indicatif;email;tenant_member_number\nAlpha;A;a@x.fr;G-0001\n;;;\nBravo;;b@x.fr;\n");
        try {
            $parsed = OrganizationMemberNumberController::parseImportCsv($file);
        } finally {
            unlink($file);
        }

        self::assertArrayNotHasKey('error', $parsed);
        self::assertSame([
            ['line' => 2, 'member_id' => 0, 'email' => 'a@x.fr', 'number' => 'G-0001'],
            ['line' => 4, 'member_id' => 0, 'email' => 'b@x.fr', 'number' => ''],
        ], $parsed['rows']);

        $bad = tempnam(sys_get_temp_dir(), 'mn');
        file_put_contents($bad, "nom,email\nA,a@x.fr\n");
        try {
            self::assertArrayHasKey('error', OrganizationMemberNumberController::parseImportCsv($bad));
        } finally {
            unlink($bad);
        }
    }
}
