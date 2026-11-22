<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\SystemUsersController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AccountPurgeRequestRepository;
use App\Repositories\PlatformAccountBanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserCommunityMembershipRepository;
use App\Repositories\UserRepository;
use App\Services\Account\PlatformAccountBanService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use PHPUnit\Framework\TestCase;

final class PlatformUsersBulkActionTest extends TestCase
{
    public function testBulkInactivityPreviewExcludesProtectedAccountsAndRechecksActivity(): void
    {
        $oldSession = $_SESSION ?? [];
        $oldPost = $_POST;
        $oldMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        Session::start();
        $_SESSION = ['user_id' => 7];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            $idle = 'repos@example.test';
            $admin = 'admin@example.test';
            $roster = 'fiche-exemple@sans-compte.invalid';
            $groups = [
                ['email_key' => $idle, 'email' => 'Repos@example.test', 'memberships' => [['id' => 5, 'account_status' => 'inactive']]],
                ['email_key' => $admin, 'email' => $admin, 'memberships' => [['id' => 7, 'account_status' => 'active']]],
                ['email_key' => $roster, 'email' => $roster, 'memberships' => [['id' => 8, 'account_status' => 'active']]],
            ];
            $users = $this->createStub(UserRepository::class);
            $users->method('platformDirectoryGroupsByEmails')->willReturn($groups);
            $users->method('inactiveEmailsForPlatformSelection')->willReturnOnConsecutiveCalls([$idle, $admin, $roster], []);
            $users->method('listIdsByEmailNormalized')->willReturnMap([[$idle, false, [5]], [$admin, false, [7]]]);
            $bans = $this->createStub(PlatformAccountBanRepository::class);
            $bans->method('supportsNotices')->willReturn(true);
            $service = $this->createMock(PlatformAccountBanService::class);
            $service->expects(self::never())->method('ban');

            $controller = new SystemUsersController(
                $users,
                $this->createStub(TenantRepository::class),
                $this->createStub(AuditService::class),
                purgeRequests: $this->createStub(AccountPurgeRequestRepository::class),
                banRepository: $bans,
                banService: $service,
            );
            $csrf = Csrf::token();
            $_POST = ['_csrf_token' => $csrf, 'operation' => 'ban-inactive', 'inactif' => '30', 'emails' => [$idle, $admin, $roster, strtoupper($idle)]];
            $preview = $controller->previewBulkAction(new Request());
            self::assertSame(200, $preview->statusCode());
            $data = json_decode($preview->body(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(1, $data['eligible']);
            self::assertCount(3, $data['rows']);
            self::assertStringContainsString('30 jours', $data['threshold']);
            self::assertSame('Compte administrateur protégé.', $data['rows'][1]['reason']);
            self::assertSame('Fiche sans compte de connexion.', $data['rows'][2]['reason']);

            $_POST = ['_csrf_token' => $csrf, 'preview_nonce' => $data['nonce'], 'duree' => 'definitif'];
            self::assertSame(422, $controller->applyBulkAction(new Request())->statusCode());
            $_POST['confirm_bulk'] = '1';
            $result = $controller->applyBulkAction(new Request());
            self::assertSame(200, $result->statusCode());
            $outcomes = json_decode($result->body(), true, flags: JSON_THROW_ON_ERROR)['outcomes'];
            self::assertSame(['skipped'], array_column($outcomes, 'state'));
            self::assertSame(409, $controller->applyBulkAction(new Request())->statusCode());
        } finally {
            $_SESSION = $oldSession;
            $_POST = $oldPost;
            if ($oldMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $oldMethod;
            }
        }
    }

    public function testRosterRemovalConfirmsOnlyThePreviewedMembershipsAndSkipsStaleRows(): void
    {
        $oldSession = $_SESSION ?? [];
        $oldPost = $_POST;
        $oldMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        Session::start();
        $_SESSION = ['user_id' => 7];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            $first = 'fiche-une@sans-compte.invalid';
            $changed = 'fiche-archivee@sans-compte.invalid';
            $account = 'compte@example.test';
            $member = ['id' => 8, 'account_status' => 'active', 'membership_status' => 'active',
                'tenant_id' => 21, 'tenant_name' => 'Alpha', 'tenant_slug' => 'alpha'];
            $groups = [
                ['email_key' => $first, 'callsign' => 'Fiche A', 'memberships' => [
                    $member,
                    array_replace($member, ['tenant_id' => 22, 'tenant_name' => 'Bravo', 'tenant_slug' => 'bravo']),
                ]],
                ['email_key' => $changed, 'callsign' => 'Fiche B', 'memberships' => [array_replace($member, ['id' => 9])]],
                ['email_key' => $account, 'memberships' => [array_replace($member, ['id' => 10])]],
            ];
            $after = $groups;
            $after[1]['memberships'][0]['membership_status'] = 'left';
            $memberships = $this->createMock(UserCommunityMembershipRepository::class);
            $memberships->expects(self::once())->method('leaveRosterMemberships')
                ->with($first, [['user_id' => 8, 'tenant_id' => 21]])->willReturn(true);
            $users = $this->createStub(UserRepository::class);
            $users->method('hasCommunityMembershipTable')->willReturn(true);
            $users->method('platformDirectoryGroupsByEmails')->willReturnOnConsecutiveCalls($groups, $after);
            $users->method('communityMemberships')->willReturn($memberships);
            $audit = $this->createMock(AuditService::class);
            $audit->expects(self::once())->method('logChange')->with(AuditAction::USER_MEMBERSHIP_REMOVED, 21, 7, 'user', 8);
            $bans = $this->createMock(PlatformAccountBanRepository::class);
            $bans->expects(self::never())->method('supportsNotices');
            $controller = new SystemUsersController(
                $users,
                $this->createStub(TenantRepository::class),
                $audit,
                purgeRequests: $this->createStub(AccountPurgeRequestRepository::class),
                banRepository: $bans,
            );

            $csrf = Csrf::token();
            $_POST = ['_csrf_token' => $csrf, 'operation' => 'remove-roster', 'tenant_id' => '21', 'emails' => [$first, $changed, $account]];
            $response = $controller->previewBulkAction(new Request());
            self::assertSame(200, $response->statusCode());
            $preview = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(2, $preview['eligible']);
            self::assertSame(['Alpha'], $preview['rows'][0]['communities']);
            self::assertFalse($preview['rows'][2]['eligible']);
            self::assertSame([], $preview['rows'][2]['communities']);

            $_POST = ['_csrf_token' => $csrf, 'preview_nonce' => $preview['nonce'], 'tenant_id' => '22'];
            self::assertSame(422, $controller->applyBulkAction(new Request())->statusCode());
            $_POST['confirm_bulk'] = '1';
            $response = $controller->applyBulkAction(new Request());
            self::assertSame(200, $response->statusCode());
            $outcomes = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR)['outcomes'];
            self::assertSame(['applied', 'skipped'], array_column($outcomes, 'state'));
            self::assertSame(['Fiche A', 'Fiche B'], array_column($outcomes, 'label'));
            self::assertSame(409, $controller->applyBulkAction(new Request())->statusCode());
        } finally {
            $_SESSION = $oldSession;
            $_POST = $oldPost;
            if ($oldMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $oldMethod;
            }
        }
    }
}
