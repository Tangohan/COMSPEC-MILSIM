<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Database;
use App\Repositories\GradeRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Community\RealUnitAffiliationCatalog;
use App\Services\Community\TenantBootstrapService;
use App\Services\Community\TenantTypeConfig;
use App\Services\Community\WizardOrbatPresets;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use App\Support\OfficialGradeSystems;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\StrictWriteRecorder;

/**
 * Création complète d'une communauté (TenantBootstrapService::createCommunity), de bout en bout, sur un vrai MySQL 8
 * ou MariaDB 10.11 en mode strict, avec la base installée de zéro par tools/ci/install-test-database.php (schema.sql,
 * étapes historiques et file active).
 *
 * Chaque requête préparée passe par StrictWriteRecorder : une valeur refusée par le mode strict fait échouer le test,
 * même quand le code l'avale (« non bloquant »). Ignoré hors CI : COMSPEC_TEST_E2E=1 et DB_NAME=comspec_e2e… requis.
 */
final class CommunityCreationStrictMysqlTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (getenv('COMSPEC_TEST_E2E') !== '1' || !str_starts_with((string) getenv('DB_NAME'), 'comspec_e2e')) {
            self::markTestSkipped('Base complète de test non configurée (COMSPEC_TEST_E2E=1, DB_NAME=comspec_e2e).');
        }
        $this->pdo = Database::getPdo();
        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [StrictWriteRecorder::class]);
        StrictWriteRecorder::reset();
        $mode = (string) $this->pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        self::assertStringContainsString('STRICT_TRANS_TABLES', $mode);
        self::assertSame('utf8mb4_unicode_ci', (string) $this->pdo->query('SELECT @@SESSION.collation_connection')->fetchColumn());
    }

    /** @return array<string, array{0: string}> */
    public static function communityNames(): array
    {
        return [
            'court' => ['Zulu'],
            '120 caractères' => [self::sized('Task Force Ébène 🦅 ', 120)],
            '255 caractères' => [self::sized('Groupement Commando ', 255)],
            '255 multi-octets' => [self::sized('Отряд «Север» — été ⚔️ 🇫🇷 ', 255)],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('communityNames')]
    public function testFullCommunityWithWizardAndOrbat(string $name): void
    {
        $wizard = $this->wizard('FR_CLASSIC', 'standard', WizardOrbatPresets::unitsFor('minimal'));
        $wizard['custom_roles'] = [
            ['name' => self::sized('Officier de liaison ✈️ ', 80), 'slug' => 'liaison', 'permission_slugs' => ['documents.view']],
        ];
        $wizard['wizard_logo_url'] = 'https://example.org/' . str_repeat('l', 470) . '.png';
        $wizard['wizard_game_label'] = self::sized('Arma 3 ', 200);
        $result = $this->create($name, $wizard);
        $this->assertCommunity($result, $name, TenantTypeConfig::TYPE_FULL, count($wizard['units']));
    }

    public function testRealUnitAffiliation(): void
    {
        $countries = RealUnitAffiliationCatalog::allowedCountryCodes();
        self::assertNotSame([], $countries);
        $units = RealUnitAffiliationCatalog::unitsForCountry($countries[0]);
        self::assertNotSame([], $units, 'référentiel des unités réelles');
        $wizard = $this->wizard('FR_CLASSIC', 'standard', WizardOrbatPresets::unitsFor('minimal'));
        $wizard['wizard_represents_real_unit'] = '1';
        $wizard['wizard_real_unit_country'] = $countries[0];
        $wizard['wizard_real_unit_ids'] = array_slice(array_column($units, 'id'), 0, 3);
        $name = 'Unité réelle ' . $countries[0];
        $this->assertCommunity($this->create($name, $wizard), $name, TenantTypeConfig::TYPE_FULL, 3);
    }

    public function testEveryGradeSystemAndRolesTemplate(): void
    {
        foreach (OfficialGradeSystems::CODES as $i => $code) {
            $template = $i % 2 === 0 ? 'quick' : 'standard';
            $name = 'Grades ' . $code . ' ' . $template . ' — ' . self::sized('Ж', 40);
            $result = $this->create($name, $this->wizard($code, $template, WizardOrbatPresets::unitsFor('minimal')));
            $this->assertCommunity($result, $name, TenantTypeConfig::TYPE_FULL, 3);
            $st = $this->pdo->prepare('SELECT settings FROM tenants WHERE id = ?');
            $st->execute([$result['tenant_id']]);
            self::assertStringContainsString($code, (string) $st->fetchColumn(), $code);
        }
    }

    public function testEveryOrbatPreset(): void
    {
        foreach (WizardOrbatPresets::all() as $preset) {
            $name = 'ORBAT ' . $preset['label'];
            $result = $this->create($name, $this->wizard('US_CLASSIC', 'quick', $preset['units']));
            $this->assertCommunity($result, $name, TenantTypeConfig::TYPE_FULL, count($preset['units']));
        }
    }

    public function testEveryOrganizationKit(): void
    {
        foreach (OrganizationKitDefinitions::officialCodes() as $kit) {
            $wizard = $this->wizard('FR_CLASSIC', 'quick', WizardOrbatPresets::unitsFor('minimal'));
            $wizard['catalog_kit_code'] = $kit;
            $name = 'Modèle ' . $kit . ' ' . self::sized('🎖️', 20);
            $result = $this->create($name, $wizard);
            $this->assertCommunity($result, $name, TenantTypeConfig::TYPE_FULL, null);
        }
    }

    public function testLongestWizardUnitNamesAndRejectedLongerOnes(): void
    {
        $units = [
            ['key' => 'root', 'parent_key' => '', 'name' => self::sized('État-major interarmées 🛡️ Ωmega ', 120), 'slug' => 'em', 'type' => 'group', 'display_order' => 0],
            ['key' => 'a', 'parent_key' => 'root', 'name' => self::sized('Section d’appui feux — Огневая ', 120), 'slug' => 'appui', 'type' => 'section', 'display_order' => 1],
            ['key' => 'b', 'parent_key' => 'a', 'name' => self::sized('Équipe 🚁 ', 120), 'slug' => str_repeat('b', 50), 'type' => 'team', 'display_order' => 2],
            ['key' => 'c', 'parent_key' => 'a', 'name' => 'C', 'slug' => 'c', 'type' => 'squad', 'display_order' => 3],
        ];
        $name = self::sized('Unités longues ', 255);
        $result = $this->create($name, $this->wizard('GB_ARMY', 'standard', $units));
        $this->assertCommunity($result, $name, TenantTypeConfig::TYPE_FULL, 4);
        $st = $this->pdo->prepare('SELECT name FROM units WHERE tenant_id = ? ORDER BY id');
        $st->execute([$result['tenant_id']]);
        self::assertSame(array_column($units, 'name'), $st->fetchAll(PDO::FETCH_COLUMN));

        $units[0]['name'] = self::sized('Trop long ', 121);
        $v = (new CommunityOnboardingValidationService())->validate($this->wizard('FR_CLASSIC', 'quick', $units));
        self::assertFalse($v['ok']);
        self::assertStringContainsString('120', implode(' ', $v['errors']));
    }

    public function testSimplifiedTypesWithAndWithoutWizard(): void
    {
        foreach ([TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_ATAK, TenantTypeConfig::TYPE_FULL] as $type) {
            $name = 'Sans assistant ' . $type . ' ' . self::sized('é', 30);
            $result = $this->create($name, null, $type);
            $this->assertCommunity($result, $name, $type, 0);

            $name = self::sized('Avec assistant ' . $type . ' 🧭 ', 255);
            $result = $this->create($name, $this->wizard('NATO_GENERIC', 'quick', WizardOrbatPresets::unitsFor('minimal')), $type);
            $this->assertCommunity($result, $name, $type, 3);
        }
    }

    public function testSameNameTwiceGetsDistinctSlugsAndFailureRollsBack(): void
    {
        $name = 'Rejouable ' . self::sized('ü', 20);
        $first = $this->create($name, $this->wizard('FR_CLASSIC', 'quick', WizardOrbatPresets::unitsFor('minimal')));
        $second = $this->create($name, $this->wizard('FR_CLASSIC', 'quick', WizardOrbatPresets::unitsFor('minimal')));
        self::assertNotSame($first['tenant_id'], $second['tenant_id']);
        $this->assertCommunity($second, $name, TenantTypeConfig::TYPE_FULL, 3);
        $slugs = $this->pdo->prepare('SELECT slug FROM tenants WHERE id IN (?, ?)');
        $slugs->execute([$first['tenant_id'], $second['tenant_id']]);
        self::assertCount(2, array_unique($slugs->fetchAll(PDO::FETCH_COLUMN)));

        $before = (int) $this->pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $wizard = $this->wizard('FR_CLASSIC', 'quick', WizardOrbatPresets::unitsFor('minimal'));
        $wizard['founder_grade_id'] = 99999999;
        try {
            $this->service()->createCommunity($this->creator(), 'Échec attendu', '', ['wizard_normalized' => $wizard]);
            self::fail('Un grade de fondateur inconnu doit être refusé.');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame($before, (int) $this->pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn(), 'transaction annulée');
        $this->assertNoStrictErrors();
    }

    // ------------------------------------------------------------------ outils

    private static function sized(string $seed, int $length): string
    {
        return mb_substr(str_repeat($seed, (int) ceil($length / max(1, mb_strlen($seed, 'UTF-8'))) + 1), 0, $length, 'UTF-8');
    }

    private function service(): TenantBootstrapService
    {
        return new TenantBootstrapService(new TenantRepository(), new UserRepository(), new ReferralRepository());
    }

    /** Compte « plateforme » du fondateur, dans une communauté d'accueil. */
    private function creator(): int
    {
        static $n = 0;
        $n++;
        $hub = $this->pdo->query("SELECT id FROM tenants WHERE slug = 'accueil-e2e'")->fetchColumn();
        if ($hub === false) {
            $this->pdo->prepare("INSERT INTO tenants (name, slug, created_at, updated_at) VALUES ('Accueil', 'accueil-e2e', NOW(), NOW())")->execute();
            $hub = $this->pdo->lastInsertId();
        }
        $email = 'fondateur' . $n . '-' . bin2hex(random_bytes(4)) . '@example.org';
        $this->pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, display_name, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([(int) $hub, $email, password_hash('x', PASSWORD_BCRYPT), 'Fondateur Ünïcode ' . $n, 'active']);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param list<array<string, mixed>> $units */
    private function wizard(string $gradeSystem, string $rolesTemplate, array $units): array
    {
        $grades = (new GradeRepository())->listBySystemCode($gradeSystem);
        self::assertNotSame([], $grades, 'grades du référentiel ' . $gradeSystem);

        return [
            'grade_system_code' => $gradeSystem,
            'timezone' => 'Europe/Paris',
            'default_locale' => 'fr',
            'orbat_visibility' => 'members',
            'founder_grade_id' => (int) $grades[0]['id'],
            'roles_template' => $rolesTemplate,
            'units' => $units,
            'grade_overrides' => [],
            'custom_roles' => [],
            'wizard_represents_real_unit' => '0',
            'wizard_fictional_unit_label' => self::sized('Bataillon fictif Ωmega 🪖 ', 200),
        ];
    }

    /** @return array{tenant_id: int, user_id: int} */
    private function create(string $name, ?array $wizardRaw, string $type = TenantTypeConfig::TYPE_FULL): array
    {
        $options = ['tenant_type' => $type, 'plan_slug' => 'free'];
        if ($wizardRaw !== null) {
            $v = (new CommunityOnboardingValidationService())->validate($wizardRaw);
            self::assertTrue($v['ok'], implode(' ', $v['errors'] ?? []));
            $options['wizard_normalized'] = $v['normalized'];
        }
        $creator = $this->creator();
        StrictWriteRecorder::reset();
        $this->pdo->exec('TRUNCATE TABLE mysql.general_log');
        try {
            $result = $this->service()->createCommunity($creator, $name, '', $options);
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage() . "\nDDL exécutées pendant la création :\n" . implode("\n", $this->ddlStatements()), 0, $e);
        }
        $this->assertNoStrictErrors($name);
        $this->assertNoDdlInsideTransaction($name);

        return $result;
    }

    /**
     * Instructions DDL vues par le serveur pendant la création (journal général activé par tools/ci/install-test-database.php).
     * Une DDL valide implicitement la transaction MySQL : la suite de createCommunity n'est alors plus annulable.
     *
     * @return list<string>
     */
    private function ddlStatements(): array
    {
        $rows = $this->pdo->query(
            "SELECT CAST(argument AS CHAR) FROM mysql.general_log WHERE command_type IN ('Query', 'Execute')"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_unique(array_map(
            static fn (string $q): string => mb_substr(preg_replace('/\s+/', ' ', trim($q)) ?? '', 0, 200),
            array_filter($rows, static fn ($q): bool => is_string($q) && preg_match('/^\s*(CREATE|ALTER|DROP|RENAME|TRUNCATE)\b/i', $q) === 1 && !str_contains($q, 'general_log'))
        )));
    }

    /** Aucune DDL entre START TRANSACTION et COMMIT sur la connexion de l'application (validation implicite). */
    private function assertNoDdlInsideTransaction(string $context): void
    {
        $st = $this->pdo->prepare("SELECT CAST(argument AS CHAR) FROM mysql.general_log WHERE thread_id = ? AND command_type IN ('Query', 'Execute')");
        $st->execute([(int) $this->pdo->query('SELECT CONNECTION_ID()')->fetchColumn()]);
        $inside = false;
        $seenBegin = false;
        $ddl = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $q) {
            $q = trim((string) $q);
            if (preg_match('/^(START TRANSACTION|BEGIN)\b/i', $q) === 1) {
                $inside = $seenBegin = true;
            } elseif (preg_match('/^(COMMIT|ROLLBACK)\b/i', $q) === 1) {
                $inside = false;
            } elseif ($inside && preg_match('/^(CREATE|ALTER|DROP|RENAME|TRUNCATE)\b/i', $q) === 1) {
                $ddl[] = mb_substr((string) preg_replace('/\s+/', ' ', $q), 0, 200);
            }
        }
        self::assertTrue($seenBegin, 'START TRANSACTION absent du journal général (' . $context . ')');
        self::assertSame([], $ddl, 'DDL pendant la transaction de création (' . $context . ')');
    }

    private function assertNoStrictErrors(string $context = ''): void
    {
        $schema = StrictWriteRecorder::schemaErrors();
        if ($schema !== []) {
            fwrite(STDERR, "\n[schéma] " . $context . "\n" . StrictWriteRecorder::describe($schema) . "\n");
        }
        $errors = StrictWriteRecorder::dataErrors();
        self::assertSame([], $errors, "Valeurs refusées par MySQL strict (" . $context . ") :\n" . StrictWriteRecorder::describe($errors));
    }

    /** @param array{tenant_id: int, user_id: int} $result */
    private function assertCommunity(array $result, string $name, string $type, ?int $units): void
    {
        $tenantId = $result['tenant_id'];
        $t = $this->pdo->prepare('SELECT * FROM tenants WHERE id = ?');
        $t->execute([$tenantId]);
        $tenant = $t->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($tenant);
        self::assertSame($name, $tenant['name'], 'nom enregistré sans perte');
        self::assertSame($type, $tenant['tenant_type']);
        if (array_key_exists('owner_user_id', $tenant)) {
            self::assertSame($result['user_id'], (int) $tenant['owner_user_id']);
        }
        $u = $this->pdo->prepare('SELECT u.tenant_id, r.slug FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
        $u->execute([$result['user_id']]);
        $founder = $u->fetch(PDO::FETCH_ASSOC);
        self::assertSame($tenantId, (int) $founder['tenant_id']);
        self::assertSame('community_owner', $founder['slug'], 'fondateur propriétaire');

        foreach (['roles', 'permissions'] as $table) {
            self::assertGreaterThan(0, $this->countFor($table, $tenantId), $table);
        }
        $rp = $this->pdo->prepare('SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.tenant_id = ?');
        $rp->execute([$tenantId]);
        self::assertGreaterThan(0, (int) $rp->fetchColumn(), 'role_permissions');
        if ($type === TenantTypeConfig::TYPE_FULL) {
            self::assertGreaterThan(0, $this->countFor('personnel_admin_panels', $tenantId), 'panneaux');
            self::assertGreaterThan(0, $this->countFor('personnel_job_roles', $tenantId), 'fonctions');
            self::assertGreaterThan(0, $this->countFor('document_categories', $tenantId), 'documents');
        }
        if ($units !== null) {
            self::assertSame($units, $this->countFor('units', $tenantId), 'unités');
        }
    }

    private function countFor(string $table, int $tenantId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }
}
