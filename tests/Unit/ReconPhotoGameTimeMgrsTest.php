<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Media\ReconPhotoHudService;
use PHPUnit\Framework\TestCase;

/**
 * Photos envoyées : grille MGRS (fn_mgrsGrid) et bandeau à l'heure Zulu du jeu (COMSPEC Link 2.0.74).
 */
final class ReconPhotoGameTimeMgrsTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testStampUsesGameDateAndTimeWhenSent(): void
    {
        $hud = new ReconPhotoHudService();
        self::assertSame('10/06/2035  05:07:09 Z', $hud->formatStamp([
            'game_date' => '2035-06-10', 'game_time' => '05:07:09', 'captured_at' => 1_760_000_000,
        ]));
        self::assertSame('10/06/2035  05:07:00 Z', $hud->formatStamp(['game_date' => '2035-6-10', 'game_time' => '5:07']));
    }

    public function testStampFallsBackToRealTimeWithoutGameTime(): void
    {
        $hud = new ReconPhotoHudService();
        $real = gmdate('d/m/Y  H:i:s', 1_760_000_000) . ' Z';
        self::assertSame($real, $hud->formatStamp(['captured_at' => 1_760_000_000]));
        self::assertSame($real, $hud->formatStamp(['captured_at' => 1_760_000_000, 'game_date' => '2035-02-31', 'game_time' => '05:07']));
        self::assertSame($real, $hud->formatStamp(['captured_at' => 1_760_000_000, 'game_date' => '2035-06-10', 'game_time' => '25:00']));
    }

    public function testGameSendsMgrsGridAndGameClock(): void
    {
        $capture = $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf');
        self::assertStringNotContainsString('mapGridPosition', $capture);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_mgrsGrid', $capture);
        self::assertStringContainsString('(count _notifyArgs) < 24', $capture);
        self::assertStringContainsString('worldName', $capture);

        $mgrs = $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_mgrsGrid.sqf');
        self::assertStringContainsString('"CDEFGHJKLMNPQRSTUVWX"', $mgrs);
        // Variables SQF insensibles à la casse : pas de _A à côté de _a.
        self::assertDoesNotMatchRegularExpression('/\b_A\b/', $mgrs);
        self::assertStringContainsString('class mgrsGrid {};', $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'));

        $cs = $this->read('mod/UptoDate/COMSPECExtension/Extension.cs');
        foreach (['"game_date", args, 24', '"game_time", args, 25', '"world_name", args, 26'] as $needle) {
            self::assertStringContainsString($needle, $cs);
        }
        self::assertStringContainsString("'game_date' => mb_substr", $this->read('app/Controllers/Api/AtakApiController.php'));
    }
}
