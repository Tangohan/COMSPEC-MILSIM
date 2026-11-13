<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\PlatformMessagesController;
use App\Controllers\Admin\System\SystemPlatformBoMessagesController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\PlatformBoMessageRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Platform\PlatformBoMessageService;
use App\Support\PlatformBoMessage;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Messages de la plateforme aux back-offices : assainissement, adresses, ciblage, isolation entre communautés,
 * fermeture et niveau urgent, comptage, droits et jeton CSRF des deux contrôleurs.
 */
final class PlatformBoMessageTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new MysqlDdlSqlitePdo();
        $root = dirname(__DIR__, 2);
        $pdo = $this->pdo;
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        $migrationRunner = null;
        ob_start();
        try {
            require $root . '/database/steps/4240_platform_bo_messages.php';
        } finally {
            ob_end_clean();
        }
        \App\Core\Session::start(); // première ouverture : la session de test est posée ensuite
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
        $_SESSION = [];
        $_POST = [];
    }

    private function repo(): PlatformBoMessageRepository
    {
        return new PlatformBoMessageRepository($this->pdo);
    }

    /** @param array<string, mixed> $over */
    private function make(array $over = []): int
    {
        return $this->repo()->create(array_merge([
            'level' => 'info', 'title_fr' => 'Bonjour', 'title_en' => 'Hello', 'body_fr' => 'Venez sur Discord', 'body_en' => 'Come on Discord',
            'cta_label_fr' => 'Rejoindre', 'cta_label_en' => 'Join', 'cta_url' => 'https://discord.gg/abc', 'starts_at' => null, 'ends_at' => null,
            'is_active' => true, 'target_mode' => 'all', 'target_json' => null,
        ], $over), 1);
    }

    private function auditSpy(): \App\Services\Audit\AuditService
    {
        $spy = new class () extends \App\Services\Audit\AuditService {
            /** @var list<array<int, mixed>> */
            public array $calls = [];

            public function __construct()
            {
            }

            public function logChange(string $action, ?int $tenantId, ?int $userId, ?string $entityType, ?int $entityId, array $oldFields, array $newFields): void
            {
                $this->calls[] = [$action, $tenantId, $userId, $entityType, $entityId, $oldFields, $newFields];
            }
        };

        return $spy;
    }

    /** @return array<string, mixed> */
    private static function tenant(int $id, string $plan = 'free', string $type = 'full'): array
    {
        return ['id' => $id, 'plan_slug' => $plan, 'tenant_type' => $type];
    }

    public function testCleanTextRemovesTagsAndControlCharacters(): void
    {
        $out = PlatformBoMessage::cleanText("<script>alert(1)</script>Salut <b>toi</b>\x00\x07 !", 100);
        self::assertStringNotContainsString('<', $out);
        self::assertStringNotContainsString("\x00", $out);
        self::assertStringContainsString('Salut toi', $out);
        self::assertSame(5, mb_strlen(PlatformBoMessage::cleanText('abcdefghij', 5)));
    }

    public function testSafeUrlAcceptsOnlyHttpAndHttps(): void
    {
        self::assertSame('https://discord.gg/abc', PlatformBoMessage::safeUrl('https://discord.gg/abc'));
        self::assertSame('http://example.org/x?a=1', PlatformBoMessage::safeUrl('http://example.org/x?a=1'));
        foreach (['javascript:alert(1)', 'data:text/html;base64,AAAA', 'ftp://x.org/a', '//evil.org', '/chemin', 'https://u:p@evil.org/', 'https://evil.org/"onmouseover="x', "https://a.org/\nx", 'https://', 'mailto:a@b.fr', ''] as $bad) {
            self::assertNull(PlatformBoMessage::safeUrl($bad), $bad);
        }
        self::assertSame('mailto:a@b.fr', PlatformBoMessage::safeUrl('mailto:a@b.fr', ['mailto']));
        self::assertNull(PlatformBoMessage::safeUrl('mailto:pas un mail', ['mailto']));
    }

    public function testRenderBodyEscapesHtmlAndOnlyLinksValidAddresses(): void
    {
        $html = PlatformBoMessage::renderBody("<img src=x onerror=alert(1)> Voir https://discord.gg/abc. Ou javascript:alert(1) et mailto:a@b.fr\nFin");
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringContainsString('<a href="https://discord.gg/abc" target="_blank" rel="noopener noreferrer nofollow">https://discord.gg/abc</a>.', $html);
        self::assertStringContainsString('href="mailto:a@b.fr"', $html);
        self::assertStringNotContainsString('href="javascript', $html);
        self::assertStringContainsString('<br>', $html);
    }

    public function testLocalizedFallsBackToTheOtherLanguage(): void
    {
        $row = ['title_fr' => 'Titre', 'title_en' => '', 'body_fr' => '', 'body_en' => 'Body'];
        self::assertSame('Titre', PlatformBoMessage::localized($row, 'title', 'en'));
        self::assertSame('Body', PlatformBoMessage::localized($row, 'body', 'fr'));
        self::assertSame('Titre', PlatformBoMessage::localized($row, 'title', 'fr'));
    }

    public function testTargetNormalizationKeepsOnlyKnownValues(): void
    {
        $t = PlatformBoMessage::normalizeTarget('tenants', ['1', '2', '999', 'x'], [], [], [1, 2, 3], ['free'], ['full']);
        self::assertSame([1, 2], $t['values']);
        self::assertNull($t['error']);
        self::assertNotNull(PlatformBoMessage::normalizeTarget('tenants', ['999'], [], [], [1], [], [])['error']);
        self::assertNotNull(PlatformBoMessage::normalizeTarget('plans', [], ['inconnue'], [], [], ['free'], [])['error']);
        self::assertSame(['atak'], PlatformBoMessage::normalizeTarget('types', [], [], ['atak', 'evil'], [], [], ['full', 'atak'])['values']);
        self::assertSame('all', PlatformBoMessage::normalizeTarget('n_importe_quoi', [], [], [], [], [], [])['mode']);
    }

    public function testATenantOnlySeesMessagesThatTargetIt(): void
    {
        $all = $this->make(['title_fr' => 'Pour tous']);
        $one = $this->make(['title_fr' => 'Pour la 2', 'target_mode' => 'tenants', 'target_json' => '[2]']);
        $plan = $this->make(['title_fr' => 'Pour pro', 'target_mode' => 'plans', 'target_json' => '["pro"]']);
        $type = $this->make(['title_fr' => 'Pour atak', 'target_mode' => 'types', 'target_json' => '["atak"]']);
        $svc = new PlatformBoMessageService($this->repo());
        $ids = static fn (array $tenant): array => array_map(static fn (array $i): int => $i['id'], $svc->forTenant($tenant, 10, 'fr'));

        self::assertSame([$all], $ids(self::tenant(1)));
        $two = $ids(self::tenant(2, 'pro', 'atak'));
        sort($two);
        self::assertSame([$all, $one, $plan, $type], $two);
        self::assertNull($svc->findForTenant($one, self::tenant(1)));
        self::assertNotNull($svc->findForTenant($one, self::tenant(2)));
        self::assertSame([], $svc->forTenant(self::tenant(0), 10, 'fr'));
    }

    public function testScheduleAndDeactivationControlVisibility(): void
    {
        $this->make(['title_fr' => 'Futur', 'starts_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $this->make(['title_fr' => 'Passé', 'ends_at' => date('Y-m-d H:i:s', time() - 3600)]);
        $this->make(['title_fr' => 'Éteint', 'is_active' => false]);
        $on = $this->make(['title_fr' => 'En cours', 'starts_at' => date('Y-m-d H:i:s', time() - 60)]);
        $svc = new PlatformBoMessageService($this->repo());
        $items = $svc->forTenant(self::tenant(1), 10, 'fr');
        self::assertSame([$on], array_map(static fn (array $i): int => $i['id'], $items));
    }

    public function testLanguageAndLinkInTheItem(): void
    {
        $id = $this->make(['title_en' => '', 'cta_label_en' => '']);
        $svc = new PlatformBoMessageService($this->repo());
        $en = $svc->forTenant(self::tenant(1), 10, 'en')[0];
        self::assertSame('Bonjour', $en['title']);
        self::assertSame('Rejoindre', $en['cta_label']);
        self::assertSame('https://discord.gg/abc', $en['cta_url']);
        self::assertSame($id, $en['id']);
    }

    public function testDismissHidesForThePersonOnlyAndUrgentCannotBeDismissed(): void
    {
        $info = $this->make();
        $urgent = $this->make(['level' => 'urgent', 'title_fr' => 'Urgent']);
        $svc = new PlatformBoMessageService($this->repo());
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(self::tenant(1));
        $ctl = new PlatformMessagesController($svc, $this->repo(), $tenants, Gate::getInstance());
        $_SESSION = ['user_id' => 10, 'tenant_id' => 1, '_csrf_token' => 'tok'];
        $_POST = ['_csrf_token' => 'tok'];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $req = new Request();

        self::assertSame(200, $ctl->dismiss($req, ['id' => (string) $info])->statusCode());
        self::assertSame(403, $ctl->dismiss($req, ['id' => (string) $urgent])->statusCode());
        $titles = static fn (int $user): array => array_map(static fn (array $i): string => $i['title'], $svc->forTenant(self::tenant(1), $user, 'fr'));
        self::assertSame(['Urgent'], $titles(10));
        self::assertSame(['Urgent', 'Bonjour'], $titles(11));
        $hist = $svc->forTenant(self::tenant(1), 10, 'fr', true);
        self::assertCount(2, $hist);
        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testDismissRejectsAMessageThatDoesNotTargetTheTenantAndBadCsrf(): void
    {
        $other = $this->make(['target_mode' => 'tenants', 'target_json' => '[2]']);
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(self::tenant(1));
        $ctl = new PlatformMessagesController(new PlatformBoMessageService($this->repo()), $this->repo(), $tenants, Gate::getInstance());
        $_SESSION = ['user_id' => 10, 'tenant_id' => 1, '_csrf_token' => 'tok'];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_POST = ['_csrf_token' => 'tok'];
        self::assertSame(404, $ctl->dismiss(new Request(), ['id' => (string) $other])->statusCode());
        $link = $ctl->link(new Request(), ['id' => (string) $other]);
        self::assertStringContainsString('messages-plateforme', (string) $link->headerValue('Location'), 'pas de redirection vers l’adresse du bouton');
        $_POST = ['_csrf_token' => 'faux'];
        self::assertSame(419, $ctl->dismiss(new Request(), ['id' => (string) $other])->statusCode());
        unset($_SERVER['HTTP_ACCEPT']);
        self::assertSame(0, $this->repo()->stats()[$other]['seen'] ?? 0);
    }

    public function testOnlyAManagerCanAcknowledgeAnUrgentMessageForTheWholeCommunity(): void
    {
        $urgent = $this->make(['level' => 'urgent']);
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(self::tenant(1));
        $gate = Gate::getInstance();
        $svc = new PlatformBoMessageService($this->repo());
        $audit = $this->auditSpy();
        $ctl = new PlatformMessagesController($svc, $this->repo(), $tenants, $gate, $audit);
        $_SESSION = ['user_id' => 10, 'tenant_id' => 1, '_csrf_token' => 'tok'];
        $_POST = ['_csrf_token' => 'tok'];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $gate->setPermissions([]);
        self::assertSame(403, $ctl->ack(new Request(), ['id' => (string) $urgent])->statusCode());
        self::assertCount(1, $svc->forTenant(self::tenant(1), 11, 'fr'));

        $gate->setPermissions(['admin.organization']);
        self::assertSame(200, $ctl->ack(new Request(), ['id' => (string) $urgent])->statusCode());
        self::assertSame([], $svc->forTenant(self::tenant(1), 11, 'fr'));
        self::assertCount(1, $svc->forTenant(self::tenant(2), 11, 'fr'), 'une autre communauté garde son message');
        self::assertSame(1, $this->repo()->stats()[$urgent]['acked']);
        self::assertSame(['platform.bo_message_acked', 1, 10], array_slice($audit->calls[0], 0, 3));
        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testStatsCountCommunitiesNotPeopleAndUnreadBadge(): void
    {
        $id = $this->make();
        $svc = new PlatformBoMessageService($this->repo());
        self::assertSame(1, $svc->unreadCount(self::tenant(1), 10)['count']);
        $svc->forTenant(self::tenant(1), 10, 'fr', false, true, false);
        $svc->forTenant(self::tenant(1), 11, 'fr', false, true, false);
        $svc->forTenant(self::tenant(2), 12, 'fr', false, true, false);
        self::assertSame(1, $svc->unreadCount(self::tenant(1), 10)['count'], 'vu sur l’accueil n’est pas ouvert');
        $svc->forTenant(self::tenant(1), 10, 'fr', true, true, true);
        self::assertSame(0, $svc->unreadCount(self::tenant(1), 10)['count']);
        self::assertSame(1, $svc->unreadCount(self::tenant(1), 11)['count']);
        $this->repo()->click($id, 1);
        $this->repo()->click($id, 1);
        $this->repo()->dismiss($id, 1, 10);
        self::assertSame(['seen' => 2, 'clicked' => 1, 'dismissed' => 1, 'acked' => 0], $this->repo()->stats()[$id]);
        $this->repo()->delete($id);
        self::assertSame([], $this->repo()->stats());
    }

    public function testAdminControllerRequiresPlatformAdminAndCsrf(): void
    {
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('listOverviewForPlatform')->willReturn([['id' => 1, 'name' => 'A', 'plan_slug' => 'free', 'tenant_type' => 'full']]);
        $plans = $this->createMock(SubscriptionPlanRepository::class);
        $plans->method('allOrdered')->willReturn([['slug' => 'free', 'name' => 'Gratuit']]);
        $gate = Gate::getInstance();
        $audit = $this->auditSpy();
        $ctl = new SystemPlatformBoMessagesController($this->repo(), $tenants, $plans, $gate, $audit);
        $_SESSION = ['user_id' => 5, '_csrf_token' => 'tok'];
        $_POST = ['_csrf_token' => 'tok', 'title_fr' => 'Salut', 'level' => 'info', 'target_mode' => 'all', 'is_active' => '1'];

        $gate->setPermissions(['admin.organization']);
        $ctl->store(new Request(), []);
        self::assertSame([], $this->repo()->listAll(), 'sans admin.system rien n’est créé');
        self::assertSame('Accès réservé à l’administration du site.', $_SESSION['_flash']['error'] ?? null);

        $gate->setPermissions([]);
        $gate->setPlatformAdmin(true);
        $_POST['_csrf_token'] = 'faux';
        $ctl->store(new Request(), []);
        self::assertSame([], $this->repo()->listAll(), 'sans jeton CSRF rien n’est créé');
        self::assertSame('Session expirée. Réessayez.', $_SESSION['_flash']['error'] ?? null);

        $_POST['_csrf_token'] = 'tok';
        $_POST['title_fr'] = '<script>x</script>Salut';
        $_POST['cta_url'] = 'javascript:alert(1)';
        $_POST['cta_label_fr'] = 'Clic';
        $ctl->store(new Request(), []);
        self::assertSame([], $this->repo()->listAll(), 'adresse javascript: refusée');

        $_POST['cta_url'] = 'https://discord.gg/abc';
        $_POST['target_mode'] = 'tenants';
        $_POST['target_tenants'] = ['999'];
        $ctl->store(new Request(), []);
        self::assertSame([], $this->repo()->listAll(), 'ciblage inconnu refusé');

        $_POST['target_tenants'] = ['1', '999'];
        $ctl->store(new Request(), []);
        $rows = $this->repo()->listAll();
        self::assertCount(1, $rows, json_encode($_SESSION));
        self::assertSame('xSalut', $rows[0]['title_fr'], 'balises retirées');
        self::assertCount(1, $audit->calls);
        self::assertSame(['platform.bo_message_created', null, 5, 'platform_bo_message'], array_slice($audit->calls[0], 0, 4));
        self::assertSame('[1]', $rows[0]['target_json']);
        self::assertSame('https://discord.gg/abc', $rows[0]['cta_url']);
    }

    public function testActiveMessagesAreLimited(): void
    {
        for ($i = 0; $i < PlatformBoMessage::MAX_ACTIVE; $i++) {
            $this->make();
        }
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('listOverviewForPlatform')->willReturn([]);
        $plans = $this->createMock(SubscriptionPlanRepository::class);
        $plans->method('allOrdered')->willReturn([]);
        $gate = Gate::getInstance();
        $gate->setPermissions([]);
        $gate->setPlatformAdmin(true);
        $ctl = new SystemPlatformBoMessagesController($this->repo(), $tenants, $plans, $gate);
        $_SESSION = ['user_id' => 5, '_csrf_token' => 'tok'];
        $_POST = ['_csrf_token' => 'tok', 'title_fr' => 'Onzième', 'level' => 'info', 'target_mode' => 'all', 'is_active' => '1'];
        $ctl->store(new Request(), []);
        self::assertCount(PlatformBoMessage::MAX_ACTIVE, $this->repo()->listAll());
    }

    public function testUpdateToggleDeleteArePerformedAndAudited(): void
    {
        $id = $this->make();
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('listOverviewForPlatform')->willReturn([]);
        $plans = $this->createMock(SubscriptionPlanRepository::class);
        $plans->method('allOrdered')->willReturn([]);
        $gate = Gate::getInstance();
        $gate->setPermissions([]);
        $gate->setPlatformAdmin(true);
        $audit = $this->auditSpy();
        $ctl = new SystemPlatformBoMessagesController($this->repo(), $tenants, $plans, $gate, $audit);
        $_SESSION = ['user_id' => 5, '_csrf_token' => 'tok'];
        $_POST = ['_csrf_token' => 'tok', 'title_fr' => 'Modifié', 'level' => 'urgent', 'target_mode' => 'all', 'is_active' => '1'];
        $ctl->update(new Request(), ['id' => (string) $id]);
        self::assertSame('Modifié', $this->repo()->find($id)['title_fr']);
        self::assertSame('urgent', $this->repo()->find($id)['level']);
        $ctl->toggle(new Request(), ['id' => (string) $id]);
        self::assertSame(0, (int) $this->repo()->find($id)['is_active']);
        $ctl->delete(new Request(), ['id' => (string) $id]);
        self::assertNull($this->repo()->find($id));
        self::assertSame(
            ['platform.bo_message_updated', 'platform.bo_message_toggled', 'platform.bo_message_deleted'],
            array_map(static fn (array $c): string => $c[0], $audit->calls)
        );
    }

    public function testPreviewIsRenderedByTheServerAndEscaped(): void
    {
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('listOverviewForPlatform')->willReturn([]);
        $plans = $this->createMock(SubscriptionPlanRepository::class);
        $plans->method('allOrdered')->willReturn([]);
        $gate = Gate::getInstance();
        $gate->setPermissions([]);
        $gate->setPlatformAdmin(true);
        $ctl = new SystemPlatformBoMessagesController($this->repo(), $tenants, $plans, $gate, $this->auditSpy());
        $_SESSION = ['user_id' => 5, '_csrf_token' => 'tok'];
        $_POST = ['_csrf_token' => 'tok', 'title_fr' => 'Rejoignez-nous', 'body_fr' => '<b onclick=x>Salut</b> https://discord.gg/abc', 'cta_label_fr' => 'Discord', 'cta_url' => 'https://discord.gg/abc', 'level' => 'important'];
        $res = $ctl->preview(new Request(), []);
        self::assertSame(200, $res->statusCode());
        $json = json_decode($res->body(), true);
        self::assertStringNotContainsString('onclick', $json['fr'] . ' ' . $json['en']);
        self::assertStringContainsString('pm-card--important', $json['fr']);
        self::assertStringContainsString('Rejoignez-nous', $json['en'], 'repli sur le français');
        self::assertSame([], $this->repo()->listAll(), 'un aperçu n’enregistre rien');
        $gate->setPlatformAdmin(false);
        self::assertSame(403, $ctl->preview(new Request(), [])->statusCode());
    }

    public function testMigrationCanBeRunTwice(): void
    {
        $root = dirname(__DIR__, 2);
        $pdo = $this->pdo;
        $migrationFlush = static function (): void {
        };
        $migrationEnsurePdo = static function (): void {
        };
        $migrationRunner = null;
        ob_start();
        try {
            require $root . '/database/steps/4240_platform_bo_messages.php';
        } finally {
            ob_end_clean();
        }
        self::assertSame([], $this->repo()->listAll());
    }
}
