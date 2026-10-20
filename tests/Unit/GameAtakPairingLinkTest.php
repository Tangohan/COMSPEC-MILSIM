<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GameAtakPairingRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\UserRepository;
use App\Services\Game\GameAtakPairingService;
use App\Services\Game\GameAuthService;
use App\Support\AtakArmaWriteGuard;
use PHPUnit\Framework\TestCase;

/** Code du jeu autorisé sur /lier : Steam déplacé sur un seul compte, demande décrite, suivi réservé à son auteur. */
final class GameAtakPairingLinkTest extends TestCase
{
    private const STEAM = '76561198000000001';

    /** @param array<string, mixed> $row */
    private function service(array $row, UserRepository $users, ?GameAuthService $auth = null): GameAtakPairingService
    {
        $challenges = $this->createMock(GameAtakPairingRepository::class);
        $challenges->method('isReady')->willReturn(true);
        $challenges->method('findPendingByUserHash')->willReturn($row);
        $challenges->method('findById')->willReturn($row);
        $challenges->method('approve')->willReturn(true);
        $guard = $this->createMock(AtakArmaWriteGuard::class);
        $guard->method('assertModNotBlocked')->willReturn(null);
        if ($auth === null) {
            $auth = $this->createMock(GameAuthService::class);
            $auth->method('ensureAccountForUser')->willReturn(['id' => 9]);
        }

        return new GameAtakPairingService(
            $challenges,
            $this->createMock(TacticalGameLinkRepository::class),
            $users,
            $auth,
            $this->createMock(TenantAtakConfigRepository::class),
            $guard,
        );
    }

    /** @return array<string, mixed> */
    private function pendingRow(): array
    {
        return [
            'id' => 42,
            'status' => 'pending',
            'steam_id' => self::STEAM,
            'mod_version' => '2.1.0',
            'created_at' => gmdate('Y-m-d H:i:s', time() - 150),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 450),
        ];
    }

    public function testApprovalMovesSteamToTheApprovingAccountOnly(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn(['id' => 5, 'steam_id' => null]);
        $users->expects(self::once())->method('detachSteamFromOtherUsers')->with(3, self::STEAM, 5)->willReturn([7]);
        $users->expects(self::once())->method('update')->with(5, 3, ['steam_id' => self::STEAM])->willReturn(true);

        $result = $this->service($this->pendingRow(), $users)->approveFromWeb('ABCD-2345', 5, 3);

        self::assertTrue($result['ok']);
        self::assertSame(42, $result['payload']['challenge_id']);
        self::assertSame([7], $result['payload']['steam_moved_from']);
    }

    public function testPendingRequestIsDescribedWithSteamOwnership(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findBySteamIdForTenant')->willReturn(['id' => 7]);

        $info = $this->service($this->pendingRow(), $users)->describePending('ABCD2345', 5, 3);

        self::assertNotNull($info);
        self::assertSame('pending', $info['state']);
        self::assertTrue($info['steam_elsewhere']);
        self::assertFalse($info['steam_here']);
        self::assertSame('2.1.0', $info['mod_version']);
        self::assertSame(2, $info['asked_min']);
        self::assertGreaterThanOrEqual(7, $info['left_min']);
    }

    public function testProgressIsOnlyVisibleToTheApprovingUser(): void
    {
        $row = ['id' => 42, 'status' => 'consumed', 'user_id' => 5, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 300)];
        $svc = $this->service($row, $this->createMock(UserRepository::class));

        self::assertSame('consumed', $svc->approvalProgress(42, 5));
        self::assertSame('unknown', $svc->approvalProgress(42, 6));
    }

    public function testApprovalIsRefusedWithTheReasonTheGameWouldGet(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn(['id' => 5, 'steam_id' => null]);
        $users->expects(self::never())->method('detachSteamFromOtherUsers');
        $auth = $this->createMock(GameAuthService::class);
        $auth->method('ensureAccountForUser')->willReturn(['id' => 9]);
        $auth->method('gameAccessProblem')->willReturn(['code' => 'ACCOUNT_DISABLED', 'message' => 'Fiche inactive.']);

        $result = $this->service($this->pendingRow(), $users, $auth)->approveFromWeb('ABCD-2345', 5, 3);

        self::assertFalse($result['ok']);
        self::assertSame(403, $result['status']);
        self::assertSame('account_disabled', $result['payload']['error']);
        self::assertSame('Fiche inactive.', $result['payload']['message']);
    }
}
