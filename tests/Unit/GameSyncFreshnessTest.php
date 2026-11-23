<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\GameSyncFreshnessService;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Fraîcheur des données du jeu (bandeau « Données du jeu il y a Xs », diagnostic par joueur) sur sqlite, et câblage :
 * routes, droits, en-têtes, bandeau des pages, instrumentation du téléphone (fn_syncMark, page Debug).
 */
final class GameSyncFreshnessTest extends TestCase
{
    private const T = 7;

    private const NOW = 1_800_000_000;

    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        ShortCache::setEnabled(false);
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE atak_ingest_player_daily (tenant_id INTEGER, day TEXT, steam_uid TEXT, callsign TEXT, last_at TEXT, PRIMARY KEY (tenant_id, day, steam_uid))');
        $this->pdo->exec('CREATE TABLE atak_units (id INTEGER PRIMARY KEY, tenant_id INTEGER, map_id INTEGER, call_sign TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE atak_air_assets (id INTEGER PRIMARY KEY, tenant_id INTEGER, callsign TEXT, updated_at TEXT)');
        $this->pdo->exec("CREATE TABLE atak_chat_messages (id INTEGER PRIMARY KEY, tenant_id INTEGER, author TEXT, source TEXT DEFAULT 'game', created_at TEXT)");
        $this->pdo->exec('CREATE TABLE atak_tactical_reports (id INTEGER PRIMARY KEY, tenant_id INTEGER, submitter_steam_id TEXT, report_timestamp TEXT)');
        // atak_markers, atak_map_shapes, atak_nine_line, atak_orders absentes : « aucune donnée », jamais une erreur.
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
        parent::tearDown();
    }

    private static function at(int $ago): string
    {
        return date('Y-m-d H:i:s', self::NOW - $ago);
    }

    public function testTenantSummaryGivesAgeAndStatePerFlow(): void
    {
        $this->pdo->exec("INSERT INTO atak_units (tenant_id, map_id, call_sign, updated_at) VALUES (7, 1, 'VIPER 1', '" . self::at(12) . "'), (3, 1, 'AUTRE', '" . self::at(1) . "')");
        $this->pdo->exec("INSERT INTO atak_air_assets (tenant_id, callsign, updated_at) VALUES (7, 'HOG 21', '" . self::at(300) . "')");
        $this->pdo->exec("INSERT INTO atak_chat_messages (tenant_id, author, source, created_at) VALUES (7, 'VIPER 1', 'web', '" . self::at(2) . "'), (7, 'VIPER 1', 'game', '" . self::at(4000) . "')");

        $s = (new GameSyncFreshnessService($this->pdo))->tenantSummary(self::T, self::NOW);
        $flows = array_column($s['flows'], null, 'key');

        self::assertSame(12, $s['latest_age_s'], 'La donnée la plus récente de la communauté, pas celle d’une autre.');
        self::assertSame('live', $s['state']);
        self::assertSame(['live', 12], [$flows['position']['state'], $flows['position']['age_s']]);
        self::assertSame(['late', 300], [$flows['aircraft']['state'], $flows['aircraft']['age_s']]);
        self::assertSame(['stale', 4000], [$flows['chat']['state'], $flows['chat']['age_s']], 'Seuls les messages venus du jeu comptent.');
        self::assertSame(['none', null], [$flows['markers']['state'], $flows['markers']['age_s']]);
        self::assertSame(array_keys(GameSyncFreshnessService::FLOWS), array_keys($flows));
    }

    public function testEmptyCommunityHasNoData(): void
    {
        $s = (new GameSyncFreshnessService($this->pdo))->tenantSummary(self::T, self::NOW);

        self::assertNull($s['latest_age_s']);
        self::assertSame('none', $s['state']);
    }

    public function testPlayersListAgesPerFlowForRecentlySeenPlayers(): void
    {
        $today = date('Y-m-d', self::NOW);
        $st = $this->pdo->prepare('INSERT INTO atak_ingest_player_daily (tenant_id, day, steam_uid, callsign, last_at) VALUES (?, ?, ?, ?, ?)');
        $st->execute([7, $today, '76561198000000001', 'Viper 1', self::at(5)]);
        $st->execute([7, $today, '76561198000000002', 'Hog 2', self::at(90)]);
        $st->execute([7, $today, '76561198000000003', 'Ancien', self::at(GameSyncFreshnessService::PLAYER_WINDOW_SECONDS + 60)]);
        $st->execute([3, $today, '76561198000000009', 'Autre', self::at(1)]);
        $this->pdo->exec("INSERT INTO atak_units (tenant_id, map_id, call_sign, updated_at) VALUES (7, 1, 'VIPER 1', '" . self::at(70) . "'), (7, 2, 'Viper 1', '" . self::at(20) . "')");
        $this->pdo->exec("INSERT INTO atak_chat_messages (tenant_id, author, source, created_at) VALUES (7, 'Hog 2', 'game', '" . self::at(600) . "')");
        $this->pdo->exec("INSERT INTO atak_tactical_reports (tenant_id, submitter_steam_id, report_timestamp) VALUES (7, '76561198000000002', '" . self::at(30) . "')");

        $players = (new GameSyncFreshnessService($this->pdo))->players(self::T, self::NOW);
        $by = array_column($players, null, 'steam_uid');

        self::assertSame(['76561198000000001', '76561198000000002'], array_column($players, 'steam_uid'), 'Le plus récent d’abord, fenêtre de 2 h, communauté seule.');
        self::assertSame(5, $by['76561198000000001']['flows']['requests']);
        self::assertSame(20, $by['76561198000000001']['flows']['position'], 'Indicatif comparé sans la casse, position la plus récente.');
        self::assertSame('position', $by['76561198000000001']['oldest_flow']);
        self::assertNull($by['76561198000000002']['flows']['position']);
        self::assertSame(600, $by['76561198000000002']['flows']['chat']);
        self::assertSame(30, $by['76561198000000002']['flows']['reports']);
    }

    public function testStateThresholds(): void
    {
        self::assertSame('none', GameSyncFreshnessService::state(null));
        self::assertSame('live', GameSyncFreshnessService::state(60));
        self::assertSame('late', GameSyncFreshnessService::state(61));
        self::assertSame('stale', GameSyncFreshnessService::state(901));
        self::assertNull(GameSyncFreshnessService::age('', self::NOW));
    }

    public function testRoutesRightsHeadersAndPages(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("get('/synchro-jeu', [\\App\\Controllers\\Web\\GameSyncFreshnessController::class, 'summary'], [AuthMiddleware::class]", $routes);
        self::assertStringContainsString("get('/synchro-jeu/joueurs', [\\App\\Controllers\\Web\\GameSyncFreshnessController::class, 'players'], [AuthMiddleware::class]", $routes);

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/GameSyncFreshnessController.php');
        self::assertStringContainsString("'Cache-Control', 'no-store, private, max-age=0'", $ctrl);
        self::assertMatchesRegularExpression('/function players\(.*?AtakNineLineViewController::isManager\(\).*?403/s', $ctrl);

        $js = (string) file_get_contents($root . '/public/assets/js/game-sync-freshness.js');
        self::assertStringContainsString("cache: 'no-store'", $js);
        self::assertStringContainsString("'/synchro-jeu'", $js);
        self::assertStringContainsString("addEventListener('visibilitychange'", $js);
        self::assertStringContainsString("addEventListener('online'", $js);
        foreach (['views/atak/nine_line_view.php', 'views/atak/toc/console.php', 'views/operateur/page.php'] as $view) {
            $v = (string) file_get_contents($root . '/' . $view);
            self::assertStringContainsString('data-game-sync', $v, $view);
            self::assertStringContainsString('game-sync-freshness.js', $v, $view);
        }
        $css = (string) file_get_contents($root . '/public/assets/css/game-sync-freshness.css');
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertStringContainsString('font-size: 13px', $css);
        self::assertStringContainsString('prefers-color-scheme: dark', $css);
        self::assertStringNotContainsString('sse-', $css . $js);

        $nl = (string) file_get_contents($root . '/public/assets/js/atak-nine-line.js');
        self::assertStringContainsString('trackCallsigns(aircraft);', $nl);
        self::assertStringContainsString("Indicatif changé (avant : %1)", $nl);
        self::assertStringContainsString("cache: 'no-store'", $nl);
    }

    public function testPhoneShowsSyncPerFlowWithoutScreenPopups(): void
    {
        $root = dirname(__DIR__, 2);
        $ow = $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $mark = (string) file_get_contents($ow . '/functions/fn_syncMark.sqf');
        self::assertStringContainsString('COMSPEC_SyncMarks', $mark);
        self::assertStringContainsString('class syncMark {};', (string) file_get_contents($ow . '/config.cpp'));
        self::assertStringContainsString('[_cmd, "HELD"] call comspec_overwatch_connect_fnc_syncMark', (string) file_get_contents($ow . '/functions/fn_extDirect.sqf'));
        self::assertStringContainsString('[_cmd, _res] call comspec_overwatch_connect_fnc_syncMark', (string) file_get_contents($ow . '/functions/fn_extAwait.sqf'));
        $an = $root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
        self::assertStringContainsString('comspec_overwatch_connect_fnc_syncMark', (string) file_get_contents($an . '/functions/network/fn_extAwait.sqf'));
        $dbg = (string) file_get_contents($an . '/functions/pages/fn_pageDebug.sqf');
        self::assertStringContainsString('["section", "Synchronisation par flux", "Dernière réponse de COMSPEC Link"]', $dbg);
        self::assertStringContainsString('"Écritures en attente"', $dbg);
        $en = (string) file_get_contents($an . '/i18n/en.txt');
        self::assertStringContainsString('["Synchronisation par flux", "Sync by data flow"]', $en);
        foreach ([$mark, $dbg] as $src) {
            foreach (['hint ', 'hintSilent', 'systemChat', 'cutText', 'titleText'] as $banned) {
                self::assertStringNotContainsString($banned, $src);
            }
        }
        // Corrections de synchronisation : nouvel essai de Squad.Sync après un échec, qualifications relues toutes les 15 min.
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_SquadSyncSig", ["", diag_tickTime]]', (string) file_get_contents($an . '/functions/teams/fn_squadSync.sqf'));
        self::assertStringContainsString('diag_tickTime - _triedAt < 900', (string) file_get_contents($an . '/functions/network/fn_avatarPath.sqf'));
    }
}
