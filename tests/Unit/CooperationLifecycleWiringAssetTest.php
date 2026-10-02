<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CooperationLifecycleWiringAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testNewLifecycleRoutesAreRegisteredAndLegacyRedirectsKept(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/back-office/cooperation/missions/{id}/remove-partner', [InterteamMissionWebController::class, 'removePartner']", $routes);
        self::assertStringContainsString("'/back-office/cooperation/missions/{id}/cancel', [InterteamMissionWebController::class, 'cancelProposal']", $routes);
        self::assertStringContainsString("'/admin/interteam-missions/{id}/invite'", $routes);
        self::assertStringContainsString("'/back-office/ressources/interteam-missions/{id}'", $routes);
    }

    public function testCooperationViewsUseTheSharedConfirmDialogAvailableInTheBackOffice(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/views/back_office/cooperation/{,missions/}*.php', GLOB_BRACE) ?: [] as $file) {
            self::assertStringNotContainsString('return confirm(', (string) file_get_contents($file), basename($file));
        }
        self::assertStringContainsString("views/partials/ui/confirm_dialog.php", $this->read('views/layout/main.php'));
        self::assertStringContainsString('data-ui-confirm="1"', $this->read('views/back_office/cooperation/missions/archive.php'));
    }

    public function testControllerUsesTransitionRulesAndScopedGrantRevocation(): void
    {
        $c = $this->read('app/Controllers/Web/InterteamMissionWebController.php');
        self::assertStringContainsString('CooperationTransitionRules::launchReadiness', $c);
        self::assertStringContainsString('CooperationTransitionRules::canRespondToInvitation', $c);
        self::assertStringContainsString('deleteGrant($grantId, $mid)', $c);
        self::assertStringNotContainsString('allPartnersAccepted($id)', $c);
        // saveMeta ne modifie que les champs envoyés (plus d’écrasement croisé Réunion / Structures).
        self::assertStringContainsString("needs_submitted", $c);
        self::assertStringContainsString('name="needs_submitted"', $this->read('views/back_office/cooperation/missions/orbat.php'));
        $show = $this->read('views/back_office/cooperation/missions/show.php');
        self::assertStringNotContainsString("\$canPilot && \$status === 'draft'", $show);
        self::assertStringContainsString('decline_reason', $show);
    }
}
