<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Auth\AuthController;
use App\Core\Session;
use App\Repositories\InviteLinkRepository;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;
use App\Services\Recruitment\InviteLinkService;
use App\Support\LoginIntendedDestination;
use App\Support\PendingInviteLink;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Liens d'invitation /i/<code> : état (expiration, plafond, révocation), consommation atomique des utilisations,
 * isolement par communauté, code gardé pendant l'inscription et la connexion, limites des formules.
 * Dépôt réel sur sqlite.
 */
final class InviteLinkTest extends TestCase
{
    private const OWN = 7;

    private const OTHER = 3;

    private PDO $pdo;

    private InviteLinkRepository $links;

    private bool $savedStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE recruitment_invite_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, code TEXT NOT NULL, label TEXT,
                code_kind TEXT NOT NULL DEFAULT \'priority\', public_code TEXT UNIQUE, link_action TEXT, requires_approval INTEGER NOT NULL DEFAULT 0,
                max_uses INTEGER, uses_count INTEGER NOT NULL DEFAULT 0, expires_at TEXT, revoked_at TEXT, auto_accept INTEGER NOT NULL DEFAULT 1,
                assign_to_opening_id INTEGER, default_specialty TEXT, metadata_json TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT,
                UNIQUE (tenant_id, code))'
        );
        $this->pdo->exec(
            'CREATE TABLE recruitment_invite_link_events (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, invite_code_id INTEGER NOT NULL,
                event TEXT NOT NULL, user_id INTEGER, enlistment_id INTEGER, ip_hash TEXT, created_at TEXT)'
        );
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, display_name TEXT, email TEXT)');
        $this->links = new InviteLinkRepository($this->pdo);

        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $extra */
    private function make(int $tenantId, string $code, array $extra = []): int
    {
        return $this->links->create($tenantId, $extra + [
            'public_code' => $code,
            'label' => 'Lien ' . $code,
            'link_action' => InviteLinkRepository::ACTION_JOIN,
        ]);
    }

    public function testMigrationExtendsTheCodesTableTwiceSafelyAndKeepsPriorityCodes(): void
    {
        $pdo = new \Tests\Support\MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE recruitment_invite_codes (id INTEGER PRIMARY KEY, tenant_id INTEGER, code TEXT, label TEXT, code_kind TEXT, max_uses INTEGER, uses_count INTEGER, expires_at TEXT, metadata_json TEXT)');
        $pdo->exec("INSERT INTO recruitment_invite_codes (id, tenant_id, code, code_kind, uses_count) VALUES (1, 7, 'MIGRATION2026', 'priority', 4)");
        $pdo->changes = 0;
        require_once dirname(__DIR__, 2) . '/bootstrap/recruitment_invite_links_migration.php';

        ob_start();
        run_recruitment_invite_links_migration($pdo);
        ob_end_clean();
        $schema = new \App\Support\Migrations\SchemaInspector($pdo);
        foreach (['public_code', 'link_action', 'requires_approval', 'revoked_at'] as $col) {
            self::assertTrue($schema->columnExists('recruitment_invite_codes', $col), $col);
        }
        self::assertTrue($schema->indexExists('recruitment_invite_codes', 'uq_invite_public_code'));
        self::assertTrue($schema->tableExists('recruitment_invite_link_events'));

        $pdo->changes = 0;
        ob_start();
        run_recruitment_invite_links_migration($pdo);
        $second = (string) ob_get_clean();
        self::assertSame(0, $pdo->changes, 'second passage sans effet');
        self::assertStringContainsString('déjà installés', $second);
        self::assertSame(['MIGRATION2026', 'priority', '4'], array_map('strval', $pdo->query('SELECT code, code_kind, uses_count FROM recruitment_invite_codes WHERE id = 1')->fetch(PDO::FETCH_NUM)));

        $empty = new \Tests\Support\MysqlDdlSqlitePdo();
        ob_start();
        run_recruitment_invite_links_migration($empty);
        ob_end_clean();
        self::assertSame(0, $empty->changes, 'sans table des codes : rien à faire');
    }

    public function testStatusCoversExpiryUsesAndRevocation(): void
    {
        $now = 1_800_000_000;
        $base = ['revoked_at' => null, 'expires_at' => null, 'max_uses' => null, 'uses_count' => 0];
        self::assertSame(InviteLinkService::STATUS_ACTIVE, InviteLinkService::status($base, $now));
        self::assertSame(InviteLinkService::STATUS_EXPIRED, InviteLinkService::status(['expires_at' => date('Y-m-d H:i:s', $now - 1)] + $base, $now));
        self::assertSame(InviteLinkService::STATUS_ACTIVE, InviteLinkService::status(['expires_at' => date('Y-m-d H:i:s', $now + 60)] + $base, $now));
        self::assertSame(InviteLinkService::STATUS_EXHAUSTED, InviteLinkService::status(['max_uses' => 3, 'uses_count' => 3] + $base, $now));
        self::assertSame(InviteLinkService::STATUS_ACTIVE, InviteLinkService::status(['max_uses' => 3, 'uses_count' => 2] + $base, $now));
        self::assertSame(InviteLinkService::STATUS_REVOKED, InviteLinkService::status(['revoked_at' => '2026-01-01 00:00:00'] + $base, $now));
        foreach ([InviteLinkService::STATUS_EXPIRED, InviteLinkService::STATUS_EXHAUSTED, InviteLinkService::STATUS_REVOKED, 'unknown'] as $s) {
            self::assertNotSame('', InviteLinkService::unusableMessage($s));
        }
    }

    public function testExpiryPresetsAndCustomDate(): void
    {
        $now = 1_800_000_000;
        self::assertNull(InviteLinkService::expiresAtFromInput('never', '', $now));
        $expected = ['30m' => 1800, '1h' => 3600, '6h' => 21600, '12h' => 43200, '1d' => 86400, '7d' => 604800];
        foreach ($expected as $preset => $seconds) {
            self::assertSame(date('Y-m-d H:i:s', $now + $seconds), InviteLinkService::expiresAtFromInput($preset, '', $now), $preset);
        }
        self::assertSame(date('Y-m-d H:i:s', $now + 7200), InviteLinkService::expiresAtFromInput('date', date('Y-m-d\TH:i:s', $now + 7200), $now));
        self::assertFalse(InviteLinkService::expiresAtFromInput('date', date('Y-m-d\TH:i', $now - 60), $now), 'date passée refusée');
        self::assertFalse(InviteLinkService::expiresAtFromInput('date', '', $now));
        self::assertFalse(InviteLinkService::expiresAtFromInput('3ans', '', $now));
    }

    public function testGeneratedCodesAreLongRandomAndAvoidTakenOnes(): void
    {
        $seen = [];
        for ($i = 0; $i < 50; $i++) {
            $code = InviteLinkService::generateCode(static fn (): bool => false);
            self::assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{10}$/', $code);
            $seen[$code] = true;
        }
        self::assertCount(50, $seen, 'aucune collision sur 50 tirages');
        $calls = 0;
        $code = InviteLinkService::generateCode(static function () use (&$calls): bool {
            return ++$calls < 3;
        });
        self::assertSame(3, $calls, 'un code déjà pris est retiré');
        self::assertTrue(InviteLinkService::isWellFormedCode($code));
    }

    public function testCustomSlugAndPastedLinks(): void
    {
        self::assertSame('tfj', InviteLinkService::normalizeCustomSlug(' TFJ '));
        self::assertSame('task-force-jaguar', InviteLinkService::normalizeCustomSlug('task-force-jaguar'));
        foreach (['ab', '-tfj', 'tfj-', 'a--b', 'tf j', 'tfj/x', str_repeat('a', 41), 'éte'] as $bad) {
            self::assertNull(InviteLinkService::normalizeCustomSlug($bad), $bad);
        }
        self::assertSame('tfj', InviteLinkService::codeFromPastedLink('https://athena-comspec.eu/public/i/TFJ'));
        self::assertSame('abc23', InviteLinkService::codeFromPastedLink('i/abc23?x=1'));
        self::assertNull(InviteLinkService::codeFromPastedLink('UNIT-ALPHA'));
        self::assertNull(InviteLinkService::codeFromPastedLink('https://evil.example/c/tfj'));
    }

    public function testConsumeUseStopsAtMaxUsesAndCanBeReleased(): void
    {
        $id = $this->make(self::OWN, 'deuxfois', ['max_uses' => 2]);
        self::assertTrue($this->links->consumeUse(self::OWN, $id));
        self::assertTrue($this->links->consumeUse(self::OWN, $id));
        self::assertFalse($this->links->consumeUse(self::OWN, $id), 'troisième utilisation refusée');
        $row = $this->links->findById(self::OWN, $id);
        self::assertSame(2, $row['uses_count']);
        self::assertSame(InviteLinkService::STATUS_EXHAUSTED, InviteLinkService::status($row));
        self::assertSame(0, $this->links->countActiveForTenant(self::OWN));

        $this->links->releaseUse(self::OWN, $id);
        self::assertTrue($this->links->consumeUse(self::OWN, $id), 'utilisation rendue après un échec');
    }

    public function testExpiredAndRevokedLinksCannotBeUsedAndReactivationRestoresTheSameAddress(): void
    {
        $expired = $this->make(self::OWN, 'perime', ['expires_at' => date('Y-m-d H:i:s', time() - 5)]);
        self::assertFalse($this->links->consumeUse(self::OWN, $expired));
        self::assertSame(InviteLinkService::STATUS_EXPIRED, InviteLinkService::status($this->links->findByPublicCode('perime')));

        $id = $this->make(self::OWN, 'revoque');
        self::assertTrue($this->links->revoke(self::OWN, $id));
        self::assertFalse($this->links->consumeUse(self::OWN, $id));
        self::assertSame(InviteLinkService::STATUS_REVOKED, InviteLinkService::status($this->links->findByPublicCode('revoque')));

        self::assertTrue($this->links->reactivate(self::OWN, $id, date('Y-m-d H:i:s', time() + 3600)));
        $row = $this->links->findByPublicCode('revoque');
        self::assertSame(InviteLinkService::STATUS_ACTIVE, InviteLinkService::status($row));
        self::assertSame($id, $row['id']);
        self::assertTrue($this->links->consumeUse(self::OWN, $id));
    }

    public function testEveryBackOfficeOperationIsScopedToTheCommunity(): void
    {
        $foreign = $this->make(self::OTHER, 'autre-commu');
        $this->make(self::OWN, 'la-notre');

        self::assertNull($this->links->findById(self::OWN, $foreign));
        self::assertFalse($this->links->revoke(self::OWN, $foreign));
        self::assertFalse($this->links->reactivate(self::OWN, $foreign, null));
        self::assertFalse($this->links->consumeUse(self::OWN, $foreign));
        self::assertFalse($this->links->update(self::OWN, $foreign, [
            'label' => 'Piratage', 'link_action' => 'join', 'requires_approval' => false, 'max_uses' => null,
            'expires_at' => null, 'opening_id' => null, 'metadata' => [],
        ]));
        self::assertSame(['la-notre'], array_column($this->links->listForTenant(self::OWN), 'public_code'));
        self::assertSame('Lien autre-commu', $this->links->findByPublicCode('autre-commu')['label'], 'rien modifié');
        self::assertNull($this->links->findByPublicCode('autre-commu')['revoked_at']);

        // La résolution publique donne la communauté du lien, quel que soit le visiteur.
        self::assertSame(self::OTHER, $this->links->findByPublicCode('AUTRE-COMMU')['tenant_id']);
        $this->links->recordEvent(self::OTHER, $foreign, InviteLinkRepository::EVENT_CLICK, null, null, '203.0.113.9');
        self::assertSame([], $this->links->statsByLinkForTenant(self::OWN));
        self::assertSame(1, $this->links->statsByLinkForTenant(self::OTHER)[$foreign]['click']);
        $hash = (string) $this->pdo->query('SELECT ip_hash FROM recruitment_invite_link_events')->fetchColumn();
        self::assertSame(64, strlen($hash));
        self::assertStringNotContainsString('203.0.113.9', $hash, 'l’adresse IP n’est jamais stockée');
    }

    public function testLinksNeverClashWithPriorityCodesOrEachOther(): void
    {
        $this->pdo->exec("INSERT INTO recruitment_invite_codes (tenant_id, code, code_kind) VALUES (7, 'TFJ', 'priority')");
        $id = $this->make(self::OWN, 'tfj');
        self::assertGreaterThan(0, $id, 'le lien « tfj » coexiste avec le code prioritaire « TFJ »');
        self::assertSame('LIEN-TFJ', $this->links->findById(self::OWN, $id)['code']);
        self::assertTrue($this->links->publicCodeTaken('TFJ'));
        $this->expectException(\PDOException::class);
        $this->make(self::OTHER, 'tfj');
    }

    public function testStatsCountEachKindOfUse(): void
    {
        $id = $this->make(self::OWN, 'stats');
        foreach (['click', 'click', 'signup', 'application', 'join', 'request', 'bidon'] as $event) {
            $this->links->recordEvent(self::OWN, $id, $event, 5);
        }
        self::assertSame(['click' => 2, 'signup' => 1, 'application' => 1, 'join' => 1, 'request' => 1], $this->links->statsByLinkForTenant(self::OWN)[$id]);
        self::assertCount(4, $this->links->recentUsesForLink(self::OWN, $id), 'les clics ne sont pas listés');
    }

    public function testCodeIsKeptThroughRegistrationAndLoginThenLeadsBackToTheLink(): void
    {
        self::assertNull(PendingInviteLink::code());
        PendingInviteLink::remember('TFJ');
        self::assertSame('tfj', PendingInviteLink::code());
        self::assertSame('tfj', PendingInviteLink::fromParamOrSession(''), 'formulaire renvoyé après une erreur : code de la session');
        self::assertSame('autre', PendingInviteLink::fromParamOrSession('autre'), 'le paramètre prime');
        self::assertSame('tfj', PendingInviteLink::fromParamOrSession('../x'), 'paramètre mal formé ignoré');

        // Le lien de confirmation de l'adresse renvoie vers la connexion avec retour sur la page du lien.
        self::assertSame('i/tfj', LoginIntendedDestination::suffixFromRedirect('i/tfj'));

        // Connexion sans adresse de retour : la session ramène sur le lien.
        $auth = (new \ReflectionClass(AuthController::class))->newInstanceWithoutConstructor();
        $resolve = new \ReflectionMethod(AuthController::class, 'resolvePostLoginContinueUrl');
        self::assertSame(url('i/tfj'), $resolve->invoke($auth));

        // Une adresse de retour explicite garde la priorité.
        LoginIntendedDestination::rememberSuffix('evenements');
        self::assertSame(url('evenements'), $resolve->invoke($auth));

        // Délai dépassé : le code est oublié.
        $_SESSION['pending_invite_link']['expires_at'] = time() - 1;
        self::assertNull(PendingInviteLink::code());
        PendingInviteLink::remember('bad code!');
        self::assertNull(PendingInviteLink::code(), 'code mal formé jamais gardé');
    }

    public function testApplicationFormOnlyAcceptsAnActiveApplyLinkOfItsOwnCommunity(): void
    {
        $this->make(self::OWN, 'candidater', ['link_action' => InviteLinkRepository::ACTION_APPLY, 'metadata' => ['registration_mode' => 'simple']]);
        $this->make(self::OTHER, 'ailleurs', ['link_action' => InviteLinkRepository::ACTION_APPLY]);
        $this->make(self::OWN, 'adhesion', ['link_action' => InviteLinkRepository::ACTION_JOIN]);
        $this->make(self::OWN, 'fini', ['link_action' => InviteLinkRepository::ACTION_APPLY, 'expires_at' => date('Y-m-d H:i:s', time() - 1)]);

        $instances = new \ReflectionProperty(\App\Core\Container::class, 'instances');
        $saved = $instances->getValue();
        $instances->setValue(null, array_merge(is_array($saved) ? $saved : [], [InviteLinkRepository::class => $this->links]));
        try {
            $ctrl = (new \ReflectionClass(\App\Controllers\Web\EnlistmentController::class))->newInstanceWithoutConstructor();
            $resolve = new \ReflectionMethod($ctrl, 'resolveInviteLink');
            $tenant = ['id' => self::OWN, 'slug' => 'notre'];
            self::assertSame('candidater', $resolve->invoke($ctrl, 'CANDIDATER', $tenant)['public_code']);
            self::assertNull($resolve->invoke($ctrl, 'ailleurs', $tenant), 'lien d’une autre communauté');
            self::assertNull($resolve->invoke($ctrl, 'adhesion', $tenant), 'lien d’adhésion');
            self::assertNull($resolve->invoke($ctrl, 'fini', $tenant), 'lien expiré');
            self::assertNull($resolve->invoke($ctrl, '', $tenant));

            $active = new \ReflectionProperty($ctrl, 'activeInviteLink');
            $active->setValue($ctrl, $resolve->invoke($ctrl, 'candidater', $tenant));
            $mode = new \ReflectionMethod($ctrl, 'applyInviteLinkMode');
            self::assertSame('simple', $mode->invoke($ctrl, ['registration_mode' => 'milsim'])['registration_mode'], 'type de candidature du lien');
            $active->setValue($ctrl, null);
            self::assertSame('milsim', $mode->invoke($ctrl, ['registration_mode' => 'milsim'])['registration_mode']);
        } finally {
            $instances->setValue(null, $saved);
        }
    }

    public function testPlanLimitsFreeTwoActiveLinksPaidUnlimitedWithCustomAddress(): void
    {
        self::assertContains(InviteLinkService::FEATURE_MAX_ACTIVE, SubscriptionPlanFeaturesCatalog::INT_FEATURES);
        self::assertContains(InviteLinkService::FEATURE_CUSTOM_SLUG, SubscriptionPlanFeaturesCatalog::BOOL_FEATURES);
        $free = SubscriptionPlanFeaturesCatalog::defaultsForSlug('free');
        self::assertSame(2, $free[InviteLinkService::FEATURE_MAX_ACTIVE]);
        self::assertFalse($free[InviteLinkService::FEATURE_CUSTOM_SLUG]);
        foreach (['standard', 'pro', 'pro_plus'] as $slug) {
            $f = SubscriptionPlanFeaturesCatalog::defaultsForSlug($slug);
            self::assertSame(0, $f[InviteLinkService::FEATURE_MAX_ACTIVE], $slug);
            self::assertTrue($f[InviteLinkService::FEATURE_CUSTOM_SLUG], $slug);
        }
        self::assertTrue(InviteLinkService::withinQuota(2, 1));
        self::assertFalse(InviteLinkService::withinQuota(2, 2));
        self::assertFalse(InviteLinkService::withinQuota(2, 3), 'au-delà après un changement de formule : pas de nouveau lien');
        self::assertTrue(InviteLinkService::withinQuota(0, 500));

        // Les liens au-delà de la limite restent utilisables : la limite ne joue qu'à la création.
        $ids = [$this->make(self::OWN, 'un'), $this->make(self::OWN, 'deux'), $this->make(self::OWN, 'trois')];
        self::assertSame(3, $this->links->countActiveForTenant(self::OWN));
        foreach ($ids as $id) {
            self::assertTrue($this->links->consumeUse(self::OWN, $id));
        }
    }

    public function testBackOfficePagesLoadAthStylesheets(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/RecruitmentInviteLinksController.php');
        self::assertStringContainsString("'recruitmentAthUi' => true", $ctrl, 'sans ce drapeau, back-office-shell.css n’est pas chargé');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-shell.css')
            . (string) file_get_contents($root . '/public/assets/css/recruitment_offers_ath.css');
        $views = glob($root . '/views/admin/organization/recruitment_invite_links/*.php') ?: [];
        self::assertNotEmpty($views);
        foreach ($views as $view) {
            preg_match_all('/class="([^"<]*)"/', (string) file_get_contents($view), $m);
            foreach ($m[1] as $list) {
                foreach (preg_split('/\s+/', trim($list)) ?: [] as $cls) {
                    if (str_starts_with($cls, 'ath-')) {
                        self::assertMatchesRegularExpression('/\.' . preg_quote($cls, '/') . '\b/', $css, basename($view) . ' : ' . $cls);
                    }
                }
            }
        }
    }

    public function testInviteLinkPagesUseTheirDedicatedStylesheet(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/RecruitmentInviteLinksController.php');
        self::assertStringContainsString('invite-links-ath.css', $ctrl, 'feuille des liens chargée dans le back-office');
        $landing = (string) file_get_contents($root . '/views/invite_link/landing.php');
        self::assertStringContainsString('invite-links-ath.css', $landing);
        $css = (string) file_get_contents($root . '/public/assets/css/invite-links-ath.css');
        self::assertStringContainsString('prefers-color-scheme: dark', $css, 'page publique en clair et en sombre');
        foreach (['views/invite_link/landing.php', 'views/admin/organization/recruitment_invite_links/show.php'] as $view) {
            preg_match_all('/class="([^"]*)"/', (string) file_get_contents($root . '/' . $view), $m);
            foreach ($m[1] as $list) {
                foreach (preg_split('/[\s<>?=$]+/', $list) ?: [] as $cls) {
                    if (preg_match('/^ilx-[a-z0-9_-]+$/', $cls)) {
                        self::assertMatchesRegularExpression('/\.' . preg_quote($cls, '/') . '\b/', $css, $view . ' : ' . $cls);
                    }
                }
            }
        }
    }
}
