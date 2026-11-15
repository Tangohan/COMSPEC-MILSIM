<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\Dossier\DossierHistoryPresenter;
use App\Services\Effectifs\Dossier\DossierStore;
use App\Services\Effectifs\Dossier\MemberDossierStepActions;
use App\Services\Effectifs\Dossier\MemberDossierTimeline;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\DossierFixture as F;
use Tests\Support\InMemoryDossierPorts;

/**
 * Gestes d'un responsable : chacun délègue au service d'origine quand il existe, refuse ce qui contournerait une règle
 * métier, n'agit que sur le dossier désigné et laisse une trace (historique et audit).
 */
final class MemberDossierStepActionsTest extends TestCase
{
    private const ACTOR = 4;
    private const MEMBER = 7;

    private PDO $pdo;
    private DossierStore $store;
    private InMemoryDossierPorts $ports;
    private MemberDossierStepActions $actions;
    private MemberDossierTimeline $timeline;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = F::pdo();
        foreach ([4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Lt Vasseur', 8 => 'Cal Autre'] as $id => $name) {
            F::user($this->pdo, $id, $name);
        }
        F::richMember($this->pdo, 7);
        $this->store = new DossierStore($this->pdo);
        $this->timeline = new MemberDossierTimeline($this->pdo, $this->store, null, static fn (int $t, array $ids): array => array_intersect_key([4 => 'Cpt Delorme', 7 => 'Sgt Marchand', 9 => 'Lt Vasseur'], array_flip($ids)));
        $this->ports = new InMemoryDossierPorts($this->pdo);
        $this->ports->tenantUsers = [4, 7, 9];
        $this->actions = new MemberDossierStepActions($this->timeline, $this->store, $this->ports);
    }

    /** @return array<string, mixed> */
    private function step(string $key, int $user = self::MEMBER): array
    {
        foreach ($this->timeline->forUser(F::TENANT, $user)['steps'] as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }
        self::fail('étape absente : ' . $key);
    }

    private function eventCount(string $action): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM member_dossier_step_events WHERE action = ?');
        $st->execute([$action]);

