<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use App\Services\ConfigurationUpdate\ConfigurationUpdateDefinition;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;
use PHPUnit\Framework\TestCase;

final class ConfigurationUpdateServiceTest extends TestCase
{
    public function testStatusAndLevelConstants(): void
    {
        self::assertSame('PENDING', ConfigurationUpdateService::STATUS_PENDING);
        self::assertSame('SEEN', ConfigurationUpdateService::STATUS_SEEN);
        self::assertSame('IN_PROGRESS', ConfigurationUpdateService::STATUS_IN_PROGRESS);
        self::assertSame('COMPLETED', ConfigurationUpdateService::STATUS_COMPLETED);
        self::assertSame('DISMISSED', ConfigurationUpdateService::STATUS_DISMISSED);
        self::assertSame('NOT_APPLICABLE', ConfigurationUpdateService::STATUS_NOT_APPLICABLE);

        self::assertSame('informative', ConfigurationUpdateDefinition::LEVEL_INFORMATIVE);
        self::assertSame('recommended', ConfigurationUpdateDefinition::LEVEL_RECOMMENDED);
        self::assertSame('required', ConfigurationUpdateDefinition::LEVEL_REQUIRED);
    }

    public function testAuditActionsRegistered(): void
    {
        self::assertSame('configuration_update.seen', AuditAction::CONFIGURATION_UPDATE_SEEN);
        self::assertSame('configuration_update.started', AuditAction::CONFIGURATION_UPDATE_STARTED);
        self::assertSame('configuration_update.completed', AuditAction::CONFIGURATION_UPDATE_COMPLETED);
        self::assertSame('configuration_update.dismissed', AuditAction::CONFIGURATION_UPDATE_DISMISSED);
        self::assertSame('configuration_update.reopened', AuditAction::CONFIGURATION_UPDATE_REOPENED);
    }

    public function testUnavailableWithoutTablesReturnsEmpty(): void
    {
        $repo = $this->createMock(\App\Repositories\ConfigurationUpdateRepository::class);
        $repo->method('tablesExist')->willReturn(false);

        $catalog = $this->createMock(\App\Services\ConfigurationUpdate\ConfigurationUpdateCatalog::class);
        $tenants = $this->createMock(\App\Repositories\TenantRepository::class);

        $svc = new ConfigurationUpdateService($repo, $catalog, $tenants, null);

        self::assertFalse($svc->isAvailable());
        self::assertSame([], $svc->listForTenant(1));
        self::assertSame([], $svc->getActionableUpdates(1));
    }

