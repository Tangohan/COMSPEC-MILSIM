<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\DataCampaignAdminController;
use App\Middleware\AuthMiddleware;
use App\Middleware\NonDefaultTenantMiddleware;
use App\Middleware\OrganizationAdminMiddleware;
use App\Repositories\DataCampaignRepository;
use App\Services\Cron\CronSchedule;
use App\Services\Cron\Jobs\DataCampaignCronJob;
use App\Services\DataCampaign\DataCampaignRecordReader;
use App\Services\DataCampaign\DataCampaignService;
use App\Services\Effectifs\MemberChronology;
use App\Services\Email\EmailEvents;
use App\Services\Jnet\JnetMemberHub;
use App\Services\Notifications\ActivityHubPresentationService;
use App\Services\Personnel\PersonnelCorrectionRequestService;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignCalendar;
use App\Support\DataCampaignLoginPrompt;
use App\Support\DataCampaignPage;
use App\Support\DataCampaignProgress;
use App\Support\DataCampaignScope;
use App\Support\DataCampaignSnapshot;
use App\Support\JnetMemberPaths;
use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;
use Tests\Support\FakeDataCampaignReader;
use Tests\Support\InMemoryDataCampaignRepository;

/**
 * Campagne de fiabilisation des données : cycle de vie, avancement, périmètre, rappels uniques, instantané et détection
 * d'une donnée modifiée, « à corriger » dans le circuit de correction existant, aucune donnée SSE, droits, CSRF, migration
 * et vues qui importent leurs classes.
 */