        return (int) $st->fetchColumn();
    }

    public function testPostponeAnIntegrationStepGoesThroughTheIntegrationPortWithAReason(): void
    {
        $day = date('Y-m-d', strtotime('+10 days'));
        $r = $this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', $day, 'Membre en déplacement');
        self::assertTrue($r['ok'], $r['message']);
        self::assertSame($day, $this->step('integration_step:4')['due_on']);
        self::assertSame('upcoming', $this->step('integration_step:4')['status'], 'plus en retard après le report');
        self::assertStringContainsString('step_postponed', $this->ports->integrationEvents[0]);
        self::assertSame(1, $this->eventCount('postpone'));
        self::assertSame('postpone', $this->ports->audits[0]['action']);
    }

    public function testPostponeRequiresAReasonAFutureDateAndAValidDate(): void
    {
        $future = date('Y-m-d', strtotime('+3 days'));
        self::assertFalse($this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', $future, '')['ok']);
        self::assertFalse($this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', date('Y-m-d', strtotime('-1 day')), 'Motif valable')['ok']);
        self::assertFalse($this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', '2026-13-45', 'Motif valable')['ok']);
        self::assertSame(0, $this->eventCount('postpone'));
    }

    public function testPostponeOnAStepOwnedByAnotherServiceStoresAnOverlayAndLeavesTheSourceUntouched(): void
    {
        $day = date('Y-m-d', strtotime('+20 days'));
        $before = $this->pdo->query('SELECT status, expires_at FROM training_enrollments WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $r = $this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'enrollment:1', $day, 'Planning chargé');
        self::assertTrue($r['ok'], $r['message']);
        $s = $this->step('enrollment:1');
        self::assertSame($day, $s['due_on']);
        self::assertTrue($s['overridden']['due']);
        self::assertSame(substr((string) $before['expires_at'], 0, 10), $s['original_due_on']);
        self::assertSame($before, $this->pdo->query('SELECT status, expires_at FROM training_enrollments WHERE id = 1')->fetch(PDO::FETCH_ASSOC), 'la formation elle-même n’est pas modifiée');
        $row = $this->store->findOverlay(F::TENANT, self::MEMBER, 'enrollment', '1');
        self::assertNotNull($row);
        // Un second report met à jour la même ligne.
        $this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'enrollment:1', date('Y-m-d', strtotime('+30 days')), 'Encore décalé');
        self::assertCount(1, $this->store->rowsForUsers(F::TENANT, [self::MEMBER])[self::MEMBER] ?? []);
    }

    public function testReassignDesignatesATenantUserAndRefusesAStranger(): void
    {
        $r = $this->actions->reassign(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:8', 9, 'Disponible cette semaine');
        self::assertTrue($r['ok'], $r['message']);
        self::assertSame('Lt Vasseur', $this->step('integration_step:8')['owner_label']);
        $bad = $this->actions->reassign(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:8', 999);
        self::assertFalse($bad['ok']);
        self::assertSame('Lt Vasseur', $this->step('integration_step:8')['owner_label']);
        $overlay = $this->actions->reassign(F::TENANT, self::ACTOR, self::MEMBER, 'elevation:1', 9);
        self::assertTrue($overlay['ok']);
        self::assertSame('Lt Vasseur', $this->step('elevation:1')['owner_label']);
        self::assertTrue($this->step('elevation:1')['overridden']['owner']);
    }

    public function testSkipNeedsAReasonAndOnlyAppliesToOptionalSteps(): void
    {
        self::assertFalse($this->actions->skip(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:6', '')['ok'], 'motif obligatoire');
        self::assertFalse($this->actions->skip(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', 'Pas utile')['ok'], 'étape obligatoire');
        $ok = $this->actions->skip(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:6', 'Hors de sa spécialité');
        self::assertTrue($ok['ok'], $ok['message']);
        self::assertSame('skipped', $this->step('integration_step:6')['status']);
        self::assertContains('unskip', $this->step('integration_step:6')['actions']);
        self::assertTrue($this->actions->unskip(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:6')['ok']);
        self::assertSame('upcoming', $this->step('integration_step:6')['status']);
        self::assertSame(2, $this->eventCount('skip') + $this->eventCount('unskip'));
    }

    public function testCompleteOnAMemberOwnedIntegrationStepNeedsAReasonThenForcesIt(): void
    {
        $no = $this->actions->complete(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4');
        self::assertFalse($no['ok']);
        self::assertSame([], $this->ports->completions);
        $yes = $this->actions->complete(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', 'Règlement lu en présence de l’instructeur');
        self::assertTrue($yes['ok'], $yes['message']);
        self::assertTrue($this->ports->completions[0]['force']);
        self::assertSame('done', $this->step('integration_step:4')['status']);
        // « À refaire » rouvre l'étape.
        self::assertTrue($this->actions->reopen(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', 'Lecture non confirmée')['ok']);
        self::assertSame('overdue', $this->step('integration_step:4')['status']);
    }

    public function testCompleteOnAStaffOwnedIntegrationStepIsNotForced(): void
    {
        $this->pdo->exec("UPDATE member_integration_steps SET step_type = 'manual_validation', responsible_kind = 'rh' WHERE id = 8");
        $r = $this->actions->complete(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:8');
        self::assertTrue($r['ok'], $r['message']);
        self::assertFalse($this->ports->completions[0]['force']);
    }

    public function testNonEditableStepsCannotBeCompletedOrSkippedHere(): void
    {
        foreach (['qualification:1', 'enrollment:1', 'campaign:1', 'enlistment_retro_member:1', 'elevation:1'] as $key) {
            foreach (['complete', 'skip'] as $geste) {
                $r = $geste === 'complete'
                    ? $this->actions->complete(F::TENANT, self::ACTOR, self::MEMBER, $key, 'Motif')
                    : $this->actions->skip(F::TENANT, self::ACTOR, self::MEMBER, $key, 'Motif');
                self::assertFalse($r['ok'], $key . ' ' . $geste);
                self::assertStringContainsString('pas permis', $r['message']);
            }
        }
        self::assertSame('in_training', $this->pdo->query('SELECT admin_status FROM personnel_qualifications WHERE id = 1')->fetchColumn(), 'la qualification n’est pas délivrée');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM data_campaign_members WHERE confirmed_at IS NOT NULL')->fetchColumn(), 'la fiche n’est pas confirmée à la place du membre');
        self::assertSame(0, $this->eventCount('complete'));
    }

    public function testAddAndRemoveACustomStep(): void
    {
        $due = date('Y-m-d', strtotime('+9 days'));
        $r = $this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', 'Signer la décharge de matériel', $due, 9);
        self::assertTrue($r['ok'], $r['message']);
        $key = 'custom:' . $r['id'];
        $s = $this->step($key);
        self::assertSame('documents', $s['track']);
        self::assertSame($due, $s['due_on']);
        self::assertSame('Lt Vasseur', $s['owner_label']);
        self::assertContains('remove', $s['actions']);
        self::assertTrue($this->actions->note(F::TENANT, self::ACTOR, self::MEMBER, $key, "Ligne 1\nLigne 2")['ok']);
        self::assertSame("Ligne 1\nLigne 2", $this->step($key)['note']);
        self::assertTrue($this->actions->complete(F::TENANT, self::ACTOR, self::MEMBER, $key)['ok']);
        self::assertSame('done', $this->step($key)['status']);
        self::assertFalse($this->actions->remove(F::TENANT, self::ACTOR, self::MEMBER, $key)['ok'], 'une étape terminée ne se retire pas');
        self::assertTrue($this->actions->reopen(F::TENANT, self::ACTOR, self::MEMBER, $key)['ok']);
        self::assertTrue($this->actions->remove(F::TENANT, self::ACTOR, self::MEMBER, $key, 'Ajoutée par erreur')['ok']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM member_dossier_steps')->fetchColumn());
        self::assertSame(1, $this->eventCount('add'));
        self::assertSame(1, $this->eventCount('remove'), 'l’historique garde la trace du retrait');
    }

    public function testAddCustomValidatesItsFields(): void
    {
        $future = date('Y-m-d', strtotime('+2 days'));
        self::assertFalse($this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', 'ab', $future, 0)['ok'], 'intitulé trop court');
        self::assertFalse($this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'inconnu', 'Titre valable', $future, 0)['ok'], 'parcours inconnu');
        self::assertFalse($this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', 'Titre valable', date('Y-m-d', strtotime('-2 days')), 0)['ok'], 'échéance passée');
        self::assertFalse($this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', 'Titre valable', $future, 999)['ok'], 'responsable étranger');
        self::assertTrue($this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', '<b>Titre</b> valable', '', 0)['ok']);
        self::assertSame('Titre valable', $this->step('custom:1')['title'], 'balises retirées');
        for ($i = 0; $i < MemberDossierStepActions::MAX_CUSTOM_PER_MEMBER; $i++) {
            $this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', 'Étape ' . $i, '', 0);
        }
        self::assertFalse($this->actions->addCustom(F::TENANT, self::ACTOR, self::MEMBER, 'documents', 'Une de trop', '', 0)['ok'], 'plafond par membre');
    }

    public function testAnActorNeverValidatesOrEditsTheirOwnDossier(): void
    {
        foreach ([
            $this->actions->complete(F::TENANT, self::MEMBER, self::MEMBER, 'integration_step:4', 'Je l’ai fait'),
            $this->actions->postpone(F::TENANT, self::MEMBER, self::MEMBER, 'integration_step:4', date('Y-m-d', strtotime('+9 days')), 'Pour éviter le retard'),
            $this->actions->skip(F::TENANT, self::MEMBER, self::MEMBER, 'integration_step:6', 'Inutile'),
            $this->actions->reassign(F::TENANT, self::MEMBER, self::MEMBER, 'integration_step:8', 7),
            $this->actions->addCustom(F::TENANT, self::MEMBER, self::MEMBER, 'documents', 'Étape pour moi', '', 0),
            $this->actions->remind(F::TENANT, self::MEMBER, self::MEMBER, 'integration_step:4'),
        ] as $r) {
            self::assertFalse($r['ok']);
        }
        self::assertSame('overdue', $this->step('integration_step:4')['status']);
        // Une note interne reste permise.
        self::assertTrue($this->actions->note(F::TENANT, self::MEMBER, self::MEMBER, 'integration_step:4', 'À voir avec le référent')['ok']);
    }

    public function testAStepOfAnotherMembersDossierIsNotFoundAndNothingIsWritten(): void
    {
        $other = F::integration($this->pdo, 8);
        $foreign = F::integrationStep($this->pdo, $other, ['title' => 'Étape de quelqu’un d’autre', 'status' => 'pending']);
        $key = 'integration_step:' . $foreign;
        $before = $this->pdo->query('SELECT due_at FROM member_integration_steps WHERE id = ' . $foreign)->fetchColumn();
        $r = $this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, $key, date('Y-m-d', strtotime('+9 days')), 'Motif valable');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('introuvable', $r['message']);
        self::assertFalse($this->actions->complete(F::TENANT, self::ACTOR, self::MEMBER, $key, 'Motif')['ok']);
        self::assertFalse($this->actions->note(F::TENANT, self::ACTOR, self::MEMBER, 'custom:999', 'Note')['ok']);
        self::assertSame($before, $this->pdo->query('SELECT due_at FROM member_integration_steps WHERE id = ' . $foreign)->fetchColumn());
        self::assertSame([], $this->ports->completions);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM member_dossier_step_events')->fetchColumn());
    }

    public function testAStepOfAnotherCommunityIsNotReachable(): void
    {
        $r = $this->actions->postpone(2, self::ACTOR, self::MEMBER, 'integration_step:4', date('Y-m-d', strtotime('+9 days')), 'Motif valable');
        self::assertFalse($r['ok']);
        self::assertSame('overdue', $this->step('integration_step:4')['status'], 'rien n’a changé');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM member_dossier_step_events')->fetchColumn());
    }

    public function testRemindSendsOneNotificationPerStepAndPerDay(): void
    {
        $now = time();
        $first = $this->actions->remind(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', $now);
        self::assertTrue($first['ok'], $first['message']);
        self::assertCount(1, $this->ports->notifications);
        self::assertSame('mon-integration', $this->ports->notifications[0]['path']);
        $again = $this->actions->remind(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', $now + 3600);
        self::assertFalse($again['ok']);
        self::assertStringContainsString('déjà été relancé', $again['message']);
        self::assertCount(1, $this->ports->notifications, 'pas de doublon');
        // Une autre étape peut l'être ; un jour plus tard, la première aussi.
        self::assertTrue($this->actions->remind(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:3', $now + 3600)['ok']);
        self::assertTrue($this->actions->remind(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', $now + 25 * 3600)['ok']);
    }

    public function testRemindOnACampaignStepDelegatesToTheCampaignService(): void
    {
        $r = $this->actions->remind(F::TENANT, self::ACTOR, self::MEMBER, 'campaign:1');
        self::assertTrue($r['ok'], $r['message']);
        self::assertSame([['campaign' => 1, 'user' => self::MEMBER]], $this->ports->campaignReminders);
        self::assertSame([], $this->ports->notifications);
    }

    public function testRemindFailsCleanlyWhenTheNotificationCannotBeDeposited(): void
    {
        $this->ports->notifyWorks = false;
        self::assertFalse($this->actions->remind(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4')['ok']);
        self::assertSame(0, $this->eventCount('remind'), 'une relance ratée n’est pas comptée');
    }

    public function testRemindManyIsCappedDedupedAndSkipsMembersWithNothingToDo(): void
    {
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $uid = 40 + $i;
            F::user($this->pdo, $uid, 'Sdt ' . $i);
            if ($i < 5) {
                $iid = F::integration($this->pdo, $uid);
                F::integrationStep($this->pdo, $iid, ['title' => 'Charte', 'status' => 'pending', 'due_at' => date('Y-m-d', strtotime('-3 days'))]);
            }
            $ids[] = $uid;
        }
        $ids[] = self::ACTOR;
        $ids[] = $ids[0];
        $r = $this->actions->remindMany(F::TENANT, self::ACTOR, $ids, 3);
        self::assertSame(3, $r['sent']);
        self::assertTrue($r['capped']);
        self::assertCount(3, $this->ports->notifications);
        // Second envoi : les trois premiers sont ignorés (déjà relancés), les deux suivants partent, le sixième n'a rien à faire.
        $r2 = $this->actions->remindMany(F::TENANT, self::ACTOR, $ids, 25);
        self::assertSame(2, $r2['sent']);
        self::assertSame(4, $r2['skipped']);
        self::assertCount(5, $this->ports->notifications);
        self::assertNotContains(self::ACTOR, array_column($this->ports->notifications, 'user'), 'on ne se relance pas soi-même');
    }

    public function testWithoutTheMigrationNoActionIsPossible(): void
    {
        $pdo = F::pdo(false);
        F::user($pdo, 7, 'Sgt Marchand');
        F::richMember($pdo, 7);
        $store = new DossierStore($pdo);
        $tl = new MemberDossierTimeline($pdo, $store, null, static fn (): array => []);
        $actions = new MemberDossierStepActions($tl, $store, new InMemoryDossierPorts($pdo));
        foreach ([
            $actions->complete(F::TENANT, self::ACTOR, 7, 'integration_step:4', 'Motif'),
            $actions->postpone(F::TENANT, self::ACTOR, 7, 'integration_step:4', date('Y-m-d', strtotime('+9 days')), 'Motif'),
            $actions->addCustom(F::TENANT, self::ACTOR, 7, 'documents', 'Titre valable', '', 0),
            $actions->note(F::TENANT, self::ACTOR, 7, 'integration_step:4', 'Note'),
        ] as $r) {
            self::assertFalse($r['ok']);
            self::assertStringContainsString('lecture seule', $r['message']);
        }
        $status = array_values(array_filter($tl->forUser(F::TENANT, 7)['steps'], static fn (array $s): bool => $s['key'] === 'integration_step:4'))[0]['status'];
        self::assertSame('overdue', $status);
    }

    public function testEveryChangeIsInTheHistoryWithBeforeAfterAndReason(): void
    {
        $day = date('Y-m-d', strtotime('+10 days'));
        $this->actions->postpone(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:4', $day, 'Membre en déplacement');
        $this->actions->reassign(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:8', 9);
        $this->actions->skip(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:6', 'Hors spécialité');
        $events = DossierHistoryPresenter::present(F::TENANT, $this->store->events(F::TENANT, self::MEMBER), static fn (int $t, array $ids): array => [4 => 'Cpt Delorme', 9 => 'Lt Vasseur']);
        self::assertCount(3, $events);
        self::assertSame('skip', $events[0]['action'], 'du plus récent au plus ancien');
        self::assertSame('Cpt Delorme', $events[0]['actor']);
        self::assertSame('à faire → ignorée', $events[0]['change']);
        self::assertSame('Hors spécialité', $events[0]['reason']);
        self::assertStringContainsString('Responsable par défaut → Lt Vasseur', $events[1]['change']);
        self::assertStringContainsString(date('d/m/Y', strtotime($day)), $events[2]['change']);
        self::assertSame('Membre en déplacement', $events[2]['reason']);
        self::assertSame(3, $this->store->eventCount(F::TENANT, self::MEMBER));
        self::assertSame(0, $this->store->eventCount(F::TENANT, 8));
        self::assertCount(3, $this->ports->audits);
    }

    public function testNoteOnAnIntegrationStepGoesToTheIntegrationJournal(): void
    {
        self::assertTrue($this->actions->note(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:3', 'Le membre a demandé un délai')['ok']);
        self::assertStringContainsString('Le membre a demandé un délai', implode('|', $this->ports->integrationEvents));
        self::assertFalse($this->actions->note(F::TENANT, self::ACTOR, self::MEMBER, 'integration_step:3', '   ')['ok']);
    }
}
