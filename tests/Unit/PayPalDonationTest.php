<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PayPalDonation;
use PHPUnit\Framework\TestCase;

final class PayPalDonationTest extends TestCase
{
    public function testAcceptsPayPalDonationLinks(): void
    {
        self::assertSame('https://paypal.me/athena', PayPalDonation::normalizeUrl('paypal.me/athena'));
        self::assertSame('https://www.paypal.com/donate/?hosted_button_id=ABC123', PayPalDonation::normalizeUrl(' https://www.paypal.com/donate/?hosted_button_id=ABC123 '));
        self::assertSame('https://www.paypal.com/paypalme/athena', PayPalDonation::normalizeUrl('http://www.paypal.com/paypalme/athena'));
    }

    public function testRejectsAnythingElse(): void
    {
        foreach (['', 'https://evil.example/paypal.me/x', 'https://paypal.com.evil.fr/donate', 'https://paypal.me', 'javascript:alert(1)', 'https://paypal.me/a"onmouseover=x'] as $bad) {
            self::assertNull(PayPalDonation::normalizeUrl($bad), $bad);
        }
    }

    public function testRoutesAndAdminAccess(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertMatchesRegularExpression("#post\\('/admin/system/dons', \\[SystemDonationsController::class, 'save'\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]#", $routes);
        self::assertStringContainsString("get('/soutenir-atak/paypal-qr.png'", $routes);
    }
}
