<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\TenantAdminInsights;
use App\Services\Admin\TenantEditRules;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditActionLabel;
use PHPUnit\Framework\TestCase;

/** Fiche communauté (administration du site) : onglets, écritures par section, diagnostic sans secret. */
final class PlatformTenantEditionTest extends TestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testTabsAreWhitelisted(): void
    {
        self::assertSame('resume', TenantEditRules::tab(null));
        self::assertSame('resume', TenantEditRules::tab('../../etc/passwd'));
        self::assertSame('resume', TenantEditRules::tab(['diagnostic']));
        foreach (TenantEditRules::TABS as $t) {
            self::assertSame($t, TenantEditRules::tab($t));
        }
    }

    public function testLocaleTimezoneAndIdValidation(): void
    {
        self::assertSame('en', TenantEditRules::locale('en-GB'));
        self::assertNull(TenantEditRules::locale('xx'));
        self::assertSame('fr', TenantEditRules::locale("fr'; DROP"), 'seul un code de la liste blanche sort');
        self::assertSame('Europe/Paris', TenantEditRules::timezone('Europe/Paris'));
        self::assertNull(TenantEditRules::timezone('Mars/Olympus'));
        self::assertNull(TenantEditRules::timezone(''));
        self::assertSame(42, TenantEditRules::positiveId('42'));
        self::assertNull(TenantEditRules::positiveId('0'));
        self::assertNull(TenantEditRules::positiveId('-3'));
        self::assertNull(TenantEditRules::positiveId('4 OR 1=1'));
        self::assertNull(TenantEditRules::positiveId(['4']));
    }

    public function testRedactionRemovesSecretsAndEmails(): void
    {
        $raw = 'SMTP refusé pour jean.dupont@example.org avec password=hunter2 et token: abcd1234 https://user:pass@host/x '
            . 'clé ' . str_repeat('A1b2', 12);
        $out = TenantEditRules::redact($raw, 400);
        self::assertStringNotContainsString('jean.dupont', $out);
        self::assertStringNotContainsString('hunter2', $out);
        self::assertStringNotContainsString('abcd1234', $out);
        self::assertStringNotContainsString('user:pass', $out);
        self::assertStringNotContainsString(str_repeat('A1b2', 12), $out);
        self::assertLessThanOrEqual(30, mb_strlen(TenantEditRules::redact(str_repeat('mot ', 40), 30)));
    }

    public function testReportTextIsPlainAndRedacted(): void
    {
        $text = TenantEditRules::reportText([
            'title' => 'Diagnostic communauté n° 7',
            'generated' => '09/11/2026 10:00',
            'sections' => [['title' => 'E-mails', 'lines' => ['échec pour a@b.fr : secret=xyz', 'ok']]],
        ]);
        self::assertStringContainsString("== E-mails ==\n- échec pour [e-mail] : secret=[masqué]\n- ok", $text);
        self::assertStringNotContainsString('a@b.fr', $text);
        self::assertStringNotContainsString('<', $text);
    }

    public function testPlanLimitsSeparateNumbersFromFlagsAndDetectReachedCap(): void
    {
        $plan = ['features_json' => json_encode(['max_members' => 10, 'atak' => true, 'discord' => false, 'bad key!' => 1, 'x' => 'texte'])];
        $out = TenantAdminInsights::planLimits($plan, ['active' => 10]);
        self::assertSame(['atak' => true, 'discord' => false], $out['flags']);
        self::assertCount(1, $out['limits']);
        self::assertSame(10, $out['limits'][0]['limit']);
        self::assertTrue($out['limits'][0]['reached']);
        self::assertSame([], TenantAdminInsights::planLimits(null, [])['limits']);
        self::assertFalse(TenantAdminInsights::planLimits(['features_json' => '{"max_members":0}'], ['active' => 99])['limits'][0]['reached'], '0 = illimité');
    }

    public function testPlanStatusCountsDaysLeft(): void
    {
        $now = strtotime('2026-11-09 12:00:00');
        $tenant = ['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => json_encode(['plan_gift' => ['plan_slug' => 'pro', 'ends_at' => '2026-11-19 12:00:00']])];
        $base = TenantAdminInsights::planStatus(['plan_slug' => 'free', 'subscription_status' => 'none', 'settings' => null], $now);
        self::assertSame('base', $base['kind']);
        self::assertNull($base['days_left']);
        self::assertContains(TenantAdminInsights::planStatus($tenant, $now)['kind'], ['gift', 'base']);
    }

    public function testNewWritesAreCsrfAuditedAndPostOnly(): void
    {
        $routes = $this->src('routes/web.php');
        foreach (['langue' => 'updateLocale', 'contact' => 'updateOwner', 'logo' => 'removeLogo'] as $url => $method) {
            self::assertStringContainsString("\$router->post('/admin/tenants/{id}/{$url}', [SystemTenantsController::class, '{$method}'], [AuthMiddleware::class, SystemAdminMiddleware::class]);", $routes);
            self::assertStringNotContainsString("get('/admin/tenants/{id}/{$url}'", $routes);
        }
        $ctl = $this->src('app/Controllers/Admin/System/SystemTenantsController.php');
        // Droit revérifié : chaque action publique commence par guard() (sauf index/constructeur).
        preg_match_all('/public function (edit|updateIdentity|updateType|updatePlan|removal|archive|restore|destroy|updateLocale|updateOwner|removeLogo)\(.*?\r?\n    \{\r?\n(.*?)\r?\n/s', $ctl, $m, PREG_SET_ORDER);
        self::assertCount(11, $m);
        foreach ($m as $hit) {
            if (!in_array($hit[1], ['updateLocale', 'updateOwner', 'removeLogo'], true)) {
                self::assertStringContainsString('guard()', $hit[2], $hit[1]);
            }
        }
        self::assertStringContainsString("deny('admin.system')", $ctl);
        self::assertStringContainsString('Csrf::validate', $ctl);
        foreach (['TENANT_LOCALE_UPDATED', 'TENANT_OWNER_UPDATED', 'TENANT_LOGO_REMOVED'] as $const) {
            self::assertStringContainsString('AuditAction::' . $const, $ctl);
            $value = constant(AuditAction::class . '::' . $const);
            self::assertNotSame($value, AuditActionLabel::toFrench($value), 'libellé français attendu pour ' . $value);
        }
        // L'identifiant de communauté vient de la route, jamais du corps de la requête.
        self::assertStringNotContainsString("input('tenant_id", $ctl);
        self::assertStringNotContainsString("input('id'", $ctl);
        // Propriétaire : compte actif de CETTE communauté seulement.
        self::assertStringContainsString('isActiveMember($tenantId, $owner)', $ctl);
        // Retrait du logo : confirmation obligatoire.
        self::assertStringContainsString('confirm_logo_removal', $ctl);
    }

    public function testInsightsQueriesArePreparedAndTenantScoped(): void
    {
        $svc = $this->src('app/Services/Admin/TenantAdminInsights.php');
        self::assertStringNotContainsString('$_GET', $svc);
        self::assertStringNotContainsString('$_POST', $svc);
        self::assertStringNotContainsString('->query(', $svc);
        self::assertStringNotContainsString('->exec(', $svc);
        preg_match_all("/(?:scalar|rows)\(\s*['\"](SELECT[^'\"]*)/s", $svc, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $sql) {
            // Seules les tâches planifiées (journal commun de la plateforme) n'ont pas de tenant_id.
            if (str_contains($sql, 'cron_job_runs')) {
                self::assertStringNotContainsString('payload', $sql);
                continue;
            }
            self::assertMatchesRegularExpression('/tenant_id\s*=\s*\?/', $sql, $sql);
        }
        // Aucun secret lu : pas de colonne de jeton, de hash ni de clé.
        foreach (['key_hash', 'token_hash', 'password', 'webhook', 'secret', 'api_key_plain'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $svc, $forbidden);
        }
        // Courriels : ni destinataire ni sujet.
        self::assertStringNotContainsString('recipient', $svc);
        self::assertStringNotContainsString('subject', $svc);
    }

    public function testViewsStayFreeOfSecretsAndKnownToTheSkinTest(): void
    {
        foreach (['resume', 'edition', 'membres', 'diagnostic'] as $tab) {
            $view = $this->src('views/admin/system/tenant_edit/' . $tab . '.php');
            self::assertStringNotContainsString('webhook', strtolower($view), $tab);
            self::assertStringNotContainsString('key_hash', $view, $tab);
            self::assertStringNotContainsString('$_GET', $view, $tab);
            self::assertStringNotContainsString('$nav', $view, $tab);
        }
        $edition = $this->src('views/admin/system/tenant_edit/edition.php') . $this->src('views/admin/system/tenant_edit/_forms_legacy.php');
        // Chaque formulaire d'écriture embarque le jeton CSRF.
        self::assertSame(substr_count($edition, '<form'), substr_count($edition, 'Csrf::field()'));
        $main = $this->src('views/admin/system/tenants_plan_form.php');
        self::assertStringContainsString('pa-tabs', $main);
        self::assertStringContainsString("in_array", $this->src('app/Services/Admin/TenantEditRules.php'));
        self::assertStringContainsString("isset(\$tabLabels[\$tab])", $main);
        $diag = $this->src('views/admin/system/tenant_edit/diagnostic.php');
        self::assertStringContainsString('Copier le rapport de diagnostic', $diag);
        self::assertStringContainsString('reportText', $diag);
    }

    public function testMembersTabReusesTheAccountDefinitionWithWhitelistedSort(): void
    {
        $ctl = $this->src('app/Controllers/Admin/System/SystemTenantsController.php');
        self::assertStringContainsString('SystemTenantAccountsController::SORTS', $ctl);
        self::assertStringContainsString('normalizePlatformAccountFilter', $ctl);
        self::assertStringContainsString('mb_substr(trim((string) $request->query(\'q\', \'\')), 0, 100)', $ctl);
        self::assertSame(20, TenantEditRules::MEMBER_PER_PAGE);
    }
}
