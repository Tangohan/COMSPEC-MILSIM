<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakSendQueueRepository;
use PHPUnit\Framework\TestCase;

final class AtakSendQueueRepositoryTest extends TestCase
{
    public function testReportIsCleaned(): void
    {
        $report = AtakSendQueueRepository::normalizeReport([
            'steam_uid' => '76561198000000001',
            'call_sign' => "N-10\n",
            'client_kind' => 'CLIENT',
            'extension_version' => '2.0.65',
            'summary' => ['queued' => '3', 'retry' => 1.6, 'failed' => -4, 'overload' => 'RATE_LIMITED', 'avg_latency_ms' => 'abc', 'p95_latency_ms' => 99999999],
            'items' => [
                ['id' => 'a1', 'kind' => 'POST /api/atak/reports', 'label' => 'Rapport tactique', 'state' => 'done', 'attempts' => 2, 'bytes' => 512,
                    'created_at' => 1760270000, 'updated_at' => 1760270005, 'last_http' => 200,
                    'steps' => [['queued', 1760270000, ''], ['sending', 1760270001, ''], ['retry', 1760270003, 'http_503'], ['done', 1760270005, '200'], 'nope', ['', 1, '']]],
                ['id' => 'b2', 'state' => 'retry', 'created_at' => 1760270100, 'updated_at' => 1760270110, 'last_error' => 'http_503'],
                ['id' => 'c3', 'state' => 'unknown'],
                'nope',
            ],
        ]);

        self::assertNotNull($report);
        self::assertSame('76561198000000001', $report['steam_uid']);
        self::assertSame('client', $report['client_kind']);
        self::assertSame('N-10', $report['call_sign']);
        self::assertSame(3, $report['summary']['queued']);
        self::assertSame(2, $report['summary']['retry']);
        self::assertSame(0, $report['summary']['failed'], 'négatif ramené à 0');
        self::assertSame(0, $report['summary']['avg_latency_ms'], 'texte ignoré');
        self::assertSame(600000, $report['summary']['p95_latency_ms'], 'plafonné');
        self::assertSame('rate_limited', $report['summary']['overload']);
        self::assertCount(2, $report['items'], 'état inconnu et élément invalide ignorés');
        self::assertSame('b2', $report['items'][0]['id'], 'plus récent d’abord');
        self::assertSame('a1', $report['items'][1]['id']);
        self::assertCount(4, $report['items'][1]['steps']);
        self::assertSame(['retry', 1760270003, 'http_503'], $report['items'][1]['steps'][2]);
    }

    public function testClientNeedsSteamButServerDoesNot(): void
    {
        self::assertNull(AtakSendQueueRepository::normalizeReport(['client_kind' => 'client', 'steam_uid' => 'abc']));
        $server = AtakSendQueueRepository::normalizeReport(['client_kind' => 'server', 'call_sign' => 'Serveur 1']);
        self::assertNotNull($server);
        self::assertSame(AtakSendQueueRepository::SERVER_KEY, $server['steam_uid']);
        // Le Steam ID vérifié par la garde d'écriture l'emporte sur le corps.
        $bound = AtakSendQueueRepository::normalizeReport(['steam_uid' => '76561198000000002'], '76561198000000003');
        self::assertSame('76561198000000003', $bound['steam_uid'] ?? null);
    }

    public function testItemsAreCappedAndOverloadDefaultsToOk(): void
    {
        $items = [];
        for ($i = 0; $i < 150; $i++) {
            $items[] = ['id' => 'x', 'state' => 'queued', 'created_at' => $i];
        }
        $out = AtakSendQueueRepository::normalizeItems($items);
        self::assertCount(AtakSendQueueRepository::MAX_ITEMS, $out);
        self::assertCount(AtakSendQueueRepository::MAX_ITEMS, array_unique(array_column($out, 'id')), 'identifiants rendus uniques');
        self::assertSame([], AtakSendQueueRepository::normalizeItems('nope'));
        self::assertSame('ok', AtakSendQueueRepository::normalizeSummary(['overload' => 'boom'])['overload']);
    }

    public function testStatusLevels(): void
    {
        $now = 1760270000;
        $row = static fn (string $overload, array $extra = []): array => $extra + [
            'overload' => $overload, 'received_ts' => $now - 10, 'queued' => 0, 'retry' => 0, 'failed' => 0, 'oldest_queued_s' => 0,
            'avg_latency_ms' => 120, 'p95_latency_ms' => 300, 'http_429' => 0, 'http_5xx' => 0, 'timeouts' => 0,
        ];

        self::assertSame('none', AtakSendQueueRepository::computeStatus([], $now)['level']);
        self::assertSame('none', AtakSendQueueRepository::computeStatus([$row('ok', ['received_ts' => $now - 600])], $now)['level'], 'bilan trop ancien ignoré');

        $normal = AtakSendQueueRepository::computeStatus([$row('ok'), $row('ok', ['avg_latency_ms' => 80])], $now);
        self::assertSame('normal', $normal['level']);
        self::assertSame('Normal', $normal['label']);
        self::assertSame(2, $normal['clients']);
        self::assertSame(100, $normal['avg_latency_ms']);

        self::assertSame('slow', AtakSendQueueRepository::computeStatus([$row('ok'), $row('slow')], $now)['level']);
        self::assertSame('slow', AtakSendQueueRepository::computeStatus([$row('ok'), $row('ok'), $row('offline')], $now)['level']);

        $over = AtakSendQueueRepository::computeStatus([$row('ok'), $row('rate_limited', ['http_429' => 4, 'queued' => 7])], $now);
        self::assertSame('overloaded', $over['level']);
        self::assertSame('Surchargé', $over['label']);
        self::assertSame(1, $over['rate_limited']);
        self::assertSame(4, $over['http_429']);
        self::assertSame(7, $over['queued']);

        $off = AtakSendQueueRepository::computeStatus([$row('offline'), $row('ok')], $now);
        self::assertSame('offline', $off['level']);
        self::assertSame('Hors ligne', $off['label']);
    }
}