final class DataCampaignTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const TENANT = 7;

    private InMemoryDataCampaignRepository $repo;

    private FakeDataCampaignReader $reader;

    /** @var list<array{string, int, int, int}> */
    private array $audits = [];

    /** @var list<array{user: int, type: string, payload: array<string, mixed>}> */
    private array $payloads = [];

    /** @var list<array{int, string}> */
    private array $notices = [];

    /** @var list<int> */
    private array $mails = [];

    /** @var list<array{array<string, string>, string}> */
    private array $corrections = [];

    private int $now = 0;

    private string $timezone = 'UTC';

    protected function setUp(): void
    {
        $this->audits = $this->notices = $this->mails = $this->corrections = $this->payloads = [];
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->now = (int) strtotime('2026-12-01 10:00:00 UTC');
        $this->repo = new InMemoryDataCampaignRepository();
        $this->reader = new FakeDataCampaignReader();
        $this->repo->unitRows = [1 => ['name' => 'Compagnie', 'parent_id' => 0], 2 => ['name' => 'Section A', 'parent_id' => 1], 3 => ['name' => 'Section B', 'parent_id' => 1], 9 => ['name' => 'Autre', 'parent_id' => 0]];
        $this->repo->rosterRows = [
            ['user_id' => 11, 'unit_id' => 2, 'display_name' => 'Alpha', 'callsign' => 'A1'],
            ['user_id' => 12, 'unit_id' => 3, 'display_name' => 'Bravo', 'callsign' => 'B1'],
            ['user_id' => 13, 'unit_id' => 9, 'display_name' => 'Charlie', 'callsign' => 'C1'],
            ['user_id' => 14, 'unit_id' => 0, 'display_name' => 'Delta', 'callsign' => 'D1'],
        ];
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    private function service(bool $correctionOk = true): DataCampaignService
    {
        return new DataCampaignService(
            $this->repo,
            $this->reader,
            function (string $action, int $tenantId, int $actorId, int $campaignId, array $details): void {
                $this->audits[] = [$action, $tenantId, $actorId, $campaignId];
            },
            function (int $tenantId, int $userId, string $type, array $payload): void {
                $this->notices[] = [$userId, $type];
                $this->payloads[] = ['user' => $userId, 'type' => $type, 'payload' => $payload];
            },
            function (int $tenantId, int $userId, array $campaign): bool {
                $this->mails[] = $userId;

                return true;
            },
            function (int $tenantId, int $userId, array $fields, string $note) use ($correctionOk): array {
                $this->corrections[] = [$fields, $note];

                return $correctionOk ? ['ok' => true, 'message' => 'Demande envoyée.', 'request_id' => 500 + count($this->corrections)] : ['ok' => false, 'message' => 'Une demande est déjà en attente.'];
            },
            fn (): int => $this->now,
            fn (int $tenantId): array => [900, 901]
        );
    }

    /** Campagne ouverte sur toute la communauté (4 membres). */
    private function openCampaign(DataCampaignService $s, array $input = []): int
    {
        $created = $s->createCampaign(self::TENANT, 1, $input + ['year' => 2026, 'due_on' => '2026-12-31', 'opens_on' => '2026-12-01']);
        self::assertTrue($created['ok']);
        $id = (int) $created['id'];
        self::assertTrue($s->openCampaign(self::TENANT, 1, $id)['ok']);

        return $id;
    }

    /** Le membre répond « c'est exact » à toutes les parties. */
    private function answerAll(DataCampaignService $s, int $campaignId, int $userId): void
    {
        foreach (DataCampaignBlocks::keys() as $key) {
            self::assertTrue($s->answer(self::TENANT, $userId, $campaignId, $key, 'ok')['ok'], $key);
        }
    }

    // ------------------------------------------------------------------ calendrier

    public function testCalendarDefaultsWindowAndAutoOpen(): void
    {
        self::assertSame('12-01', DataCampaignCalendar::DEFAULT_OPEN_MD);
        self::assertSame('12-31', DataCampaignCalendar::DEFAULT_CLOSE_MD);
        self::assertSame(['opens_on' => '2026-12-01', 'due_on' => '2026-12-31'], DataCampaignCalendar::window(2026, '12-01', '12-31'));
        self::assertSame('12-01', DataCampaignCalendar::normalizeMd('13-45', '12-01'));
        self::assertSame('02-28', DataCampaignCalendar::normalizeMd('2-28', '12-01'));
        self::assertSame('2027-02-28', DataCampaignCalendar::dateIn(2027, '02-29'));
        self::assertFalse(DataCampaignCalendar::shouldAutoOpen('2026-11-30', 2026, '12-01', '12-31'));
        self::assertTrue(DataCampaignCalendar::shouldAutoOpen('2026-12-01', 2026, '12-01', '12-31'));
        self::assertTrue(DataCampaignCalendar::shouldAutoOpen('2026-12-31', 2026, '12-01', '12-31'));
        self::assertFalse(DataCampaignCalendar::shouldAutoOpen('2027-01-01', 2027, '12-01', '12-31'));
        // Limite avant l'ouverture dans l'année : reportée à l'année suivante.
        self::assertSame('2027-01-15', DataCampaignCalendar::window(2026, '12-01', '01-15')['due_on']);
        self::assertSame([14, 3, 0], DataCampaignCalendar::normalizeReminderDays('nonsense'));
        self::assertSame([14, 3], DataCampaignCalendar::normalizeReminderDays('3, 14, 3, 900'));
    }

    public function testRemindersAreDueOnTheirDateOnlyOnceAndNeverBeforeTheOpening(): void
    {
        $days = [14, 3, 0];
        self::assertSame([], DataCampaignCalendar::dueSlots('2026-12-16', '2026-12-01', '2026-12-31', $days, []));
        self::assertSame(['j14'], DataCampaignCalendar::dueSlots('2026-12-17', '2026-12-01', '2026-12-31', $days, []));
        self::assertSame([], DataCampaignCalendar::dueSlots('2026-12-17', '2026-12-01', '2026-12-31', $days, ['j14']));
        // Trop en retard : le rappel de J-14 ne part plus le 28.
        self::assertSame(['j3'], DataCampaignCalendar::dueSlots('2026-12-28', '2026-12-01', '2026-12-31', $days, []));
        self::assertSame(['j0'], DataCampaignCalendar::dueSlots('2026-12-31', '2026-12-01', '2026-12-31', $days, ['j14', 'j3']));
        // Campagne ouverte après la date du rappel : il est sauté.
        self::assertSame([], DataCampaignCalendar::dueSlots('2026-12-17', '2026-12-20', '2026-12-31', $days, []));
    }

    // ------------------------------------------------------------------ blocs, instantané, avancement, périmètre

    public function testBlocksAreNormalizedInOrderAndHoursAreVolatile(): void
    {
        self::assertSame(DataCampaignBlocks::keys(), DataCampaignBlocks::normalize(null));
        self::assertSame(['identity', 'hours'], DataCampaignBlocks::normalize(['hours', 'bogus', 'identity', 'identity']));
        self::assertSame(['qualifications'], DataCampaignBlocks::normalize('["qualifications"]'));
        self::assertTrue(DataCampaignBlocks::isVolatile('hours'));
        self::assertFalse(DataCampaignBlocks::isVolatile('identity'));
        // Les champs proposables sont des champs de correction de fiche que le membre a déjà le droit de proposer.
        foreach (DataCampaignBlocks::keys() as $key) {
            foreach (DataCampaignBlocks::correctionFields($key) as $field) {
                self::assertArrayHasKey($field, PersonnelCorrectionRequestService::CORRECTABLE_FIELDS, $field);
                self::assertArrayNotHasKey($field, PersonnelCorrectionRequestService::STAFF_ONLY_FIELDS, $field);
            }
        }
    }

    public function testSnapshotHashIsStableAndDetectsAChangedValue(): void
    {
        $a = [['label' => 'Nom', 'value' => 'Dupont  '], ['label' => 'Indicatif', 'value' => 'FOX']];
        $b = [['label' => 'Nom', 'value' => 'Dupont'], ['label' => 'Indicatif', 'value' => 'FOX']];
        $c = [['label' => 'Nom', 'value' => 'Dupont'], ['label' => 'Indicatif', 'value' => 'WOLF']];
        self::assertSame(DataCampaignSnapshot::hash($a), DataCampaignSnapshot::hash($b));
        self::assertNotSame(DataCampaignSnapshot::hash($b), DataCampaignSnapshot::hash($c));
        $snap = DataCampaignSnapshot::build(['identity' => $b, 'hours' => [['label' => 'Heures de jeu', 'value' => '10 h 00 min']]]);
        // L'identité a changé : signalée ; les heures, qui grandissent seules, jamais.
        self::assertSame(['identity'], DataCampaignSnapshot::changedBlocks($snap, ['identity' => DataCampaignSnapshot::hash($c), 'hours' => 'autre']));
        self::assertSame([], DataCampaignSnapshot::changedBlocks($snap, ['identity' => DataCampaignSnapshot::hash($b), 'hours' => 'autre']));
        $reversed = array_reverse($snap, true);
        self::assertSame(DataCampaignSnapshot::overallHash($snap), DataCampaignSnapshot::overallHash($reversed));
    }

    public function testProgressBucketsAreExclusiveAndGroupedByUnit(): void
    {
        self::assertSame('confirmed', DataCampaignProgress::bucket(true, 0));
        self::assertSame('flagged', DataCampaignProgress::bucket(true, 2));
        self::assertSame('flagged', DataCampaignProgress::bucket(false, 1));
        self::assertSame('none', DataCampaignProgress::bucket(false, 0));
        $rows = [
            ['confirmed' => true, 'open_flags' => 0, 'unit_id' => 2],
            ['confirmed' => true, 'open_flags' => 1, 'unit_id' => 2],
            ['confirmed' => false, 'open_flags' => 0, 'unit_id' => 3],
            ['confirmed' => false, 'open_flags' => 0, 'unit_id' => 0],
        ];
        $t = DataCampaignProgress::totals($rows);
        self::assertSame(['total' => 4, 'confirmed' => 1, 'flagged' => 1, 'none' => 2, 'percent' => 25], $t);
        self::assertSame($t['total'], $t['confirmed'] + $t['flagged'] + $t['none']);
        $units = DataCampaignProgress::byUnit($rows, [2 => 'Section A', 3 => 'Section B']);
        self::assertSame(['Section A', 'Section B', 'Sans unité'], array_column($units, 'label'));
        self::assertSame(2, $units[0]['total']);
    }

    public function testScopeIncludesSubUnitsAndRestrictsAViewerToTheirUnits(): void
    {
        $units = $this->repo->unitRows;
        self::assertEqualsCanonicalizing([1, 2, 3], DataCampaignScope::expand([1], $units));
        self::assertTrue(DataCampaignScope::memberInCampaign(2, [1], $units));
        self::assertFalse(DataCampaignScope::memberInCampaign(9, [1], $units));
        self::assertTrue(DataCampaignScope::memberInCampaign(9, [], $units), 'périmètre vide : toute la communauté');
        self::assertFalse(DataCampaignScope::memberInCampaign(0, [1], $units), 'sans unité : hors d\'un périmètre d\'unités');
        self::assertNull(DataCampaignScope::viewerUnits(true, [], $units));
        self::assertSame([], DataCampaignScope::viewerUnits(false, [], $units));
        $rows = [['user_id' => 11, 'unit_id' => 2], ['user_id' => 13, 'unit_id' => 9]];
        self::assertSame([['user_id' => 11, 'unit_id' => 2]], DataCampaignScope::filterRows($rows, [2, 3]));
        self::assertSame($rows, DataCampaignScope::filterRows($rows, null));
        self::assertSame([], DataCampaignScope::filterRows($rows, []));
    }

    // ------------------------------------------------------------------ cycle de vie

    public function testCampaignLifecycleDraftOpenClosedWithAuditAndScope(): void
    {
        $s = $this->service();
        self::assertFalse($s->createCampaign(self::TENANT, 1, ['year' => 2026, 'due_on' => 'pas une date'])['ok']);
        self::assertFalse($s->createCampaign(self::TENANT, 1, ['year' => 2026, 'opens_on' => '2026-12-10', 'due_on' => '2026-12-01'])['ok']);
        $created = $s->createCampaign(self::TENANT, 1, ['year' => 2026, 'due_on' => '2026-12-31', 'unit_ids' => [1, 777]]);
        self::assertTrue($created['ok']);
        $id = (int) $created['id'];
        $campaign = $this->repo->findCampaign($id, self::TENANT);
        self::assertSame('draft', $campaign['status']);
        self::assertSame([1], $campaign['unit_ids'], 'une unité inconnue est ignorée');
        self::assertSame('Fiabilisation des données 2026', $campaign['title']);
        self::assertSame([], $this->repo->memberRows, 'un brouillon n\'inscrit personne');

        self::assertFalse($s->closeCampaign(self::TENANT, 1, $id)['ok'], 'on ne clôture pas un brouillon');
        $opened = $s->openCampaign(self::TENANT, 1, $id);
        self::assertTrue($opened['ok']);
        // Périmètre : la compagnie et ses sections (11, 12), pas l'autre unité (13) ni le membre sans unité (14).
        self::assertSame(['1:11', '1:12'], array_keys($this->repo->memberRows));
        self::assertSame([[11, 'data_campaign_open'], [12, 'data_campaign_open']], $this->notices);
        self::assertFalse($s->openCampaign(self::TENANT, 1, $id)['ok'], 'déjà ouverte');
        self::assertFalse($s->openCampaign(self::TENANT + 1, 1, $id)['ok'], 'une autre communauté ne voit pas la campagne');

        self::assertTrue($s->closeCampaign(self::TENANT, 1, $id)['ok']);
        self::assertSame('closed', $this->repo->findCampaign($id, self::TENANT)['status']);
        self::assertSame(['data_campaign.created', 'data_campaign.opened', 'data_campaign.closed'], array_column($this->audits, 0));
        self::assertSame([$id, $id, $id], array_column($this->audits, 3));
    }

    public function testProgressOfAnOpenCampaignIsComputedPerBucketAndPerUnit(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s);
        $this->answerAll($s, $id, 11);
        self::assertTrue($s->confirm(self::TENANT, 11, $id)['ok']);
        $s->answer(self::TENANT, 12, $id, 'identity', 'flag', 'Mon prénom est mal écrit');
        $campaign = $this->repo->findCampaign($id, self::TENANT);
        $o = $s->overview(self::TENANT, $campaign, null);
        self::assertSame(['total' => 4, 'confirmed' => 1, 'flagged' => 1, 'none' => 2, 'percent' => 25], $o['totals']);
        self::assertSame(['Autre', 'Section A', 'Section B', 'Sans unité'], array_column($o['byUnit'], 'label'));
        // Un chef de la section A ne voit que sa section.
        $scoped = $s->overview(self::TENANT, $campaign, DataCampaignScope::viewerUnits(false, [2], $this->repo->unitRows));
        self::assertSame([11], array_column($scoped['members'], 'user_id'));
        self::assertSame([], $scoped['flags'], 'le signalement du membre 12 est hors de son périmètre');
        $scoped = $s->overview(self::TENANT, $campaign, DataCampaignScope::viewerUnits(false, [1], $this->repo->unitRows));
        self::assertSame([11, 12], array_column($scoped['members'], 'user_id'));
        self::assertCount(1, $scoped['flags']);
    }

    // ------------------------------------------------------------------ parcours du membre

    public function testMemberAnswersEveryBlockThenConfirmsAndTheSnapshotRecordsWhatWasSeen(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['identity', 'hours']]);
        self::assertFalse($s->confirm(self::TENANT, 11, $id)['ok'], 'rien n\'est répondu');
        self::assertTrue($s->answer(self::TENANT, 11, $id, 'identity', 'ok')['ok']);
        self::assertFalse($s->confirm(self::TENANT, 11, $id)['ok'], 'il reste les heures');
        self::assertFalse($s->answer(self::TENANT, 11, $id, 'qualifications', 'ok')['ok'], 'bloc hors campagne');
        self::assertFalse($s->answer(self::TENANT, 11, $id, 'identity', 'peut-être')['ok']);
        self::assertTrue($s->answer(self::TENANT, 11, $id, 'hours', 'ok')['ok']);
        $done = $s->confirm(self::TENANT, 11, $id);
        self::assertTrue($done['ok']);
        $row = $this->repo->memberRow($id, 11);
        self::assertTrue($row['confirmed']);
        self::assertSame($done['hash'], $row['snapshot_hash']);
        self::assertSame(['identity', 'hours'], array_keys($row['snapshot']));
        self::assertSame('Valeur', $row['snapshot']['identity']['rows'][0]['label']);
        self::assertSame(64, strlen((string) $row['snapshot_hash']));
    }

    public function testADataChangeBetweenTheAnswerAndTheConfirmationAsksForANewReading(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['identity']]);
        self::assertTrue($s->answer(self::TENANT, 11, $id, 'identity', 'ok')['ok']);
        $this->reader->setValue(11, 'identity', 'Indicatif', 'NOUVEAU');
        $refused = $s->confirm(self::TENANT, 11, $id);
        self::assertFalse($refused['ok']);
        self::assertStringContainsString('a changé', $refused['message']);
        self::assertFalse($this->repo->memberRow($id, 11)['confirmed']);
        // La vue du membre redemande cette partie.
        $view = $s->memberView(self::TENANT, 11, $this->repo->findCampaign($id, self::TENANT), $this->repo->memberRow($id, 11));
        self::assertSame('', $view['blocks']['identity']['state']);
        self::assertTrue($s->answer(self::TENANT, 11, $id, 'identity', 'ok')['ok']);
        self::assertTrue($s->confirm(self::TENANT, 11, $id)['ok']);
    }

    public function testADataChangeAfterConfirmationIsFlaggedButHoursNeverAre(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['identity', 'hours']]);
        $this->answerAll2($s, $id, 11, ['identity', 'hours']);
        self::assertTrue($s->confirm(self::TENANT, 11, $id)['ok']);
        $status = $s->ficheStatus(self::TENANT, 11);
        self::assertSame(2026, $status['year']);
        self::assertSame([], $status['changed']);
        $this->reader->setValue(11, 'hours', 'Heures de jeu', '999 h');
        self::assertSame([], $s->ficheStatus(self::TENANT, 11)['changed'], 'les heures grandissent seules');
        $this->reader->setValue(11, 'identity', 'Indicatif', 'AUTRE');
        self::assertSame(['Identité'], $s->ficheStatus(self::TENANT, 11)['changed']);
        // Rien n'est cassé : la confirmation d'origine reste lisible.
        self::assertNotNull($this->repo->memberRow($id, 11)['confirmed_at']);
        self::assertNull($s->ficheStatus(self::TENANT, 12), 'un membre qui n\'a rien confirmé n\'a pas de mention');
    }

    /** @param list<string> $keys */
    private function answerAll2(DataCampaignService $s, int $campaignId, int $userId, array $keys): void
    {
        foreach ($keys as $key) {
            self::assertTrue($s->answer(self::TENANT, $userId, $campaignId, $key, 'ok')['ok'], $key);
        }
    }

    public function testToCorrectNeedsAnExplanationAndWithValuesCreatesARequestInTheExistingCircuit(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['identity', 'qualifications']]);
        self::assertFalse($s->answer(self::TENANT, 11, $id, 'identity', 'flag', 'x')['ok'], 'explication trop courte');
        self::assertSame([], $this->repo->flagRows);

        $r = $s->answer(self::TENANT, 11, $id, 'identity', 'flag', 'Mon indicatif est faux', ['callsign' => 'FOX', 'last_name' => '', 'bogus_field' => 'x', 'motto' => 'interdit ici']);
        self::assertTrue($r['ok']);
        // Seuls les champs du bloc partent dans la demande de correction existante, avec la précision dans la note.
        self::assertCount(1, $this->corrections);
        self::assertSame(['callsign' => 'FOX'], $this->corrections[0][0]);
        self::assertStringContainsString('Fiabilisation 2026', $this->corrections[0][1]);
        self::assertStringContainsString('Mon indicatif est faux', $this->corrections[0][1]);
        self::assertSame(501, $this->repo->flagRows[1]['correction_request_id']);
        self::assertSame('flag', $this->repo->memberRow($id, 11)['answers']['identity']['state']);

        // Demande en attente : le bloc ne peut plus être répondu autrement tant qu'elle n'est pas décidée.
        $this->repo->requestStatus[501] = 'pending';
        self::assertFalse($s->answer(self::TENANT, 11, $id, 'identity', 'ok')['ok']);
        // Décidée : le signalement n'est plus compté comme ouvert.
        $this->repo->requestStatus[501] = 'approved';
        $o = $s->overview(self::TENANT, $this->repo->findCampaign($id, self::TENANT), null);
        $row = array_values(array_filter($o['members'], static fn (array $m): bool => (int) $m['user_id'] === 11))[0];
        self::assertSame(0, $row['open_flags']);
    }

    public function testToCorrectWithoutValuesIsAFlagForTheStaffQueueAndNoFieldIsEverWrittenByTheModule(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['qualifications']]);
        $r = $s->answer(self::TENANT, 11, $id, 'qualifications', 'flag', 'Il manque mon brevet de pilote', ['callsign' => 'ignoré']);
        self::assertTrue($r['ok']);
        self::assertSame([], $this->corrections, 'ce bloc n\'a aucun champ proposable');
        self::assertNull($this->repo->flagRows[1]['correction_request_id']);
        $handled = $s->handleFlag(self::TENANT, 1, 1, 'Ajouté par l\'instructeur', null);
        self::assertTrue($handled['ok']);
        self::assertFalse($s->handleFlag(self::TENANT, 1, 1, 'Encore', null)['ok'], 'déjà traité');
        self::assertContains([11, 'data_campaign_flag_handled'], $this->notices);
        self::assertFalse($s->handleFlag(self::TENANT, 1, 99, 'Une réponse', null)['ok'], 'signalement inconnu');
    }

    public function testAFlagIsRecordedEvenWhenTheCorrectionRequestIsRefused(): void
    {
        $s = $this->service(false);
        $id = $this->openCampaign($s, ['blocks' => ['identity']]);
        $r = $s->answer(self::TENANT, 11, $id, 'identity', 'flag', 'Prénom mal écrit', ['first_name' => 'Jean']);
        self::assertTrue($r['ok']);
        self::assertStringContainsString('déjà en attente', $r['message']);
        self::assertNull($this->repo->flagRows[1]['correction_request_id']);
    }

    public function testAMemberOnlyActsOnTheirOwnRecordAndOnAnOpenCampaign(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['unit_ids' => [2]]);
        // Le membre 13 (autre unité) n'est pas dans le périmètre : ni lecture, ni réponse.
        self::assertNull($s->activeFor(self::TENANT, 13));
        self::assertFalse($s->answer(self::TENANT, 13, $id, 'identity', 'ok')['ok']);
        self::assertFalse($s->confirm(self::TENANT, 13, $id)['ok']);
        self::assertSame(['1:11'], array_keys($this->repo->memberRows));
        // Un membre arrivé après l'ouverture, dans le périmètre, est inscrit à sa première visite.
        $this->repo->rosterRows[] = ['user_id' => 15, 'unit_id' => 2, 'display_name' => 'Echo', 'callsign' => 'E1'];
        self::assertNotNull($s->activeFor(self::TENANT, 15));
        self::assertTrue($s->closeCampaign(self::TENANT, 1, $id)['ok']);
        self::assertFalse($s->answer(self::TENANT, 11, $id, 'identity', 'ok')['ok'], 'campagne close');
        self::assertNull($s->activeFor(self::TENANT, 11));
        self::assertSame(0, $s->pendingBadge(self::TENANT, 11));
    }

    public function testThePendingBadgeStaysUntilTheMemberConfirms(): void
    {
        $s = $this->service();
        self::assertSame(0, $s->pendingBadge(self::TENANT, 11), 'pas de campagne : pas de pastille');
        $id = $this->openCampaign($s, ['blocks' => ['identity']]);
        self::assertSame(1, $s->pendingBadge(self::TENANT, 11));
        $this->answerAll2($s, $id, 11, ['identity']);
        self::assertSame(1, $s->pendingBadge(self::TENANT, 11), 'répondu mais pas confirmé');
        $s->confirm(self::TENANT, 11, $id);
        self::assertSame(0, $s->pendingBadge(self::TENANT, 11));
    }

    // ------------------------------------------------------------------ relances et tâche planifiée

    public function testManualRemindersSkipAnsweredMembersRespectTheCooldownAndAreAudited(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['identity']]);
        $this->mails = [];
        $this->answerAll2($s, $id, 11, ['identity']);
        $s->confirm(self::TENANT, 11, $id);
        $r = $s->remind(self::TENANT, 1, $id, null, null);
        self::assertTrue($r['ok']);
        self::assertSame(3, $r['sent'], 'le membre qui a confirmé n\'est pas relancé');
        self::assertSame([12, 13, 14], $this->mails);
        // Anti-spam : une seconde relance tout de suite n'envoie rien.
        $again = $s->remind(self::TENANT, 1, $id, null, null);
        self::assertFalse($again['ok']);
        self::assertSame(0, $again['sent']);
        self::assertSame([12, 13, 14], $this->mails);
        // Quatre jours plus tard (plus de 72 h), une relance individuelle repart.
        $this->now += 4 * 86400;
        $one = $s->remind(self::TENANT, 1, $id, [12], null);
        self::assertSame(1, $one['sent']);
        self::assertContains('data_campaign.reminded', array_column($this->audits, 0));
        // Hors périmètre du lecteur : personne.
        self::assertSame(0, $s->remind(self::TENANT, 1, $id, null, [])['sent']);
    }

    public function testAthenaOpensTheAnnualCampaignByDefaultForEveryCommunityAndOnlyOnce(): void
    {
        $s = $this->service();
        // Aucun réglage enregistré : l'ouverture automatique est active par défaut.
        self::assertTrue($this->repo->settings(self::TENANT)['auto_enabled']);
        self::assertTrue($this->repo->settings(self::TENANT)['emails_enabled']);
        $this->now = (int) strtotime('2026-11-30 10:00:00 UTC');
        self::assertSame(0, $s->runScheduled()['opened'], 'avant la période');
        $this->now = (int) strtotime('2026-12-01 10:00:00 UTC');
        $out = $s->runScheduled();
        self::assertSame(1, $out['opened']);
        $c = $this->repo->campaigns[1];
        self::assertSame('open', $c['status']);
        self::assertSame(1, (int) $c['auto_created']);
        self::assertSame(DataCampaignBlocks::keys(), $c['blocks'], 'tous les blocs par défaut');
        self::assertSame([], $c['unit_ids'], 'tous les membres actifs');
        self::assertSame('2026-12-31', $c['due_on']);
        self::assertNull($c['forced_on']);
        self::assertCount(4, $this->repo->memberRows);
        self::assertSame(4, $out['notified']);
        self::assertSame(4, $out['mails'], 'e-mails activés par défaut');
        // Les responsables reçoivent un seul avis, les membres la notification d'ouverture.
        $managerNotices = array_values(array_filter($this->payloads, static fn (array $n): bool => $n['type'] === DataCampaignService::NOTIFY_AUTO_OPENED));
        self::assertSame([900, 901], array_column($managerNotices, 'user'));
        self::assertSame(4, $managerNotices[0]['payload']['members']);
        self::assertFalse($managerNotices[0]['payload']['incomplete']);
        self::assertFalse($managerNotices[0]['payload']['forced']);

        // Idempotent : ni un second passage, ni une clôture manuelle ne produisent une seconde campagne ou un second envoi.
        $count = count($this->notices);
        self::assertSame(0, $s->runScheduled()['opened']);
        $s->closeCampaign(self::TENANT, 1, 1);
        self::assertSame(0, $s->runScheduled()['opened']);
        self::assertCount(1, $this->repo->campaigns);
        self::assertSame($count, count($this->notices), 'aucun envoi de plus');
    }

    public function testAManualCampaignOfTheYearPreventsASecondOneAndTheSettingCanSwitchTheAutomatismOff(): void
    {
        $s = $this->service();
        $manual = $s->createCampaign(self::TENANT, 5, ['year' => 2026, 'due_on' => '2026-12-20', 'unit_ids' => [2]]);
        self::assertTrue($manual['ok']);
        self::assertSame(0, $s->runScheduled()['opened'], 'une campagne manuelle (même brouillon) de l\'année suffit');
        self::assertCount(1, $this->repo->campaigns);

        // « Ouverture automatique : Non » : rien de plus, ni cette année ni l'année suivante.
        $other = $this->service();
        $this->repo->campaigns = [];
        self::assertTrue($other->saveSettings(self::TENANT, 1, ['auto_enabled' => '0'])['ok']);
        self::assertSame(0, $other->runScheduled()['opened']);
        $this->now = (int) strtotime('2027-12-05 10:00:00 UTC');
        self::assertSame(0, $other->runScheduled()['opened']);
        self::assertSame([], $this->repo->campaigns);
        // La gestion peut toujours créer et ouvrir à la main.
        $made = $other->createCampaign(self::TENANT, 1, ['year' => 2027, 'due_on' => '2027-12-20']);
        self::assertTrue($made['ok']);
        self::assertTrue($other->openCampaign(self::TENANT, 1, (int) $made['id'])['ok']);
    }

    public function testManagementCanEditOrCancelTheCampaignAthenaOpened(): void
    {
        $s = $this->service();
        $s->runScheduled();
        self::assertTrue($s->updateCampaign(self::TENANT, 1, 1, 'Relecture de fin d’année', '2026-12-24')['ok']);
        self::assertSame('2026-12-24', $this->repo->campaigns[1]['due_on']);
        self::assertSame('Relecture de fin d’année', $this->repo->campaigns[1]['title']);
        self::assertFalse($s->updateCampaign(self::TENANT, 1, 1, 'x', '2026-11-01')['ok'], 'avant l\'ouverture');
        self::assertFalse($s->updateCampaign(self::TENANT, 1, 1, 'x', 'n\'importe quoi')['ok']);
        self::assertTrue($s->closeCampaign(self::TENANT, 1, 1)['ok'], 'annuler = clôturer');
        self::assertFalse($s->updateCampaign(self::TENANT, 1, 1, 'x', '2026-12-30')['ok'], 'une campagne close ne se modifie plus');
    }

    public function testScheduledRemindersAreSentOncePerMemberAndCampaignsCloseAfterTheDeadlineWithAReport(): void
    {
        $s = $this->service();
        $s->saveSettings(self::TENANT, 1, ['blocks' => ['identity']]);
        $s->runScheduled();
        self::assertSame(1, count($this->repo->campaigns));
        $this->mails = [];
        // Le 1er décembre : aucun rappel (J-14 est le 17).
        self::assertSame(0, $s->runScheduled()['reminders']);

        $this->now = (int) strtotime('2026-12-17 08:00:00 UTC');
        $first = $s->runScheduled();
        self::assertSame(4, $first['reminders']);
        self::assertSame([11, 12, 13, 14], $this->mails);
        // Deux passages dans la même heure : le rappel ne repart pas.
        self::assertSame(0, $s->runScheduled()['reminders']);
        self::assertSame(4, count($this->mails));
        self::assertContains('j14', $this->repo->doneSlots(1));

        // Un membre qui confirme n'est plus relancé aux dates suivantes.
        $this->answerAll2($s, 1, 11, ['identity']);
        $s->confirm(self::TENANT, 11, 1);
        $this->now = (int) strtotime('2026-12-28 08:00:00 UTC');
        $third = $s->runScheduled();
        self::assertSame(3, $third['reminders']);
        self::assertSame(1, count(array_filter($this->repo->reminderRows, static fn (array $r): bool => $r['slot'] === 'j14' && (int) $r['user_id'] === 11)));

        // Après la date limite, la campagne est close et la gestion reçoit le rapport d'avancement.
        $this->now = (int) strtotime('2027-01-02 08:00:00 UTC');
        $last = $s->runScheduled();
        self::assertSame(1, $last['closed']);
        self::assertSame('closed', $this->repo->campaigns[1]['status']);
        $reports = array_values(array_filter($this->payloads, static fn (array $n): bool => $n['type'] === DataCampaignService::NOTIFY_AUTO_CLOSED));
        self::assertSame([900, 901], array_column($reports, 'user'));
        self::assertSame(1, $reports[0]['payload']['confirmed']);
        self::assertSame(3, $reports[0]['payload']['none']);
        self::assertSame(4, $reports[0]['payload']['total']);
    }

    public function testNotificationsAreBoundedPerPassAndNeverSentTwice(): void
    {
        $s = $this->service();
        $this->repo->rosterRows = [];
        for ($i = 1; $i <= DataCampaignService::BATCH + 20; $i++) {
            $this->repo->rosterRows[] = ['user_id' => 100 + $i, 'unit_id' => 0, 'display_name' => 'M' . $i, 'callsign' => ''];
        }
        $first = $s->runScheduled();
        self::assertSame(1, $first['opened']);
        self::assertSame(DataCampaignService::BATCH, $first['notified'], 'au plus 150 membres servis par passage');
        self::assertSame(DataCampaignService::BATCH, $first['mails']);
        $second = $s->runScheduled();
        self::assertSame(0, $second['opened']);
        self::assertSame(20, $second['notified'], 'le reste part au passage suivant');
        self::assertSame(['open'], $this->repo->doneSlots(1));
        $third = $s->runScheduled();
        self::assertSame(0, $third['notified']);
        $perUser = array_count_values(array_column(array_filter($this->notices, static fn (array $n): bool => $n[1] === DataCampaignService::NOTIFY_OPEN), 0));
        self::assertSame(1, max($perUser));
        self::assertCount(DataCampaignService::BATCH + 20, $perUser);
    }

    public function testRemindersAreBoundedToo(): void
    {
        $s = $this->service();
        $this->repo->rosterRows = [];
        for ($i = 1; $i <= DataCampaignService::BATCH + 20; $i++) {
            $this->repo->rosterRows[] = ['user_id' => 100 + $i, 'unit_id' => 0, 'display_name' => 'M' . $i, 'callsign' => ''];
        }
        $this->openCampaign($s, ['reminder_days' => [14], 'opens_on' => '2026-12-01']);
        $this->now = (int) strtotime('2026-12-17 08:00:00 UTC');
        // Les 20 derniers membres reçoivent d'abord leur notification d'ouverture, puis le reste du lot part en rappels.
        self::assertSame(DataCampaignService::BATCH - 20, $s->runScheduled()['reminders']);
        self::assertSame(['open'], $this->repo->doneSlots(1));
        self::assertSame(40, $s->runScheduled()['reminders']);
        self::assertSame(['open', 'j14'], $this->repo->doneSlots(1));
        self::assertSame(0, $s->runScheduled()['reminders']);
    }

    // ------------------------------------------------------------------ ouverture forcée (commande et bouton) et simulation

    public function testForcedOpeningUsesTheSameServiceOutsideThePeriodAndIsIdempotent(): void
    {
        $s = $this->service();
        $this->now = (int) strtotime('2026-06-10 09:00:00 UTC');
        self::assertSame(0, $s->runScheduled()['opened'], 'hors période : la tâche planifiée n\'ouvre rien');
        $options = ['force' => true, 'tenant' => self::TENANT, 'actor' => 3];
        $first = $s->runScheduled($options);
        self::assertSame(1, $first['opened']);
        self::assertSame(4, $first['notified']);
        $c = $this->repo->campaigns[1];
        self::assertSame('2026-06-10', $c['forced_on']);
        self::assertSame('2026-12-31', $c['due_on'], 'date limite par défaut : 31 décembre de l\'année');
        self::assertSame(1, (int) $c['auto_created']);
        self::assertContains('data_campaign.opened_by_athena', array_column($this->audits, 0));

        // Relancer la commande ne renvoie aucune notification déjà envoyée et ne crée pas de seconde campagne.
        $notices = count($this->notices);
        $again = $s->runScheduled($options);
        self::assertSame(0, $again['opened']);
        self::assertSame(0, $again['notified']);
        self::assertSame($notices, count($this->notices));
        self::assertCount(1, $this->repo->campaigns);
        self::assertSame('Une campagne existe déjà pour cette année.', $again['plan'][0]['reason']);
    }

    public function testForcedOpeningHonoursDeadlineBlocksAndNoEmail(): void
    {
        $s = $this->service();
        $this->now = (int) strtotime('2026-06-10 09:00:00 UTC');
        $out = $s->runScheduled(['force' => true, 'tenant' => self::TENANT, 'deadline' => '2026-09-30', 'blocks' => ['identity', 'hours'], 'email' => false]);
        self::assertSame(1, $out['opened']);
        self::assertSame('2026-09-30', $this->repo->campaigns[1]['due_on']);
        self::assertSame(['identity', 'hours'], $this->repo->campaigns[1]['blocks']);
        self::assertSame(4, $out['notified']);
        self::assertSame(0, $out['mails'], '--no-email');
        self::assertSame([], $this->mails);
        // Une date limite passée est refusée.
        $this->repo->campaigns = [];
        $late = $s->runScheduled(['force' => true, 'tenant' => self::TENANT, 'deadline' => '2026-06-01']);
        self::assertSame(0, $late['opened']);
        self::assertSame('La date limite est déjà passée.', $late['plan'][0]['reason']);
    }

    public function testForcedOpeningRespectsAManualCampaignOfTheYear(): void
    {
        $s = $this->service();
        $s->createCampaign(self::TENANT, 5, ['year' => 2026, 'due_on' => '2026-12-20']);
        $out = $s->runScheduled(['force' => true, 'tenant' => self::TENANT]);
        self::assertSame(0, $out['opened']);
        self::assertCount(1, $this->repo->campaigns);
        self::assertSame(0, $out['notified']);
    }

    public function testTheBulkCommandSkipsCommunitiesThatSwitchedTheAutomatismOffUnlessNamed(): void
    {
        $s = $this->service();
        $this->repo->tenantIds = [7, 8];
        $this->repo->settingsByTenant[8] = ['auto_enabled' => 0];
        $this->now = (int) strtotime('2026-06-10 09:00:00 UTC');
        $bulk = $s->runScheduled(['force' => true, 'dry_run' => true]);
        self::assertSame([7], array_column(array_filter($bulk['plan'], static fn (array $p): bool => $p['action'] === 'open'), 'tenant_id'));
        $named = $s->runScheduled(['force' => true, 'dry_run' => true, 'tenant' => 8]);
        self::assertSame('open', $named['plan'][0]['action']);
    }

    public function testDryRunListsCommunitiesAndMemberCountsWithoutWritingOrSendingAnything(): void
    {
        $s = $this->service();
        $this->now = (int) strtotime('2026-06-10 09:00:00 UTC');
        $out = $s->runScheduled(['force' => true, 'dry_run' => true]);
        self::assertSame(1, $out['tenants']);
        self::assertSame('open', $out['plan'][0]['action']);
        self::assertSame(4, $out['plan'][0]['members']);
        self::assertSame(self::TENANT, $out['plan'][0]['tenant_id']);
        self::assertSame(0, $out['opened']);
        self::assertSame(0, $out['notified']);
        self::assertSame([], $this->repo->campaigns);
        self::assertSame([], $this->repo->memberRows);
        self::assertSame([], $this->notices);
        self::assertSame([], $this->mails);
        self::assertSame([], $this->audits);
        self::assertSame(0, $this->repo->writes);
        // La simulation de la tâche planifiée, un jour de la période, n'écrit rien non plus.
        $this->now = (int) strtotime('2026-12-01 09:00:00 UTC');
        $s->runScheduled(['dry_run' => true]);
        self::assertSame(0, $this->repo->writes);
        self::assertSame([], $this->repo->campaigns);
    }

    public function testADormantCommunityIsLeftAloneAndIncompleteRecordsAddANoticeToTheManagers(): void
    {
        $s = $this->service();
        $this->repo->recentUsers = [];
        $out = $s->runScheduled();
        self::assertSame(0, $out['opened']);
        self::assertSame([], $this->repo->campaigns);
        $forced = $s->runScheduled(['force' => true, 'dry_run' => true, 'tenant' => self::TENANT]);
        self::assertSame('skip', $forced['plan'][0]['action'], 'mêmes garde-fous pour l\'ouverture forcée');
        self::assertStringContainsString('connecté', $forced['plan'][0]['reason']);

        $this->repo->recentUsers = null;
        $this->repo->withoutName = [11, 12, 13];
        $opened = $s->runScheduled();
        self::assertSame(1, $opened['opened']);
        $notice = array_values(array_filter($this->payloads, static fn (array $n): bool => $n['type'] === DataCampaignService::NOTIFY_AUTO_OPENED))[0];
        self::assertTrue($notice['payload']['incomplete'], 'plus de la moitié des fiches sans prénom ni nom');

        // Moins de la moitié : pas d'avis « fiches incomplètes ».
        $this->payloads = [];
        $this->repo->campaigns = [];
        $this->repo->memberRows = [];
        $this->repo->withoutName = [11];
        $s->runScheduled();
        $notice = array_values(array_filter($this->payloads, static fn (array $n): bool => $n['type'] === DataCampaignService::NOTIFY_AUTO_OPENED))[0];
        self::assertFalse($notice['payload']['incomplete']);
    }

    public function testEmailsCanBeSwitchedOffForACommunityWhileNotificationsStay(): void
    {
        $s = $this->service();
        $s->saveSettings(self::TENANT, 1, ['emails_enabled' => '0']);
        $out = $s->runScheduled();
        self::assertSame(1, $out['opened']);
        self::assertSame(4, $out['notified']);
        self::assertSame(0, $out['mails']);
        self::assertSame([], $this->mails);
    }

    public function testTheCommandTheButtonAndTheDocumentationExistAndShareTheService(): void
    {
        $cli = (string) file_get_contents(self::ROOT . '/data-campaign.php');
        foreach (['--open-now', '--dry-run', '--no-email', '--deadline=', '--blocks=', '--tenant='] as $flag) {
            self::assertStringContainsString($flag, $cli, $flag);
        }
        self::assertStringContainsString('DataCampaignService::class)->runScheduled($options)', $cli, 'aucune logique dupliquée');
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $cli);
        self::assertDoesNotMatchRegularExpression('/INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|->prepare\(/i', $cli);
        $admin = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/DataCampaignAdminController.php');
        preg_match('/function openNow\(.*?\n    \}/s', $admin, $m);
        self::assertStringContainsString('writeContext(', $m[0]);
        self::assertStringContainsString("'force' => true", $m[0]);
        self::assertStringContainsString('runScheduled(', $m[0]);
        $route = Map::match('POST', '/back-office/organisation/fiabilisation/ouvrir-maintenant');
        self::assertNotNull($route);
        self::assertSame([AuthMiddleware::class, OrganizationAdminMiddleware::class], $route['middleware']);
        $view = (string) file_get_contents(self::ROOT . '/views/admin/organization/data_campaigns.php');
        self::assertStringContainsString('data-confirm="Ouvrir la campagne maintenant ?', $view, 'confirmation du nombre de membres avant l\'envoi');
        self::assertStringContainsString('Ouverte par Athena', (string) file_get_contents(self::ROOT . '/views/admin/organization/data_campaign_show.php'));
        $doc = (string) file_get_contents(self::ROOT . '/docs/fiabilisation-donnees.md');
        foreach (['--open-now --dry-run', 'migration', 'Procédure de lancement'] as $needle) {
            self::assertStringContainsString($needle, $doc, $needle);
        }
        $sql = (string) file_get_contents(self::ROOT . '/migrations/2026_11_15_090_data_campaigns.sql');
        self::assertStringContainsString('auto_enabled TINYINT(1) NOT NULL DEFAULT 1', $sql, 'ouverture automatique active par défaut');
        self::assertStringContainsString('UNIQUE KEY uq_data_campaign_auto (tenant_id, auto_year)', $sql, 'une campagne d\'Athena par communauté et par année');
        self::assertStringContainsString('emails_enabled TINYINT(1) NOT NULL DEFAULT 1', $sql);
    }

    public function testTheCronJobReportsTheScheduledPassAndIsScheduled(): void
    {
        $job = new DataCampaignCronJob($this->service());
        self::assertSame('data_campaign', $job->key());
        $r = $job->run();
        self::assertTrue($r['ok']);
        self::assertArrayHasKey('opened', $r['details']);
        self::assertSame(60, CronSchedule::intervalMinutes('data_campaign'));
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('self::get(\App\Services\Cron\Jobs\DataCampaignCronJob::class)', $container);
        self::assertStringContainsString('Jobs\DataCampaignCronJob::class => new', $container);
    }

    // ------------------------------------------------------------------ export, vie privée, SSE

    public function testExportHoldsOnlyStatusesNeverTheContentOfARecordOrAFlag(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s, ['blocks' => ['identity']]);
        $s->answer(self::TENANT, 11, $id, 'identity', 'flag', 'Texte secret du signalement');
        $rows = $s->exportRows(self::TENANT, $this->repo->findCampaign($id, self::TENANT), null);
        self::assertSame(DataCampaignService::exportHeaders(), $rows[0]);
        self::assertCount(5, $rows);
        $flat = json_encode($rows, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Texte secret', (string) $flat);
        self::assertStringNotContainsString('défaut identity', (string) $flat);
        self::assertSame('Avec signalements', $rows[1][3]);
        self::assertSame('Sans réponse', $rows[2][3]);
    }

    public function testNoSseIdentityCoverOrPaperIsReadOrStoredByTheModule(): void
    {
        $sources = [
            'app/Services/DataCampaign/DataCampaignRecordReader.php',
            'app/Services/DataCampaign/DataCampaignService.php',
            'app/Repositories/DataCampaignRepository.php',
            'app/Controllers/Web/MemberDataCampaignController.php',
            'app/Controllers/Admin/Organization/DataCampaignAdminController.php',
            'views/admin/member_situation/data_campaign.php',
            'views/admin/organization/data_campaigns.php',
            'views/admin/organization/data_campaign_show.php',
            'migrations/2026_11_15_090_data_campaigns.sql',
        ];
        foreach ($sources as $file) {
            $code = strtolower((string) file_get_contents(self::ROOT . '/' . $file));
            foreach (['sse_', 'sseidentity', 'cover_export', 'mrz', 'identity_papers', 'identitypapers', 'alias_identit', 'sse\\'] as $needle) {
                self::assertStringNotContainsString($needle, $code, $file . ' ne doit pas toucher aux données SSE (' . $needle . ')');
            }
        }
        foreach (DataCampaignBlocks::keys() as $key) {
            self::assertDoesNotMatchRegularExpression('/sse|cover|papier|paper|alias/i', $key . DataCampaignBlocks::label($key));
        }
    }

    // ------------------------------------------------------------------ droits, CSRF, routes

    public function testManagementRightsAreTheExistingDataQualityAndHrRights(): void
    {
        self::assertSame(['admin.organization', 'admin.access', 'organization.orbat.manage', 'personnel.profile.update', 'personnel.assignments.manage'], DataCampaignAdminController::PERMISSIONS);
        $data = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/OrganizationDataQualityController.php');
        foreach (['admin.organization', 'admin.access', 'organization.orbat.manage'] as $slug) {
            self::assertStringContainsString("'" . $slug . "'", $data);
        }
        foreach (DataCampaignAdminController::PERMISSIONS as $slug) {
            self::assertContains($slug, array_merge(PersonnelCorrectionRequestService::STAFF_PERMISSIONS, ['organization.orbat.manage']), $slug);
        }
        $admin = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/DataCampaignAdminController.php');
        self::assertDoesNotMatchRegularExpression('/permission_catalog|TenantPermissionCatalog/', $admin, 'aucun droit nouveau');
    }

    public function testEveryWriteChecksTheCsrfTokenAndTheMemberNeverNamesAnotherMember(): void
    {
        $admin = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/DataCampaignAdminController.php');
        self::assertStringContainsString('Csrf::validate', $admin);
        foreach (['create', 'saveSettings', 'open', 'close', 'remind', 'handleFlag'] as $method) {
            self::assertMatchesRegularExpression('/function ' . $method . '\(.*?\n    \}/s', $admin, $method);
        }
        // Chaque écriture passe par writeContext (droit + méthode POST + jeton CSRF).
        foreach (['create', 'saveSettings', 'remind', 'handleFlag'] as $method) {
            preg_match('/function ' . $method . '\(.*?\n    \}/s', $admin, $m);
            self::assertStringContainsString('writeContext(', $m[0], $method);
        }
        self::assertStringContainsString('transition(', $admin);
        $member = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/MemberDataCampaignController.php');
        self::assertSame(3, substr_count($member, 'Csrf::validate('), 'repondre, confirmer et tout-confirmer');
        self::assertStringContainsString("Session::get('tenant_id')", $member);
        self::assertStringContainsString('$this->authService->user()', $member);
        // L'identifiant du membre ne vient jamais de la requête.
        self::assertDoesNotMatchRegularExpression("/input\('(user_id|member_id|userId|membre)'/", $member);
        self::assertDoesNotMatchRegularExpression("/queryString\('(user_id|member_id|userId|membre)'/", $member);
        self::assertTrue(defined(\App\Controllers\Web\MemberDataCampaignController::class . '::JNET_MOI_READY'));
    }

    public function testRoutesAreDeclaredWithTheMemberAndManagementMiddlewares(): void
    {
        $get = Map::match('GET', '/jnet/moi/fiabilisation');
        self::assertNotNull($get);
        self::assertSame([AuthMiddleware::class, NonDefaultTenantMiddleware::class], array_map(static fn (mixed $m): string => is_string($m) ? $m : 'closure', $get['middleware']));
        foreach (['/jnet/moi/fiabilisation/repondre', '/jnet/moi/fiabilisation/confirmer'] as $path) {
            self::assertNotNull(Map::match('POST', $path), $path);
            self::assertNull(Map::match('GET', $path), $path . ' n\'est pas une lecture');
        }
        foreach (['', '/1', '/1/export'] as $suffix) {
            $route = Map::match('GET', '/back-office/organisation/fiabilisation' . $suffix);
            self::assertNotNull($route, $suffix);
            self::assertSame([AuthMiddleware::class, OrganizationAdminMiddleware::class], $route['middleware'], $suffix);
        }
        foreach (['/creer', '/reglages', '/1/ouvrir', '/1/cloturer', '/1/relancer', '/1/signalements/2/traiter'] as $suffix) {
            $post = Map::match('POST', '/back-office/organisation/fiabilisation' . $suffix);
            self::assertNotNull($post, $suffix);
            self::assertSame([AuthMiddleware::class, OrganizationAdminMiddleware::class], $post['middleware'], $suffix);
        }
        // Aucune écriture n'est servie en GET : les envois n'ont pas de route de lecture à leur adresse.
        foreach (Map::routes() as $route) {
            if ($route['method'] === 'GET' && str_starts_with($route['path'], '/back-office/organisation/fiabilisation')) {
                self::assertDoesNotMatchRegularExpression('#/(creer|reglages|ouvrir|cloturer|relancer|traiter)$#', $route['path']);
            }
        }
        // Les routes ne portent jamais l'identifiant d'un membre.
        foreach (Map::routes() as $route) {
            if (str_starts_with($route['path'], '/jnet/moi/fiabilisation')) {
                self::assertDoesNotMatchRegularExpression('/\{(userId|memberId|personnelId|id)\}/', $route['path']);
            }
        }
    }

    // ------------------------------------------------------------------ hub, navigation, notifications, fiche

    public function testTheHubOffersTheCampaignUnderMyDataWithAPendingBadge(): void
    {
        $found = null;
        foreach (JnetMemberHub::sections(['tenantType' => 'full', 'badges' => ['data_campaign_pending' => 1]]) as $section) {
            foreach ($section['items'] as $item) {
                if ($item['path'] === 'jnet/moi/fiabilisation') {
                    $found = [$section['key'], $item];
                }
            }
        }
        self::assertNotNull($found);
        self::assertSame('donnees', $found[0]);
        self::assertSame(1, $found[1]['badge']);
        self::assertSame('fiabilisation', $found[1]['page']);
        self::assertSame('fiabilisation', JnetMemberPaths::pageOf('fiabilisation'));
        self::assertSame('fiabilisation', JnetMemberPaths::pageOf('fiabilisation/repondre'));
        self::assertNull(JnetMemberPaths::pageOf('fiabilisationx'));
        self::assertStringEndsWith('/jnet/moi/fiabilisation', jnet_moi_url('fiabilisation'));
        $portal = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("'data_campaign_pending'", $portal);
    }

    public function testNotificationsAndEmailAreWiredIntoTheExistingMechanisms(): void
    {
        $presenter = new ActivityHubPresentationService();
        foreach ([DataCampaignService::NOTIFY_OPEN, DataCampaignService::NOTIFY_REMINDER, DataCampaignService::NOTIFY_FLAG_HANDLED] as $type) {
            $row = $presenter->formatNotificationRow(['type' => $type, 'payload_json' => json_encode(['due_on' => '2026-12-31']), 'created_at' => '2026-12-01 10:00:00', 'read_at' => null]);
            self::assertNotSame('Notification', $row['title'], $type);
            self::assertStringEndsWith('/jnet/moi/fiabilisation', $row['href']);
        }
        self::assertContains(EmailEvents::DATA_CAMPAIGN_REMINDER, EmailEvents::EMAIL_EVENTS);
        self::assertStringContainsString('EmailEvents::DATA_CAMPAIGN_REMINDER', (string) file_get_contents(self::ROOT . '/app/Services/Email/MailBudget.php'));
        self::assertStringContainsString('EmailEvents::DATA_CAMPAIGN_REMINDER', (string) file_get_contents(self::ROOT . '/app/Controllers/Web/AccountController.php'));
        $displayName = 'Alpha';
        $tenantName = 'Compagnie';
        $year = 2026;
        $dueOn = '2026-12-31';
        $pageUrl = 'https://exemple.test/jnet/moi/fiabilisation';
        $parts = require self::ROOT . '/views/emails/data_campaign_reminder.php';
        self::assertStringContainsString('Vérifier ma fiche', $parts['html']);
        self::assertStringContainsString('31/12/2026', $parts['text']);
        self::assertStringContainsString('https://exemple.test/jnet/moi/fiabilisation', $parts['text']);
    }

    public function testTheRecordShowsWhenItWasMadeReliableAndTheChronologyListsIt(): void
    {
        $events = MemberChronology::build(['data_campaigns' => [['confirmed_at' => '2026-12-05 09:00:00', 'campaign_year' => 2026, 'title' => 'Fiabilisation des données 2026']]]);
        self::assertCount(1, $events);
        self::assertSame('fiabilisation', $events[0]['type']);
        self::assertSame('2026-12-05', $events[0]['date']);
        self::assertArrayHasKey('fiabilisation', MemberChronology::TYPES);
        self::assertContains('fiabilisation', \App\Support\MemberFicheTabs::anchors('suivi-rh'));
        $view = (string) file_get_contents(self::ROOT . '/views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString('id="fiabilisation"', $view);
        self::assertStringContainsString('Fiabilisée le', $view);
    }

    // ------------------------------------------------------------------ migration

    public function testOpeningNotificationCarriesTheOriginAndTheStartDate(): void
    {
        $s = $this->service();
        $created = $s->createCampaign(self::TENANT, 1, ['year' => 2026, 'opens_on' => '2026-12-01', 'due_on' => '2026-12-31']);
        self::assertTrue($s->openCampaign(self::TENANT, 1, (int) $created['id'])['ok']);
        $open = array_values(array_filter($this->payloads, static fn (array $n): bool => $n['type'] === DataCampaignService::NOTIFY_OPEN));
        self::assertNotSame([], $open);
        self::assertSame('organization', $open[0]['payload']['origin']);
        self::assertSame('2026-12-01', $open[0]['payload']['opens_on']);
        self::assertSame('2026-12-31', $open[0]['payload']['due_on']);
        self::assertArrayHasKey('org', $open[0]['payload']);
    }

    public function testTheMigrationIsIdempotentReversibleAndRegistered(): void
    {
        $sql = (string) file_get_contents(self::ROOT . '/migrations/2026_11_15_090_data_campaigns.sql');
        $down = (string) file_get_contents(self::ROOT . '/migrations/rollback/data_campaigns_down.sql');
        foreach (DataCampaignRepository::TABLES as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table . ' ', $sql, $table);
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table . ';', $down, $table);
        }
        self::assertDoesNotMatchRegularExpression('/INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM/i', $sql, 'aucune donnée écrite par la migration');
        self::assertStringContainsString('auto_enabled TINYINT(1) NOT NULL DEFAULT 1', $sql, 'activée par défaut');
        self::assertStringContainsString('UNIQUE KEY uq_data_campaign_reminder (campaign_id, user_id, slot)', $sql, 'un rappel une seule fois');
        self::assertStringContainsString('UNIQUE KEY uq_data_campaign_member (campaign_id, user_id)', $sql);
        $step = (string) file_get_contents(self::ROOT . '/database/steps/4580_data_campaigns.php');
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString('runSqlFile', $step);
        self::assertDoesNotMatchRegularExpression('/INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM/i', $step);
        $registry = require self::ROOT . '/database/steps/registry.php';
        $entry = array_values(array_filter($registry, static fn (array $e): bool => $e['id'] === 'data_campaigns'));
        self::assertCount(1, $entry);
        self::assertSame('4580_data_campaigns.php', $entry[0]['file']);
        self::assertFileExists(self::ROOT . '/database/steps/' . $entry[0]['file']);
        self::assertTrue($entry[0]['rerun']);
        $files = array_column($registry, 'file');
        self::assertSame(count($files), count(array_unique($files)));
    }

    public function testScreensAreToleratedWhenTablesAreMissing(): void
    {
        $repo = new class extends DataCampaignRepository {
            public function __construct()
            {
                parent::__construct(null);
            }

            public function schemaReady(): bool
            {
                return false;
            }
        };
        self::assertSame([], $repo->listCampaigns(1));
        self::assertNull($repo->currentOpenCampaign(1));
        self::assertSame([], $repo->tenantsWithAutoEnabled());
        self::assertTrue($repo->settings(1)['auto_enabled'], 'défaut : ouverture automatique active');
        $service = new DataCampaignService($repo, $this->reader);
        self::assertNull($service->activeFor(1, 1));
        self::assertSame(0, $service->pendingBadge(1, 1));
        self::assertSame(0, $service->runScheduled()['opened']);
    }

    // ------------------------------------------------------------------ vues

    /** @return array<string, array{0: string}> */
    public static function viewFiles(): array
    {
        $out = [];
        foreach (['views/admin/member_situation/data_campaign.php', 'views/admin/organization/data_campaigns.php', 'views/admin/organization/data_campaign_show.php', 'views/emails/data_campaign_reminder.php', 'views/admin/effectifs_workspace/member.php'] as $file) {
            $out[$file] = [$file];
        }

        return $out;
    }

    /** Une vue qui appelle une classe l'importe (« use ») ou la nomme en entier : sinon c'est une erreur 500 en production. */
    #[\PHPUnit\Framework\Attributes\DataProvider('viewFiles')]
    public function testEveryViewImportsTheClassesItCalls(string $file): void
    {
        $code = (string) file_get_contents(self::ROOT . '/' . $file);
        $imported = [];
        if (preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $code, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $u) {
                $parts = explode('\\', $u[1]);
                $imported[($u[2] ?? '') !== '' ? $u[2] : (string) end($parts)] = true;
            }
        }
        // Appels « Classe:: » hors espace de noms complet, hors chaînes et commentaires simples.
        $stripped = (string) preg_replace('#/\*.*?\*/|//[^\n]*|\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"#s', '', $code);
        preg_match_all('/(?<![\\\\\w$>:\'"])([A-Z][A-Za-z0-9_]+)::/', $stripped, $calls);
        foreach (array_unique($calls[1]) as $class) {
            if (in_array($class, ['self', 'static', 'parent'], true)) {
                continue;
            }
            self::assertArrayHasKey($class, $imported, $file . ' appelle ' . $class . ' sans « use »');
        }
        // Une vue sans appel statique est valide : le test ne doit pas être signalé « risqué ».
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------ fiabilisation à la connexion

    public function testLoginPromptRulesNeverTriggerForGuestsImpersonationOrAjax(): void
    {
        $base = ['pending' => true, 'hasOrganization' => true, 'continueUrl' => '/public/jnet'];
        self::assertTrue(DataCampaignLoginPrompt::shouldPrompt($base));
        self::assertFalse(DataCampaignLoginPrompt::shouldPrompt(['pending' => false] + $base));
        self::assertFalse(DataCampaignLoginPrompt::shouldPrompt(['hasOrganization' => false] + $base), 'compte invité ou sans organisation');
        self::assertFalse(DataCampaignLoginPrompt::shouldPrompt(['impersonating' => true] + $base));
        self::assertFalse(DataCampaignLoginPrompt::shouldPrompt(['ajax' => true] + $base));
        self::assertFalse(DataCampaignLoginPrompt::shouldPrompt(['continueUrl' => '/public/jnet/moi/fiabilisation?etape=recap'] + $base), 'déjà sur la campagne');
    }

    public function testLoginPromptToneBecomesInsistentAfterTheDueDateButNeverBlocks(): void
    {
        self::assertSame('open', DataCampaignLoginPrompt::tone('2026-12-31', '2026-12-31'));
        self::assertSame('overdue', DataCampaignLoginPrompt::tone('2026-12-31', '2027-01-01'));
        self::assertSame('open', DataCampaignLoginPrompt::tone('', '2027-01-01'));
        // Après la date limite, la décision de redirection est la même : l'écran n'est jamais forcé en dehors de la connexion.
        self::assertTrue(DataCampaignLoginPrompt::shouldPrompt(['pending' => true, 'hasOrganization' => true]));
    }

    public function testBlockStatusAndSummaryDriveTheOverviewAndTheConfirmAllButton(): void
    {
        $ok = ['state' => 'ok', 'rows' => [['label' => 'A', 'value' => 'x']]];
        $todo = ['state' => '', 'rows' => [['label' => 'A', 'value' => 'x']]];
        $empty = ['state' => '', 'rows' => []];
        $blank = ['state' => '', 'rows' => [['label' => 'A', 'value' => ' — ']]];
        $flag = ['state' => 'flag', 'rows' => [['label' => 'A', 'value' => 'x']]];
        self::assertSame('ok', DataCampaignLoginPrompt::blockStatus($ok));
        self::assertSame('to_confirm', DataCampaignLoginPrompt::blockStatus($todo));
        self::assertSame('incomplete', DataCampaignLoginPrompt::blockStatus($empty));
        self::assertSame('incomplete', DataCampaignLoginPrompt::blockStatus($blank));
        self::assertSame('flagged', DataCampaignLoginPrompt::blockStatus($flag));
        $all = DataCampaignLoginPrompt::summarize(['a' => $ok, 'b' => $todo]);
        self::assertTrue($all['can_confirm_all']);
        self::assertSame(50, $all['percent']);
        self::assertFalse(DataCampaignLoginPrompt::summarize(['a' => $ok, 'b' => $empty])['can_confirm_all']);
        self::assertFalse(DataCampaignLoginPrompt::summarize(['a' => $flag])['can_confirm_all']);
        self::assertSame(0, DataCampaignLoginPrompt::summarize([])['percent']);
    }

    public function testConfirmAllAnswersEveryPartThenConfirmsTheFile(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s);
        $result = $s->confirmAll(self::TENANT, 11, $id);
        self::assertTrue($result['ok'], $result['message']);
        self::assertSame(0, $s->pendingBadge(self::TENANT, 11));
    }

    public function testConfirmAllRefusesWhenAPartIsIncompleteOrFlagged(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s);
        $this->reader->data[11]['identity'] = [];
        self::assertFalse($s->confirmAll(self::TENANT, 11, $id)['ok'], 'une partie vide est à compléter');
        $this->reader->data = [];
        $s->answer(self::TENANT, 12, $id, 'identity', 'flag', 'Mon prénom est mal écrit');
        self::assertFalse($s->confirmAll(self::TENANT, 12, $id)['ok']);
        self::assertFalse($s->confirmAll(self::TENANT, 11, $id + 50)['ok'], 'campagne inconnue');
    }

    public function testAMissingRequiredValueMakesTheBlockToCompleteWhateverTheLanguage(): void
    {
        $row = static fn (string $v, array $extra = []): array => ['state' => '', 'rows' => [['label' => 'Prénom', 'value' => $v] + $extra, ['label' => 'Nom', 'value' => 'Durand']]];
        // Le lecteur remplace l'absence par « Non renseigné » : c'est bien « À compléter », jamais « À confirmer ».
        self::assertSame('incomplete', DataCampaignLoginPrompt::blockStatus($row(DataCampaignPage::MISSING)));
        self::assertSame('incomplete', DataCampaignLoginPrompt::blockStatus($row('', ['missing' => true])));
        self::assertSame('incomplete', DataCampaignLoginPrompt::blockStatus($row('Alain', ['missing' => true, 'required' => true])));
        self::assertSame('to_confirm', DataCampaignLoginPrompt::blockStatus($row('Alain')));
        // Une valeur facultative absente ne bloque pas.
        self::assertSame('to_confirm', DataCampaignLoginPrompt::blockStatus($row(DataCampaignPage::MISSING, ['missing' => true, 'required' => false])));
        self::assertSame(['Prénom'], DataCampaignLoginPrompt::missingLabels($row(DataCampaignPage::MISSING)));
        self::assertTrue(DataCampaignPage::isMissingValue(null));
        self::assertTrue(DataCampaignPage::isMissingValue(' — '));
        self::assertFalse(DataCampaignPage::isMissingValue('Non renseigné par erreur'));
    }

    public function testTheReaderMarksRequiredAndOptionalRowsWithoutChangingTheStoredText(): void
    {
        $reader = new DataCampaignRecordReader();
        $identity = (new \ReflectionMethod($reader, 'identity'))->invoke($reader, [
            'user' => ['display_name' => 'Pseudo', 'callsign' => 'RAVEN-2'],
            'snapshot' => ['callsign' => 'RAVEN-2'],
            'profile' => [],
            'tenantId' => 1,
        ]);
        $byLabel = [];
        foreach ($identity['rows'] as $r) {
            $byLabel[$r['label']] = $r;
        }
        // Membre qui n'a qu'un indicatif : prénom et nom restent à compléter.
        self::assertTrue($byLabel['Prénom']['required'] && $byLabel['Prénom']['missing']);
        self::assertTrue($byLabel['Nom']['required'] && $byLabel['Nom']['missing']);
        self::assertSame('Non renseigné', $byLabel['Prénom']['value'], 'texte stocké inchangé : les empreintes déjà confirmées restent valides');
        self::assertFalse($byLabel['Surnom principal']['required']);
        self::assertFalse($byLabel['Indicatif']['missing']);
        self::assertSame('incomplete', DataCampaignLoginPrompt::blockStatus($identity));
        // Les lignes marquées ne changent pas l'empreinte d'un bloc.
        self::assertSame(DataCampaignSnapshot::hash([['label' => 'Prénom', 'value' => 'Non renseigné']]), DataCampaignSnapshot::hash([$byLabel['Prénom']]));

        $contact = (new \ReflectionMethod($reader, 'contact'))->invoke($reader, ['user' => ['email' => ''], 'snapshot' => [], 'profile' => [], 'tenantId' => 1]);
        self::assertSame(['Adresse e-mail du compte'], DataCampaignLoginPrompt::missingLabels($contact));
        $full = (new \ReflectionMethod($reader, 'contact'))->invoke($reader, ['user' => ['email' => 'a@b.fr'], 'snapshot' => [], 'profile' => [], 'tenantId' => 1]);
        self::assertSame('to_confirm', DataCampaignLoginPrompt::blockStatus($full));
    }

    public function testConfirmAllIsRefusedByTheServerWhileARequiredValueIsMissing(): void
    {
        $s = $this->service();
        $id = $this->openCampaign($s);
        $this->reader->data[11]['identity'] = ['Prénom' => 'Non renseigné', 'Nom' => 'Durand'];
        $result = $s->confirmAll(self::TENANT, 11, $id);
        self::assertFalse($result['ok']);
        self::assertStringContainsString('Prénom', $result['message'], 'le message dit quoi compléter');
        self::assertSame(1, $s->pendingBadge(self::TENANT, 11), 'rien n’a été confirmé');
        // Valeur présente : « Tout confirmer » repasse.
        $this->reader->data[11]['identity'] = ['Prénom' => 'Alain', 'Nom' => 'Durand'];
        self::assertTrue($s->confirmAll(self::TENANT, 11, $id)['ok']);
    }

    public function testTheLoginHookIsWiredWithoutBreakingTheReturnUrlOrTheJnetModal(): void
    {
        $auth = (string) file_get_contents(self::ROOT . '/app/Controllers/Auth/AuthController.php');
        $resolve = strpos($auth, '$this->resolvePostLoginContinueUrl();');
        $hook = strpos($auth, 'DataCampaignLoginPrompt::prepare($user, $continueUrl)');
        $arm = strpos($auth, 'LoginWelcomeGate::arm($continueUrl);');
        self::assertNotFalse($hook);
        self::assertTrue($resolve < $hook && $hook < $arm, 'la destination d\'origine est résolue avant, et gardée par la fiabilisation');
        $prompt = (string) file_get_contents(self::ROOT . '/app/Support/DataCampaignLoginPrompt.php');
        self::assertStringContainsString('Session::set(self::SESSION_CONTINUE, $continueUrl)', $prompt);
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("path('fiabilisation/plus-tard')", $routes);
        self::assertStringContainsString("path('fiabilisation/tout-confirmer')", $routes);
    }

    public function testTheViewsCarryNoSmallLabelAboveATitleAndNoEmoji(): void
    {
        foreach (['views/admin/member_situation/data_campaign.php', 'views/admin/organization/data_campaigns.php', 'views/admin/organization/data_campaign_show.php'] as $file) {
            $code = (string) file_get_contents(self::ROOT . '/' . $file);
            self::assertDoesNotMatchRegularExpression('/kicker|eyebrow|surtitre/i', $code, $file);
            self::assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $code, $file);
        }
    }
}
