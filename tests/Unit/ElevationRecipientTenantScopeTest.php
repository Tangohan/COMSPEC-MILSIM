<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\UserRepository;
use App\Services\Effectifs\EffectifsStaffAlertService;
use App\Services\Training\TrainingStaffAlertService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Garde-fous : les destinataires d’élévation (RH / publication) restent bornés au tenant.
 * Pas de liste globale type listActiveEmailsHavingPermissionGlobally.
 */
final class ElevationRecipientTenantScopeTest extends TestCase
{
    public function testPermissionListingGuardsInvalidTenantBeforeQuery(): void
    {
        $method = new ReflectionMethod(UserRepository::class, 'listActiveUserIdsWithAnyPermissionSlug');
        $file = (string) $method->getFileName();
        $start = (int) $method->getStartLine();
        $end = (int) $method->getEndLine();
        $body = implode('', array_slice(file($file) ?: [], $start - 1, $end - $start + 1));

        self::assertStringContainsString('if ($tenantId < 1 || $permissionSlugs === [])', $body);
        self::assertStringContainsString('return [];', $body);
    }

    public function testPermissionListingSqlBindsTenantOnUsersRolesAndPermissions(): void
    {
        $method = new ReflectionMethod(UserRepository::class, 'listActiveUserIdsWithAnyPermissionSlug');
        $file = (string) $method->getFileName();
        $start = (int) $method->getStartLine();
        $end = (int) $method->getEndLine();
        $body = implode('', array_slice(file($file) ?: [], $start - 1, $end - $start + 1));

        // Appartenance via prédicat (concaténation PHP réelle, pas interpolation de propriété).
        self::assertStringContainsString("\$this->sqlMemberOfTenantPredicate('u', \$tenantId)", $body);
        self::assertStringNotContainsString("WHERE ' . \$this->sqlMemberOfTenantPredicate", $body);
        self::assertStringContainsString('r.tenant_id = ?', $body);
        self::assertStringContainsString('p.tenant_id = ?', $body);
        self::assertStringContainsString('tur.tenant_id = ?', $body);
        self::assertStringNotContainsString('listActiveEmailsHavingPermissionGlobally', $body);
    }

    public function testEffectifsElevationDoesNotUseGlobalRecipientListing(): void
    {
        $file = (new ReflectionClass(EffectifsStaffAlertService::class))->getFileName();
        self::assertNotFalse($file);
        $src = (string) file_get_contents($file);
        self::assertStringContainsString('listActiveUserIdsWithAnyPermissionSlug', $src);
        self::assertStringContainsString('findByIdsForTenant', $src);
        self::assertStringNotContainsString('listActiveEmailsHavingPermissionGlobally', $src);
        self::assertStringNotContainsString("'admin.system'", $src);
    }

    public function testTrainingPublishElevationDoesNotUseGlobalRecipientListing(): void
    {
        $file = (new ReflectionClass(TrainingStaffAlertService::class))->getFileName();
        self::assertNotFalse($file);
        $src = (string) file_get_contents($file);
        self::assertStringContainsString('listActiveUserIdsWithAnyPermissionSlug', $src);
        self::assertStringContainsString('findByIdsForTenant', $src);
        self::assertStringNotContainsString('listActiveEmailsHavingPermissionGlobally', $src);
        self::assertStringNotContainsString("'admin.system'", $src);
    }
}
