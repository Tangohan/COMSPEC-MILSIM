<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MemberIntegration\MemberIntegrationInvitationService;
use App\Support\MemberIntegrationCatalog;
use PHPUnit\Framework\TestCase;

final class MemberIntegrationAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testRoutesAndViewsExist(): void
    {
        $routes = (string) file_get_contents($this->root() . '/routes/web.php');
        self::assertStringContainsString('/back-office/integration-membres', $routes);
        self::assertStringContainsString('/mon-integration', $routes);
        self::assertStringContainsString('/integration/invitation/repondre', $routes);
        self::assertStringContainsString('MemberIntegrationAdminController', $routes);
        self::assertFileExists($this->root() . '/views/admin/member_integration/index.php');
        self::assertFileExists($this->root() . '/views/admin/member_integration/show.php');
        self::assertFileExists($this->root() . '/views/admin/member_integration/templates.php');
        self::assertFileExists($this->root() . '/views/member_integration/index.php');
        self::assertFileExists($this->root() . '/views/member_integration/respond.php');
        self::assertFileExists($this->root() . '/migrations/20260901000001_member_integration.sql');
        self::assertFileExists($this->root() . '/bootstrap/member_integration_migration.php');
        self::assertFileExists($this->root() . '/scripts/member-integration-backfill.php');
    }

    public function testRepositoriesAlwaysFilterTenant(): void
    {
        foreach ([
            '/app/Repositories/MemberIntegrationRepository.php',
            '/app/Repositories/MemberIntegrationTemplateRepository.php',
            '/app/Repositories/MemberIntegrationAppointmentRepository.php',
        ] as $rel) {
            $src = (string) file_get_contents($this->root() . $rel);
            self::assertGreaterThan(3, substr_count($src, 'tenant_id'));
            self::assertStringContainsString('WHERE', $src);
        }
    }

    public function testPermissionsAndRoleMappingAreDeclared(): void
    {
        $perms = (string) file_get_contents($this->root() . '/app/Authorization/TenantPermissionCatalog.php');
        foreach ([
            'member_integration.view',
            'member_integration.manage',
            'member_integration.assign',
            'member_integration.note',
            'member_integration.template_manage',
        ] as $slug) {
            self::assertStringContainsString($slug, $perms);
        }
        $roles = (string) file_get_contents($this->root() . '/app/Services/Community/TenantDefaultRoleDefinitions.php');
        self::assertStringContainsString('member_integration.view', $roles);
        $mw = (string) file_get_contents($this->root() . '/app/Middleware/OrganizationAdminMiddleware.php');
        self::assertStringContainsString('/back-office/integration-membres', $mw);
    }

    public function testHooksWireAfterAccountReadyNotProvisionAfterAccept(): void
    {
        $prov = (string) file_get_contents($this->root() . '/app/Services/Recruitment/EnlistmentAcceptanceProvisioningService.php');
        self::assertStringContainsString('completeAcceptanceOnboarding', $prov);
        self::assertStringContainsString('MemberIntegrationEntryHook::afterAccountReady', $prov);
        self::assertStringContainsString('SOURCE_RECRUITMENT', $prov);
        $userAdmin = (string) file_get_contents($this->root() . '/app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertStringContainsString('MemberIntegrationEntryHook::afterAccountReady', $userAdmin);
        $invite = (string) file_get_contents($this->root() . '/app/Controllers/Web/InvitationAcceptController.php');
        self::assertStringContainsString('MemberIntegrationEntryHook::afterAccountReady', $invite);
        $old = (string) file_get_contents($this->root() . '/app/Controllers/Admin/Organization/OrganizationCommunityController.php');
        self::assertStringContainsString('back-office/integration-membres', $old);
        self::assertStringNotContainsString('buildStaffDashboard', $old);
    }

    public function testLmsOnlyViaAssignUserAndMatricesReuseRepository(): void
    {
        $svc = (string) file_get_contents($this->root() . '/app/Services/MemberIntegration/MemberIntegrationService.php');
        self::assertStringContainsString('assignUser(', $svc);
        self::assertStringContainsString('getScoreWithMissingLabels', $svc);
        self::assertStringContainsString('TYPE_LMS_OPTIONAL', $svc);
        self::assertStringContainsString('linked_course_id', $svc);
        $admin = (string) file_get_contents($this->root() . '/app/Controllers/Admin/MemberIntegrationAdminController.php');
        self::assertStringContainsString('listAssignmentsForUser', $admin);
        $invite = (string) file_get_contents($this->root() . '/app/Services/MemberIntegration/MemberIntegrationInvitationService.php');
        self::assertStringContainsString('hash(\'sha256\'', $invite);
        self::assertStringNotContainsString('response_token,', $invite);
    }

    public function testMemberViewsOmitStaffNotesAndUseCsrfOnLoggedPost(): void
    {
        $member = (string) file_get_contents($this->root() . '/views/member_integration/index.php');
        self::assertStringContainsString('Csrf::field', $member);
        self::assertStringNotContainsString('visible_member', $member);
        $admin = (string) file_get_contents($this->root() . '/views/admin/member_integration/show.php');
        self::assertStringContainsString('Csrf::field', $admin);
        $respond = (string) file_get_contents($this->root() . '/app/Controllers/Web/MemberIntegrationInvitationRespondController.php');
        self::assertStringContainsString('Csrf::validate', $respond);
        $ctrl = (string) file_get_contents($this->root() . '/app/Controllers/Web/MemberIntegrationController.php');
        self::assertStringContainsString('VISIBILITY_MEMBER', $ctrl);
        $email = (string) file_get_contents($this->root() . '/views/emails/member_integration_started.php');
        self::assertStringNotContainsString('note interne', strtolower($email));
    }

    public function testInvitationHashIsStableAndNotReversibleFromConstant(): void
    {
        $a = MemberIntegrationInvitationService::hashToken('abc');
        $b = MemberIntegrationInvitationService::hashToken('abc');
        self::assertSame($a, $b);
        self::assertSame(64, strlen($a));
        self::assertNotSame('abc', $a);
        self::assertSame(MemberIntegrationCatalog::RSVP_ACCEPTED, 'accepted');
    }

    public function testTemplateAdminScreensUseLightBackOfficeChrome(): void
    {
        $css = (string) file_get_contents($this->root() . '/public/assets/css/member-integration.css');
        $list = (string) file_get_contents($this->root() . '/views/admin/member_integration/templates.php');
        $form = (string) file_get_contents($this->root() . '/views/admin/member_integration/template_form.php');
        $pages = (string) file_get_contents($this->root() . '/config/back_office_pages.php');
        $ctrl = (string) file_get_contents($this->root() . '/app/Controllers/Admin/MemberIntegrationAdminController.php');

        self::assertStringContainsString('--mi-ink: var(--ath-ink, #0c1116)', $css);
        self::assertStringContainsString('.mi-table td', $css);
        self::assertStringContainsString('color: var(--mi-ink)', $css);
        self::assertStringContainsString('.mi-step-card', $css);
        self::assertStringContainsString('.mi-field--days', $css);
        self::assertStringContainsString('.mi-empty', $css);
        self::assertStringNotContainsString('background: #0f172a', $css);
        self::assertStringNotContainsString('color: #e2e8f0', $css);

        self::assertStringContainsString('ath-table-panel', $list);
        self::assertStringContainsString('ath-table-toolbar__title', $list);
        self::assertStringContainsString('Nouveau modèle', $list);
        self::assertStringContainsString('mi-empty', $list);
        self::assertStringContainsString('ath-tag--ok', $list);
        self::assertStringNotContainsString('<h1>', $list);

        self::assertStringContainsString('ath-form', $form);
        self::assertStringContainsString('ath-field__label', $form);
        self::assertStringContainsString('Nom du parcours', $form);
        self::assertStringContainsString('mi-step-card', $form);
        self::assertStringContainsString('ath-check', $form);
        self::assertStringContainsString('mi-field--days', $form);
        self::assertStringNotContainsString('mi-panel', $form);
        self::assertStringNotContainsString('<h1>', $form);

        self::assertStringContainsString('back-office/integration-membres/modeles/nouveau', $pages);
        self::assertStringContainsString('Modèles de parcours', $pages);
        self::assertStringContainsString("'boPageTitle'", $ctrl);
    }

    public function testConfigurationUpdateAndCronAreDeclared(): void
    {
        $cat = (string) file_get_contents($this->root() . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        self::assertStringContainsString('MEMBER_INTEGRATION_V1', $cat);
        $probe = (string) file_get_contents($this->root() . '/app/Services/ConfigurationUpdate/ConfigurationUpdateProbes.php');
        self::assertStringContainsString('hasActiveMemberIntegrationTemplate', $probe);
        $sched = (string) file_get_contents($this->root() . '/app/Services/Cron/CronSchedule.php');
        self::assertStringContainsString('member_integration_daily', $sched);
        $boot = (string) file_get_contents($this->root() . '/app/Services/Community/TenantBootstrapService.php');
        self::assertStringContainsString('ensureDefaultRecruitTemplate', $boot);
    }
}
