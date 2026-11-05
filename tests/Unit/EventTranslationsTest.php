<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EventTranslationsTest extends TestCase
{
    public function testEnglishCatalogCoversAllFrenchKeysAndPlaceholders(): void
    {
        $fr = require dirname(__DIR__, 2) . '/lang/fr/events.php';
        $en = require dirname(__DIR__, 2) . '/lang/en/events.php';
        self::assertSame(array_keys($fr), array_keys($en));
        foreach ($fr as $key => $text) {
            self::assertNotSame('', trim($en[$key]));
            preg_match_all('/:([a-z_]+)/', $text, $french);
            preg_match_all('/:([a-z_]+)/', $en[$key], $english);
            sort($french[1]);
            sort($english[1]);
            self::assertSame($french[1], $english[1], $key);
        }
    }
}
