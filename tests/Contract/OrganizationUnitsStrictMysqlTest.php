<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Container;
use App\Core\Database;
use App\Repositories\GradeRepository;
use App\Repositories\OrganizationCatalogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Admin\TenantRolePermissionPresetService;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Community\TenantBootstrapService;
use App\Services\Community\WizardOrbatPresets;
use App\Services\Organization\OrbatBilletService;
use App\Services\OrganizationCatalog\OrganizationCatalogService;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use App\Services\Personnel\JobRoleEnrichmentService;
use App\Services\Personnel\UnitJobRoleSyncService;
use App\Services\Rbac\AccessRoleProfileService;
use App\Services\Sse\SseFieldNoteTagService;
use App\Support\JobRoleIconLibrary;
use App\Support\SseFieldNoteTagCatalog;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\StrictWriteRecorder;

/**
 * Structure d'une organisation après sa création, sur un vrai MySQL 8 ou MariaDB 10.11 en mode strict (base installée par
 * tools/ci/install-test-database.php) : unités (création, renommage, déplacement, suppression), fonctions dérivées,
 * emplois et catégories, étiquettes, niveaux d'accès enrichis, modèles d'organisation, postes clés, étiquettes SSE.
 *
 * Valeurs courtes, de 120 et 255 caractères, multi-octets : chaque requête passe par StrictWriteRecorder et toute valeur
 * refusée par le mode strict fait échouer le test, même avalée par le code. Ignoré hors CI (COMSPEC_TEST_E2E=1).
 */
final class OrganizationUnitsStrictMysqlTest extends TestCase
{
    private PDO $pdo;

    private int $tenantId;

    private int $userId;

