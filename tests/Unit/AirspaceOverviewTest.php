<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\AirspaceOverviewService as Svc;
use App\Services\Personnel\PilotQualificationEngine as Eng;
use App\Support\PilotSyllabusCatalog as Cat;
use PHPUnit\Framework\TestCase;

/** Espace aérien : ligne de vol, file « À valider », cursus et pilotes tirés des heures relevées et du cursus installé. */
final class AirspaceOverviewTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @param array<string, list<array<string, mixed>>> $groups */
    private static function rule(int $id, string $tier, string $scope, string $label, array $groups): array
    {
        $t = Cat::tier($tier);

        return ['id' => $id, 'definition_id' => 100 + $id, 'track' => 'heli', 'tier' => $tier, 'scope' => $scope, 'scope_label' => $label,
            'min_scope_hours' => $t['min_scope_hours'], 'min_track_hours' => $t['min_track_hours'], 'award_mode' => $t['mode'], 'is_enabled' => 1,
            'definition_name' => $label, 'validators' => null,
            'groups' => $groups + [Eng::ELIGIBLE => [], Eng::PENDING => [], Eng::HELD => [], Eng::PROGRESS => [], Eng::BLOCKED => []]];
    }

    public function testFlightLineQueuePilotsAndTracksComeFromRealData(): void
    {
        $now = strtotime('2026-11-02 12:00:00');
        $rules = [
            self::rule(1, 'solo', 'kind:heli', 'Hélicoptère', [Eng::HELD => [['user_id' => 5], ['user_id' => 6]]]),
            self::rule(2, 'type', 'family:uh60', 'UH-60 Black Hawk', [Eng::HELD => [['user_id' => 5], ['user_id' => 6]]]),
            self::rule(3, 'rl1', 'family:uh60', 'UH-60 Black Hawk', [Eng::HELD => [['user_id' => 5]], Eng::PROGRESS => [['user_id' => 6, 'pct' => 40, 'scope_hours' => 4.0, 'target_scope' => 10.0, 'prereq_ok' => true]]]),
            self::rule(4, 'pc', 'family:uh60', 'UH-60 Black Hawk', [Eng::PENDING => [['user_id' => 5, 'award_id' => 77, 'scope_hours' => 21.0, 'target_scope' => 20.0, 'track_hours' => 31.0, 'target_track' => 30.0]]]),
        ];
        $totals = [
            5 => ['kind:heli' => ['seconds' => 31 * 3600, 'last_flown_at' => '2026-10-30 20:00:00'], 'family:uh60' => ['seconds' => 21 * 3600, 'last_flown_at' => '2026-10-30 20:00:00'], 'cond:night:heli' => ['seconds' => 1800, 'last_flown_at' => '']],
            6 => ['kind:heli' => ['seconds' => 4 * 3600, 'last_flown_at' => '2026-07-01 20:00:00'], 'family:uh60' => ['seconds' => 4 * 3600, 'last_flown_at' => '2026-07-01 20:00:00']],
        ];
        $out = Svc::compose([
            'board' => ['rules' => $rules, 'members' => [5 => ['display_name' => 'Marc', 'callsign' => 'Viper'], 6 => ['display_name' => 'Léa', 'callsign' => 'Hawk']]],
            'totals' => $totals,
            'declared' => [['id' => 9, 'user_id' => 6, 'vehicle_kind' => 'heli', 'seconds' => 3600, 'flown_on' => '2026-10-31']],
            'seconds_30' => 9000,
            'actor' => 6,
            'can_review' => ['heli' => true],
            'now' => $now,
        ]);

        // Ligne de vol : les deux qualifiés UH-60, le plus haut palier d'abord ; Hawk n'a pas volé depuis plus de 90 jours.
        self::assertCount(1, $out['line']);
        $col = $out['line'][0];
        self::assertSame('UH-60 Black Hawk', $col['label']);
        self::assertSame([5, 6], array_column($col['pilots'], 'user_id'));
        self::assertSame('OPS', $col['pilots'][0]['top']);
        self::assertSame('ok', $col['pilots'][0]['cur']['s']);
        self::assertSame('exp', $col['pilots'][1]['cur']['s']);
        self::assertSame(['pilots' => 2, 'current_ok' => 1, 'current_total' => 2, 'hours_30' => 2.5, 'queue' => 2], $out['hud']);

        // À valider : la proposition de Viper (Hawk peut valider), le vol de Hawk (le sien : il ne peut pas le contresigner).
        self::assertSame(['rule', 'flight'], array_column($out['queue'], 'kind'));
        self::assertTrue($out['queue'][0]['can']);
        self::assertFalse($out['queue'][1]['can']);
        self::assertTrue($out['queue'][1]['mine']);

        // Cursus : tronc commun puis l'appareil, nœuds dans l'ordre des paliers.
        self::assertSame(['common', 'family'], array_column(array_values($out['tracks']['heli']), 'kind'));
        self::assertSame(['QT', 'OPS', 'CDB'], array_column($out['tracks']['heli']['family:uh60']['nodes'], 'code'));

        // Pilotes : le plus expérimenté d'abord, prochaine étape et maintien.
        self::assertSame([5, 6], array_column($out['pilots'], 'user_id'));
        self::assertSame(0.5, $out['pilots'][0]['night']);
        $row = $out['pilots'][1]['rows'][0];
        self::assertSame('OPS', $row['next']['code']);
        self::assertSame(40, $row['next']['pct']);
        self::assertSame('pending', $out['pilots'][0]['rows'][0]['next']['state'], 'CDB proposé : en attente d\'un instructeur');
    }

    public function testEmptyCommunityGivesEmptyStatesNotErrors(): void
    {
        $out = Svc::compose(['board' => ['rules' => [], 'members' => []]]);
        self::assertSame([], $out['line']);
        self::assertSame([], $out['queue']);
        self::assertSame([], $out['pilots']);
        self::assertSame(0, $out['hud']['pilots']);
    }

    public function testPageRouteMenuAndContainerAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        self::assertStringContainsString("'/back-office/espace-aerien', [\\App\\Controllers\\Admin\\Organization\\AirspaceController::class, 'index'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        self::assertStringContainsString("'Espace aérien', 'href' => url('back-office/espace-aerien')", (string) file_get_contents(self::ROOT . '/views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString('AirspaceController::class => new', (string) file_get_contents(self::ROOT . '/app/Core/Container.php'));
        self::assertFileExists(self::ROOT . '/public/assets/css/espace-aerien.css');
        self::assertStringContainsString('_hero.php', (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/pilot_syllabus.php'));
    }
}
