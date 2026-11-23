<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakSupportController;
use App\Core\ExceptionHandler;
use App\Core\Request;
use App\Repositories\AtakDonationRepository;
use App\Repositories\UserRepository;
use App\Services\Billing\AtakDonationFulfillmentService;
use App\Services\Billing\StripeCheckoutService;
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

    public function testAmountIsPrefilledOnlyForPayPalMeLinks(): void
    {
        self::assertSame('https://www.paypal.me/TTRDProIndividual/10.00EUR', PayPalDonation::amountUrl('https://www.paypal.me/TTRDProIndividual', 1000));
        self::assertSame('https://www.paypal.com/paypalme/athena/5.00EUR', PayPalDonation::amountUrl('https://www.paypal.com/paypalme/athena', 500));
        $button = 'https://www.paypal.com/donate/?hosted_button_id=ABC123';
        self::assertSame($button, PayPalDonation::amountUrl($button, 1000));
    }

    public function testDonationPageUsesThePaymentsPayPalSetting(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        // Un seul réglage PayPal : l’ancienne page Admin > Dons renvoie vers Admin > Paiements.
        self::assertStringNotContainsString('SystemDonationsController', $routes);
        self::assertFileDoesNotExist($root . '/app/Controllers/Admin/System/SystemDonationsController.php');
        self::assertMatchesRegularExpression("#get\\('/admin/system/dons', fn \\(\\) => \\\\App\\\\Core\\\\Response::redirect\\(url\\('admin/paiements'\\)#", $routes);
        self::assertStringContainsString("get('/soutenir-atak/paypal-qr.png'", $routes);
        self::assertStringNotContainsString('admin/system/dons', (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php'));

        $support = (string) file_get_contents($root . '/app/Support/PayPalDonation.php');
        self::assertStringContainsString('ManualBilling::SETTING_PAYPAL_URL', $support);
        $service = (string) file_get_contents($root . '/app/Services/Billing/PlanPaymentService.php');
        self::assertStringContainsString('PayPalDonation::accountUrl(', $service);
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/AtakSupportController.php');
        self::assertStringContainsString('PayPalDonation::accountUrl()', $controller);
    }

    public function testQrEndpointReturnsAnImageWithTheApplicationErrorHandler(): void
    {
        $controller = new AtakSupportController(
            $this->createStub(AtakDonationRepository::class),
            $this->createStub(StripeCheckoutService::class),
            $this->createStub(AtakDonationFulfillmentService::class),
            $this->createStub(UserRepository::class),
        );

        $previousLevel = error_reporting(E_ALL);
        ExceptionHandler::register();
        try {
            $response = $controller->paypalQr(new Request());
        } finally {
            restore_error_handler();
            restore_exception_handler();
            error_reporting($previousLevel);
        }

        self::assertSame(200, $response->statusCode());
        self::assertSame('public, max-age=3600', $response->headerValue('Cache-Control'));
        $mime = $response->headerValue('Content-Type');
        if (extension_loaded('gd')) {
            self::assertSame('image/png', $mime);
        }
        if ($mime === 'image/png') {
            self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->body());
        } else {
            self::assertStringStartsWith('image/svg+xml', (string) $mime);
            self::assertStringContainsString('<svg', $response->body());
        }
    }
}
