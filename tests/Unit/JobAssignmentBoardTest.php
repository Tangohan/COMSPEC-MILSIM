<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\JobAssignmentBoard;
use App\Services\Personnel\JobAssignmentBoardLoader;
use App\Services\Personnel\JobAssignmentBulkService;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Onglet « Qui tient quel emploi » : filtres de l’URL, recherche, indicateurs, confidentialité des membres et unités,
 * actions groupées (attribuer, retirer, remplacer) et export CSV.
 */
final class JobAssignmentBoardTest extends TestCase
{
    private PDO $pdo;
    private mixed $previousPdo;

    protected function setUp(): void
    {
        JobRoleEnrichmentService::resetCache();
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
        $this->pdo->exec("CREATE TABLE personnel_job_role_categories(id INTEGER PRIMARY KEY,tenant_id INTEGER,parent_id INTEGER,name TEXT,slug TEXT,sort_order INTEGER);
            INSERT INTO personnel_job_role_categories VALUES(1,14,NULL,'Santé','sante',0),(2,14,1,'Équipe médicale','equipe-med',0),(3,14,NULL,'Commandement','cdt',1),(9,15,NULL,'Autre','autre',0);
            CREATE TABLE personnel_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,category_id INTEGER,source_unit_id INTEGER,name TEXT,slug TEXT,description TEXT,sort_order INTEGER,is_system INTEGER,abbreviation TEXT,icon TEXT);
            INSERT INTO personnel_job_roles(id,tenant_id,category_id,name,slug,sort_order,is_system,abbreviation) VALUES
                (1,14,1,'Médecin militaire','medical_officer',0,0,'MED'),(2,14,2,'Infirmier','infirmier-perso',0,0,NULL),
                (3,14,3,'Chef de groupe','chef-de-groupe',0,0,'CDG'),(4,15,9,'Pilote','pilote',0,0,NULL);
            CREATE TABLE personnel_profile_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,personnel_job_role_id INTEGER,is_primary INTEGER,sort_order INTEGER,role_detail TEXT);
            INSERT INTO personnel_profile_job_roles(tenant_id,user_id,personnel_job_role_id,is_primary,sort_order,role_detail) VALUES
                (14,7,1,1,0,'Chef'),(14,7,3,0,1,''),(14,8,1,1,0,''),(14,9,2,1,0,''),(14,10,3,1,0,''),(15,50,4,1,0,'');
            CREATE TABLE personnel_job_role_tags(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,name TEXT,color TEXT,created_at TEXT,UNIQUE(tenant_id,name));
            INSERT INTO personnel_job_role_tags(id,tenant_id,name,color) VALUES(5,14,'Santé',NULL);
            CREATE TABLE personnel_job_role_tag_links(tenant_id INTEGER,job_role_id INTEGER,tag_id INTEGER,PRIMARY KEY(job_role_id,tag_id));
            INSERT INTO personnel_job_role_tag_links VALUES(14,1,5);
            CREATE TABLE personnel_job_role_catalog_hidden(tenant_id INTEGER,catalog_slug TEXT,hidden_by INTEGER,hidden_at TEXT,PRIMARY KEY(tenant_id,catalog_slug));");
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        JobRoleEnrichmentService::resetCache();
    }

    /** @param list<array<string,mixed>> $users */
    private function jobRoles(array $users = []): PersonnelJobRoleRepository
    {
        $repo = $this->getMockBuilder(PersonnelJobRoleRepository::class)->disableOriginalConstructor()
            ->onlyMethods(['tablesExist', 'pivotTableExists', 'personnelProfilesHaveJobRoleColumns', 'countUsersForJobRoleAssignments', 'listUsersForJobRoleAssignments'])->getMock();
        $repo->method('tablesExist')->willReturn(true);
        $repo->method('pivotTableExists')->willReturn(true);
        $repo->method('personnelProfilesHaveJobRoleColumns')->willReturn(true);
        $repo->method('countUsersForJobRoleAssignments')->willReturn(count($users));
        $repo->method('listUsersForJobRoleAssignments')->willReturnCallback(static fn (int $t, $s, $j, $u, int $limit, int $offset): array => array_slice($users, $offset, $limit));
        (new ReflectionProperty(PersonnelJobRoleRepository::class, 'pdo'))->setValue($repo, $this->pdo);
        (new ReflectionProperty(PersonnelJobRoleRepository::class, 'jobRolesLabelEnColumn'))->setValue(null, false);

        return $repo;
    }

    private static function member(string $key, array $extra = []): array
    {
        return $extra + ['key' => $key, 'id' => (int) substr($key, 1), 'name' => 'Membre ' . $key, 'callsign' => '', 'unit_label' => '', 'unit_path' => '',
            'unit_chain' => [], 'grade' => '', 'grade_long' => '', 'grade_order' => 999, 'account_status' => 'active', 'situation' => '',
            'jobs' => [], 'primary_job' => 0, 'selectable' => true, 'anonymized' => false];
    }

    private static function job(int $id, array $extra = []): array
    {
        return $extra + ['id' => $id, 'name' => 'Emploi ' . $id, 'category_id' => 1, 'category' => 'Santé', 'abbreviation' => null, 'tags' => [],
            'origin' => 'community', 'hidden' => false, 'places' => 0, 'linked_unit_ids' => [], 'linked_unit_chain' => [], 'holder_keys' => [], 'billets' => []];
    }

    /** @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>, 2: list<array<string,mixed>>} */
    private static function dataset(): array
    {
        $members = [
            self::member('u7', ['name' => 'Élodie Martin', 'callsign' => 'VIPER', 'unit_label' => '1re section', 'unit_chain' => [100, 110], 'grade' => 'SGT', 'grade_order' => 5, 'jobs' => [1, 3], 'primary_job' => 1]),
            self::member('u8', ['name' => 'Paul Durand', 'unit_label' => '2e section', 'unit_chain' => [100, 120], 'grade' => 'CPL', 'grade_order' => 7, 'jobs' => [1], 'primary_job' => 1, 'situation' => 'Réserve']),
            self::member('u9', ['name' => 'Zoé Petit', 'unit_label' => 'Base arrière', 'unit_chain' => [200], 'grade' => 'SGT', 'grade_order' => 5, 'account_status' => 'inactive', 'jobs' => []]),
        ];
        $jobs = [
            self::job(1, ['name' => 'Médecin militaire', 'abbreviation' => 'MED', 'tags' => [['id' => 5, 'name' => 'Santé', 'color' => null]], 'places' => 3, 'holder_keys' => ['u7', 'u8']]),
            self::job(2, ['name' => 'Infirmier', 'category_id' => 2, 'category' => 'Santé / Équipe médicale', 'places' => 1, 'linked_unit_ids' => [120], 'linked_unit_chain' => [100, 120]]),
            self::job(3, ['name' => 'Chef de groupe', 'abbreviation' => 'CDG', 'category_id' => 3, 'category' => 'Commandement', 'origin' => 'site', 'holder_keys' => ['u7']]),
            self::job(4, ['name' => 'Radio masqué', 'origin' => 'site', 'hidden' => true]),
        ];
        $categories = [['id' => 1, 'parent_id' => null, 'name' => 'Santé'], ['id' => 2, 'parent_id' => 1, 'name' => 'Équipe médicale'], ['id' => 3, 'parent_id' => null, 'name' => 'Commandement']];

        return [$jobs, $members, $categories];
    }

    private static function ids(array $rows, string $field = 'id'): array
    {
        return array_map(static fn (array $r): int|string => $r[$field], $rows);
    }

    public function testFiltersAreBoundedAndOnlyNonDefaultValuesStayInTheUrl(): void
    {
        $f = JobAssignmentBoard::filters(['q' => '  médecin ', 'categorie' => '-4', 'etat' => 'nimporte', 'affichage' => 'membres', 'tri' => 'vacants', 'origine' => 'masquees']);
        self::assertSame('médecin', $f['q']);
        self::assertSame(0, $f['categorie']);
        self::assertSame('', $f['etat']);
        self::assertSame('nom', $f['tri'], 'un tri d’emplois n’est pas un tri de membres');
        self::assertTrue($f['sous_unites']);
        self::assertSame(['vue' => 'attributions', 'affichage' => 'membres', 'q' => 'médecin', 'origine' => 'masquees'], JobAssignmentBoard::query($f));
        self::assertSame(['vue' => 'attributions', 'unite' => '12', 'sous_unites' => '0'], JobAssignmentBoard::query(JobAssignmentBoard::filters(['unite' => '12', 'sous_unites' => '0'])));
    }

    public function testSearchIsAccentInsensitiveAcrossJobsTagsMembersCallsignsAndUnits(): void
    {
        [$jobs, $members, $cats] = self::dataset();
        $search = static fn (string $q, string $view = 'matrice'): array => JobAssignmentBoard::build($jobs, $members, JobAssignmentBoard::filters(['q' => $q, 'affichage' => $view]), $cats);
        self::assertSame([1], self::ids($search('medecin')['jobs']));
        self::assertSame([3, 1], self::ids($search('viper')['jobs']), 'indicatif d’un titulaire');
        self::assertSame(['u7'], $search('viper')['jobs'][0]['matching_keys']);
        self::assertSame([1, 2], self::ids($search('sante')['jobs']), 'une catégorie ou une étiquette « Santé »');
        self::assertSame([1], self::ids($search('2e section')['jobs']));
        self::assertSame(['u7', 'u8'], self::ids($search('MED', 'membres')['members'], 'key'), 'un membre est trouvé par l’abrégé d’un emploi tenu');
        self::assertSame(['u7'], self::ids($search('elodie', 'membres')['members'], 'key'));
    }

    public function testJobFiltersCategoryWithSubcategoriesTagAndOrigin(): void
    {
        [$jobs, $members, $cats] = self::dataset();
        $build = static fn (array $q): array => JobAssignmentBoard::build($jobs, $members, JobAssignmentBoard::filters($q), $cats);
        self::assertSame([3, 1, 2], self::ids($build([])['jobs']), 'tri par catégorie ; une fonction du site masquée sans titulaire n’est pas proposée');
        self::assertSame([1, 2], self::ids($build(['categorie' => 1])['jobs']), 'Santé couvre Équipe médicale');
        self::assertSame([1], self::ids($build(['etiquette' => 5])['jobs']));
        self::assertSame([3], self::ids($build(['origine' => 'site'])['jobs']));
        self::assertSame([4], self::ids($build(['origine' => 'masquees'])['jobs']));
        self::assertSame(['u7'], self::ids($build(['origine' => 'site', 'affichage' => 'membres'])['members'], 'key'));
    }

    public function testPersonFiltersUnitSubunitsGradeAndStatusRestrictHolders(): void
    {
        [$jobs, $members, $cats] = self::dataset();
        $build = static fn (array $q): array => JobAssignmentBoard::build($jobs, $members, JobAssignmentBoard::filters($q), $cats);
        $unit = $build(['unite' => 100]);
        self::assertSame([3, 1, 2], self::ids($unit['jobs']), 'l’infirmier vacant reste visible : il est rattaché à une sous-unité');
        $direct = $build(['unite' => 120, 'sous_unites' => '0']);
        self::assertSame([1, 2], self::ids($direct['jobs']));
        self::assertSame(['u8'], self::ids($direct['jobs'][0]['holders'], 'key'));
        self::assertSame([], self::ids($build(['unite' => 100, 'sous_unites' => '0'])['members'], 'key'), 'personne n’est affecté à la seule unité mère');
        self::assertSame(['u7', 'u9'], self::ids($build(['grade' => 'SGT', 'affichage' => 'membres'])['members'], 'key'));
        self::assertSame(['u9'], self::ids($build(['statut' => 'compte:inactive', 'affichage' => 'membres'])['members'], 'key'));
        self::assertSame(['u8'], self::ids($build(['statut' => 'situation:reserve', 'affichage' => 'membres'])['members'], 'key'));
    }

    public function testStatesIndicatorsAndSorting(): void
    {
        [$jobs, $members, $cats] = self::dataset();
        $build = static fn (array $q): array => JobAssignmentBoard::build($jobs, $members, JobAssignmentBoard::filters($q), $cats);
        self::assertSame([2, 1], self::ids($build(['etat' => 'vacants', 'tri' => 'nom'])['jobs']), 'Médecin : 2 titulaires pour 3 places');
        self::assertSame([2], self::ids($build(['etat' => 'sans_titulaire'])['jobs']));
        self::assertSame([2], self::ids($build(['etat' => 'critiques'])['jobs']), 'poste ORBAT prévu sans titulaire');
        self::assertSame(['u9'], self::ids($build(['etat' => 'sans_emploi', 'affichage' => 'membres'])['members'], 'key'));
        self::assertSame(['u7'], self::ids($build(['etat' => 'multi', 'affichage' => 'membres'])['members'], 'key'));
        self::assertSame([1, 3, 2], self::ids($build(['tri' => 'titulaires'])['jobs']));
        self::assertSame([2, 1, 3], self::ids($build(['tri' => 'vacants'])['jobs']));
        self::assertSame(['u7', 'u8', 'u9'], self::ids($build(['affichage' => 'membres', 'tri' => 'emplois'])['members'], 'key'));
        $ind = $build([])['indicators'];
        self::assertSame(['jobs' => 3, 'jobs_held' => 2, 'coverage' => 67, 'vacant' => 1, 'critical' => 1, 'places' => 4, 'places_filled' => 2,
            'members' => 3, 'with_job' => 2, 'without_job' => 1, 'multi' => 1, 'links' => 3], $ind);
        $facets = $build([])['facets'];
        self::assertSame(2, $facets['units'][100]);
        self::assertSame(2, $facets['grades']['SGT']);
        self::assertSame(1, $facets['statuses']['situation:reserve']);
    }

    public function testCsvNeutralisesFormulasAndKeepsVacantJobs(): void
    {
        [$jobs, $members, $cats] = self::dataset();
        $members[0]['name'] = '=HYPERLINK("x")';
        $csv = JobAssignmentBoard::csv(JobAssignmentBoard::build($jobs, $members, JobAssignmentBoard::filters([]), $cats), ['vacant' => 'Vacant', 'yes' => 'Oui', 'job' => 'Emploi']);
        self::assertStringStartsWith("\xEF\xBB\xBF\"Emploi\";", $csv);
        self::assertStringContainsString('"\'=HYPERLINK(""x"")"', $csv);
        self::assertStringContainsString('"Infirmier";"";"Santé / Équipe médicale";"";"origin_community";"1";"0";"Vacant"', $csv);
    }

    public function testLoaderHidesMembersAnonymisesIdentitiesAndRedactsRestrictedUnitsForAnOrdinaryReader(): void
    {
        $users = [['id' => 7, 'display_name' => 'Élodie Martin', 'callsign' => 'VIPER', 'status' => 'active'],
            ['id' => 8, 'display_name' => 'Agent secret', 'callsign' => 'GHOST', 'status' => 'active'],
            ['id' => 9, 'display_name' => 'Masqué total', 'callsign' => 'NONE', 'status' => 'active'],
            ['id' => 10, 'display_name' => 'Opérateur spécial', 'callsign' => 'SOF', 'status' => 'active']];
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('listEffectifsRosterByIds')->willReturn([
            ['id' => 7, 'unit_id' => 110, 'unit_name' => '1re section', 'grade_short' => 'SGT'],
            ['id' => 8, 'unit_id' => 110, 'unit_name' => '1re section', 'grade_short' => 'ADJ'],
            ['id' => 9, 'unit_id' => 110, 'unit_name' => '1re section'],
            ['id' => 10, 'unit_id' => 300, 'unit_name' => 'Détachement S'],
        ]);
        $units = $this->createMock(UnitRepository::class);
        $units->method('listFlatForStructure')->willReturn([
            ['id' => 100, 'name' => 'Compagnie', 'parent_id' => null, 'visibility_level' => VisibilityLevel::NORMAL],
            ['id' => 110, 'name' => '1re section', 'parent_id' => 100, 'visibility_level' => VisibilityLevel::NORMAL],
            ['id' => 300, 'name' => 'Détachement S', 'parent_id' => null, 'visibility_level' => VisibilityLevel::HIDDEN],
        ]);
        $units->method('personnelVisibilityByUserIdsForTenant')->willReturn([
            7 => ['visibility_level' => VisibilityLevel::NORMAL, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => ''],
            8 => ['visibility_level' => VisibilityLevel::ANONYMIZED, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => 'Opérateur'],
            9 => ['visibility_level' => VisibilityLevel::HIDDEN, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => ''],
            10 => ['visibility_level' => VisibilityLevel::NORMAL, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => ''],
        ]);
        $loader = new JobAssignmentBoardLoader($this->jobRoles($users), $userRepo, $units, null, $this->pdo);

        $reader = $loader->load(14, new OrgVisibilityCapabilities());
        $byName = array_column($reader['members'], null, 'name');
        self::assertArrayNotHasKey('Masqué total', $byName, 'membre masqué : absent, même des compteurs');
        self::assertCount(3, $reader['members']);
        $anon = $byName['Opérateur'];
        self::assertSame([0, '', false, '', ''], [$anon['id'], $anon['callsign'], $anon['selectable'], $anon['grade'], $anon['avatar']]);
        self::assertStringStartsWith('a', $anon['key']);
        self::assertStringNotContainsString('8', $anon['key']);
        self::assertStringContainsString('data-confidential="person_anonymized"', $anon['person_badge']);
        $sof = $byName['Opérateur spécial'];
        self::assertSame([0, []], [$sof['unit_id'], $sof['unit_chain']], 'unité masquée : l’affectation ne sert pas de filtre');
        self::assertStringNotContainsString('Détachement', $sof['unit_label'] . $sof['unit_path']);
        self::assertNotContains(300, array_column($reader['units'], 'id'));
        self::assertSame([100, 110], $byName['Élodie Martin']['unit_chain']);
        $jobs = array_column($reader['jobs'], null, 'id');
        self::assertArrayNotHasKey(4, $jobs, 'emploi d’une autre communauté');
        self::assertSame(['u7', $anon['key']], $jobs[1]['holder_keys']);
        self::assertSame('MED', $jobs[1]['abbreviation']);
        self::assertSame('Santé', $jobs[1]['tags'][0]['name']);
        self::assertSame('Santé / Équipe médicale', $jobs[2]['category']);

        $staff = new OrgVisibilityCapabilities();
        $staff->bypassAll = true;
        JobRoleEnrichmentService::resetCache();
        $full = $loader->load(14, $staff);
        self::assertCount(4, $full['members']);
        $names = array_column($full['members'], null, 'name');
        self::assertSame(8, $names['Agent secret']['id']);
        self::assertSame('Détachement S', $names['Opérateur spécial']['unit_label']);
        self::assertStringContainsString('ath-confid', $names['Opérateur spécial']['unit_badge'], 'lecteur habilité : pastille du niveau');
    }

    public function testBulkAssignRemoveAndReplaceKeepOtherJobsPrimaryAndTenant(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturnCallback(static fn (int $id, ?int $tenant = null): ?array => in_array($id, [7, 8, 9, 10, 11], true) && $tenant === 14 ? ['id' => $id, 'display_name' => 'M' . $id] : null);
        $bulk = new JobAssignmentBulkService($this->jobRoles(), $users);
        $this->pdo->exec("INSERT INTO personnel_profile_job_roles(tenant_id,user_id,personnel_job_role_id,is_primary,sort_order,role_detail) VALUES(14,11,2,1,0,''),(14,11,3,0,1,'')");
        $held = fn (int $uid): array => $this->pdo->query('SELECT personnel_job_role_id, is_primary, COALESCE(role_detail, \'\') FROM personnel_profile_job_roles WHERE tenant_id = 14 AND user_id = ' . $uid . ' ORDER BY sort_order')->fetchAll(PDO::FETCH_NUM);

        $r = $bulk->apply(14, 1, 'attribuer', 2, [7, 8, 9, 11], 0, 2);
        self::assertTrue($r['ok']);
        self::assertSame([1, 2, ['M7']], [$r['changed'], $r['unchanged'], $r['skipped']], 'M7 a déjà deux emplois ; M9 et M11 tiennent déjà l’infirmier');
        self::assertSame([[1, 1, ''], [2, 0, '']], array_map(static fn (array $row): array => [(int) $row[0], (int) $row[1], $row[2]], $held(8)));
        self::assertSame([[1, 1, 'Chef'], [3, 0, '']], array_map(static fn (array $row): array => [(int) $row[0], (int) $row[1], $row[2]], $held(7)), 'précision et principal conservés');

        $r = $bulk->apply(14, 1, 'retirer', 2, [8, 9, 7], 0, 5);
        self::assertSame([2, 1], [$r['changed'], $r['unchanged']]);
        self::assertSame([], $held(9));

        // M11 tenait l’infirmier comme emploi principal : M7 le reprend sans perdre ses deux autres emplois ni son principal.
        $r = $bulk->apply(14, 1, 'remplacer', 2, [7], 11, 5);
        self::assertTrue($r['ok'], $r['message']);
        self::assertSame([[3, 1]], array_map(static fn (array $row): array => [(int) $row[0], (int) $row[1]], $held(11)));
        self::assertSame([[1, 1], [3, 0], [2, 0]], array_map(static fn (array $row): array => [(int) $row[0], (int) $row[1]], $held(7)));
        $r = $bulk->apply(14, 1, 'remplacer', 2, [10], 9, 5);
        self::assertFalse($r['ok'], 'l’ancien titulaire ne tient plus l’emploi');
        // Un nouveau titulaire sans emploi reprend aussi le rôle d’emploi principal.
        $bulk->apply(14, 1, 'remplacer', 2, [9], 7, 5);
        self::assertSame([[2, 1]], array_map(static fn (array $row): array => [(int) $row[0], (int) $row[1]], $held(9)));

        self::assertFalse($bulk->apply(14, 1, 'attribuer', 4, [7], 0, 5)['ok'], 'emploi d’une autre communauté');
        self::assertFalse($bulk->apply(14, 1, 'attribuer', 1, [50], 0, 5)['ok'], 'membre d’une autre communauté');
        self::assertFalse($bulk->apply(14, 1, 'supprimer', 1, [7], 0, 5)['ok']);
        self::assertFalse($bulk->apply(14, 1, 'remplacer', 1, [7, 8], 10, 5)['ok'], 'un seul nouveau titulaire');
    }
}
