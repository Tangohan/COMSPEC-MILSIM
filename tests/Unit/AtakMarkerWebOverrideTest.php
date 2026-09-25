<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakMarkerWebOverride;
use PHPUnit\Framework\TestCase;

final class AtakMarkerWebOverrideTest extends TestCase
{
    public function testPreserveOnUpsertKeepsWebFieldsWhenLocked(): void
    {
        $previous = [
            'text' => 'Hangar du 160th',
            'description' => 'Hangar principal',
            'type' => 'b_air',
            'color' => 'ColorBlue',
            'web_locked' => true,
            'web_permanent' => true,
            'pos' => [100, 200],
        ];
        $incoming = [
            'text' => 'marker_12',
            'type' => 'mil_dot',
            'color' => 'ColorRed',
            'pos' => [110, 210],
            'source' => 'arma',
        ];
        $merged = AtakMarkerWebOverride::preserveOnUpsert($incoming, $previous);
        self::assertSame('Hangar du 160th', $merged['text']);
        self::assertSame('Hangar principal', $merged['description']);
        self::assertSame('b_air', $merged['type']);
        self::assertSame('ColorBlue', $merged['color']);
        self::assertTrue($merged['web_locked']);
        self::assertTrue($merged['web_permanent']);
        self::assertSame([110, 210], $merged['pos']);
    }

    public function testPreserveOnUpsertDoesNothingWithoutLock(): void
    {
        $previous = ['text' => 'Ancien', 'type' => 'mil_flag'];
        $incoming = ['text' => 'Nouveau', 'type' => 'mil_dot', 'pos' => [1, 2]];
        $merged = AtakMarkerWebOverride::preserveOnUpsert($incoming, $previous);
        self::assertSame('Nouveau', $merged['text']);
        self::assertSame('mil_dot', $merged['type']);
    }
}
