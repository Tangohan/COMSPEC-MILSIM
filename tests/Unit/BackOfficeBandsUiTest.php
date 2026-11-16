<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Session;
use App\Services\BackOffice\Home\HomeLayout;
use App\Services\BackOffice\Menu\PendingTaskDetail;
use App\Services\Workflow\WorkflowEngine;
use App\Support\PilotageDashboardPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Nouvelle interface en bandes du back-office (back-office-bands.css) : rendu réel du tableau de bord et de
 * « Mon service », données remplies et vides (bandes, index, chiffres clés, miroir, fenêtre d'avis, accessibilité).
 */
final class BackOfficeBandsUiTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('b', 64), 'tenant_id' => 0, 'user_id' => 0];
        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization', 'admin.access']);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        Gate::reset();
    }

    /** @param array<string, mixed> $vars */
    private function render(string $relative, array $vars): string
    {
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

        return $render(self::ROOT . '/' . $relative, $vars);
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $relative);
    }

    /** @return array<string, mixed> */
    private function serviceTasks(): array
    {
        $day = 86400;

        return [
            ['id' => 41, 'title' => 'Avis sur la mutation de Tom Durand', 'description' => 'Votre avis de chef d’unité est demandé.', 'priority' => 'high', 'status' => 'open', 'due_at' => date('Y-m-d 18:00:00', time() + 2 * $day), 'type' => WorkflowEngine::TASK_TYPE_OPINION, 'linked_entity_id' => 9],
            ['id' => 42, 'title' => 'Valider le compte rendu', 'description' => '', 'priority' => 'normal', 'status' => 'acknowledged', 'due_at' => date('Y-m-d 21:00:00', time() - 3 * $day), 'type' => 'validate_report'],
            ['id' => 43, 'title' => 'Préparer l’ordre de mouvement', 'description' => '', 'priority' => 'critical', 'status' => 'in_progress', 'due_at' => '', 'type' => 'sitrep'],
        ];
    }

    public function testMonServiceRendersOneBandPerTaskWithIndexFigureAndMirror(): void
    {
        $html = $this->render('views/portal/action_center.php', [
            'action_center_digest' => ['total_attention' => 5, 'sections' => [
                ['title' => 'Agenda et échéances', 'items' => [['label' => 'Op. Aube Rouge', 'href' => '/evenements', 'hint' => 'Mercredi', 'priority' => 'high', 'action' => 'Répondre', 'event_id' => 12, 'rsvp_status' => '']]],
                ['title' => 'Raccourcis', 'items' => [['label' => 'Boîte de réception', 'href' => '/boite-reception', 'hint' => 'Messages.']]],
            ]],
            'mon_service' => [
                'display_name' => 'Jake Garnier', 'grade_label' => 'Sergent-chef', 'unit_label' => 'Alpha', 'organic_post_label' => 'Chef de groupe', 'technical_role_label' => 'Gestionnaire',
                'mission_duty' => ['duty_title' => 'Chef de patrouille', 'duty_callsign' => 'Viper 2-1', 'mission_label' => 'Op. Aube Rouge'],
                'tasks' => $this->serviceTasks(), 'waiting' => ['validations' => 1, 'reports' => 1],
            ],
        ]);

        self::assertStringNotContainsString('Fatal error', $html);
        self::assertSame(1, substr_count($html, '<h1'), 'Un seul titre de page : celui de la bande d’en-tête.');
        self::assertMatchesRegularExpression('#<h1 class="bo-band__title" id="ath-page-title">Jake Garnier</h1>#', $html);
        self::assertStringContainsString('<span class="bo-faces__it">JG</span>', $html);
        self::assertStringContainsString('href="#mon-service-tasks"', $html, 'Une seule flèche d’action dans l’en-tête.');
        self::assertSame(1, substr_count($html, 'bo-band__arrow'));
        self::assertMatchesRegularExpression('#<p class="bo-figure is-hot"><strong>5</strong><span>éléments à traiter</span></p>#', $html);
        self::assertStringContainsString('<dt>Indicatif</dt><dd>Viper 2-1</dd>', $html);

        // Une bande par tâche, index « / 01 » à côté du titre, une sur deux en miroir.
        self::assertSame(3, preg_match_all('#<article class="bo-band bo-band--[a-z]+(?: is-mirror)?" style="--bo-band-i:\d+"#', $html));
        foreach (['/ 01', '/ 02', '/ 03'] as $index) {
            self::assertStringContainsString('<span class="bo-band__index" aria-hidden="true">' . $index . '</span>', $html);
        }
        self::assertSame(1, substr_count($html, ' is-mirror"'));
        self::assertStringContainsString('bo-band--rose', $html, 'Une tâche critique prend l’accent d’alerte.');
        foreach ([41, 42, 43] as $id) {
            self::assertStringContainsString('aria-labelledby="mon-service-task-' . $id . '"', $html);
            self::assertStringContainsString('id="mon-service-task-' . $id . '"', $html);
        }

        // Chiffre clé : jours avant l'échéance, retard en alerte ; aucune date brute de base de données.
        self::assertMatchesRegularExpression('#<p class="bo-figure"><strong>2<small> j</small></strong><span>avant l’échéance</span></p>#', $html);
        self::assertMatchesRegularExpression('#<p class="bo-figure is-alert"><strong>3<small> j</small></strong><span>de retard</span></p>#', $html);
        self::assertDoesNotMatchRegularExpression('#\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}#', $html);
        self::assertStringContainsString('Sans échéance', $html);
        self::assertStringContainsString('<span>Pris en compte</span>', $html);

        // Avis : un bouton ouvre la fenêtre qui porte le formulaire (mêmes route, champs et jeton).
        self::assertStringContainsString('data-bo-dialog-open="mon-service-avis-41" aria-haspopup="dialog"', $html);
        self::assertStringContainsString('<dialog class="bo-dialog" id="mon-service-avis-41" aria-labelledby="mon-service-avis-41-title">', $html);
        self::assertMatchesRegularExpression('#action="[^"]*mon-service/circuit/9/avis"#', $html);
        self::assertStringContainsString('name="avis" value="favorable"', $html);
        self::assertStringContainsString('name="avis" value="defavorable"', $html);
        self::assertStringContainsString('<label class="bo-dialog__label" for="avis-41">', $html);
        self::assertStringContainsString('name="commentaire"', $html);
        self::assertStringNotContainsString('mon-service/tache/41/', $html, 'Une tâche d’avis ne se termine pas par « Terminer ».');

        // Actions d'état : une pleine (Terminer), les autres discrètes, toutes avec le jeton CSRF.
        self::assertMatchesRegularExpression('#action="[^"]*mon-service/tache/42/demarrer"#', $html);
        self::assertMatchesRegularExpression('#action="[^"]*mon-service/tache/43/terminer"#', $html);
        self::assertStringNotContainsString('mon-service/tache/43/demarrer', $html);
        self::assertSame(2, substr_count($html, 'class="bo-band__btn bo-band__btn--solid">Terminer</button>'));
        self::assertSame(4, substr_count($html, 'name="_csrf_token" value="' . str_repeat('b', 64) . '"'));

        // Suivi : sections avec index et une ligne de description, tuiles, ancre de l'agenda conservée.
        self::assertStringContainsString('id="agenda-et-echeances"', $html);
        self::assertStringContainsString('<span class="bo-section__index" aria-hidden="true">/ 02</span>', $html);
        self::assertStringContainsString('<p class="bo-section__desc">Les pages utiles au quotidien.</p>', $html);
        self::assertStringContainsString('data-rsvp-group', $html);
        self::assertStringContainsString('class="bo-tile bo-tile--link"', $html);
        self::assertStringContainsString('back-office-bands.js', $html);
    }

    public function testMonServiceEmptyStateIsAQuietBandWithoutCallToAction(): void
    {
        $html = $this->render('views/portal/action_center.php', ['action_center_digest' => ['sections' => []], 'mon_service' => []]);

        self::assertStringContainsString('Aucune tâche ouverte', $html);
        self::assertStringContainsString('bo-band bo-band--empty', $html);
        self::assertStringNotContainsString('<ol class="bo-bands">', $html);
        self::assertStringNotContainsString('bo-band__arrow', $html);
        self::assertStringNotContainsString('<dialog', $html);
        self::assertMatchesRegularExpression('#<strong>0</strong><span>élément à traiter</span>#', $html);
        self::assertStringContainsString('<b>0</b> tâche ouverte', $html);
    }

    /** @return array<string, mixed> */
    private function dashboardVars(bool $filled): array
    {
        $now = (int) strtotime('2026-11-01 15:20:00');
        $in = ['now' => $now, 'tenant_name' => 'SOAR', 'viewer_name' => 'Jake', 'link_allowed' => null];
        $tasks = [];
        if ($filled) {
            $in += [
                'kpis' => [['id' => 'members_active', 'value' => '36'], ['id' => 'members_inactive', 'value' => '4']],
                'upcoming' => [['id' => 12, 'title' => 'Op. Aube Rouge', 'starts_at' => '2026-11-03 20:30:00', 'ends_at' => '2026-11-03 23:30:00', 'location' => 'Altis', 'campaign_tag' => 'Campagne Altis', 'event_type' => 'operation']],
                'rsvp' => [12 => ['yes' => array_fill(0, 28, []), 'maybe' => array_fill(0, 3, []), 'no' => array_fill(0, 2, [])]],
                'activity' => [
                    ['action' => 'qualification.awarded', 'actor_name' => 'Andrew Anderson', 'action_label' => 'A validé une qualification', 'target' => 'Transmissions', 'created_at' => '2026-11-01 13:10:00'],
                    ['action' => 'event.created', 'actor_name' => 'Marie Lefèvre', 'action_label' => 'A programmé une activité', 'target' => 'Op. Aube Rouge', 'created_at' => '2026-11-01 12:10:00'],
                ],
            ];
            $tasks = [
                ['title' => 'Candidatures', 'path' => 'back-office/recruitments', 'count' => 2, 'important' => true, 'key' => 'recruit', 'detail' => new PendingTaskDetail('', 'Deux dossiers attendent.', 'La candidature est close.', 'Ouvrez chaque dossier.', 'Examiner les candidatures', 'back-office/recruitments', '', 'Une candidature passe par plusieurs étapes.')],
                ['title' => 'Corrections', 'path' => 'back-office/corrections', 'count' => 3, 'important' => false, 'key' => 'corr', 'detail' => null],
            ];
        }

        return [
            'pilotage' => PilotageDashboardPresenter::build($in),
            'initialSetupBanner' => null, 'configurationUpdateBadge' => 0, 'discordInviteMissing' => false, 'missingMediaCount' => 0,
            'orgActivityChart' => ['days' => [], 'portal' => [], 'atak' => [], 'max' => 1, 'period_days' => 14],
            'orgMessagesRecent' => [],
            'orgProfileGaps' => ['rows' => [], 'total' => 0, 'shown' => 0, 'truncated' => false, 'counts' => [], 'error' => null],
            'home' => ['items' => HomeLayout::defaults(HomeLayout::ROLE_MANAGER), 'sheets' => [], 'tasks' => $tasks, 'customize' => false],
        ];
    }

    public function testDashboardHeroOperationAndTasksAreBands(): void
    {
        $html = $this->render('views/admin/organization/dashboard.php', $this->dashboardVars(true));

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('<header class="bo-band bo-band--hero bo-band--emerald pil-hero">', $html);
        self::assertStringContainsString('aria-label="Actifs récemment : Andrew Anderson, Marie Lefèvre"', $html);
        self::assertStringContainsString('>AA</span>', $html);
        self::assertStringContainsString('Personnaliser l’accueil', $html);
        self::assertSame(1, preg_match('#<header class="bo-band bo-band--hero.*?</header>#s', $html, $hero));
        self::assertSame(1, substr_count($hero[0], 'bo-band__arrow'), 'Une seule flèche d’action dans l’en-tête.');
        self::assertStringContainsString('<p class="bo-figure"><strong>0</strong><span>décision en attente</span></p>', $hero[0]);

        // Prochaine opération : bande, index, compte à rebours dans la pastille, chiffre clé des présences.
        self::assertMatchesRegularExpression('#<section class="bo-band bo-band--emerald pil-op" aria-labelledby="pil-op-title">#', $html);
        self::assertStringContainsString('<h2 class="bo-band__title" id="pil-op-title">Op. Aube Rouge</h2>', $html);
        self::assertMatchesRegularExpression('#<p class="bo-band__meta pil-cd" data-pil-countdown="[^"]+" role="timer"#', $html);
        self::assertStringContainsString('<p class="bo-figure is-hot"><strong>28<small> / 36</small></strong><span>présences confirmées</span></p>', $html);
        self::assertStringContainsString('class="bo-band__btn bo-band__btn--solid" href', $html);
        self::assertStringContainsString('Campagne Altis', $html);

        // Tâche expliquée : bande compacte avec index et chiffre clé ; tâche simple : ligne de lien.
        self::assertStringContainsString('class="bo-band bo-band--compact bo-band--rose"', $html);
        self::assertStringContainsString('<p class="bo-figure is-hot"><strong>2</strong><span>en attente</span></p>', $html);
        self::assertStringContainsString('class="home-tasks__it"', $html);
        self::assertStringContainsString('Si vous ne faites rien', $html);

        // Pas de surtitre.
        self::assertDoesNotMatchRegularExpression('#class="[^"]*\b(?:eyebrow|kicker|overline)\b#', $html);
    }

    public function testDashboardEmptyOperationIsAnEmptyBandWithOneAction(): void
    {
        $html = $this->render('views/admin/organization/dashboard.php', $this->dashboardVars(false));

        self::assertStringContainsString('bo-band bo-band--empty bo-band--sky pil-op pil-op--empty', $html);
        self::assertStringContainsString('id="pil-op-title">À planifier</h2>', $html);
        self::assertStringContainsString('Programmer une activité <span class="bo-band__arrow" aria-hidden="true">→</span>', $html);
        self::assertStringNotContainsString('role="timer"', $html);
        self::assertStringNotContainsString('bo-faces', $html, 'Pas de vignettes sans activité réelle.');
        self::assertStringContainsString('Rien en attente', $html);
    }

    public function testBandStylesAreReusableAccessibleAndWired(): void
    {
        $css = self::read('public/assets/css/back-office-bands.css');
        foreach (['.bo-section__index', '.bo-band__glow', '.bo-band__veil', '.bo-band__media', '.bo-band.is-mirror', '.bo-figure', '.bo-band__meta', '.bo-tiles', '.bo-dialog', '@container (min-width: 720px)', 'prefers-reduced-motion: reduce', 'min-height: 44px', '--bo-band-i'] as $needle) {
            self::assertStringContainsString($needle, $css, $needle);
        }
        preg_match_all('#font-size:\s*(\d+(?:\.\d+)?)px#', $css, $sizes);
        foreach ($sizes[1] as $size) {
            self::assertGreaterThanOrEqual(13, (float) $size, 'Texte de 13 px au moins.');
        }
        self::assertStringNotContainsString('Archivo', $css);
        self::assertDoesNotMatchRegularExpression('#\.sse-|sse-ds#', $css);
        // Couleurs littérales seulement dans les propriétés personnalisées (le générateur du mode nuit ne les touche pas).
        foreach (preg_split('/\R/', $css) ?: [] as $line) {
            if (preg_match('/^\s*(?:background|color|border(?:-color)?)\s*:[^;]*#[0-9a-f]{3,8}\b/i', $line) === 1) {
                self::fail('Couleur littérale hors jeton : ' . trim($line));
            }
        }

        $js = self::read('public/assets/js/back-office-bands.js');
        self::assertStringContainsString('showModal', $js);
        self::assertStringContainsString('data-bo-dialog-close', $js);

        self::assertStringContainsString("'back-office-bands.css'", self::read('app/Controllers/Admin/Organization/OrganizationDashboardController.php'));
        $svc = self::read('app/Controllers/Web/ActionCenterController.php');
        self::assertStringContainsString("'backOfficePageCss' => ['back-office-bands.css']", $svc);
        self::assertStringContainsString("'isBackOfficeShell' => true", $svc);
        self::assertStringContainsString("'boSkipPageHead' => true", $svc);
    }
}
