<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonnelDirectoryHints;
use PHPUnit\Framework\TestCase;

final class PersonnelDirectoryHintsTest extends TestCase
{
    public function testDistinctCharacterLabelHidesSameName(): void
    {
        self::assertSame('', PersonnelDirectoryHints::distinctCharacterLabel('MORPHIDE', 'MORPHIDE'));
        self::assertSame('', PersonnelDirectoryHints::distinctCharacterLabel('Morphide', 'MORPHIDE'));
        self::assertSame('', PersonnelDirectoryHints::distinctCharacterLabel('Noopy', ''));
    }

    public function testDistinctCharacterLabelKeepsDifferentSceneName(): void
    {
        self::assertSame('Jake Gylen', PersonnelDirectoryHints::distinctCharacterLabel('Noopy', 'Jake Gylen'));
    }

    public function testEnrichUnitHintsBuildsTooltipFromPathAndBlurb(): void
    {
        $rows = [[
            'id' => 1,
            'primary_unit_id' => 0,
            'unit_name' => '24th STS Gold Team SOF TACP',
            'unit_code' => '24STS',
            'unit_blurb' => 'Équipe TACP air-sol.',
        ]];
        $out = PersonnelDirectoryHints::enrichUnitHints(0, $rows);
        self::assertSame($rows, $out);

        $out = PersonnelDirectoryHints::enrichUnitHints(1, $rows);
        self::assertNotSame('', (string) ($out[0]['unit_tooltip'] ?? ''));
        self::assertStringContainsString('24th STS Gold Team SOF TACP', (string) $out[0]['unit_tooltip']);
        self::assertStringContainsString('24STS', (string) $out[0]['unit_tooltip']);
        self::assertStringContainsString('Équipe TACP', (string) $out[0]['unit_tooltip']);
    }
}
