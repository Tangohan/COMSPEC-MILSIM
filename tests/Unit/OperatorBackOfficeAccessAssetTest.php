<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OperatorBackOfficeAccessAssetTest extends TestCase
{
    public function testBackOfficeRootIsAVisibleMemberSpaceWhileChildrenStayProtected(): void
    {
        $root = dirname(__DIR__, 2);
        $navigation = (string) file_get_contents($root . '/config/navigation.php');
        $helpers = (string) file_get_contents($root . '/app/Support/helpers.php');
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');

        self::assertStringContainsString("['label' => 'Mon back-office', 'path' => 'back-office', 'auth_only' => true]", $navigation);
        self::assertStringContainsString("if (\$path === 'back-office')", $helpers);
        self::assertStringContainsString('$canOpenBackOffice', $header);
        self::assertStringContainsString("'path' => 'back-office/users', 'any_permissions'", $navigation);
    }
}
