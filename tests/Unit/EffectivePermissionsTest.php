<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Core\Gate;
use App\Services\Rbac\EffectivePermissions;
use App\Services\Rbac\EffectivePermissionsService;
use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 4.3 : les droits effectifs reflètent exactement ce que la porte (Gate) accorde. */
final class EffectivePermissionsTest extends TestCase
{
    /** @var array{0: list<string>, 1: array<string, list<int>>, 2: bool} */
    private array $gateBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $gate = Gate::getInstance();
        $this->gateBefore = [$gate->permissionSlugs(), $gate->getUnitPermissionMap(), $gate->isPlatformAdmin()];
    }

    protected function tearDown(): void
    {
        $gate = Gate::getInstance();
        $gate->setFullRbacState($this->gateBefore[0], $this->gateBefore[1]);
        $gate->setPlatformAdmin($this->gateBefore[2]);
        parent::tearDown();
    }

    /** @return list<array{0: list<string>, 1: bool}> */
    private static function scenarios(): array
    {
        return [
            [[], false],
            [['documents.view'], false],
            [['admin.organization'], false],
            [['admin.access'], false],
            [['organization.orbat.manage'], false],
            [['personnel.manage_visibility'], false],
            [['training.manage'], false],
            [['media.manage'], false],
            [['comms.email.broadcast', 'comms.announcement.send'], false],
            [['interteam.missions.manage'], false],
            [['interteam.missions.respond'], false],
            [['cooperation.missions.manage'], false],
            [['site.support', 'admin.system', '*', 'admin.backoffice.view'], false],
            [[], true],
            [['admin.organization'], true],
        ];
    }

    /** Pour chaque droit du catalogue et chaque jeu de droits, même réponse que Gate (implications et plateforme comprises). */
    public function testAnswersMatchGateForEveryCatalogSlug(): void
    {
        $catalog = array_merge(TenantPermissionCatalog::allSlugs(), ['admin.system', 'site.support', '*', '']);
        foreach (self::scenarios() as [$flat, $platformAdmin]) {
            $gate = new Gate();
            $gate->setFullRbacState($flat, []);
            $gate->setPlatformAdmin($platformAdmin);
            $effective = new EffectivePermissions($flat, [], $platformAdmin);
            foreach ($catalog as $slug) {
                self::assertSame($gate->allows($slug), $effective->allows($slug), $slug . ' avec ' . implode(',', $flat));
            }
        }
    }

    public function testImplicationsAreFollowed(): void
    {
        $orbat = new EffectivePermissions(['organization.orbat.manage'], [], false);
        self::assertTrue($orbat->allows('organization.orbat.view'));
        self::assertTrue($orbat->allows('personnel.view_hidden'));
        self::assertFalse($orbat->allows('admin.organization'));

        $org = new EffectivePermissions(['admin.organization'], [], false);
        self::assertTrue($org->allows('personnel.grades.manage'));
        self::assertTrue($org->allows('training.manage'));
        self::assertFalse($org->allows('admin.system'), 'admin.organization ne donne jamais le pilotage du site');
        self::assertFalse($org->allows('admin.members.moderate'), 'droit sensible : à accorder explicitement');
    }

    public function testSiteAndPlatformPermissionsAreNotGrantedByRoles(): void
    {
        $siteRole = new EffectivePermissions(['site.support', 'admin.system', '*', 'admin.backoffice.view'], [], false);
        self::assertFalse($siteRole->allows('admin.system'));
        self::assertFalse($siteRole->allows('site.support'));
        self::assertFalse($siteRole->allows('personnel.grades.manage'), 'le joker « * » ne donne rien');
        self::assertTrue($siteRole->allows('admin.backoffice.view'), 'un droit du site ordinaire reste accordé');

        $platform = new EffectivePermissions([], [], true);
        self::assertTrue($platform->isPlatformAdmin());
        self::assertTrue($platform->allows('admin.system'));
        self::assertTrue($platform->allows('personnel.grades.manage'));
    }

    public function testUnitScopedPermissionsOnlyApplyInTheirUnits(): void
    {
        $effective = new EffectivePermissions(['documents.view'], ['personnel.status.manage' => [4, 9, 0, 4]], false);
        self::assertFalse($effective->allows('personnel.status.manage'), 'pas valable partout');
        self::assertTrue($effective->allowsInUnit('personnel.status.manage', 9));
        self::assertTrue($effective->allowsInUnit('personnel.status.manage', 4));
        self::assertFalse($effective->allowsInUnit('personnel.status.manage', 5));
        self::assertFalse($effective->allowsInUnit('personnel.status.manage', null));
        self::assertSame([4, 9], $effective->unitScoped()['personnel.status.manage'], 'identifiants nettoyés comme Gate le fait');
        self::assertTrue($effective->allowsInUnit('documents.view', 5), "un droit valable partout l'est dans toute unité");
    }

    public function testSourceTellsWhyAPermissionIsGranted(): void
    {
        $effective = new EffectivePermissions(['admin.organization', 'documents.view'], ['personnel.status.manage' => [3]], false);
        self::assertSame(EffectivePermissions::SOURCE_DIRECT, $effective->source('documents.view'));
        self::assertSame(EffectivePermissions::SOURCE_IMPLIED, $effective->source('personnel.grades.manage'));
        $unitOnly = new EffectivePermissions(['documents.view'], ['personnel.status.manage' => [3]], false);
        self::assertSame(EffectivePermissions::SOURCE_UNIT, $unitOnly->source('personnel.status.manage'));
        self::assertSame(EffectivePermissions::SOURCE_NONE, $effective->source('admin.system'));
        self::assertSame(EffectivePermissions::SOURCE_NONE, $effective->source('atak.manage'));

        $platform = new EffectivePermissions([], [], true);
        self::assertSame(EffectivePermissions::SOURCE_PLATFORM_ADMIN, $platform->source('personnel.grades.manage'));
    }

    public function testAllowedAmongKeepsTheOrderOfTheList(): void
    {
        $effective = new EffectivePermissions(['organization.orbat.manage'], [], false);
        self::assertSame(
            ['organization.orbat.view', 'personnel.view_hidden'],
            $effective->allowedAmong(['organization.orbat.view', 'admin.access', 'personnel.view_hidden'])
        );
    }

    public function testToArrayExposesTheUnion(): void
    {
        $effective = new EffectivePermissions(['documents.view'], ['personnel.status.manage' => [3]], false);
        self::assertSame(
            ['flat' => ['documents.view'], 'unit_scoped' => ['personnel.status.manage' => [3]], 'platform_admin' => false],
            $effective->toArray()
        );
    }

    /** Le service lit l'état que RbacService pose dans la porte, puis rend la porte de la requête telle quelle. */
    public function testServiceReadsLoadedStateAndRestoresTheGate(): void
    {
        $gate = Gate::getInstance();
        $gate->setFullRbacState(['documents.view'], ['atak.manage' => [7]]);
        $gate->setPlatformAdmin(true);

        $seen = null;
        $service = new EffectivePermissionsService(null, null, function (array $row) use (&$seen): void {
            $seen = $row;
            Gate::getInstance()->setFullRbacState(['organization.orbat.manage'], ['personnel.status.manage' => [2]]);
            Gate::getInstance()->setPlatformAdmin(false);
        });
        $effective = $service->forUserRow(['id' => 12, 'email' => 'a@b.test', 'tenant_id' => 1], 5);

        self::assertSame(5, $seen['tenant_id'], 'la communauté demandée prime sur celle de la ligne');
        self::assertSame(12, $seen['id']);
        self::assertSame(['organization.orbat.manage'], $effective->flat());
        self::assertSame([2], $effective->unitScoped()['personnel.status.manage']);
        self::assertFalse($effective->isPlatformAdmin());
        self::assertTrue($effective->allows('organization.orbat.view'));

        self::assertSame(['documents.view'], $gate->permissionSlugs());
        self::assertSame(['atak.manage' => [7]], $gate->getUnitPermissionMap());
        self::assertTrue($gate->isPlatformAdmin());
    }

    public function testServiceRestoresTheGateWhenLoadingFails(): void
    {
        $gate = Gate::getInstance();
        $gate->setFullRbacState(['documents.view'], []);
        $gate->setPlatformAdmin(false);
        $service = new EffectivePermissionsService(null, null, static function (array $row): void {
            Gate::getInstance()->setFullRbacState(['admin.access'], []);
            throw new \RuntimeException('base indisponible');
        });
        $message = null;
        try {
            $service->forUserRow(['id' => 1]);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
        }
        self::assertSame('base indisponible', $message);
        self::assertSame(['documents.view'], $gate->permissionSlugs());
    }

    public function testCurrentSessionIsReadWithoutReloading(): void
    {
        $gate = Gate::getInstance();
        $gate->setFullRbacState(['training.manage'], []);
        $gate->setPlatformAdmin(false);
        $effective = (new EffectivePermissionsService())->forCurrentSession();
        self::assertTrue($effective->allows('training.view'));
        self::assertFalse($effective->allows('admin.access'));
    }

    /** Le service ne calcule rien lui-même : il passe par RbacService (implications, droits du site, surcharges). */
    public function testServiceDelegatesToRbacService(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Rbac/EffectivePermissionsService.php');
        self::assertStringContainsString('->setPermissionsForGateFromUserRow($userRow, $this->users)', $src);
        self::assertStringNotContainsString('tenant_user_roles', $src);
        self::assertStringNotContainsString('user_permission_overrides', $src);
    }
}
