<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\AccountDeletionService;
use PHPUnit\Framework\TestCase;

final class AccountPurgeRequestAssetTest extends TestCase
{
    public function testIsAnonymizedUserDetectsDeletedStubs(): void
    {
        self::assertTrue(AccountDeletionService::isAnonymizedUser([
            'email' => 'deleted-12-99@deleted.invalid',
            'display_name' => 'Compte supprimé',
        ]));
        self::assertTrue(AccountDeletionService::isAnonymizedUser([
            'email' => 'alive@example.com',
            'display_name' => 'Compte supprimé',
        ]));
        self::assertTrue(AccountDeletionService::isAnonymizedUser([
            'email' => 'alive@example.com',
            'display_name' => 'Jean',
            'deleted_at' => '2026-01-01 00:00:00',
        ]));
        self::assertFalse(AccountDeletionService::isAnonymizedUser([
            'email' => 'alive@example.com',
            'display_name' => 'Jean',
        ]));
        self::assertFalse(AccountDeletionService::isAnonymizedUser(null));
        self::assertFalse(AccountDeletionService::isAnonymizedUser([]));
    }

    public function testOrgRequestAndPlatformQueueAreWired(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/app/Repositories/AccountPurgeRequestRepository.php');
        self::assertFileExists($root . '/bootstrap/account_purge_requests_migration.php');
        self::assertFileExists($root . '/migrations/20260829180000_account_purge_requests.sql');

        $migration = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('account_purge_requests_migration.php', $migration);

        $orgCtrl = (string) file_get_contents($root . '/app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertStringContainsString('function requestPurge', $orgCtrl);
        self::assertStringContainsString('isAnonymizedUser', $orgCtrl);
        self::assertStringContainsString('AccountPurgeRequestRepository', $orgCtrl);

        $sysCtrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('function approvePurgeRequest', $sysCtrl);
        self::assertStringContainsString('function rejectPurgeRequest', $sysCtrl);
        self::assertStringContainsString('pendingPurgeRequests', $sysCtrl);
        self::assertStringContainsString("scope' => 'org'", $sysCtrl);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('/back-office/users/{id}/request-purge', $routes);
        self::assertStringContainsString('/admin/users/purge-requests/approve', $routes);
        self::assertStringContainsString('/admin/users/purge-requests/reject', $routes);

        $orgShow = (string) file_get_contents($root . '/views/admin/organization/users/show.php');
        self::assertStringContainsString('Demander la suppression définitive', $orgShow);
        self::assertStringContainsString('request-purge', $orgShow);
        self::assertStringContainsString('Compte supprimé', $orgShow);

        $platUsers = (string) file_get_contents($root . '/views/admin/system/users.php');
        self::assertStringContainsString('Demandes de suppression définitive', $platUsers);
        self::assertStringContainsString('purge-requests/approve', $platUsers);
        self::assertStringContainsString('Approuver &amp; purger', $platUsers);

        $container = (string) file_get_contents($root . '/app/Core/Container.php');
        self::assertStringContainsString('AccountPurgeRequestRepository::class', $container);
    }
}
