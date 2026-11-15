<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseAccessPolicy;
use PHPUnit\Framework\TestCase;

final class SseAccessManageRightsAdminTest extends TestCase
{
    /** @param array<int, list<string>> $perms @param list<int> $platform */
    private function policy(array $perms, array $platform = []): SseAccessPolicy
    {
        return new SseAccessPolicy(
            null,
            null,
            static fn (int $u, int $t, string $slug): bool => in_array($slug, $perms[$u] ?? [], true),
            static fn (?int $u, int $t, string $mode): bool => $u !== null && $u > 0,
            static fn (): string => '2026-11-15 12:00:00',
            static fn (int $u, int $t): bool => in_array($u, $platform, true),
        );
    }

    public function testOrganizationAdministratorsAndPlatformAdministratorsManageRights(): void
    {
        $policy = $this->policy([10 => ['atak.sse.grant'], 20 => ['admin.organization'], 21 => ['atak.sse.files.admin']], [30]);
        $ctx = ['tenant_id' => 5, 'guest' => false];

        self::assertTrue($policy->canManageRights(10, $ctx), 'le droit de gestion SSE existant');
        self::assertTrue($policy->canManageRights(20, $ctx), 'administrateur de l’organisation');
        self::assertTrue($policy->canManageRights(30, $ctx), 'administrateur de la plateforme');
        self::assertFalse($policy->canManageRights(21, $ctx), 'un droit SSE de fichiers ne suffit pas');
    }

    public function testAGuestSessionNeverManagesRights(): void
    {
        $policy = $this->policy([20 => ['admin.organization']], [30]);

        self::assertFalse($policy->canManageRights(20, ['tenant_id' => 5, 'guest' => true]));
        self::assertFalse($policy->canManageRights(30, ['tenant_id' => 5, 'guest' => true]));
        self::assertFalse($policy->canManageRights(0, ['tenant_id' => 5, 'guest' => false]));
    }
}
