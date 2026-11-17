<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseWantedNoticeController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedMetaRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedNoticeData;
use App\Services\Sse\SseWantedNoticePdfService;
use App\Services\Sse\SseWantedService;
use App\Support\SseWantedServiceAdmin;
use App\Support\SseWantedServices;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseWantedSqlite;

/**
 * Processus d'émission des avis : service demandeur gardé avec l'avis, contrôles de cohérence à la pose, circuit de validation,
 * renouvellement, prolongation, levée avec le service lévant, jeu (format additif), PDF, droits, jeton CSRF et cloisonnement des
 * communautés. Vraie base (sqlite en mémoire, tables créées depuis les fichiers SQL des migrations).
 */
final class SseWantedProcessTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    /** @var list<array{0: int, 1: int, 2: array<string, mixed>}> */
    private array $notified = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        \App\Core\Csrf::token();
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'RESPONSABLE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
        $this->notified = [];
        Gate::reset();
    }

    protected function tearDown(): void
    {
        SseWantedSqlite::close();
        $_POST = $this->saved['post'];
        $_GET = $this->saved['get'];
        $_SERVER = $this->saved['server'];
        if ($this->saved['session'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved['session'];
        }
        Gate::reset();
    }

    private static function admin(int $tenant = 1, int $user = 7): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, $tenant, $user, 'RESPONSABLE', true, true);
    }

    private static function analyst(int $tenant = 1, int $user = 8): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, $tenant, $user, 'ANALYSTE', true, false);
    }

    private function identity(int $tenant = 1, string $last = 'Karimzadeh'): int
    {
        return (new SseIdentityRepository())->create($tenant, ['last_name' => $last, 'first_name' => 'Élodie', 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK']);
    }

    private function service(): SseWantedService
    {
        return new SseWantedService(null, null, null, null, null, null, null, function (int $tenant, int $user, array $payload): void {
            $this->notified[] = [$tenant, $user, $payload];
        }, static fn (int $tenant, int $identity, ?int $author, bool $managersOnly): array => $managersOnly ? [7, 9] : [7, 8, 9, 10]);
    }

    /** @return array<string, mixed> */
    private static function input(array $extra = []): array
    {
        return $extra + [
            'notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'no', 'reason' => 'Participation présumée à une attaque coordonnée.',
            'zone' => 'Corne de l’Est', 'service_code' => 'SVC-CTC', 'classification' => 'confidentiel', 'lang' => 'fr',
        ];
    }

    public function testAmendmentKeepsBeforeAfterAndEndDateAndRejectsStaleChanges(): void
    {
        $identity = $this->identity();
        $svc = $this->service();
        $placed = $svc->place(1, $identity, self::input(), self::admin());
        self::assertTrue($placed['ok'], json_encode($placed));
        $repo = new SseWantedNoticeRepository();
        $before = $repo->find(1, $placed['id']);
        $version = SseWantedService::amendToken($before);
        $end = gmdate('Y-m-d H:i:s', time() + 86400 * 20);
        $updated = $svc->amend(1, $placed['id'], ['version' => $version, 'change_reason' => 'Nouvelle observation confirmée',
            'last_seen_place' => 'Marché nord', 'expires_at' => $end], self::admin());
        self::assertTrue($updated['ok'], json_encode($updated));
        $after = $repo->find(1, $placed['id']);
        self::assertSame($before['ref'], $after['ref']);
        self::assertSame('Marché nord', $after['last_seen_place']);
        self::assertSame($end, $after['expires_at']);
        $events = (new \App\Repositories\SseIdentityEventRepository())->listForIdentity(1, $identity, ['notice.updated']);
        self::assertCount(1, $events);
        self::assertSame($before, $events[0]['details']['before']);
        self::assertSame($after, $events[0]['details']['after']);
        self::assertSame('conflict', $svc->amend(1, $placed['id'], ['version' => $version, 'change_reason' => 'Autre observation'], self::admin())['error']);
        self::assertSame('forbidden', $svc->amend(1, $placed['id'], ['version' => $version, 'change_reason' => 'Autre observation'], self::admin(2))['error']);
        self::assertSame('invalid', $svc->amend(1, $placed['id'], ['version' => SseWantedService::amendToken($after), 'change_reason' => 'Date incorrecte', 'expires_at' => 'hier'], self::admin())['error']);
        self::assertSame($after, $repo->find(1, $placed['id']));
    }

    public function testAmendmentRollsBackWhenHistoryCannotBeWritten(): void
    {
        $identity = $this->identity();
        $svc = $this->service();
        $placed = $svc->place(1, $identity, self::input(), self::admin());
        $repo = new SseWantedNoticeRepository();
        $before = $repo->find(1, $placed['id']);
        \App\Repositories\SseIdentityEventRepository::ready();
        $this->pdo->exec('DROP TABLE sse_identity_events');
        $result = $svc->amend(1, $placed['id'], ['version' => SseWantedService::amendToken($before), 'change_reason' => 'Nouvelle donnée', 'zone' => 'Autre zone'], self::admin());
        self::assertFalse($result['ok']);
        self::assertSame($before, $repo->find(1, $placed['id']));
    }

    public function testEditPageShowsTheStoredVersionAndReadableHistory(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $identity = $this->identity();
        $service = $this->service();
        $placed = $service->place(1, $identity, self::input(), self::admin());
        $repo = new SseWantedNoticeRepository();
        $before = $repo->find(1, $placed['id']);
        $updated = $service->amend(1, $placed['id'], [
            'version' => SseWantedService::amendToken($before), 'change_reason' => 'Nouveau signalement reçu',
            'expires_at' => '2040-01-01 12:00:00', 'physical' => ['marks' => 'Cicatrice sur la joue'],
        ], self::admin());
        self::assertTrue($updated['ok']);
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::admin());
        $response = $controller->editForm(new Request(), ['id' => $placed['id']]);
        self::assertStringContainsString('name="expires_at"', $response->body());
        self::assertStringContainsString('Nouveau signalement reçu', $response->body());
        self::assertStringContainsString('Cicatrice sur la joue', $response->body());
        self::assertStringContainsString('RESPONSABLE', $response->body());
        $current = $repo->find(1, $placed['id']);
        self::assertTrue($service->amend(1, $placed['id'], [
            'version' => SseWantedService::amendToken($current), 'change_reason' => 'Recherche sans échéance', 'expires_at' => '',
        ], self::admin())['ok']);
        self::assertNull($repo->find(1, $placed['id'])['expires_at']);
        $this->enableValidation();
        $current = $repo->find(1, $placed['id']);
        self::assertTrue($service->amend(1, $placed['id'], [
            'version' => SseWantedService::amendToken($current), 'change_reason' => 'Nouvelle piste à valider', 'zone' => 'Dépôt nord',
        ], self::analyst())['ok']);
        self::assertSame('pending', $this->meta($placed['id'])['state']);
    }

    private function enableValidation(bool $on = true): void
    {
        $repo = new SseWantedSettingsRepository();
        $config = $repo->get(1);
        $config['process'] = SseWantedSettingsRepository::normalizeProcess(['validation' => $on, 'timezone' => 'Europe/Paris', 'notify' => true]);
        $repo->save(1, $config, 7);
    }

    /** @return array<string, mixed> */
    private function meta(int $noticeId): array
    {
        return (new SseWantedMetaRepository())->forNotice(1, $noticeId);
    }

    // ------------------------------------------------------------------ Service gardé avec l'avis

    public function testPlacingWithAServiceStoresItsCodeSnapshotAndPrefilledText(): void
    {
        $id = $this->identity();
        $res = $this->service()->place(1, $id, self::input(), self::admin());
        self::assertTrue($res['ok'], implode(' ', $res['errors'] ?? []));
        self::assertSame('live', $res['state']);
        $notice = (new SseWantedNoticeRepository())->find(1, $res['id']);
        self::assertStringContainsString('JCTC', (string) $notice['contact_label'], 'Le texte final du service est écrit dans l’avis');
        self::assertStringContainsString('FALCON NET', (string) $notice['contact_detail']);
        self::assertSame('Cellule de contre-terrorisme interarmées', $notice['authority']);
        $meta = $this->meta($res['id']);
        self::assertSame('SVC-CTC', $meta['service_code']);
        self::assertSame('confidentiel', $meta['classification']);
        self::assertSame('JCTC', $meta['service']['ac']);
        self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $meta['service']['color']);
        self::assertSame('live', $meta['state']);
        self::assertSame(7, $meta['submitted_by']);
        self::assertNotNull($notice['expires_at'], 'La durée de validité du service s’applique');
        $days = (int) round((strtotime((string) $notice['expires_at'] . ' UTC') - strtotime((string) $notice['issued_at'] . ' UTC')) / 86400);
        self::assertSame(14, $days);
        self::assertSame('SVC-CTC', (new SseWantedMetaRepository())->lastServiceCode(1, 7), 'Le dernier service utilisé est mémorisé');
        self::assertSame(1, (new SseWantedMetaRepository())->countUsing(1, 'SVC-CTC'));
    }

    public function testTheOtherEntryStaysFreeButGoesThroughTheRealNameCheck(): void
    {
        $id = $this->identity();
        $free = $this->service()->place(1, $id, self::input(['service_code' => '', 'contact_label' => 'Cellule du Détroit', 'contact_detail' => 'Canal 41']), self::admin());
        self::assertTrue($free['ok']);
        self::assertNull($this->meta($free['id'])['service_code']);
        self::assertSame('Cellule du Détroit', (new SseWantedNoticeRepository())->find(1, $free['id'])['contact_label']);
        $other = $this->identity(1, 'Autre');
        $bad = $this->service()->place(1, $other, self::input(['service_code' => '', 'contact_label' => 'Bureau du FBI']), self::admin());
        self::assertFalse($bad['ok']);
        self::assertStringContainsString('organisation réelle', implode(' ', $bad['errors']));
        $unknown = $this->service()->place(1, $other, self::input(['service_code' => 'SVC-INCONNU']), self::admin());
        self::assertFalse($unknown['ok']);
        self::assertStringContainsString('inconnu', implode(' ', $unknown['errors']));
    }

    public function testAServiceThatCannotIssueAtTheRequestedClassificationBlocksTheNotice(): void
    {
        $id = $this->identity();
        $res = $this->service()->place(1, $id, self::input(['service_code' => 'SVC-NGO-SEC', 'classification' => 'confidentiel']), self::admin());
        self::assertFalse($res['ok']);
        self::assertSame('checks', $res['error']);
        self::assertStringContainsString('HHA-SEC', $res['errors'][0]);
        self::assertStringContainsString('ne peut pas émettre au niveau', $res['errors'][0]);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_notices')->fetchColumn(), 'Rien n’est écrit');
    }

    public function testCoherenceChecksAreEnforcedByTheServerNotJustTheForm(): void
    {
        $id = $this->identity();
        $svc = $this->service();
        $past = $svc->place(1, $id, self::input(['issued_at' => '2026-01-01 10:00:00', 'expires_at' => '2026-01-05 10:00:00']), self::admin());
        self::assertFalse($past['ok']);
        $order = $svc->place(1, $id, self::input(['issued_at' => '2036-01-10 10:00:00', 'expires_at' => '2036-01-05 10:00:00']), self::admin());
        self::assertFalse($order['ok']);
        $member = $svc->place(1, $id, self::input(), self::admin(), ['is_member' => true]);
        self::assertFalse($member['ok']);
        self::assertStringContainsString('membre', implode(' ', $member['errors']));
        $noReason = $svc->place(1, $id, self::input(['reason' => '']), self::admin());
        self::assertFalse($noReason['ok']);
        $armed = $svc->place(1, $id, self::input(['armed' => 'yes', 'zone' => '']), self::admin());
        self::assertTrue($armed['ok'], 'Armé sans zone : avertissement seulement');
        self::assertStringContainsString('armé et dangereux', implode(' ', $armed['warnings']));
        $second = $svc->place(1, $id, self::input(), self::admin());
        self::assertFalse($second['ok']);
        self::assertSame('exists', $second['error']);
    }

    // ------------------------------------------------------------------ Circuit de validation

    public function testWithTheCircuitOnAnAnalystNoticeWaitsForAManagerAndIsNeitherBroadcastNorDetected(): void
    {
        $this->enableValidation();
        $id = $this->identity();
        $res = $this->service()->place(1, $id, self::input(), self::analyst());
        self::assertTrue($res['ok']);
        self::assertSame('pending', $res['state']);
        self::assertSame('pending', $this->meta($res['id'])['state']);
        self::assertSame([7, 9], array_column($this->notified, 1), 'Les gestionnaires sont prévenus, pas les abonnés');
        self::assertSame(['validate', 'validate'], array_column(array_column($this->notified, 2), 'event'));
        self::assertSame([$res['id']], (new SseWantedMetaRepository())->pendingIds(1), 'File « À valider »');
        self::assertSame([], SseWantedNoticeData::forGame(1), 'Le jeu ne voit pas un avis à valider');
        self::assertSame(['wanted' => false], (new \App\Services\Sse\SseWantedDetectionService())->wantedPayload(1, $id), 'Pas d’alerte avant la diffusion');
        self::assertNull((new \App\Services\Sse\SseWantedDetectionService())->report(1, $id, 'id_check', ['source' => 'game']), 'Pas de détection avant la diffusion');
        $again = $this->service()->place(1, $id, self::input(), self::analyst());
        self::assertSame('exists', $again['error'], 'Un avis à valider tient la place : jamais deux avis sur une identité');
    }

    public function testAManagerPlacesDirectlyEvenWithTheCircuitOnAndOthersValidateOrRefuseWithAReason(): void
    {
        $this->enableValidation();
        $id = $this->identity();
        $direct = $this->service()->place(1, $id, self::input(), self::admin());
        self::assertSame('live', $direct['state']);
        self::assertCount(1, SseWantedNoticeData::forGame(1));

        $other = $this->identity(1, 'Okoye');
        $pending = $this->service()->place(1, $other, self::input(), self::analyst());
        $this->notified = [];
        $svc = $this->service();
        self::assertFalse($svc->decide(1, $pending['id'], true, '', self::analyst())['ok'], 'Un analyste ne valide pas');
        self::assertSame('self', $svc->decide(1, $pending['id'], true, '', new SseWantedAccess(false, true, true, true, 1, 8, 'ANALYSTE', true, true))['error'], 'On ne valide pas son propre avis');
        self::assertSame('reason', $svc->decide(1, $pending['id'], false, '', self::admin())['error'], 'Un refus demande un motif');
        $ok = $svc->decide(1, $pending['id'], true, '', self::admin());
        self::assertTrue($ok['ok']);
        self::assertSame('live', $this->meta($pending['id'])['state']);
        self::assertSame(7, $this->meta($pending['id'])['validated_by']);
        self::assertCount(2, SseWantedNoticeData::forGame(1), 'Diffusé au jeu');
        $events = array_column(array_column($this->notified, 2), 'event');
        self::assertContains('validated', $events, 'L’auteur est prévenu');
        self::assertContains('placed', $events, 'Les abonnés sont prévenus à la diffusion');
        self::assertSame('state', $svc->decide(1, $pending['id'], true, '', self::admin())['error'], 'Une décision ne se rejoue pas');

        $third = $this->identity(1, 'Mbeki');
        $p3 = $this->service()->place(1, $third, self::input(), self::analyst());
        $refused = $svc->decide(1, $p3['id'], false, 'Source non recoupée.', self::admin());
        self::assertTrue($refused['ok']);
        self::assertSame('rejected', $this->meta($p3['id'])['state']);
        $row = (new SseWantedNoticeRepository())->find(1, $p3['id']);
        self::assertSame('lifted', $row['status'], 'Un avis refusé est clos : il ne reste jamais actif sans avoir été diffusé');
        self::assertStringContainsString('Source non recoupée', (string) $row['closed_reason']);
        self::assertCount(2, SseWantedNoticeData::forGame(1));
        $timeline = (string) $this->pdo->query("SELECT GROUP_CONCAT(event_type) FROM sse_identity_events WHERE identity_id = {$third}")->fetchColumn();
        self::assertStringContainsString('notice.submitted', $timeline);
        self::assertStringContainsString('notice.rejected', $timeline);
    }

    // ------------------------------------------------------------------ Renouvellement, prolongation, levée

    public function testRenewalCreatesALinkedNoticeWithTheSameServiceCodesAndNewDates(): void
    {
        $id = $this->identity();
        $first = $this->service()->place(1, $id, self::input(['codes_reasons' => ['M-TER-01'], 'codes_measures' => ['A-DNG'], 'physical' => ['height_cm' => '181']]), self::admin());
        $res = $this->service()->renew(1, $first['id'], [], self::admin());
        self::assertTrue($res['ok'], implode(' ', $res['errors'] ?? []));
        self::assertNotSame($first['id'], $res['id']);
        $repo = new SseWantedNoticeRepository();
        self::assertSame('expired', $repo->find(1, $first['id'])['status']);
        self::assertStringContainsString('Renouvelé', (string) $repo->find(1, $first['id'])['closed_reason']);
        self::assertSame('active', $repo->find(1, $res['id'])['status']);
        $meta = $this->meta($res['id']);
        self::assertSame($first['id'], $meta['parent_notice_id']);
        self::assertSame('SVC-CTC', $meta['service_code']);
        self::assertSame('confidentiel', $meta['classification']);
        $extras = (new \App\Repositories\SseWantedExtrasRepository())->forNotice(1, $res['id']);
        self::assertSame(['M-TER-01'], array_column($extras['codes']['reasons'], 'code'));
        self::assertSame('181', (string) ($extras['physical']['height_cm'] ?? ''));
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_wanted_notices WHERE status = 'active'")->fetchColumn(), 'Un seul avis actif');
        self::assertStringContainsString('notice.renewed', (string) $this->pdo->query("SELECT GROUP_CONCAT(event_type) FROM sse_identity_events WHERE identity_id = {$id}")->fetchColumn());
        // Un avis levé ne se renouvelle pas ; dates incohérentes refusées sans toucher à l’avis en place.
        $bad = $this->service()->renew(1, $res['id'], ['expires_at' => '2020-01-01 10:00:00'], self::admin());
        self::assertFalse($bad['ok']);
        self::assertSame('active', $repo->find(1, $res['id'])['status'], 'L’avis en place reste actif');
        $this->service()->lift(1, $res['id'], 'lifted', 'Identité confondue.', self::admin());
        self::assertSame('closed', $this->service()->renew(1, $res['id'], [], self::admin())['error']);
    }

    public function testAnExpiredNoticeIsRenewedAndTheRenewalFollowsTheCircuit(): void
    {
        $this->enableValidation();
        $id = $this->identity();
        $first = $this->service()->place(1, $id, self::input(), self::admin());
        $this->pdo->exec("UPDATE sse_wanted_notices SET status = 'expired', active_key = NULL WHERE id = " . (int) $first['id']);
        $res = $this->service()->renew(1, $first['id'], [], self::analyst());
        self::assertTrue($res['ok']);
        self::assertSame('pending', $this->meta($res['id'])['state'], 'Le renouvellement d’un analyste attend lui aussi sa validation');
    }

    public function testAnExtensionNeedsAReasonAFutureLaterDateAndKeepsTheVersionHistory(): void
    {
        $id = $this->identity();
        $first = $this->service()->place(1, $id, self::input(), self::admin());
        $svc = $this->service();
        $current = (string) (new SseWantedNoticeRepository())->find(1, $first['id'])['expires_at'];
        $later = gmdate('Y-m-d H:i:s', strtotime($current . ' UTC') + 20 * 86400);
        self::assertSame('reason', $svc->extend(1, $first['id'], $later, '', self::admin())['error']);
        self::assertSame('invalid', $svc->extend(1, $first['id'], $current, 'Même date', self::admin())['error']);
        self::assertSame('invalid', $svc->extend(1, $first['id'], 'pas une date', 'Motif', self::admin())['error']);
        $ok = $svc->extend(1, $first['id'], $later, 'Nouvelle piste signalée par la cellule de théâtre.', self::admin());
        self::assertTrue($ok['ok']);
        self::assertSame($later, (new SseWantedNoticeRepository())->find(1, $first['id'])['expires_at']);
        $history = $this->meta($first['id'])['history'];
        self::assertCount(1, $history);
        self::assertSame($current, $history[0]['from']);
        self::assertSame($later, $history[0]['to']);
        self::assertStringContainsString('Nouvelle piste', $history[0]['reason']);
        self::assertStringContainsString('notice.extended', (string) $this->pdo->query("SELECT GROUP_CONCAT(event_type) FROM sse_identity_events WHERE identity_id = {$id}")->fetchColumn());
        self::assertFalse((new SseWantedService())->extend(1, $first['id'], $later, 'Motif', new SseWantedAccess(false, false, false, false, 1, 7))['ok']);
    }

    public function testLiftingNotesTheLiftingServiceAndRefusesAnUnknownOne(): void
    {
        $id = $this->identity();
        $first = $this->service()->place(1, $id, self::input(), self::admin());
        self::assertSame('service', $this->service()->lift(1, $first['id'], 'lifted', 'Identité confondue.', self::admin(), 'SVC-INCONNU')['error']);
        self::assertSame('active', (new SseWantedNoticeRepository())->find(1, $first['id'])['status']);
        $ok = $this->service()->lift(1, $first['id'], 'captured', 'Sujet capturé au point de contrôle.', self::admin(), 'SVC-MCIU');
        self::assertTrue($ok['ok']);
        self::assertSame('SVC-MCIU', $this->meta($first['id'])['closing_service_code']);
        self::assertSame(1, (new SseWantedMetaRepository())->countUsing(1, 'SVC-MCIU'), 'Un service lévant compte comme utilisé : jamais supprimé');
        self::assertStringContainsString('MCIU', (string) $this->pdo->query("SELECT summary FROM sse_identity_events WHERE event_type = 'notice.captured'")->fetchColumn());
    }

    // ------------------------------------------------------------------ Mode dégradé, jeu, PDF

    public function testWithoutTheProcessTableTheNoticeIsPlacedWithItsServiceTextAndAClearMessage(): void
    {
        $this->pdo->exec('DROP TABLE sse_wanted_notice_meta');
        SseWantedMetaRepository::forgetReady();
        self::assertFalse(SseWantedMetaRepository::ready());
        $this->enableValidation();
        $id = $this->identity();
        $res = $this->service()->place(1, $id, self::input(), self::analyst());
        self::assertTrue($res['ok'], 'L’avis est placé');
        self::assertSame('live', $res['state'], 'Sans la table, le circuit ne peut pas suivre l’avis : il est diffusé, et l’écran le dit');
        self::assertStringContainsString('processus des avis', implode(' ', $res['warnings']));
        self::assertStringContainsString('JCTC', (string) (new SseWantedNoticeRepository())->find(1, $res['id'])['contact_label']);
        self::assertSame(0, (new SseWantedMetaRepository())->countUsing(1, 'SVC-CTC'));
        $game = SseWantedNoticeData::forGame(1);
        self::assertCount(1, $game);
        self::assertSame('', $game[0]['service']['code']);
        self::assertStringContainsString('JCTC', $game[0]['service']['fr'], 'Le jeu reçoit le texte du service');
        $renew = $this->service()->renew(1, $res['id'], [], self::admin());
        self::assertTrue($renew['ok']);
        self::assertStringContainsString('processus des avis', implode(' ', $renew['warnings']));
    }

    public function testTheGameListIsAdditiveAndCarriesTheServiceCode(): void
    {
        $id = $this->identity();
        $this->service()->place(1, $id, self::input(), self::admin());
        $game = SseWantedNoticeData::forGame(1);
        self::assertCount(1, $game);
        $n = $game[0];
        foreach (['id', 'ref', 'name', 'type', 'threat', 'armed', 'zone', 'reasons', 'measures', 'physical', 'images'] as $existing) {
            self::assertArrayHasKey($existing, $n, 'Le format existant ne change pas');
        }
        self::assertSame('SVC-CTC', $n['service']['code']);
        self::assertSame('Cellule de contre-terrorisme interarmées', $n['service']['fr']);
        self::assertSame('Joint Counter-Terrorism Cell', $n['service']['en']);
        self::assertSame('JCTC', $n['service']['ac']);
        self::assertStringContainsString('555 0121', $n['service']['contact']);
        self::assertSame('confidentiel', $n['classification']);
        self::assertSame([], SseWantedNoticeData::forGame(2), 'Une autre communauté ne voit rien');
    }

    public function testThePdfDataCarryTheServiceNameAcronymContactAndHeaderColour(): void
    {
        $id = $this->identity();
        $res = $this->service()->place(1, $id, self::input(), self::admin());
        $notice = (new SseWantedNoticeRepository())->find(1, $res['id']);
        $data = (new SseWantedNoticePdfService())->data(1, $notice, 'fr');
        self::assertNotNull($data);
        self::assertSame('Cellule de contre-terrorisme interarmées', $data['authority'][0]);
        self::assertSame('Joint Counter-Terrorism Cell', $data['authority'][1]);
        self::assertStringContainsString('JCTC', $data['contact_label']);
        self::assertStringContainsString('FALCON NET', $data['contact_detail']);
        self::assertSame('SVC-CTC', $data['service']['code']);
        self::assertSame('JCTC', $data['service']['ac']);
        self::assertCount(3, $data['service']['color']);
        // Sans service : l'autorité de la communauté et aucune couleur de service.
        $other = $this->identity(1, 'Autre');
        $free = $this->service()->place(1, $other, self::input(['service_code' => '', 'contact_label' => 'Cellule libre']), self::admin());
        $d2 = (new SseWantedNoticePdfService())->data(1, (new SseWantedNoticeRepository())->find(1, $free['id']), 'fr');
        self::assertNull($d2['service']);
        if (class_exists(\TCPDF::class)) {
            $built = (new SseWantedNoticePdfService())->build(1, $notice, 'fr');
            self::assertNotNull($built);
            self::assertStringStartsWith('%PDF', $built['bytes']);
        }
    }

    // ------------------------------------------------------------------ Droits, jeton CSRF, tenant

    public function testServiceManagementNeedsTheManageLevelTheCsrfTokenAndStaysInTheSessionCommunity(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = ['op' => 'create', 'fr' => 'Cellule des opérations du Détroit', 'en' => 'Strait Operations Cell', 'ac' => 'COD', 'family' => 'theatre', 'roles' => ['request'], 'tel' => '+1 555 0142', 'mail' => 'veille@cod.invalid', 'max' => 'confidentiel', 'class' => 'encadrement', 'thr' => 'high', 'exp' => '21', 'color' => 'teal', 'emblem' => 'ring'];
        $settings = new SseWantedSettingsRepository();

        $_POST = $form + ['_csrf_token' => 'mauvais'];
        $admin = new SseWantedNoticeController(null, null, null, null, null, null, null, self::admin());
        $admin->servicesSave(new Request());
        self::assertSame(\App\Controllers\Web\SseWantedNoticeController::TOKEN_EXPIRED, \App\Core\Session::getFlash('error'));
        self::assertSame([], $settings->get(1)['services']['custom']);

        $_POST = $form + ['_csrf_token' => 'jeton'];
        $analyst = new SseWantedNoticeController(null, null, null, null, null, null, null, self::analyst());
        $analyst->servicesSave(new Request());
        self::assertSame(SseWantedNoticeController::NEED_ADMIN, \App\Core\Session::getFlash('error'), 'Sans le niveau Gérer, le message nomme le droit');
        self::assertSame([], $settings->get(1)['services']['custom']);

        $guest = new SseWantedNoticeController(null, null, null, null, null, null, null, new SseWantedAccess(true, false, false, false, 1, null));
        $guest->servicesSave(new Request());
        self::assertSame([], $settings->get(1)['services']['custom']);

        $admin->servicesSave(new Request());
        self::assertArrayHasKey('CSV-COD', $settings->get(1)['services']['custom']);
        self::assertSame([], $settings->get(2)['services']['custom'], 'Les services d’une communauté ne sont jamais ceux d’une autre');

        // Exportation : réservée aux gestionnaires, JSON téléchargé.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        self::assertSame(302, $analyst->servicesExport(new Request())->statusCode());
        $export = $admin->servicesExport(new Request());
        self::assertSame(200, $export->statusCode());
        self::assertStringContainsString('comspec.wanted-services', $export->body());
        self::assertStringContainsString('attachment', (string) $export->headerValue('Content-Disposition'));

        // Un service utilisé par un avis est désactivé, jamais supprimé.
        $id = $this->identity();
        $this->service()->place(1, $id, self::input(['service_code' => 'CSV-COD', 'classification' => 'encadrement']), self::admin());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['op' => 'delete', 'code' => 'CSV-COD', '_csrf_token' => 'jeton'];
        $admin->servicesSave(new Request());
        $cfg = $settings->get(1);
        self::assertArrayHasKey('CSV-COD', $cfg['services']['custom'], 'Jamais de perte');
        self::assertFalse($cfg['services']['custom']['CSV-COD']['enabled']);
    }

    public function testLifecycleActionsNeedCsrfRightsAndTheNoticeMustBelongToTheSessionCommunity(): void
    {
        $this->enableValidation();
        $id = $this->identity();
        $other = $this->identity(2, 'Autrecommunaute');
        $mine = $this->service()->place(1, $id, self::input(), self::analyst());
        $foreign = $this->service()->place(2, $other, self::input(['service_code' => 'SVC-CTC']), self::admin(2, 70));
        self::assertTrue($foreign['ok']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $admin = new SseWantedNoticeController(null, null, null, null, null, null, null, self::admin());
        $analyst = new SseWantedNoticeController(null, null, null, null, null, null, null, self::analyst());

        // Jeton absent ou faux : rien ne change.
        $_POST = ['decision' => 'approve', '_csrf_token' => 'mauvais'];
        $admin->decide(new Request(), ['id' => $mine['id']]);
        self::assertSame('pending', $this->meta($mine['id'])['state']);
        // Sans le niveau Gérer : refus motivé.
        $_POST = ['decision' => 'approve', '_csrf_token' => 'jeton'];
        $analyst->decide(new Request(), ['id' => $mine['id']]);
        self::assertSame('pending', $this->meta($mine['id'])['state']);
        self::assertStringContainsString('réservée aux gestionnaires', (string) \App\Core\Session::getFlash('error'));
        // IDOR : l'avis d'une autre communauté est introuvable, rien n'est touché.
        foreach (['decide' => ['decision' => 'reject', 'reason' => 'Non'], 'renew' => [], 'extend' => ['expires_at' => '2040-01-01T10:00', 'reason' => 'Motif']] as $action => $post) {
            $_POST = $post + ['_csrf_token' => 'jeton'];
            $admin->{$action}(new Request(), ['id' => $foreign['id']]);
            self::assertSame('Avis introuvable.', \App\Core\Session::getFlash('error'), $action);
        }
        $rows = (new SseWantedNoticeRepository())->find(2, $foreign['id']);
        self::assertSame('active', $rows['status']);
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sse_wanted_notices WHERE tenant_id = 2 AND id <> {$foreign['id']}")->fetchColumn());
        // Un responsable valide : la file se vide.
        $_POST = ['decision' => 'approve', '_csrf_token' => 'jeton'];
        $admin->decide(new Request(), ['id' => $mine['id']]);
        self::assertSame('live', $this->meta($mine['id'])['state']);
        // Prolongation par le contrôleur : l'heure saisie est celle de la communauté, gardée en UTC.
        $_POST = ['expires_at' => '2040-01-01T10:00', 'reason' => 'Piste confirmée.', '_csrf_token' => 'jeton'];
        $admin->extend(new Request(), ['id' => $mine['id']]);
        self::assertSame('2040-01-01 09:00:00', (new SseWantedNoticeRepository())->find(1, $mine['id'])['expires_at'], 'Europe/Paris : 10 h locale = 9 h UTC en janvier');
    }

    public function testLocalDatesAreConvertedFromTheCommunityTimeZoneToUtc(): void
    {
        self::assertSame('2026-07-01 08:30:00', SseWantedNoticeController::localToUtc('2026-07-01T10:30', 'Europe/Paris'));
        self::assertSame('2026-01-01 09:30:00', SseWantedNoticeController::localToUtc('2026-01-01 10:30', 'Europe/Paris'));
        self::assertSame('2026-01-01 15:30:00', SseWantedNoticeController::localToUtc('2026-01-01T10:30', 'America/New_York'));
        self::assertSame('', SseWantedNoticeController::localToUtc('', 'Europe/Paris'));
        self::assertSame('n’importe quoi', SseWantedNoticeController::localToUtc('n’importe quoi', 'Europe/Paris'), 'Une saisie illisible est renvoyée au contrôle du service');
        self::assertSame('2026-01-01 10:30:00', SseWantedNoticeController::localToUtc('2026-01-01T10:30', 'Zone/Inconnue'));
    }

    public function testViewsRenderTheServicePickerTheServicesTabAndTheLifecycleForReal(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $config = (new SseWantedSettingsRepository())->get(1);
        $config = SseWantedServiceAdmin::create($config, ['fr' => 'Cellule des opérations du Détroit', 'en' => 'Strait Operations Cell', 'ac' => 'COD', 'family' => 'theatre', 'roles' => ['request'], 'max' => 'confidentiel', 'class' => 'encadrement', 'color' => 'teal'])['config'];
        $config['process'] = SseWantedSettingsRepository::normalizeProcess(['validation' => true]);
        (new SseWantedSettingsRepository())->save(1, $config, 7);
        $id = $this->identity();
        $other = $this->identity(1, 'Okoye');
        $this->service()->place(1, $other, self::input(), self::analyst());
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::admin());

        $form = $controller->placeForm(new Request(), ['id' => $id]);
        self::assertSame(200, $form->statusCode());
        $body = $form->body();
        foreach (['Émission', 'name="service_code"', '<optgroup', 'Cellule interarmées de fusion du renseignement', 'Cellule des opérations du Détroit', 'Autre… (saisie ponctuelle)', 'data-ws-recap', 'id="ws-data"', 'Classification de l’avis', 'data-ws-noexp', 'sse-wanted-services.js'] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        self::assertStringNotContainsString('Service et dates', $body);
        self::assertStringNotContainsString('Warning', $body);
        self::assertStringContainsString('Proposer à la validation', (new SseWantedNoticeController(null, null, null, null, null, null, null, self::analyst()))->placeForm(new Request(), ['id' => $id])->body(), 'Le bouton annonce le circuit de validation');

        $settingsPage = $controller->settings(new Request())->body();
        foreach (['id="wc-pane-services"', 'Nouveau service', 'Importer', 'Exporter', 'SVC-JIFC', 'CSV-COD', 'data-svc-modal', 'Circuit de validation', 'name="process_timezone"'] as $needle) {
            self::assertStringContainsString($needle, $settingsPage, $needle);
        }
        $readOnly = (new SseWantedNoticeController(null, null, null, null, null, null, null, self::analyst()))->settings(new Request())->body();
        self::assertStringContainsString('SVC-JIFC', $readOnly);
        self::assertStringNotContainsString('data-svc-new', $readOnly, 'Sans le niveau Gérer, aucun bouton de création');
        self::assertStringNotContainsString('data-svc-save', $readOnly, 'Lecture seule : aucun bouton d’enregistrement');

        $list = (function () use ($controller): string {
            $_GET = ['statut' => 'a_valider'];

            return $controller->index(new Request())->body();
        })();
        self::assertStringContainsString('À valider', $list);
        self::assertStringContainsString('KARIMZADEH' === '' ? '' : 'OKOYE', $list);

        $fiche = (new \App\Controllers\Web\SseIdentityRegistryController())->identityShow(new Request(), ['id' => $other])->body();
        foreach (['En attente de validation', 'Valider et diffuser', 'Refuser', 'Prolonger', 'Renouveler', 'Lever ou clore', 'name="service_code"'] as $needle) {
            self::assertStringContainsString($needle, $fiche, $needle);
        }
    }

    public function testProcessMigrationStepIsRegisteredWithItsSqlAndRollbackAndStaysSqliteCompatible(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = require $root . '/database/steps/registry.php';
        $entry = null;
        foreach ($registry as $row) {
            if ($row['id'] === 'sse_wanted_process') {
                $entry = $row;
            }
        }
        self::assertNotNull($entry, 'Étape numérotée dans le registre');
        self::assertSame('schema', $entry['group']);
        self::assertTrue($entry['rerun']);
        self::assertFileExists($root . '/database/steps/' . $entry['file']);
        self::assertMatchesRegularExpression('/^\d{4}_sse_wanted_process\.php$/', $entry['file']);
        $numbers = array_map(static fn (array $r): int => (int) substr((string) $r['file'], 0, 4), $registry);
        self::assertSame(1, count(array_filter($numbers, static fn (int $n): bool => $n === (int) substr($entry['file'], 0, 4))), 'Numéro unique');
        self::assertGreaterThan(4690, (int) substr($entry['file'], 0, 4));
        self::assertStringContainsString('sse_wanted_process_down.sql', $entry['label']);
        $sql = (string) file_get_contents($root . '/migrations/2026_11_18_100_sse_wanted_process.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS sse_wanted_notice_meta', $sql);
        self::assertDoesNotMatchRegularExpression('/ALTER\s+TABLE/i', $sql, 'Aucune colonne ajoutée, aucun ADD UNIQUE KEY');
        $down = (string) file_get_contents($root . '/migrations/rollback/sse_wanted_process_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS sse_wanted_notice_meta', $down);
        // La table existe bien dans la base du test, créée depuis ce fichier SQL.
        self::assertTrue(SseWantedMetaRepository::ready());
    }

    public function testNewScreensHaveNoSimulationWordingNoEyebrowAndNoTextInTheScripts(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['views/atak/sse/papers/_wanted_services.php', 'public/assets/js/sse-wanted-services.js', 'public/assets/js/sse-wanted-services-admin.js', 'public/assets/css/sse-wanted-services.css', 'config/sse_wanted_services.php'] as $file) {
            $text = (string) file_get_contents($root . '/' . $file);
            self::assertDoesNotMatchRegularExpression('/simulation|simulé/i', $text, $file);
            self::assertDoesNotMatchRegularExpression('/eyebrow|sur-titre/i', $text, $file);
        }
        foreach (['public/assets/js/sse-wanted-services.js', 'public/assets/js/sse-wanted-services-admin.js'] as $script) {
            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*~s', '', (string) file_get_contents($root . '/' . $script));
            self::assertDoesNotMatchRegularExpression('/["\'][^"\'\n]*[àâçéèêëîïôùûüœ][^"\'\n]*["\']/u', $code, $script . ' : les textes viennent de la vue');
        }
        $css = (string) file_get_contents($root . '/public/assets/css/sse-wanted-services.css');
        self::assertDoesNotMatchRegularExpression('/font-size:\s*(?:[0-9]|1[0-2])px/', $css, 'Texte de 13 px au moins');
        self::assertDoesNotMatchRegularExpression('/font-size:\s*0\.(?:[0-7][0-9]?|8[0-3])rem/', $css, 'Texte de 13 px au moins');
    }
}
