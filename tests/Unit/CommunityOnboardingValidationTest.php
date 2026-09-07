<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\CommunityOnboardingValidationService;
use PHPUnit\Framework\TestCase;

final class CommunityOnboardingValidationTest extends TestCase
{
    public function testDefaultsFounderCommandsRootAndFoundingToday(): void
    {
        $result = (new CommunityOnboardingValidationService())->validate($this->baseWizard());
        self::assertTrue($result['ok'], implode(' ', $result['errors'] ?? []));
        $n = $result['normalized'] ?? [];
        self::assertTrue((bool) ($n['founder_commands_root'] ?? false));
        self::assertSame('today', $n['org_founding_choice'] ?? '');
        self::assertSame((new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d'), $n['org_founding_date'] ?? '');
        self::assertSame('', $n['catalog_kit_code'] ?? 'x');
    }

    public function testFounderCanLeaveRootVacant(): void
    {
        $wizard = $this->baseWizard();
        $wizard['wizard_founder_commands_root'] = '0';
        $result = (new CommunityOnboardingValidationService())->validate($wizard);
        self::assertTrue($result['ok'], implode(' ', $result['errors'] ?? []));
        self::assertFalse((bool) ($result['normalized']['founder_commands_root'] ?? true));
    }

    public function testOlderUnitWithoutDateLeavesFoundingEmpty(): void
    {
        $wizard = $this->baseWizard();
        $wizard['wizard_org_founding_choice'] = 'older';
        $result = (new CommunityOnboardingValidationService())->validate($wizard);
        self::assertTrue($result['ok'], implode(' ', $result['errors'] ?? []));
        self::assertSame('older', $result['normalized']['org_founding_choice'] ?? '');
        self::assertNull($result['normalized']['org_founding_date'] ?? 'x');
    }

    public function testOlderUnitWithPastDateIsKept(): void
    {
        $wizard = $this->baseWizard();
        $wizard['wizard_org_founding_choice'] = 'older';
        $wizard['wizard_org_founding_date'] = '2018-03-12';
        $result = (new CommunityOnboardingValidationService())->validate($wizard);
        self::assertTrue($result['ok'], implode(' ', $result['errors'] ?? []));
        self::assertSame('2018-03-12', $result['normalized']['org_founding_date'] ?? '');
    }

    public function testFutureFoundingDateIsRejected(): void
    {
        $wizard = $this->baseWizard();
        $wizard['wizard_org_founding_choice'] = 'older';
        $wizard['wizard_org_founding_date'] = '2099-01-01';
        $result = (new CommunityOnboardingValidationService())->validate($wizard);
        self::assertFalse($result['ok']);
        self::assertSame('review', $result['step'] ?? '');
        self::assertNotSame([], $result['errors'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseWizard(): array
    {
        return [
            'grade_system_code' => 'FR_CLASSIC',
            'timezone' => 'Europe/Paris',
            'default_locale' => 'fr',
            'orbat_visibility' => 'members',
            'founder_grade_id' => 12,
            'roles_template' => 'quick',
            'wizard_represents_real_unit' => '0',
            'wizard_fictional_unit_label' => 'Unité d’essai',
            'units' => [
                [
                    'key' => 'root',
                    'parent_key' => '',
                    'name' => 'Groupe',
                    'slug' => 'groupe',
                    'type' => 'group',
                    'display_order' => 0,
                ],
            ],
        ];
    }
}
