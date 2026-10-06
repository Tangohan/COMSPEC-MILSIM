<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QualificationExamples as E;
use PHPUnit\Framework\TestCase;

final class QualificationExamplesTest extends TestCase
{
    public function testNumberPreview(): void
    {
        self::assertSame('QUAL-SC1-2026-0042', E::numberPreview('', 'sc1', 2026, 42));
        self::assertSame('JTAC-2026-0001', E::numberPreview('JTAC-{year}-{seq}', 'JTAC', 2026, 1));
        self::assertSame('PIL-H/2026/0007', E::numberPreview('{code}/{année}/{sequence}', 'PIL-H', 2026, 7));
        self::assertSame('QUAL-QUAL-2026-0001', E::numberPreview('', '***', 2026, 1));
    }

    public function testExamplesAreCoherent(): void
    {
        $keys = [];
        foreach (E::all() as $ex) {
            self::assertArrayHasKey('code', $ex);
            self::assertMatchesRegularExpression('/^[A-Z0-9_\-]+$/', (string) $ex['code']);
            self::assertNotSame('', (string) $ex['name']);
            self::assertContains($ex['scope'], ['global', 'unit']);
            self::assertArrayNotHasKey((string) $ex['key'], $keys);
            $keys[(string) $ex['key']] = true;
            if ($ex['is_permanent']) {
                self::assertSame('', $ex['validity'], $ex['key'] . ' : une qualification permanente n’a pas de durée');
                self::assertFalse($ex['renewal_required']);
            } else {
                self::assertGreaterThan(0, (int) $ex['validity']);
            }
            if ($ex['enforce_level_progression']) {
                self::assertTrue($ex['uses_levels']);
            }
        }
        self::assertArrayHasKey('sc1', $keys);
        self::assertArrayHasKey('jtac', $keys);
        self::assertTrue(is_string(json_encode(E::all(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
    }
}