    protected function setUp(): void
    {
        if (getenv('COMSPEC_TEST_E2E') !== '1' || !str_starts_with((string) getenv('DB_NAME'), 'comspec_e2e')) {
            self::markTestSkipped('Base complète de test non configurée (COMSPEC_TEST_E2E=1, DB_NAME=comspec_e2e).');
        }
        $this->pdo = Database::getPdo();
        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [StrictWriteRecorder::class]);
        $result = $this->createCommunity(self::sized('Organisation 🛰️ Сектор ', 255));
        $this->tenantId = $result['tenant_id'];
        $this->userId = $result['user_id'];
        StrictWriteRecorder::reset();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $errors = StrictWriteRecorder::dataErrors();
            StrictWriteRecorder::reset();
            self::assertSame([], $errors, "Valeurs refusées par MySQL strict :\n" . StrictWriteRecorder::describe($errors));
        }
    }

    /** @return array<string, array{0: string}> */
    public static function unitNames(): array
    {
        return [
            'court' => ['A'],
            '120 caractères' => [self::sized('Section de reconnaissance ', 120)],
            '255 caractères ASCII' => [self::sized('Detachment Alpha ', 255)],
            '255 multi-octets' => [self::sized('Взвод «Север» — équipe 🚁 ', 255)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unitNames')]
    public function testUnitCreateRenameMoveDelete(string $name): void
    {
        $units = new UnitRepository();
        $root = $units->create($this->tenantId, ['name' => mb_substr('Racine ' . $name, 0, 255, 'UTF-8'), 'type' => 'group']);
        $child = $units->create($this->tenantId, [
            'name' => $name,
            'slug' => $units->uniqueSlugForTenant($this->tenantId, $name),
            'type' => 'section',
            'code' => str_repeat('Z', 20),
            'parent_id' => (int) $root['id'],
        ]);
        $twin = $units->create($this->tenantId, ['name' => $name, 'slug' => $units->uniqueSlugForTenant($this->tenantId, $name), 'type' => 'team']);
        foreach ([$root, $child, $twin] as $u) {
            self::assertLessThanOrEqual(100, mb_strlen((string) $this->unit((int) $u['id'])['slug']));
        }
        self::assertSame($name, $this->unit((int) $child['id'])['name']);

        // Fonction du même nom (personnel_job_roles.name VARCHAR(120)).
        $job = $this->pdo->prepare('SELECT name, slug FROM personnel_job_roles WHERE tenant_id = ? AND source_unit_id = ?');
        $job->execute([$this->tenantId, (int) $child['id']]);
        $jobRow = $job->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($jobRow, 'fonction dérivée de l’unité');
        self::assertSame(rtrim(mb_substr($name, 0, 120, 'UTF-8')), $jobRow['name']);

        $renamed = self::sized('Renommée ✦ Équipe ', 255);
        $units->update((int) $child['id'], $this->tenantId, [
            'name' => $renamed,
            'parent_id' => (int) $twin['id'],
            'motto' => self::sized('Toujours prêts ⚓ ', 255),
            'short_label' => self::sized('ÉQ-Ω ', 40),
            'org_callsign' => self::sized('Сокол ', 80),
            'code' => self::sized('C1', 20),
        ]);
        $row = $this->unit((int) $child['id']);
        self::assertSame($renamed, $row['name']);
        self::assertSame((int) $twin['id'], (int) $row['parent_id']);

        try {
            $units->update((int) $child['id'], $this->tenantId, ['code' => str_repeat('X', 21)]);
            self::fail('Un code de 21 caractères doit être refusé.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Le code de l’unité ne peut pas dépasser 20 caractères.', $e->getMessage());
        }
        try {
            $units->create($this->tenantId, ['name' => str_repeat('n', 256)]);
            self::fail('Un nom de 256 caractères doit être refusé.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Le nom de l’unité ne peut pas dépasser 255 caractères.', $e->getMessage());
        }
        try {
            $units->update((int) $child['id'], $this->tenantId, ['name' => null]);
            self::fail('Un nom vide doit être refusé.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Le nom de l’unité est requis.', $e->getMessage());
        }

        // Postes clés de l'unité (ORBAT) et suppression.
        $billets = Container::get(OrbatBilletService::class);
        self::assertInstanceOf(OrbatBilletService::class, $billets);
        $billets->ensureKeyPosts($this->tenantId, (int) $child['id'], $this->userId);
        self::assertTrue($units->delete((int) $child['id'], $this->tenantId));
        self::assertNull($this->unit((int) $child['id']));
    }

    public function testJobRolesCategoriesAndTags(): void
    {
        $jobs = new PersonnelJobRoleRepository();
        $catName = self::sized('Catégorie œuvre ßtraße ', 120);
        $catId = $jobs->createCategory($this->tenantId, null, $catName, $jobs->uniqueCategorySlugFromName($this->tenantId, $catName));
        $name = self::sized('Chef d’œuvre Æther — ', 120);
        $roleId = $jobs->createRole($this->tenantId, $catId, $name, $jobs->uniqueSlugFromName($this->tenantId, $name), self::sized('Описание ', 500), 0);
        $again = $jobs->createRole($this->tenantId, $catId, $name, $jobs->uniqueSlugFromName($this->tenantId, $name), null, 1);
        foreach ([$roleId, $again] as $id) {
            $row = $jobs->findRoleById($id, $this->tenantId);
            self::assertIsArray($row);
            self::assertLessThanOrEqual(80, strlen((string) $row['slug']));
        }
        self::assertNull(PersonnelJobRoleRepository::fieldLengthError($name, 'slug', self::sized('é', 500)));
        self::assertSame('Le nom ne peut pas dépasser 120 caractères.', PersonnelJobRoleRepository::fieldLengthError($name . 'x', 'slug'));

        // Poste nommé à l'affectation ou à l'enrôlement : libellé sans borne.
        $imported = $jobs->findOrCreateImportedRoleByLabel($this->tenantId, self::sized('Opérateur radio longue portée ', 300));
        self::assertNotNull($imported);
        $row = $jobs->findRoleById((int) $imported, $this->tenantId);
        self::assertLessThanOrEqual(120, mb_strlen((string) $row['name']));
        self::assertGreaterThanOrEqual(119, mb_strlen((string) $row['name']));
        self::assertLessThanOrEqual(80, strlen((string) $row['slug']));

        $enrich = new JobRoleEnrichmentService($this->pdo);
        $icon = (string) array_key_first(JobRoleIconLibrary::all());
        $tags = ['Médical', 'medical', self::sized('Тактика ', 40), self::sized('é', 40)];
        $data = $enrich->validate($this->tenantId, $roleId, ['abbreviation' => 'CHFOE', 'icon' => $icon, 'tags' => $tags]);
        $enrich->save($this->tenantId, $roleId, $data);
        // Même étiquette avec une autre casse ou sans accent, sur une autre fonction : réutilisée, pas de doublon.
        $data = $enrich->validate($this->tenantId, $again, ['abbreviation' => 'CHF2', 'icon' => $icon, 'tags' => ['MEDICAL', 'Médical']]);
        $enrich->save($this->tenantId, $again, $data);
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM personnel_job_role_tags WHERE tenant_id = ?');
        $st->execute([$this->tenantId]);
        self::assertSame(3, (int) $st->fetchColumn());
    }

    public function testAccessRoleProfiles(): void
    {
        $svc = new AccessRoleProfileService($this->pdo);
        self::assertTrue($svc->ready(), 'étape access_role_profiles (4870)');
        $st = $this->pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? ORDER BY id');
        $st->execute([$this->tenantId]);
        $roleIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        self::assertNotSame([], $roleIds);
        $icon = (string) array_key_first(JobRoleIconLibrary::all());
        foreach (array_slice($roleIds, 0, 2) as $i => $roleId) {
            $data = $svc->validate($this->tenantId, $roleId, [
                'abbreviation' => 'NIV' . $i,
                'details' => self::sized('Niveau d’accès détaillé — Уровень 🔐 ', 4000),
                'icon' => $icon,
                'tags' => ['Médical', $i === 0 ? 'medical' : 'MÉDICAL', self::sized('Ω', 40)],
                'sort_order' => 999,
            ]);
            self::assertTrue($svc->save($this->tenantId, $roleId, $data, $this->userId));
        }
    }

    public function testOrganizationKitsApplyAndSnapshot(): void
    {
        $catalog = new OrganizationCatalogService(
            new OrganizationCatalogRepository(),
            new UnitRepository(),
            new PersonnelJobRoleRepository(),
            new RoleRepository(),
            new PermissionRepository(),
            Container::get(TenantRolePermissionPresetService::class),
            new TenantRepository(),
            null,
            null,
            null,
            static fn (): ?int => null,
        );
        foreach (OrganizationKitDefinitions::officialCodes() as $code) {
            $tenantId = $this->createCommunity('Kit ' . $code . ' ' . self::sized('Ж', 20))['tenant_id'];
            $result = $catalog->apply($tenantId, $code, [], $this->userId);
            self::assertTrue($result['ok'], $code . ' : ' . ($result['error'] ?? ''));
            self::assertGreaterThan(0, $this->rowsFor('units', $tenantId), $code);
        }
        // Le titre par défaut « Modèle de <nom> » dépasse 180 caractères avec un nom de 255.
        $snap = $catalog->snapshot($this->tenantId, $this->userId);
        self::assertTrue($snap['ok'], (string) ($snap['error'] ?? ''));
        $refused = $catalog->snapshot($this->tenantId, $this->userId, str_repeat('t', 181));
        self::assertFalse($refused['ok']);
        self::assertSame('Le nom du modèle ne peut pas dépasser 180 caractères.', $refused['error']);
    }

    public function testDerivedJobRoleIsCutTo120(): void
    {
        $units = new UnitRepository();
        $unit = $units->create($this->tenantId, ['name' => self::sized('Groupement 🛡️ ', 255), 'type' => 'group']);
        $id = (new UnitJobRoleSyncService($this->pdo))->ensureForUnit($this->tenantId, (int) $unit['id'], self::sized('Groupement 🛡️ ', 255));
        self::assertNotNull($id);
    }

    public function testSseTagLabels(): void
    {
        $svc = new SseFieldNoteTagService();
        if (!$svc->ready()) {
            self::fail('Étape sse_tags (4850) absente de la base de test.');
        }
        $result = $svc->saveTag($this->tenantId, [
            'code' => 'ZX-' . str_repeat('9', SseFieldNoteTagCatalog::CODE_MAX - 3),
            'family' => 'theme',
            'label_fr' => self::sized('Étiquette très détaillée — Тег 🏷️ ', 80),
            'label_en' => self::sized('Detailed label ', 80),
            'desc_fr' => self::sized('Description longue é ', 200),
            'color' => (string) array_key_first(SseFieldNoteTagCatalog::COLORS),
            'sort' => 10,
        ], ['user_id' => $this->userId, 'label' => 'Test']);
        self::assertTrue($result['ok'], implode(' ', $result['errors']));
    }

    // ------------------------------------------------------------------ outils

    private static function sized(string $seed, int $length): string
    {
        return mb_substr(str_repeat($seed, (int) ceil($length / max(1, mb_strlen($seed, 'UTF-8'))) + 1), 0, $length, 'UTF-8');
    }

    /** @return array<string, mixed>|null */
    private function unit(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM units WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function rowsFor(string $table, int $tenantId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /** @return array{tenant_id: int, user_id: int} */
    private function createCommunity(string $name): array
    {
        $hub = $this->pdo->query("SELECT id FROM tenants WHERE slug = 'accueil-e2e'")->fetchColumn();
        if ($hub === false) {
            $this->pdo->prepare("INSERT INTO tenants (name, slug, created_at, updated_at) VALUES ('Accueil', 'accueil-e2e', NOW(), NOW())")->execute();
            $hub = $this->pdo->lastInsertId();
        }
        $this->pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, display_name, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([(int) $hub, 'orga-' . bin2hex(random_bytes(6)) . '@example.org', password_hash('x', PASSWORD_BCRYPT), 'Fondatrice Ünïcode', 'active']);
        $creator = (int) $this->pdo->lastInsertId();
        $grades = (new GradeRepository())->listBySystemCode('FR_CLASSIC');
        $v = (new CommunityOnboardingValidationService())->validate([
            'grade_system_code' => 'FR_CLASSIC',
            'timezone' => 'Europe/Paris',
            'default_locale' => 'fr',
            'orbat_visibility' => 'members',
            'founder_grade_id' => (int) ($grades[0]['id'] ?? 0),
            'roles_template' => 'standard',
            'units' => WizardOrbatPresets::unitsFor('company_fr'),
            'custom_roles' => [],
            'wizard_represents_real_unit' => '0',
            'wizard_fictional_unit_label' => 'Unité de test',
        ]);
        self::assertTrue($v['ok'], implode(' ', $v['errors'] ?? []));

        return (new TenantBootstrapService(new TenantRepository(), new UserRepository(), new ReferralRepository()))
            ->createCommunity($creator, $name, '', ['wizard_normalized' => $v['normalized']]);
    }
}
