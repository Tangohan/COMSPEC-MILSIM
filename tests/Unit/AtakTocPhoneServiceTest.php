<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AtakToc\TocPhoneService;
use App\Services\AtakToc\TocPhoneStore;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Actions du poste TOC web sur les téléphones en jeu (base sqlite réelle, file des commandes et horloge injectées) :
 * réglage de la communauté (désactivé par défaut), notifier, faire sonner, localiser, trianguler, limites de débit,
 * cibles hors communauté, PNJ et hostiles, journal d'audit, visibilité côté cible, résultats et expirations.
 */
final class AtakTocPhoneServiceTest extends TestCase
{
    private PDO $pdo;

    private int $now = 1_800_000_000;

    /** @var list<array<string, mixed>> */
    private array $queue = [];

    /** @var array<int, array<string, mixed>> commandes telles que la file les présente (status, result_text, result) */
    private array $commands = [];

    /** @var list<array{0: string, 1: int, 2: ?int, 3: ?int, 4: array<string, mixed>}> */
    private array $audit = [];

    /** @var array<int, list<array<string, mixed>>|null> */
    private array $unitsByTenant = [];

    /** @var list<array<string, mixed>> */
    private array $relays = [];

    private TocPhoneService $svc;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_toc_phone_migration.php';
        $migrate($this->pdo);
        $this->now = 1_800_000_000;
        $this->queue = [];
        $this->commands = [];
        $this->audit = [];
        $this->relays = [
            ['relay_uid' => 'R1', 'pos_x' => 10600, 'pos_y' => 12100, 'range_m' => 2500, 'alive' => 1, 'display_name' => 'Relais NORD'],
            ['relay_uid' => 'R2', 'pos_x' => 9500, 'pos_y' => 12800, 'range_m' => 2500, 'alive' => 1, 'display_name' => 'Relais OUEST'],
            ['relay_uid' => 'R3', 'pos_x' => 10300, 'pos_y' => 11200, 'range_m' => 2500, 'alive' => 0, 'display_name' => 'Relais HS'],
        ];
        $this->unitsByTenant = [1 => [
            self::unit(1, 'ALPHA 1-1', '76561198000000001', 10000, 12000, 'linked', 5, ['side' => 'WEST']),
            self::unit(2, 'ALPHA 1-2', '76561198000000002', 10200, 12100, 'linked', 8, ['side' => 'WEST']),
            self::unit(3, 'BRAVO 2-1', '76561198000000003', 9800, 11800, 'delayed', 90, ['side' => 'WEST']),
            self::unit(4, 'CHARLIE 3', '76561198000000004', 9000, 11000, 'offline', 4000, ['side' => 'WEST']),
            self::unit(5, 'Garde 4', '', 10100, 12200, 'linked', 10, ['side' => 'EAST', 'enemy_ai' => true, 'is_ai' => true, 'source' => 'enemy']),
            self::unit(6, 'Rebelle 1', '76561198000000006', 10500, 12300, 'linked', 12, ['side' => 'EAST']),
            self::unit(7, 'Civil 1', '', 9900, 12050, 'linked', 15, ['side' => 'WEST', 'is_ai' => true]),
        ], 2 => [self::unit(1, 'ALPHA 1-1', '76561198999999999', 5000, 5000, 'linked', 5, ['side' => 'WEST'])]];
        $this->svc = $this->service();
    }

    /** @param array<string, mixed> $extra @return array<string, mixed> */
    private static function unit(int $id, string $callsign, string $steam, float $x, float $y, string $status, int $age, array $extra): array
    {
        if ($steam !== '') {
            $extra['steam_uid'] = $steam;
        }

        return ['id' => $id, 'call_sign' => $callsign, 'group_name' => 'ALPHA', 'status' => $status, 'pos_x' => $x, 'pos_y' => $y, 'age_seconds' => $age, 'map_id' => 1, 'extra' => $extra, 'battery_pct' => 80];
    }

    private function service(): TocPhoneService
    {
        return new TocPhoneService(
            $this->pdo,
            function (int $tenantId, int $mapId, string $cmd, string $uid, string $ref, string $label, array $args, array $wire, int $ttl, string $who, ?int $userId): ?int {
                $this->queue[] = compact('tenantId', 'mapId', 'cmd', 'uid', 'ref', 'label', 'args', 'wire', 'ttl', 'who', 'userId');
                $id = 900 + count($this->queue);
                $this->commands[$id] = ['status' => 'pending', 'result_text' => '', 'result' => null];

                return $id;
            },
            fn (int $tenantId, int $id): ?array => $this->commands[$id] ?? null,
            fn (int $tenantId, int $mapId, int $userId): ?array => $this->unitsByTenant[$tenantId] ?? [],
            fn (int $tenantId, int $mapId): array => $this->relays,
            function (string $action, int $tenantId, ?int $userId, ?int $id, array $details): void {
                $this->audit[] = [$action, $tenantId, $userId, $id, $details];
            },
            fn (): int => $this->now,
            'secret-de-test'
        );
    }

    private function enable(int $tenant = 1, bool $hideHostile = false): void
    {
        self::assertTrue($this->svc->saveSettings($tenant, 7, 'TOC-1', true, $hideHostile)['ok']);
    }

    /** @return array<string, array<string, mixed>> annuaire indexé par indicatif */
    private function phones(int $tenant = 1): array
    {
        $d = $this->svc->directory($tenant, 1, 7, 'WEST');
        self::assertTrue($d['ok']);

        return array_column($d['phones'], null, 'callsign');
    }

    private function ref(string $callsign, int $tenant = 1): string
    {
        return (string) $this->phones($tenant)[$callsign]['ref'];
    }

    public function testMigrationIsIdempotentAndSqlFileDeclaresTheSameColumns(): void
    {
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/atak_toc_phone_migration.php';
        $migrate($this->pdo);
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/2026_11_15_140_atak_toc_phone.sql');
        foreach (['atak_toc_phone_settings', 'atak_toc_phone_events'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
            preg_match('/CREATE TABLE IF NOT EXISTS ' . $table . ' \((.*?)\n\) ENGINE/s', $sql, $m);
            $declared = [];
            foreach (explode("\n", $m[1]) as $line) {
                if (preg_match('/^\s{2}([a-z_]+) [A-Z]/', $line, $c) === 1 && !in_array($c[1], ['PRIMARY', 'KEY'], true)) {
                    $declared[] = $c[1];
                }
            }
            $actual = array_column($this->pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name');
            sort($declared);
            sort($actual);
            self::assertSame($actual, $declared, $table);
        }
        self::assertStringContainsString('DROP TABLE IF EXISTS atak_toc_phone_events', (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/rollback/atak_toc_phone_down.sql'));
    }

    public function testDisabledByDefaultThenActivatedPerCommunity(): void
    {
        self::assertFalse($this->svc->status(1)['enabled']);
        $ref = $this->ref('ALPHA 1-2');
        $r = $this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $ref, 'Test', 'info', false, false);
        self::assertSame('disabled', $r['error']);
        self::assertSame([], $this->queue, 'rien n’est envoyé au jeu');
        $this->enable(1);
        self::assertTrue($this->svc->status(1)['enabled']);
        self::assertFalse($this->svc->status(2)['enabled'], 'autre communauté : toujours désactivée');
        self::assertTrue($this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $ref, 'Test', 'info', false, false)['ok']);
        $names = array_column($this->audit, 0);
        self::assertContains('atak_toc.phone.settings', $names);
        self::assertContains('atak_toc.phone.notify', $names);
    }

    public function testWithoutTheMigrationTheFunctionIsOffWithAClearError(): void
    {
        $svc = new TocPhoneService(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), null, null, fn (): array => []);
        self::assertFalse($svc->status(1)['ready']);
        self::assertSame('not_ready', $svc->saveSettings(1, 7, 'x', true, false)['error']);
        self::assertSame('not_ready', $svc->locate(1, 1, 7, 'x', 'WEST', 'p000000000000')['error']);
        self::assertSame([], $svc->events(1));
    }

    public function testDirectoryHidesSteamAccountsAndClassifiesPhones(): void
    {
        $d = $this->phones();
        self::assertCount(7, $d);
        $json = json_encode($d);
        self::assertStringNotContainsString('76561198', (string) $json, 'aucun compte Steam ne sort');
        self::assertMatchesRegularExpression('/^p[0-9a-f]{12}$/', $d['ALPHA 1-1']['ref']);
        self::assertSame('online', $d['ALPHA 1-1']['state']);
        self::assertSame('no_coverage', $d['BRAVO 2-1']['state']);
        self::assertSame('offline', $d['CHARLIE 3']['state']);
        self::assertSame('player', $d['ALPHA 1-1']['kind']);
        self::assertSame('npc', $d['Garde 4']['kind']);
        self::assertTrue($d['Garde 4']['hostile']);
        self::assertTrue($d['Rebelle 1']['hostile'], 'joueur du camp adverse');
        self::assertFalse($d['ALPHA 1-1']['hostile']);
        self::assertSame(80, $d['ALPHA 1-1']['battery']);
        self::assertFalse($d['Garde 4']['can']['notify']);
        self::assertFalse($d['Garde 4']['can']['locate']);
        self::assertTrue($d['Garde 4']['can']['triangulate']);
        self::assertFalse($d['Rebelle 1']['can']['notify'], 'pas de notification vers un hostile');
        self::assertTrue($d['Rebelle 1']['can']['locate']);
        self::assertTrue($d['ALPHA 1-1']['can']['notify']);
        self::assertSame('online', array_values($this->svc->directory(1, 1, 7, 'WEST')['phones'])[0]['state'], 'en ligne d’abord');
    }

    public function testPhoneRefsAreStableOpaqueAndPerCommunity(): void
    {
        $a = $this->ref('ALPHA 1-1', 1);
        self::assertSame($a, $this->ref('ALPHA 1-1', 1));
        self::assertNotSame($a, $this->ref('ALPHA 1-1', 2), 'même callsign, autre communauté : autre référence');
        self::assertNotSame($a, $this->ref('ALPHA 1-2', 1));
        self::assertStringNotContainsString('7656', $a);
    }

    public function testNotifyQueuesAShortMessageWithLevelAckAndAudit(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        $r = $this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $ref, 'Repli sur Rouge 2 : départ à 14h30 !', 'alert', true, false);
        self::assertTrue($r['ok'], json_encode($r));
        $q = $this->queue[0];
        self::assertSame('notify', $q['cmd']);
        self::assertSame('76561198000000002', $q['uid'], 'la commande vise le compte du joueur, sans le montrer au navigateur');
        self::assertSame(86400, $q['ttl'], 'notification : 24 h');
        self::assertSame('a', $q['wire']['lv']);
        self::assertSame(1, $q['wire']['ak']);
        self::assertSame('Repli sur Rouge 2 : départ à 14h30 !', TocPhoneService::unwireText($q['wire']['m']));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9 .,:\-@_]+$/', $q['wire']['m'], 'fil en ASCII sûr');
        $e = $this->svc->events(1)[0];
        self::assertSame('notify', $e['action']);
        self::assertSame('queued', $e['status']);
        self::assertSame('TOC-1', $e['operator']);
        self::assertTrue($e['ack_requested']);
        $audit = array_values(array_filter($this->audit, static fn (array $a): bool => $a[0] === 'atak_toc.phone.notify'))[0];
        self::assertSame(7, $audit[2]);
        self::assertSame($ref, $audit[4]['target']);
    }

    public function testNotifyValidation(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        self::assertSame('message_required', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, '   ', 'info', false, false)['error']);
        self::assertSame('message_too_long', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, str_repeat('a', 161), 'info', false, false)['error']);
        self::assertSame('message_too_long_encoded', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, str_repeat('é', 160), 'info', false, false)['error']);
        self::assertSame('level_invalid', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'ok', 'critique', false, false)['error']);
        self::assertSame('confirm_required', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'ok', 'urgent', false, false)['error'], 'urgent demande une confirmation');
        self::assertTrue($this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, str_repeat('a', 160), 'urgent', false, true)['ok']);
        self::assertSame('target_unknown', $this->svc->notify(1, 1, 7, 'T', 'WEST', 'pffffffffffff', 'ok', 'info', false, false)['error']);
        // Balises retirées : le texte reste du texte.
        $this->svc->notify(1, 1, 7, 'T', 'WEST', $this->ref('ALPHA 1-1'), '<b>Bonjour</b>', 'info', false, false);
        self::assertSame('Bonjour', TocPhoneService::unwireText(end($this->queue)['wire']['m']));
    }

    public function testWireTextRoundTripAndEscapes(): void
    {
        foreach (['Grille 123 456 : go', 'À l’est — « OK » ~=|@_', "tab\tretour\nligne", 'x'] as $s) {
            $w = TocPhoneService::wireText($s);
            self::assertMatchesRegularExpression('/^[A-Za-z0-9 .,:\-@_]*$/', $w);
            self::assertSame(trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $s) ?? ''), trim(TocPhoneService::unwireText($w)), $s);
        }
    }

    public function testWireTextMaxNeverCutsAnEscapeInTwo(): void
    {
        $long = str_repeat('é', 30);
        foreach ([5, 6, 11, 40] as $max) {
            $w = TocPhoneService::wireTextMax($long, $max);
            self::assertLessThanOrEqual($max, strlen($w));
            self::assertDoesNotMatchRegularExpression('/@\d*$/', $w, 'jamais un code coupé : ' . $w);
            self::assertSame(str_repeat('é', (int) (strlen($w) / 5)), TocPhoneService::unwireText($w));
        }
        self::assertSame('Cdt Mercier', TocPhoneService::wireTextMax('Cdt Mercier', 40));
    }

    public function testNotifyRateLimitIsTenPerMinutePerOperator(): void
    {
        $this->enable();
        $refs = [$this->ref('ALPHA 1-1'), $this->ref('ALPHA 1-2')];
        for ($i = 0; $i < 10; $i++) {
            // Les accusés ramènent les files ouvertes à zéro pour ne tester que le débit.
            $this->commands = array_map(static fn (array $c): array => ['status' => 'done'] + $c, $this->commands);
            $this->svc->events(1);
            $r = $this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $refs[$i % 2], 'm' . $i, 'info', false, false);
            self::assertTrue($r['ok'], 'message ' . $i . ' ' . json_encode($r));
        }
        $this->commands = array_map(static fn (array $c): array => ['status' => 'done'] + $c, $this->commands);
        $this->svc->events(1);
        $r = $this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $refs[0], 'onze', 'info', false, false);
        self::assertSame('rate_limited', $r['error']);
        self::assertSame(60, $r['retry_after']);
        // Un autre opérateur n'est pas bloqué ; la minute suivante non plus.
        self::assertTrue($this->svc->notify(1, 1, 8, 'TOC-2', 'WEST', $refs[0], 'autre', 'info', false, false)['ok']);
        $this->now += 61;
        self::assertTrue($this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $refs[0], 'plus tard', 'info', false, false)['ok']);
    }

    public function testAtMostThreeUnreadNotificationsPerPhone(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        for ($i = 0; $i < 3; $i++) {
            self::assertTrue($this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'n' . $i, 'info', true, false)['ok']);
        }
        self::assertSame('too_many_pending', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'n4', 'info', true, false)['error']);
    }

    public function testNoNotificationNorRingToNpcOrHostile(): void
    {
        $this->enable();
        foreach (['Garde 4', 'Civil 1', 'Rebelle 1'] as $cs) {
            $ref = $this->ref($cs);
            self::assertSame('target_not_player', $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'ok', 'info', false, false)['error'], $cs);
            self::assertSame('target_not_player', $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 10, 'ring', true)['error'], $cs);
        }
        self::assertSame([], $this->queue);
        $refused = $this->svc->store()->events(1, ['statuses' => ['refused']]);
        self::assertCount(6, $refused, 'les refus sont journalisés');
    }

    public function testRingNeedsConfirmationDurationAndModeAndIsLimited(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        self::assertSame('confirm_required', $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 10, 'ring', false)['error']);
        self::assertSame('duration_invalid', $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 2, 'ring', true)['error']);
        self::assertSame('duration_invalid', $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 31, 'ring', true)['error']);
        self::assertSame('mode_invalid', $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 10, 'cloche', true)['error']);
        $r = $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 12, 'vib', true);
        self::assertTrue($r['ok']);
        $q = end($this->queue);
        self::assertSame('ring', $q['cmd']);
        self::assertSame(12, $q['wire']['d']);
        self::assertSame('vib', $q['wire']['md']);
        self::assertSame(60, $q['ttl']);
        self::assertTrue($this->svc->ringStop(1, 1, 7, 'T', 'WEST', $ref)['ok']);
        self::assertSame('stop', end($this->queue)['cmd']);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 5, 'ring', true)['ok']);
        }
        self::assertSame('rate_limited', $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 5, 'ring', true)['error']);
    }

    public function testLocateWaitsForThePhoneAndOnlyShowsTheReportedPosition(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        $r = $this->svc->locate(1, 1, 7, 'TOC-1', 'WEST', $ref);
        self::assertTrue($r['ok']);
        self::assertSame(1, $this->queue[0]['wire']['vis'], 'visible par la cible : entrée « localisé par le TOC »');
        self::assertSame(60, $this->queue[0]['ttl']);
        $e = $this->svc->events(1)[0];
        self::assertSame('queued', $e['status']);
        self::assertNull($e['fix'], 'aucune position avant la réponse du téléphone');
        // Le téléphone répond.
        $this->commands[$r['command_id']] = ['status' => 'done', 'result_text' => 'ok', 'result' => ['x' => 10321.4, 'y' => 12100.2, 'acc' => 12.5]];
        $e = $this->svc->events(1)[0];
        self::assertSame('done', $e['status']);
        self::assertEquals(10321.4, $e['fix']['x']);
        self::assertEquals(12.5, $e['fix']['accuracy_m']);
        self::assertSame('phone', $e['fix']['source']);
        self::assertSame(0, $e['fix']['age']);
        $this->now += 90;
        self::assertSame(90, $this->phones()['ALPHA 1-2']['fix']['age'], 'âge de la donnée');
        self::assertCount(1, $this->svc->sheet(1, 1, 7, 'WEST', $ref)['fixes']);
    }

    public function testLocateWithoutAnswerIsPositionUnknownNeverInvented(): void
    {
        $this->enable();
        $ref = $this->ref('BRAVO 2-1');
        $r = $this->svc->locate(1, 1, 7, 'TOC-1', 'WEST', $ref);
        self::assertTrue($r['ok']);
        $this->now += 61;
        $e = $this->svc->events(1)[0];
        self::assertSame('expired', $e['status']);
        self::assertSame('position_unknown', $e['result']);
        self::assertNull($e['fix']);
        self::assertNull($this->phones()['BRAVO 2-1']['fix']);
    }

    public function testLocateAnswerWithoutValidPositionIsPositionUnknown(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        foreach ([['x' => 'abc', 'y' => 1], ['x' => 0, 'y' => 0], ['x' => 9e9, 'y' => 12], []] as $i => $bad) {
            $this->now += 20;
            $r = $this->svc->locate(1, 1, 7, 'TOC-1', 'WEST', $ref);
            self::assertTrue($r['ok'], 'essai ' . $i);
            $this->commands[$r['command_id']] = ['status' => 'done', 'result_text' => '', 'result' => $bad];
            $e = $this->svc->events(1)[0];
            self::assertSame('failed', $e['status'], 'essai ' . $i);
            self::assertSame('position_unknown', $e['result']);
            self::assertNull($e['fix']);
        }
    }

    public function testLocateIsLimitedToOnePerFifteenSecondsPerPhone(): void
    {
        $this->enable();
        $a = $this->ref('ALPHA 1-1');
        $b = $this->ref('ALPHA 1-2');
        self::assertTrue($this->svc->locate(1, 1, 7, 'T', 'WEST', $a)['ok']);
        $this->now += 10;
        $r = $this->svc->locate(1, 1, 8, 'T2', 'WEST', $a);
        self::assertSame('rate_limited', $r['error'], 'même téléphone, autre opérateur : aussi limité');
        self::assertSame(5, $r['retry_after']);
        self::assertTrue($this->svc->locate(1, 1, 7, 'T', 'WEST', $b)['ok'], 'autre téléphone');
        $this->now += 6;
        self::assertTrue($this->svc->locate(1, 1, 7, 'T', 'WEST', $a)['ok']);
    }

    public function testLocateRefusedOnNpcAndJournalHiddenOnlyForHostiles(): void
    {
        $this->enable(1, true);
        self::assertSame('target_not_player', $this->svc->locate(1, 1, 7, 'T', 'WEST', $this->ref('Garde 4'))['error']);
        // Joueur hostile + réglage : journal masqué. Joueur allié : toujours visible.
        $this->svc->locate(1, 1, 7, 'T', 'WEST', $this->ref('Rebelle 1'));
        self::assertSame(0, end($this->queue)['wire']['vis']);
        $this->svc->locate(1, 1, 7, 'T', 'WEST', $this->ref('ALPHA 1-1'));
        self::assertSame(1, end($this->queue)['wire']['vis'], 'jamais masqué pour un allié');
        $this->enable(1, false);
        $this->now += 20;
        $this->svc->locate(1, 1, 7, 'T', 'WEST', $this->ref('Rebelle 1'));
        self::assertSame(1, end($this->queue)['wire']['vis'], 'par défaut : visible');
    }

    public function testTriangulationUsesRelaysAndAlliedPhonesAndDeliversAfterTheListeningTime(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-1');
        $r = $this->svc->triangulate(1, 1, 7, 'TOC-1', 'WEST', $ref, 'Altis');
        self::assertTrue($r['ok'], json_encode($r));
        self::assertGreaterThanOrEqual(6, $r['listen_s']);
        self::assertLessThanOrEqual(10, $r['listen_s']);
        $e = $this->svc->events(1)[0];
        self::assertSame('running', $e['status']);
        self::assertNull($e['triangulation'], 'résultat retenu pendant l’écoute');
        self::assertNull($e['fix']);
        self::assertSame($r['listen_s'], $e['ready_in']);
        $this->now += $r['listen_s'];
        $e = $this->svc->events(1)[0];
        self::assertSame('done', $e['status']);
        $t = $e['triangulation'];
        self::assertTrue($t['is_estimate']);
        // Sources : 2 relais intacts (le 3e est hors service) + téléphones alliés en ligne (ALPHA 1-2 ; ni BRAVO hors couverture, ni hostiles).
        self::assertSame(3, $t['sources_available']);
        self::assertSame(3, $t['heard']);
        self::assertSame('estimate', $t['status']);
        self::assertNotNull($e['fix']);
        self::assertSame('estimate', $e['fix']['source']);
        self::assertGreaterThan(0, $e['fix']['accuracy_m']);
        $ids = array_column($t['readings'], 'kind');
        sort($ids);
        self::assertSame(['phone', 'relay', 'relay'], $ids);
        foreach ($t['readings'] as $x) {
            self::assertStringNotContainsString('7656', json_encode($x));
        }
        // Visible pour la cible (trace) : une commande « trace » est partie.
        $trace = array_values(array_filter($this->queue, static fn (array $q): bool => $q['cmd'] === 'trace'));
        self::assertCount(1, $trace);
        self::assertSame('76561198000000001', $trace[0]['uid']);
        self::assertSame('tri', $trace[0]['wire']['kd']);
    }

    public function testTriangulationIsDeterministicInTheThirtySecondWindow(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-1');
        $this->now = 1_800_000_010;
        $a = $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $ref);
        $this->now += 40;
        $this->svc->events(1);
        $first = $this->svc->events(1, $ref)[0]['triangulation'];
        // Même fenêtre de 30 s sur une base vierge : même estimation.
        $this->setUp();
        $this->enable();
        $this->now = 1_800_000_010;
        $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('ALPHA 1-1'));
        $this->now += 40;
        self::assertSame($first['position'], $this->svc->events(1)[0]['triangulation']['position']);
        self::assertTrue($a['ok']);
    }

    public function testTriangulationCapsAndGuards(): void
    {
        $this->enable();
        $refs = [$this->ref('ALPHA 1-1'), $this->ref('ALPHA 1-2'), $this->ref('Garde 4')];
        self::assertTrue($this->svc->triangulate(1, 1, 7, 'T', 'WEST', $refs[0])['ok']);
        self::assertTrue($this->svc->triangulate(1, 1, 7, 'T', 'WEST', $refs[1])['ok']);
        self::assertSame('too_many_triangulations', $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $refs[2])['error'], '2 par opérateur');
        self::assertTrue($this->svc->triangulate(1, 1, 8, 'T2', 'WEST', $refs[2])['ok'], 'un autre opérateur : jusqu’à 3 pour la communauté');
        self::assertSame('too_many_triangulations', $this->svc->triangulate(1, 1, 9, 'T3', 'WEST', $this->ref('Rebelle 1'))['error'], '3 pour la communauté');
        $this->now += 11;
        $this->svc->events(1);
        $r = $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $refs[0]);
        self::assertTrue($r['ok'] || $r['error'] === 'rate_limited');
        // Position périmée ou absente : refus, jamais d'invention.
        $this->unitsByTenant[1][2]['age_seconds'] = 5000;
        self::assertSame('stale_position', $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('BRAVO 2-1'))['error']);
        $this->unitsByTenant[1][6]['pos_x'] = null;
        $this->unitsByTenant[1][6]['pos_y'] = null;
        self::assertSame('no_position', $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('Civil 1'))['error']);
    }

    public function testTriangulationWithoutAnySourceAnswersNoneNotAPosition(): void
    {
        $this->enable();
        $this->relays = [];
        $this->unitsByTenant[1] = [self::unit(1, 'SEUL', '76561198000000001', 10000, 12000, 'linked', 5, ['side' => 'WEST'])];
        $r = $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('SEUL'));
        self::assertTrue($r['ok']);
        $this->now += 12;
        $e = $this->svc->events(1)[0];
        self::assertSame('none', $e['triangulation']['status']);
        self::assertNull($e['fix']);
        self::assertNull($e['triangulation']['position']);
    }

    public function testTriangulationTraceHiddenOnlyForHostilesWhenTheCommunityAsksForIt(): void
    {
        $this->enable(1, true);
        $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('Rebelle 1'));
        self::assertCount(0, array_filter($this->queue, static fn (array $q): bool => $q['cmd'] === 'trace'), 'hostile + réglage : pas d’entrée de journal');
        $this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('ALPHA 1-2'));
        self::assertCount(1, array_filter($this->queue, static fn (array $q): bool => $q['cmd'] === 'trace'), 'allié : toujours visible');
    }

    public function testNpcTriangulationSendsNothingToAnyPhone(): void
    {
        $this->enable();
        self::assertTrue($this->svc->triangulate(1, 1, 7, 'T', 'WEST', $this->ref('Garde 4'))['ok']);
        self::assertSame([], $this->queue);
    }

    public function testAnotherCommunityCannotReachThisCommunitysPhones(): void
    {
        $this->enable(1);
        $this->enable(2);
        $ref1 = $this->ref('ALPHA 1-2', 1);
        foreach (['notify' => fn () => $this->svc->notify(2, 1, 8, 'X', 'WEST', $ref1, 'intrus', 'info', false, false),
            'locate' => fn () => $this->svc->locate(2, 1, 8, 'X', 'WEST', $ref1),
            'ring' => fn () => $this->svc->ring(2, 1, 8, 'X', 'WEST', $ref1, 5, 'ring', true),
            'tri' => fn () => $this->svc->triangulate(2, 1, 8, 'X', 'WEST', $ref1),
            'sheet' => fn () => $this->svc->sheet(2, 1, 8, 'WEST', $ref1)] as $name => $call) {
            $r = $call();
            self::assertFalse($r['ok'], $name);
            self::assertSame('target_unknown', $r['error'], $name);
        }
        self::assertSame([], $this->queue);
        self::assertSame([], $this->svc->events(2), 'rien dans le journal de l’autre communauté');
        // Le journal d'une communauté ne montre pas les demandes de l'autre.
        $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref1, 'ok', 'info', false, false);
        self::assertSame([], $this->svc->events(2));
        self::assertCount(1, $this->svc->events(1));
    }

    public function testDegradedViewRefusesToActOnPositions(): void
    {
        $this->enable();
        $svc = new TocPhoneService($this->pdo, null, null, fn (): ?array => null, fn (): array => [], static function (): void {
        }, fn (): int => $this->now, 'secret-de-test');
        self::assertSame('view_degraded', $svc->directory(1, 1, 7, 'WEST')['error']);
        self::assertSame('view_degraded', $svc->locate(1, 1, 7, 'T', 'WEST', 'pabcdefabcdef')['error']);
    }

    public function testFailedExpiredAndDeliveredStatusesFollowTheCommandQueue(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        $n = $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'accusé demandé', 'info', true, false);
        $this->commands[$n['command_id']] = ['status' => 'accepted', 'result_text' => 'reçu', 'result' => null];
        self::assertSame('delivered', $this->svc->events(1)[0]['status'], 'reçu sur le téléphone');
        $this->commands[$n['command_id']] = ['status' => 'done', 'result_text' => 'lu', 'result' => null];
        self::assertSame('done', $this->svc->events(1)[0]['status'], 'accusé de réception (lu)');
        $this->now += 20;
        $r = $this->svc->ring(1, 1, 7, 'T', 'WEST', $ref, 5, 'ring', true);
        $this->commands[$r['command_id']] = ['status' => 'failed', 'result_text' => 'téléphone éteint', 'result' => null];
        self::assertSame('failed', $this->svc->events(1)[0]['status']);
        $this->now += 100;
        $m = $this->svc->notify(1, 1, 7, 'T', 'WEST', $ref, 'trop tard', 'info', false, false);
        $this->now += 86401;
        self::assertSame('expired', $this->svc->events(1)[0]['status'], 'notification : 24 h');
        self::assertTrue($m['ok']);
    }

    public function testJournalKeepsWhoWhatWhichPhoneWhenAndResult(): void
    {
        $this->enable();
        $ref = $this->ref('ALPHA 1-2');
        $this->svc->notify(1, 1, 7, 'TOC-1', 'WEST', $ref, 'Salut', 'info', false, false);
        $raw = $this->pdo->query('SELECT * FROM atak_toc_phone_events ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('TOC-1', $raw['operator']);
        self::assertSame(7, (int) $raw['user_id']);
        self::assertSame('ALPHA 1-2', $raw['target_label']);
        self::assertSame($ref, $raw['target_ref']);
        self::assertSame('notify', $raw['action']);
        self::assertSame('Salut', $raw['message']);
        self::assertSame(TocPhoneStore::dt($this->now), $raw['created_at']);
        self::assertStringNotContainsString('7656', implode('|', array_map('strval', $raw)), 'aucun compte Steam dans le journal');
    }

    public function testTerrainFactorAndAffiliation(): void
    {
        self::assertSame(0.25, TocPhoneService::terrainFactor('Altis'));
        self::assertSame(0.5, TocPhoneService::terrainFactor('Tanoa'));
        self::assertSame(0.3, TocPhoneService::terrainFactor('inconnue'));
        self::assertSame('F', TocPhoneService::affiliation('', 'WEST', false));
        self::assertSame('F', TocPhoneService::affiliation('BLUFOR', 'WEST', false));
        self::assertSame('H', TocPhoneService::affiliation('EAST', 'WEST', false));
        self::assertSame('H', TocPhoneService::affiliation('WEST', 'WEST', true));
        self::assertSame('N', TocPhoneService::affiliation('CIV', 'WEST', false));
        self::assertSame('U', TocPhoneService::affiliation('GUER', 'WEST', false));
    }
}
