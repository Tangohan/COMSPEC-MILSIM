<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UserCommunityIdentityMigrationIdempotentTest extends TestCase
{
    public function testFileCanBeRequiredTwiceWithoutRedeclare(): void
    {
        $path = dirname(__DIR__, 2) . '/bootstrap/user_community_identity_migration.php';
        self::assertFileExists($path);

        $first = require $path;
        $second = require $path;

        self::assertTrue(function_exists('run_user_community_identity_migration'));
        self::assertTrue(is_callable($first));
        self::assertTrue(is_callable($second));
    }

    public function testDeclarationIsGuarded(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/user_community_identity_migration.php');
        self::assertStringContainsString("function_exists('run_user_community_identity_migration')", $src);
    }
}
