<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseObjectPerson;
use PHPUnit\Framework\TestCase;

final class SseObjectPersonTest extends TestCase
{
    public function testDesignationDoesNotInventCivilIdentity(): void
    {
        $fields = SseObjectPerson::fields(7, 'Contact du marché', [
            'sex' => 'M', 'status' => 'civil', 'nationality' => 'FRA',
            'description' => 'Cicatrice', 'notes' => 'Vu au marché', 'precision' => 'Source bureau',
        ]);
        self::assertSame(7, $fields['tenant_id']);
        self::assertSame(1, $fields['context_id']);
        self::assertSame('Contact du marché', $fields['alias']);
        self::assertArrayNotHasKey('last_name', $fields);
        self::assertArrayNotHasKey('first_name', $fields);
        self::assertSame('M', $fields['sex_apparent']);
        self::assertSame('Cicatrice', $fields['distinguishing_marks']);
        self::assertSame("Vu au marché\nSource bureau", $fields['statements']);
    }
}
