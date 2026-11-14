<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RoleplayFollowupBoard;
use PHPUnit\Framework\TestCase;

/**
 * Bureau de suivi roleplay : les bilans se coupent depuis le bureau, « À compléter » dit ce qui manque ; aperçu de visibilité
 * (membre standard, cadre, commandement) qui change vraiment ce que voit le lecteur simulé.
 */
final class RoleplayFollowupBilansAssetTest extends TestCase
{
    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $p));
    }

    public function testBilansOffMeansNoBilanAction(): void
    {
        $row = ['tracked' => true, 'tutor_id' => 4, 'rp_bilan_next_due_at' => null, 'rp_bilan_overdue' => false];
        self::assertNull(RoleplayFollowupBoard::nextAction($row, '2026-11-14'));
        self::assertNotContains('retard', RoleplayFollowupBoard::filtersOf($row, '2026-11-14'));
        $on = ['tracked' => true, 'tutor_id' => 4, 'rp_bilan_next_due_at' => '2026-10-01', 'rp_bilan_overdue' => true];
        self::assertSame('Bilan', RoleplayFollowupBoard::nextAction($on, '2026-11-14')['label']);
        self::assertContains('retard', RoleplayFollowupBoard::filtersOf($on, '2026-11-14'));
    }

    public function testControllerCutsBilansAndExplainsWhatIsMissing(): void
    {
        $c = self::read('app/Controllers/Admin/Organization/RoleplayFollowupAdminController.php');
        self::assertStringContainsString('$bilansOn = !empty($cadence[\'enabled\']);', $c);
        self::assertStringContainsString('$rpBilanNextDueAt = $bilansOn ? RoleplayBilanPolicy::nextReviewDueAt(', $c);
        self::assertStringContainsString('\'rp_bilan_overdue\' => $bilansOn && RoleplayBilanPolicy::isOverdue(', $c);
        self::assertStringContainsString('\'missing\' => $missing,', $c);
        self::assertStringContainsString('\'evaluated\' => $snapshot !== [],', $c);
        self::assertStringContainsString('\'rpBilansEnabled\' => $bilansOn,', $c);
        self::assertStringContainsString('public function toggleBilans(', $c);
        self::assertStringContainsString('RoleplayFollowupSettings::saveForTenant($tenantId, [\'bilans\' => [\'enabled\' => $enable]]', $c);
        // L'action vérifie le jeton CSRF avant d'écrire.
        self::assertMatchesRegularExpression('/function toggleBilans.*?Csrf::validate.*?saveForTenant/s', $c);
        self::assertStringContainsString("/back-office/roleplay-followup/bilans', [RoleplayFollowupAdminController::class, 'toggleBilans'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", self::read('routes/web.php'));
    }

    public function testDeskShowsTheSwitchTheReasonAndHidesBilansWhenOff(): void
    {
        $v = self::read('views/admin/organization/roleplay_followup.php');
        self::assertStringContainsString('url(\'back-office/roleplay-followup/bilans\')', $v);
        self::assertStringContainsString('Désactiver les bilans', $v);
        self::assertStringContainsString('Réactiver les bilans', $v);
        self::assertStringContainsString('Jamais évalué : ouvrez le dossier et enregistrez-le', $v);
        self::assertStringContainsString('Manque : ', $v);
        self::assertStringContainsString("'bilansOn' => \$rpBilansEnabled,", $v);
        self::assertSame(3, substr_count($v, 'data-sheet-bilan'), 'Le bouton et les deux lignes de bilan de la fenêtre se cachent ensemble.');
        $js = self::read('public/assets/js/roleplay-followup-board.js');
        self::assertStringContainsString("sheet.querySelectorAll('[data-sheet-bilan]')", $js);
        self::assertStringContainsString('d.bilansOn === false', $js);
        self::assertStringContainsString("setFact('missing'", $js);
    }

    public function testDeskExplainsAnInactiveFollowupAndCanActivateIt(): void
    {
        $v = self::read('views/admin/organization/roleplay_followup.php');
        self::assertStringContainsString('Le suivi n’est pas activé.', $v);
        self::assertStringContainsString('Activer le suivi', $v);
        self::assertStringContainsString("'Non évalué'", $v, 'Un dossier jamais évalué n’est pas « À compléter ».');
        self::assertStringContainsString('toggleFollowup', self::read('routes/web.php'));
        self::assertMatchesRegularExpression('/function toggleFollowup.*?Csrf::validate.*?saveForTenant\(\$tenantId, \[.enabled. => \$enable\]/s', self::read('app/Controllers/Admin/Organization/RoleplayFollowupAdminController.php'));
    }

    public function testVisibilityPreviewAppliesTheStaffTiers(): void
    {
        $p = self::read('app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString("\$canStaffView = \$previewAs !== 'member';", $p);
        self::assertStringContainsString("\$canStaffEdit = \$previewAs === 'command';", $p);
        self::assertStringContainsString("\$canSensitive = \$previewAs === 'command';", $p);
        self::assertStringContainsString("\$isReportModerator = \$previewAs === 'command';", $p);
        // Les droits simulés sont posés avant tout ce qui s'en sert (notes de commandement, dossier roleplay, identité privée).
        $setAt = strpos($p, "\$canStaffView = \$previewAs !== 'member';");
        $useAt = strpos($p, '$rpDossierNeedsAttention = ($isSelf || $canStaffView');
        self::assertNotFalse($setAt);
        self::assertNotFalse($useAt);
        self::assertTrue($setAt < $useAt);
    }
}
