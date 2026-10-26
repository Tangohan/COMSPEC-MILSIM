<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Support\YearReviewCalendar;
use App\Support\YearReviewStats;
use App\Support\YearReviewStory;
use PHPUnit\Framework\TestCase;

/** Rétrospective annuelle des joueurs : calendrier de publication, chiffres, écrans et câblage. */
final class YearReviewTest extends TestCase
{
    private function ts(string $date): int
    {
        return (int) strtotime($date);
    }

    public function testDecemberPublicationCoversTheCurrentYear(): void
    {
        $c = new YearReviewCalendar();
        self::assertSame('12-01', $c->publishMd());
        self::assertNull($c->currentYear($this->ts('2026-11-30 23:59:59')));
        self::assertSame(2026, $c->currentYear($this->ts('2026-12-01 00:00:00')));
        self::assertSame(2026, $c->currentYear($this->ts('2027-06-15')));
        self::assertSame(2027, $c->currentYear($this->ts('2027-12-02')));
        self::assertSame([2027, 2026], $c->publishedYears($this->ts('2027-12-02')));
    }

    public function testJanuaryPublicationCoversThePreviousYear(): void
    {
        $c = new YearReviewCalendar(true, '01-01', 30);
        self::assertFalse($c->publishesInYear());
        self::assertNull($c->currentYear($this->ts('2026-12-31')));
        self::assertSame(2026, $c->currentYear($this->ts('2027-01-01 08:00')));
        self::assertTrue($c->inPopupWindow(2026, $this->ts('2027-01-20')));
        self::assertFalse($c->inPopupWindow(2026, $this->ts('2027-02-05')));
    }

    public function testDisabledCalendarPublishesNothing(): void
    {
        $c = YearReviewCalendar::fromSettings([YearReviewCalendar::SETTING_ENABLED => '0']);
        self::assertNull($c->currentYear($this->ts('2027-03-01')));
        self::assertFalse($c->isPublished(2026, $this->ts('2027-03-01')));
        self::assertSame([], $c->publishedYears($this->ts('2027-03-01')));
    }

    public function testPublishDateIsValidated(): void
    {
        self::assertSame('01-05', YearReviewCalendar::normalizeMd('1-5'));
        self::assertNull(YearReviewCalendar::normalizeMd('02-29'));
        self::assertNull(YearReviewCalendar::normalizeMd('13-01'));
        self::assertSame('12-01', (new YearReviewCalendar(true, 'n’importe quoi'))->publishMd());
    }

    public function testPeriodStopsTodayForTheYearInProgress(): void
    {
        $p = YearReviewCalendar::period(2026, $this->ts('2026-12-02 10:00:00'));
        self::assertSame('2026-01-01 00:00:00', $p['from']);
        self::assertSame('2026-12-02 10:00:00', $p['until']);
        self::assertTrue($p['partial']);
        $full = YearReviewCalendar::period(2026, $this->ts('2027-01-03'));
        self::assertSame('2027-01-01 00:00:00', $full['until']);
        self::assertFalse($full['partial']);
    }

    /** @return array<string, array<string, mixed>> */
    private function sample(): array
    {
        return [
            'u1' => ['play_s' => 360000, 'screen_s' => 90000, 'months' => [5 => 200000, 10 => 160000], 'apps' => ['Carte' => 50000, 'Messagerie' => 20000],
                'days' => 40, 'missions' => 32, 'foot_m' => 182000, 'land_m' => 400000, 'wia' => 4, 'kia' => 2, 'sse_notes' => 12, 'sse_photos' => 30, 'cr' => 14, 'debriefs' => 9],
            'u2' => ['play_s' => 120000, 'months' => [3 => 120000], 'pilot_s' => 40000, 'aircraft' => ['UH-60 Black Hawk' => 40000], 'days' => 12, 'missions' => 10, 'cr' => 2],
            'u3' => ['play_s' => 60000, 'months' => [2 => 60000], 'foot_m' => 20000, 'days' => 5, 'missions' => 4, 'sse_samples' => 6],
            'u4' => ['play_s' => 300],
            'c-ghost' => ['kia' => 3],
        ];
    }

