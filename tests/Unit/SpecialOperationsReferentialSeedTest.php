<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SpecialOperationsReferentialSeedTest extends TestCase
{
    public function testRequestedUnitsAreExplicitlySeeded(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/military_referential_seed.php');
        foreach (['us-24sts', '24th STS', 'us-delta-b-squadron', 'B Squadron', 'us-160soar', '160th SOAR', 'us-ussocom', "['SOF']"] as $expected) {
            self::assertStringContainsString($expected, $seed);
        }
    }
}
