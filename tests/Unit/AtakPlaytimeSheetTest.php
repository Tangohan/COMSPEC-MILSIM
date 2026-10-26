<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakScreenTimeRepository;
use App\Support\AtakRoleDetection;
use App\Support\PlaytimeSheet;
use PHPUnit\Framework\TestCase;

/**
 * Tableur des heures (/back-office/atak/heures), détection des rôles, source des remontées et synthèse de la fiche.
 */
final class AtakPlaytimeSheetTest extends TestCase
{
    public function testRoleKeysAreClassifiedLikeTheGame(): void
    {
        self::assertSame('crew', AtakRoleDetection::category('PIL'));
        self::assertSame('crew', AtakRoleDetection::category('CREW'));
        self::assertSame('specialty', AtakRoleDetection::category('MED'));
        self::assertSame('specialty', AtakRoleDetection::category('drn'));
        self::assertSame('slot', AtakRoleDetection::category('SLOT:FUSILIER'));
        self::assertSame('team', AtakRoleDetection::category('CDE'));
        self::assertSame('team', AtakRoleDetection::category('A12'));
        self::assertSame('manual', AtakRoleDetection::category('SLOT:TACP', true));
        self::assertSame('Poste d’équipage', AtakRoleDetection::label('crew'));
        self::assertSame('Nom du slot', AtakRoleDetection::label('slot'));
        self::assertSame('Rôle d’équipe (téléphone)', AtakRoleDetection::label('inconnu'));
    }

    public function testSourceMaskAndKindLabels(): void
    {
        self::assertSame('Non précisé', AtakRoleDetection::sourceLabel(0));
        self::assertSame('Téléphone ATAK', AtakRoleDetection::sourceLabel(AtakScreenTimeRepository::SOURCE_PHONE));
        self::assertSame('Serveur Arma', AtakRoleDetection::sourceLabel(AtakScreenTimeRepository::SOURCE_SERVER));
        self::assertSame('Serveur + téléphone', AtakRoleDetection::sourceLabel(3));
        self::assertSame('Temps de jeu', AtakRoleDetection::kindLabel('play', 'TOTAL'));
        self::assertSame('Écran en main', AtakRoleDetection::kindLabel('screen', 'HAND'));
        self::assertSame('Écran porté', AtakRoleDetection::kindLabel('screen', 'CARRY'));
        self::assertSame('Écran (total)', AtakRoleDetection::kindLabel('screen', 'TOTAL'));
        self::assertSame('Rôle', AtakRoleDetection::kindLabel('role', 'PIL'));
    }

    public function testSheetFormats(): void
    {
        self::assertSame('5:43', PlaytimeSheet::clock(20580));
        self::assertSame('0:00', PlaytimeSheet::clock(-5));
        self::assertSame('5,72', PlaytimeSheet::decimal(20580));
        self::assertSame(25.0, PlaytimeSheet::share(900, 3600));
        self::assertSame(100.0, PlaytimeSheet::share(5000, 3600), 'plafonné à 100 %');
        self::assertNull(PlaytimeSheet::share(10, 0));
        self::assertSame('12,5', PlaytimeSheet::frenchNumber(12.5));
        self::assertSame('100', PlaytimeSheet::frenchNumber(100.0));
        self::assertSame('', PlaytimeSheet::frenchNumber(null));
        self::assertSame("'=SOMME(A1)", PlaytimeSheet::csvCell('=SOMME(A1)'));
        self::assertSame('14/10/2026', PlaytimeSheet::frenchDate('2026-10-14'));
        self::assertSame('', PlaytimeSheet::frenchDate('nope'));
    }

    public function testPivotGivesOneRowPerPlayerWithUnsplitTime(): void
    {
        $base = ['display_name' => 'Dupont', 'callsign' => 'Bravo 6', 'source_mask' => 2, 'detection' => '', 'first_day' => '2026-10-01'];
        $rows = [
            $base + ['user_id' => 7, 'kind' => 'play', 'key' => 'TOTAL', 'label' => 'Temps de jeu', 'seconds' => 7200, 'sessions' => 3, 'days' => 3, 'last_day' => '2026-10-10'],
            $base + ['user_id' => 7, 'kind' => 'role', 'key' => 'SLOT:TACP', 'label' => 'TACP', 'seconds' => 5400, 'sessions' => 2, 'days' => 2, 'last_day' => '2026-10-12'],
            $base + ['user_id' => 7, 'kind' => 'role', 'key' => 'DRV', 'label' => 'Conducteur', 'seconds' => 60, 'sessions' => 1, 'days' => 1, 'last_day' => '2026-10-02'],
            $base + ['user_id' => 7, 'kind' => 'screen', 'key' => 'TOTAL', 'label' => 'Écran', 'seconds' => 999, 'sessions' => 1, 'days' => 1, 'last_day' => '2026-10-02'],
        ];
        $players = PlaytimeSheet::pivot($rows, [7 => ['total_seconds' => 108600, 'server_seconds' => 84300, 'zeus_seconds' => 0, 'editor_seconds' => 3840, 'last_report_at' => null]]);

        self::assertCount(1, $players);
        self::assertSame(7200, $players[0]['play']);
        self::assertSame('TACP', $players[0]['top_role']);
        self::assertSame(75.0, $players[0]['top_role_share']);
        self::assertSame(2, $players[0]['roles']);
        self::assertSame(3, $players[0]['sessions']);
        self::assertSame('2026-10-12', $players[0]['last_day']);
        self::assertSame(20460, $players[0]['unsplit'], '30 h 10 − 23 h 25 − 1 h 04');
        self::assertSame(0, PlaytimeSheet::unsplit(['total_seconds' => 10, 'server_seconds' => 50]));
    }

