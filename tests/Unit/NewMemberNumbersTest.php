<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NewMemberNumbersTest extends TestCase
{
    public function testEveryAccountCreationPathAssignsAServiceNumber(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'app/Controllers/Web/RegisterController.php',
            'app/Controllers/Web/InvitationAcceptController.php',
            'app/Controllers/Admin/Organization/UserAdminController.php',
            'app/Services/Effectifs/RosterOnlyMemberService.php',
        ] as $file) {
            self::assertStringContainsString('NewMemberNumbers::assign(', (string) file_get_contents($root . '/' . $file), $file);
        }
        self::assertStringContainsString(
            'NewMemberNumbers::assignMissing(',
            (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php')
        );
    }

    public function testOrganizationNumberFollowsTheCommunityNotTheHomeAccount(): void
    {
        $root = dirname(__DIR__, 2);
        $repo = (string) file_get_contents($root . '/app/Repositories/UserRepository.php');
        // Un membre venu d’une autre communauté reçoit son matricule dans sa fiche communauté.
        self::assertStringContainsString('upsertProfile($userId, $tenantId, [\'tenant_member_number\' => $normalized])', $repo);
        self::assertStringContainsString('function tenantMemberNumberFor(', $repo);
        self::assertStringContainsString('withTenantMemberNumbers(', $repo);
        $service = (string) file_get_contents($root . '/app/Services/Personnel/TenantMemberNumberService.php');
        self::assertStringContainsString('tenantMemberNumberFor($userId, $tenantId)', $service);
        $backfill = (string) file_get_contents($root . '/app/Services/Personnel/NewMemberNumbers.php');
        self::assertStringContainsString('sqlMemberOfTenantPredicate', $backfill);
        self::assertStringContainsString("=== 'automatic'", $backfill);
    }
}
