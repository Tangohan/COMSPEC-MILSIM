<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PaymentLedgerRepository;
use PHPUnit\Framework\TestCase;

/** Registre des paiements : chaque ligne non traitée a ses actions (annuler, refuser, encaisser, rembourser). */
final class PaymentRegisterActionsTest extends TestCase
{
    public function testStripeDonationTransitionsOnlyFromUntreatedOrPaid(): void
    {
        $t = PaymentLedgerRepository::STRIPE_DONATION_TRANSITIONS;
        self::assertSame(['pending'], $t['cancelled']);
        self::assertSame(['pending'], $t['rejected']);
        self::assertSame(['pending'], $t['paid']);
        self::assertSame(['paid'], $t['refunded']);
    }

    public function testRoutesAndViewExposeEveryAction(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/plan_payments.php');
        foreach (['annuler' => 'stripeDonationCancel', 'refuser' => 'stripeDonationReject', 'encaisser' => 'stripeDonationMarkPaid', 'rembourser' => 'stripeDonationRefund'] as $path => $method) {
            self::assertStringContainsString("'/admin/paiements/dons-stripe/{id}/" . $path . "'", $routes);
            self::assertStringContainsString("'" . $method . "'", $routes);
            self::assertStringContainsString("'/dons-stripe/' . \$e['id'] . '/" . $path . "'", $view);
        }
        self::assertSame(2, substr_count($view, "\$base . '/' . \$e['id'] . '/refuser'"), 'refus possible pour une demande à payer comme à vérifier');
    }

    public function testWebhookNeverOverwritesARefund(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/AtakDonationRepository.php');
        self::assertStringContainsString("status IN (\\'pending\\', \\'cancelled\\', \\'rejected\\')", $repo);
    }
}
