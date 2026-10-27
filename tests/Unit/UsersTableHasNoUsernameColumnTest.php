<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La table users n'a pas de colonne username (25/10 : 500 sur /back-office/referentiels/qualifications/{id}/edit).
 */
final class UsersTableHasNoUsernameColumnTest extends TestCase
{
    public function testSchemaHasNoUsernameColumn(): void
    {
        $schema = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/schema.sql');
        self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `users` \((.*?)\n\) /s', $schema, $m));
        self::assertStringNotContainsString('`username`', $m[1]);
    }

    public function testNoQueryReadsUsersUsername(): void
    {
        $root = dirname(__DIR__, 2);
        $hits = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) ?: [] as $n => $line) {
                if (preg_match('/\b(?:u|users)\.`?username\b/', $line)) {
                    $hits[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($n + 1);
                }
            }
        }

        self::assertSame([], $hits);
    }
}
