<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\WizardOrbatPresets;
use PHPUnit\Framework\TestCase;

final class WizardOrbatPresetsTest extends TestCase
{
    public function testEveryPresetPassesTheWizardUnitRules(): void
    {
        $presets = WizardOrbatPresets::all();
        self::assertGreaterThanOrEqual(6, count($presets));
        $ids = array_column($presets, 'id');
        self::assertSame(count($ids), count(array_unique($ids)));
        self::assertContains(WizardOrbatPresets::DEFAULT_ID, $ids);

        foreach ($presets as $preset) {
            $keys = [];
            $slugs = [];
            $roots = 0;
            foreach ($preset['units'] as $unit) {
                self::assertArrayNotHasKey($unit['key'], $keys, $preset['id']);
                $keys[$unit['key']] = true;
                self::assertMatchesRegularExpression('/^[a-z0-9]([a-z0-9-]{0,48}[a-z0-9])?$/', $unit['slug']);
                self::assertArrayNotHasKey($unit['slug'], $slugs, $preset['id'] . ':' . $unit['slug']);
                $slugs[$unit['slug']] = true;
                self::assertContains($unit['type'], ['group', 'section', 'team', 'squad']);
                self::assertNotSame('', trim($unit['name']));
                if ($unit['parent_key'] === '') {
                    $roots++;
                }
            }
            self::assertSame(1, $roots, $preset['id']);
            foreach ($preset['units'] as $unit) {
                if ($unit['parent_key'] !== '') {
                    self::assertArrayHasKey($unit['parent_key'], $keys, $preset['id'] . ':' . $unit['key']);
                }
            }
        }
    }

    public function testUnknownPresetFallsBackToDefault(): void
    {
        self::assertSame(
            WizardOrbatPresets::unitsFor(WizardOrbatPresets::DEFAULT_ID),
            WizardOrbatPresets::unitsFor('inexistant')
        );
        foreach (WizardOrbatPresets::forWizard() as $preset) {
            self::assertSame(count($preset['units']), $preset['count']);
        }
    }
}
