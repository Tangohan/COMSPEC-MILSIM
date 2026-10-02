<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cooperation\CooperationProgress as P;
use PHPUnit\Framework\TestCase;

final class CooperationProgressTest extends TestCase
{
    /** @return array<string, mixed> */
    private function mission(string $status, ?string $phase, string $stage = 'opord_draft', array $extra = []): array
    {
        return array_merge(['id' => 1, 'status' => $status, 'cooperation_phase' => $phase, 'operational_stage' => $stage, 'created_by_tenant_id' => 10], $extra);
    }

    /** @return list<array<string, mixed>> */
    private function parts(array $others): array
    {
        $out = [['tenant_id' => 10, 'tenant_name' => 'Support', 'role' => 'lead', 'status' => 'active']];
        foreach ($others as $tid => $st) {
            $out[] = ['tenant_id' => $tid, 'tenant_name' => 'Unité ' . $tid, 'role' => 'partner', 'status' => $st];
        }

        return $out;
    }

    public function testDraftIsStepOneAndThePilotMustInvite(): void
    {
        $p = P::compute($this->mission('draft', 'draft'), $this->parts([]), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertSame(1, $p['current']);
        self::assertSame('Étape 1 sur 5 — Cadrage', $p['heading']);
        self::assertSame('Invitations & négociation', $p['next_label']);
        self::assertSame('Inviter une première unité', $p['next_action']['label']);
        self::assertTrue($p['next_action']['actor_is_viewer']);
        self::assertSame('neutral', $p['state']['variant']);
    }

    public function testPendingInvitationsAskToRemindAndNameTheUnitsAwaited(): void
    {
        $p = P::compute($this->mission('pending', 'proposed'), $this->parts([20 => 'active', 30 => 'invited', 40 => 'declined']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertSame(2, $p['current']);
        self::assertSame('Relancer', $p['next_action']['label']);
        self::assertSame('1 unité sur 3 a accepté — en attente de Unité 30.', $p['next_action']['description']);
        self::assertSame('Unité 30', $p['next_action']['actor']);
        self::assertFalse($p['next_action']['actor_is_viewer']);
        self::assertNotSame('', $p['steps'][1]['blocked_reason']);
        self::assertTrue($p['steps'][0]['done']);
    }

    public function testOnceEveryoneAnsweredThePilotCanLaunch(): void
    {
        $p = P::compute($this->mission('pending', 'proposed'), $this->parts([20 => 'active', 30 => 'declined']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertSame('Lancer la coopération', $p['next_action']['label']);
        $partner = P::compute($this->mission('pending', 'proposed'), $this->parts([20 => 'active', 30 => 'declined']), ['viewer_tenant_id' => 20]);
        self::assertSame('En attente du lancement', $partner['next_action']['label']);
        self::assertSame('Support', $partner['next_action']['actor']);
    }

    public function testInvitedViewerMustAnswer(): void
    {
        $p = P::compute($this->mission('pending', 'proposed'), $this->parts([20 => 'invited']), ['viewer_tenant_id' => 20, 'urls' => ['invitation' => '/x#invitation']]);
        self::assertSame('Répondre à l’invitation', $p['next_action']['label']);
        self::assertTrue(P::actionRequiredForViewer($p['next_action']));
        self::assertSame('/x#invitation', $p['next_action']['href']);
    }

    public function testPreparationAndExecutionFollowTheOperationalStage(): void
    {
        $prep = P::compute($this->mission('active', 'preparing'), $this->parts([20 => 'active']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertSame(3, $prep['current']);
        self::assertSame('Rédiger l’ordre d’opération', $prep['next_action']['label']);
        self::assertSame('info', $prep['state']['variant']);
        $val = P::compute($this->mission('active', 'preparing', 'command_validation', ['opord_text' => 'OPORD']), $this->parts([20 => 'active']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertSame('Passer en exécution', $val['next_action']['label']);
        $exe = P::compute($this->mission('active', 'active', 'execution'), $this->parts([20 => 'active']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertSame(4, $exe['current']);
        self::assertSame('4/5', $exe['short']);
        self::assertSame('Ajouter un point de situation', $exe['next_action']['label']);
    }

    public function testMissingConsentComesFirstForAnEngagedPartner(): void
    {
        $p = P::compute($this->mission('active', 'preparing'), $this->parts([20 => 'active']), ['viewer_tenant_id' => 20, 'consent_done' => false]);
        self::assertSame('Valider votre autorisation de partage', $p['next_action']['label']);
    }

    public function testClosureWaitsForTheViewersRex(): void
    {
        $p = P::compute($this->mission('archived', 'closed', 'closed_aar'), $this->parts([20 => 'active']), ['viewer_tenant_id' => 20, 'rex_done' => false]);
        self::assertSame(5, $p['current']);
        self::assertTrue($p['steps'][4]['active']);
        self::assertSame('Rédiger votre retour d’expérience', $p['next_action']['label']);
        $done = P::compute($this->mission('archived', 'closed', 'closed_aar'), $this->parts([20 => 'active']), ['viewer_tenant_id' => 20, 'rex_done' => true]);
        self::assertNull($done['next_action']);
        self::assertTrue($done['steps'][4]['done']);
    }

    public function testCancelledAndSuspendedStates(): void
    {
        $c = P::compute($this->mission('archived', 'cancelled', 'opord_draft', ['closure_motive' => 'Exercice reporté']), $this->parts([20 => 'invited']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertTrue($c['cancelled']);
        self::assertSame('danger', $c['state']['variant']);
        self::assertStringContainsString('Exercice reporté', $c['steps'][1]['blocked_reason']);
        self::assertNull($c['next_action']);
        $s = P::compute($this->mission('active', 'suspended', 'execution'), $this->parts([20 => 'active']), ['viewer_tenant_id' => 10, 'can_pilot' => true]);
        self::assertTrue($s['suspended']);
        self::assertSame('Reprendre la coopération', $s['next_action']['label']);
        self::assertSame('warning', $s['state']['variant']);
    }

    public function testLegacyRowsWithoutPhaseColumnStillMap(): void
    {
        self::assertSame(2, P::currentStep(['status' => 'pending']));
        self::assertSame(5, P::currentStep(['status' => 'archived']));
        self::assertSame('Proposition envoyée', P::stateBadge(['status' => 'pending'])['label']);
    }
}
