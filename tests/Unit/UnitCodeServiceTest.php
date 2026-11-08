<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UnitCodeRepository;
use App\Services\Effectifs\UnitCodeService;
use PDO;
use PHPUnit\Framework\TestCase;

final class UnitCodeServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        UnitCodeService::forget();
        UnitCodeRepository::resetCache();
        $this->pdo = self::schema();
    }

    protected function tearDown(): void
    {
        UnitCodeService::forget();
        UnitCodeRepository::resetCache();
    }

    /** Schéma minimal équivalent (SQLite) à migrations/2026_11_01_002_unit_codes.sql. */
    public static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT);
            CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, parent_id INTEGER NULL, name TEXT NOT NULL,
                slug TEXT, type TEXT, code TEXT NULL, display_order INTEGER DEFAULT 0, short_label TEXT NULL);
            CREATE TABLE tenant_unit_codes (tenant_id INTEGER PRIMARY KEY, community_code TEXT NOT NULL UNIQUE,
                admin_number INTEGER NOT NULL UNIQUE, next_unit_number INTEGER NOT NULL DEFAULT 1, is_custom INTEGER NOT NULL DEFAULT 0, updated_at TEXT);
            CREATE TABLE unit_codes (unit_id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, segment TEXT NOT NULL DEFAULT '',
                code_path TEXT NOT NULL, admin_number INTEGER NOT NULL, updated_at TEXT,
                UNIQUE (tenant_id, code_path), UNIQUE (tenant_id, admin_number));
            INSERT INTO tenants VALUES (14, 'SOAR - The Special Operations Action Regiments', 'soar'), (15, 'Task Force Ranger', 'tfr');
            INSERT INTO units (id, tenant_id, parent_id, name) VALUES
                (1, 14, NULL, 'Escadron B'), (2, 14, 1, 'Troop A'), (3, 14, 2, 'Groupe 1'), (4, 14, NULL, 'État-major'),
                (20, 15, NULL, 'Escadron B')");

        return $pdo;
    }

    private function service(): UnitCodeService
    {
        return new UnitCodeService(new UnitCodeRepository($this->pdo));
    }

    public function testDisplayPrefixPreservesAdministrativeCodesAndOtherTenantSettings(): void
    {
        $this->pdo->exec("ALTER TABLE tenants ADD COLUMN settings TEXT");
        $this->pdo->exec('UPDATE tenants SET settings = \'{"other":{"enabled":true}}\' WHERE id = 14');
        $service = $this->service();
        $before = $service->sync(14);
        self::assertNull($service->changeDisplayPrefix(14, 'JTF'));
        $after = $service->sync(14);
        self::assertSame('JTF-B-A-1', $after['units'][3]['code']);
        self::assertSame('SOAR-B-A-1', $after['units'][3]['administrative_code']);
        self::assertSame($before['units'][3]['admin_id'], $after['units'][3]['admin_id']);
        self::assertSame('SOAR-B-A-1', $this->pdo->query('SELECT code_path FROM unit_codes WHERE unit_id = 3')->fetchColumn());
        $settings = json_decode($this->pdo->query('SELECT settings FROM tenants WHERE id = 14')->fetchColumn(), true);
        self::assertTrue($settings['other']['enabled']);
        self::assertNotNull($service->changeDisplayPrefix(14, 'bad-prefix'));
        self::assertNull($service->changeDisplayPrefix(14, ''));
        self::assertSame($before['units'][3]['code'], $service->sync(14)['units'][3]['code']);
    }

    public function testSyncAssignsReadableCodesAndStableAdminIdentifiers(): void
    {
        $codes = $this->service()->sync(14);

        self::assertSame('SOAR', $codes['community']['code']);
        self::assertSame('SOAR-B-A-1', $codes['units'][3]['code']);
        self::assertSame('SOAR-EM', $codes['units'][4]['code']);
        // Numéro de communauté sur 4 chiffres + numéro de l'unité dans la communauté.
        self::assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $codes['units'][3]['admin_id']);
        self::assertSame(['0001-0001', '0001-0002', '0001-0003', '0001-0004'], array_column($codes['units'], 'admin_id'));
        $paths = array_column($codes['units'], 'code');
        self::assertSame($paths, array_unique($paths));
    }

    public function testAdminIdentifiersAreUniqueAcrossCommunities(): void
    {
        $a = $this->service()->sync(14);
        $b = $this->service()->sync(15);

        self::assertNotSame($a['community']['admin_number'], $b['community']['admin_number']);
        self::assertNotSame($a['community']['code'], $b['community']['code']);
        self::assertNotSame($a['units'][1]['admin_id'], $b['units'][20]['admin_id']);
    }

    public function testMovingOrRenamingAUnitRecomputesTheCodeButKeepsItsIdentifier(): void
    {
        $before = $this->service()->sync(14)['units'][3];
        $this->pdo->exec("UPDATE units SET parent_id = 4, name = 'Groupe 7' WHERE id = 3");
        UnitCodeService::forget(14);
        $after = $this->service()->sync(14)['units'][3];

        self::assertSame('SOAR-B-A-1', $before['code']);
        self::assertSame('SOAR-EM-7', $after['code']);
        self::assertSame($before['admin_id'], $after['admin_id']);
        self::assertSame('SOAR-EM-7', $this->pdo->query('SELECT code_path FROM unit_codes WHERE unit_id = 3')->fetchColumn());
    }

    public function testSwappingTwoCodesDoesNotBreakUniqueness(): void
    {
        $this->pdo->exec("INSERT INTO units (id, tenant_id, parent_id, name) VALUES (5, 14, 2, 'Groupe 2')");
        $this->service()->sync(14);
        $this->pdo->exec("UPDATE units SET name = 'Groupe 2' WHERE id = 3; UPDATE units SET name = 'Groupe 1' WHERE id = 5");
        UnitCodeService::forget(14);
        $codes = $this->service()->sync(14)['units'];

        self::assertSame('SOAR-B-A-2', $codes[3]['code']);
        self::assertSame('SOAR-B-A-1', $codes[5]['code']);
    }

    public function testDeletedUnitNumbersAreNeverReused(): void
    {
        $first = $this->service()->sync(14)['units'];
        $this->pdo->exec('DELETE FROM units WHERE id = 4');
        UnitCodeService::forget(14);
        $this->service()->sync(14);
        $this->pdo->exec("INSERT INTO units (id, tenant_id, parent_id, name) VALUES (6, 14, NULL, 'Logistique')");
        UnitCodeService::forget(14);
        $codes = $this->service()->sync(14)['units'];

        self::assertArrayNotHasKey(4, $codes);
        self::assertNotSame($first[4]['admin_id'], $codes[6]['admin_id']);
        self::assertSame('0001-0005', $codes[6]['admin_id']);
    }

    public function testCommunityCodeChangeIsValidatedAndPropagated(): void
    {
        $service = $this->service();
        $service->sync(14);
        $service->sync(15);

        self::assertNotNull($service->changeCommunityCode(14, 'S O'));
        self::assertSame('Ce code est déjà utilisé par une autre communauté.', $service->changeCommunityCode(14, 'tfr'));
        self::assertNull($service->changeCommunityCode(14, 'soar160'));
        self::assertSame('SOAR160-B-A-1', $service->sync(14)['units'][3]['code']);
    }

    public function testSegmentValidationRejectsADuplicateAmongSiblings(): void
    {
        $units = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Alpha', 'code' => 'A'],
            ['id' => 2, 'parent_id' => null, 'name' => 'Bravo', 'code' => null],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Alpha 1', 'code' => null],
        ];
        $out = null;

        self::assertNotNull(UnitCodeService::validateSegment($units, 2, 'a', $out));
        self::assertNull(UnitCodeService::validateSegment($units, 3, 'a', $out));
        self::assertSame('A', $out);
        self::assertNotNull(UnitCodeService::validateSegment($units, 2, 'trop-long!', $out));
        self::assertNull(UnitCodeService::validateSegment($units, 2, '', $out));
    }
}
