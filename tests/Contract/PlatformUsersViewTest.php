<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;

final class PlatformUsersViewTest extends TestCase
{
    /** Les clés '30', '90', '180' deviennent des entiers : la vue doit les convertir (erreur 500 sur /admin/users). */
    public function testEmptyDirectoryRendersWithInactivityFilterSelected(): void
    {
        $platformUserGroups = [];
        $platformUsersInactivity = '30';

        ob_start();
        try {
            require dirname(__DIR__, 2) . '/views/admin/system/users.php';
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringContainsString('<option value="30" selected>', $html);
    }
}
