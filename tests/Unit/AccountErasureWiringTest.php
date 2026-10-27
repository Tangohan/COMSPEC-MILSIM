<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Une suppression de compte doit être définitive : un nouveau compte ouvert avec la même
 * adresse ne reprend rien. Ces vérifications verrouillent le câblage, les services étant
 * testés séparément (CommunityHandoverServiceTest, AccountIdentityTraceEraserTest).
 */
final class AccountErasureWiringTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testEraseAccountHandsOverCommunitiesAndRemovesTheRow(): void
    {
        $svc = $this->read('app/Services/Account/AccountDeletionService.php');
        if (!preg_match('/function eraseAccount\b.*?^\    \}/ms', $svc, $match)) {
            self::fail('eraseAccount introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString('handover()->handOver($ids)', $body);
        self::assertStringContainsString('traceEraser()->erase($email, $ids)', $body);
        self::assertStringContainsString('purgeFromTenantPreservingHistory($id)', $body);
        self::assertLessThan(
            strpos($body, 'anonymizeUserIdentity'),
            strpos($body, 'handOver'),
            'Les communautés sont transmises avant que la fiche ne disparaisse.'
        );
    }

    public function testEveryDeletionPathUsesTheErasure(): void
    {
        $svc = $this->read('app/Services/Account/AccountDeletionService.php');
        foreach (['softDeleteAccount', 'anonymizeAccountCompletely'] as $fn) {
            if (!preg_match('/function ' . $fn . '\b.*?^\    \}/ms', $svc, $match)) {
                self::fail($fn . ' introuvable.');
            }
            self::assertStringContainsString('eraseAccount(', $match[0], $fn);
        }
        if (!preg_match('/function softDeleteMembership\b.*?^\    \}/ms', $svc, $match)) {
            self::fail('softDeleteMembership introuvable.');
        }
        self::assertStringContainsString('handOver([$userId], $tenantId)', $match[0]);

        $ctrl = $this->read('app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('releaseIdentityBeforePurge(', $ctrl);
    }

    public function testOverdueDeletionIsAppliedAtLoginAndRegistration(): void
    {
        self::assertStringContainsString('eraseOverdueForEmail($email)', $this->read('app/Controllers/Auth/AuthController.php'));
        $svc = $this->read('app/Services/Account/AccountDeletionService.php');
        if (!preg_match('/function releaseEmailHeldByDeletedAccounts\b.*?^\    \}/ms', $svc, $match)) {
            self::fail('releaseEmailHeldByDeletedAccounts introuvable.');
        }
        self::assertStringContainsString('eraseOverdueForEmail($email)', $match[0]);
        self::assertStringContainsString('eraseOrphanGameAccount($email)', $match[0]);
        self::assertStringContainsString('releaseEmailHeldByDeletedAccounts($email)', $this->read('app/Controllers/Web/RegisterController.php'));
    }

    public function testSoloDashboardShowsProfileAtakAndHistory(): void
    {
        self::assertStringContainsString('SoloAccountOverviewService', $this->read('app/Controllers/Web/HomeController.php'));
        self::assertStringContainsString('dashboard_solo_overview.php', $this->read('views/dashboard.php'));
        $partial = $this->read('views/partials/dashboard_solo_overview.php');
        foreach (['Mon ATAK', 'Mes données de jeu', 'Profil'] as $label) {
            self::assertStringContainsString($label, $partial);
        }
    }
}