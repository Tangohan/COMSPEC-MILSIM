<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Services\Community\ContributorShowcase;
use App\Services\Platform\DonationBanner;
use PHPUnit\Framework\TestCase;

/**
 * Page /contribuer (mur des contributeurs, sans données privées) et bandeau de don sur tout le site
 * (réglage plateforme, dates, pages exclues, droit administrateur).
 */
final class ContributePageAndDonationBannerTest extends TestCase
{
    private ?bool $sessionStarted = null;
    /** @var array<string, mixed> */
    private array $savedSession = [];

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $prop->getValue();
        $prop->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64)];
        $GLOBALS['__app_locale'] = 'fr';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        unset($GLOBALS['__app_locale']);
    }

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
        $file = base_path('views/site/contribute.php');
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

        return $render($file, $vars);
    }

    /** @return array<string, mixed> */
    private function showcase(): array
    {
        $now = strtotime('2026-11-01 12:00:00');
        $wall = [
            ['user_id' => 11, 'name' => 'Ghost', 'roles' => ['translator', 'tester'], 'languages' => 'es,de', 'since' => '2026-10-10 10:00:00', 'email' => 'jean.dupont@example.com', 'display_name' => 'Jean Dupont'],
            ['user_id' => 12, 'name' => 'Viper Six', 'roles' => ['developer'], 'languages' => '', 'since' => '2025-01-05 10:00:00'],
            ['user_id' => 13, 'name' => 'leak@example.com', 'roles' => ['support'], 'languages' => '', 'since' => '2026-10-01 10:00:00'],
            ['user_id' => 14, 'name' => 'Nobody', 'roles' => ['unknown'], 'languages' => '', 'since' => null],
        ];
        $leaders = [
            ['user_id' => 11, 'name' => 'Ghost', 'validated' => 42, 'public_credit' => true, 'public_roles' => ['translator']],
            ['user_id' => 15, 'name' => 'Hidden', 'validated' => 99, 'public_credit' => false, 'public_roles' => []],
        ];
        $donors = [['name' => 'Raven', 'tier' => 'pilier', 'since' => '2026-01-01', 'community' => '1er RIMa', 'community_slug' => '1rima']];
        $packs = [['public_id' => 'abc123', 'lang_name' => 'Español', 'author_label' => 'Lobo', 'downloads' => 12]];

        return ContributorShowcase::build($wall, $leaders, $donors, $packs, ['contributors' => 5, 'texts' => 340, 'languages' => 2, 'communities' => 3], $now);
    }

    public function testShowcaseKeepsOnlyPublicNamesAndRealRoles(): void
    {
        $s = $this->showcase();
        $names = array_column($s['people'], 'name');
        self::assertSame(['Ghost', 'Viper Six'], $names, 'Plus actif en tête ; adresse e-mail et rôle inconnu écartés.');
        self::assertSame(42, $s['people'][0]['texts']);
        self::assertTrue($s['people'][0]['is_new']);
        self::assertFalse($s['people'][1]['is_new']);
        self::assertSame(['ES', 'DE'], $s['people'][0]['languages']);
        self::assertSame('GH', $s['people'][0]['monogram']);
        self::assertSame('VS', $s['people'][1]['monogram']);
        self::assertSame(['Ghost'], array_column($s['active'], 'name'), 'Un traducteur qui refuse d’être cité n’est pas classé.');
        self::assertSame(['Ghost'], array_column($s['newcomers'], 'name'));
        self::assertSame(1, $s['roles']['translator']);
        self::assertSame(0, $s['roles']['support']);
        self::assertSame(5, $s['stats']['contributors']);
        self::assertSame(3, $s['stats']['languages'], 'Langues du site publiées + packs de langue des mods.');
        foreach ($s['people'] as $p) {
            self::assertArrayNotHasKey('email', $p);
            self::assertArrayNotHasKey('display_name', $p);
        }
    }

    public function testPageWithContributorsShowsWallWithoutPrivateData(): void
    {
        $html = $this->render([
            'contribUser' => null,
            'contribReady' => true,
            'contribMine' => [],
            'contribShowcase' => $this->showcase(),
        ]);
        self::assertStringContainsString('id="mur"', $html);
        self::assertStringContainsString('class="ctr-wall', $html);
        self::assertStringContainsString('class="don-hero"', $html, 'Même famille que la page de dons.');
        self::assertStringContainsString('assets/css/site-support.css', (string) file_get_contents(base_path('app/Controllers/Web/ContributorController.php')));
        self::assertStringContainsString('Ghost', $html);
        self::assertStringContainsString('Viper Six', $html);
        self::assertStringContainsString('Raven', $html);
        self::assertStringContainsString('Español', $html);
        self::assertStringContainsString('data-ctr-filter', $html);
        self::assertStringContainsString('traduire-site', $html);
        self::assertStringContainsString('soutenir-atak', $html);
        self::assertStringContainsString('340', $html);
        self::assertStringNotContainsString('@example.com', $html);
        self::assertStringNotContainsString('Jean Dupont', $html);
        self::assertStringNotContainsString('Hidden', $html);
        self::assertStringNotContainsString('site.contrib_', $html, 'Aucune clé de traduction brute.');
        self::assertStringNotContainsString('ctr-empty', $html);
    }

    public function testPageWithoutContributorsInvitesToJoin(): void
    {
        $html = $this->render([
            'contribUser' => null,
            'contribReady' => true,
            'contribMine' => [],
            'contribShowcase' => ContributorShowcase::build([], [], [], []),
        ]);
        self::assertStringContainsString('ctr-empty', $html);
        self::assertStringContainsString(__('site.contrib_wall_empty_title'), $html);
        self::assertStringNotContainsString('class="ctr-wall', $html);
        self::assertStringNotContainsString('id="donateurs"', $html);
        self::assertStringNotContainsString('ctr-stats', $html, 'Pas de chiffres à zéro.');
        self::assertStringContainsString('id="participer"', $html);
        self::assertStringContainsString(__('site.contrib_login'), $html);
        self::assertStringNotContainsString('site.contrib_', $html);
    }

    public function testPageKeysExistInBothLanguages(): void
    {
        $fr = require base_path('lang/fr/site.php');
        $en = require base_path('lang/en/site.php');
        foreach (array_keys($fr) as $k) {
            if (str_starts_with((string) $k, 'contrib_') || str_starts_with((string) $k, 'dnb_')) {
                self::assertArrayHasKey($k, $en, $k);
            }
        }
    }

    // ---- Bandeau de don ------------------------------------------------------------------------

    /** @param array<string, string> $extra */
    private function cfg(array $extra = []): array
    {
        return DonationBanner::configFrom($extra + [
            DonationBanner::KEY_ENABLED => '1',
            DonationBanner::KEY_MESSAGE_FR => 'Un petit don garde les serveurs allumés.',
            DonationBanner::KEY_MESSAGE_EN => 'A small donation keeps the servers running.',
            DonationBanner::KEY_GOAL_EUR => '300',
            DonationBanner::KEY_VERSION => '3',
        ]);
    }

    public function testBannerActiveInactiveAndDates(): void
    {
        $now = strtotime('2026-11-15 12:00:00');
        self::assertTrue(DonationBanner::isLive($this->cfg(), $now));
        self::assertFalse(DonationBanner::isLive($this->cfg([DonationBanner::KEY_ENABLED => '0']), $now));
        self::assertFalse(DonationBanner::isLive(DonationBanner::configFrom([]), $now), 'Désactivé par défaut.');
        self::assertTrue(DonationBanner::isLive($this->cfg([DonationBanner::KEY_STARTS_ON => '2026-11-15', DonationBanner::KEY_ENDS_ON => '2026-11-15']), $now), 'Bornes incluses.');
        self::assertFalse(DonationBanner::isLive($this->cfg([DonationBanner::KEY_STARTS_ON => '2026-11-16']), $now), 'Pas encore commencé.');
        self::assertFalse(DonationBanner::isLive($this->cfg([DonationBanner::KEY_ENDS_ON => '2026-11-14']), $now), 'Terminé.');
        self::assertSame('', $this->cfg([DonationBanner::KEY_STARTS_ON => '2026-13-40'])['starts_on'], 'Date illisible ignorée.');

        $view = DonationBanner::view($this->cfg(), 15000, 'en', $now);
        self::assertNotNull($view);
        self::assertSame('A small donation keeps the servers running.', $view['message']);
        self::assertSame(50, $view['percent']);
        self::assertSame(30000, $view['goal_cents']);
        self::assertSame(100, DonationBanner::view($this->cfg(), 99999, 'fr', $now)['percent'] ?? null, 'Jauge plafonnée à 100 %.');
        self::assertSame('Un petit don garde les serveurs allumés.', DonationBanner::view($this->cfg([DonationBanner::KEY_MESSAGE_EN => '']), 0, 'en', $now)['message'] ?? null, 'Repli sur le français.');
        self::assertNull(DonationBanner::view($this->cfg([DonationBanner::KEY_ENABLED => '0']), 0, 'fr', $now));
        self::assertNotSame(DonationBanner::view($this->cfg(), 0, 'fr', $now)['key'] ?? '', DonationBanner::view($this->cfg([DonationBanner::KEY_VERSION => '4']), 0, 'fr', $now)['key'] ?? '', 'Un nouvel enregistrement fait revenir le bandeau fermé.');
        self::assertSame('Merci, vraiment', DonationBanner::cleanMessage("<b>Merci</b>,\n  vraiment"), 'Balises et retours à la ligne retirés.');
    }

    public function testBannerHiddenOnWorkPagesAndShownOnPublicPages(): void
    {
        foreach (['admin/settings', 'back-office/members', 'login', 'register', 'paiement/formule', 'soutenir-atak', 'atak', 'atak/sse/x', 'public/admin', 'api/atak/web-log', 'formation/sessions'] as $path) {
            self::assertTrue(DonationBanner::hiddenOnPath($path), $path);
        }
        foreach (['', 'public', 'contribuer', 'public/contribuer', 'a-propos', 'atak-natif', 'communities', 'hub', 'account'] as $path) {
            self::assertFalse(DonationBanner::hiddenOnPath($path), $path);
        }
    }

    public function testBannerRaisedCountsOnlyPaidDonationsInPeriod(): void
    {
        $ledger = [
            ['source' => 'donation', 'status' => 'activated', 'amount_cents' => 1000, 'paid_at' => '2026-11-02 10:00:00', 'refunded_at' => null],
            ['source' => 'stripe_donation', 'status' => 'activated', 'amount_cents' => 500, 'paid_at' => '2026-11-03 10:00:00', 'refunded_at' => null],
            ['source' => 'heart', 'status' => 'activated', 'amount_cents' => 700, 'paid_at' => '2026-11-04 10:00:00', 'refunded_at' => null],
            ['source' => 'plan', 'status' => 'activated', 'amount_cents' => 9999, 'paid_at' => '2026-11-04 10:00:00', 'refunded_at' => null],
            ['source' => 'donation', 'status' => 'refunded', 'amount_cents' => 2000, 'paid_at' => '2026-11-05 10:00:00', 'refunded_at' => '2026-11-06'],
            ['source' => 'stripe_donation', 'status' => 'awaiting', 'amount_cents' => 3000, 'paid_at' => null, 'refunded_at' => null],
            ['source' => 'donation', 'status' => 'activated', 'amount_cents' => 4000, 'paid_at' => '2026-10-30 10:00:00', 'refunded_at' => null],
            ['source' => 'donation', 'status' => 'activated', 'amount_cents' => 8000, 'paid_at' => '2026-12-01 10:00:00', 'refunded_at' => null],
        ];
        self::assertSame(2200, DonationBanner::raisedFromLedger($ledger, '2026-11-01', '2026-11-30'));
        self::assertSame(10200, DonationBanner::raisedFromLedger($ledger, '2026-11-01', ''));
        self::assertSame(['2026-11-01', ''], DonationBanner::countedPeriod(['starts_on' => '', 'ends_on' => ''], strtotime('2026-11-20')));
    }

    public function testBannerFormValidation(): void
    {
        $ok = DonationBanner::pairsFromForm(['enabled' => '1', 'message_fr' => 'Merci !', 'goal_eur' => '250', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30'], 2);
        self::assertNull($ok['error']);
        self::assertSame('1', $ok['pairs'][DonationBanner::KEY_ENABLED]);
        self::assertSame('3', $ok['pairs'][DonationBanner::KEY_VERSION]);
        self::assertSame('250', $ok['pairs'][DonationBanner::KEY_GOAL_EUR]);
        self::assertNotNull(DonationBanner::pairsFromForm(['enabled' => '1'], 0)['error'], 'Activé sans message : refusé.');
        self::assertNotNull(DonationBanner::pairsFromForm(['message_fr' => 'x', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-01'], 0)['error']);
        self::assertNotNull(DonationBanner::pairsFromForm(['message_fr' => 'x', 'goal_eur' => '12,5'], 0)['error']);
        self::assertNull(DonationBanner::pairsFromForm([], 0)['error'], 'Désactiver sans rien remplir est permis.');
    }

    public function testBannerRenderedInLayoutsAndSettingIsAdminOnly(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));
        self::assertMatchesRegularExpression(
            "~post\\('/admin/paiements/bandeau-don', \\[SystemSettingsController::class, 'saveDonationBanner'\\], \\[AuthMiddleware::class, SystemAdminMiddleware::class\\]\\)~",
            $routes
        );
        self::assertStringContainsString("views/partials/donation_banner.php", (string) file_get_contents(base_path('views/layout/main.php')));
        self::assertStringContainsString("views/partials/donation_banner.php", (string) file_get_contents(base_path('views/layout/marketing.php')));
        $settings = (string) file_get_contents(base_path('views/admin/system/settings.php'));
        self::assertStringContainsString('Gérer depuis Paiements', $settings);
        self::assertStringNotContainsString('name="message_fr"', $settings, 'Le formulaire vit dans Paiements, plus dans Paramètres système.');
        self::assertStringNotContainsString("'/admin/settings/bandeau-don'", $routes);
        self::assertStringContainsString('views/partials/donation_banner_markup.php', (string) file_get_contents(base_path('views/partials/donation_banner.php')));
        $partial = (string) file_get_contents(base_path('views/partials/donation_banner.php')) . (string) file_get_contents(base_path('views/partials/donation_banner_markup.php'));
        self::assertStringContainsString('try {', $partial);
        self::assertStringContainsString('localStorage', $partial);
        self::assertStringContainsString("url('soutenir-atak')", $partial);
        $css = (string) file_get_contents(base_path('public/assets/css/donation-banner.css'));
        self::assertStringContainsString('prefers-reduced-motion', $css);
        self::assertStringContainsString('prefers-color-scheme: dark', $css);
    }

    public function testSaveRefusesExpiredSessionWithoutWriting(): void
    {
        $repo = $this->getMockBuilder(\App\Repositories\PlatformSettingsRepository::class)->disableOriginalConstructor()->getMock();
        $repo->expects(self::never())->method('setMany');
        $controller = new \App\Controllers\Admin\System\SystemSettingsController($repo);
        $savedPost = $_POST;
        $_POST = ['_csrf_token' => 'wrong', 'enabled' => '1', 'message_fr' => 'x'];
        try {
            $response = $controller->saveDonationBanner(new \App\Core\Request());
        } finally {
            $_POST = $savedPost;
        }
        self::assertSame(302, $response->statusCode());
    }

    public function testAdminStateStatusGaugeAndPreview(): void
    {
        $now = strtotime('2026-11-15 12:00:00');
        $raw = [DonationBanner::KEY_ENABLED => '1', DonationBanner::KEY_MESSAGE_FR => 'Aidez-nous', DonationBanner::KEY_GOAL_EUR => '200', DonationBanner::KEY_STARTS_ON => '2026-11-01', DonationBanner::KEY_ENDS_ON => '2026-11-30'];
        $state = DonationBanner::adminState($raw, 5000, $now);
        self::assertSame('live', $state['status']);
        self::assertSame(25, $state['percent']);
        self::assertSame(20000, $state['goal_cents']);
        self::assertSame('Aidez-nous', $state['preview']['en']['message'] ?? null, 'Sans anglais, le français est repris.');
        self::assertSame('scheduled', DonationBanner::adminState([DonationBanner::KEY_STARTS_ON => '2026-12-01'] + $raw, 0, $now)['status']);
        self::assertSame('ended', DonationBanner::adminState([DonationBanner::KEY_ENDS_ON => '2026-11-10'] + $raw, 0, $now)['status']);
        $off = DonationBanner::adminState([DonationBanner::KEY_ENABLED => '0'] + $raw, 0, $now);
        self::assertSame('disabled', $off['status']);
        self::assertNotNull($off['preview']['fr'], 'L’aperçu reste visible quand le bandeau est coupé.');
    }

    public function testPaymentsPageRendersBannerSection(): void
    {
        $now = time();
        $raw = [DonationBanner::KEY_ENABLED => '1', DonationBanner::KEY_MESSAGE_FR => 'Gardez & serveurs allumés', DonationBanner::KEY_MESSAGE_EN => 'Keep servers on', DonationBanner::KEY_GOAL_EUR => '300'];
        $dnbAdmin = DonationBanner::adminState($raw, 7500, $now);
        $h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $base = '/admin/paiements';
        ob_start();
        try {
            require base_path('views/admin/system/partials/payments_donation_banner.php');
        } finally {
            $html = (string) ob_get_clean();
        }
        self::assertStringContainsString('id="bandeau-don"', $html);
        self::assertStringContainsString('Affiché en ce moment', $html);
        self::assertStringContainsString('action="/admin/paiements/bandeau-don"', $html);
        self::assertStringContainsString('data-dnb-theme="light"', $html);
        self::assertStringContainsString('data-dnb-theme="dark"', $html);
        self::assertStringContainsString('Gardez &amp; serveurs allumés', $html);
        self::assertStringContainsString('Keep servers on', $html);
        self::assertStringContainsString('aria-valuenow="25"', $html);
        self::assertStringNotContainsString('id="athena-donation-banner"', $html, 'L’aperçu ne doit pas déclencher le script de fermeture du site.');
        self::assertStringContainsString("'payDonationBanner'", (string) file_get_contents(base_path('app/Controllers/Admin/System/SystemPlanPaymentsController.php')));
        self::assertStringContainsString('payments_donation_banner.php', (string) file_get_contents(base_path('views/admin/system/plan_payments.php')));
    }

    public function testSaveRedirectsBackToPayments(): void
    {
        $repo = $this->getMockBuilder(\App\Repositories\PlatformSettingsRepository::class)->disableOriginalConstructor()->getMock();
        $repo->method('get')->willReturn('');
        $repo->expects(self::never())->method('setMany');
        $controller = new \App\Controllers\Admin\System\SystemSettingsController($repo);
        $savedPost = $_POST;
        $_POST = ['_csrf_token' => str_repeat('a', 64), 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-01'];
        try {
            $response = $controller->saveDonationBanner(new \App\Core\Request());
        } finally {
            $_POST = $savedPost;
        }
        self::assertSame(302, $response->statusCode());
        self::assertStringContainsString('admin/paiements?vue=dons#bandeau-don', (string) $response->headerValue('Location'));
    }
}
