<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakOrderRepository;
use App\Repositories\FireTeamRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantAlertRepository;
use App\Repositories\TenantEmailRecipientGroupRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserAlertDismissalRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\Broadcast\BroadcastCenterService;
use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Tactical\AtakCommunityBriefService;
use App\Support\BroadcastCenter as BC;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Centre de diffusion : envoi par canal (tables existantes), regroupement de la liste, retrait sur tous les canaux. */
final class BroadcastCenterServiceTest extends TestCase
{
    /** @var array<string, MockObject> */
    private array $m = [];

    private ?\Closure $orderTarget = null;

    private function service(): BroadcastCenterService
    {
        foreach ([
            'alerts' => TenantAlertRepository::class,
            'dismissals' => UserAlertDismissalRepository::class,
            'brief' => AtakCommunityBriefService::class,
            'fleet' => AtakAdminControlRepository::class,
            'orders' => AtakOrderRepository::class,
            'discord' => DiscordEventRelayService::class,
            'units' => UnitRepository::class,
            'users' => UserRepository::class,
            'roles' => RoleRepository::class,
            'groups' => TenantEmailRecipientGroupRepository::class,
            'teams' => FireTeamRepository::class,
            'audit' => AuditService::class,
        ] as $name => $class) {
            $this->m[$name] ??= $this->createMock($class);
        }

        return new BroadcastCenterService(
            $this->m['alerts'], $this->m['dismissals'], $this->m['brief'], $this->m['fleet'], $this->m['orders'], $this->m['discord'],
            $this->m['units'], $this->m['users'], $this->m['roles'], $this->m['groups'], $this->m['teams'], $this->m['audit'],
            $this->orderTarget,
        );
    }

    private function mock(string $name, string $class): MockObject
    {
        return $this->m[$name] = $this->createMock($class);
    }

    /** @return array<string, mixed> */
    private static function data(array $in): array
    {
        $r = BC::validate($in + ['type' => 'message', 'title' => 'Briefing', 'body' => 'Ce soir 20 h', 'importance' => 'info', 'channels' => ['game'], 'target' => 'all'], ['site', 'phone', 'tasks', 'game', 'discord'], time());
        self::assertSame([], $r['errors']);

        return $r['data'];
    }

    public function testGameMessageGoesOnceToEachTargetedPlayerWithTheBroadcastKey(): void
    {
        $this->mock('users', UserRepository::class)->method('allForTenant')->willReturn([
            ['id' => 5, 'status' => 'active', 'display_name' => 'Alpha'],
            ['id' => 6, 'status' => 'active', 'display_name' => 'Bravo'],
            ['id' => 7, 'status' => 'active', 'display_name' => 'Charlie'],
        ]);
        $fleet = $this->mock('fleet', AtakAdminControlRepository::class);
        $fleet->method('ready')->willReturn(true);
        $fleet->method('listTerminals')->willReturn([
            ['tenant_id' => 3, 'user_id' => 5, 'terminal_uid' => 'T1', 'steam_id' => ''],
            ['tenant_id' => 3, 'user_id' => 5, 'terminal_uid' => 'T2', 'steam_id' => ''],
            ['tenant_id' => 3, 'user_id' => 7, 'terminal_uid' => 'T3', 'steam_id' => ''],
        ]);
        $sent = [];
        $fleet->expects(self::once())->method('createMessage')->willReturnCallback(static function (array $m) use (&$sent): int {
            $sent[] = $m;

            return 41;
        });
        $this->mock('audit', AuditService::class)->expects(self::once())->method('logChange')
            ->with('broadcast_center.send', 3, 9, 'broadcast', null, [], self::callback(static fn (array $d): bool => $d['channels'] === ['game']));

        $result = $this->service()->send(3, self::data(['target' => 'person', 'user_ids' => ['5', '6'], 'importance' => 'urgent']), ['id' => 9, 'label' => 'TOC']);

        self::assertTrue($result['ok']);
        self::assertSame(5, $sent[0]['user_id']);
        self::assertSame('critical', $sent[0]['level']);
        self::assertSame($result['key'], $sent[0]['broadcast_key']);
        self::assertSame('m', $result['key'][0]);
    }

    public function testNothingIsWrittenWhenNoTargetedPlayerHasAnAtak(): void
    {
        $fleet = $this->mock('fleet', AtakAdminControlRepository::class);
        $fleet->method('ready')->willReturn(true);
        $fleet->method('listTerminals')->willReturn([]);
        $fleet->expects(self::never())->method('createMessage');
        $alerts = $this->mock('alerts', TenantAlertRepository::class);
        $alerts->expects(self::never())->method('insert');

        $result = $this->service()->send(3, self::data(['type' => 'annonce', 'channels' => ['site', 'game']]), ['id' => 9, 'label' => 'TOC']);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('aucune personne visée', $result['errors'][0]);
    }

