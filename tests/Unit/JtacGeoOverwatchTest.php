<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CasNineLineRepository;
use PHPUnit\Framework\TestCase;

/**
 * Géométrie de l'app JTAC (objet « geo » du 9-LINE) : nettoyée côté API, stockée, redessinée sur Overwatch.
 */
final class JtacGeoOverwatchTest extends TestCase
{
    public function testSanitizeKeepsKnownFieldsAndRoundsPositions(): void
    {
        $geo = CasNineLineRepository::sanitizeGeo([
            'source' => 'jtac',
            'target' => [4521.6, 7012.2, 33],
            'ip' => ['8000', '9000'],
            'ipName' => 'IP ATHENA',
            'fah' => '250-290',
            'fahWindow' => [250, 290],
            'risk' => 525,
            'dangerClose' => true,
            'jtac' => [4400, 6900],
            'friendly' => 'oops',
            'aircraft' => 'VIPER 2',
            'aircraftPos' => [12000, 15000],
            'laser' => true,
            'laserCode' => 1688,
            'type' => 2,
            'method' => 'BOT',
            'ordnance' => 'GBU-12',
            'evil' => '<script>',
        ]);

        self::assertNotNull($geo);
        self::assertSame([4522.0, 7012.0], $geo['target']);
        self::assertSame([8000.0, 9000.0], $geo['ip']);
        self::assertSame([250, 290], $geo['fahWindow']);
        self::assertNull($geo['friendly']);
        self::assertTrue($geo['dangerClose']);
        self::assertSame(1688, $geo['laserCode']);
        self::assertArrayNotHasKey('evil', $geo);
    }

    public function testSanitizeRejectsGeoWithoutTarget(): void
    {
        self::assertNull(CasNineLineRepository::sanitizeGeo(null));
        self::assertNull(CasNineLineRepository::sanitizeGeo(['ip' => [1, 2]]));
        self::assertNull(CasNineLineRepository::sanitizeGeo(['target' => ['a', 'b']]));
    }

    public function testGeoIsWiredFromModToOverwatchMap(): void
    {
        $root = dirname(__DIR__, 2);
        $mod = $root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions';
        self::assertStringContainsString('_f set ["geo"', (string) file_get_contents($mod . '/tactical/fn_jtacAction.sqf'));
        self::assertStringContainsString('"geo":%1', (string) file_get_contents($mod . '/pages/fn_firesAction.sqf'));
        self::assertStringContainsString('atak_nine_line_geo_migration.php', (string) file_get_contents($root . '/run-migrations.php'));
        self::assertStringContainsString('`geo_json`', (string) file_get_contents($root . '/migrations/schema.sql'));
        self::assertStringContainsString('function renderJtacGeoOnMap', (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js'));
        self::assertStringContainsString('data-ow-layer="jtac"', (string) file_get_contents($root . '/views/atak-overwatch-beta.php'));
    }

    public function testJtacMapAndSheetKeepTheirPositionAcrossRefreshes(): void
    {
        $dir = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/tactical';
        $draw = (string) file_get_contents($dir . '/fn_jtacDraw.sqf');
        $page = (string) file_get_contents($dir . '/fn_pageJtac.sqf');
        // La vue n'est enregistrée qu'une fois la carte calée (sinon le coin haut gauche du monde revenait).
        self::assertStringContainsString('comspec_jtacReady', $draw);
        self::assertStringContainsString('comspec_jtacAim', $page);
        // Le défilement de la fiche est relevé par fn_pageRender et retrouvé au rendu suivant.
        self::assertStringContainsString('COMSPEC_ATAK_ScrollGroups', $page);
    }

    public function testScreenTimeMembersFoldAndAppDurationsHiddenByDefault(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/atak/screen_time.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-atak-squads.css');
        self::assertStringContainsString('data-aksq-durations', $view);
        self::assertStringContainsString('aksq__member-detail', $view);
        self::assertStringContainsString('back-office-atak-screen-time.js', $view);
        self::assertStringContainsString('[data-aksq-screen]:not(.is-durations) .aksq__app-dur { display: none; }', $css);
        self::assertFileExists($root . '/public/assets/js/back-office-atak-screen-time.js');
    }
}
