<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelPhaseRulesAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCatalogSeedProbesAndBootstrap(): void
    {
        $root = $this->root();
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $probes = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateProbes.php');
        $boot = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/PersonnelPhaseRepository.php');

        self::assertStringContainsString('ROLEPLAY_FOLLOWUP_CADENCE_V1', $catalog);
        self::assertStringContainsString('PERSONNEL_PHASE_RULES_V1', $catalog);
        self::assertStringContainsString('ROLEPLAY_FOLLOWUP_CADENCE_V1', $seed);
        self::assertStringContainsString('PERSONNEL_PHASE_RULES_V1', $seed);
        self::assertStringContainsString('Configurer mon parcours RH', $catalog);
        self::assertStringContainsString('isRoleplayFollowupEnabled', $probes);
        self::assertStringContainsString('hasRoleplayCadenceReviewed', $probes);
        self::assertStringContainsString('hasPersonnelPhaseRuleSets', $probes);
        self::assertStringContainsString('seedDefaultPhases($tenantId)', $boot);
        self::assertStringContainsString("'label' => 'Intégration'", $repo);
        self::assertStringNotContainsString('createRuleSet', $repo);
    }

    public function testEmptyRuleSetNeverEligibleAndNoRetrogradation(): void
    {
        $engine = (string) file_get_contents($this->root() . '/app/Services/Personnel/PhaseRules/PhaseRuleEngine.php');
        $svc = (string) file_get_contents($this->root() . '/app/Services/Personnel/PhaseRules/PhaseTransitionService.php');
        self::assertStringContainsString("\$items === []", $engine);
        self::assertStringContainsString("'eligible' => false", $engine);
        self::assertStringContainsString('isForwardTransition', $svc);
        self::assertStringContainsString('Le parcours n’autorise pas un retour en arrière.', $svc);
        self::assertStringContainsString('FOR UPDATE', $svc);
        self::assertStringContainsString('recordAutoError', $svc);
    }

    public function testEffectifsAndFileSurfaces(): void
    {
        $root = $this->root();
        $roster = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roster.php');
        $file = (string) file_get_contents($root . '/views/personnel/file.php');
        $suivi = (string) file_get_contents($root . '/views/partials/personnel/file_suivi_complet.php');
        $shell = (string) file_get_contents($root . '/views/admin/effectifs_workspace/shell.php');
        $extras = (string) file_get_contents($root . '/app/Support/EffectifsWorkspaceShellExtras.php');
        $svc = (string) file_get_contents($root . '/app/Services/Personnel/PhaseRules/PhaseTransitionService.php');

        self::assertStringContainsString('VALIDATION REQUISE', $svc);
        self::assertStringContainsString('phaseBadgesByUserId', $roster);
        self::assertStringContainsString('parcours-rh', $suivi);
        self::assertStringContainsString('phase_mode', $suivi);
        self::assertStringContainsString('override', $suivi);
        self::assertStringContainsString('file_suivi_complet.php', $file);
        self::assertStringContainsString('phaseGateCount', $shell);
        self::assertStringContainsString('countPendingGates', $extras);
        self::assertStringContainsString('countOpenAutoErrors', $extras);
    }
}