    public function testHubSummaryGroupsStatuses(): void
    {
        $repo = $this->createMock(\App\Repositories\ConfigurationUpdateRepository::class);
        $repo->method('tablesExist')->willReturn(true);
        $repo->method('listActiveSystemUpdates')->willReturn([
            [
                'id' => 1,
                'code' => 'TIMEZONE_V1',
                'title' => 'Fuseau horaire',
                'description' => 'Choisir un fuseau',
                'configuration_level' => 'recommended',
                'configure_path' => 'back-office/organisation/parametres#timezone',
                'estimate_minutes' => 1,
                'dismissible' => 1,
                'blocking' => 0,
                'released_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'code' => 'PUBLIC_PROFILE_V1',
                'title' => 'Vitrine',
                'description' => 'Présentation',
                'configuration_level' => 'informative',
                'configure_path' => 'back-office/community/presentation',
                'estimate_minutes' => 5,
                'dismissible' => 1,
                'blocking' => 0,
                'released_at' => date('Y-m-d H:i:s'),
            ],
        ]);
        $repo->method('mapTenantStates')->willReturn([
            2 => ['status' => 'COMPLETED', 'completed_at' => '2026-07-01 10:00:00'],
        ]);
        $repo->method('findSystemByCode')->willReturnCallback(static function (string $code): ?array {
            return match ($code) {
                'TIMEZONE_V1' => ['id' => 1, 'code' => 'TIMEZONE_V1'],
                'PUBLIC_PROFILE_V1' => ['id' => 2, 'code' => 'PUBLIC_PROFILE_V1'],
                default => null,
            };
        });

        $defPending = new ConfigurationUpdateDefinition(
            code: 'TIMEZONE_V1',
            title: 'Fuseau horaire',
            description: 'Choisir un fuseau',
            level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
            configurePath: 'back-office/organisation/parametres#timezone',
            estimateMinutes: 1,
            dismissible: true,
            blocking: false,
            dependsOn: [],
            sortOrder: 20,
            isApplicable: static fn (int $id): bool => true,
            isSatisfied: static fn (int $id): bool => false,
        );
        $defDone = new ConfigurationUpdateDefinition(
            code: 'PUBLIC_PROFILE_V1',
            title: 'Vitrine',
            description: 'Présentation',
            level: ConfigurationUpdateDefinition::LEVEL_INFORMATIVE,
            configurePath: 'back-office/community/presentation',
            estimateMinutes: 5,
            dismissible: true,
            blocking: false,
            dependsOn: [],
            sortOrder: 50,
            isApplicable: static fn (int $id): bool => true,
            isSatisfied: static fn (int $id): bool => true,
        );

        $catalog = $this->createMock(\App\Services\ConfigurationUpdate\ConfigurationUpdateCatalog::class);
        $catalog->method('definitions')->willReturn([$defPending, $defDone]);

        $tenants = $this->createMock(\App\Repositories\TenantRepository::class);
        $tenants->method('getSettings')->willReturn([]);

        $svc = new ConfigurationUpdateService($repo, $catalog, $tenants, null);
        $hub = $svc->hubSummary(42);

        self::assertSame(1, $hub['counts']['actionable']);
        self::assertSame(1, $hub['counts']['recommended']);
        self::assertSame(1, $hub['counts']['completed']);
        self::assertTrue($hub['show_intro']);
        self::assertSame(1, $hub['nav_badge']);
    }

    public function testNotApplicableWhenProbeRejects(): void
    {
        $repo = $this->createMock(\App\Repositories\ConfigurationUpdateRepository::class);
        $repo->method('tablesExist')->willReturn(true);
        $repo->method('listActiveSystemUpdates')->willReturn([
            [
                'id' => 9,
                'code' => 'ATAK_CONFIGURATION_V1',
                'title' => 'ATAK',
                'description' => 'Config',
                'configuration_level' => 'recommended',
                'configure_path' => 'admin/atak-config',
                'estimate_minutes' => 4,
                'dismissible' => 1,
                'blocking' => 0,
                'released_at' => date('Y-m-d H:i:s'),
            ],
        ]);
        $repo->method('mapTenantStates')->willReturn([]);

        $def = new ConfigurationUpdateDefinition(
            code: 'ATAK_CONFIGURATION_V1',
            title: 'ATAK',
            description: 'Config',
            level: ConfigurationUpdateDefinition::LEVEL_RECOMMENDED,
            configurePath: 'admin/atak-config',
            estimateMinutes: 4,
            dismissible: true,
            blocking: false,
            dependsOn: [],
            sortOrder: 60,
            isApplicable: static fn (int $id): bool => false,
            isSatisfied: static fn (int $id): bool => false,
        );

        $catalog = $this->createMock(\App\Services\ConfigurationUpdate\ConfigurationUpdateCatalog::class);
        $catalog->method('definitions')->willReturn([$def]);

        $tenants = $this->createMock(\App\Repositories\TenantRepository::class);
        $tenants->method('getSettings')->willReturn(['configuration_updates_intro_seen_at' => date('c')]);

        $svc = new ConfigurationUpdateService($repo, $catalog, $tenants, null);
        $items = $svc->listForTenant(7);
        self::assertCount(1, $items);
        self::assertSame('NOT_APPLICABLE', $items[0]['status']);
        self::assertSame([], $svc->getActionableUpdates(7));
    }
}
