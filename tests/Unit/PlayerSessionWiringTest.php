<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlayerSessionRepository;
use App\Services\Personnel\MemberAvailabilityRate;
use App\Services\Personnel\PlayerSessionService as S;
use App\Support\MemberFicheTabs;
use App\Support\PlayerMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Câblage des sessions de jeu : onglet et colonnes, libellés explicites, réglages, rétrocompatibilité sans données.
 */
final class PlayerSessionWiringTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testFicheHasAnAvailabilityTabWithExplicitLabelsAndNoEyebrow(): void
    {
        self::assertContains('disponibilite', MemberFicheTabs::slugs());
        self::assertSame('disponibilite', MemberFicheTabs::tabForAnchor('#disponibilite'));
        $p = self::read('views/admin/effectifs_workspace/partials/member_availability.php');
        self::assertStringContainsString('Sessions de %1', $p);
        self::assertStringContainsString('<details class="eff-fm-av__drawer"', $p);
        self::assertStringContainsString('Régler la durée qui compte', $p);
        self::assertDoesNotMatchRegularExpression('/(eyebrow|kicker|simulation)/i', $p);
        self::assertStringContainsString("\$panelOpen('disponibilite')", self::read('views/admin/effectifs_workspace/member.php'));
        self::assertStringContainsString('panelForUser', self::read('app/Controllers/Admin/EffectifsWorkspaceController.php'));
    }

    public function testRosterAndMetricsAndInactivityUseTheThreshold(): void
    {
        $roster = self::read('views/admin/effectifs_workspace/roster.php');
        self::assertStringContainsString('server_sessions', $roster);
        self::assertStringContainsString("'sessions' => !empty(\$filters['sessions'])", $roster);
        self::assertStringContainsString('name="sessions"', self::read('views/admin/effectifs_workspace/partials/roster_toolbar.php'));
        self::assertStringContainsString('withServerSessions', self::read('app/Controllers/Admin/AdminPlayerMetricsController.php'));
        self::assertStringContainsString('qualifyingExistsClause', self::read('app/Services/Effectifs/RhAlertAggregatorService.php'));
        self::assertStringContainsString('recordHeartbeat', self::read('app/Controllers/Api/AtakApiController.php'));
        self::assertStringContainsString('rp_player_min_minutes', self::read('views/admin/organization/roleplay_game_sessions.php'));
        self::assertStringContainsString('player_sessions', self::read('app/Services/Cron/Jobs/PlayerDataRetentionCronJob.php'));
        self::assertStringContainsString('player_sessions', self::read('app/Services/Admin/PlayerTracePurgeService.php'));
    }

    public function testEnglishDictionaryTranslatesTheThresholdPhrases(): void
    {
        $GLOBALS['__app_site_lang'] = 'en';
        $out = \App\Support\HtmlTranslator::translate('<p>Sessions de plus d’1 h</p><p>Actifs : sessions plus de 30 min (30 j)</p>');
        unset($GLOBALS['__app_site_lang']);
        self::assertStringContainsString('over 1 h', $out);
        self::assertStringContainsString('over 30 min', $out);
    }

    public function testAvailabilityRateIsUntouchedWithoutSessionData(): void
    {
        $rate = MemberAvailabilityRate::fromCounts(4, 3, 2);
        self::assertSame($rate, MemberAvailabilityRate::withSessions($rate, null));
        $with = MemberAvailabilityRate::withSessions($rate, S::summarize([], 3600, 1_800_000_000, 'Europe/Paris', true));
        self::assertSame($rate['availability_pct'], $with['availability_pct'], 'le pourcentage ne dépend pas des sessions');
        self::assertSame(0, $with['server']['sessions']);
        self::assertFalse($with['server']['active']);
    }

    public function testMetricsRowsKeepNullForUnlinkedMembers(): void
    {
        $rows = PlayerMetrics::withServerSessions([['user_id' => 1], ['user_id' => 2]], [1 => ['sessions' => 4, 'seconds' => 20000]]);
        self::assertSame(4, $rows[0]['srv_sessions']);
        self::assertNull($rows[1]['srv_sessions']);
        self::assertSame(4, PlayerMetrics::totals(array_map(static fn (array $r): array => $r + ['garmin' => null], $rows))['srv_sessions']);
    }

    public function testSettingsAreBoundedAndDefaultToOneHour(): void
    {
        $d = \App\Services\Personnel\RoleplayGameSessionSettings::defaults();
        self::assertSame(60, $d['player_min_minutes']);
        self::assertSame(25, $d['player_gap_minutes']);
        self::assertTrue(class_exists(PlayerSessionRepository::class));
    }
}
