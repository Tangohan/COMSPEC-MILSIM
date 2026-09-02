<?php

declare(strict_types=1);

namespace Tests\Courrier;

use App\Services\Courrier\CourrierLetterhead;
use PHPUnit\Framework\TestCase;

final class CourrierLetterheadTest extends TestCase
{
    public function testGroupChildUsesParentAsUnit(): void
    {
        $org = CourrierLetterhead::fromAssignmentChain(
            [
                ['name' => '1er Groupe', 'type' => 'group'],
                ['name' => 'CIE A', 'type' => 'company'],
            ],
            'CERBERE',
            '92e RI',
            'RH / S1'
        );

        self::assertSame('CERBERE', $org['tenant_name']);
        self::assertSame('CIE A', $org['unit_name']);
        self::assertSame('1er Groupe', $org['group_name']);
    }

    public function testUnitMatchingTenantFallsBackToAffiliation(): void
    {
        $org = CourrierLetterhead::fromAssignmentChain(
            [
                ['name' => 'CERBERE', 'type' => 'unit'],
            ],
            'CERBERE',
            '92e RI',
            ''
        );

        self::assertSame('92e RI', $org['unit_name']);
        self::assertSame('', $org['group_name']);
    }

    public function testJobRoleFillsGroupWhenOrgHasNoGroup(): void
    {
        $org = CourrierLetterhead::fromAssignmentChain(
            [
                ['name' => 'CIE A', 'type' => 'company'],
            ],
            'CERBERE',
            '',
            'RH / S1'
        );

        self::assertSame('CIE A', $org['unit_name']);
        self::assertSame('RH / S1', $org['group_name']);
    }

    public function testOverlayReplacesDummyPlaceholders(): void
    {
        $merged = CourrierLetterhead::overlay(
            [
                'header_line1' => 'MINISTÈRE DE LA DÉFENSE',
                'header_unit' => '92e RI — CERBERE',
                'header_section' => 'RH / S1',
            ],
            [
                'header_line1' => 'CERBERE',
                'header_unit' => 'CIE A',
                'header_section' => '1er Groupe',
            ]
        );

        self::assertSame('CERBERE', $merged['header_line1']);
        self::assertSame('CIE A', $merged['header_unit']);
        self::assertSame('1er Groupe', $merged['header_section']);
    }

    public function testOverlayKeepsCustomHeader(): void
    {
        $merged = CourrierLetterhead::overlay(
            [
                'header_line1' => 'Commandement des opérations',
                'header_unit' => '',
                'header_section' => '',
            ],
            [
                'header_line1' => 'CERBERE',
                'header_unit' => 'CIE A',
                'header_section' => '1er Groupe',
            ]
        );

        self::assertSame('Commandement des opérations', $merged['header_line1']);
        self::assertSame('CIE A', $merged['header_unit']);
        self::assertSame('1er Groupe', $merged['header_section']);
    }
}
