<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\ElevationApprovalService;
use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 1 : les écritures et le renseignement protégés derrière un droit, comme leurs écrans. */
final class SecurityAuditLot1Test extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** JNET ne montre le renseignement SSE qu'aux deux portes du portail SSE : droit d'entrer, puis confidentialité. */
    public function testJnetKeepsSseIntelligenceBehindTheSseGates(): void
    {
        $s = self::src('app/Services/Jnet/JnetDashboardService.php');
        self::assertStringContainsString('$sse->canEnterWithoutCode() && $sse->hasAcceptedConfidentiality()', $s);
        // Chaque lecture de données SSE passe par la porte.
        self::assertStringContainsString('if (!$this->mayReadIntelligence()) {', $s);
        self::assertStringContainsString('$open ? count($this->fieldNotes->listForTenant', $s);
        self::assertStringContainsString('$open ? count($this->sseCases->listForTenant', $s);
        self::assertStringContainsString('$open ? count($this->interestCases->listForSession', $s);
        self::assertStringContainsString('$intelOpen ? $this->intelEvents->listForTenant', $s);
        self::assertStringContainsString('$intelOpen ? $this->fieldNotes->listForTenant', $s);
        // Un échec de vérification ferme la porte.
        self::assertStringContainsString("\$this->memo['intel'] = false;", $s);
        self::assertStringContainsString('intelLocked', self::src('views/jnet/targets.php'));
        self::assertStringContainsString('intelLocked', self::src('views/jnet/exploitation.php'));
    }

    /** La remise à zéro du roleplay est une écriture : POST avec jeton, jamais un lien. */
    public function testRoleplayResetIsAPostWithCsrf(): void
    {
        $routes = self::src('routes/web.php');
        self::assertStringContainsString("post('/back-office/atak/roleplay/reset'", $routes);
        self::assertStringNotContainsString("get('/back-office/atak/roleplay/reset'", $routes);
        $ctrl = self::src('app/Controllers/Admin/AdminAtakRoleplayController.php');
        $reset = substr($ctrl, (int) strpos($ctrl, 'public function reset('));
        self::assertStringContainsString('Csrf::validate', substr($reset, 0, 900));
        foreach (['views/admin/atak/roleplay.php', 'views/admin/atak/roleplay_enhanced.php'] as $view) {
            $v = self::src($view);
            self::assertStringContainsString('formaction="<?= $h($roleplayResetUrl) ?>" formmethod="post"', $v, $view);
            self::assertStringNotContainsString('window.location.href', substr($v, (int) strpos($v, 'roleplayResetUrl')), $view);
        }
    }

    /** Carte tactique : écrire exige l'éditeur de la carte et le jeton ; les brouillons ne sont lus que par lui. */
    public function testTacticalMarkersWriteNeedsEditorRightAndCsrf(): void
    {
        $api = self::src('app/Controllers/Api/AthenaTacticalApiController.php');
        foreach (['store', 'update', 'delete'] as $method) {
            $body = substr($api, (int) strpos($api, 'public function ' . $method . '('));
            self::assertStringContainsString('$this->writeGuard($request)', substr($body, 0, 260), $method);
        }
        self::assertStringContainsString("Csrf::validate(\$request->header('X-CSRF-Token'))", $api);
        self::assertStringContainsString("\$gate->allows('admin.system') || \$gate->allows('admin.organization') || \$gate->allows('admin.access')", $api);
        self::assertStringContainsString('$this->isWeb() && $this->canEdit()', $api);
        // Le constructeur reste à un paramètre facultatif (résolution par le routeur).
        self::assertSame(1, (new \ReflectionClass(\App\Controllers\Api\AthenaTacticalApiController::class))->getConstructor()?->getNumberOfParameters());
        $js = self::src('public/assets/js/athena-tactical-editor.js');
        self::assertSame(3, substr_count($js, "'X-CSRF-Token':root.dataset.csrf||''"));
        self::assertStringContainsString('data-csrf=', self::src('views/admin/athena_tactical_map/index.php'));
    }

    /** Une élévation qui accorde un rôle ou des droits n'est acceptée que par un responsable des accès. */
    public function testElevationThatGrantsAccessNeedsAnAccessManager(): void
    {
        self::assertFalse(ElevationApprovalService::proposalGrantsAccess([]));
        self::assertFalse(ElevationApprovalService::proposalGrantsAccess(['grade_id' => 4, 'job_role_id' => 7, 'unit_id' => 2, 'permission_ids' => [], 'role_id' => 0]));
        self::assertTrue(ElevationApprovalService::proposalGrantsAccess(['role_id' => 3]));
        self::assertTrue(ElevationApprovalService::proposalGrantsAccess(['permission_ids' => [12]]));
        self::assertFalse(ElevationApprovalService::proposalGrantsAccess(['permission_ids' => [0, '']]));
        $ctrl = self::src('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString('ElevationApprovalService::proposalGrantsAccess($proposal) && !EffectifsLmsAccess::canManageRoles($gate)', $ctrl);
    }

    /** La configuration de départ d'une communauté est réservée à ses responsables. */
    public function testCommunitySetupNeedsAManager(): void
    {
        $c = self::src('app/Controllers/Web/CommunityController.php');
        self::assertSame(2, substr_count($c, '!$this->maySetupCommunity()'));
        self::assertStringContainsString("\$gate->allows('admin.organization') || \$gate->allows('admin.access')", $c);
        self::assertStringContainsString('DateTimeZone::listIdentifiers()', $c);
    }
}
