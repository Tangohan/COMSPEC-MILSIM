<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Contrôles de structure de la page Niveaux d’accès : CSRF, communauté jamais lue dans la requête, anti-IDOR, dialogues. */
final class EffectifsRolesSecurityTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/EffectifsWorkspaceController.php');
    }

    private function method(string $name): string
    {
        $src = $this->controller();
        $start = strpos($src, 'function ' . $name . '(');
        self::assertNotFalse($start, $name);
        $end = strpos($src, "\n    public function ", $start + 10);

        return substr($src, (int) $start, $end === false ? 6000 : $end - (int) $start);
    }

    public function testEveryWriteGoesThroughCsrfAndPermissionGuard(): void
    {
        self::assertStringContainsString('Csrf::verify($request)', $this->method('denyAccessWrite'));
        self::assertStringContainsString('canManageRoles', $this->method('denyAccessWrite'));
        foreach (['createAccessRole', 'saveAccessRole', 'restoreAccessRole', 'deleteAccessRole', 'showAccessRole'] as $m) {
            self::assertStringContainsString('$this->denyAccessWrite($request', $this->method($m), $m);
        }
        $preview = $this->method('previewAccessRole');
        self::assertStringContainsString('Csrf::verify($request)', $preview);
        self::assertStringContainsString('canManageRoles', $preview);
    }

    public function testCommunityIsNeverReadFromTheRequest(): void
    {
        foreach (['roles', 'createAccessRole', 'saveAccessRole', 'previewAccessRole', 'restoreAccessRole', 'deleteAccessRole', 'showAccessRole'] as $m) {
            $body = $this->method($m);
            self::assertStringContainsString("Session::get('tenant_id')", $body, $m);
            self::assertStringNotContainsString("input('tenant_id'", $body, $m);
            self::assertStringNotContainsString("query('tenant_id'", $body, $m);
        }
    }

    public function testRoleIdsAreCheckedAgainstTheCommunity(): void
    {
        self::assertStringContainsString('canAssignInTenantAdminContext($roleId, $tenantId)', $this->method('saveAccessRole'));
        self::assertStringContainsString('findById($roleId, $tenantId)', $this->method('deleteAccessRole'));
        self::assertStringContainsString('findById($roleId, $tenantId)', $this->method('restoreAccessRole'));
        self::assertStringContainsString('isset($snapshot[\'perms\'][$copyId])', $this->method('createAccessRole'));
        self::assertStringContainsString('forTenantAccessProfiles($tenantId, true)', $this->method('previewAccessRole'));
        self::assertStringContainsString('Niveau introuvable', $this->method('previewAccessRole'));
        self::assertStringContainsString('listForTenant($tenantId', $this->method('accessRoleHistory'));
    }

    public function testNoEscalationAndLastAdminGuardsAreWired(): void
    {
        foreach (['createAccessRole', 'saveAccessRole', 'restoreAccessRole'] as $m) {
            self::assertStringContainsString('AccessRoleRules::escalation(', $this->method($m), $m);
        }
        foreach (['saveAccessRole', 'restoreAccessRole', 'deleteAccessRole'] as $m) {
            self::assertStringContainsString('AccessRoleRules::removesLastAdmin(', $this->method($m), $m);
        }
    }

    public function testRoutesKeepOrganizationAdminMiddleware(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach (['', '/apercu', '/nouveau', '/retablir', '/supprimer', '/reafficher'] as $suffix) {
            $verb = $suffix === '' ? '(?:get|post)' : 'post';
            $re = '#\$router->' . $verb . "\\('/back-office/ressources/effectifs/roles" . preg_quote($suffix, '#') . "',[^;]*OrganizationAdminMiddleware::class\\]\\);#";
            self::assertMatchesRegularExpression($re, $routes, 'route roles' . $suffix);
        }
    }

    public function testViewUsesWebDialogsAndNoEyebrow(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roles.php');
        $js = (string) file_get_contents($root . '/public/assets/js/effectifs-roles.js');
        self::assertStringNotContainsString('onsubmit=', $view);
        self::assertStringNotContainsString('confirm(', $view);
        self::assertDoesNotMatchRegularExpression('/\bconfirm\(/', $js);
        self::assertStringContainsString('data-ui-confirm="1"', $view);
        self::assertStringNotContainsString('eff-page-kicker', $view);
        self::assertStringNotContainsString('eyebrow', $view);
        self::assertStringContainsString('_csrf_token', $view);
    }
}
