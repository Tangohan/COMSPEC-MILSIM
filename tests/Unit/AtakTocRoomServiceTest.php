<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakWebCommandRepository;
use App\Services\AtakToc\TocRoom;
use App\Services\AtakToc\TocRoomService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Salle TOC partagée entre le jeu et le web : instantané du jeu (bornes, UID retirés, révision plus ancienne
 * ignorée), actions du web (validation, rendu immédiat, file des commandes WC1 en ASCII), règle d'autorité (le jeu
 * fait foi en liaison ; action reprise ou perdue), chronos, cloisonnement entre communautés et camps.
 */
final class AtakTocRoomServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var list<array{cmd: string, ref: string, args: array<string, scalar>, ttl: int}> */
    private array $queue = [];

    /** @var array<int, string> */
    private array $status = [];

    private TocRoomService $svc;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = AtakTocPlanServiceTest::open();
        $this->queue = [];
        $this->status = [];
        $this->svc = new TocRoomService(
            $this->pdo,
            function (int $t, int $m, string $cmd, string $ref, array $args, int $ttl): ?int {
                $this->queue[] = ['cmd' => $cmd, 'ref' => $ref, 'args' => $args, 'ttl' => $ttl];

                return 500 + count($this->queue);
            },
            fn (int $t, int $id): ?string => $this->status[$id] ?? 'pending'
        );
    }

    /** @return array<string, mixed> */
    private static function snapshot(int $rev = 3, array $wc = []): array
    {
        return [
            'channel' => 'toc_room', 'side' => 'WEST', 'mission_key' => 'op_raven', 'world' => 'Altis', 'rev' => $rev, 'wc' => $wc,
            'room' => [
                'timers' => [['T1', 'H-HOUR', 'AT', 720, true, 0], ['T2', 'EXFIL', 'DOWN', 600, true, 120]],
                'log' => [['12:00:01', 'CR', 'ALPHA 1-1', 'Contact NE']],
                'excheck' => [['E1', 1, 'Départ', 'QUEBEC', 'TOUS', '11:15', '']],
                'requests' => [['R1', '12:31:00', 'MEDEVAC', 'CHARLIE 3', '76561198000000000', '043 132', '1 urgence A', 'RECUE', '', [4330, 13220]]],
                'flash' => [],
                'posts' => [['BRAVO-6', 'Adj Mercier', '76561198000000001', '12:20']],
                'kills' => [['ALPHA', 1, 0, 3, 0]],
                'sats' => [[3800, 13000, 5000, 'AUTO', '', '', 'Adj Mercier', '76561198000000001', [false, true]]],
                'screens' => [['Mur gauche', 'split', 'Salle BRAVO']],
                'local' => '13:35:00',
            ],
        ];
    }

    public function testSnapshotIsNormalisedWithoutPlayerUids(): void
    {
        self::assertTrue($this->svc->ingestFromGame(1, 1, self::snapshot(), 1000)['ok']);
        $v = $this->svc->view(1, 1, 'WEST', 1010);
        $st = $v['state'];
        self::assertTrue($v['game']['live']);
        self::assertSame('altis', $v['game']['world']);
        self::assertSame(['T1', 'T2'], array_column($st['timers'], 'id'));
        self::assertSame('-00:07:50', $st['timers'][1]['text'], '600 s, 120 + 10 écoulées');
        self::assertEquals(1000, $st['sats'][0]['hw'], 'emprise bornée');
        self::assertSame('SPLIT', $st['screens'][0]['display']);
        self::assertSame('13:35:00', $st['clock']['local']);
        $json = json_encode($st) ?: '';
        self::assertStringNotContainsString('7656119800000000', $json, 'aucun identifiant Steam publié');
        self::assertEquals([4330, 13220], $st['requests'][0]['pos']);
        // Pas de fuite : autre communauté, autre camp.
        self::assertSame([], $this->svc->view(2, 1, 'WEST', 1010)['state']['timers']);
        self::assertSame([], $this->svc->view(1, 1, 'EAST', 1010)['state']['timers']);
        self::assertFalse($this->svc->view(1, 1, 'WEST', 1000 + TocRoomService::LIVE_SECONDS + 5)['game']['live']);
    }

    public function testOlderRevisionOfSameMissionIsIgnored(): void
    {
        $this->svc->ingestFromGame(1, 1, self::snapshot(5), 1000);
        self::assertSame('stale', $this->svc->ingestFromGame(1, 1, self::snapshot(4), 1001)['reason']);
        $other = self::snapshot(1);
        $other['mission_key'] = 'op_suivante';
        self::assertTrue($this->svc->ingestFromGame(1, 1, $other, 1002)['ok'], 'nouvelle mission : révision repart de zéro');
        self::assertSame('invalid', $this->svc->ingestFromGame(1, 1, ['side' => 'MARS', 'room' => []], 1003)['reason']);
    }

    public function testWebActionsShowAtOnceAndAreQueuedForTheGame(): void
    {
        $this->svc->ingestFromGame(1, 1, self::snapshot(), 1000);
        self::assertSame('action_unknown', $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'rm', [], 1001)['reason']);
        self::assertSame('value_invalid', $this->svc->act(1, 1, 'WEST', 7, 'x', 'timerAdd', ['name' => 'X', 'kind' => 'AT', 'value' => 5000], 1001)['reason']);
        self::assertSame('time_invalid', $this->svc->act(1, 1, 'WEST', 7, 'x', 'exAdd', ['ev' => 'Assaut', 'at' => '25:00'], 1001)['reason']);
        self::assertSame('request_invalid', $this->svc->act(1, 1, 'WEST', 7, 'x', 'reqSet', ['id' => 'R1', 'status' => 'PERDUE'], 1001)['reason']);

        $add = $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'timerAdd', ['name' => 'relève', 'kind' => 'DOWN', 'value' => 300], 1001);
        self::assertTrue($add['ok']);
        $wid = 'W' . $add['action_id'];
        $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'timerStart', ['id' => $wid], 1002);
        $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'exDone', ['id' => 'E1'], 1003);
        $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'reqSet', ['id' => 'R1', 'status' => 'PRISE', 'answer' => 'Hélico décollé, ETA 8 min'], 1004);
        $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'flash', ['text' => 'Contact nord, tous aux abris'], 1005);
        $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'kill', ['el' => 'alpha', 'key' => 'ekia', 'n' => 2], 1006);

        $st = $this->svc->view(1, 1, 'WEST', 1010)['state'];
        $t = $st['timers'][2];
        self::assertSame([$wid, 'RELÈVE', true, true], [$t['id'], $t['name'], $t['running'], $t['pending']]);
        self::assertSame('-00:04:52', $t['text']);
        self::assertSame('00:16', $st['excheck'][0]['done']);
        self::assertSame(['PRISE', 'Hélico décollé, ETA 8 min'], [$st['requests'][0]['status'], $st['requests'][0]['answer']]);
        self::assertSame('Contact nord, tous aux abris', $st['flash']['text']);
        self::assertSame(5, $st['kills'][0]['ekia']);
        self::assertContains('FLASH', array_column($st['log'], 'kind'));

        self::assertSame(['timeradd', 'timerstart', 'exdone', 'reqset', 'flash', 'kill'], array_column($this->queue, 'cmd'));
        self::assertSame(TocRoomService::TTL['flash'], $this->queue[4]['ttl']);
        foreach ($this->queue as $q) {
            $wire = AtakWebCommandRepository::buildWire('toc', '*', $q['cmd'], $q['ref'], $q['args']);
            self::assertLessThanOrEqual(480, strlen($wire));
            self::assertMatchesRegularExpression('/^[A-Za-z0-9 .:,_@~=*\-]+$/', $wire, 'fil ASCII');
        }
        self::assertSame('Helico decolle, ETA 8 min', $this->queue[3]['args']['an']);
        self::assertSame($wid, $this->queue[1]['ref']);
    }

    public function testGameAuthorityAppliedAndDroppedActions(): void
    {
        $this->svc->ingestFromGame(1, 1, self::snapshot(3), 1000);
        $a = $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'log', ['text' => 'Fréquence de repli 47.300'], 1001);
        $b = $this->svc->act(1, 1, 'WEST', 7, 'Adj Mercier', 'exAdd', ['ev' => 'Assaut', 'cw' => 'victor', 'el' => 'alpha', 'at' => '13:05'], 1002);
        self::assertSame(2, $this->svc->view(1, 1, 'WEST', 1003)['game']['pending']);
        // Le jeu a appliqué la première commande (501) ; la seconde (502) a expiré sans être reprise.
        $this->status[502] = 'expired';
        $snap = self::snapshot(4, [501]);
        $snap['room']['log'][] = ['12:01:00', 'NOTE', 'Adj Mercier', 'Frequence de repli 47.300'];
        $this->svc->ingestFromGame(1, 1, $snap, 1010);
        $v = $this->svc->view(1, 1, 'WEST', 1011);
        self::assertSame(0, $v['game']['pending']);
        self::assertCount(2, $v['state']['log'], 'plus de doublon : la ligne vient du jeu');
        self::assertCount(1, $v['state']['excheck'], 'action perdue : le jeu fait foi');
        $journal = $this->svc->recentActions(1, 1, 'WEST');
        self::assertSame(['dropped', 'applied'], array_column($journal, 'status'));
        self::assertSame([(int) $b['action_id'], (int) $a['action_id']], array_column($journal, 'id'));
    }

    public function testWithoutGameTheWebHoldsTheRoom(): void
    {
        $r = $this->svc->act(3, 1, 'EAST', 7, 'Cne Martin', 'timerAdd', ['name' => 'H-HOUR', 'kind' => 'AT', 'value' => 600], 2000);
        $this->svc->act(3, 1, 'EAST', 7, 'Cne Martin', 'log', ['text' => 'Poste web ouvert'], 2001);
        $v = $this->svc->view(3, 1, 'EAST', 2005);
        self::assertFalse($v['game']['live']);
        self::assertNull($v['game']['seen_at']);
        self::assertSame('W' . $r['action_id'], $v['state']['timers'][0]['id']);
        self::assertSame('H-09:26:35', $v['state']['timers'][0]['text'], 'heure H 10:00Z vue à 00:33:25Z');
        // Reste affiché tant que le jeu n'est pas revenu, même commande expirée.
        $this->status[501] = 'expired';
        self::assertCount(1, $this->svc->view(3, 1, 'EAST', 2000 + 3600)['state']['timers']);
        self::assertSame([], $this->svc->view(3, 1, 'EAST', 2000 + TocRoomService::KEEP_SECONDS + 10)['state']['timers'], '12 h au plus');
        $this->svc->reset(3, 1, 'EAST');
        self::assertSame([], $this->svc->view(3, 1, 'EAST', 2010)['state']['log']);
    }

    public function testTimerTextMatchesTheGame(): void
    {
        $base = ['acc' => 0, 'base_at' => 100, 'running' => true, 'value' => 0];
        self::assertSame(['+00:01:40', 'RUN'], TocRoom::timerText(['kind' => 'UP', 'acc' => 40, 'base_at' => 100, 'running' => true, 'value' => 0], 160));
        self::assertSame(['+00:00:10', 'OVER'], TocRoom::timerText(['kind' => 'DOWN', 'value' => 50] + $base, 160));
        self::assertSame(['-00:00:50', 'STOP'], TocRoom::timerText(['kind' => 'DOWN', 'value' => 50, 'running' => false] + $base, 160));
        // Heure H 12:00Z vue à 11:59:00Z puis à 12:01:00Z.
        self::assertSame(['H-00:01:00', 'RUN'], TocRoom::timerText(['kind' => 'AT', 'value' => 720] + $base, 86400 * 3 + 11 * 3600 + 59 * 60));
        self::assertSame(['H+00:01:00', 'OVER'], TocRoom::timerText(['kind' => 'AT', 'value' => 720] + $base, 86400 * 3 + 12 * 3600 + 60));
    }
}
