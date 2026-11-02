<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\JobRolePickerService;
use App\Support\JobRoleBadge;
use App\Support\JobRoleIconLibrary;
use App\Support\Migrations\SchemaInspector;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Fonctions enrichies : abrégé unique par communauté, icône de la bibliothèque, étiquettes, origine, et fonctions
 * du site masquées par une communauté sans effet sur les membres qui les occupent ni sur les autres communautés.
 */
final class JobRoleEnrichmentTest extends TestCase
{
    private PDO $pdo;
    private mixed $previousPdo;

    protected function setUp(): void
    {
        JobRoleEnrichmentService::resetCache();
        JobRoleBadge::resetStyles();
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
        $this->pdo->exec("CREATE TABLE tenants(id INTEGER PRIMARY KEY); INSERT INTO tenants VALUES(14),(15);
            CREATE TABLE personnel_job_role_categories(id INTEGER PRIMARY KEY,tenant_id INTEGER,parent_id INTEGER,name TEXT,slug TEXT,sort_order INTEGER);
            INSERT INTO personnel_job_role_categories VALUES(1,14,NULL,'Santé','domaine-sante',0),(2,15,NULL,'Santé','domaine-sante',0);
            CREATE TABLE personnel_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,category_id INTEGER,source_unit_id INTEGER,name TEXT,slug TEXT,description TEXT,sort_order INTEGER,is_system INTEGER,abbreviation TEXT,icon TEXT);
            CREATE UNIQUE INDEX uk_pjr_tenant_abbreviation ON personnel_job_roles(tenant_id,abbreviation);
            INSERT INTO personnel_job_roles(id,tenant_id,category_id,name,slug,description,sort_order,is_system) VALUES
                (1,14,1,'Médecin militaire','medical_officer','Soins',0,0),
                (2,14,1,'Infirmier militaire','medical_nurse',NULL,0,0),
                (3,14,1,'Chef de groupe','chef-de-groupe','Commande le groupe',0,0),
                (4,15,2,'Médecin militaire','medical_officer',NULL,0,0);
            CREATE TABLE personnel_profile_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,personnel_job_role_id INTEGER,is_primary INTEGER,sort_order INTEGER,role_detail TEXT);
            INSERT INTO personnel_profile_job_roles(tenant_id,user_id,personnel_job_role_id,is_primary,sort_order,role_detail) VALUES(14,7,1,1,0,'');
            CREATE TABLE personnel_job_role_tags(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,name TEXT,color TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(tenant_id,name));
            CREATE TABLE personnel_job_role_tag_links(tenant_id INTEGER,job_role_id INTEGER,tag_id INTEGER,PRIMARY KEY(job_role_id,tag_id));
            CREATE TABLE personnel_job_role_catalog_hidden(tenant_id INTEGER,catalog_slug TEXT,hidden_by INTEGER,hidden_at TEXT,PRIMARY KEY(tenant_id,catalog_slug));");
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        JobRoleEnrichmentService::resetCache();
    }

    private function repository(): PersonnelJobRoleRepository
    {
        $repo = $this->getMockBuilder(PersonnelJobRoleRepository::class)->disableOriginalConstructor()
            ->onlyMethods(['tablesExist', 'pivotTableExists', 'jobRolesHaveMosColumns'])->getMock();
        $repo->method('tablesExist')->willReturn(true);
        $repo->method('pivotTableExists')->willReturn(true);
        $repo->method('jobRolesHaveMosColumns')->willReturn(false);
        (new ReflectionProperty(PersonnelJobRoleRepository::class, 'pdo'))->setValue($repo, $this->pdo);
        (new ReflectionProperty(PersonnelJobRoleRepository::class, 'jobRolesLabelEnColumn'))->setValue(null, false);

        return $repo;
    }

    public function testAbbreviationIsUppercaseShortAndUniqueWithinTheCommunityOnly(): void
    {
        self::assertSame('CDG', JobRoleEnrichmentService::normalizeAbbreviation(' cdg '));
        self::assertSame('JTAC', JobRoleEnrichmentService::normalizeAbbreviation('j-tac'));
        self::assertSame('MED', JobRoleEnrichmentService::normalizeAbbreviation('Méd'));
        self::assertNull(JobRoleEnrichmentService::normalizeAbbreviation('  '));
        foreach (['A', 'TROPLONGX', 'C/D'] as $invalid) {
            try {
                JobRoleEnrichmentService::normalizeAbbreviation($invalid);
                self::fail($invalid . ' accepté');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $service = new JobRoleEnrichmentService($this->pdo);
        self::assertTrue($service->schemaReady());
        $service->save(14, 1, $service->validate(14, 1, ['abbreviation' => 'med', 'icon' => 'medical', 'tags' => 'Santé, Qualifié nuit']));
        // Même abrégé dans la communauté : refusé ; la fonction elle-même peut le garder ; une autre communauté l'utilise librement.
        try {
            $service->validate(14, 2, ['abbreviation' => 'MED']);
            self::fail('Abrégé en double accepté.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('MED', $e->getMessage());
        }
        self::assertSame('MED', $service->validate(14, 1, ['abbreviation' => 'MED'])['abbreviation']);
        self::assertSame('MED', $service->validate(15, 4, ['abbreviation' => 'MED'])['abbreviation']);

        $this->expectException(InvalidArgumentException::class);
        $service->validate(14, 3, ['icon' => '<script>']);
    }

    public function testTagsAreReusedCaseInsensitivelyAndDecorationsCarryOriginAndDefaultIcon(): void
    {
        $service = new JobRoleEnrichmentService($this->pdo);
        $service->save(14, 1, $service->validate(14, 1, ['abbreviation' => 'MED', 'icon' => '', 'tags' => ['Santé', 'Qualifié nuit']]));
        $service->save(14, 3, $service->validate(14, 3, ['abbreviation' => 'CDG', 'icon' => 'star', 'tags' => 'santé, Commandement, Commandement']));
        $tags = array_column($service->tags(14), 'usage', 'name');
        self::assertSame(['Commandement' => 1, 'Qualifié nuit' => 1, 'Santé' => 2], $tags);
        self::assertSame([], $service->tags(15));
        $tagId = (int) $this->pdo->query("SELECT id FROM personnel_job_role_tags WHERE name='Commandement'")->fetchColumn();
        self::assertTrue($service->updateTagColor(14, $tagId, 'teal'));
        self::assertFalse($service->updateTagColor(15, $tagId, 'red'), 'Étiquette d’une autre communauté.');

        $decor = $service->decorations(14);
        self::assertSame(JobRoleEnrichmentService::ORIGIN_SITE, $decor[1]['origin']);
        self::assertSame('medical', $decor[1]['icon']);
        self::assertTrue($decor[1]['icon_is_default']);
        self::assertSame(JobRoleEnrichmentService::ORIGIN_COMMUNITY, $decor[3]['origin']);
        self::assertSame('star', $decor[3]['icon']);
        self::assertSame('teal', array_column($decor[3]['tags'], 'color', 'name')['Commandement']);
        self::assertSame([7 => true], $service->userIdsWithTag(14, (int) $this->pdo->query("SELECT id FROM personnel_job_role_tags WHERE name='Santé'")->fetchColumn()));

        $badge = JobRoleBadge::html($decor[3]);
        self::assertStringContainsString('CDG', $badge);
        self::assertStringContainsString('translate="no"', $badge);
        self::assertStringContainsString('title="Commande le groupe"', $badge);
        self::assertStringContainsString('<svg', $badge);
        self::assertSame('', JobRoleBadge::html(['icon' => 'inconnue', 'abbreviation' => '']));
        self::assertStringContainsString('Créée par vous', JobRoleBadge::origin('community'));
        self::assertTrue(JobRoleIconLibrary::has('radio'));
        self::assertFalse(JobRoleIconLibrary::has('../radio'));
    }

    public function testHidingASiteFunctionKeepsCurrentHoldersAndDoesNotAffectAnotherCommunity(): void
    {
        $service = new JobRoleEnrichmentService($this->pdo);
        // Seuls les identifiants du référentiel du site sont acceptés (pas une fonction créée par la communauté).
        self::assertSame(2, $service->setCatalogHidden(14, ['medical_officer', 'medical_nurse', 'chef-de-groupe', 'inconnu'], true, 7));
        self::assertSame(0, $service->setCatalogHidden(14, ['medical_officer'], true, 7), 'Relance sans effet.');
        self::assertEqualsCanonicalizing(['medical_officer' => true, 'medical_nurse' => true], $service->hiddenCatalogSlugs(14));
        self::assertSame([], $service->hiddenCatalogSlugs(15));
        $before = $this->pdo->query('SELECT * FROM personnel_profile_job_roles')->fetchAll(PDO::FETCH_ASSOC);

        $repo = $this->repository();
        $ids = array_column($repo->listRoleOptionsForSelect(14), 'hidden', 'id');
        // L'infirmier masqué n'est plus proposé ; le médecin masqué reste, signalé, car un membre l'occupe.
        self::assertEqualsCanonicalizing([1 => true, 3 => false], $ids);
        self::assertSame([4 => false], array_column($repo->listRoleOptionsForSelect(15), 'hidden', 'id'));

        $picker = new JobRolePickerService($repo);
        $values = static function (array $groups): array {
            $out = [];
            foreach ($groups as $group) {
                foreach ($group['items'] as $item) {
                    $out[$item['value']] = $item;
                }
            }

            return $out;
        };
        $tenant14 = $values($picker->groups(14, []));
        self::assertArrayHasKey('1', $tenant14, 'Fonction masquée encore occupée : gardée pour ne pas la retirer à l’enregistrement.');
        self::assertTrue($tenant14['1']['hidden']);
        self::assertArrayNotHasKey('2', $tenant14);
        self::assertArrayNotHasKey('cat:medical_nurse', $tenant14);
        self::assertSame('community', $tenant14['3']['origin']);
        $tenant15 = $values($picker->groups(15, []));
        self::assertArrayHasKey('4', $tenant15);
        self::assertArrayHasKey('cat:medical_nurse', $tenant15);
        self::assertSame('site', $tenant15['cat:medical_nurse']['origin']);
        self::assertSame('medical', $tenant15['cat:medical_nurse']['icon']);

        // Le membre garde sa fonction : aucune attribution n'est modifiée, l'affichage la conserve.
        self::assertSame($before, $this->pdo->query('SELECT * FROM personnel_profile_job_roles')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame('Médecin militaire', $repo->listPivotAssignmentsForUsers(14, [7])[7][0]['role_name']);
        self::assertTrue($service->decorations(14)[1]['hidden']);
        self::assertFalse($service->decorations(15)[4]['hidden']);

        self::assertSame(1, $service->setCatalogHidden(14, ['medical_nurse'], false, 7));
        self::assertArrayHasKey('2', $values($picker->groups(14, [])));
        self::assertEqualsCanonicalizing([1 => true, 2 => false, 3 => false], array_column($repo->listRoleOptionsForSelect(14), 'hidden', 'id'));
    }

    public function testMigrationStepAddsColumnsIndexAndTablesOnceAndSkipsWithoutJobRoles(): void
    {
        $root = dirname(__DIR__, 2);
        $run = static function (PDO $pdo) use ($root): string {
            $migrationFlush = static function (): void {
            };
            $migrationEnsurePdo = static function (): void {
            };
            require $root . '/database/steps/_context.php';
            ob_start();
            require $root . '/database/steps/3360_personnel_job_role_enrichment.php';

            return (string) ob_get_clean();
        };
        $empty = new MysqlDdlSqlitePdo();
        self::assertStringContainsString('rien à faire', $run($empty));
        self::assertSame(0, $empty->changes);

        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT)');
        $pdo->changes = 0;
        $first = $run($pdo);
        self::assertStringNotContainsString('[ERREUR]', $first);
        $schema = new SchemaInspector($pdo);
        self::assertTrue($schema->columnExists('personnel_job_roles', 'abbreviation'));
        self::assertTrue($schema->columnExists('personnel_job_roles', 'icon'));
        self::assertTrue($schema->indexExists('personnel_job_roles', 'uk_pjr_tenant_abbreviation'));
        foreach (['personnel_job_role_tags', 'personnel_job_role_tag_links', 'personnel_job_role_catalog_hidden'] as $table) {
            self::assertTrue($schema->tableExists($table), $table);
        }
        $pdo->changes = 0;
        $pdo->ddl = [];
        $run($pdo);
        self::assertSame(0, $pdo->changes, implode(' | ', $pdo->ddl));
        // Index unique : deux abrégés vides permis, un abrégé en double refusé.
        $pdo->exec("INSERT INTO personnel_job_roles (id, tenant_id, slug, abbreviation) VALUES (1, 1, 'a', NULL), (2, 1, 'b', NULL), (3, 1, 'c', 'CDG'), (4, 2, 'c', 'CDG')");
        $this->expectException(\PDOException::class);
        $pdo->exec("INSERT INTO personnel_job_roles (id, tenant_id, slug, abbreviation) VALUES (5, 1, 'd', 'CDG')");
    }
}
