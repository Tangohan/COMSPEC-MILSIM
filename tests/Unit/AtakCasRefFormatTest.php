<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use App\Services\Monitoring\AtakModReportClientState;
use App\Services\Monitoring\AtakModReportDiscordTicketService;
use App\Services\Tactical\AirBriefService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Identifiant d'une demande d'appui aérien : le mod ne fabrique plus de nombre en notation scientifique
 * (« B765611998118681986.35867e+06 » donnait un 404 sur /api/cas/{ref}/status), le site retrouve les anciens ids
 * (« %2B » décodé) et répond un 404 JSON explicite sinon.
 */
final class AtakCasRefFormatTest extends TestCase
{
    private const LEGACY = 'B765611998118681986.35867e+06';

    private const TACTICAL = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical/';

    private static function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testNormalizeRefDecodesPercentPlusAndKeepsLegacyIds(): void
    {
        self::assertSame(self::LEGACY, AirBriefService::normalizeRef('B765611998118681986.35867e%2B06'));
        self::assertSame(self::LEGACY, AirBriefService::normalizeRef(self::LEGACY));
        self::assertSame('CAS-11868198-6358670', AirBriefService::normalizeRef('CAS-11868198-6358670'));
        self::assertSame('42', AirBriefService::normalizeRef(' 42 '));
    }

    public function testNormalizeRefRefusesMalformedIdsWithoutThrowing(): void
    {
        foreach (['', '%', '%zz', 'a b', 'a%20b', '../x', 'a/b', "a\0b", '<script>', str_repeat('A', 129), '%00'] as $bad) {
            self::assertNull(AirBriefService::normalizeRef($bad), 'refusé : ' . json_encode($bad));
        }
    }

    public function testRouterKeepsPercentEncodedSegmentAndDotsAreNotAnExtension(): void
    {
        $router = new Router(new Request());
        $regex = new \ReflectionMethod($router, 'pathToRegex');
        $pattern = $regex->invoke($router, '/api/cas/{id}/status');
        self::assertSame(1, preg_match($pattern, '/api/cas/B765611998118681986.35867e%2B06/status', $m));
        self::assertSame('B765611998118681986.35867e%2B06', $m['id'], 'le routeur ne décode pas : le contrôleur le fait');
        self::assertSame(1, preg_match($pattern, '/api/cas/' . self::LEGACY . '/status', $m2));
        self::assertSame(self::LEGACY, $m2['id']);
        self::assertSame(0, preg_match($pattern, '/api/cas/a/b/status'));
    }

    public function testLegacyIdIsFoundOnceDecodedAndUnknownIdIsNotFound(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE atak_nine_line (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
            mission_kind TEXT NOT NULL DEFAULT 'cas', mission_id TEXT, author TEXT NOT NULL DEFAULT 'x', author_user_id INTEGER, assigned_aircraft TEXT,
            line1 TEXT, line2 TEXT, line3 TEXT, line4 TEXT, line5 TEXT, line6 TEXT, line7 TEXT, line8 TEXT, line9 TEXT, remarks TEXT,
            lines_checked TEXT, geo_json TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)");
        $pdo->exec("CREATE TABLE atak_nine_line_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, nine_line_id INTEGER NOT NULL,
            callsign TEXT NOT NULL, label TEXT, crew_names TEXT, status TEXT NOT NULL DEFAULT 'sent', received_at TEXT, read_at TEXT, ack_at TEXT,
            ack_by TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT, UNIQUE (nine_line_id, callsign))");
        $pdo->prepare('INSERT INTO atak_nine_line (tenant_id, mission_id) VALUES (7, ?)')->execute([self::LEGACY]);
        $svc = new AirBriefService($pdo);

        self::assertSame('not_found', $svc->acknowledge(7, 'B765611998118681986.35867e%2B06', 'wilco', 'HOG 21')['result'], 'sans décodage : le 404 du signalement');
        $ref = AirBriefService::normalizeRef('B765611998118681986.35867e%2B06');
        self::assertNotNull($ref);
        self::assertSame('ok', $svc->acknowledge(7, $ref, 'wilco', 'HOG 21')['result']);
        self::assertSame('not_found', $svc->acknowledge(7, 'CAS-00000000-1', 'wilco', 'HOG 21')['result']);
    }

