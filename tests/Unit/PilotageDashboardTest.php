<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Services\Admin\PilotageDashboardService;
use App\Services\Personnel\UnitReadinessService;
use App\Support\PilotageDashboardPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Tableau de bord de pilotage (back-office communauté) : mise en forme, rendu réel de la vue
 * (données vides et remplies) et respect des droits sur les liens et les blocs.
 */
final class PilotageDashboardTest extends TestCase
{
    private const NOW = '2026-11-01 15:20:00';

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 0, 'user_id' => 0];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization', 'admin.access']);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        Gate::reset();
    }

    /** @return array<string, mixed> */
    private function emptyInput(?callable $allowed = null): array
    {
        return [
            'now' => (int) strtotime(self::NOW),
            'tenant_name' => 'SOAR',
            'viewer_name' => 'Jake',
            'link_allowed' => $allowed,
        ];
    }

    /** @return array<string, mixed> */
    private function filledInput(?callable $allowed = null): array
    {
        $now = (int) strtotime(self::NOW);
        $participation = [];
        foreach ([['Basalte', 36, 32], ['Sirocco', 34, 30], ['Granit', 31, 29]] as $i => [$name, $reg, $present]) {
            $participation[] = [
                'id' => 100 + $i,
                'title' => 'Op. ' . $name,
                'starts_at' => date('Y-m-d 20:30:00', $now - ($i + 1) * 3 * 86400),
                'event_type' => 'operation',
                'registered' => $reg,
                'present' => $present,
            ];
        }

        return [
            'now' => $now,
            'tenant_name' => 'SOAR',
            'viewer_name' => 'Jake <b>G</b>',
            'link_allowed' => $allowed,
            'kpis' => [
                ['id' => 'members_active', 'value' => '36'],
                ['id' => 'members_inactive', 'value' => '4'],
                ['id' => 'active_30d', 'value' => '31'],
                ['id' => 'training_expiring', 'value' => '2'],
                ['id' => 'invites_expired', 'value' => '0'],
                ['id' => 'moderation_open', 'value' => null, 'error' => 'indisponible'],
            ],
            'profile_gaps_total' => 5,
            'upcoming' => [
                ['id' => 11, 'title' => 'Formation fast-rope', 'starts_at' => '2026-11-02 15:00:00', 'location' => 'Stand', 'event_type' => 'formation'],
                ['id' => 12, 'title' => 'Op. Aube Rouge', 'starts_at' => '2026-11-03 20:30:00', 'ends_at' => '2026-11-03 23:30:00', 'location' => 'Altis', 'campaign_tag' => 'Campagne Altis', 'event_type' => 'operation'],
                ['id' => 13, 'title' => 'Réunion lointaine', 'starts_at' => '2026-11-20 21:00:00', 'event_type' => 'autre'],
            ],
            'rsvp' => [12 => ['yes' => array_fill(0, 28, []), 'maybe' => array_fill(0, 3, []), 'no' => array_fill(0, 2, [])]],
            'elevations' => [['target_display_name' => 'James Alexander', 'kind' => 'role', 'status' => 'pending']],
            'elevation_count' => 1,
            'elevation_kind_labels' => ['role' => 'droits Gestionnaire'],
            'enlistment_counts' => ['submitted' => 1, 'interview_scheduled' => 1, 'reviewed' => 4, 'accepted' => 1],
            'enlistments' => [
                ['id' => 5, 'first_name' => 'Jules', 'last_name' => 'Petit', 'status' => 'submitted', 'created_at' => '2026-10-30 10:00:00'],
                ['id' => 6, 'first_name' => 'Ana', 'last_name' => 'Refus', 'status' => 'rejected', 'created_at' => '2026-10-29 10:00:00'],
                ['id' => 7, 'callsign' => 'Lynx', 'status' => 'interview_scheduled', 'created_at' => '2026-10-20 10:00:00'],
            ],
            'participation' => $participation,
            'presence' => ['current_yes' => 100, 'current_present' => 84, 'current_checked' => 86, 'previous_yes' => 50, 'previous_present' => 39],
            'month_events' => ['total' => 9, 'done' => 6, 'month' => 11],
            'units' => [
                ['name' => 'Alpha Assault', 'authorized' => 8, 'filled' => 8, 'score' => 88],
                ['name' => 'Vide', 'authorized' => 0, 'filled' => 0, 'score' => 50],
                ['name' => 'Aviation', 'authorized' => 4, 'filled' => 3, 'score' => 61],
            ],
            'activity' => [
                ['action' => 'auth.login', 'actor_name' => 'Connexion', 'created_at' => '2026-11-01 15:00:00'],
                ['action' => 'qualification.awarded', 'actor_name' => 'Andrew Anderson', 'action_label' => 'A validé une qualification', 'target' => 'Transmissions', 'created_at' => '2026-11-01 13:10:00'],
            ],
        ];
    }

    /** @param array<string, mixed> $pilotage @param array<string, mixed> $extra */
    private function render(array $pilotage, array $extra = []): string
    {
        $vars = array_merge([
            'pilotage' => $pilotage,
            'initialSetupBanner' => null,
            'configurationUpdateBadge' => 0,
            'discordInviteMissing' => false,
            'missingMediaCount' => 0,
            'orgActivityChart' => ['days' => [], 'portal' => [], 'atak' => [], 'max' => 1, 'period_days' => 14],
            'orgMessagesRecent' => [],
            'orgProfileGaps' => ['rows' => [], 'total' => 0, 'shown' => 0, 'truncated' => false, 'counts' => [], 'error' => null],
        ], $extra);
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render(base_path('views/admin/organization/dashboard.php'), $vars);
    }

    public function testEmptyDataGivesHonestEmptyBlocks(): void
    {
        $p = PilotageDashboardPresenter::build($this->emptyInput());

        self::assertNull($p['hero']);
        self::assertSame(0, $p['decisions']['total']);
        self::assertSame([], $p['decisions']['items']);
        self::assertCount(4, $p['kpis']);
        self::assertSame('—', $p['kpis'][1]['value']);
        self::assertNull($p['kpis'][1]['spark']);
        self::assertSame([], $p['participation']['bars']);
        self::assertSame([], $p['units']);
        self::assertSame([], $p['feed']);
        self::assertSame('À venir', $p['agenda']['title']);
        self::assertSame('dimanche 1 novembre', $p['hello']['date_label']);
        self::assertSame('Bonjour', $p['hello']['greeting']);

        $html = $this->render($p);
        foreach (['Bonjour, Jake.', 'À planifier', 'Programmer une activité', 'Tout est à jour', 'Aucune activité terminée pour l’instant', 'Aucune unité avec des postes', 'Rien n’est prévu pour le moment.', 'Aucune action récente', 'Aucune conversation récente', 'id="dossiers-incomplets"', 'org-profile-gaps-heading'] as $needle) {
            self::assertStringContainsString($needle, $html);
        }
        self::assertStringNotContainsString('role="timer"', $html);
        self::assertStringNotContainsString('pil-spark', $html);
    }

    public function testFilledDataBuildsEveryBlock(): void
    {
        $p = PilotageDashboardPresenter::build($this->filledInput());

        $hero = $p['hero'];
        self::assertSame(12, $hero['id'], 'Une opération passe avant une formation plus proche.');
        self::assertSame('Prochaine opération', $hero['kicker']);
        self::assertSame('Mardi 3 nov.', $hero['date_label']);
        self::assertSame('20:30 – 23:30', $hero['time_label']);
        self::assertSame(['days' => 2, 'hours' => 5, 'minutes' => 10], $hero['countdown']);
        self::assertSame(28, $hero['rsvp']['yes']);
        self::assertSame(3, $hero['rsvp']['no_reply'], '36 actifs − 33 réponses.');
        self::assertSame('2 jours', $p['hello']['next_in']);
        self::assertSame('La prochaine opération', $p['hello']['next_noun']);
        self::assertSame(40, $p['hello']['total']);

        // 1 élévation + 1 candidature + formations (2) + dossiers (5) ; signalements indisponibles ignorés.
        self::assertSame(4, $p['decisions']['total']);
        $titles = array_column($p['decisions']['items'], 'title');
        self::assertSame(['James Alexander', 'Jules Petit', '2 formations arrivent à échéance', '5 dossiers à compléter'], $titles);

        [$effectif, $presence, $ops, $files] = $p['kpis'];
        self::assertSame('36', $effectif['value']);
        self::assertSame('/ 40', $effectif['unit']);
        self::assertSame('84 %', $presence['value']);
        self::assertSame(['text' => '+6 pts', 'tone' => 'up'], $presence['badge']);
        self::assertSame([94, 88, 89], $presence['spark'], 'Chronologique : la plus ancienne d’abord.');
        self::assertSame([29, 30, 32], $ops['spark']);
        self::assertSame('9', $ops['value']);
        self::assertSame('dont 6 terminées', $ops['unit']);
        self::assertSame('86 %', $files['value']);
        self::assertSame('#dossiers-incomplets', $files['note_href']);

        self::assertSame(['Granit', 'Sirocco', 'Basalte'], array_column($p['participation']['bars'], 'short'));
        self::assertSame(40, $p['participation']['scale']);
        self::assertTrue($p['participation']['has_checkins']);

        self::assertSame(['Alpha Assault', 'Aviation'], array_column($p['units'], 'name'));
        self::assertSame('3/4 postes · 1 vacant', $p['units'][1]['posts']);
        self::assertSame('bad', $p['units'][1]['tone']);

        self::assertSame(['Jules Petit', 'Lynx'], array_column($p['pipeline']['people'], 'name'));
        self::assertSame(5, $p['pipeline']['tiles'][3]['n']);

        self::assertSame('Cette semaine', $p['agenda']['title']);
        self::assertCount(2, $p['agenda']['items']);
        self::assertTrue($p['agenda']['items'][1]['highlight']);

        self::assertCount(1, $p['feed'], 'Les connexions ne remontent pas dans le fil.');
        self::assertSame('a validé une qualification', $p['feed'][0]['action']);
        self::assertSame('AA', $p['feed'][0]['initials']);

        $html = $this->render($p, [
            'configurationUpdateBadge' => 11,
            'missingMediaCount' => 2,
            'orgMessagesRecent' => [['id' => 3, 'subject' => 'Briefing', 'last_preview' => 'Relire les ordres', 'updated_at' => '2026-11-01 09:00:00', 'thread_unread' => true]],
            'orgActivityChart' => ['days' => ['31', '01'], 'portal' => [3, 5], 'atak' => [1, 2], 'max' => 5, 'period_days' => 2],
        ]);
        foreach (['Jake &lt;b&gt;G&lt;/b&gt;', 'La prochaine opération est dans <b>2 jours</b>', 'Op. Aube Rouge', 'role="timer"', 'Ouvrir la fiche', 'reponses-nominatives', 'Campagne Altis', '11 nouvelles configurations', 'back-office/mise-a-niveau', 'lors de la migration', 'James Alexander', 'Examiner', 'Parcours des recrues', 'back-office/recruitments/5', 'Transmissions', 'pil-spark', 'Briefing', 'Non lu', 'État des unités', 'back-office/organisation/disponibilite'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        self::assertStringNotContainsString('Jake <b>G</b>', $html);
        self::assertStringNotContainsString('Ana Refus', $html);
    }

    public function testLinksAndBlocksFollowRights(): void
    {
        $denied = ['back-office/recruitments', 'back-office/ressources/effectifs', 'back-office/events', 'back-office/audit'];
        $allowed = static function (string $href) use ($denied): bool {
            foreach ($denied as $path) {
                if (str_contains($href, $path)) {
                    return false;
                }
            }

            return true;
        };
        $p = PilotageDashboardPresenter::build($this->filledInput($allowed));

        self::assertNull($p['pipeline'], 'Sans accès au recrutement, le bloc disparaît.');
        self::assertNull($p['hero']['href']);
        self::assertNull($p['links']['effectifs']);
        self::assertSame([], array_filter($p['decisions']['items'], static fn (array $i): bool => in_array($i['title'], ['James Alexander', 'Jules Petit'], true)));
        foreach ($p['agenda']['items'] as $item) {
            self::assertNull($item['href']);
        }

        $html = $this->render($p);
        self::assertStringNotContainsString('Parcours des recrues', $html);
        self::assertStringNotContainsString('back-office/recruitments', $html);
        self::assertStringNotContainsString('Ouvrir la fiche', $html);
        self::assertStringNotContainsString('>Effectifs<', $html);
        self::assertStringNotContainsString('back-office/audit', $html);
        // Accueil en blocs (HomeLayout) : sans accès au recrutement, le bloc n'est pas rendu du tout.
        self::assertStringNotContainsString('data-widget="pipeline"', $html);
        self::assertStringContainsString('data-widget="agenda"', $html);
        self::assertStringContainsString('Op. Aube Rouge', $html, 'Le résumé de l’opération reste lisible.');
    }

    public function testServiceSurvivesErrorsAndCachesUnitReadiness(): void
    {
        $events = $this->createMock(CommunityEventRepository::class);
        $events->method('participationForRecentEvents')->willThrowException(new \RuntimeException('base absente'));
        $events->method('presenceWindowsForTenant')->willThrowException(new \RuntimeException('base absente'));
        $events->expects(self::once())->method('countStartingInPeriod')
            ->with(7, '2026-11-01 00:00:00', '2026-12-01 00:00:00')
            ->willReturn(['total' => 3, 'done' => 1]);

        $readiness = $this->createMock(UnitReadinessService::class);
        $readiness->expects(self::once())->method('scoresForTenant')->with(7)
            ->willReturn([['unit_id' => 1, 'name' => 'Alpha', 'authorized' => 2, 'filled' => 2, 'score' => 90]]);

        $dir = sys_get_temp_dir() . '/pilotage-test-' . bin2hex(random_bytes(4));
        $service = new PilotageDashboardService($events, $readiness, $dir);

        self::assertSame([], $service->participation(7));
        self::assertNull($service->presence(7));
        self::assertSame(['total' => 3, 'done' => 1, 'month' => 11], $service->monthEvents(7, (int) strtotime(self::NOW)));
        self::assertSame([], $service->unitReadiness(0));

        $first = $service->unitReadiness(7);
        $second = $service->unitReadiness(7);
        self::assertSame('Alpha', $first[0]['name']);
        self::assertSame($first, $second, 'Le second appel vient du cache.');

        @unlink($dir . '/unit_readiness_7.json');
        @rmdir($dir);
    }

    public function testControllerAndStylesAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        $css = (string) file_get_contents($root . '/public/assets/css/pilotage-dashboard.css');

        self::assertStringContainsString('PilotageDashboardPresenter::build', $ctrl);
        self::assertStringContainsString("'boSkipPageHead' => true", $ctrl);
        self::assertStringContainsString('pilotage-dashboard.css', $ctrl);
        self::assertStringContainsString('portal_nav_href_permission_allowed', $ctrl);
        self::assertStringContainsString('html[data-bo-theme="dark"] .pil', $css);
        self::assertStringContainsString('@media (max-width: 560px)', $css);
        self::assertFileDoesNotExist($root . '/views/partials/ath_dashboard_dash.php');
    }
}
