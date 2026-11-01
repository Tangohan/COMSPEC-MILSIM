<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\OrbatApiController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\OrbatChartTypeRepository;
use App\Repositories\OrganizationVisibilityHistoryRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * API de l’ORBAT : symbole MIL-STD-2525 (sidc) et échelon validés côté serveur, droits de gestion exigés.
 */
final class OrbatApiMilitarySymbolTest extends TestCase
{
    private const TENANT = 7;

    private UnitRepository&MockObject $units;

    /** @var list<array<string, mixed>> */
    private array $updates = [];

    private bool $savedStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array<string, mixed> */
    private array $savedPost = [];

    /** @var list<string> */
    private array $columns = ['sidc', 'echelon'];

    protected function setUp(): void
    {
        parent::setUp();
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = ['tenant_id' => self::TENANT, 'user_id' => 3];
        $this->savedPost = $_POST;
        Gate::getInstance()->setPermissions(['organization.orbat.manage', 'organization.orbat.view']);

        $this->units = $this->createMock(UnitRepository::class);
        $this->units->method('findById')->willReturnCallback(
            static fn (int $id, int $tenant): ?array => $id === 12 && $tenant === self::TENANT ? ['id' => 12, 'name' => 'Alpha', 'type' => 'team'] : null
        );
        $this->units->method('hasTableColumn')->willReturnCallback(fn (string $t, string $c): bool => in_array($c, $this->columns, true));
        $this->units->method('getTree')->willReturn([]);
        $this->units->method('update')->willReturnCallback(function (int $id, int $tenant, array $data): bool {
            $this->updates[] = $data;

            return true;
        });
    }

    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        $_POST = $this->savedPost;
        parent::tearDown();
    }

    private function controller(): OrbatApiController
    {
        $history = $this->createMock(PersonnelOrgHistoryRepository::class);
        $history->method('schemaReady')->willReturn(false);

        return new OrbatApiController(
            $this->units,
            $this->createMock(UserRepository::class),
            $this->createMock(OrbatChartTypeRepository::class),
            $history,
            $this->createMock(OrganizationVisibilityHistoryRepository::class),
        );
    }

    /** @param array<string, mixed> $fields @return array{0: int, 1: array<string, mixed>} */
    private function patch(array $fields): array
    {
        $_POST = ['_csrf_token' => Csrf::token(), 'unit_id' => '12'] + $fields;
        $res = $this->controller()->updateUnit(new Request());

        return [$res->statusCode(), (array) json_decode($res->body(), true)];
    }

    public function testValidSymbolAndEchelonAreStored(): void
    {
        [$status, $json] = $this->patch(['sidc' => 'sfgpuca--------', 'echelon' => 'peloton']);

        self::assertSame(200, $status);
        self::assertTrue($json['success']);
        self::assertSame([['sidc' => 'SFGPUCA--------', 'echelon' => 'peloton']], $this->updates);
    }

    public function testEmptyValuesGoBackToAutomatic(): void
    {
        [$status] = $this->patch(['sidc' => '', 'echelon' => '']);

        self::assertSame(200, $status);
        self::assertSame([['sidc' => null, 'echelon' => null]], $this->updates);
    }

    public function testInvalidSymbolIsRefusedWithoutWriting(): void
    {
        [$status, $json] = $this->patch(['name' => 'Alpha 2', 'sidc' => 'DROP TABLE units']);

        self::assertSame(400, $status);
        self::assertFalse($json['success']);
        self::assertStringContainsString('MIL-STD-2525', (string) $json['message']);
        self::assertSame([], $this->updates);
    }

    public function testUnknownEchelonIsRefused(): void
    {
        [$status, $json] = $this->patch(['echelon' => 'superdivision']);

        self::assertSame(400, $status);
        self::assertSame('Échelon non reconnu.', $json['message']);
        self::assertSame([], $this->updates);
    }

    public function testMissingColumnsAskForTheSiteUpdate(): void
    {
        $this->columns = [];
        [$status, $json] = $this->patch(['echelon' => 'groupe']);

        self::assertSame(409, $status);
        self::assertStringContainsString('mise à jour du site', (string) $json['message']);
        self::assertSame([], $this->updates);
    }

    public function testMembersWithoutOrbatManagementCannotChangeTheSymbol(): void
    {
        Gate::getInstance()->setPermissions(['organization.orbat.view']);
        [$status] = $this->patch(['sidc' => 'SFGPUCA--------']);

        self::assertSame(403, $status);
        self::assertSame([], $this->updates);
    }

    public function testExpiredSessionTokenIsRefused(): void
    {
        Csrf::token();
        $_POST = ['_csrf_token' => 'faux', 'unit_id' => '12', 'echelon' => 'groupe'];
        $res = $this->controller()->updateUnit(new Request());

        self::assertSame(403, $res->statusCode());
        self::assertSame([], $this->updates);
    }

    public function testCreationRefusesAnInvalidEchelonBeforeCreatingAnything(): void
    {
        $this->units->expects(self::never())->method('create');
        $_POST = ['_csrf_token' => Csrf::token(), 'action' => 'create', 'name' => 'Nouvelle équipe', 'echelon' => 'escouade_geante'];
        $res = $this->controller()->structure(new Request());

        self::assertSame(400, $res->statusCode());
    }

    public function testStructureOptionsListSymbolsForManagersOnly(): void
    {
        $res = $this->controller()->structureOptions(new Request());
        $json = (array) json_decode($res->body(), true);
        self::assertSame(200, $res->statusCode());
        self::assertTrue($json['capabilities']['military_symbol']);
        self::assertNotEmpty($json['symbols']['echelons']);
        self::assertNotEmpty($json['symbols']['functions']);

        Gate::getInstance()->setPermissions(['organization.orbat.view']);
        self::assertSame(403, $this->controller()->structureOptions(new Request())->statusCode());
    }
}
