<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\TenantRecoveryService;
use PHPUnit\Framework\TestCase;

final class TenantRecoveryServiceTest extends TestCase
{
    public function testParseTenantRowFromInsertWithColumns(): void
    {
        $sql = <<<'SQL'
INSERT INTO `tenants` (`id`, `name`, `slug`, `tenant_type`, `logo_url`, `settings`, `created_at`, `updated_at`)
VALUES (14, 'ATHENA', 'athena', 'full', NULL, NULL, '2024-01-01 00:00:00', '2024-06-01 00:00:00');
SQL;
        $service = new TenantRecoveryService();
        $row = $service->parseTenantRowFromSqlDump($sql, 14);

        self::assertIsArray($row);
        self::assertSame(14, $row['id']);
        self::assertSame('ATHENA', $row['name']);
        self::assertSame('athena', $row['slug']);
        self::assertSame('full', $row['tenant_type']);
    }

    public function testParseTenantRowReturnsNullWhenMissing(): void
    {
        $service = new TenantRecoveryService();
        self::assertNull($service->parseTenantRowFromSqlDump('SELECT 1;', 14));
    }

    public function testValidateRestoreRejectsMissingName(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT UNIQUE)');
        $service = new TenantRecoveryService($pdo);
        $result = $service->validateRestore([
            'id' => 14,
            'name' => '',
            'slug' => 'athena',
        ]);

        self::assertFalse($result['ok']);
        self::assertNotEmpty($result['errors']);
    }

    public function testNormalizeRestoreInputLowercasesSlug(): void
    {
        $service = new TenantRecoveryService();
        $normalized = $service->normalizeRestoreInput([
            'id' => '14',
            'slug' => 'ATHENA-OPS',
            'name' => ' Test ',
        ]);

        self::assertSame(14, $normalized['id']);
        self::assertSame('athena-ops', $normalized['slug']);
        self::assertSame('Test', $normalized['name']);
    }
}