    public function testPlayersGetDerivedTotalsPersonaAndRanks(): void
    {
        $b = YearReviewStats::build($this->sample(), ['u1' => 'Viper 1-1', 'u2' => 'Hawk'], 40);
        $u1 = $b['players']['u1'];
        self::assertSame(582000, $u1['total_m']);
        self::assertSame(42, $u1['sse_total']);
        self::assertSame(23, $u1['reports']);
        self::assertSame('Carte', $u1['top_app']['label']);
        self::assertSame('juin', $u1['busiest_month']['label']);
        self::assertSame(1, $u1['ranks']['play_s']['rank']);
        self::assertSame('Viper 1-1', $u1['name']);
        self::assertSame('pilote', $b['players']['u2']['persona']['key']);
        self::assertSame('UH-60 Black Hawk', $b['players']['u2']['top_aircraft']['label']);
        // Dix minutes de jeu ne font pas une rétrospective.
        self::assertFalse($b['players']['u4']['active']);
        self::assertSame('Membre #4', $b['players']['u4']['name']);
    }

    public function testCommunityCountsEveryoneButRanksOnlyAccounts(): void
    {
        $c = YearReviewStats::build($this->sample(), [], 40)['community'];
        self::assertSame(3, $c['members_active']);
        self::assertSame(40, $c['missions'], 'les sessions se comptent une fois pour la communauté');
        self::assertSame(5, $c['kia'], 'un indicatif sans compte compte dans le bilan');
        self::assertSame(['u1', 'u2', 'u3'], array_column($c['boards']['play_s']['top'], 'key'));
        self::assertArrayNotHasKey('days', $c);
    }

    public function testRanksStayFlatteringAndNeedEnoughPlayers(): void
    {
        $accounts = [];
        for ($i = 1; $i <= 10; $i++) {
            $accounts['u' . $i] = ['play_s' => $i * 1000];
        }
        self::assertSame(1, YearReviewStats::ranks(['play_s' => 10000], $accounts)['play_s']['rank']);
        self::assertSame(10, YearReviewStats::ranks(['play_s' => 9000], $accounts)['play_s']['top_pct'] - 10);
        self::assertSame([], YearReviewStats::ranks(['play_s' => 2000], $accounts), 'pas de « top 90 % »');
        self::assertSame([], YearReviewStats::ranks(['play_s' => 5], array_slice($accounts, 0, 2)), 'moins de 3 joueurs classés');
    }

    public function testStoryShowsOnlyScreensWithFigures(): void
    {
        $b = YearReviewStats::build($this->sample(), [], 40);
        $keys = static fn (array $slides): array => array_column($slides, 'key');

        $full = YearReviewStory::slides(2026, $b['players']['u1'], $b['community'], ['collective' => true]);
        self::assertSame(['intro', 'play', 'distance', 'medical', 'intel', 'atak', 'persona', 'community', 'boards', 'summary'], $keys($full));

        // Pas de distances remontées (heures de vol en correction) : l'écran disparaît, sans zéro affiché.
        $pilot = YearReviewStory::slides(2026, $b['players']['u2'], $b['community'], ['collective' => false]);
        self::assertNotContains('distance', $keys($pilot));
        self::assertContains('flight', $keys($pilot));
        self::assertNotContains('community', $keys($pilot));

        $upsell = YearReviewStory::slides(2026, $b['players']['u3'], $b['community'], ['collective' => false, 'upsell' => true]);
        self::assertContains('locked', $keys($upsell));

        $nothing = YearReviewStory::slides(2026, null, null, []);
        self::assertSame(['intro'], $keys($nothing));
    }

    public function testCollectiveReviewIsAPaidPlanModule(): void
    {
        self::assertContains('year_review_collective', SubscriptionPlanFeaturesCatalog::BOOL_FEATURES);
        self::assertFalse(SubscriptionPlanFeaturesCatalog::defaultsForSlug('free')['year_review_collective']);
        foreach (['standard', 'pro', 'pro_plus'] as $slug) {
            self::assertTrue(SubscriptionPlanFeaturesCatalog::defaultsForSlug($slug)['year_review_collective'], $slug);
        }
    }

    public function testRoutesMenuAndLoginWindowAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/retrospective/{year}', [\\App\\Controllers\\Web\\YearReviewController::class, 'show'], [AuthMiddleware::class]", $routes);
        self::assertStringContainsString("'/admin/system/retrospective', [\\App\\Controllers\\Admin\\System\\SystemYearReviewController::class, 'index'], [AuthMiddleware::class, SystemAdminMiddleware::class]", $routes);
        self::assertStringContainsString("'admin/system/retrospective'", (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php'));
        self::assertStringContainsString('year_review_modal.php', (string) file_get_contents($root . '/views/layout/main.php'));
        $dict = (string) file_get_contents($root . '/lang/en/site_auto.php');
        self::assertStringContainsString("'Rétrospective %1' => '%1 year in review'", $dict);
    }
}
