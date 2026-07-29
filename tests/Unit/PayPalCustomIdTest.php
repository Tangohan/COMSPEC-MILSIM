<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\PayPalCheckoutService;
use PHPUnit\Framework\TestCase;

final class PayPalCustomIdTest extends TestCase
{
    public function testEncodeDecodeJsonCustomId(): void
    {
        $svc = new PayPalCheckoutService();
        $encoded = $svc->encodeCustomId(['pct' => str_repeat('a', 64), 'plan' => 'standard']);
        self::assertNotSame('', $encoded);
        $decoded = $svc->decodeCustomId($encoded);
        self::assertSame(str_repeat('a', 64), $decoded['pct'] ?? null);
        self::assertSame('standard', $decoded['plan'] ?? null);
    }

    public function testDecodeCompactTenantPlan(): void
    {
        $svc = new PayPalCheckoutService();
        $decoded = $svc->decodeCustomId('t42:pro_plus');
        self::assertSame('42', $decoded['tid'] ?? null);
        self::assertSame('pro_plus', $decoded['plan'] ?? null);
    }
}
