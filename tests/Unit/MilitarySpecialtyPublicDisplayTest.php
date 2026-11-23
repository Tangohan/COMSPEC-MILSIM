<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetDashboardService;
use App\Core\Gate;
use App\Repositories\UnitRepository;
use App\Support\OrbatRosterPayload;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class MilitarySpecialtyPublicDisplayTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::reset();
    }

    public function testOrbatAttachesSpecialtiesOnlyAfterPersonalAndUnitMasks(): void
    {
        $caps = new OrgVisibilityCapabilities();
        $root = ['commanderUserId' => 10, 'commander' => ['userId' => 10, 'label' => 'Chef'],
            'members' => [['user_id' => 10, 'label' => 'Visible'], ['user_id' => 11, 'label' => 'Masqué']],
            'children' => [['viewerNamesRedacted' => true, 'commanderUserId' => 12,
                'members' => [['user_id' => 12, 'label' => 'Unité masquée']], 'children' => []]]];
        $visibility = [
            11 => ['visibility_level' => VisibilityLevel::ANONYMIZED, 'anonymized_label' => 'Personnel protégé'],
        ];
        $filter = new ReflectionMethod(OrbatRosterPayload::class, 'enrichAndFilterRosterMembers');
        $root['members'] = $filter->invoke(null, [1 => $root['members']], $visibility, $caps, [['id' => 1]])[1];
        $root = OrbatRosterPayload::redactPeopleForViewer($root, $caps, $visibility);
        // L'enrichissement réel utilise ce même parcours après la dernière rédaction du payload.
        $attach = new ReflectionMethod(OrbatRosterPayload::class, 'attachGrades');
        $root = $attach->invoke(null, $root, [10 => '1Z2X1 (niveau 0)', 11 => 'CODE-SECRET', 12 => 'UNITE-SECRET'], 'specialty');
        self::assertSame('1Z2X1 (niveau 0)', $root['members'][0]['specialty']);
        self::assertSame('1Z2X1 (niveau 0)', $root['commander']['specialty']);
        self::assertSame(0, $root['members'][1]['user_id']);
        self::assertSame('', $root['members'][1]['specialty']);
        self::assertArrayNotHasKey('specialty', $root['children'][0]['members'][0]);
        self::assertStringNotContainsString('CODE-SECRET', json_encode($root, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('UNITE-SECRET', json_encode($root, JSON_THROW_ON_ERROR));
        $same = $attach->invoke(null, ['members' => [['user_id' => 10, 'label' => 'Visible']], 'children' => []], [], 'specialty');
        self::assertArrayNotHasKey('specialty', $same['members'][0]);
    }

    public function testJnetDoesNotCopySpecialtiesIntoAnonymousOrAssignmentRestrictedCards(): void
    {
        $service = (new ReflectionClass(JnetDashboardService::class))->newInstanceWithoutConstructor();
        $normalize = new ReflectionMethod(JnetDashboardService::class, 'normalizePersonCard');
        $base = ['id' => 10, 'display_name' => 'Visible', 'first_name' => 'Jean',
            'specialties' => [['code' => '<script>CODE</script>', 'label_fr' => 'Un métier', 'level_code' => '0']]];
        $visible = $normalize->invoke($service, $base);
        self::assertStringContainsString('niveau 0', $visible['specialties']);
        foreach ([['anonymized' => true], ['assignment_redacted' => true]] as $mask) {
            self::assertSame('', $normalize->invoke($service, $mask + $base)['specialties']);
        }
        ob_start();
        $person = $visible + ['profileFacts' => [], 'qualifications' => []];
        require dirname(__DIR__, 2) . '/views/jnet/personnel_show.php';
        $html = (string) ob_get_clean();
        self::assertStringNotContainsString('<script>CODE</script>', $html);
        self::assertStringContainsString('&lt;script&gt;CODE&lt;/script&gt;', $html);
        self::assertStringContainsString('Spécialités confirmées', $html);
    }

    public function testJnetStopsSpecialtyLoadingWhenEveryListedPersonIsRestricted(): void
    {
        Gate::getInstance()->setPermissions([]);
        $units = $this->createMock(UnitRepository::class);
        $units->expects(self::once())->method('personnelVisibilityByUserIdsForTenant')->with(7, [10,11])->willReturn([
            10 => ['visibility_level' => VisibilityLevel::HIDDEN],
            11 => ['visibility_level' => VisibilityLevel::NORMAL, 'assignment_visibility' => VisibilityLevel::RESTRICTED],
        ]);
        $reflection = new ReflectionClass(JnetDashboardService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('units')->setValue($service, $units);
        $load = new ReflectionMethod(JnetDashboardService::class, 'visibleSpecialties');
        self::assertSame([], $load->invoke($service, 7, [['id' => 10], ['id' => 11], ['id' => 12, 'anonymized' => true]]));
    }
}
