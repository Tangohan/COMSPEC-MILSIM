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
}
