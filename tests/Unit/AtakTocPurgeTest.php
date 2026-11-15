<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\AtakToc\Purge\TocDeletionService;
use App\Services\AtakToc\Purge\TocElementStore;
use App\Services\AtakToc\Purge\TocPurgeService;
use App\Services\AtakToc\Purge\TocScope;
use App\Services\AtakToc\Purge\TocSoftDelete;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;
use Tests\Support\MysqlDdlSqlitePdo;
use Tests\Support\TocPurgeFixture as F;

/**
 * Purge de la carte du poste TOC : périmètres (type, auteur, unité, âge, zone, cycle, toute la carte), aperçu égal au nombre
 * réellement supprimé, archivage de la mission AVANT toute suppression (un échec d'archive ne supprime rien), épinglés
 * conservés, exécution par lots de 500, reprise, idempotence, verrou contre les purges concurrentes, droit dédié, journal.
 */
final class AtakTocPurgeTest extends TestCase
{
    private MysqlDdlSqlitePdo $pdo;

    private InMemoryMarkerCycleStore $archives;

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
        $this->archives = new InMemoryMarkerCycleStore();
        $this->audits = [];
    }

    protected function tearDown(): void
    {
        TocSoftDelete::force(null);
    }

    private function purge(?int $at = null, ?bool $soft = null): TocPurgeService
    {
        $at ??= $this->now;
        $clock = static fn (): int => $at;
        $audit = function (string $action, int $tenant, int $user, int $map, array $details): void {
            $this->audits[] = [$action, $tenant, $user, $map, $details];
        };
        $store = new TocElementStore($this->pdo, $soft);

        return new TocPurgeService($store, new TocDeletionService($store, $clock, $audit), $this->archives, new AtakMarkerCycleService($this->archives, $clock), $clock, $audit);
    }

    /** @return array{userId: int, aliases: list<string>, manage: bool, purge: bool} */
    private static function actor(bool $purge = true, bool $manage = true, int $user = 7): array
    {
        return ['userId' => $user, 'aliases' => ['Chef'], 'manage' => $manage, 'purge' => $purge];
    }

    /** Éléments affichés : marqueurs, tracés, pings et pistes. */
    private function live(int $tenant = 1, int $map = 1): int
    {
        return count((new TocElementStore($this->pdo, true))->elements($tenant, $map));
    }

    /** Marqueurs et tracés seulement. */
    private function liveShapes(int $tenant = 1, int $map = 1): int
    {
        return count((new TocElementStore($this->pdo, true))->elements($tenant, $map, ['marker', 'shape']));
    }

    /** Carte réaliste : 30 marqueurs (dont 4 épinglés), 12 tracés de 3 familles, 6 pings, pistes en liaison et muettes. */
    private function seedMap(int $tenant = 1, int $map = 1): void
    {
        for ($i = 1; $i <= 30; $i++) {
            F::marker($this->pdo, $tenant, $map, [
                'pos' => [1000 + $i * 100, 2000 + $i * 50], 'text' => 'M' . $i, 'type' => 'mil_dot', 'author' => $i % 2 === 0 ? 'Alpha' : 'Bravo',
                'unit' => $i % 3 === 0 ? 'Section 1' : 'Section 2', 'web_permanent' => $i % 8 === 0,
            ], $i % 5 === 0 ? '_USER_DEFINED #' . $i : null, $i <= 10 ? '2026-11-14 08:00:00' : '2026-11-15 12:30:00');
        }
        for ($i = 1; $i <= 4; $i++) {
            F::shape($this->pdo, $tenant, $map, 'POLYGON', ['coordinates' => [[5000, 5000], [5200, 5000], [5200, 5200], [5000, 5200]]], 'Alpha', $i === 1 ? ['web_permanent' => true] : null);
            F::shape($this->pdo, $tenant, $map, 'LINE', ['coordinates' => [[100, 100], [900, 900]]], 'Bravo');
            F::shape($this->pdo, $tenant, $map, 'POINT', ['center' => [7000, 7000]], 'Alpha');
        }
        for ($i = 1; $i <= 6; $i++) {
            F::ping($this->pdo, $tenant, $map, 'Alpha', $i <= 3 ? '2026-11-15 13:50:00' : '2026-11-14 09:00:00');
        }
        F::unit($this->pdo, $tenant, $map, 'ALPHA-1', '2026-11-15 13:59:30');
        F::unit($this->pdo, $tenant, $map, 'BRAVO-1', '2026-11-15 06:00:00');
    }

    /** @return array<string, mixed> */
    private function run(array $scope, bool $archive = true, bool $keepPinned = true): array
    {
        $scope['archiveFirst'] = $archive;
        $scope['keepPinned'] = $keepPinned;
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        self::assertTrue($pre['ok'], json_encode($pre));
        $start = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', $pre['total']);
        self::assertTrue($start['ok'], json_encode($start));
        $job = $start['job'];
        for ($i = 0; $i < 40 && !in_array($job['status'], ['done', 'failed', 'cancelled'], true); $i++) {
            $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        }

        return ['pre' => $pre, 'job' => $job];
    }

    public function testScopeIsNormalizedAndBounded(): void
    {
        $s = TocScope::normalize([
            'types' => ['marker', 'MARKER', 'ping', 'rm -rf', 5, 'track'], 'authors' => array_merge(['  Alpha  ', 'alpha', ''], array_map(static fn (int $i): string => 'u' . $i, range(1, 40))),
            'olderThanHours' => 99999, 'zone' => ['type' => 'rect', 'rect' => [500, 900, 100, 200]], 'cycleOnly' => '1',
        ]);
        self::assertSame(['marker', 'ping', 'track'], $s['types']);
        self::assertCount(TocScope::MAX_AUTHORS, $s['authors']);
        self::assertSame('Alpha', $s['authors'][0]);
        self::assertSame(TocScope::MAX_AGE_HOURS, $s['olderThanHours']);
        self::assertSame([100.0, 200.0, 500.0, 900.0], $s['zone']['rect']);
        self::assertTrue($s['cycleOnly']);
        self::assertTrue($s['keepPinned'], 'épinglés conservés par défaut');
        self::assertTrue($s['archiveFirst'], 'archivage par défaut');
        self::assertFalse(TocScope::normalize(['types' => ['marker'], 'keepPinned' => false])['keepPinned']);
        self::assertNull(TocScope::normalize(['types' => ['marker'], 'zone' => ['type' => 'polygon', 'pts' => [[1, 1], [2, 2]]]])['zone'], 'un polygone a trois sommets au moins');
        $poly = TocScope::normalize(['types' => ['marker'], 'zone' => ['type' => 'polygon', 'pts' => array_fill(0, 200, [1, 2])]])['zone'];
        self::assertCount(TocScope::MAX_ZONE_POINTS, $poly['pts']);
        self::assertTrue(TocScope::isWholeMap(TocScope::normalize(['types' => ['marker']])));
        self::assertFalse(TocScope::isWholeMap(TocScope::normalize(['types' => ['marker'], 'authors' => ['Alpha']])));
    }

    public function testPolygonAndRectangleZones(): void
    {
        $tri = ['type' => 'polygon', 'pts' => [[0.0, 0.0], [100.0, 0.0], [0.0, 100.0]]];
        self::assertTrue(TocScope::inZone($tri, 20.0, 20.0));
        self::assertFalse(TocScope::inZone($tri, 80.0, 80.0));
        $rect = ['type' => 'rect', 'rect' => [0.0, 0.0, 10.0, 10.0]];
        self::assertTrue(TocScope::inZone($rect, 10.0, 10.0));
        self::assertFalse(TocScope::inZone($rect, 10.1, 5.0));
    }

    public function testPreviewCountsMatchWhatIsReallyDeletedForEveryScope(): void
    {
        $scopes = [
            'toute la carte, marqueurs' => ['types' => ['marker']],
            'tracés' => ['types' => ['form', 'route', 'zone']],
            'une famille de tracés' => ['types' => ['route']],
            'auteur' => ['types' => ['marker', 'form', 'route', 'zone'], 'authors' => ['alpha']],
            'unité' => ['types' => ['marker'], 'authors' => ['Section 1']],
            'âge' => ['types' => ['marker', 'ping'], 'olderThanHours' => 12],
            'zone rectangle' => ['types' => ['marker', 'zone', 'form'], 'zone' => ['type' => 'rect', 'rect' => [1000, 2000, 3000, 3500]]],
            'zone polygone' => ['types' => ['marker'], 'zone' => ['type' => 'polygon', 'pts' => [[1000, 2000], [4000, 2000], [4000, 3600]]]],
            'cycle courant' => ['types' => ['marker', 'ping'], 'cycleOnly' => true],
            'pings seuls' => ['types' => ['ping']],
            'pistes muettes' => ['types' => ['track']],
        ];
        foreach ($scopes as $name => $scope) {
            $this->pdo = F::open();
            $this->archives = new InMemoryMarkerCycleStore();
            $this->seedMap();
            $before = $this->live();
            $r = $this->run($scope);
            self::assertSame('done', $r['job']['status'], $name);
            self::assertGreaterThan(0, $r['pre']['total'], $name . ' : le jeu d\'essai doit couvrir le périmètre');
            self::assertSame($r['pre']['total'], $r['job']['deleted'], $name . ' : aperçu = suppression réelle');
            $after = $this->live();
            self::assertSame($before - $r['pre']['total'], $after, $name . ' : la carte perd exactement l\'aperçu');
            self::assertSame(array_sum($r['pre']['byKind']), $r['pre']['total'], $name);
        }
    }

    public function testPreviewIsReadOnlyAndSplitsByCategory(): void
    {
        $this->seedMap();
        $before = $this->live();
        $pre = $this->purge()->preview(1, 1, self::actor(), ['types' => ['marker', 'zone', 'form', 'route', 'ping', 'track']]);
        self::assertTrue($pre['ok']);
        self::assertSame($before, $this->live(), 'un aperçu ne supprime rien');
        self::assertSame(27, $pre['byKind']['marker'], '30 marqueurs dont 3 épinglés conservés');
        self::assertSame(4, $pre['pinnedKept'], 'trois marqueurs épinglés conservés, plus un tracé épinglé');
        self::assertSame(3, $pre['byKind']['zone'], 'zone : 4 polygones dont un épinglé');
        self::assertSame(4, $pre['byKind']['route']);
        self::assertSame(4, $pre['byKind']['form']);
        self::assertSame(6, $pre['byKind']['ping']);
        self::assertSame(1, $pre['byKind']['track'], 'la piste vue il y a 30 secondes n\'est jamais purgée');
        self::assertSame(7, $pre['irreversible'], 'pings et pistes ne reviennent pas');
        self::assertTrue($pre['wholeMap']);
        self::assertSame('PURGER', $pre['phrase']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_toc_purges')->fetchColumn(), 'aucune ligne de purge avant confirmation');
    }

    public function testArchiveIsWrittenBeforeAnyDeletionAndPinnedAreKept(): void
    {
        $this->seedMap();
        $scope = ['types' => ['marker', 'form', 'route', 'zone'], 'archiveFirst' => true];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        $start = $this->purge()->start(1, 1, self::actor(), $scope, 'purger', $pre['total']);
        self::assertSame('archiving', $start['job']['status']);
        self::assertSame($this->live(), $this->live(), 'rien n\'est encore supprimé');
        $liveBefore = $this->live();
        $job = $this->purge()->step(1, 1, self::actor(), $start['job']['id'])['job'];
        self::assertSame('deleting', $job['status'], 'toute la sélection tient dans un lot : l\'archivage est terminé');
        self::assertSame($liveBefore, $this->live(), 'instantané écrit, aucune suppression pendant l\'archivage');
        self::assertSame($pre['total'], $job['archived']);
        $archiveId = $job['archiveId'];
        self::assertNotNull($archiveId);
        self::assertSame($pre['total'], count($this->archives->items[$archiveId]));
        $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        self::assertSame('done', $job['status']);
        self::assertSame($pre['total'], $job['deleted']);
        self::assertSame(4, $this->liveShapes(), 'restent : 3 marqueurs épinglés et 1 tracé épinglé');
        foreach ((new TocElementStore($this->pdo, true))->elements(1, 1, ['marker', 'shape']) as $el) {
            self::assertTrue($el['pinned']);
        }
        self::assertSame('purge', $this->archives->archives[$archiveId]['source']);
        self::assertSame(['atak.toc.purge_start', 'atak.toc.purge'], array_column($this->audits, 0));
        self::assertSame($pre['total'], $this->audits[1][4]['deleted']);
        self::assertSame($archiveId, $this->audits[1][4]['archive_id']);
    }

    public function testArchiveFailureDeletesNothingAndMarksTheJobFailed(): void
    {
        $this->seedMap();
        $this->archives->failWrites = true;
        $scope = ['types' => ['marker']];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        $start = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', $pre['total']);
        $before = $this->live();
        $job = $this->purge()->step(1, 1, self::actor(), $start['job']['id'])['job'];
        self::assertSame('failed', $job['status']);
        self::assertSame('archive_failed', $job['error']);
        self::assertSame($before, $this->live(), 'échec d\'archive : rien n\'est supprimé');
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_markers WHERE deleted_at IS NOT NULL')->fetchColumn());
        // Le verrou est libéré : une nouvelle purge peut être lancée.
        $this->archives->failWrites = false;
        $again = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', $pre['total']);
        self::assertTrue($again['ok']);
        self::assertContains('atak.toc.purge_failed', array_column($this->audits, 0));
    }

    public function testWithoutArchiveOptionNothingIsWritten(): void
    {
        $this->seedMap();
        $r = $this->run(['types' => ['marker']], false);
        self::assertSame('done', $r['job']['status']);
        self::assertSame([], $this->archives->archives);
        self::assertNull($r['job']['archiveId']);
    }

    public function testDeleteRunsInBatchesOfFiveHundredWithVisibleProgress(): void
    {
        for ($i = 1; $i <= 1203; $i++) {
            F::marker($this->pdo, 1, 1, ['pos' => [$i, $i], 'text' => 'm' . $i, 'author' => 'Alpha']);
        }
        $scope = ['types' => ['marker'], 'archiveFirst' => false];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        self::assertSame(1203, $pre['total']);
        $job = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', 1203)['job'];
        self::assertSame('deleting', $job['status']);
        $seen = [];
        $steps = 0;
        while ($job['status'] === 'deleting' && $steps < 10) {
            $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
            $seen[] = $job['deleted'];
            $steps++;
        }
        self::assertSame(3, $steps);
        self::assertSame([500, 1000, 1203], $seen);
        self::assertSame('done', $job['status']);
        self::assertSame(100, $job['progress']);
        self::assertSame(0, $this->live());
    }

    public function testArchiveRunsInBatchesToo(): void
    {
        for ($i = 1; $i <= 1100; $i++) {
            F::marker($this->pdo, 1, 1, ['pos' => [$i, $i], 'text' => 'm' . $i, 'author' => 'Alpha']);
        }
        $scope = ['types' => ['marker']];
        $job = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', 1100)['job'];
        $archived = [];
        $progress = [];
        while ($job['status'] === 'archiving') {
            $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
            $archived[] = $job['archived'];
            $progress[] = $job['progress'];
            self::assertSame(1100, $this->live(), 'aucune suppression avant la fin de l\'archivage');
        }
        self::assertSame([500, 1000, 1100], $archived);
        self::assertSame(50, $progress[2], 'l\'archivage compte pour la première moitié de la progression');
        self::assertSame('deleting', $job['status']);
    }

    public function testResumeAndIdempotence(): void
    {
        for ($i = 1; $i <= 700; $i++) {
            F::marker($this->pdo, 1, 1, ['pos' => [$i, $i], 'text' => 'm' . $i, 'author' => 'Alpha']);
        }
        $scope = ['types' => ['marker'], 'archiveFirst' => false];
        $job = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', 700)['job'];
        $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        self::assertSame(500, $job['deleted']);
        // Reprise après une coupure : nouvelle instance, état relu en base ; le statut de la carte propose la purge en cours.
        $status = $this->purge()->status(1, 1);
        self::assertSame($job['id'], $status['running']['id']);
        // Un lot rejoué (curseur remis à zéro) ne supprime rien de plus.
        $this->pdo->exec('UPDATE atak_toc_purges SET cursor_pos = 0');
        $replay = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        self::assertSame(500, $replay['deleted'], 'rien de plus supprimé');
        $this->pdo->exec('UPDATE atak_toc_purges SET cursor_pos = 500');
        $done = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        self::assertSame('done', $done['status']);
        self::assertSame(700, $done['deleted']);
        $again = $this->purge()->step(1, 1, self::actor(), $job['id']);
        self::assertSame('done', $again['job']['status']);
        self::assertSame(700, $again['job']['deleted'], 'rappeler une purge terminée ne fait rien');
        self::assertCount(2, array_filter($this->audits, static fn (array $a): bool => $a[0] === 'atak.toc.purge' || $a[0] === 'atak.toc.purge_start') );
    }

    public function testConcurrentPurgesAndConcurrentBatchesAreRefused(): void
    {
        $this->seedMap();
        $scope = ['types' => ['marker'], 'archiveFirst' => false];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        $a = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', $pre['total']);
        self::assertTrue($a['ok']);
        $b = $this->purge()->start(1, 1, self::actor(false, true, 8), $scope, 'PURGER', $pre['total']);
        self::assertSame('forbidden', $b['error']);
        $c = $this->purge()->start(1, 1, self::actor(true, true, 8), $scope, 'PURGER', $pre['total']);
        self::assertFalse($c['ok']);
        self::assertSame('purge_running', $c['error']);
        self::assertSame($a['job']['id'], $c['job']['id']);
        // Une autre carte n'est pas bloquée.
        $other = $this->purge()->preview(1, 2, self::actor(), $scope);
        self::assertSame(0, $other['total']);
        // Un lot déjà pris par une autre exécution renvoie busy sans rien faire.
        $this->pdo->exec("UPDATE atak_toc_purges SET locked_until = '2026-11-15 14:00:30'");
        $busy = $this->purge()->step(1, 1, self::actor(), $a['job']['id']);
        self::assertTrue($busy['busy']);
        self::assertSame(0, $busy['job']['deleted']);
        // Verrou expiré : le lot reprend.
        $later = $this->purge($this->now + 60)->step(1, 1, self::actor(), $a['job']['id']);
        self::assertArrayNotHasKey('busy', $later);
        self::assertGreaterThan(0, $later['job']['deleted']);
        // Une purge restée sans nouvelles depuis 30 minutes ne bloque plus.
        $this->pdo->exec("UPDATE atak_toc_purges SET status = 'deleting', updated_at = '2026-11-15 10:00:00'");
        self::assertNull($this->purge()->status(1, 1)['running']);
    }

    public function testStartNeedsThePurgeRightThePhraseAndAFreshPreview(): void
    {
        $this->seedMap();
        $scope = ['types' => ['marker'], 'archiveFirst' => false];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        // atak.toc.manage seul ne suffit pas.
        self::assertSame('forbidden', $this->purge()->preview(1, 1, self::actor(false, true), $scope)['error']);
        self::assertSame('forbidden', $this->purge()->start(1, 1, self::actor(false, true), $scope, 'PURGER', $pre['total'])['error']);
        self::assertSame('forbidden', $this->purge()->step(1, 1, self::actor(false, true), 1)['error']);
        // Phrase exacte.
        self::assertSame('confirm_required', $this->purge()->start(1, 1, self::actor(), $scope, 'SUPPRIMER', $pre['total'])['error']);
        self::assertSame('confirm_required', $this->purge()->start(1, 1, self::actor(), $scope, '', $pre['total'])['error']);
        // Aperçu périmé : la carte a changé depuis, il faut confirmer le nouveau nombre.
        F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'nouveau', 'author' => 'X']);
        $stale = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', $pre['total']);
        self::assertSame('preview_changed', $stale['error']);
        self::assertSame($pre['total'] + 1, $stale['total']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_toc_purges')->fetchColumn());
        self::assertSame('types_required', $this->purge()->preview(1, 1, self::actor(), ['types' => []])['error']);
        self::assertSame('nothing', $this->purge()->start(1, 3, self::actor(), ['types' => ['ping']], 'PURGER', 0)['error']);
    }

    public function testCeilingRefusesTooLargePurgeWithoutTouchingAnything(): void
    {
        $this->pdo->exec('BEGIN');
        $st = $this->pdo->prepare('INSERT INTO atak_pings (tenant_id, map_id, author, pos_x, pos_y, message, created_at) VALUES (1, 1, ?, 1, 1, ?, ?)');
        for ($i = 0; $i < TocPurgeService::MAX_ITEMS + 5; $i++) {
            $st->execute(['p', 'x', '2026-11-10 10:00:00']);
        }
        $this->pdo->exec('COMMIT');
        $scope = ['types' => ['ping']];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        self::assertTrue($pre['capped']);
        self::assertSame(TocPurgeService::MAX_ITEMS, $pre['total']);
        $r = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', $pre['total']);
        self::assertSame('too_many', $r['error']);
        self::assertSame(TocPurgeService::MAX_ITEMS + 5, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_pings')->fetchColumn());
    }

    public function testPinnedAreDeletedOnlyWhenKeepPinnedIsOffAndRecheckedAtDeleteTime(): void
    {
        $a = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'a', 'author' => 'Alpha']);
        $b = F::marker($this->pdo, 1, 1, ['pos' => [2, 2], 'text' => 'b', 'author' => 'Alpha', 'web_permanent' => true]);
        $scope = ['types' => ['marker'], 'archiveFirst' => false];
        $pre = $this->purge()->preview(1, 1, self::actor(), $scope);
        self::assertSame(1, $pre['total']);
        self::assertSame(1, $pre['pinnedKept']);
        $job = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', 1)['job'];
        // Entre l'aperçu et le lot, quelqu'un épingle le marqueur « a » : il est conservé.
        (new TocElementStore($this->pdo, true))->setPinned(1, 1, 'marker', $a, true, $this->now);
        $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        self::assertSame(0, $job['deleted']);
        self::assertSame(2, $job['keptPinned'], 'un épinglé écarté à l\'aperçu, un de plus relevé au lot');
        self::assertSame(2, $this->live());
        $all = ['types' => ['marker'], 'keepPinned' => false, 'archiveFirst' => false];
        $pre = $this->purge()->preview(1, 1, self::actor(), $all);
        self::assertSame(2, $pre['total']);
        self::assertSame(2, $pre['pinnedIncluded']);
    }

    public function testPurgeBatchIsUndoableButPingsAndTracksAreNot(): void
    {
        $m = F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'a', 'author' => 'Alpha']);
        $s = F::shape($this->pdo, 1, 1, 'LINE', ['coordinates' => [[0, 0], [9, 9]]]);
        F::ping($this->pdo, 1, 1, 'Alpha', '2026-11-15 13:00:00');
        $r = $this->run(['types' => ['marker', 'route', 'ping']], false);
        self::assertSame(3, $r['job']['deleted']);
        self::assertSame(0, $this->live());
        $undo = (new TocDeletionService(new TocElementStore($this->pdo, true), fn (): int => $this->now + 120, fn () => null))
            ->undo(1, 1, self::actor(true, false), $r['job']['batch']);
        self::assertTrue($undo['ok']);
        self::assertSame(2, $undo['restored'], 'le marqueur et le tracé reviennent');
        self::assertSame(2, $this->live());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_pings')->fetchColumn());
        self::assertSame($r['job']['undoUntil'], $this->now + 900);
        self::assertGreaterThan(0, $m + $s);
    }

    public function testTenantAndMapIsolation(): void
    {
        $this->seedMap(1, 1);
        $this->seedMap(2, 1);
        $this->seedMap(1, 2);
        $before2 = $this->live(2, 1);
        $beforeMap2 = $this->live(1, 2);
        $r = $this->run(['types' => ['marker', 'form', 'route', 'zone', 'ping'], 'archiveFirst' => false], false);
        self::assertSame('done', $r['job']['status']);
        self::assertSame($before2, $this->live(2, 1), 'autre communauté intacte');
        self::assertSame($beforeMap2, $this->live(1, 2), 'autre carte intacte');
        $row = $this->pdo->query('SELECT tenant_id, map_id FROM atak_toc_purges')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame([1, 1], [(int) $row['tenant_id'], (int) $row['map_id']]);
        // Un identifiant de purge d'une autre communauté est introuvable.
        self::assertSame('not_found', $this->purge()->step(2, 1, self::actor(), $r['job']['id'])['error']);
        self::assertSame('not_found', $this->purge()->step(1, 2, self::actor(), $r['job']['id'])['error']);
        self::assertSame('not_found', $this->purge()->cancel(2, 1, self::actor(), $r['job']['id'])['error']);
    }

    public function testCancelStopsTheJobAndKeepsWhatWasDeletedUndoable(): void
    {
        for ($i = 1; $i <= 900; $i++) {
            F::marker($this->pdo, 1, 1, ['pos' => [$i, $i], 'text' => 'm' . $i, 'author' => 'Alpha']);
        }
        $scope = ['types' => ['marker'], 'archiveFirst' => false];
        $job = $this->purge()->start(1, 1, self::actor(), $scope, 'PURGER', 900)['job'];
        $job = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        $c = $this->purge()->cancel(1, 1, self::actor(), $job['id']);
        self::assertSame('cancelled', $c['job']['status']);
        self::assertSame(500, $c['job']['deleted']);
        self::assertSame(400, $this->live());
        $after = $this->purge()->step(1, 1, self::actor(), $job['id'])['job'];
        self::assertSame('cancelled', $after['status']);
        self::assertSame(400, $this->live(), 'une purge annulée ne reprend pas');
        self::assertSame('atak.toc.purge_cancel', end($this->audits)[0]);
    }

    public function testDegradedModeDisablesThePurgeWithAClearError(): void
    {
        $this->pdo = F::open(false);
        F::marker($this->pdo, 1, 1, ['pos' => [1, 1], 'text' => 'a']);
        $svc = $this->purge(null, false);
        self::assertSame('unavailable', $svc->preview(1, 1, self::actor(), ['types' => ['marker']])['error']);
        self::assertSame('unavailable', $svc->start(1, 1, self::actor(), ['types' => ['marker']], 'PURGER', 1)['error']);
        $st = $svc->status(1, 1);
        self::assertFalse($st['available']);
        self::assertSame([], $st['recent']);
    }

    public function testStaleTracksAreNeverPurgedWhenSeenLessThanAnHourAgo(): void
    {
        F::unit($this->pdo, 1, 1, 'LIVE-1', '2026-11-15 13:30:00');
        F::unit($this->pdo, 1, 1, 'DEAD-1', '2026-11-15 11:00:00');
        $r = $this->run(['types' => ['track']], false);
        self::assertSame(1, $r['job']['deleted']);
        self::assertSame('LIVE-1', $this->pdo->query('SELECT call_sign FROM atak_units')->fetchColumn());
    }
}