    public function testPlayStatsCountWindowsSessionsAndWeeks(): void
    {
        // Mercredi 14/10/2026 : la semaine en cours commence le lundi 12/10.
        $stats = AtakScreenTimeRepository::summarizePlay([
            ['day' => '2026-10-14', 'mission_key' => 'Op@Altis#1', 'seconds' => 3600],
            ['day' => '2026-10-12', 'mission_key' => 'Op@Altis#1', 'seconds' => 600],
            ['day' => '2026-10-05', 'mission_key' => '', 'seconds' => 1800],
            ['day' => '2026-09-01', 'mission_key' => 'Old@Tanoa#2', 'seconds' => 7200],
            ['day' => '2026-10-01', 'mission_key' => '', 'seconds' => 0],
        ], '2026-10-14');

        self::assertSame(4200, $stats['play_7d']);
        self::assertSame(6000, $stats['play_30d']);
        self::assertSame(2, $stats['sessions_30d'], 'une clé de mission, plus un jour sans clé');
        self::assertSame(3, $stats['sessions_total']);
        self::assertSame(13200, $stats['total_seconds']);
        self::assertSame('2026-10-14', $stats['last_day']);
        self::assertCount(8, $stats['weeks']);
        self::assertSame('2026-10-12', $stats['weeks'][7]['start']);
        self::assertSame(4200, $stats['weeks'][7]['seconds']);
        self::assertSame(1800, $stats['weeks'][6]['seconds']);
        self::assertSame('2026-08-24', $stats['weeks'][0]['start']);
        self::assertSame(7200, $stats['weeks'][1]['seconds'], 'semaine du 31/08');
    }

    public function testSourceIsRecordedAndSheetIsWired(): void
    {
        $root = dirname(__DIR__, 2);
        $read = static fn (string $p): string => (string) file_get_contents($root . '/' . $p);
        $mig = $read('bootstrap/atak_mission_key_migration.php');
        $repo = $read('app/Repositories/AtakScreenTimeRepository.php');
        $api = $read('app/Controllers/Api/AtakApiController.php');
        $routes = $read('routes/web.php');
        $nav = $read('config/navigation.php');
        $pages = $read('config/back_office_pages.php');
        $fiche = $read('views/personnel/file.php') . $read('views/partials/personnel/file_playtime.php');

        self::assertStringContainsString('source_mask TINYINT UNSIGNED NOT NULL DEFAULT 0', $mig);
        self::assertStringContainsString('source_mask = source_mask | VALUES(source_mask)', $repo);
        self::assertStringContainsString('AtakScreenTimeRepository::SOURCE_PHONE)', $api);
        self::assertStringContainsString('AtakScreenTimeRepository::SOURCE_SERVER)', $api);
        self::assertStringContainsString("'/back-office/atak/heures', [\\App\\Controllers\\Admin\\AdminAtakSquadsController::class, 'hours']", $routes);
        self::assertSame(2, substr_count($nav, "'path' => 'back-office/atak/heures'"));
        self::assertStringContainsString("'path' => 'back-office/atak/heures'", $pages);
        self::assertFileExists($root . '/public/assets/js/back-office-atak-hours.js');
        self::assertFileExists($root . '/public/assets/css/back-office-atak-hours.css');
        // Bloc « Temps de jeu » (partial depuis #1066) : cumul serveur / Zeus / éditeur puis rôles à côté.
        self::assertStringContainsString("require base_path('views/partials/personnel/file_playtime.php')", $fiche);
        self::assertStringContainsString('lg:col-span-3', $fiche);
        self::assertStringContainsString('Avant le détail', $fiche);
        self::assertStringNotContainsString('Relevé par le téléphone ATAK en partie', $fiche);
    }
}
