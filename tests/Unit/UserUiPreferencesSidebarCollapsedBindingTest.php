<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * PDO lie bool false en chaîne vide ; MySQL TINYINT strict rejette ''.
 * L’UPDATE sidebar_collapsed doit donc binder 0/1 (comme l’INSERT).
 */
final class UserUiPreferencesSidebarCollapsedBindingTest extends TestCase
{
    public function testUpsertCastsSidebarCollapsedToIntOnUpdate(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserUiPreferencesRepository.php');
        if (!preg_match('/function upsert\b.*?^\    \}/ms', $src, $match)) {
            self::fail('upsert introuvable.');
        }
        $body = $match[0];
        self::assertStringContainsString("\$key === 'sidebar_collapsed'", $body);
        self::assertStringContainsString('(int) (bool) $data[$key]', $body);
        self::assertStringContainsString("(int) (bool) \$data['sidebar_collapsed']", $body);
    }
}