    public function testControllerAnswersExplicitJsonNotFound(): void
    {
        $src = self::read('app/Controllers/Api/AtakApiController.php');
        $start = (int) strpos($src, 'public function casStatus');
        $end = (int) strpos($src, 'Notes de reconnaissance', $start);
        $body = substr($src, $start, $end - $start);
        self::assertStringContainsString('AirBriefService::normalizeLookup(', $body);
        self::assertSame(3, substr_count($body, "'cas_not_found'"));
        self::assertStringNotContainsString("'Not found", $body);
    }

    public function testModBuildsSafeIdsWithoutScientificNotation(): void
    {
        foreach (['fn_airBrief.sqf', 'fn_firesQuick.sqf'] as $f) {
            $src = self::read(self::TACTICAL . $f);
            self::assertDoesNotMatchRegularExpression('/format \["[BQ]%1%2"/', $src, $f);
            self::assertStringContainsString('toFixed 0', $src, $f);
            self::assertDoesNotMatchRegularExpression('/systemChat|cutText|hintSilent|\bhint\s+[\["]/', $src, $f);
        }
        self::assertStringContainsString('format ["CAS-%1-%2", (getPlayerUID player) select [9], (round (diag_tickTime * 1000)) toFixed 0]', self::read(self::TACTICAL . 'fn_airBrief.sqf'));
        self::assertStringContainsString('format ["QF-%1-%2", (getPlayerUID player) select [9], (round (diag_tickTime * 1000)) toFixed 0]', self::read(self::TACTICAL . 'fn_firesQuick.sqf'));
    }

    public function testSqfBracesStayBalanced(): void
    {
        foreach (['fn_airBrief.sqf', 'fn_firesQuick.sqf'] as $f) {
            $src = (string) preg_replace('#//[^\n]*#', '', self::read(self::TACTICAL . $f));
            $src = (string) preg_replace('/"(?:[^"]|"")*"/', '""', $src);
            $src = (string) preg_replace("/'(?:[^']|'')*'/", "''", $src);
            foreach (['{' => '}', '[' => ']'] as $o => $c) {
                self::assertSame(substr_count($src, $o), substr_count($src, $c), $f . ' : ' . $o . $c);
            }
        }
    }

    public function testReportExplainsMalformedIdAndOldVersion(): void
    {
        $latest = ['atak' => '4.21.2', 'link' => '2.0.125', 'connect' => '1.6.40', 'sse' => null];
        $report = [
            'id' => 1, 'message' => 'ÉCHEC · HTTP POST — code 404', 'channel' => 'Net', 'source' => 'auto', 'player_name' => 'GHOST',
            'detail_text' => '404|/public/api/cas/B765611998118681986.35867e%2B06/status|0',
            'mod_version' => '1.6.34', 'extension_version' => '2.0.123',
        ];
        $hints = implode("\n", AtakModReportClientState::hints($report, $latest));
        self::assertStringContainsString('id mal formé (notation scientifique)', $hints);
        self::assertStringContainsString('version du mod ancienne : mettre à jour', $hints);
        $embed = AtakModReportDiscordTicketService::buildHeaderEmbed($report, 'new', ['reports' => 1, 'hits' => 1, 'players' => [], 'last_seen' => ''], '', $latest);
        self::assertStringContainsString('id mal formé (notation scientifique)', (string) $embed['description']);

        $ok = ['detail_text' => '404|/public/api/cas/CAS-11868198-6358670/status|0', 'mod_version' => '1.6.40', 'extension_version' => '2.0.125', 'atak_version' => '4.21.2'] + $report;
        self::assertSame([], AtakModReportClientState::hints($ok, $latest));
    }
}
