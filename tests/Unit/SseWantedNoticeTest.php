<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Controllers\Web\SseWantedNoticeController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityEventRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseWantedDetectionRepository;
use App\Repositories\SseWantedNoticeRepository;
use App\Repositories\SseWantedSettingsRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdentityDossierService;
use App\Services\Sse\SseIdentityTimelineService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedDetectionService;
use App\Services\Sse\SseWantedNoticePdf;
use App\Services\Sse\SseWantedNoticePdfService;
use App\Services\Sse\SseWantedService;
use App\Support\SseWantedCatalog;
use App\Support\SseWantedRealNames;
use App\Support\TrainingCertificatePdfEngine;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\SseWantedSqlite;

/**
 * Avis de recherche des identités fictives (jeu de rôle) : un seul avis actif, levée avec motif, droits et jeton CSRF,
 * références uniques, validations, notice PDF (cas limites, aucune organisation réelle, mention de fiction), détections
 * système (avis actif seulement, dédupliquées, plafonnées, sans effet sur l'identité), chronologie en ajout seul et vues
 * qui importent leurs classes.
 */
final class SseWantedNoticeTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        // Démarre la session une fois pour toutes (en ligne de commande, le premier démarrage vide $_SESSION).
        \App\Core\Csrf::token();
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        // Session du bureau SSE ouverte (porte d'entrée : session active et engagement de confidentialité signé).
        $_SESSION = [
            'user_id' => 7, 'tenant_id' => 1, 'callsign' => 'ANALYSTE', '_csrf_token' => 'jeton',
            'sse_tenant_id' => 1, 'sse_guest' => false, 'sse_clearance_until' => time() + 3600, 'sse_access_code_id' => 0,
            'sse_confidentiality_version' => SseAccessCodeService::CONFIDENTIALITY_VERSION, 'sse_confidentiality_tenant_id' => 1, 'sse_confidentiality_code_id' => 0,
        ];
        $_POST = [];
        $_GET = [];
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

    private static function manager(int $tenant = 1): SseWantedAccess
    {
        return new SseWantedAccess(false, true, true, true, $tenant, 7, 'ANALYSTE');
    }

    private function identity(int $tenant = 1, string $last = 'Karimzadeh', string $first = 'Élodie', array $extra = []): int
    {
        return (new SseIdentityRepository())->create($tenant, $extra + ['last_name' => $last, 'first_name' => $first, 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK']);
    }

    /** @return array<string, mixed> */
    private static function input(array $extra = []): array
    {
        return $extra + ['notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes', 'reason' => 'Participation présumée à une attaque coordonnée.'];
    }

    private function place(int $identityId, array $extra = [], ?SseWantedAccess $access = null): array
    {
        return (new SseWantedService())->place(1, $identityId, self::input($extra), $access ?? self::manager());
    }

    // ------------------------------------------------------------------ Avis

    public function testOnlyOneNoticeIsActivePerIdentityAndItCanBeReplacedAfterTheLift(): void
    {
        $id = $this->identity();
        $first = $this->place($id);
        self::assertTrue($first['ok']);
        self::assertSame('active', (new SseWantedNoticeRepository())->find(1, $first['id'])['status']);

        $second = $this->place($id);
        self::assertFalse($second['ok']);
        self::assertSame('exists', $second['error']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_notices')->fetchColumn());

        // Le verrou tient aussi côté base : un second avis actif est refusé par la clé unique.
        try {
            $this->pdo->exec("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, notice_type, threat_level, armed, status, active_key, issued_at, created_at) VALUES (1, {$id}, 'X-1', 'poi', 'low', 'no', 'active', 1, '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
            self::fail('Un second avis actif aurait dû être refusé.');
        } catch (\PDOException) {
            self::assertTrue(true);
        }

        $lift = (new SseWantedService())->lift(1, $first['id'], 'lifted', 'Erreur de personne.', self::manager());
        self::assertTrue($lift['ok']);
        self::assertTrue($this->place($id)['ok'], 'Un nouvel avis est possible une fois le premier levé.');
        self::assertSame(2, (new SseWantedNoticeRepository())->listForIdentity(1, $id) === [] ? 0 : count((new SseWantedNoticeRepository())->listForIdentity(1, $id)));
    }

    public function testLiftNeedsAReasonAndKeepsTheHistory(): void
    {
        $id = $this->identity();
        $notice = $this->place($id);
        $service = new SseWantedService();

        $none = $service->lift(1, $notice['id'], 'lifted', '  ', self::manager());
        self::assertFalse($none['ok']);
        self::assertSame('reason', $none['error']);
        self::assertSame('active', (new SseWantedNoticeRepository())->find(1, $notice['id'])['status']);

        self::assertTrue($service->lift(1, $notice['id'], 'captured', 'Capturé au poste nord.', self::manager())['ok']);
        $row = (new SseWantedNoticeRepository())->find(1, $notice['id']);
        self::assertSame('captured', $row['status']);
        self::assertSame('Capturé au poste nord.', $row['closed_reason']);
        self::assertSame(7, $row['closed_by_user_id']);
        self::assertNull((new SseWantedNoticeRepository())->activeForIdentity(1, $id));

        $again = $service->lift(1, $notice['id'], 'lifted', 'Encore.', self::manager());
        self::assertSame('closed', $again['error']);
        $types = array_column((new SseIdentityEventRepository())->listForIdentity(1, $id), 'type');
        self::assertContains('notice.placed', $types);
        self::assertContains('notice.captured', $types);
    }

    public function testReferencesAreUniqueSequentialAndFollowTheCommunityFormat(): void
    {
        $a = $this->identity(1, 'Alpha');
        $b = $this->identity(1, 'Bravo');
        $c = $this->identity(2, 'Charlie');
        $year = gmdate('Y');
        $one = $this->place($a);
        $two = $this->place($b);
        self::assertSame('A-0001/' . $year, $one['ref']);
        self::assertSame('A-0002/' . $year, $two['ref']);
        // Chaque communauté a sa propre suite.
        $other = (new SseWantedService())->place(2, $c, self::input(), self::manager(2));
        self::assertTrue($other['ok']);
        self::assertSame('A-0001/' . $year, $other['ref']);

        $settings = new SseWantedSettingsRepository();
        $checked = SseWantedSettingsRepository::validate(['authority_fr' => 'Bureau fictif', 'authority_en' => 'Fictional bureau', 'ref_format' => 'WN-{N:5}/{y}']);
        self::assertSame([], $checked['errors']);
        $settings->save(1, $checked['settings'], 7);
        $d = $this->identity(1, 'Delta');
        $three = $this->place($d);
        self::assertSame(sprintf('WN-00003/%02d', (int) $year % 100), $three['ref'], 'Le compteur de l’année continue malgré le nouveau format');

        self::assertSame('A-0123/2026', SseWantedNoticeRepository::buildRef('A-{N}/{Y}', 2026, 123));
        self::assertFalse(SseWantedSettingsRepository::validFormat('SANS-NUMERO'));
        self::assertFalse(SseWantedSettingsRepository::validFormat('A-{N}/<script>'));
        self::assertTrue(SseWantedSettingsRepository::validFormat('RP-{N:6}'));
    }

    public function testValidationRefusesBadInputAndRealOrganisations(): void
    {
        $config = SseWantedSettingsRepository::normalize([]);
        $bad = SseWantedService::validate(['notice_type' => 'inconnu', 'threat_level' => 'extrême', 'armed' => 'peut-être', 'reason' => 'x', 'issued_at' => 'demain', 'expires_at' => '2020-01-01', 'last_seen_at' => '31/02/2026'], $config);
        self::assertGreaterThanOrEqual(5, count($bad['errors']));

        $ok = SseWantedService::validate(self::input(['issued_at' => '2026-11-14T10:00', 'expires_at' => '2026-12-01', 'last_seen_at' => '2026-11-10 18:30', 'zone' => str_repeat('Z', 400)]), $config);
        self::assertSame([], $ok['errors']);
        self::assertSame('2026-11-14 10:00:00', $ok['data']['issued_at']);
        self::assertSame(120, mb_strlen((string) $ok['data']['zone']));
        self::assertSame(SseWantedCatalog::DEFAULTS['default_instructions_fr'], $ok['data']['instructions'], 'Consigne vide : celle de la communauté');

        $real = SseWantedService::validate(self::input(['contact_label' => 'Service de liaison Interpol']), $config);
        self::assertNotSame([], $real['errors']);
        $settings = SseWantedSettingsRepository::validate(['authority_fr' => 'FBI Field Office', 'authority_en' => 'Bureau fictif', 'ref_format' => 'A-{N}']);
        self::assertNotSame([], $settings['errors']);
        self::assertNotNull(SseWantedRealNames::find('Gendarmerie nationale'));
        self::assertNull(SseWantedRealNames::find('Bureau international de recherche — RP'));
        self::assertNull(SseWantedRealNames::find('Cellule de coordination des recherches'));
    }

    public function testOnlyTheManagementRightsPlaceOrLiftAndTheIdentityMustBelongToTheCommunity(): void
    {
        $id = $this->identity();
        $service = new SseWantedService();
        $reader = new SseWantedAccess(false, false, false, false, 1, 7, 'LECTEUR');
        $guest = new SseWantedAccess(true, true, true, true, 1, null);
        self::assertSame('forbidden', $service->place(1, $id, self::input(), $reader)['error']);
        self::assertSame('forbidden', $service->place(1, $id, self::input(), $guest)['error']);
        self::assertSame('forbidden', $service->place(1, $id, self::input(), self::manager(2))['error'], 'Une session d’une autre communauté ne pose rien');
        self::assertSame('identity', (new SseWantedService())->place(2, $id, self::input(), self::manager(2))['error'], 'Identité d’une autre communauté');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_notices')->fetchColumn());

        $notice = $this->place($id);
        self::assertSame('forbidden', $service->lift(1, $notice['id'], 'lifted', 'Motif', $reader)['error']);
        self::assertNotNull((new SseWantedNoticeRepository())->activeForIdentity(1, $id));

        // Générer le PDF : gestion des fichiers ET droits de l'atelier de papiers.
        self::assertTrue(self::manager()->canGeneratePdf());
        self::assertFalse((new SseWantedAccess(false, true, false, true, 1, 7))->canGeneratePdf());
        self::assertFalse((new SseWantedAccess(false, false, true, true, 1, 7))->canGeneratePdf());
        self::assertFalse($guest->canManage());
        self::assertTrue($reader->canRead());
        self::assertSame(['base'], $reader->timelineSections());
    }

    public function testReadersWithoutTheFilesSectionSeeNoNoticeNorDetection(): void
    {
        $id = $this->identity();
        $this->place($id);
        $this->detector()->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-1']);
        $identity = (new SseIdentityRepository())->find($id, 1);
        self::assertNotNull($identity);
        $blind = new SseWantedAccess(false, false, false, false, 1, 7, 'LECTEUR', false);
        self::assertFalse($blind->canRead());
        $dossier = (new SseIdentityDossierService())->build(1, $identity, $blind, 'fr');
        self::assertNull($dossier['notice']);
        self::assertSame([], $dossier['detections']);
        self::assertFalse($dossier['wantedReadable']);
        $types = array_column($dossier['timeline'], 'type');
        self::assertNotContains('notice.placed', $types);
        self::assertNotContains('detection.raised', $types);
        self::assertContains('identity.created', $types);

        $seeing = (new SseIdentityDossierService())->build(1, $identity, self::manager(), 'fr');
        self::assertNotNull($seeing['notice']);
        self::assertCount(1, $seeing['detections']);
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, $blind);
        self::assertSame(302, $controller->index(new Request())->statusCode());
        self::assertSame(302, $controller->detections(new Request())->statusCode());
    }

    public function testControllerWritesNeedTheCsrfTokenAndTheRights(): void
    {
        $id = $this->identity();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = self::input();
        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager());

        // Sans jeton : refusé, rien n'est écrit.
        $denied = $controller->place(new Request(), ['id' => $id]);
        self::assertSame(302, $denied->statusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_notices')->fetchColumn());

        $_POST['_csrf_token'] = 'jeton';
        $ok = $controller->place(new Request(), ['id' => $id]);
        self::assertSame(302, $ok->statusCode());
        self::assertStringContainsString('#avis', (string) $ok->headerValue('Location'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_notices')->fetchColumn());
        $noticeId = (int) $this->pdo->query('SELECT id FROM sse_wanted_notices')->fetchColumn();

        // Levée : jeton, droits et motif obligatoires.
        $_POST = ['status' => 'lifted', 'reason' => 'Motif', '_csrf_token' => 'mauvais'];
        $controller->lift(new Request(), ['id' => $noticeId]);
        self::assertSame('active', (string) $this->pdo->query('SELECT status FROM sse_wanted_notices')->fetchColumn());
        $_POST['_csrf_token'] = 'jeton';
        $_POST['reason'] = '';
        $controller->lift(new Request(), ['id' => $noticeId]);
        self::assertSame('active', (string) $this->pdo->query('SELECT status FROM sse_wanted_notices')->fetchColumn());
        $_POST['reason'] = 'Identité confondue.';
        $reader = new SseWantedNoticeController(null, null, null, null, null, null, null, new SseWantedAccess(false, false, false, false, 1, 7));
        $reader->lift(new Request(), ['id' => $noticeId]);
        self::assertSame('active', (string) $this->pdo->query('SELECT status FROM sse_wanted_notices')->fetchColumn());
        $controller->lift(new Request(), ['id' => $noticeId]);
        self::assertSame('lifted', (string) $this->pdo->query('SELECT status FROM sse_wanted_notices')->fetchColumn());

        // Le PDF et les réglages exigent aussi leurs droits.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $pdf = $reader->pdf(new Request(), ['id' => $noticeId]);
        self::assertSame(302, $pdf->statusCode());
        self::assertSame(302, $reader->settings(new Request())->statusCode());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['authority_fr' => 'Bureau fictif', 'authority_en' => 'Fictional bureau', 'ref_format' => 'A-{N}', '_csrf_token' => 'mauvais'];
        $controller->settingsSave(new Request());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_settings')->fetchColumn());
        $_POST['_csrf_token'] = 'jeton';
        $controller->settingsSave(new Request());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_wanted_settings')->fetchColumn());
    }

    public function testAnExpiredNoticeNoLongerCountsAsActiveAndFreesTheIdentity(): void
    {
        $id = $this->identity();
        $this->pdo->exec("INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, notice_type, threat_level, armed, status, active_key, issued_at, expires_at, created_at) VALUES (1, {$id}, 'OLD-1', 'poi', 'low', 'no', 'active', 1, '2020-01-01 00:00:00', '2020-02-01 00:00:00', '2020-01-01 00:00:00')");
        $repo = new SseWantedNoticeRepository();
        self::assertNull($repo->activeForIdentity(1, $id));
        self::assertSame('expired', $repo->listForIdentity(1, $id)[0]['status']);
        self::assertTrue($this->place($id)['ok'], 'L’avis arrivé à échéance libère l’identité');
        self::assertContains('expired', array_column($repo->listForIdentity(1, $id), 'status'));
    }

    public function testScreensTolerateMissingTables(): void
    {
        SseWantedSqlite::close();
        $this->pdo = SseWantedSqlite::open(false);
        $id = $this->identity();
        self::assertFalse(SseWantedNoticeRepository::ready());
        self::assertSame('unavailable', $this->place($id)['error']);
        self::assertSame(['wanted' => false], (new SseWantedDetectionService())->wantedPayload(1, $id));
        SseWantedDetectionService::tryReport(1, $id, 'id_check', ['source' => 'game']);
        $identity = (new SseIdentityRepository())->find($id, 1);
        self::assertNotNull($identity);
        self::assertNotSame([], (new SseIdentityTimelineService())->build(1, $identity, self::manager()), 'La chronologie garde la création de la fiche');
    }

    // ------------------------------------------------------------ Détections

    /** @return list<array{int, int, array<string, mixed>}> */
    private array $notified = [];

    private function detector(): SseWantedDetectionService
    {
        $this->notified = [];

        return new SseWantedDetectionService(
            null,
            null,
            null,
            function (int $tenant, int $user, array $payload): void {
                $this->notified[] = [$tenant, $user, $payload];
            },
            static fn (int $tenant, int $identity, ?int $author): array => array_values(array_filter([$author, 8, 9]))
        );
    }

    public function testDetectionsExistOnlyForAnActiveNoticeAndNeverChangeTheIdentity(): void
    {
        $id = $this->identity();
        $other = $this->identity(1, 'Sansavis');
        $detector = $this->detector();
        self::assertNull($detector->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-1']), 'Pas d’avis : pas de détection');

        $notice = $this->place($id);
        $before = $this->pdo->query("SELECT * FROM sse_identities WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
        $first = $detector->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-1', 'place' => 'Grille 123456', 'operator_label' => 'RAVEN 2-1']);
        self::assertNotNull($first);
        self::assertTrue($first['created']);
        self::assertNull($detector->report(1, $other, 'id_check', ['source' => 'game']), 'Identité sans avis : jamais de détection');
        self::assertNull($detector->report(1, $id, 'inconnu', []));
        self::assertSame($before, $this->pdo->query("SELECT * FROM sse_identities WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC), 'Une détection ne modifie rien de l’identité');

        $row = (new SseWantedDetectionRepository())->find(1, $first['id']);
        self::assertSame('new', $row['status']);
        self::assertSame($notice['ref'], $row['notice_ref']);
        self::assertSame('RAVEN 2-1', $row['operator_label']);
        self::assertSame('Grille 123456', $row['place']);

        // Notification des seuls utilisateurs concernés (auteur de l'avis, responsables), jamais deux fois.
        self::assertSame([7, 8, 9], array_column($this->notified, 1));
        self::assertSame('atak/sse/avis/detections', $this->notified[0][2]['path']);

        // Après la levée, plus rien.
        (new SseWantedService())->lift(1, $notice['id'], 'lifted', 'Levé.', self::manager());
        self::assertNull($detector->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-9']));
        $types = array_column((new SseIdentityEventRepository())->listForIdentity(1, $id), 'type');
        self::assertContains('detection.raised', $types);
    }

    public function testDetectionsAreDeduplicatedAndCapped(): void
    {
        $id = $this->identity();
        $this->place($id);
        $detector = $this->detector();
        $one = $detector->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-1']);
        $notifiedAfterFirst = count($this->notified);
        // Même contrôle renvoyé : aucune nouvelle ligne.
        $same = $detector->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-1']);
        self::assertFalse($same['created']);
        self::assertSame($one['id'], $same['id']);
        // Autre contrôle du même canal dans le laps court : regroupé sur la première détection.
        $near = $detector->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-2']);
        self::assertFalse($near['created']);
        self::assertSame($one['id'], $near['id']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_detections')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query("SELECT repeat_count FROM sse_detections WHERE id = {$one['id']}")->fetchColumn());
        self::assertCount($notifiedAfterFirst, $this->notified, 'Une répétition ne prévient personne de plus');
        // Un autre type de détection est distinct.
        self::assertTrue($detector->report(1, $id, 'photo', ['source' => 'web', 'source_ref' => 'photo:1'])['created']);

        // Plafond par heure : au-delà, rien n'est créé.
        $noticeId = (int) $this->pdo->query('SELECT id FROM sse_wanted_notices')->fetchColumn();
        $now = gmdate('Y-m-d H:i:s');
        for ($i = 0; $i < SseWantedDetectionService::HOURLY_CAP; $i++) {
            $this->pdo->exec("INSERT INTO sse_detections (tenant_id, identity_id, notice_id, detection_type, source, status, repeat_count, created_at) VALUES (1, {$id}, {$noticeId}, 'link', 'system', 'new', 1, '{$now}')");
        }
        self::assertNull($detector->report(1, $id, 'phone', ['source' => 'web', 'source_ref' => 'ext:1']));
    }

    public function testDetectionStatusIsHandledAndHistoryIsAppendOnly(): void
    {
        $id = $this->identity();
        $this->place($id);
        $found = $this->detector()->report(1, $id, 'seek', ['source' => 'game', 'source_ref' => 'REC-7']);
        $repo = new SseWantedDetectionRepository();
        self::assertTrue($repo->setStatus(1, $found['id'], 'acknowledged', 'Vu avec le chef.', 7));
        self::assertFalse($repo->setStatus(2, $found['id'], 'dismissed', '', 7), 'Une autre communauté ne traite pas cette détection');
        self::assertNull($repo->find(2, $found['id']));
        self::assertSame(['new' => 0, 'acknowledged' => 1, 'dismissed' => 0], $repo->counts(1));
        self::assertSame('acknowledged', $repo->list(1, ['status' => 'acknowledged'])[0]['status']);

        // Chronologie : aucune méthode publique ne modifie ou ne supprime une ligne.
        foreach ((new \ReflectionClass(SseIdentityEventRepository::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            self::assertDoesNotMatchRegularExpression('/^(update|delete|remove|purge|clear|set|edit)/i', $m->getName(), $m->getName());
        }
        $events = new SseIdentityEventRepository();
        $events->add(1, $id, 'file.mention', 'Mention.', [], 7, 'ANALYSTE');
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_events')->fetchColumn();
        $events->add(1, $id, 'file.mention', 'Mention 2.', [], 7, 'ANALYSTE');
        self::assertSame($count + 1, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_identity_events')->fetchColumn());
    }

    public function testACheckOnAnIdentityUnderNoticeRaisesADetectionAndTheAnswerCarriesTheWantedField(): void
    {
        $id = $this->identity(1, 'Papadopoulos', 'Nikos');
        $docs = new SseIdDocumentRepository();
        $docId = $docs->create(1, ['identity_id' => $id, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'ALK482913']);
        $docs->transition($docId, 1, 'validated');
        $docs->transition($docId, 1, 'issued');
        $notice = $this->place($id, ['instructions' => 'Prévenir le PC sans engager le contact.']);
        $service = new SseIdCheckService();
        $body = [
            'idempotency_key' => 'chk-wanted-1', 'map' => 1,
            'document' => ['type' => 'passport', 'country' => 'ALT', 'number' => 'ALK482913'],
            'presented_name' => ['last' => 'Papadopoulos', 'first' => 'Nikos'],
            'controller' => ['callsign' => 'VIPER 1-1'], 'levels' => ['N3'], 'verdict' => 'ras', 'grid' => '123456',
        ];
        $stored = $service->store(1, 1, $body);
        self::assertSame($id, $service->identityOf(1, (int) $stored['id']));
        $rows = (new SseWantedDetectionRepository())->list(1, ['identity_id' => $id]);
        self::assertCount(1, $rows);
        self::assertSame('id_check', $rows[0]['type']);
        self::assertSame('game', $rows[0]['source']);
        self::assertSame('VIPER 1-1', $rows[0]['operator_label']);
        self::assertSame('Grille 123456', $rows[0]['place']);

        // Le contrôle renvoyé (même clé) ne crée rien de plus.
        $service->store(1, 1, $body);
        self::assertCount(1, (new SseWantedDetectionRepository())->list(1, ['identity_id' => $id]));

        $payload = (new SseWantedDetectionService())->wantedPayload(1, $id);
        self::assertTrue($payload['wanted']);
        self::assertSame($notice['ref'], $payload['ref']);
        self::assertSame('high', $payload['threat_level']);
        self::assertSame('Prévenir le PC sans engager le contact.', $payload['instruction']);
        self::assertSame(['wanted' => false], (new SseWantedDetectionService())->wantedPayload(1, null));
        self::assertSame(['wanted' => false], (new SseWantedDetectionService())->wantedPayload(2, $id), 'Autre communauté');
        self::assertNotNull((new SseIdCheckRepository())->find((int) $stored['id'], 1));
    }

    public function testLinksPhotosAndPhonesOnAWantedIdentityRaiseDetections(): void
    {
        $id = $this->identity();
        $other = $this->identity(1, 'Voisin');
        $this->place($id);
        $records = new \App\Repositories\SseFileRecordRepository();
        $records->addLink(1, $id, ['kind' => 'phone', 'value' => '+93 70 123 4567'], 7);
        self::assertSame(['phone'], array_column((new SseWantedDetectionRepository())->list(1, ['identity_id' => $id]), 'type'));
        // Le même numéro rattaché à une autre identité recoupe l'identité sous avis.
        $records->addLink(1, $other, ['kind' => 'phone', 'value' => '0093701234567'], 7);
        self::assertCount(1, (new SseWantedDetectionRepository())->list(1, ['identity_id' => $id]), 'Dédupliqué dans le laps court');
        $records->addPhoto(1, ['identity_id' => $id, 'path' => 'uploads/x.jpg', 'mime' => 'image/jpeg', 'source' => 'upload'], 7);
        self::assertContains('photo', array_column((new SseWantedDetectionRepository())->list(1, ['identity_id' => $id]), 'type'));
        self::assertSame([], (new SseWantedDetectionRepository())->list(1, ['identity_id' => $other]));
    }

    // ------------------------------------------------------------ Chronologie

    public function testTimelineMergesEventsAndTracesAndHidesTruthFromReaders(): void
    {
        $id = $this->identity(1, 'Dupont', 'Jean', ['kind' => 'cover']);
        $repo = new SseIdentityRepository();
        $identity = $repo->find($id, 1);
        self::assertNotNull($identity);
        $repo->update($id, 1, array_merge($identity, ['profession' => 'Pilote', 'kind' => 'alias']));
        $docs = new SseIdDocumentRepository();
        $docs->create(1, ['identity_id' => $id, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'ALK111111']);
        $this->place($id);

        $identity = $repo->find($id, 1);
        $timeline = new SseIdentityTimelineService();
        $truth = $timeline->build(1, $identity, self::manager());
        $types = array_column($truth, 'type');
        self::assertContains('identity.created', $types);
        self::assertContains('identity.updated', $types);
        self::assertContains('notice.placed', $types);
        self::assertContains('document.created', $types);
        self::assertSame(array_column($truth, 'at'), array_reverse(array_values(array_reverse(array_column($truth, 'at')))));
        $labels = [];
        foreach ($truth as $row) {
            foreach ($row['details'] as $d) {
                $labels[] = $d['label'];
            }
        }
        self::assertContains('Profession', $labels);
        self::assertContains('Nature de l’identité', $labels);

        // Un lecteur sans la vérité de jeu ne voit ni la nature, ni les papiers d'une identité d'emprunt.
        $reader = new SseWantedAccess(false, false, false, false, 1, 7);
        $limited = $timeline->build(1, $identity, $reader);
        $labelsLimited = [];
        foreach ($limited as $row) {
            foreach ($row['details'] as $d) {
                $labelsLimited[] = $d['label'];
            }
        }
        self::assertContains('Profession', $labelsLimited);
        self::assertNotContains('Nature de l’identité', $labelsLimited);
        self::assertNotContains('document.created', array_column($limited, 'type'));
        // Filtre par famille et autre communauté.
        $notices = $timeline->build(1, $identity, self::manager(), 'fr', 'notices');
        self::assertSame(['notice.placed'], array_values(array_unique(array_column($notices, 'type'))));
        self::assertSame([], $timeline->build(2, $identity, self::manager(2)));
        $en = $timeline->build(1, $identity, self::manager(), 'en');
        self::assertStringContainsString('Wanted notice', implode(' ', array_column($en, 'summary')));
    }

    // ------------------------------------------------------------------- PDF

    /** @return array<string, mixed> */
    private static function noticeData(array $extra = []): array
    {
        return $extra + [
            'lang' => 'fr', 'banner' => ['NOTICE ROUGE', 'RED NOTICE'], 'authority' => ['Bureau international de recherche — RP', 'International Search Bureau — RP'],
            'ref' => 'A-0123/2026', 'issued_at' => '2026-11-14 10:00:00', 'expires_at' => null,
            'last_name' => 'Karimzadeh', 'first_name' => 'Élodie', 'sex' => 'M', 'birth_date' => '1984-03-12', 'birth_place' => 'Zargabad',
            'nationality' => 'Takistan (TAK)', 'height_cm' => 182, 'weight_kg' => 84, 'eyes' => 'Marron', 'hair' => 'Noirs', 'marks' => 'Cicatrice',
            'aliases' => ['Abou Hamza'], 'languages' => 'pachto', 'type_label' => 'Recherche en vue d’arrestation', 'threat_level' => 'high',
            'threat_label' => 'Élevé', 'armed' => 'yes', 'reason' => 'Motif de test.', 'instructions' => 'Signaler au service demandeur.',
            'zone' => 'Takistan', 'last_seen_place' => 'Poste nord', 'last_seen_at' => '2026-11-10 18:30:00', 'contact_label' => 'Cellule de coordination',
            'contact_detail' => '', 'photo' => null, 'extra_photos' => [], 'signature' => null, 'emblem_path' => null, 'watermark' => true, 'footer' => true,
            'faces' => 'auto', 'generated_at' => '15/11/2026 10:00 UTC',
        ];
    }

    private static function requirePdfEngine(): void
    {
        if (!TrainingCertificatePdfEngine::ensureTcpdfLoaded() || !TrainingCertificatePdfEngine::tcpdfCertificateFontsReady()) {
            self::markTestSkipped('TCPDF et ses polices requis');
        }
    }

    private static function pages(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page\b(?!s)~', $pdf);
    }

    private static function jpeg(): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        $im = imagecreatetruecolor(210, 270);
        ob_start();
        imagejpeg($im, null, 80);

        return (string) ob_get_clean();
    }

    public function testNoticePdfIsGeneratedForEdgeCases(): void
    {
        self::requirePdfEngine();
        $template = new SseWantedNoticePdf();
        $long = str_repeat('Participation présumée à l’attaque du poste avancé et au financement d’un réseau. ', 40);
        $cases = [
            'base' => self::noticeData(),
            'nom long et accents' => self::noticeData(['last_name' => 'Øksnevad-Çelik-Papadopoulos-Constantinopoulos-Vandenberghe', 'first_name' => 'Zoë-Łukasz Ñandú Jean-Baptiste Alexandre', 'reason' => 'Œuvre « scènes » — très dangereux ; ≥ 3 “cibles”']),
            'sans photo ni date' => self::noticeData(['photo' => null, 'birth_date' => null, 'birth_place' => null, 'nationality' => '', 'height_cm' => null, 'weight_kg' => null, 'eyes' => '', 'hair' => '', 'marks' => '', 'aliases' => [], 'languages' => '', 'last_seen_at' => null, 'last_seen_place' => '', 'zone' => '', 'contact_label' => '', 'armed' => 'unknown', 'threat_level' => 'low', 'threat_label' => 'Faible']),
            'textes très longs' => self::noticeData(['reason' => $long, 'instructions' => $long, 'marks' => $long, 'aliases' => ['a', 'b', 'c', 'd', 'e', 'f']]),
            'anglais recto verso' => self::noticeData(['lang' => 'en', 'faces' => '2', 'expires_at' => '2027-01-01 00:00:00', 'footer' => false, 'watermark' => false]),
        ];
        $jpeg = self::jpeg();
        if ($jpeg !== null) {
            $cases['photos et signature'] = self::noticeData(['photo' => $jpeg, 'extra_photos' => [$jpeg, $jpeg], 'signature' => $jpeg]);
        }
        foreach ($cases as $name => $data) {
            $pdf = $template->render($data);
            self::assertIsString($pdf, $name);
            self::assertStringStartsWith('%PDF-', $pdf, $name);
            self::assertGreaterThan(1500, strlen($pdf), $name);
        }
        self::assertSame(1, self::pages((string) $template->render(self::noticeData())));
        self::assertSame(2, self::pages((string) $template->render(self::noticeData(['faces' => '2']))));
        self::assertSame(2, self::pages((string) $template->render(self::noticeData(['reason' => $long]))), 'Texte long : verso automatique');
        self::assertSame(1, self::pages((string) $template->render(self::noticeData(['faces' => '1', 'reason' => $long]))));
    }

    public function testNoticePdfCarriesTheFictionMentionAndNamesNoRealOrganisation(): void
    {
        self::requirePdfEngine();
        $pdf = (string) (new SseWantedNoticePdf())->render(self::noticeData());
        // Les propriétés du fichier gardent la mention même si le pied de page est désactivé (texte PDF en UTF-16BE).
        $needle = mb_convert_encoding('Document de fiction', 'UTF-16BE', 'UTF-8');
        self::assertNotFalse(strpos($pdf, $needle), 'Mention « Document de fiction » absente du fichier');
        $off = (string) (new SseWantedNoticePdf())->render(self::noticeData(['footer' => false]));
        self::assertNotFalse(strpos($off, $needle), 'Les propriétés gardent la mention sans pied de page');

        // Test statique : ni le gabarit, ni le service, ni le catalogue ne citent une organisation réelle.
        $root = dirname(__DIR__, 2) . '/app/';
        foreach (['Services/Sse/SseWantedNoticePdf.php', 'Services/Sse/SseWantedNoticePdfService.php', 'Support/SseWantedCatalog.php', 'Services/Sse/SseWantedService.php'] as $file) {
            $source = (string) file_get_contents($root . $file);
            self::assertNull(SseWantedRealNames::find($source), $file . ' cite une organisation réelle : ' . (string) SseWantedRealNames::find($source));
            foreach (['interpol', 'europol', 'fbi', 'gendarmerie', 'police nationale', 'scotland yard'] as $word) {
                self::assertStringNotContainsStringIgnoringCase($word, $source, $file);
            }
        }
        $template = (string) file_get_contents($root . 'Services/Sse/SseWantedNoticePdf.php');
        self::assertStringContainsString('Document de fiction — jeu de rôle Arma 3', $template);
        self::assertStringContainsString('Fictional document — Arma 3 role-play', $template);
        self::assertStringNotContainsString('->Image(\'http', $template, 'Aucun logo téléchargé');
        foreach (SseWantedCatalog::DEFAULTS as $key => $value) {
            if (is_string($value)) {
                self::assertNull(SseWantedRealNames::find($value), $key);
            }
        }
        self::assertNotNull(SseWantedRealNames::find('Interpol'));
        self::assertNotNull(SseWantedRealNames::find('Europol Liaison'));
        self::assertNotNull(SseWantedRealNames::find('FBI'));
        self::assertNull(SseWantedRealNames::find('Fabien'));
    }

    public function testPdfServiceUsesOnlyTheFictionalIdentityNeverTheMemberAccount(): void
    {
        self::requirePdfEngine();
        $id = $this->identity(1, 'Karimzadeh', 'Élodie', ['user_id' => 7]);
        (new SseIdentityRepository())->linkUser($id, 1, 7);
        $notice = $this->place($id);
        $row = (new SseWantedNoticeRepository())->find(1, $notice['id']);
        $service = new SseWantedNoticePdfService();
        $data = $service->data(1, $row, 'fr');
        self::assertNotNull($data);
        $dump = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        self::assertStringNotContainsString('Vrai Membre', $dump);
        self::assertStringNotContainsString('76561198000000007', $dump);
        self::assertSame('KARIMZADEH', mb_strtoupper((string) $data['last_name']));
        self::assertNull($data['photo'], 'Sans photo : silhouette neutre');

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/SseWantedNoticePdfService.php');
        foreach (['FROM users', 'display_name', 'steam_id', 'avatar', 'Session::'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source, 'Le PDF ne lit jamais le compte d’un membre : ' . $forbidden);
        }
        $built = $service->build(1, $row, 'en', '2');
        self::assertNotNull($built);
        self::assertStringStartsWith('%PDF-', $built['bytes']);
        self::assertSame('avis-A-0001-' . gmdate('Y') . '-en.pdf', $built['filename']);
        self::assertNull($service->build(2, $row, 'fr'), 'Un avis d’une autre communauté ne produit rien');
        // Images : seuls les envois rangés sous uploads/ sont lus.
        foreach (['../../etc/passwd', 'uploads/../secret.jpg', 'http://exemple.test/a.jpg', 'C:/Windows/win.ini', 'uploads/inconnu.jpg'] as $path) {
            self::assertNull(SseWantedNoticePdfService::imageBytes($path, false), $path);
        }
    }

    public function testPdfFilenameAndLanguageAreSafeAndServerSide(): void
    {
        self::assertSame('avis-A-0123-2026-fr.pdf', SseWantedNoticePdfService::filename('A-0123/2026', 'fr'));
        self::assertSame('avis-x-en.pdf', SseWantedNoticePdfService::filename('../../<x>"', 'en'));
        self::assertSame('avis-notice-en.pdf', SseWantedNoticePdfService::filename('../..//', 'en'));
        self::assertSame('avis-notice-fr.pdf', SseWantedNoticePdfService::filename('', 'zz'));
        self::assertSame('en', SseWantedNoticePdfService::resolveLang('en', 'fr', 'fr'));
        self::assertSame('fr', SseWantedNoticePdfService::resolveLang('xx', 'fr', 'en'));
        self::assertSame('en', SseWantedNoticePdfService::resolveLang('', '', 'en'));
    }

    // ----------------------------------------------------------------- Vues

    public function testMigrationStepIsRegisteredAndMatchesTheSqlFile(): void
    {
        $root = dirname(__DIR__, 2);
        $registry = (string) file_get_contents($root . '/database/steps/registry.php');
        self::assertStringContainsString("'file' => '4600_sse_wanted_notices.php'", $registry);
        self::assertFileExists($root . '/database/steps/4600_sse_wanted_notices.php');
        self::assertFileExists($root . '/migrations/rollback/sse_wanted_notices_down.sql');
        $down = (string) file_get_contents($root . '/migrations/rollback/sse_wanted_notices_down.sql');
        $scratch = new MysqlDdlSqlitePdo();
        $scratch->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $inspector = new \App\Support\Migrations\SchemaInspector($scratch);
        $inspector->runSqlFile(SseWantedSqlite::sqlFile());
        foreach (SseWantedSqlite::TABLES as $table) {
            self::assertTrue($inspector->tableExists($table), $table);
            self::assertStringContainsString('DROP TABLE IF EXISTS ' . $table . ';', $down);
        }
        // Rejouer le fichier ne change rien (CREATE TABLE IF NOT EXISTS).
        $before = $scratch->changes;
        $inspector->runSqlFile(SseWantedSqlite::sqlFile());
        self::assertSame($before, $scratch->changes);
    }

    public function testViewsImportTheClassesTheyUse(): void
    {
        $dir = dirname(__DIR__, 2) . '/views/atak/sse/papers/';
        foreach (['identity_show.php', '_dossier_timeline.php', '_dossier_detections.php', '_dossier_notice.php', 'wanted_form.php', 'wanted_list.php', 'wanted_detections.php', 'wanted_settings.php'] as $file) {
            $source = (string) file_get_contents($dir . $file);
            $imported = [];
            if (preg_match_all('~^use\s+([\\\\\w]+)(?:\s+as\s+(\w+))?;~m', $source, $m, PREG_SET_ORDER) > 0) {
                foreach ($m as $use) {
                    $imported[$use[2] ?? '' ?: substr((string) strrchr('\\' . $use[1], '\\'), 1)] = $use[1];
                }
            }
            // Retire les chaînes pour ne lire que le code (les textes affichés citent des noms propres).
            $code = (string) preg_replace(["~'(?:[^'\\\\]|\\\\.)*'~s", '~"(?:[^"\\\\]|\\\\.)*"~s', '~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);
            preg_match_all('~(?<![\\\\\w$>:])([A-Z]\w+)(?=::|\s*\()|new\s+([A-Z]\w+)~', $code, $found);
            foreach (array_unique(array_filter(array_merge($found[1], $found[2]))) as $class) {
                if (isset($imported[$class]) || in_array($class, ['SseWantedAccess'], true) && isset($imported[$class])) {
                    continue;
                }
                self::assertTrue(
                    class_exists($class) || interface_exists($class),
                    $file . ' : la classe ' . $class . ' n’est ni importée par « use » ni globale'
                );
            }
            // Appels qualifiés : la classe doit exister.
            preg_match_all('~\\\\(App\\\\[\w\\\\]+)~', $code, $qualified);
            foreach (array_unique($qualified[1]) as $class) {
                self::assertTrue(class_exists($class) || interface_exists($class), $file . ' : ' . $class . ' introuvable');
            }
            foreach ($imported as $alias => $class) {
                self::assertTrue(class_exists($class) || interface_exists($class), $file . ' : use ' . $class . ' introuvable');
            }
        }
    }

    public function testViewsRenderForReal(): void
    {
        Gate::getInstance()->setPermissions(['admin.access', 'atak.sse.case.manage', 'atak.sse.clearance.confidentiel', 'atak.sse.clearance.encadrement']);
        $id = $this->identity(1, 'Karimzadeh', 'Élodie', ['alias' => 'Abou Hamza']);
        $notice = $this->place($id, ['reason' => 'Motif <b>à échapper</b> pour le jeu de rôle.']);
        $this->detector()->report(1, $id, 'id_check', ['source' => 'game', 'source_ref' => 'CTRL-4', 'place' => 'Grille 1', 'operator_label' => 'RAVEN']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $fiche = (new SseIdentityRegistryController())->identityShow(new Request(), ['id' => $id]);
        $body = $fiche->body();
        self::assertSame(200, $fiche->statusCode());
        foreach (['Avis de recherche actif', 'role="tablist"', 'id="historique"', 'id="detections"', 'Lever l’avis', 'KARIMZADEH', $notice['ref']] as $needle) {
            self::assertStringContainsString($needle, $body, $needle);
        }
        self::assertStringNotContainsString('<b>à échapper</b>', $body, 'Le motif est échappé');
        self::assertStringContainsString('&lt;b&gt;à échapper&lt;/b&gt;', $body);
        self::assertStringNotContainsString('Warning', $body);

        $controller = new SseWantedNoticeController(null, null, null, null, null, null, null, self::manager());
        foreach ([
            'index' => $controller->index(new Request()),
            'detections' => $controller->detections(new Request()),
            'settings' => $controller->settings(new Request()),
        ] as $name => $response) {
            self::assertSame(200, $response->statusCode(), $name);
            self::assertStringNotContainsString('Warning', $response->body(), $name);
        }
        self::assertStringContainsString($notice['ref'], $controller->index(new Request())->body());
        self::assertStringContainsString('RAVEN', $controller->detections(new Request(), [])->body());
    }
}
