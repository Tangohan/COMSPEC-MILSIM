<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\Game\GameSseCatalogueApiController;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\SseDigitalLabRepository;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseSiteRepository;
use App\Services\Sse\SseGameCatalogueService;
use App\Services\Sse\SseWantedAccess;
use App\Services\Sse\SseWantedService;
use App\Support\ComspecApiKeyAuth;
use App\Support\SseAcquisitionContexts;
use App\Support\SseFileCatalog;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseWantedSqlite;

/**
 * Catalogue du mod SSE (ICD-SSE-CATALOGUE-001) : contenu borné à la communauté de la clé, rien de plus que des
 * identifiants, des références et des noms affichés, plafonds, pages de la taille d'un retour de COMSPEC Link, révision
 * stable, communauté jamais lue dans la requête, débit borné.
 */
final class SseGameCatalogueTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->saved = ['session' => $_SESSION ?? null, 'post' => $_POST ?? [], 'get' => $_GET ?? [], 'server' => $_SERVER];
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseWantedSqlite::open();
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (7, 1, '76561198000000007', 'Vrai Membre')");
        $this->pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, steam_id TEXT, access_token_hash TEXT, expires_at TEXT, revoked_at TEXT)');
        $this->pdo->exec('CREATE TABLE sse_cases (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, context_id INTEGER, reference_code TEXT, title TEXT, summary TEXT,
            classification TEXT, status TEXT, is_folder INTEGER DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE sse_sites (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, context_id INTEGER, case_id INTEGER, reference_code TEXT, name TEXT,
            site_type TEXT, status TEXT, summary TEXT, submitter_steam_id TEXT)');
        $this->pdo->exec('CREATE TABLE sse_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, campaign_key TEXT, name TEXT, status TEXT, payload TEXT, created_at TEXT, updated_at TEXT)');
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_IF_NONE_MATCH']);
        ComspecApiKeyAuth::resetForTests();
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
        ComspecApiKeyAuth::resetForTests();
    }

    // ------------------------------------------------------------------ Jeu de données

    private function service(bool $seesHidden = false): SseGameCatalogueService
    {
        return new SseGameCatalogueService(null, static fn (): bool => $seesHidden);
    }

    private function controller(bool $tooMany = false, ?string $maintenance = null, bool $sessionAllowed = true): GameSseCatalogueApiController
    {
        return new GameSseCatalogueApiController(
            $this->service(),
            static fn (): bool => $tooMany,
            static fn (): ?string => $maintenance,
            static fn (): bool => $sessionAllowed,
        );
    }

    /** Jeton de jeu du compte 7 de la communauté donnée. */
    private function gameToken(int $tenant): void
    {
        $token = str_repeat('b', 64);
        $st = $this->pdo->prepare('INSERT INTO game_sessions (tenant_id, user_id, access_token_hash, expires_at) VALUES (?, 7, ?, ?)');
        $st->execute([$tenant, hash('sha256', $token), '2099-01-01 00:00:00']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        ComspecApiKeyAuth::resetForTests();
    }

    private function identity(int $tenant, string $last, string $first): int
    {
        return (new SseIdentityRepository())->create($tenant, ['last_name' => $last, 'first_name' => $first, 'sex' => 'M', 'birth_date' => '1984-03-12', 'nationality' => 'TAK', 'birth_place' => 'Pyrgos']);
    }

    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO sse_cases (tenant_id, context_id, reference_code, title, summary, classification, status, is_folder) VALUES
            (1, 1, 'SSE-2026-0007', 'Réseau du port', 'Résumé interne à ne pas sortir', 'interne', 'ouvert', 0),
            (1, 1, 'SSE-2026-0008', 'Source protégée', 'x', 'confidentiel', 'ouvert', 0),
            (1, 1, 'SSE-2026-0003', 'Affaire close', 'x', 'interne', 'clos', 0),
            (1, 1, 'CLASSEUR-A', 'Classeur', 'x', 'interne', 'ouvert', 1),
            (2, 1, 'SSE-2026-0900', 'Dossier de Bravo', 'x', 'interne', 'ouvert', 0)");
        $this->pdo->exec("INSERT INTO sse_sites (tenant_id, context_id, case_id, reference_code, name, site_type, status, summary, submitter_steam_id) VALUES
            (1, 1, 1, 'SITE-2026-0001', 'Entrepôt quai 4', 'depot', 'ouvert', 'Compte rendu', '76561198000000001'),
            (1, 1, 2, 'SITE-2026-0002', 'Planque nord', 'habitation', 'en_cours', 'x', ''),
            (1, 1, NULL, 'SITE-2026-0003', 'Site clôturé', 'cache', 'cloture', 'x', ''),
            (1, 9, NULL, 'SITE-2026-0004', 'Autre carte', 'cache', 'ouvert', 'x', ''),
            (2, 1, NULL, 'SITE-2026-0900', 'Site de Bravo', 'cache', 'ouvert', 'x', '')");
        $this->pdo->exec("INSERT INTO sse_campaigns (tenant_id, campaign_key, name, status, payload, created_at) VALUES
            (1, 'kavala', 'Campagne Kavala', 'active', 'CONTENU-SECRET', '2026-11-01 10:00:00'),
            (1, 'vieille', 'Archivée', 'archived', 'x', '2026-10-01 10:00:00'),
            (2, 'bravo', 'Campagne Bravo', 'active', 'x', '2026-11-01 10:00:00')");

        $wanted = $this->identity(1, 'Karimzadeh', 'Élodie');
        $access = new SseWantedAccess(false, true, true, true, 1, 7, 'ANALYSTE');
        $placed = (new SseWantedService())->place(1, $wanted, [
            'notice_type' => 'hvt', 'threat_level' => 'high', 'armed' => 'yes',
            'reason' => 'MOTIF-CONFIDENTIEL', 'instructions' => 'CONSIGNE-CONFIDENTIELLE',
        ], $access);
        self::assertTrue($placed['ok']);
        $other = $this->identity(2, 'Bravo', 'Cible');
        (new SseWantedService())->place(2, $other, ['notice_type' => 'poi', 'threat_level' => 'low', 'armed' => 'no', 'reason' => 'x'], new SseWantedAccess(false, true, true, true, 2, 7, 'ANALYSTE'));

        $lore = new SseLoreRepository();
        $sid = $lore->create(1, ['title' => 'Soirée du 15', 'op_key' => 'OP-KAVALA', 'context_id' => 1], 7);
        $known = $this->identity(1, 'Dupont', 'Marc');
        $hidden = $this->identity(1, 'Secret', 'Agent');
        (new SseFileRecordRepository())->saveProfile(1, $hidden, ['visibility' => ['identity' => ['v' => 'hidden', 'phase' => 0]]], null);
        $lore->addMember(1, $sid, $known, 'AI-001');
        $lore->addMember(1, $sid, $hidden, 'AI-002');
        $sid2 = $lore->create(2, ['title' => 'Chez Bravo', 'op_key' => 'OP-KAVALA', 'context_id' => 1], 7);
        $lore->addMember(2, $sid2, $other, 'AI-900');
    }

    /** @return list<array<int, mixed>> ce que le jeu relit : même forme que parseSimpleArray. */
    private static function parse(string $ref): array
    {
        self::assertStringNotContainsString('|', $ref);
        self::assertStringNotContainsString("\n", $ref);
        $json = preg_replace_callback('/"((?:[^"]|"")*)"/', static fn (array $m): string => (string) json_encode(str_replace('""', '"', $m[1]), JSON_UNESCAPED_UNICODE), $ref);
        $rows = json_decode((string) $json, true);
        self::assertIsArray($rows, $ref);

        return $rows;
    }

    // ------------------------------------------------------------------ Contenu

    public function testCatalogueListsOnlyWhatTheCommunityMayShowTheGame(): void
    {
        $this->seed();
        $c = $this->service()->build(1, 1, 'OP-KAVALA', null);

        self::assertSame(SseGameCatalogueService::VERSION, $c['version']);
        self::assertSame(array_keys(SseGameCatalogueService::SECTIONS), array_keys($c['sections']));
        self::assertSame([['id' => 1, 'ref' => 'SSE-2026-0007', 'name' => 'Réseau du port']], $c['sections']['cases'], 'Ni confidentiel, ni clos, ni classeur, ni autre communauté');
        self::assertSame(
            [
                ['id' => 2, 'ref' => 'SITE-2026-0002', 'name' => 'Planque nord', 'type' => 'habitation', 'case' => ''],
                ['id' => 1, 'ref' => 'SITE-2026-0001', 'name' => 'Entrepôt quai 4', 'type' => 'depot', 'case' => 'SSE-2026-0007'],
            ],
            $c['sections']['sites'],
            'Sites ouverts de la carte ; le dossier confidentiel d’un site n’est pas nommé'
        );
        self::assertSame([['key' => 'kavala', 'name' => 'Campagne Kavala']], $c['sections']['campaigns']);
        self::assertCount(1, $c['sections']['wanted']);
        self::assertSame(['id', 'ref', 'name', 'level', 'type'], array_keys($c['sections']['wanted'][0]));
        self::assertSame('KARIMZADEH Élodie', $c['sections']['wanted'][0]['name']);
        self::assertSame('high', $c['sections']['wanted'][0]['level']);
        self::assertSame(['id' => 1, 'op' => 'OP-KAVALA', 'title' => 'Soirée du 15'], $c['lore_session']);
        self::assertSame(
            [['uid' => 'AI-001', 'name' => 'DUPONT Marc'], ['uid' => 'AI-002', 'name' => '']],
            array_map(static fn (array $r): array => ['uid' => $r['uid'], 'name' => $r['name']], $c['sections']['lore']),
            'Identité cachée par le MJ : la référence seule'
        );

        $all = (string) json_encode($c, JSON_UNESCAPED_UNICODE);
        foreach (['MOTIF-CONFIDENTIEL', 'CONSIGNE-CONFIDENTIELLE', 'CONTENU-SECRET', 'Résumé interne', 'Bravo', '1984-03-12', 'Pyrgos', '7656119', 'Source protégée', 'SSE-2026-0008', 'Compte rendu'] as $leak) {
            self::assertStringNotContainsString($leak, $all);
        }
    }

    public function testHiddenLoreNameIsGivenOnlyToAnAccountAllowedToReadIt(): void
    {
        $this->seed();
        $plain = $this->service(true)->build(1, 1, 'OP-KAVALA', null);
        self::assertSame('', $plain['sections']['lore'][1]['name'], 'Clé de communauté seule : personne n’est identifié');
        $master = $this->service(true)->build(1, 1, 'OP-KAVALA', 7);
        self::assertSame('SECRET Agent', $master['sections']['lore'][1]['name']);
        self::assertNotSame($plain['revision'], $master['revision']);

        $none = $this->service()->build(1, 1, '', null);
        self::assertNull($none['lore_session']);
        self::assertSame([], $none['sections']['lore']);
        self::assertNull($this->service()->build(1, 1, 'OP-INCONNUE', null)['lore_session']);
        self::assertNull($this->service()->build(1, 1, "x' OR 1=1 --", null)['lore_session']);
    }

    public function testReferenceListsFollowTheSiteCataloguesInBothLanguages(): void
    {
        $s = $this->service()->build(1, 1)['sections'];
        self::assertSame(array_column(SseAcquisitionContexts::defaults()->entries(true), 'code'), array_column($s['contexts'], 'code'));
        self::assertSame(array_keys(SsePersonRepository::STATUS_LABELS), array_column($s['statuses'], 'code'));
        self::assertSame(array_keys(SseSiteRepository::SEIZURE_LABELS), array_column($s['pieces'], 'code'));
        self::assertSame(array_keys(SseDigitalLabRepository::DEVICE_TYPES), array_column($s['supports'], 'code'));
        self::assertSame(array_keys(SseSiteRepository::TYPE_LABELS), array_column($s['site_types'], 'code'));
        self::assertSame(array_keys(SseFileCatalog::MENTIONS), array_column($s['mentions'], 'code'));
        self::assertContains('passport', array_column($s['documents'], 'code'));
        self::assertSame(['low', 'medium', 'high', 'critical'], array_column($s['threats'], 'code'));
        // Chaque libellé anglais écrit ici correspond à un code du site (sinon il ne servirait jamais).
        foreach (SseGameCatalogueService::EN as $section => $labels) {
            self::assertSame(array_column($s[$section], 'code'), array_keys($labels), $section);
        }
        foreach ($s as $name => $rows) {
            foreach ($rows as $row) {
                self::assertSame(SseGameCatalogueService::SECTIONS[$name], array_keys($row), $name);
                if (isset($row['fr'])) {
                    self::assertNotSame('', $row['fr']);
                    self::assertNotSame('', $row['en']);
                }
            }
        }
        self::assertSame('Civilian', $s['statuses'][0]['en']);
    }

    public function testCommunityContextSettingsReachTheGame(): void
    {
        (new SseFileRecordRepository())->saveSettings(1, [
            'acquisition_set' => 'us_sof',
            'acquisition_overrides' => ['cordon_fouille' => ['enabled' => false], 'raid_action_directe' => ['fr' => 'Assaut maison', 'en' => 'House raid']],
            'acquisition_custom' => [['code' => 'x_marche', 'group' => 'autre', 'fr' => 'Contrôle au marché', 'en' => 'Market check', 'enabled' => true]],
        ], null);
        $rows = array_column($this->service()->build(1, 1)['sections']['contexts'], null, 'code');
        self::assertArrayNotHasKey('cordon_fouille', $rows);
        self::assertSame('Assaut maison', $rows['raid_action_directe']['fr']);
        self::assertSame('House raid', $rows['raid_action_directe']['en']);
        $custom = array_values(array_filter($rows, static fn (array $r): bool => $r['fr'] === 'Contrôle au marché'));
        self::assertCount(1, $custom);
        // L'autre communauté garde le catalogue par défaut.
        self::assertContains('cordon_fouille', array_column($this->service()->build(2, 1)['sections']['contexts'], 'code'));
    }

    public function testLivingListsAreCappedAndSayWhenTheyAreCut(): void
    {
        $st = $this->pdo->prepare("INSERT INTO sse_cases (tenant_id, context_id, reference_code, title, classification, status, is_folder) VALUES (1, 1, ?, ?, 'interne', 'ouvert', 0)");
        for ($i = 0; $i < SseGameCatalogueService::LIMITS['cases'] + 15; $i++) {
            $st->execute([sprintf('SSE-2026-%04d', $i), "Dossier \"n° {$i}\" | très long " . str_repeat('é', 200)]);
        }
        $c = $this->service()->build(1, 1);
        self::assertCount(SseGameCatalogueService::LIMITS['cases'], $c['sections']['cases']);
        self::assertSame(['cases'], $c['truncated']);
        self::assertLessThanOrEqual(SseGameCatalogueService::LABEL_MAX, mb_strlen($c['sections']['cases'][0]['name']));

        $pages = SseGameCatalogueService::pages($c);
        self::assertGreaterThan(1, count($pages));
        self::assertLessThanOrEqual(SseGameCatalogueService::MAX_PAGES, count($pages));
        $seen = [];
        foreach ($pages as $i => $page) {
            self::assertLessThanOrEqual(SseGameCatalogueService::PAGE_BYTES, strlen($page), "page {$i}");
            $p = self::parse($page);
            self::assertSame([SseGameCatalogueService::VERSION, $c['revision']], [$p[0], $p[1]]);
            self::assertSame($i, $p[4]);
            self::assertSame($i + 1 < count($pages) ? $i + 1 : 0, $p[5]);
            self::assertSame(['cases'], $p[7]);
            foreach ($p[6] as [$name, $rows]) {
                foreach ($rows as $row) {
                    $seen[$name][] = $row;
                }
                $seen[$name] ??= [];
            }
        }
        // Recollées, les pages redonnent le paquet entier, section par section et dans l'ordre des colonnes.
        self::assertSame(array_keys(SseGameCatalogueService::SECTIONS), array_keys($seen));
        foreach ($c['sections'] as $name => $rows) {
            self::assertCount(count($rows), $seen[$name], $name);
        }
        self::assertSame(array_values($c['sections']['cases'][3]), [$seen['cases'][3][0], $seen['cases'][3][1], str_replace(['\p'], ['|'], $seen['cases'][3][2])]);
    }

    public function testRevisionChangesOnlyWithTheContent(): void
    {
        $this->seed();
        $a = $this->service()->build(1, 1, 'OP-KAVALA');
        $b = $this->service()->build(1, 1, 'OP-KAVALA');
        self::assertSame($a['revision'], $b['revision']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $a['revision']);
        self::assertGreaterThan(0, SseGameCatalogueService::revisionId($a['revision']));
        $this->pdo->exec("UPDATE sse_cases SET status = 'clos' WHERE id = 1");
        self::assertNotSame($a['revision'], $this->service()->build(1, 1, 'OP-KAVALA')['revision']);

        $only = SseGameCatalogueService::only($a, ['cases', 'inconnue']);
        self::assertSame(['cases'], array_keys($only['sections']));
        self::assertSame($a['revision'], $only['revision']);
        self::assertSame(array_keys($a['sections']), array_keys(SseGameCatalogueService::only($a, [])['sections']));
    }

    public function testMissingTablesGiveEmptyListsNotAnError(): void
    {
        foreach (['sse_cases', 'sse_sites', 'sse_campaigns', 'sse_wanted_notices'] as $t) {
            $this->pdo->exec('DROP TABLE ' . $t);
        }
        \App\Repositories\SseWantedNoticeRepository::forgetReady();
        $c = $this->service()->build(1, 1, 'OP-KAVALA');
        foreach (['cases', 'sites', 'campaigns', 'wanted', 'lore'] as $name) {
            self::assertSame([], $c['sections'][$name], $name);
        }
        self::assertNotSame([], $c['sections']['contexts']);
        self::assertCount(1, SseGameCatalogueService::pages($c));
    }

    // ------------------------------------------------------------------ Route

    public function testRouteAnswersForTheCommunityOfTheKeyNeverForARequestedOne(): void
    {
        $this->seed();
        $this->gameToken(2);
        $_GET = ['tenant_id' => '1', 'tenant_slug' => 'alpha', 'map' => '1', 'op' => 'OP-KAVALA'];
        $resp = $this->controller()->show(new Request());
        self::assertSame(200, $resp->statusCode());
        $data = json_decode($resp->body(), true);
        self::assertSame([['id' => 5, 'ref' => 'SSE-2026-0900', 'name' => 'Dossier de Bravo']], $data['sections']['cases']);
        self::assertSame('Chez Bravo', $data['lore_session']['title']);
        self::assertStringNotContainsString('Réseau du port', $resp->body());
        self::assertStringNotContainsString('KARIMZADEH', $resp->body());
        self::assertStringStartsWith('W/"ssecat-', (string) $resp->headerValue('ETag'));
        self::assertSame('private, no-cache', $resp->headerValue('Cache-Control'));
    }

    public function testRouteRefusesWithoutKeyOrWithAWrongOne(): void
    {
        $this->seed();
        $resp = $this->controller()->show(new Request());
        self::assertSame(401, $resp->statusCode());

        $_GET = ['tenant_id' => '1'];
        self::assertSame(401, $this->controller()->show(new Request())->statusCode(), 'Une communauté en paramètre ne vaut pas une clé');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('z', 64);
        ComspecApiKeyAuth::resetForTests();
        $resp = $this->controller()->show(new Request());
        self::assertSame(401, $resp->statusCode());
        self::assertStringNotContainsString('sections', $resp->body());
    }

    public function testPortalSessionNeedsItsClearance(): void
    {
        $this->seed();
        $_SESSION = ['user_id' => 7, 'tenant_id' => 1];
        self::assertSame(403, $this->controller(false, null, false)->show(new Request())->statusCode());
        $resp = $this->controller()->show(new Request());
        self::assertSame(200, $resp->statusCode());
        self::assertSame('SSE-2026-0007', json_decode($resp->body(), true)['sections']['cases'][0]['ref']);
    }

    public function testKnownRevisionCostsNothingAndSectionsCanBeFiltered(): void
    {
        $this->seed();
        $this->gameToken(1);
        $first = json_decode($this->controller()->show(new Request())->body(), true);

        $_GET = ['since_rev' => $first['revision']];
        $same = json_decode($this->controller()->show(new Request())->body(), true);
        self::assertTrue($same['unchanged']);
        self::assertArrayNotHasKey('sections', $same);

        $_GET = [];
        $_SERVER['HTTP_IF_NONE_MATCH'] = 'W/"ssecat-' . $first['revision'] . '"';
        $resp = $this->controller()->show(new Request());
        self::assertSame(304, $resp->statusCode());
        self::assertSame('', $resp->body());
        unset($_SERVER['HTTP_IF_NONE_MATCH']);

        $_GET = ['sections' => 'contexts, cases'];
        $some = json_decode($this->controller()->show(new Request())->body(), true);
        self::assertSame(['contexts', 'cases'], array_keys($some['sections']));
    }

    public function testGameChannelReturnsPagesAndTheUnchangedMark(): void
    {
        $this->seed();
        $this->gameToken(1);
        $resp = $this->controller()->kindResponse(['kind' => SseGameCatalogueService::KIND, 'map' => 1, 'op' => 'OP-KAVALA', 'tenant_id' => 2]);
        self::assertSame(200, $resp->statusCode());
        $data = json_decode($resp->body(), true);
        self::assertIsInt($data['id']);
        self::assertGreaterThan(0, $data['id']);
        self::assertLessThanOrEqual(SseGameCatalogueService::PAGE_BYTES, strlen($data['ref']));
        $page = self::parse($data['ref']);
        self::assertSame(SseGameCatalogueService::TTL, $page[3]);
        self::assertSame([1, 'OP-KAVALA', 'Soirée du 15'], $page[8]);
        $sections = array_column($page[6], 1, 0);
        self::assertSame([1, 'SSE-2026-0007', 'Réseau du port'], $sections['cases'][0]);
        self::assertContains('exploitation_site_sensible', array_column($sections['contexts'], 0));

        $again = json_decode($this->controller()->kindResponse(['kind' => SseGameCatalogueService::KIND, 'rev' => $page[1], 'op' => 'OP-KAVALA'])->body(), true);
        self::assertTrue($again['unchanged']);
        $mark = self::parse($again['ref']);
        self::assertSame($page[1], $mark[1]);
        self::assertSame([], $mark[6]);
        self::assertTrue($mark[9]);

        // Format SQF aussi par la route de lecture, page hors borne ramenée à la dernière.
        $_GET = ['format' => 'sqf', 'page' => '99', 'op' => 'OP-KAVALA'];
        $paged = json_decode($this->controller()->show(new Request())->body(), true);
        self::assertSame($paged['pages'] - 1, $paged['page']);
        self::assertSame(0, $paged['next']);
    }

    public function testRateLimitAndMaintenanceStopTheRead(): void
    {
        $this->seed();
        $this->gameToken(1);
        $resp = $this->controller(true)->show(new Request());
        self::assertSame(429, $resp->statusCode());
        self::assertSame((string) GameSseCatalogueApiController::RATE_WINDOW, $resp->headerValue('Retry-After'));
        self::assertSame(GameSseCatalogueApiController::RATE_WINDOW, json_decode($resp->body(), true)['retry_after']);

        $resp = $this->controller(false, 'Exercice en cours.')->show(new Request());
        self::assertSame(503, $resp->statusCode());
        self::assertSame('Exercice en cours.', json_decode($resp->body(), true)['message']);
    }

    public function testRouteIsDeclaredUnderTheProtectedGamePrefix(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertStringContainsString("\$router->get('/api/game/v1/sse/catalogue', [\\App\\Controllers\\Api\\Game\\GameSseCatalogueApiController::class, 'show']);", $routes);
        self::assertTrue(ComspecApiKeyAuth::pathRequiresProtection('/api/game/v1/sse/catalogue'));
        self::assertInstanceOf(Database::class, Database::getInstance());
    }
}
