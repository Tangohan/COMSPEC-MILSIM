<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseDigitalLabRepository;
use App\Services\Sse\SseDigitalLabService;
use App\Support\SseDigitalTerrainDetail;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseDigitalLabSqlite;

/**
 * Exploitation numérique : de l'événement en jeu à l'écran non vide. Le mod envoie le contenu relevé (phone_data /
 * computer_data), le site le range dans les rubriques du laboratoire (contacts, messages, appels, images, positions,
 * comptes, applications, réseaux, pièces, chronologie), sans doublon ni donnée inventée ; un support identifié seulement
 * (ancien mod) l'annonce en réserve ; les écrans disent quoi faire pour avoir des données.
 */
final class SseDigitalLabDataTest extends TestCase
{
    private ?PDO $pdo = null;

    private string $tmp = '';

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            SseDigitalLabSqlite::close();
            $this->pdo = null;
        }
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->tmp);
        }
    }

    private function lab(): SseDigitalLabService
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseDigitalLabSqlite::open();

        return new SseDigitalLabService(new SseDigitalLabRepository());
    }

    /** @return array<string, mixed> */
    private static function phoneBody(string $uid = 'SSE-DIG-0001'): array
    {
        return [
            'record_id' => $uid,
            'idempotency_key' => 'DIG-' . $uid,
            'source_type' => 'PHONE',
            'collector' => 'VIPER 1-1',
            'grid_reference' => '123456',
            'phone_summary' => ['ok' => true, 'deviceType' => 'PHONE', 'model' => 'Nokia 3310', 'owner' => 'Karim', 'contacts' => 3, 'messages' => 2, 'calls' => 2, 'images' => 1, 'locations' => 1],
            'extraction_lines' => ['Contacts récupérés : 3', 'Messages : 2'],
            'phone_data' => [
                'contacts' => ['ABU YASSIN', ['name' => 'Farid Garage', 'phone' => '+33 6 12 34 56 78'], ['name' => '']],
                'sms' => [
                    ['from' => 'Farid Garage', 'to' => 'Karim', 'when' => '2026-11-12 08:40', 'text' => 'Le colis est arrivé.'],
                    ['from' => 'Karim', 'to' => 'Farid Garage', 'when' => '08:45', 'text' => 'Je passe ce soir.', 'dir' => 'out'],
                ],
                'calls' => [['with' => 'ABU YASSIN', 'dir' => 'in', 'duration' => 42, 'when' => '2026-11-12 09:00'], ['with' => 'Inconnu', 'missed' => true]],
                'photos' => [['id' => 'IMG_0042', 'caption' => 'Entrepôt nord', 'when' => '2026-11-11 17:30']],
                'locations' => [['label' => 'Entrepôt nord', 'grid' => '045 078']],
                'credentials' => [['service' => 'Telegram', 'user' => 'karim_h', 'hint' => 'date de naissance']],
                'applications' => ['Telegram', ['name' => 'Maps', 'version' => '3.1']],
                'deletedData' => [['type' => 'sms', 'text' => 'Rendez-vous annulé']],
            ],
        ];
    }

    // ------------------------------------------------------------------ normalisation (sans base)

    public function testNormalizeReadsEveryRubricAndNeverInventsDates(): void
    {
        $body = self::phoneBody();
        $d = SseDigitalTerrainDetail::normalize($body['phone_data']);
        self::assertCount(2, $d['contacts'], 'un contact sans nom est écarté');
        self::assertSame('Farid Garage', $d['contacts'][1]['display_name']);
        self::assertSame('+33 6 12 34 56 78', $d['contacts'][1]['phone_number']);
        self::assertCount(2, $d['messages']);
        self::assertSame('2026-11-12 08:40:00', $d['messages'][0]['sent_at']);
        self::assertNull($d['messages'][1]['sent_at'], 'une heure seule n’est pas une date : aucune date inventée');
        self::assertStringContainsString('[08:45] Je passe ce soir.', (string) $d['messages'][1]['body']);
        self::assertSame('inbound', $d['messages'][0]['direction']);
        self::assertSame('outbound', $d['messages'][1]['direction']);
        self::assertSame('inbound', $d['calls'][0]['direction']);
        self::assertSame('missed', $d['calls'][1]['direction']);
        self::assertSame(42, $d['calls'][0]['duration_sec']);
        self::assertSame('Entrepôt nord', $d['media'][0]['name']);
        self::assertSame('Carroyage 045 078', $d['locations'][0]['source_label']);
        self::assertNull($d['locations'][0]['lat'], 'le jeu donne un carroyage, pas des coordonnées géographiques');
        self::assertSame('Telegram', $d['accounts'][0]['service_label']);
        self::assertSame('Maps', $d['applications'][1]['app_name']);
        self::assertSame('deleted', $d['artifacts'][0]['category']);
        self::assertTrue($d['artifacts'][0]['is_deleted']);
    }

    public function testNormalizeReadsComputerRubrics(): void
    {
        $d = SseDigitalTerrainDetail::normalize([], [
            'files' => [['name' => 'plan_depot.docx', 'path' => 'C:/Docs', 'relevant' => true], 'notes.txt'],
            'browser' => [['title' => 'Carte du port', 'url' => 'http://exemple.test/port']],
            'mail' => [['from' => 'farid@exemple.test', 'subject' => 'Livraison', 'snippet' => 'Jeudi 5h']],
            'credentials' => [['service' => 'VPN', 'user' => 'admin']],
            'usbHistory' => ['SANDISK 16G'],
            'encryptedFiles' => ['archive.7z'],
            'users' => [['name' => 'karim', 'role' => 'admin']],
            'network' => ['ssid' => 'DEPOT-WIFI', 'lastIP' => '10.0.0.8'],
        ]);
        $byCat = [];
        $interest = [];
        foreach ($d['artifacts'] as $a) {
            $byCat[$a['category']][] = $a['name'];
            $interest[$a['name']] = $a['interest_level'] ?? 'courant';
        }
        self::assertSame(['plan_depot.docx', 'notes.txt'], $byCat['document']);
        self::assertSame(['Carte du port'], $byCat['navigation']);
        self::assertSame(['Livraison'], $byCat['email']);
        self::assertSame(['archive.7z'], $byCat['encrypted']);
        self::assertCount(2, $byCat['system'], 'USB et utilisateurs');
        self::assertSame('prioritaire', $interest['plan_depot.docx'], 'fichier jugé pertinent par le jeu : prioritaire');
        self::assertSame('courant', $interest['notes.txt']);
        self::assertSame('DEPOT-WIFI', $d['networks'][0]['ssid_or_name']);
        self::assertSame('VPN', $d['accounts'][0]['service_label']);
    }

    public function testNormalizeIsBoundedAndToleratesOldOrBrokenData(): void
    {
        $many = SseDigitalTerrainDetail::normalize(['contacts' => array_map(static fn (int $i): string => 'Contact ' . $i, range(1, 500)), 'sms' => [['text' => str_repeat('x', 5000)]]]);
        self::assertCount(SseDigitalTerrainDetail::MAX_PER_LIST, $many['contacts']);
        self::assertSame(SseDigitalTerrainDetail::MAX_TEXT, mb_strlen((string) $many['messages'][0]['body']));

        $broken = SseDigitalTerrainDetail::normalize(['contacts' => 'pas une liste', 'sms' => null, 'calls' => [null, ['x' => 1]], 'photos' => [[]], 'locations' => [['when' => '2026-13-45 99:99']], 'applications' => [['version' => '1']]]);
        self::assertTrue(SseDigitalTerrainDetail::isEmpty($broken), 'valeurs NULL ou absentes : rien d’inventé, pas d’erreur');
        self::assertTrue(SseDigitalTerrainDetail::isEmpty(SseDigitalTerrainDetail::normalize([])));
        self::assertSame(['contacts' => 0, 'messages' => 0, 'calls' => 0, 'media' => 0, 'locations' => 0, 'accounts' => 0, 'applications' => 0, 'networks' => 0, 'artifacts' => 0], SseDigitalTerrainDetail::counts(SseDigitalTerrainDetail::normalize([])));
        $badDate = SseDigitalTerrainDetail::normalize(['locations' => [['label' => 'Pont', 'when' => '2026-13-45 99:99']]]);
        self::assertNull($badDate['locations'][0]['observed_at']);
    }

    public function testOldModLinesStillGiveWhatTheyCite(): void
    {
        $phone = SseDigitalTerrainDetail::fromLines(['Contacts récupérés : 5', 'Contacts (extrait) : ABU YASSIN, Farid, Karim', 'SMS : « Le colis est arrivé »']);
        $d = SseDigitalTerrainDetail::normalize($phone);
        self::assertCount(3, $d['contacts']);
        self::assertSame('Le colis est arrivé', $d['messages'][0]['body']);
        self::assertSame([], SseDigitalTerrainDetail::fromLines(['Messages : 2', 'Images : 1']), 'des totaux ne sont pas des données');
    }

    // ------------------------------------------------------------------ ingestion → lecture (sqlite)

    public function testIngestionFillsEveryScreenFromTheFieldPayload(): void
    {
        $lab = $this->lab();
        $repo = $lab->repository();
        $r = $lab->ingestFromTerrain(1, self::phoneBody(), 7);
        self::assertTrue($r['ok']);
        self::assertFalse($r['duplicate']);
        $dev = (int) $r['device_id'];
        self::assertSame(2, $r['detail']['contacts']);
        self::assertCount(2, $repo->listContacts($dev, 1));
        self::assertCount(2, $repo->listMessages($dev, 1));
        self::assertCount(2, $repo->listCalls($dev, 1));
        self::assertCount(1, $repo->listMedia($dev, 1));
        self::assertCount(1, $repo->listLocations($dev, 1));
        self::assertCount(1, $repo->listAccounts($dev, 1));
        self::assertCount(2, $repo->listApplications($dev, 1));
        $counts = $repo->contentCounts($dev, 1);
        self::assertSame(2, $counts['contacts']);
        self::assertGreaterThan(0, $counts['artifacts']);
        $acq = $repo->findAcquisition((int) $r['acquisition_id'], 1);
        self::assertSame('completed', $acq['status']);
        self::assertSame(0, (int) $acq['is_partial']);
        // Chronologie : seulement les éléments portant une vraie date (SMS du 12/11, appel du 12/11, image du 11/11).
        $types = array_map(static fn (array $e): string => (string) $e['event_type'], $repo->listTimeline(1, ['device_id' => $dev]));
        self::assertContains('message', $types);
        self::assertContains('call', $types);
        self::assertContains('media', $types);
        self::assertNotContains('location', $types, 'position sans date relevée : pas d’événement');
        $hub = $repo->hubCounts(1);
        self::assertSame(1, $hub['devices']);
        self::assertGreaterThan(0, $hub['artifacts']);
    }

    public function testReplayAndSplitSendsNeverDuplicateRows(): void
    {
        $lab = $this->lab();
        $repo = $lab->repository();
        $first = $lab->ingestFromTerrain(1, self::phoneBody(), 7);
        $dev = (int) $first['device_id'];
        $again = $lab->ingestFromTerrain(1, self::phoneBody(), 7);
        self::assertTrue($again['duplicate']);
        self::assertSame($dev, $again['device_id']);
        self::assertSame(array_fill_keys(array_keys($again['detail']), 0), $again['detail'], 'rejeu : rien d’écrit une seconde fois');
        self::assertCount(2, $repo->listContacts($dev, 1));
        self::assertCount(2, $repo->listMessages($dev, 1));

        // Détail découpé par la file du SSE : un nouveau morceau s'ajoute, le même morceau rejoué ne double rien.
        $part = ['kind' => 'digital_detail', 'record_id' => 'SSE-DIG-0001', 'idempotency_key' => 'DIGD-1', 'phone_data' => ['contacts' => ['ABU YASSIN', 'Nouveau Contact']]];
        $r1 = $lab->ingestDetailPayload(1, $part, 7);
        self::assertSame(1, $r1['detail']['contacts'], 'seul le contact inconnu est ajouté');
        $r2 = $lab->ingestDetailPayload(1, $part, 7);
        self::assertSame(0, $r2['detail']['contacts']);
        self::assertTrue($r2['duplicate']);
        self::assertCount(3, $repo->listContacts($dev, 1));
    }

    public function testDeviceKnownByItsSummaryOnlyAnnouncesReservesThenGetsItsContent(): void
    {
        $lab = $this->lab();
        $repo = $lab->repository();
        // Ancien mod (ou support seulement identifié) : totaux et lignes, aucun contenu.
        $old = self::phoneBody('SSE-DIG-0002');
        unset($old['phone_data']);
        $old['extraction_lines'] = ['Contacts récupérés : 3', 'Messages : 2'];
        $r = $lab->ingestFromTerrain(1, $old, 7);
        $dev = (int) $r['device_id'];
        self::assertSame([], $r['detail']);
        $acq = $repo->findAcquisition((int) $r['acquisition_id'], 1);
        self::assertSame('completed_with_reserves', $acq['status']);
        self::assertSame(1, (int) $acq['is_partial']);
        self::assertStringContainsString('3 contacts', (string) $acq['reserves'], 'dit ce que le terrain n’a envoyé qu’en total');
        self::assertStringContainsString('Extraire le support en jeu', (string) $acq['reserves'], 'dit quoi faire');
        self::assertSame(0, array_sum(array_diff_key($repo->contentCounts($dev, 1), ['artifacts' => 0])));

        // Le contenu relevé arrive ensuite : les écrans se remplissent, la réserve tombe.
        $d = $lab->ingestDetailPayload(1, ['record_id' => 'SSE-DIG-0002', 'phone_data' => self::phoneBody()['phone_data']], 7);
        self::assertSame($dev, $d['device_id']);
        self::assertSame(2, $d['detail']['contacts']);
        $acq = $repo->findAcquisition((int) $r['acquisition_id'], 1);
        self::assertSame('completed', $acq['status']);
        self::assertSame(0, (int) $acq['is_partial']);
        self::assertEmpty($acq['reserves']);
    }

    public function testContentArrivingBeforeTheAcquisitionCreatesTheDeviceOnce(): void
    {
        $lab = $this->lab();
        $repo = $lab->repository();
        $d = $lab->ingestDetailPayload(1, ['record_id' => 'SSE-DIG-0003', 'source_type' => 'LAPTOP', 'computer_data' => ['files' => [['name' => 'plan.docx']]]], 7);
        self::assertArrayNotHasKey('error', $d);
        $dev = (int) $d['device_id'];
        self::assertSame('ordinateur', $repo->findDevice($dev, 1)['device_type']);
        // L'acquisition qui suit retrouve le même support : pas de second support.
        $body = self::phoneBody('SSE-DIG-0003');
        unset($body['phone_data']);
        $body['source_type'] = 'LAPTOP';
        $again = $lab->ingestFromTerrain(1, $body, 7);
        self::assertSame($dev, $again['device_id']);
        self::assertTrue($again['duplicate']);
        self::assertSame(1, $repo->hubCounts(1)['devices']);
    }

    public function testEmptyOrAnonymousDetailIsRefusedAndOtherCommunitiesStayApart(): void
    {
        $lab = $this->lab();
        $repo = $lab->repository();
        self::assertSame('record_id_required', $lab->ingestDetailPayload(1, ['phone_data' => ['contacts' => ['A']]])['error'] ?? null);
        self::assertSame('detail_empty', $lab->ingestDetailPayload(1, ['record_id' => 'SSE-DIG-0004', 'phone_data' => []])['error'] ?? null);
        self::assertSame('detail_empty', $lab->ingestDetailPayload(1, ['record_id' => 'SSE-DIG-0004', 'phone_data' => ['contacts' => ['', null]]])['error'] ?? null);
        self::assertSame(0, $repo->hubCounts(1)['devices'], 'rien d’enregistré sur un refus');

        $r = $lab->ingestFromTerrain(1, self::phoneBody(), 7);
        $dev = (int) $r['device_id'];
        self::assertCount(2, $repo->listContacts($dev, 1));
        self::assertSame([], $repo->listContacts($dev, 2), 'autre communauté : rien');
        self::assertSame(0, $repo->hubCounts(2)['devices']);
        self::assertSame(0, array_sum($repo->contentCounts($dev, 2)));
        // Même identifiant de support dans l'autre communauté : un support distinct.
        $other = $lab->ingestFromTerrain(2, self::phoneBody(), 8);
        self::assertNotSame($dev, $other['device_id']);
        self::assertCount(2, $repo->listContacts((int) $other['device_id'], 2));
    }

    // ------------------------------------------------------------------ écrans

    /** @param array<string, mixed> $vars */
    private function renderView(string $name, array $vars): string
    {
        if (!function_exists('url') || !function_exists('asset_url')) {
            self::markTestSkipped('helpers du site requis');
        }
        $root = dirname(__DIR__, 2);
        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'labviews_' . bin2hex(random_bytes(4));
        $dir = $this->tmp . '/atak/sse/digital';
        mkdir($dir, 0777, true);
        foreach (glob($root . '/views/atak/sse/digital/*.php') ?: [] as $f) {
            copy($f, $dir . '/' . basename($f));
        }
        file_put_contents($this->tmp . '/atak/sse/_layout.php', '<?php echo $sseContent;');
        extract($vars, EXTR_SKIP);
        ob_start();
        require $dir . '/' . $name . '.php';

        return (string) ob_get_clean();
    }

    /** @return array<string, mixed> */
    private static function emptyPhoneVars(): array
    {
        return [
            'device' => ['id' => 3, 'reference_code' => 'DEV-000003', 'device_type_label' => 'Téléphone', 'status_label' => 'Acquis'],
            'contacts' => [], 'messages' => [], 'calls' => [], 'accounts' => [], 'locations' => [], 'networks' => [], 'applications' => [],
            'media' => [], 'artifacts' => [], 'timeline' => [], 'acquisitions' => [], 'contentCounts' => ['contacts' => 0, 'messages' => 0, 'artifacts' => 0], 'labSubnav' => 'supports',
        ];
    }

    public function testEmptyPhoneScreenSaysWhatToDoToGetData(): void
    {
        $html = $this->renderView('phone', self::emptyPhoneVars());
        self::assertStringContainsString('Aucun contenu relevé sur ce support', $html);
        self::assertStringContainsString('exploitez le téléphone à fond', $html, 'dit quoi faire en jeu');
        self::assertStringContainsString('terminal SEEK', $html);
        foreach (['Aucun contact relevé.', 'Aucun message relevé.', 'Aucun appel relevé.', 'Aucune image relevée.', 'Aucune position relevée.', 'Aucun compte relevé.', 'Aucune application relevée.'] as $line) {
            self::assertStringContainsString($line, $html);
        }
        // Support relevé seulement par ses totaux : la réserve de l'acquisition est affichée telle quelle.
        $vars = self::emptyPhoneVars();
        $vars['acquisitions'] = [['reserves' => 'Support identifié, contenu non relevé : le terrain n’a transmis que les totaux (3 contacts).']];
        self::assertStringContainsString('contenu non relevé : le terrain n’a transmis que les totaux (3 contacts)', $this->renderView('phone', $vars));
    }

    public function testFilledPhoneScreenShowsAllRubrics(): void
    {
        $vars = self::emptyPhoneVars();
        $vars['contacts'] = [['display_name' => 'Farid Garage', 'phone_number' => '+33 6 12 34 56 78', 'alias_label' => null]];
        $vars['messages'] = [['sent_at' => '2026-11-12 08:40:00', 'sender_label' => 'Farid Garage', 'recipient_label' => 'Karim', 'body' => 'Le colis est arrivé.', 'is_deleted' => 1]];
        $vars['calls'] = [['started_at' => null, 'peer_label' => 'Inconnu', 'peer_number' => null, 'direction' => 'missed', 'duration_sec' => 0]];
        $vars['media'] = [['name' => 'Entrepôt nord', 'captured_at' => '2026-11-11 17:30:00', 'media_type' => 'image']];
        $vars['locations'] = [['label' => 'Entrepôt nord', 'source_label' => 'Carroyage 045 078', 'lat' => null, 'lng' => null]];
        $vars['accounts'] = [['service_label' => 'Telegram', 'username' => 'karim_h']];
        $vars['applications'] = [['app_name' => 'Maps']];
        $vars['contentCounts'] = ['contacts' => 1, 'messages' => 1, 'artifacts' => 0];
        $html = $this->renderView('phone', $vars);
        self::assertStringNotContainsString('Aucun contenu relevé sur ce support', $html);
        foreach (['Farid Garage', '+33 6 12 34 56 78', 'Le colis est arrivé.', '(supprimé)', 'Manqué', 'Entrepôt nord', 'Carroyage 045 078', 'Telegram', 'Maps', '2026-11-12 08:40'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringNotContainsString('Aucun contact relevé.', $html);
    }

    public function testComputerCommunicationsTimelineAndHubExplainWhenEmpty(): void
    {
        $pc = $this->renderView('computer', ['device' => ['id' => 4, 'reference_code' => 'DEV-000004'], 'contacts' => [], 'messages' => [], 'calls' => [], 'accounts' => [], 'locations' => [], 'networks' => [], 'applications' => [], 'media' => [], 'artifacts' => [], 'timeline' => [], 'acquisitions' => [], 'contentCounts' => [], 'labSubnav' => 'supports']);
        self::assertStringContainsString('Aucun contenu relevé sur ce support', $pc);
        self::assertStringContainsString('exploitez l’ordinateur à fond', $pc);
        self::assertStringContainsString('Aucun fichier chiffré relevé.', $pc);
        self::assertStringContainsString('Aucun historique de navigation ni courriel relevé.', $pc);

        $com = $this->renderView('communications', ['devices' => [['id' => 3, 'reference_code' => 'DEV-000003']], 'deviceId' => 3, 'messages' => [], 'calls' => [], 'contacts' => [], 'contentCounts' => null, 'labSubnav' => 'communications']);
        self::assertStringContainsString('Aucun message relevé sur ce support', $com);
        self::assertStringContainsString('extraction complète', $com);
        $noDevice = $this->renderView('communications', ['devices' => [], 'deviceId' => 0, 'messages' => [], 'calls' => [], 'contacts' => [], 'contentCounts' => null, 'labSubnav' => 'communications']);
        self::assertStringContainsString('terminal SEEK ou app SSE de l’ATAK', $noDevice);

        $tl = $this->renderView('timeline', ['events' => [], 'devices' => [], 'filters' => [], 'labSubnav' => 'chronologies']);
        self::assertStringContainsString('aucune date n’est inventée', $tl);
        self::assertStringContainsString('value="media"', $tl);
        self::assertStringContainsString('value="location"', $tl);
    }

    // ------------------------------------------------------------------ câblage site et mod

    public function testApiRoutesTheDetailKindAndTheModSendsIt(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSame('digital_detail', SseDigitalLabService::DETAIL_KIND);
        $api = (string) file_get_contents($root . '/app/Controllers/Api/SseIdentityApiController.php');
        self::assertStringContainsString('SseDigitalLabService::DETAIL_KIND', $api);
        self::assertStringContainsString('ingestDetailPayload', $api);
        self::assertStringContainsString("'sse_digital_detail|'", $api, 'débit borné');

        $mod = $root . '/mod/@COMSPEC_SSE/addons/network';
        $builder = (string) file_get_contents($mod . '/functions/fn_buildAthenaDigitalDetail.sqf');
        self::assertStringContainsString('"EXPLOITED"', $builder, 'seulement ce que le joueur a extrait');
        self::assertStringContainsString('"full", "computer_full"', $builder);
        self::assertStringContainsString('"*"', $builder);
        self::assertStringContainsString('_limit', $builder, 'morceaux bornés');
        $submit = (string) file_get_contents($mod . '/functions/fn_submitDigitalAcquisition.sqf');
        self::assertStringContainsString('buildAthenaDigitalDetail', $submit);
        self::assertStringContainsString('"digital_detail"', $submit);
        self::assertStringContainsString('comspec_sse_fnc_fileSubmit', $submit);
        self::assertStringContainsString('"record_id", _uid', $submit, 'même support que l’acquisition');
        self::assertStringContainsString('class buildAthenaDigitalDetail', (string) file_get_contents($mod . '/config.cpp'));
    }

    public function testSimulationStaysManagementOnlyAndNoCannedFindingsForFieldData(): void
    {
        $root = dirname(__DIR__, 2);
        $ctl = (string) file_get_contents($root . '/app/Controllers/Web/SseDigitalLabController.php');
        $store = substr($ctl, (int) strpos($ctl, 'public function acquisitionStore'));
        $store = substr($store, 0, (int) strpos($store, 'public function acquisitionsIndex'));
        self::assertStringContainsString('canManage()', $store);
        self::assertStringContainsString('Csrf::validate', $store);
        $svc = (string) file_get_contents($root . '/app/Services/Sse/SseDigitalLabService.php');
        $ingest = substr($svc, (int) strpos($svc, 'public function ingestFromTerrain'));
        $ingest = substr($ingest, 0, (int) strpos($ingest, 'public function ingestDetailPayload'));
        self::assertStringNotContainsString('generateFindings', $ingest, 'pas de signaux d’exemple sur des données réelles');
        self::assertStringNotContainsString('seedProfile', $ingest);
    }
}
