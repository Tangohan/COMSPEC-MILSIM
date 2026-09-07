<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ArmaPlaytimeRepository;
use PHPUnit\Framework\TestCase;

final class ArmaPlaytimeContextAssetTest extends TestCase
{
    public function testNormalizeContextAcceptsOnlyKnownBuckets(): void
    {
        self::assertSame('server', ArmaPlaytimeRepository::normalizeContext('server'));
        self::assertSame('zeus', ArmaPlaytimeRepository::normalizeContext('ZEUS'));
        self::assertSame('editor', ArmaPlaytimeRepository::normalizeContext(' editor '));
        self::assertSame('server', ArmaPlaytimeRepository::normalizeContext('unknown'));
        self::assertSame('server', ArmaPlaytimeRepository::normalizeContext(''));
    }

    public function testBreakdownHelperFormatsHumanLabels(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';

        self::assertSame('', format_arma_playtime_breakdown_french([]));
        self::assertSame('', format_arma_playtime_breakdown_french([
            'total_seconds' => 3600,
            'server_seconds' => 0,
            'zeus_seconds' => 0,
            'editor_seconds' => 0,
        ]));
        self::assertSame(
            'en serveur 2 h · en Zeus 45 min · dans l’éditeur 10 min',
            format_arma_playtime_breakdown_french([
                'server_seconds' => 7200,
                'zeus_seconds' => 2700,
                'editor_seconds' => 600,
            ])
        );
    }

    public function testAtakTrackerClassifiesServerZeusAndEditor(): void
    {
        $root = dirname(__DIR__, 2);
        $tracker = (string) file_get_contents(
            $root . '/mod/Overwatch 2026/ProdVersion/GPT/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_playtimeTracker.sqf'
        );
        $ow = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_playtimeTracker.sqf'
        );
        $ext = (string) file_get_contents(
            $root . '/mod/UptoDate/COMSPECExtension/Extension.cs'
        );
        $mig = (string) file_get_contents($root . '/bootstrap/arma_playtime_migration.php');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $fiche = (string) file_get_contents($root . '/views/personnel/file.php');

        foreach ([$tracker, $ow] as $sqf) {
            self::assertStringContainsString('is3DEN', $sqf);
            self::assertStringContainsString('is3DENPreview', $sqf);
            self::assertStringContainsString('curatorCamera', $sqf);
            self::assertStringContainsString('findDisplay 312', $sqf);
            self::assertStringContainsString('"editor"', $sqf);
            self::assertStringContainsString('"zeus"', $sqf);
            self::assertStringContainsString('"server"', $sqf);
            self::assertStringContainsString('_flushCtx', $sqf);
        }
        self::assertStringContainsString('"context"', $ext);
        self::assertStringContainsString('ctx != "zeus" && ctx != "editor"', $ext);
        self::assertStringContainsString('server_seconds', $mig);
        self::assertStringContainsString('zeus_seconds', $mig);
        self::assertStringContainsString('editor_seconds', $mig);
        self::assertStringContainsString('recorded_context', $api);
        self::assertStringContainsString('En serveur', $fiche);
        self::assertStringContainsString('En Zeus', $fiche);
        self::assertStringContainsString('Dans l’éditeur', $fiche);
    }
}
