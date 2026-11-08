<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\DiscordPhotoGrouper;
use App\Services\Integrations\DiscordPhotoQueueService;
use PHPUnit\Framework\TestCase;

/**
 * Photos regroupées en un seul message Discord : fenêtre, limite de 10, joueurs et missions distincts.
 */
final class DiscordPhotoGrouperTest extends TestCase
{
    private const T0 = 1_800_000_000;

    /** @return array<string, mixed> */
    private function photo(int $id, int $at, string $author = 'u1', string $mission = 'op-aube', int $tenant = 1, string $batch = ''): array
    {
        return [
            'id' => $id,
            'tenant_id' => $tenant,
            'author_key' => $author,
            'mission_key' => $mission,
            'batch_id' => $batch,
            'created_ts' => $at,
        ];
    }

    public function testPhotosOfSamePlayerAndMissionLeaveInOneMessageAfterTheQuietWindow(): void
    {
        $items = [$this->photo(1, self::T0), $this->photo(2, self::T0 + 8), $this->photo(3, self::T0 + 15)];

        self::assertSame([[1, 2, 3]], DiscordPhotoGrouper::messagesReady($items, self::T0 + 15 + DiscordPhotoGrouper::FENETRE));
    }

    public function testSeriesStillOpenIsKeptWaiting(): void
    {
        $items = [$this->photo(1, self::T0), $this->photo(2, self::T0 + 10)];

        self::assertSame([], DiscordPhotoGrouper::messagesReady($items, self::T0 + 10 + DiscordPhotoGrouper::FENETRE - 1));
    }

    public function testPauseLongerThanTheWindowStartsANewMessage(): void
    {
        $items = [$this->photo(1, self::T0), $this->photo(2, self::T0 + 10), $this->photo(3, self::T0 + 60)];

        self::assertSame([[1, 2], [3]], DiscordPhotoGrouper::messagesReady($items, self::T0 + 200));
    }

    public function testNeverMoreThanTenPhotosPerMessage(): void
    {
        $items = [];
        for ($i = 1; $i <= 23; ++$i) {
            $items[] = $this->photo($i, self::T0 + $i);
        }
        $messages = DiscordPhotoGrouper::messagesReady($items, self::T0 + 23 + DiscordPhotoGrouper::FENETRE);

        self::assertSame([10, 10, 3], array_map(static fn (array $m): int => count($m), $messages));
        self::assertSame(range(1, 23), array_merge(...$messages));
    }

    public function testFullMessageLeavesWithoutWaitingWhileTheRestKeepsWaiting(): void
    {
        $items = [];
        for ($i = 1; $i <= 12; ++$i) {
            $items[] = $this->photo($i, self::T0 + $i);
        }
        $messages = DiscordPhotoGrouper::messagesReady($items, self::T0 + 13);

        self::assertSame([range(1, 10)], $messages);
    }

    public function testDifferentPlayersMissionsAndCommunitiesNeverShareAMessage(): void
    {
        $items = [
            $this->photo(1, self::T0, 'u1', 'op-aube'),
            $this->photo(2, self::T0 + 1, 'u2', 'op-aube'),
            $this->photo(3, self::T0 + 2, 'u1', 'op-nuit'),
            $this->photo(4, self::T0 + 3, 'u1', 'op-aube', 2),
        ];
        $messages = DiscordPhotoGrouper::messagesReady($items, self::T0 + 300);
        usort($messages, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        self::assertSame([[1], [2], [3], [4]], $messages);
    }

    public function testBatchIdGroupsEvenAcrossLongGaps(): void
    {
        $items = [
            $this->photo(1, self::T0, 'u1', 'op-aube', 1, 'lot-7'),
            $this->photo(2, self::T0 + 50, 'u1', 'op-aube', 1, 'lot-7'),
            $this->photo(3, self::T0 + 51, 'u1', 'op-aube', 1, 'lot-8'),
        ];
        $messages = DiscordPhotoGrouper::messagesReady($items, self::T0 + 300);
        usort($messages, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        self::assertSame([[1, 2], [3]], $messages);
    }

    public function testEndlessSeriesLeavesAfterTheMaximumWait(): void
    {
        $items = [];
        for ($i = 0; $i < 6; ++$i) {
            $items[] = $this->photo($i + 1, self::T0 + $i * 24);
        }
        $now = self::T0 + DiscordPhotoGrouper::ATTENTE_MAX + 1;

        self::assertSame([[1, 2, 3, 4, 5, 6]], DiscordPhotoGrouper::messagesReady($items, $now));
    }

    public function testQueueKeysUseAccountThenCallsignAndMission(): void
    {
        self::assertSame('u12', DiscordPhotoQueueService::authorKey(['author_user_id' => 12, 'author_callsign' => 'Viper']));
        self::assertSame('c:viper 1', DiscordPhotoQueueService::authorKey(['author_callsign' => ' Viper 1 ']));
        self::assertSame('op aube', DiscordPhotoQueueService::missionKey(['extra' => ['mission' => 'Op Aube'], 'mission_id' => '9']));
        self::assertSame('9', DiscordPhotoQueueService::missionKey(['mission_id' => 9]));
        self::assertSame('lot-7script', DiscordPhotoQueueService::batchId(['batch_id' => 'lot-7<script>']));
    }
}
