<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakOrderRepository;
use PHPUnit\Framework\TestCase;

final class AtakOrderAllyTargetTest extends TestCase
{
    public function testAllyIsAKnownRecipientType(): void
    {
        self::assertContains('ally', AtakOrderRepository::TARGET_TYPES);
    }
}
