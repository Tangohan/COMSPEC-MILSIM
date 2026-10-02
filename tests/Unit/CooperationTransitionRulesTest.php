<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cooperation\CooperationTransitionRules as R;
use PHPUnit\Framework\TestCase;

final class CooperationTransitionRulesTest extends TestCase
{
    private const LEAD = 10;

    /** @return array<string, mixed> */
    private function mission(string $status, ?string $phase = null): array
    {
        return ['id' => 1, 'status' => $status, 'cooperation_phase' => $phase, 'created_by_tenant_id' => self::LEAD];
    }

    /** @return array<string, mixed> */
    private function part(int $tid, string $status, string $role = 'partner'): array
    {
        return ['tenant_id' => $tid, 'tenant_name' => 'Unité ' . $tid, 'role' => $role, 'status' => $status];
    }

    /** @return list<array<string, mixed>> */
    private function withLead(array ...$others): array
    {
        return array_merge([$this->part(self::LEAD, 'active', 'lead')], $others);
    }

    public function testInvitationStaysOpenAfterFirstInvitationAndBecomesReinforcementOnceActive(): void
    {
        self::assertTrue(R::invitation($this->mission('draft', 'draft'))['allowed']);
        // La première invitation fait passer en « pending » : il faut pouvoir continuer d’inviter.
        $pending = R::invitation($this->mission('pending', 'proposed'));
        self::assertTrue($pending['allowed']);
        self::assertFalse($pending['reinforcement']);
        $active = R::invitation($this->mission('active', 'active'));
        self::assertTrue($active['allowed']);
        self::assertTrue($active['reinforcement']);
        self::assertFalse(R::invitation($this->mission('archived', 'closed'))['allowed']);
        self::assertFalse(R::invitation($this->mission('archived', 'cancelled'))['allowed']);
    }

    public function testCannotInviteSelfOrAlreadyEngagedButCanReinviteDeclinedOrRemoved(): void
    {
        $parts = $this->withLead($this->part(20, 'invited'), $this->part(30, 'active'), $this->part(40, 'declined'), $this->part(50, 'left'));
        self::assertSame('invalid_tenant', R::canInviteTenant(self::LEAD, self::LEAD, $parts)['reason']);
        self::assertSame('already_invited', R::canInviteTenant(20, self::LEAD, $parts)['reason']);
        self::assertSame('already_engaged', R::canInviteTenant(30, self::LEAD, $parts)['reason']);
        self::assertTrue(R::canInviteTenant(40, self::LEAD, $parts)['allowed']);
        self::assertTrue(R::canInviteTenant(50, self::LEAD, $parts)['allowed']);
        self::assertTrue(R::canInviteTenant(60, self::LEAD, $parts)['allowed']);
    }

    public function testPickerExcludesOwnUnitAndDisablesEngagedOnes(): void
    {
        $tenants = [['id' => self::LEAD, 'name' => 'Moi'], ['id' => 20, 'name' => 'Bravo'], ['id' => 30, 'name' => 'Alpha'], ['id' => 40, 'name' => 'Charlie']];
        $picker = R::invitablePicker($tenants, $this->withLead($this->part(20, 'invited'), $this->part(40, 'declined')), self::LEAD);
        $byId = array_column($picker, null, 'id');
        self::assertArrayNotHasKey(self::LEAD, $byId);
        self::assertFalse($byId[20]['selectable']);
        self::assertSame('Invitation en attente', $byId[20]['state_label']);
        self::assertTrue($byId[30]['selectable']);
        self::assertTrue($byId[40]['selectable']);
        // Les unités sélectionnables viennent en premier.
        self::assertFalse(end($picker)['selectable']);
    }

    public function testADeclineNoLongerBlocksLaunch(): void
    {
        $m = $this->mission('pending', 'proposed');
        $r = R::launchReadiness($m, $this->withLead($this->part(20, 'active'), $this->part(30, 'declined'), $this->part(40, 'left')));
        self::assertTrue($r['ok']);
        self::assertSame(['Unité 20'], $r['accepted']);
        self::assertSame(['Unité 30', 'Unité 40'], $r['ignored']);
    }

    public function testLaunchNeedsOneAcceptedPartnerAndNoPendingInvitation(): void
    {
        $m = $this->mission('pending', 'proposed');
        self::assertSame('no_partner_accepted', R::launchReadiness($m, $this->withLead($this->part(30, 'declined')))['reason']);
        self::assertSame('no_partner_accepted', R::launchReadiness($m, $this->withLead())['reason']);
        $pending = R::launchReadiness($m, $this->withLead($this->part(20, 'active'), $this->part(30, 'invited')));
        self::assertSame('invitations_pending', $pending['reason']);
        self::assertSame(['Unité 30'], $pending['pending']);
        self::assertSame('counter_proposal_pending', R::launchReadiness($m, $this->withLead($this->part(20, 'active')), true)['reason']);
        self::assertSame('already_active', R::launchReadiness($this->mission('active', 'active'), $this->withLead($this->part(20, 'active')))['reason']);
        self::assertSame('mission_terminal', R::launchReadiness($this->mission('archived', 'cancelled'), $this->withLead($this->part(20, 'active')))['reason']);
    }

