<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Régression : /back-office/analytics nommait des joueurs d’autres communautés.
 * Les événements d’audience portent le tenant de la page consultée (fiche publique, recrutement,
 * formations) mais l’acteur est n’importe quel compte connecté : seuls les membres du tenant
 * peuvent être nommés ou comptés comme « membres ».
 */
final class AnalyticsTenantMemberScopeTest extends TestCase
{
    private function method(string $name): string
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/TenantAnalyticsRepository.php');
        $start = strpos($src, 'function ' . $name . '(');
        self::assertNotFalse($start, $name . ' introuvable');
        $next = strpos($src, "\n    public function ", $start + 1);
        $nextPrivate = strpos($src, "\n    private function ", $start + 1);
        $ends = array_filter([$next, $nextPrivate], static fn ($v) => $v !== false);

        return substr($src, $start, ($ends === [] ? strlen($src) : min($ends)) - $start);
    }

    public function testTopActorsOnlyNameMembersOfTheTenant(): void
    {
        $body = $this->method('listTenantTopActors');
        self::assertStringContainsString('INNER JOIN users u ON u.id = e.actor_user_id', $body);
        self::assertStringNotContainsString('LEFT JOIN users u', $body);
        self::assertStringContainsString("memberOfTenantPredicate('u', \$tenantId)", $body);
        self::assertStringContainsString('e.tenant_id = ?', $body);
    }

    public function testUsageSummarySeparatesMembersFromOtherCommunities(): void
    {
        $body = $this->method('getTenantUsageSummary');
        self::assertStringContainsString("memberOfTenantPredicate('u', \$tenantId)", $body);
        self::assertStringContainsString("'external_actors'", $body);
    }

    public function testMemberPredicateReusesPersonnelDirectoryRule(): void
    {
        $body = $this->method('memberOfTenantPredicate');
        self::assertStringContainsString('sqlMemberOfTenantPredicate(', $body);
    }

    public function testAnalyticsQueriesNeverTakeTenantFromRequest(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/OrganizationAnalyticsController.php');
        self::assertStringContainsString("\$tenantId = (int) Session::get('tenant_id');", $src);
        self::assertStringNotContainsString("query('tenant", $src);
        self::assertStringNotContainsString("input('tenant", $src);
    }

    public function testViewNeverCallsOtherCommunitiesMembers(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/analytics.php');
        self::assertStringNotContainsString('personnes distinctes', $view);
        self::assertStringContainsString("external_actors", $view);
    }

    /**
     * Même famille sur /back-office/tableau-operationnel : un identifiant d’utilisateur saisi à la main
     * (chef, adjoint, remplaçant, personnel) affichait le nom d’un compte d’une autre communauté.
     */
    public function testOperationalBoardOnlyAcceptsMembersAsPeople(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/OperationalBoardController.php');
        self::assertStringNotContainsString("\$this->nullableInt(\$request->input('chief_user_id'", $src);
        self::assertStringNotContainsString("\$this->nullableInt(\$request->input('deputy_user_id'", $src);
        self::assertStringNotContainsString("\$this->nullableInt(\$request->input('replacement_user_id'", $src);
        self::assertSame(6, substr_count($src, "\$this->memberUserIdOrNull(\$request->input("));
        self::assertStringContainsString('$this->users->isMemberOfTenant($id, $tenantId)', $src);
        self::assertStringContainsString('!$this->users->isMemberOfTenant($uid, $tenantId)', $src);
    }
}
