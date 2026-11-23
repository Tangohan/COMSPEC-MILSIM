<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\MilitarySpecialtyController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\UserRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Services\Personnel\MilitarySpecialtyService;
use App\Support\JobCatalog\JobCatalog;
use App\Support\MilitarySpecialtyCatalog;
use App\Support\MilitarySpecialtyLabels;
use PHPUnit\Framework\TestCase;

final class MilitarySpecialtyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        Session::start();
        $_SESSION = ['tenant_id' => 7, 'user_id' => 42, '_csrf_token' => str_repeat('a', 64)];
        $_POST = $_GET = [];
        Gate::getInstance()->setPermissions([]);
    }

    protected function tearDown(): void
    {
        Gate::reset();
        $_POST = $_GET = $_SESSION = [];
    }

    public function testReadOnlyPersonnelPermissionDoesNotOpenTheRhCatalog(): void
    {
        Gate::getInstance()->setPermissions(['personnel.profile.view']);
        $service = $this->createMock(MilitarySpecialtyService::class);
        $service->expects(self::never())->method('ready');
        $response = (new MilitarySpecialtyController($service))->pageData(new Request());
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(403, $response->statusCode());
    }

    public function testWriteRejectsInvalidCsrfBeforeReadingOrMutatingTheDatabase(): void
    {
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $service = $this->createMock(MilitarySpecialtyService::class);
        $service->expects(self::never())->method('ready');
        $_POST = ['action' => 'member', 'target_id' => 10, '_csrf_token' => 'incorrect'];
        self::assertSame(419, (new MilitarySpecialtyController($service))->save(new Request())->statusCode());
    }

    public function testJobManagerCannotConfirmAMembersSpecialty(): void
    {
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        $service = $this->createMock(MilitarySpecialtyService::class);
        $service->expects(self::never())->method('saveMember');
        $_POST = ['action' => 'member', 'target_id' => 10, '_csrf_token' => Csrf::token()];
        self::assertSame(403, (new MilitarySpecialtyController($service))->save(new Request())->statusCode());
    }

    public function testMemberOutsideCurrentCommunityCannotBeRead(): void
    {
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $_GET = ['member' => 10];
        $users = $this->createMock(UserRepository::class);
        $users->expects(self::once())->method('isMemberOfTenant')->with(10, 7)->willReturn(false);
        $users->expects(self::never())->method('findById');
        $service = $this->createMock(MilitarySpecialtyService::class);
        $service->expects(self::never())->method('listForMember');
        $response = (new MilitarySpecialtyController($service, $users))->pageData(new Request());
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(404, $response->statusCode());
    }

    public function testSaveUsesSessionCommunityAndActorRatherThanPostedValues(): void
    {
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $service = $this->createMock(MilitarySpecialtyService::class);
        $service->method('ready')->willReturn(true);
        $service->expects(self::once())->method('saveMember')->with(7, 10, self::callback(static fn (array $v): bool => $v['specialty_code_id'] === '3' && $v['specialty_level_id'] === '' && $v['status'] === 'declared'), 42)->willReturn(5);
        $_POST = ['action' => 'member', 'target_id' => '10', 'specialty_code_id' => '3', 'specialty_level_id' => '',
            'status' => 'declared', 'tenant_id' => '8', 'user_id' => '99', '_csrf_token' => Csrf::token()];
        $response = (new MilitarySpecialtyController($service))->save(new Request());
        self::assertSame(302, $response->statusCode());
        self::assertStringContainsString('member=10', (string) $response->headerValue('Location'));
    }

    public function testExplanationAndSourceEscapeUntrustedContentAndPreserveLevelZero(): void
    {
        $html = Response::view('admin.effectifs_workspace.specialties', [
            'specialtyReady' => true, 'specialtyCanMember' => false,
            'specialtyMember' => ['id' => 10, 'display_name' => '<script>alert(1)</script>'],
            'specialtyMemberRows' => [['id' => 1, 'specialty_code_id' => 2, 'code' => '1Z000', 'label_fr' => 'Gestion',
                'status' => 'confirmed', 'level_code' => '0', 'level_label_fr' => 'Chief Enlisted Manager']],
            'specialtyCatalog' => [], 'csrfToken' => 'test',
        ])->body();
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('Niveau 0', $html);
        self::assertStringContainsString('JTAC', $html);
        self::assertStringContainsString('BM1', $html);
        self::assertStringContainsString('candidature', $html);
        self::assertStringNotContainsString('name="status"', $html);
        self::assertSame('', MilitarySpecialtyLabels::sourceUrl('javascript:alert(1)'));
        self::assertSame('', MilitarySpecialtyLabels::sourceUrl('data:text/html,unsafe'));
    }

    public function testAnExistingArchivedLevelStaysSelectedInTheDossierForm(): void
    {
        Gate::getInstance()->setPermissions(['personnel.profile.update']);
        $_GET = ['member' => 10, 'code' => 2];
        $level = ['id' => 99, 'level_code' => '0', 'official_title' => 'Chief Enlisted Manager', 'label_fr' => 'Gestion',
            'verification_status' => 'verified', 'archived_at' => '2026-01-01'];
        $code = ['id' => 2, 'code' => '1Z2X1', 'label_fr' => 'Contrôle aérien', 'label_en' => 'Combat Control',
            'official_title' => 'Combat Control', 'country_code' => 'US', 'country_name_fr' => 'États-Unis',
            'service_name' => 'USAF', 'system_name' => 'AFSC', 'kind' => 'specialty', 'verification_status' => 'verified',
            'description' => 'Métier', 'levels' => [$level]];
        $service = $this->createMock(MilitarySpecialtyService::class);
        $service->method('ready')->willReturn(true);
        $service->method('catalog')->willReturn([array_replace($code, ['levels' => []])]);
        $service->expects(self::once())->method('findCode')->with(2)->willReturn($code);
        $service->method('listForMember')->willReturn([['id' => 5, 'specialty_code_id' => 2, 'specialty_level_id' => 99,
            'status' => 'confirmed', 'level_code' => '0', 'level_label_fr' => 'Gestion', 'code' => '1Z2X1', 'label_fr' => 'Contrôle aérien']]);
        $users = $this->createMock(UserRepository::class);
        $users->method('isMemberOfTenant')->willReturn(true);
        $users->method('findById')->willReturn(['id' => 10, 'display_name' => 'Membre']);
        $awards = $this->createMock(PersonnelQualificationRepository::class);
        $awards->method('listForUser')->willReturn([]);
        $data = (new MilitarySpecialtyController($service, $users, null, null, $awards))->pageData(new Request());
        self::assertIsArray($data);
        $html = Response::view('admin.effectifs_workspace.specialties', $data)->body();
        self::assertStringContainsString('value="99" selected', $html);
        self::assertStringContainsString('Niveau archivé conservé', $html);
    }

    public function testCatalogReferencesUseStableSeedKeysAndCannotInventACountry(): void
    {
        $byRef = array_column(MilitarySpecialtyCatalog::entries(), null, 'reference_key');
        $matched = 0;
        foreach (JobCatalog::rawEntries() as $entry) {
            foreach ($entry['specialty_refs'] ?? [] as $ref) {
                self::assertArrayHasKey($ref, $byRef);
                self::assertSame($entry['country'], $byRef[$ref]['country']);
                self::assertSame($entry['code'], $byRef[$ref]['code']);
                $matched++;
            }
        }
        self::assertGreaterThanOrEqual(7, $matched);
    }
}