    public function testOnlyAPendingInvitationCanBeAnswered(): void
    {
        $m = $this->mission('pending', 'proposed');
        $parts = $this->withLead($this->part(20, 'invited'), $this->part(30, 'declined'), $this->part(40, 'left'));
        self::assertTrue(R::canRespondToInvitation($m, 20, $parts)['allowed']);
        self::assertSame('no_pending_invitation', R::canRespondToInvitation($m, 30, $parts)['reason']);
        // Une unité retirée ne peut pas se réintégrer seule.
        self::assertSame('no_pending_invitation', R::canRespondToInvitation($m, 40, $parts)['reason']);
        self::assertSame('not_invited', R::canRespondToInvitation($m, 99, $parts)['reason']);
        self::assertSame('not_invited', R::canRespondToInvitation($m, self::LEAD, $parts)['reason']);
        self::assertSame('mission_terminal', R::canRespondToInvitation($this->mission('archived', 'cancelled'), 20, $parts)['reason']);
    }

    public function testRemovingAPartner(): void
    {
        $m = $this->mission('active', 'active');
        $parts = $this->withLead($this->part(20, 'invited'), $this->part(30, 'active', 'co_lead'), $this->part(40, 'declined'));
        $r = R::canRemovePartner($m, 20, self::LEAD, $parts);
        self::assertTrue($r['allowed']);
        self::assertSame('invited', $r['was']);
        self::assertTrue(R::canRemovePartner($m, 30, self::LEAD, $parts)['allowed']);
        self::assertSame('cannot_remove_lead', R::canRemovePartner($m, self::LEAD, 30, $parts)['reason']);
        self::assertSame('cannot_remove_self', R::canRemovePartner($m, 30, 30, $parts)['reason']);
        self::assertSame('already_out', R::canRemovePartner($m, 40, self::LEAD, $parts)['reason']);
        self::assertSame('not_participant', R::canRemovePartner($m, 99, self::LEAD, $parts)['reason']);
        self::assertSame('mission_terminal', R::canRemovePartner($this->mission('archived', 'closed'), 20, self::LEAD, $parts)['reason']);
    }

    public function testCancellingAProposalRequiresAMotiveAndAnUnlaunchedCooperation(): void
    {
        self::assertTrue(R::canCancelProposal($this->mission('pending', 'proposed'), 'Exercice reporté')['allowed']);
        self::assertTrue(R::canCancelProposal($this->mission('draft', 'draft'), 'Doublon')['allowed']);
        self::assertSame('motive_required', R::canCancelProposal($this->mission('pending', 'proposed'), '  ')['reason']);
        self::assertSame('already_active', R::canCancelProposal($this->mission('active', 'active'), 'Trop tard')['reason']);
        self::assertSame('mission_terminal', R::canCancelProposal($this->mission('archived', 'cancelled'), 'Encore')['reason']);
    }

    public function testClosingIsForLaunchedOrDraftCooperationsOnly(): void
    {
        self::assertTrue(R::canClose($this->mission('active', 'active'))['allowed']);
        self::assertTrue(R::canClose($this->mission('draft', 'draft'))['allowed']);
        self::assertSame('use_cancel', R::canClose($this->mission('pending', 'proposed'))['reason']);
        self::assertSame('mission_terminal', R::canClose($this->mission('archived', 'closed'))['reason']);
    }

    public function testSuspendResumeAndConduct(): void
    {
        $active = $this->mission('active', 'preparing');
        self::assertSame('motive_required', R::canSuspend($active, '')['reason']);
        self::assertTrue(R::canSuspend($active, 'Incident serveur')['allowed']);
        self::assertSame('not_launched', R::canSuspend($this->mission('pending', 'proposed'), 'Report')['reason']);
        $suspended = $this->mission('active', 'suspended') + ['operational_stage' => 'execution'];
        self::assertSame('already_suspended', R::canSuspend($suspended, 'Encore')['reason']);
        $resume = R::canResume($suspended);
        self::assertTrue($resume['allowed']);
        self::assertSame('active', $resume['phase']);
        self::assertSame('preparing', R::canResume($this->mission('active', 'suspended') + ['operational_stage' => 'command_validation'])['phase']);
        self::assertSame('not_suspended', R::canResume($active)['reason']);
        self::assertSame('suspended', R::canConduct($suspended)['reason']);
        self::assertTrue(R::canConduct($active)['allowed']);
    }

    public function testReminderIsLimitedToOnePerDayAndToPendingInvitations(): void
    {
        $m = $this->mission('pending', 'proposed');
        $parts = $this->withLead($this->part(20, 'invited'), $this->part(30, 'active'));
        $now = strtotime('2026-10-02 12:00:00');
        self::assertTrue(R::canRemind($m, 20, $parts, null, $now)['allowed']);
        self::assertSame('reminder_too_soon', R::canRemind($m, 20, $parts, '2026-10-02 08:00:00', $now)['reason']);
        self::assertTrue(R::canRemind($m, 20, $parts, '2026-10-01 11:00:00', $now)['allowed']);
        self::assertSame('no_pending_invitation', R::canRemind($m, 30, $parts, null, $now)['reason']);
    }

    public function testEveryReasonHasAReadableMessage(): void
    {
        foreach (['mission_terminal', 'already_active', 'invalid_tenant', 'already_invited', 'already_engaged', 'not_invited',
            'no_pending_invitation', 'counter_proposal_pending', 'invitations_pending', 'no_partner_accepted', 'cannot_remove_lead',
            'cannot_remove_self', 'already_out', 'motive_required', 'use_cancel', 'not_launched', 'already_suspended',
            'not_suspended', 'suspended', 'reminder_too_soon'] as $reason) {
            self::assertNotSame(R::reasonLabel('unknown'), R::reasonLabel($reason), $reason);
        }
    }
}