    public function testTacticalAlertBecomesATrackedOrderOfTheTasksApp(): void
    {
        $this->orderTarget = static fn (int $t, int $map, string $type, string $ref): array => ['target' => 'Alpha', 'target_ref' => $ref, 'target_label' => 'Équipe Alpha', 'error' => null];
        $this->mock('teams', FireTeamRepository::class);
        $orders = $this->mock('orders', AtakOrderRepository::class);
        $orders->method('tablesReady')->willReturn(true);
        $orders->expects(self::once())->method('upsertByExternalId')
            ->with(3, 2, self::callback(static fn (array $d): bool => str_starts_with($d['external_id'], 'ORD-W-x')
                && $d['order_type'] === 'NOTIFY_FULL' && $d['priority'] === 'URGENT' && $d['source'] === 'web'
                && $d['target_type'] === 'fire_team' && $d['radio_sim'] === false && $d['issuer'] === 'TOC'))
            ->willReturn(['id' => 1]);

        $result = $this->service()->send(3, self::data(['type' => 'alerte', 'channels' => ['tasks'], 'importance' => 'urgent', 'target' => 'fire_team', 'fire_team_id' => '12', 'map_id' => '2']), ['id' => 9, 'label' => 'TOC']);

        self::assertTrue($result['ok'], json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function testListMergesTheChannelsOfOneBroadcastAndWithdrawCancelsEachPart(): void
    {
        $key = 'cd0123456789';
        $alerts = $this->mock('alerts', TenantAlertRepository::class);
        $alerts->method('allForTenantOrdered')->willReturn([[
            'id' => 8, 'kind' => 'notice', 'title' => 'Tenue', 'body' => 'Désert', 'is_active' => 1, 'starts_at' => null, 'ends_at' => null,
            'created_at' => '2026-11-08 10:00:00', 'features_json' => '{"dismissible":true}', 'broadcast_key' => $key,
        ]]);
        $this->mock('dismissals', UserAlertDismissalRepository::class)->method('countsForTenantAlerts')->willReturn([8 => 4]);
        $fleet = $this->mock('fleet', AtakAdminControlRepository::class);
        $fleet->method('broadcastMessagesForTenant')->willReturn([
            ['id' => 20, 'title' => 'Tenue', 'body' => 'Désert', 'level' => 'info', 'require_ack' => 1, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'created_at' => gmdate('Y-m-d H:i:s'), 'delivered_at' => null, 'acked_at' => null, 'cancelled_at' => null, 'created_by_user_id' => 9, 'broadcast_key' => $key],
            ['id' => 21, 'title' => 'Tenue', 'body' => 'Désert', 'level' => 'info', 'require_ack' => 1, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'created_at' => gmdate('Y-m-d H:i:s'), 'delivered_at' => gmdate('Y-m-d H:i:s'), 'acked_at' => gmdate('Y-m-d H:i:s'), 'cancelled_at' => null, 'created_by_user_id' => 9, 'broadcast_key' => $key],
        ]);
        $this->mock('orders', AtakOrderRepository::class)->method('listWebForTenant')->willReturn([]);

        $service = $this->service();
        $items = $service->items(3)['items'];

        self::assertSame(['bc:' . $key], array_column($items, 'ref'));
        self::assertSame(['site', 'discord', 'game'], $items[0]['channels']);
        self::assertSame('consigne', $items[0]['type']);
        self::assertSame('active', $items[0]['state']);
        self::assertContains('Fermée par 4 membre(s)', $items[0]['receipts']);
        self::assertContains('1 validé(s)', $items[0]['receipts']);

        $alerts->expects(self::once())->method('setActive')->with(8, 3, false)->willReturn(true);
        $fleet->expects(self::once())->method('cancelMessagesForTenant')->with(3, [20, 21])->willReturn(1);
        $r = $service->withdraw(3, 'bc:' . $key, '', ['site', 'game'], ['id' => 9, 'label' => 'TOC']);
        self::assertTrue($r['ok'], $r['message']);
        self::assertStringContainsString('Discord reste dans le salon', $r['message']);

        $denied = $service->withdraw(3, 'bc:' . $key, '', ['site'], ['id' => 9, 'label' => 'TOC']);
        self::assertFalse($denied['ok']);
        self::assertSame(403, $denied['status']);
    }

    public function testLegacyFleetMessagesAreGroupedByAuthorTextAndTime(): void
    {
        $row = static fn (int $id, string $at, string $title = 'Op ce soir'): array => ['id' => $id, 'title' => $title, 'body' => 'x', 'level' => 'info', 'created_at' => $at, 'created_by_user_id' => 4, 'broadcast_key' => null];
        $groups = BroadcastCenterService::groupMessages([
            $row(3, '2026-11-08 10:00:40'),
            $row(2, '2026-11-08 10:00:01'),
            $row(1, '2026-11-08 10:00:00'),
            $row(4, '2026-11-08 18:00:00'),
            $row(5, '2026-11-08 10:00:02', 'Autre'),
        ]);

        self::assertSame([[1, 2, 3], [4], [5]], array_map(static fn (array $g): array => array_map('intval', array_column($g, 'id')), $groups));
    }

    public function testFilterAndImportanceSort(): void
    {
        $item = static fn (string $ref, ?string $imp, string $state, int $ts, array $channels = ['site']): array => ['ref' => $ref, 'type' => 'annonce', 'importance' => $imp, 'state' => $state, 'ts' => $ts, 'channels' => $channels];
        $items = [$item('a', 'info', 'active', 3), $item('b', 'urgent', 'active', 1), $item('c', 'urgent', 'expired', 5), $item('d', null, 'active', 2, ['phone'])];

        self::assertSame(['c', 'a', 'd', 'b'], array_column(BroadcastCenterService::filter($items, []), 'ref'));
        self::assertSame(['b', 'a', 'd', 'c'], array_column(BroadcastCenterService::filter($items, ['tri' => 'importance']), 'ref'));
        self::assertSame(['d'], array_column(BroadcastCenterService::filter($items, ['canal' => 'phone']), 'ref'));
        self::assertSame(['c'], array_column(BroadcastCenterService::filter($items, ['etat' => 'expired']), 'ref'));
    }
}
