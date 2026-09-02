<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SystemUsersGroupedDirectoryTest extends TestCase
{
    public function testGroupedDirectoryAndScopedActionsAreWired(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/System/SystemUsersController.php');
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Account/AccountDeletionService.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/system/users.php');

        self::assertStringContainsString('listGroupedAccountsForPlatformDirectory', $repo);
        self::assertStringContainsString('GROUP BY LOWER(TRIM(u.email))', $repo);
        self::assertStringContainsString('listGroupedAccountsForPlatformDirectory', $ctrl);
        self::assertStringContainsString("scope === 'org'", $ctrl);
        self::assertStringContainsString('softDeleteMembership', $ctrl);
        self::assertStringContainsString('function softDeleteMembership', $svc);
        self::assertStringContainsString('platformUserGroups', $view);
        self::assertStringContainsString('scope" value="org"', $view);
        self::assertStringContainsString('scope" value="site"', $view);
        self::assertStringContainsString('Retirer de l’orga', $view);
        self::assertStringContainsString('Anonymiser (site)', $view);
        self::assertStringContainsString('Supprimer définitivement (site)', $view);
    }

    public function testPlatformDirectoryUsesMembershipRowsWhenAvailable(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('fetchPlatformDirectoryMembershipRowsByEmails', $repo);
        self::assertStringContainsString('user_community_memberships m ON m.user_id = u.id', $repo);
        if (!preg_match('/function listGroupedAccountsForPlatformDirectory\b.*?^\    \}/ms', $repo, $match)) {
            self::fail('listGroupedAccountsForPlatformDirectory introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString("'NOT ' . SqlText::inLiterals(\$pdo, 'u.status', ['merged'])", $body);
        self::assertStringNotContainsString("t.slug <> 'default' OR NOT EXISTS", $body);
    }

    public function testSoftDeleteMembershipDoesNotAnonymizeGlobalIdentity(): void
    {
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Account/AccountDeletionService.php');
        if (!preg_match('/function softDeleteMembership\b.*?^\    \}/ms', $svc, $match)) {
            self::fail('softDeleteMembership introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString('communityMemberships()->leave', $body);
        self::assertStringNotContainsString('anonymizeUserIdentity', $body);
    }
}
