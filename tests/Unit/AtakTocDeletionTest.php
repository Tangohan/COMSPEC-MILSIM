<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\AtakToc\Purge\TocDeletionService;
use App\Services\AtakToc\Purge\TocElementStore;
use App\Services\AtakToc\Purge\TocSoftDelete;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\TocPurgeFixture as F;

/**
 * Suppression douce de la carte du poste TOC : corbeille de 15 minutes, annulation, expiration, sélection multiple, épinglés
 * (confirmation renforcée), droits (ses éléments, ceux des autres), cloisonnement par communauté et par carte, pierres
 * tombales du jeu, mode dégradé sans migration, archivage de la sélection. Base SQLite réelle, migration 4750 rejouée.
 */
final class AtakTocDeletionTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;

    private int $now;

    /** @var list<array{string, int, int, int, array<string, mixed>}> */
    private array $audits = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        TocSoftDelete::force(null);
        $this->pdo = F::open();
        $this->now = F::now();
        $this->audits = [];
    }

    protected function tearDown(): void
    {
        TocSoftDelete::force(null);
    }

    private function svc(?int $at = null, ?bool $soft = null): TocDeletionService
    {
        $at ??= $this->now;

        return new TocDeletionService(
            new TocElementStore($this->pdo, $soft),
            static fn (): int => $at,
            function (string $action, int $tenant, int $user, int $map, array $details): void {
                $this->audits[] = [$action, $tenant, $user, $map, $details];
            }
        );
    }

    /** @return array{userId: int, aliases: list<string>, manage: bool, purge: bool} */
    private static function actor(int $user = 7, string $alias = 'Alpha', bool $manage = false, bool $purge = false): array
    {
        return ['userId' => $user, 'aliases' => [$alias], 'manage' => $manage, 'purge' => $purge];
    }

    /** @return list<array{table: string, id: int}> */
    private static function refs(string $table, int ...$ids): array
    {
        return array_map(static fn (int $id): array => ['table' => $table, 'id' => $id], $ids);
    }

    private function live(int $tenant = 1, int $map = 1): int
    {
        return count((new TocElementStore($this->pdo, true))->elements($tenant, $map));
    }

    public function testMigrationStepIsReplayableAndCreatesColumnsAndJournal(): void
    {
        $before = $this->pdo->changes;
        $out = F::migrate($this->pdo);
        self::assertSame($before, $this->pdo->changes, 'rejouée : aucun changement de schéma');
        self::assertStringContainsString('déjà en place', $out);
        $cols = array_column($this->pdo->query("PRAGMA table_info('atak_markers')")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach (['deleted_at', 'deleted_by', 'delete_batch'] as $c) {
            self::assertContains($c, $cols);
        }
        $cols = array_column($this->pdo->query("PRAGMA table_info('atak_map_shapes')")->fetchAll(PDO::FETCH_ASSOC), 'name');
        self::assertContains('delete_batch', $cols);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_toc_purges')->fetchColumn());
    }

    public function testSoftDeleteMovesToTrashAndUndoRestoresWithinFifteenMinutes(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1000, 2000], 'text' => 'Point A', 'author' => 'Alpha']);
        $s = F::shape($this->pdo, 1, 1, 'POLYGON', ['coordinates' => [[0, 0], [100, 0], [100, 100]]], 'Alpha');
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha', true), [...self::refs('marker', $a), ...self::refs('shape', $s)]);
        self::assertTrue($res['ok']);
        self::assertSame(2, $res['count']);
        self::assertTrue($res['soft']);
        self::assertSame($this->now + 900, $res['undoUntil']);
        self::assertSame(1, preg_match('/^[0-9a-f]{16}$/', $res['batch']));
        self::assertSame(0, $this->live(), 'plus affichés');
        $raw = json_decode((string) $this->pdo->query("SELECT marker_data FROM atak_markers WHERE id = $a")->fetchColumn(), true);
        self::assertTrue($raw['suppressed'], 'état lu par toutes les lectures du site');
        $trash = $this->svc()->trash(1, 1, self::actor(7, 'Alpha', true));
        self::assertSame(1, count($trash['batches']));
        self::assertSame(2, $trash['batches'][0]['count']);
        self::assertSame($this->now + 900, $trash['batches'][0]['until']);

        $undo = $this->svc($this->now + 600)->undo(1, 1, self::actor(7, 'Alpha'), $res['batch']);
        self::assertTrue($undo['ok']);
        self::assertSame(2, $undo['restored']);
        self::assertSame(2, $this->live());
        $raw = json_decode((string) $this->pdo->query("SELECT marker_data FROM atak_markers WHERE id = $a")->fetchColumn(), true);
        self::assertArrayNotHasKey('suppressed', $raw);
        self::assertSame('Point A', $raw['text']);
        self::assertNull($this->pdo->query("SELECT deleted_at FROM atak_map_shapes WHERE id = $s")->fetchColumn() ?: null);
    }

    public function testUndoAfterFifteenMinutesIsRefusedAndTrashIsPurgedForGood(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Alpha']);
        $s = F::shape($this->pdo, 1, 1, 'LINE', ['coordinates' => [[0, 0], [10, 10]]]);
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha', true), [...self::refs('marker', $a), ...self::refs('shape', $s)]);
        $late = $this->now + 901;
        $undo = $this->svc($late)->undo(1, 1, self::actor(7, 'Alpha'), $res['batch']);
        self::assertFalse($undo['ok']);
        self::assertSame('expired', $undo['error']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_markers')->fetchColumn(), 'purge définitive automatique');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_map_shapes')->fetchColumn());
    }

    public function testGameMarkerStaysAsTombstoneFortyEightHoursButCannotBeUndone(): void
    {
        $g = F::marker($this->pdo, 1, 1, ['pos' => [3000, 4000], 'text' => 'Mortier', 'author' => 'Alpha', 'source' => 'arma'], '_USER_DEFINED #12/3');
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha', true), self::refs('marker', $g));
        self::assertTrue($res['ok']);
        $store = new TocElementStore($this->pdo, true);
        $t = $store->gameTombstones(1, 1, 0, 0, $this->now, 40);
        self::assertSame(1, count($t));
        self::assertSame('_USER_DEFINED #12/3', $t[0]['arma_name']);
        self::assertSame($this->now, $t[0]['at']);
        // 20 minutes plus tard : plus annulable, mais la pierre tombale est toujours servie au jeu.
        $later = $this->now + 1200;
        self::assertFalse($this->svc($later)->undo(1, 1, self::actor(7, 'Alpha'), $res['batch'])['ok']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_markers')->fetchColumn());
        self::assertSame(1, count($store->gameTombstones(1, 1, 0, 0, $later, 40)));
        // 49 heures plus tard : la ligne est retirée.
        $store->expire(null, $this->now + 49 * 3600);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_markers')->fetchColumn());
    }

    public function testTombstoneCursorIsOrderedBoundedAndTenantScoped(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = F::marker($this->pdo, 1, 1, ['pos' => [$i, $i], 'text' => 'M' . $i, 'author' => 'Alpha'], '_USER_DEFINED #' . $i);
        }
        $other = F::marker($this->pdo, 2, 1, ['pos' => [9, 9], 'text' => 'X'], '_USER_DEFINED #99');
        $this->svc()->delete(1, 1, self::actor(7, 'Alpha', true), self::refs('marker', ...$ids));
        $this->svc()->delete(2, 1, self::actor(8, 'Zed', true), self::refs('marker', $other));
        $store = new TocElementStore($this->pdo, true);
        $first = $store->gameTombstones(1, 1, 0, 0, $this->now, 2);
        self::assertSame([$ids[0], $ids[1]], array_column($first, 'id'));
        $last = $first[1];
        $next = $store->gameTombstones(1, 1, $last['at'], $last['id'], $this->now, 40);
        self::assertSame([$ids[2], $ids[3], $ids[4]], array_column($next, 'id'), 'même seconde : le curseur avance par identifiant');
        self::assertSame([], $store->gameTombstones(1, 1, $next[2]['at'], $next[2]['id'], $this->now, 40), 'rejouer le curseur final ne renvoie rien');
        self::assertNotContains($other, array_column($first + $next, 'id'));
        self::assertSame([['id' => 'del:' . $ids[0], 'layerId' => 1, 'markerData' => $this->rowJson($first[0]), 'updated_at' => null]], [TocElementStore::tombstoneRows([$first[0]])[0]]);
        self::assertSame([1700000000, 5], TocElementStore::parseCursor('1700000000:5'));
        self::assertSame([1700000000, 0], TocElementStore::parseCursor('1700000000'));
        self::assertNull(TocElementStore::parseCursor('abc'));
        self::assertNull(TocElementStore::parseCursor('1; DROP TABLE'));
    }

    /** @param array<string, mixed> $t */
    private function rowJson(array $t): string
    {
        return TocElementStore::tombstoneRows([$t])[0]['markerData'];
    }

    public function testTombstoneRowsKeepTheGameDelContract(): void
    {
        $row = TocElementStore::tombstoneRows([['id' => 12, 'arma_name' => '_USER_DEFINED #5', 'pos' => [10.0, 20.0], 'at' => 1794751200]])[0];
        self::assertSame('del:12', $row['id']);
        $d = json_decode($row['markerData'], true);
        self::assertSame('game_del', $d['source']);
        self::assertSame('_USER_DEFINED #5', $d['text']);
        self::assertSame(1794751200, $d['deleted_at']);
    }

    public function testMultipleSelectionAuditAndRightsOwnVersusOthers(): void
    {
        $own = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'mien', 'author' => 'Alpha', 'owner_user_id' => 7]);
        $byAlias = F::marker($this->pdo, 1, 1, ['pos' => [2, 2], 'text' => 'mien aussi', 'author' => 'alpha']);
        $others = F::marker($this->pdo, 1, 1, ['pos' => [3, 3], 'text' => 'autre', 'author' => 'Bravo', 'owner_user_id' => 9]);
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha'), self::refs('marker', $own, $byAlias, $others));
        self::assertTrue($res['ok']);
        self::assertSame(2, $res['count']);
        self::assertSame(1, $res['forbidden']);
        self::assertSame(1, $this->live(), 'le marqueur de Bravo reste');
        $only = $this->svc()->delete(1, 1, self::actor(7, 'Alpha'), self::refs('marker', $others));
        self::assertFalse($only['ok']);
        self::assertSame('forbidden', $only['error']);
        // La gestion du poste supprime ceux des autres.
        $mgr = $this->svc()->delete(1, 1, self::actor(3, 'Chef', true), self::refs('marker', $others));
        self::assertTrue($mgr['ok']);
        self::assertSame(0, $this->live());
        self::assertSame('atak.toc.delete', $this->audits[0][0]);
        self::assertSame(2, $this->audits[0][4]['count']);
        self::assertSame(['marker' => 2], $this->audits[0][4]['kinds']);
        self::assertSame($res['batch'], $this->audits[0][4]['batch']);
        self::assertSame(7, $this->audits[0][2]);
        self::assertSame(1, $this->audits[0][1]);
    }

    public function testPinnedElementsNeedTheDistinctStrongConfirmation(): void
    {
        $pinned = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'P', 'author' => 'Alpha', 'web_permanent' => true]);
        $plain = F::marker($this->pdo, 1, 1, ['pos' => [2, 2], 'text' => 'N', 'author' => 'Alpha']);
        $actor = self::actor(7, 'Alpha', true);
        $r = $this->svc()->delete(1, 1, $actor, self::refs('marker', $pinned, $plain));
        self::assertFalse($r['ok']);
        self::assertSame('pinned_confirm', $r['error']);
        self::assertSame(1, $r['pinned']);
        self::assertSame(2, $this->live(), 'rien n\'a été supprimé');
        $r = $this->svc()->delete(1, 1, $actor, self::refs('marker', $pinned, $plain), ['confirmPinned' => 'supprimer']);
        self::assertSame('pinned_confirm', $r['error'], 'la phrase de la purge ne vaut pas la confirmation des épinglés');
        $r = $this->svc()->delete(1, 1, $actor, self::refs('marker', $pinned, $plain), ['confirmPinned' => 'permanent']);
        self::assertTrue($r['ok']);
        self::assertSame(1, $r['pinned']);
        self::assertSame(0, $this->live());
    }

    public function testCrossTenantAndCrossMapIdentifiersAreNeverTouched(): void
    {
        $mine = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'ok', 'author' => 'Alpha']);
        $otherTenant = F::marker($this->pdo, 2, 1, ['pos' => [1, 1], 'text' => 'autre communauté', 'author' => 'Alpha']);
        $otherMap = F::marker($this->pdo, 1, 2, ['pos' => [1, 1], 'text' => 'autre carte', 'author' => 'Alpha']);
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha', true), self::refs('marker', $mine, $otherTenant, $otherMap));
        self::assertSame(1, $res['count']);
        self::assertSame(2, $res['missing']);
        self::assertSame(1, $this->live(2, 1));
        self::assertSame(1, $this->live(1, 2));
        $r = $this->svc()->delete(1, 1, self::actor(7, 'Alpha', true), self::refs('marker', $otherTenant));
        self::assertSame('nothing', $r['error']);
        // Annuler le lot d'une autre communauté ne remet rien.
        $this->svc()->delete(2, 1, self::actor(8, 'Zed', true), self::refs('marker', $otherTenant));
        $trash2 = $this->svc()->trash(2, 1, self::actor(8, 'Zed', true));
        $undoWrong = $this->svc()->undo(1, 1, self::actor(7, 'Alpha', true), $trash2['batches'][0]['batch']);
        self::assertFalse($undoWrong['ok']);
        self::assertSame(0, $this->live(2, 1));
    }

    public function testOnlyTheAuthorOrAManagerCanUndoABatch(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Alpha']);
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha'), self::refs('marker', $a));
        $no = $this->svc()->undo(1, 1, self::actor(9, 'Bravo'), $res['batch']);
        self::assertSame('forbidden', $no['error']);
        self::assertFalse($this->svc()->undo(1, 1, self::actor(9, 'Bravo'), 'zzzz')['ok']);
        self::assertTrue($this->svc()->undo(1, 1, self::actor(9, 'Chef', true), $res['batch'])['ok']);
        self::assertSame(1, $this->live());
        self::assertSame('atak.toc.delete_undo', $this->audits[1][0]);
    }

    public function testDegradedModeWithoutMigrationAsksForExplicitHardDelete(): void
    {
        $pdo = F::open(false);
        $this->pdo = $pdo;
        $a = F::marker($pdo, 1, 1, ['pos' => [1, 1], 'text' => 'web', 'author' => 'Alpha']);
        $g = F::marker($pdo, 1, 1, ['pos' => [2, 2], 'text' => 'jeu', 'author' => 'Alpha'], '_USER_DEFINED #7');
        $svc = $this->svc(null, false);
        $r = $svc->delete(1, 1, self::actor(7, 'Alpha', true), self::refs('marker', $a, $g));
        self::assertFalse($r['ok']);
        self::assertSame('degraded_confirm', $r['error']);
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM atak_markers')->fetchColumn());
        $r = $svc->delete(1, 1, self::actor(7, 'Alpha', true), self::refs('marker', $a, $g), ['hard' => true]);
        self::assertTrue($r['ok']);
        self::assertFalse($r['soft']);
        self::assertSame('', $r['batch'], 'rien à annuler');
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM atak_markers')->fetchColumn(), 'le marqueur du jeu reste, masqué, pour que le jeu le retire');
        $left = json_decode((string) $pdo->query('SELECT marker_data FROM atak_markers')->fetchColumn(), true);
        self::assertTrue($left['suppressed']);
        self::assertSame([], $svc->trash(1, 1, self::actor(7, 'Alpha'))['batches']);
    }

    public function testPingsAreRemovedForGoodAndTracksCannotBeDeletedBySelection(): void
    {
        $p = F::ping($this->pdo, 1, 1, 'Alpha');
        $u = F::unit($this->pdo, 1, 1, 'ALPHA-1', '2026-11-15 10:00:00');
        $res = $this->svc()->delete(1, 1, self::actor(7, 'Alpha'), [...self::refs('ping', $p), ...self::refs('track', $u)]);
        self::assertTrue($res['ok']);
        self::assertSame(1, $res['count']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_pings')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_units')->fetchColumn(), 'les pistes ne se suppriment que par la purge');
        self::assertSame(1, $res['irreversible']);
    }

    public function testPinSelectionTogglesPermanentAndIsLimitedToOwnElements(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Alpha']);
        $b = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'B', 'author' => 'Bravo']);
        $s = F::shape($this->pdo, 1, 1, 'POLYGON', ['coordinates' => [[0, 0], [10, 0], [10, 10]]], 'Alpha');
        $r = $this->svc()->pin(1, 1, self::actor(7, 'Alpha'), [...self::refs('marker', $a, $b), ...self::refs('shape', $s)], true);
        self::assertSame(2, $r['count']);
        $els = (new TocElementStore($this->pdo, true))->find(1, 1, [...self::refs('marker', $a, $b), ...self::refs('shape', $s)]);
        self::assertTrue($els['marker:' . $a]['pinned']);
        self::assertFalse($els['marker:' . $b]['pinned']);
        self::assertTrue($els['shape:' . $s]['pinned']);
        $r = $this->svc()->pin(1, 1, self::actor(7, 'Alpha'), self::refs('marker', $a), false);
        self::assertSame(1, $r['count']);
    }

    public function testArchiveSelectionWritesTheSnapshotBeforeRemovingAndKeepsEverythingOnFailure(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'A', 'author' => 'Alpha']);
        $s = F::shape($this->pdo, 1, 1, 'LINE', ['coordinates' => [[0, 0], [5, 5]]], 'Alpha');
        $mem = new InMemoryMarkerCycleStore();
        $cycles = new AtakMarkerCycleService($mem, fn (): int => $this->now);
        $mem->failWrites = true;
        $fail = $this->svc()->archive(1, 1, self::actor(7, 'Alpha'), [...self::refs('marker', $a), ...self::refs('shape', $s)], $mem, $cycles);
        self::assertFalse($fail['ok']);
        self::assertSame('archive_failed', $fail['error']);
        self::assertSame(2, $this->live(), 'échec d\'archive : rien n\'est retiré');
        $mem->failWrites = false;
        $ok = $this->svc()->archive(1, 1, self::actor(7, 'Alpha'), [...self::refs('marker', $a), ...self::refs('shape', $s)], $mem, $cycles);
        self::assertTrue($ok['ok']);
        self::assertSame(2, $ok['count']);
        self::assertSame(0, $this->live());
        self::assertSame(2, count($mem->items[$ok['archiveId']]), 'instantané complet');
        self::assertSame('atak.toc.archive', $this->audits[0][0]);
        // Hors corbeille : pas d'annulation d'un archivage (la mission archivée sert de copie).
        self::assertSame([], $this->svc()->trash(1, 1, self::actor(7, 'Alpha'))['batches']);
    }

    public function testStoreCleansRefsAndBoundsTheirNumber(): void
    {
        $refs = TocElementStore::cleanRefs([
            ['kind' => 'marker', 'id' => 5], ['table' => 'form', 'id' => 6], ['table' => 'zone', 'id' => 6], ['table' => 'drop', 'id' => 1],
            ['table' => 'marker', 'id' => -1], 'x', ['table' => 'marker', 'id' => 5],
        ], 10);
        self::assertSame([['table' => 'marker', 'id' => 5], ['table' => 'shape', 'id' => 6]], $refs);
        $many = [];
        for ($i = 1; $i <= 50; $i++) {
            $many[] = ['table' => 'marker', 'id' => $i];
        }
        self::assertCount(10, TocElementStore::cleanRefs($many, 10));
    }
}
