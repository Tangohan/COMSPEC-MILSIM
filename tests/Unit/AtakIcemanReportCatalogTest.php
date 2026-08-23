<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakIcemanReportCatalog;
use App\Support\TacticalAlertParser;
use PHPUnit\Framework\TestCase;

final class AtakIcemanReportCatalogTest extends TestCase
{
    public function testKnownTypesCoverIcemanReportsApp(): void
    {
        foreach (['TIC', 'EAGLE_DOWN', 'BDA', 'FRAGO', 'SALUTE'] as $code) {
            self::assertTrue(AtakIcemanReportCatalog::isKnown($code), $code);
            self::assertNotSame('Rapport', AtakIcemanReportCatalog::labelFr($code), $code);
        }
    }

    public function testParseEagleDownFormBody(): void
    {
        $raw = implode("\n", [
            'Category: EAGLE DOWN',
            'DTG: 20350624 1213',
            'Callsign: N-10',
            'Grid: 200092',
            'Casualty: NewPI',
            'Status: Critical',
            'Mechanism: GSW',
            'Situation: Contact Ongoing',
            'Medevac: Priority',
            'LZ: Secure',
            'Current Treatment: N/A',
            'Remarks: N/A',
        ]);
        $fields = AtakIcemanReportCatalog::parseFields('EAGLE_DOWN', $raw);

        self::assertSame('NewPI', $fields['casualty'] ?? null);
        self::assertSame('Critical', $fields['status'] ?? null);
        self::assertSame('GSW', $fields['mechanism'] ?? null);
        self::assertSame('Secure', $fields['lz'] ?? null);
        self::assertArrayNotHasKey('treatment', $fields);
        self::assertSame('FLASH', AtakIcemanReportCatalog::priorityFor('EAGLE_DOWN', $fields));
    }

    public function testParseSaluteNumberedLines(): void
    {
        $raw = "SPOT REPORT / SALUTE\n1. Size: 4\n2. Activity: patrol\n3. Location: 200092\n4. Unit/Uniform: mixed\n5. Time Observed: 1213\n6. Equipment: rifles";
        $fields = AtakIcemanReportCatalog::parseFields('SALUTE', $raw);

        self::assertSame('4', $fields['size'] ?? null);
        self::assertSame('patrol', $fields['activity'] ?? null);
        self::assertSame('rifles', $fields['equipment'] ?? null);
    }

    public function testChatPrefixParsesEagleDownIntoStructuredAlert(): void
    {
        $body = 'ALERTE TACTIQUE|EAGLE_DOWN|N-10|200092|19345.12|18400.50|Category: EAGLE DOWN · Casualty: NewPI · Status: Critical · Mechanism: GSW · LZ: Secure';
        $parsed = TacticalAlertParser::parse($body);

        self::assertIsArray($parsed);
        self::assertSame('eagle_down', $parsed['kind']);
        self::assertSame('NewPI', $parsed['eagle_down']['casualty'] ?? null);
        self::assertSame('Critical', $parsed['eagle_down']['status'] ?? null);
        self::assertSame('EAGLE_DOWN', AtakIcemanReportCatalog::reportTypeForAlertKind('eagle_down'));
        self::assertNull(AtakIcemanReportCatalog::reportTypeForAlertKind('tic_clear'));
    }

    public function testParseBdaReportsForm(): void
    {
        $raw = "BDA REPORT\nDTG: 20350624 1213\nUnit: N-10\nType: Infantry\nDesc: squad suppressed\nRating: Damaged\nReattack: No Reattack Required";
        $fields = AtakIcemanReportCatalog::parseFields('BDA', $raw);

        self::assertSame('Infantry', $fields['type'] ?? null);
        self::assertSame('Damaged', $fields['rating'] ?? null);
        self::assertSame('ROUTINE', AtakIcemanReportCatalog::priorityFor('BDA', $fields));
    }
}
