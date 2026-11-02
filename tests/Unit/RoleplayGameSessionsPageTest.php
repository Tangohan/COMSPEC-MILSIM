<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\CommunityEventRepository;
use App\Services\Personnel\RoleplayGameSessionService;
use App\Support\RoleplaySessionBoard as Board;
use PHPUnit\Framework\TestCase;

/**
 * Sessions Arma (back-office/roleplay/sessions) : classement à venir / en cours / passées, présence retenue par un
 * responsable qui tient sous les seuils, rendu de la liste et de la fiche.
 */
final class RoleplayGameSessionsPageTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private bool $sessionWasStarted = false;

    protected function setUp(): void
    {
        $started = new \ReflectionProperty(\App\Core\Session::class, 'started');
        $this->sessionWasStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION['_csrf_token'] ??= 'test';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(\App\Core\Session::class, 'started'))->setValue(null, $this->sessionWasStarted);
    }

    /** @param array<string, mixed> $vars */
    private function render(string $view, array $vars): string
    {
        return (static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            require $__file;

            return (string) ob_get_clean();
        })(self::ROOT . '/views/admin/organization/' . $view . '.php', $vars);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function sample(): array
    {
        $future = date('Y-m-d H:i:s', time() + 3 * 86400);
        $past = date('Y-m-d H:i:s', time() - 3600);

        return Board::group([
            ['id' => 1, 'mission_name' => 'Opération Dague', 'session_kind' => 'officielle', 'hour_category' => 'Opération', 'status' => 'open', 'planned_starts_at' => $future, 'started_at' => null, 'attendance_enabled' => 1, 'community_event_id' => 7],
            ['id' => 2, 'mission_name' => 'Tir <b>', 'session_kind' => 'entrainement', 'hour_category' => 'Instruction', 'status' => 'open', 'started_at' => $past],
            ['id' => 3, 'mission_name' => 'Vieille', 'session_kind' => 'libre', 'hour_category' => 'Libre', 'status' => 'closed', 'started_at' => '2026-01-01 20:00:00', 'ended_at' => '2026-01-01 23:00:00'],
            ['id' => 4, 'mission_name' => 'Annulée', 'session_kind' => 'officielle', 'hour_category' => 'Opération', 'status' => 'cancelled', 'planned_starts_at' => $future],
            ['id' => 5, 'mission_name' => 'En retard', 'session_kind' => 'officielle', 'hour_category' => 'Opération', 'status' => 'open', 'planned_starts_at' => $past, 'started_at' => null],
        ]);
    }

    public function testSessionsAreSortedIntoUpcomingLiveAndPast(): void
    {
        $g = $this->sample();
        self::assertSame([5, 1], array_map(static fn (array $s): int => $s['id'], $g['upcoming']), 'à venir : la plus proche d’abord');
        self::assertSame([2], array_map(static fn (array $s): int => $s['id'], $g['live']));
        self::assertEqualsCanonicalizing([3, 4], array_map(static fn (array $s): int => $s['id'], $g['past']));
        self::assertTrue(Board::isOverdue($g['upcoming'][0]));
        self::assertFalse(Board::isOverdue($g['upcoming'][1]));
        self::assertTrue(Board::isStale(['status' => 'open', 'started_at' => date('Y-m-d H:i:s', time() - 13 * 3600)]));
        self::assertFalse(Board::isStale($g['live'][0]));
        self::assertSame('2026-11-02 21:30:00', Board::parseInput('2026-11-02T21:30'));
        self::assertNull(Board::parseInput(''));
        self::assertSame('2026-11-02T21:30', Board::inputValue('2026-11-02 21:30:00'));
        self::assertSame('2 h 05', Board::duration(7500));
        self::assertSame('45 min', Board::duration(2700));
    }

    public function testStaffKeptAttendanceSurvivesThresholds(): void
    {
        // qualifyMember ne lit pas la base : service construit sans dépôts.
        $svc = (new \ReflectionClass(RoleplayGameSessionService::class))->newInstanceWithoutConstructor();
        $session = ['session_kind' => 'officielle', 'min_minutes' => 45, 'min_percent' => 50, 'started_at' => '2026-01-01 20:00:00', 'ended_at' => '2026-01-01 23:00:00'];
        $short = ['raw_seconds' => 600, 'joined_at' => '2026-01-01 20:00:00'];
        self::assertSame(0, $svc->qualifyMember($session, $short)['attendance_valid']);
        $kept = $svc->qualifyMember($session, $short + ['staff_validated_at' => '2026-01-02 00:00:00', 'attendance_valid' => 1]);
        self::assertSame(1, $kept['attendance_valid']);
        self::assertSame(600, $kept['validated_seconds']);
        $excluded = $svc->qualifyMember($session, ['raw_seconds' => 9000, 'rh_excluded' => 1, 'staff_validated_at' => '2026-01-02', 'attendance_valid' => 0]);
        self::assertSame(0, $excluded['attendance_valid']);
    }

    public function testUpcomingEventsMethodExistsAndControllerUsesIt(): void
    {
        self::assertTrue(method_exists(CommunityEventRepository::class, 'upcomingForTenant'));
        self::assertTrue(method_exists(CommunityEventRepository::class, 'findByIdForTenant'));
        $src = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/RoleplayGameSessionAdminController.php');
        self::assertStringNotContainsString('listUpcomingForTenant', $src);
        self::assertStringContainsString('->upcomingForTenant(', $src);
    }

    /** @return array<string, mixed> */
    private function indexVars(string $tab, array $groups): array
    {
        return [
            'sessionSchemaReady' => true,
            'sessionGroups' => $groups,
            'sessionStats' => [2 => ['total' => 4, 'checked_in' => 3, 'counted' => 3, 'validated_seconds' => 9000]],
            'sessionSettings' => ['hour_categories' => ['Opération', 'Instruction'], 'min_minutes' => 45, 'min_percent' => 50, 'late_tolerance_minutes' => 15, 'heartbeat_timeout_minutes' => 15, 'sync_enabled' => true],
            'sessionTab' => $tab,
            'sessionTabs' => ['a-venir' => 'upcoming', 'en-cours' => 'live', 'passees' => 'past'],
            'sessionSuggestedEvents' => [['id' => 9, 'title' => 'Opération Faucon', 'starts_at' => date('Y-m-d H:i:s', time() + 86400)]],
            'sessionEventsById' => [7 => ['id' => 7, 'title' => 'Soirée du jeudi', 'starts_at' => date('Y-m-d H:i:s', time() + 3 * 86400)]],
            'sessionEvents' => [],
        ];
    }

    public function testIndexShowsUpcomingSessionsWithStartAction(): void
    {
        $html = $this->render('roleplay_game_sessions', $this->indexVars('a-venir', $this->sample()));
        self::assertStringContainsString('Opération Dague', $html);
        self::assertStringContainsString('Prévue le', $html);
        self::assertStringContainsString('Événement : Soirée du jeudi', $html);
        self::assertStringContainsString('/back-office/roleplay/sessions/1/etat', $html);
        self::assertStringContainsString('value="start"', $html);
        self::assertStringContainsString('À démarrer', $html, 'heure prévue dépassée');
        self::assertStringContainsString('Opération Faucon', $html, 'événements du calendrier proposés');
        self::assertStringContainsString('Comment ça marche, en 3 étapes', $html);
        self::assertStringNotContainsString('Vieille', $html, 'les passées restent dans leur onglet');
        self::assertMatchesRegularExpression('~aria-current="page"[^>]*>\s*À venir~u', $html);
    }

    public function testIndexLiveTabCountsParticipantsAndEscapes(): void
    {
        $html = $this->render('roleplay_game_sessions', $this->indexVars('en-cours', $this->sample()));
        self::assertStringContainsString('Tir &lt;b&gt;', $html);
        self::assertStringContainsString('Comptés pour le suivi : <strong>3</strong>', $html);
        self::assertStringContainsString('value="close"', $html);
        self::assertStringNotContainsString('href="/back-office/roleplay/sessions/1"', $html, 'les sessions à venir restent dans leur onglet');
    }

    public function testEmptyStateExplainsHowToCreateASession(): void
    {
        $vars = $this->indexVars('a-venir', Board::group([]));
        $vars['sessionSuggestedEvents'] = [];
        $html = $this->render('roleplay_game_sessions', $vars);
        self::assertStringContainsString('Aucune session pour l’instant', $html);
        self::assertStringContainsString('/back-office/events', $html);
        self::assertMatchesRegularExpression('~<details class="rps-panel" id="nouvelle" open>~', $html);
    }

    public function testShowPageListsParticipantsInPlainWords(): void
    {
        $html = $this->render('roleplay_game_session_show', [
            'gameSession' => ['id' => 2, 'mission_name' => 'Tir', 'session_kind' => 'officielle', 'hour_category' => 'Opération', 'status' => 'open', 'started_at' => date('Y-m-d H:i:s', time() - 3600), 'min_minutes' => 45, 'min_percent' => 50, 'late_tolerance_minutes' => 15, 'attendance_enabled' => 1],
            'sessionMembers' => [
                ['id' => 11, 'user_id' => 5, 'display_name' => 'Viper', 'raw_seconds' => 3600, 'validated_seconds' => 3600, 'attendance_valid' => 1, 'joined_at' => '2026-01-01', 'checked_in_at' => '2026-01-01'],
                ['id' => 12, 'user_id' => null, 'steam_uid' => '7656119', 'raw_seconds' => 300, 'joined_at' => '2026-01-01'],
            ],
            'sessionSettings' => ['hour_categories' => ['Opération']],
            'sessionEvents' => [],
            'sessionLinkedEvent' => null,
            'sessionRoster' => [['id' => 8, 'label' => 'Bravo']],
            'sessionLengthSeconds' => 3600,
        ]);
        self::assertStringContainsString('Pointé et vu en jeu', $html);
        self::assertStringContainsString('Compte pour le suivi', $html);
        self::assertStringContainsString('Joueur non relié', $html);
        self::assertStringNotContainsString('7656119', $html, 'pas d’identifiant technique affiché');
        self::assertStringContainsString('Clôturer la session', $html);
        self::assertStringContainsString('<option value="8">Bravo</option>', $html);
        self::assertStringNotContainsString('numéro de dossier', $html);
    }
}
