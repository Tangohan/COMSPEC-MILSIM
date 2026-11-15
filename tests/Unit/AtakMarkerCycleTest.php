<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\MarkerCycle\AtakMarkerArchiveService;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleFilter;
use App\Services\Atak\MarkerCycle\AtakMarkerCycleService;
use App\Services\Cron\CronSchedule;
use App\Services\Cron\Jobs\AtakMarkerCycleCronJob;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryMarkerCycleStore;

/**
 * Cycle de 24 h des marqueurs des postes web : bornes (11:59, 12:00, 12:01), changements d'heure, fuseaux, durée de
 * plusieurs jours ; archivage à la bascule (instantané écrit avant tout retrait, rien retiré si l'écriture échoue),
 * rattrapage progressif des cycles manqués, rejeu sans doublon, éléments épinglés conservés, cloisonnement par communauté,
 * archives en lecture seule, restauration, rétention, verrou ; désactivation = comportement d'avant.
 */
final class AtakMarkerCycleTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const T = 7;

    private InMemoryMarkerCycleStore $store;

    /** @var list<string> */
    private array $logs = [];

    private int $now = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryMarkerCycleStore();
        $this->logs = [];
        // 15/11/2026 13:00 à Paris (12:00 UTC) : le cycle en cours a commencé à 12:00 heure de Paris (11:00 UTC).
        $this->now = self::ts('2026-11-15 13:00:00', 'Europe/Paris');
        $this->store->now = '2026-11-15 12:00:00';
    }

    private static function ts(string $local, string $tz): int
    {
        return (new \DateTimeImmutable($local, new \DateTimeZone($tz)))->getTimestamp();
    }

    private function cycles(): AtakMarkerCycleService
    {
        return new AtakMarkerCycleService($this->store, fn (): int => $this->now);
    }

    private function archiver(): AtakMarkerArchiveService
    {
        return new AtakMarkerArchiveService($this->store, fn (): int => $this->now, function (string $level, string $message): void {
            $this->logs[] = $level . ': ' . $message;
        });
    }

    /** @return array{enabled: bool, switch_time: string, duration_days: int, timezone: string, retention_days: int} */
    private static function paris(array $over = []): array
    {
        return array_replace(['enabled' => true, 'switch_time' => '12:00', 'duration_days' => 1, 'timezone' => 'Europe/Paris', 'retention_days' => 1095], $over);
    }

    // ------------------------------------------------------------------ bornes du cycle

    public function testWindowBoundariesAroundNoon(): void
    {
        $before = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-11-15 11:59:59', 'Europe/Paris'));
        self::assertSame('2026-11-14 11:00:00', $before['start_utc']);
        self::assertSame('2026-11-15 11:00:00', $before['end_utc']);

        $at = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-11-15 12:00:00', 'Europe/Paris'));
        self::assertSame('2026-11-15 11:00:00', $at['start_utc'], '12:00 pile ouvre le nouveau cycle');
        self::assertSame('2026-11-16 11:00:00', $at['end_utc']);

        $after = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-11-15 12:01:00', 'Europe/Paris'));
        self::assertSame($at['key'], $after['key']);
        self::assertNotSame($before['key'], $at['key']);
        self::assertSame($before['end'], $at['start'], 'les cycles s’enchaînent sans trou ni chevauchement');
        self::assertSame('Mission du 14/11 12:00 → 15/11 12:00', $before['label']);
        self::assertSame('20261114T110000Z', $before['key']);
    }

    public function testWindowAcrossDaylightSavingChanges(): void
    {
        // Retour à l'heure d'hiver (25/10/2026) : le cycle dure 25 h, il commence et finit toujours à 12:00 heure de Paris.
        $w = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-10-25 08:00:00', 'Europe/Paris'));
        self::assertSame('2026-10-24 10:00:00', $w['start_utc']);
        self::assertSame('2026-10-25 11:00:00', $w['end_utc']);
        self::assertSame(25 * 3600, $w['end'] - $w['start']);
        self::assertSame('24/10 12:00', $w['start_local']);
        self::assertSame('25/10 12:00', $w['end_local']);

        // Passage à l'heure d'été (29/03/2026) : 23 h.
        $s = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-03-29 13:00:00', 'Europe/Paris'));
        self::assertSame('2026-03-29 10:00:00', $s['start_utc']);
        self::assertSame(24 * 3600, $s['end'] - $s['start'], 'du 29/03 12:00 (heure d’été) au 30/03 12:00');
        $before = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-03-29 03:30:00', 'Europe/Paris'));
        self::assertSame(23 * 3600, $before['end'] - $before['start']);
    }

    public function testTimezonesGiveDifferentCycleBoundaries(): void
    {
        $ts = self::ts('2026-11-15 13:00:00', 'Europe/Paris');
        $paris = AtakMarkerCycleService::windowAt(self::paris(), $ts);
        $ny = AtakMarkerCycleService::windowAt(self::paris(['timezone' => 'America/New_York']), $ts);
        $tokyo = AtakMarkerCycleService::windowAt(self::paris(['timezone' => 'Asia/Tokyo']), $ts);
        self::assertSame('2026-11-15 11:00:00', $paris['start_utc']);
        self::assertSame('2026-11-14 17:00:00', $ny['start_utc'], '12:00 à New York = 17:00 UTC, la veille de ce 15/11 12:00 UTC');
        self::assertSame('2026-11-15 03:00:00', $tokyo['start_utc']);
        foreach ([$paris, $ny, $tokyo] as $w) {
            self::assertTrue($w['start'] <= $ts && $ts < $w['end']);
        }
    }

    public function testSettingsFallBackToCommunityTimezoneThenSiteDefault(): void
    {
        $this->store->timezones[self::T] = 'America/Toronto';
        self::assertSame('America/Toronto', $this->cycles()->settings(self::T)['timezone']);
        $this->store->settingsByTenant[self::T] = ['timezone' => 'Pas/UnFuseau'];
        self::assertSame('America/Toronto', $this->cycles()->settings(self::T)['timezone'], 'fuseau invalide ignoré');
        self::assertSame('Europe/Paris', $this->cycles()->settings(99)['timezone'], 'sans réglage ni fuseau de communauté');
        $this->store->settingsByTenant[self::T] = ['timezone' => 'Asia/Tokyo'];
        self::assertSame('Asia/Tokyo', $this->cycles()->settings(self::T)['timezone']);
    }

    public function testNormalizeSettingsRejectsInvalidValues(): void
    {
        $s = AtakMarkerCycleService::normalizeSettings(['switch_time' => '25:99', 'duration_days' => 99, 'retention_days' => 5, 'enabled' => 0]);
        self::assertSame('12:00', $s['switch_time']);
        self::assertSame(7, $s['duration_days']);
        self::assertSame(1095, $s['retention_days']);
        self::assertFalse($s['enabled']);
        $d = AtakMarkerCycleService::normalizeSettings(null);
        self::assertTrue($d['enabled'], 'sans réglage enregistré, le cycle est actif');
        self::assertSame(1, $d['duration_days']);
        self::assertSame(0, AtakMarkerCycleService::normalizeSettings(['retention_days' => 0])['retention_days'], 'conservation sans limite');
    }

    public function testCustomSwitchTimeAndMultiDayCycles(): void
    {
        $w = AtakMarkerCycleService::windowAt(self::paris(['switch_time' => '06:30']), self::ts('2026-11-15 06:29:00', 'Europe/Paris'));
        self::assertSame('14/11 06:30', $w['start_local']);
        self::assertSame('15/11 06:30', $w['end_local']);

        $three = self::paris(['duration_days' => 3]);
        $a = AtakMarkerCycleService::windowAt($three, self::ts('2026-11-15 13:00:00', 'Europe/Paris'));
        self::assertSame(3 * 86400, $a['end'] - $a['start'], 'trois jours (hors changement d’heure)');
        $b = AtakMarkerCycleService::windowAt($three, $a['end']);
        self::assertSame($a['end'], $b['start'], 'le cycle suivant commence où le précédent finit');
        $inside = AtakMarkerCycleService::windowAt($three, $a['start'] + 86400);
        self::assertSame($a['key'], $inside['key']);
    }

    public function testStatusCountsDownToTheNextSwitch(): void
    {
        $st = $this->cycles()->status(self::T);
        self::assertTrue($st['enabled']);
        self::assertSame(23 * 3600, $st['remaining'], '13:00 -> 12:00 le lendemain, soit 23 h');
        self::assertSame('12:00', $st['endTime']);
        self::assertSame('Europe/Paris', $st['timezone']);
        $this->store->ready = false;
        $off = $this->cycles()->status(self::T);
        self::assertFalse($off['enabled']);
        self::assertFalse($off['ready']);
    }

    public function testReadCutoffOnlyWhenEnabledAndReady(): void
    {
        self::assertSame('2026-11-15 11:00:00', $this->cycles()->readCutoff(self::T));
        $this->store->settingsByTenant[self::T] = ['enabled' => 0];
        self::assertNull($this->cycles()->readCutoff(self::T), 'désactivé : tout reste affiché comme avant');
        $this->store->settingsByTenant[self::T] = ['enabled' => 1];
        $this->store->ready = false;
        self::assertNull($this->cycles()->readCutoff(self::T), 'tables absentes : aucun filtre');
    }

    public function testReadFilterIsAppliedOnEveryMarkerAndShapeRead(): void
    {
        AtakMarkerCycleFilter::useService($this->cycles());
        try {
            self::assertSame('2026-11-15 11:00:00', AtakMarkerCycleFilter::cutoff(self::T));
            self::assertNull(AtakMarkerCycleFilter::cutoff(0));
            $this->store->settingsByTenant[self::T] = ['enabled' => 0];
            AtakMarkerCycleFilter::useService($this->cycles());
            self::assertNull(AtakMarkerCycleFilter::cutoff(self::T));
        } finally {
            AtakMarkerCycleFilter::useService(null);
        }
        $markers = (string) file_get_contents(self::ROOT . '/app/Repositories/AtakDataRepository.php');
        $shapes = (string) file_get_contents(self::ROOT . '/app/Repositories/MapShapeRepository.php');
        self::assertStringContainsString('AtakMarkerCycleFilter::cutoff($tenantId)', $markers);
        self::assertStringContainsString('AtakMarkerCycleFilter::MARKER_SQL', $markers);
        self::assertStringContainsString('AtakMarkerCycleFilter::cutoff($tenantId)', $shapes);
        self::assertStringContainsString('AtakMarkerCycleFilter::SHAPE_SQL', $shapes);
        self::assertStringContainsString('web_permanent', AtakMarkerCycleFilter::MARKER_SQL, 'un marqueur épinglé reste affiché');
        self::assertStringContainsString('web_permanent', AtakMarkerCycleFilter::SHAPE_SQL);
    }

    // ------------------------------------------------------------------ archivage

    public function testArchivingMovesFinishedCycleAndKeepsCurrentOne(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-14 14:00:00');
        $this->store->addMarker(self::T, 1, 2, '2026-11-14 22:30:00', [], 'arma_marker_2');
        $this->store->addShape(self::T, 1, 3, '2026-11-15 09:00:00');
        $this->store->addMarker(self::T, 1, 4, '2026-11-15 11:00:00');
        $this->store->addMarker(self::T, 1, 5, '2026-11-15 11:59:59', ['text' => 'Dernier']);
        $this->store->addShape(self::T, 1, 6, '2026-11-15 11:30:00');

        $r = $this->archiver()->runScheduled();

        self::assertTrue($r['ok']);
        self::assertSame(3, $r['archived']);
        self::assertSame(3, $r['removed']);
        self::assertSame(1, $r['missions']);
        self::assertSame([self::T . ':1:marker:4', self::T . ':1:marker:5', self::T . ':1:shape:6'], $this->store->liveKeys(), '12:00 pile (11:00 UTC) ouvre le cycle courant');
        $archives = $this->store->listArchives(self::T, [], 10, 0);
        self::assertCount(1, $archives);
        self::assertSame('Mission du 14/11 12:00 → 15/11 12:00', $archives[0]['label']);
        self::assertSame(2, $archives[0]['markers_count']);
        self::assertSame(1, $archives[0]['shapes_count']);
        self::assertNull($archives[0]['created_by'], 'auteur système');
        $items = $this->archiver()->view(self::T, (int) $archives[0]['id']);
        self::assertNotNull($items);
        self::assertCount(3, $items['items']);
        self::assertSame('arma_marker_2', $items['items'][1]['payload']['arma_name'], 'le contenu complet est conservé');
    }

    public function testElementJustBeforeAndAfterSwitchLandInDifferentMissions(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-15 10:59:59');
        $this->store->addMarker(self::T, 1, 2, '2026-11-15 11:00:00');
        $this->archiver()->runScheduled();
        self::assertSame([self::T . ':1:marker:2'], $this->store->liveKeys());
        $this->now += 86400;
        $this->store->now = '2026-11-16 12:00:00';
        $this->archiver()->runScheduled();
        self::assertSame([], $this->store->liveKeys());
        self::assertCount(2, $this->store->listArchives(self::T, [], 10, 0), 'une mission par cycle');
    }

    public function testPinnedElementsSurviveTheSwitch(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 10:00:00', ['web_permanent' => true]);
        $this->store->addShape(self::T, 1, 2, '2026-11-10 10:00:00', ['web_permanent' => true]);
        $this->store->addMarker(self::T, 1, 3, '2026-11-10 10:00:00');
        $r = $this->archiver()->runScheduled();
        self::assertSame(1, $r['archived']);
        self::assertSame([self::T . ':1:marker:1', self::T . ':1:shape:2'], $this->store->liveKeys());

        // Désépinglé, il part à la bascule suivante.
        self::assertTrue($this->archiver()->setPinned(self::T, 'marker', 1, false));
        $this->archiver()->runScheduled();
        self::assertSame([self::T . ':1:shape:2'], $this->store->liveKeys());
        self::assertFalse($this->archiver()->setPinned(99, 'shape', 2, true), 'jamais l’élément d’une autre communauté');
        self::assertFalse($this->archiver()->setPinned(self::T, 'zone', 2, true));
    }

    public function testSuppressedMarkersAreNeverArchived(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 10:00:00', ['suppressed' => true]);
        $r = $this->archiver()->runScheduled();
        self::assertSame(0, $r['archived']);
        self::assertSame([], $this->store->listArchives(self::T, [], 10, 0));
    }

    public function testNothingIsRemovedWhenTheArchiveCannotBeWritten(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-14 14:00:00');
        $this->store->addShape(self::T, 1, 2, '2026-11-14 15:00:00');
        $this->store->failWrites = true;

        $r = $this->archiver()->runScheduled();

        self::assertFalse($r['ok']);
        self::assertSame(1, $r['errors']);
        self::assertSame(0, $r['removed']);
        self::assertSame([self::T . ':1:marker:1', self::T . ':1:shape:2'], $this->store->liveKeys(), 'aucune suppression sans instantané');
        self::assertSame([], $this->store->archives);
        self::assertStringContainsString('error:', implode("\n", $this->logs));

        // La base revient : le passage suivant reprend où l'on s'était arrêté.
        $this->store->failWrites = false;
        $again = $this->archiver()->runScheduled();
        self::assertTrue($again['ok']);
        self::assertSame([], $this->store->liveKeys());
        self::assertCount(1, $this->store->archives);
    }

    public function testReplayDoesNotDuplicateAndResumesAnInterruptedRun(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-14 14:00:00');
        $this->store->addShape(self::T, 1, 2, '2026-11-14 15:00:00');
        // Passage interrompu entre l'écriture de l'instantané et le retrait : l'archive existe, la carte est intacte.
        $w = AtakMarkerCycleService::windowAt(self::paris(), self::ts('2026-11-14 16:00:00', 'Europe/Paris'));
        $this->store->writeArchive(self::T, 1, $w, 'auto', null, $this->store->staleItems(self::T, 1, '2026-11-15 11:00:00', 10));
        self::assertCount(2, $this->store->liveKeys());

        $first = $this->archiver()->runScheduled();
        $second = $this->archiver()->runScheduled();

        self::assertSame(2, $first['removed']);
        self::assertSame(0, $second['archived']);
        self::assertCount(1, $this->store->archives, 'une seule mission pour ce cycle');
        self::assertCount(2, $this->store->items[array_key_first($this->store->archives)], 'aucun élément en double');
        self::assertSame([], $this->store->liveKeys());
    }

    public function testMissedCyclesAreCaughtUpOneBatchAtATime(): void
    {
        foreach ([1, 2, 3, 4, 5] as $day) {
            $this->store->addMarker(self::T, 1, $day, '2026-11-0' . $day . ' 15:00:00');
        }
        $this->store->addMarker(self::T, 1, 9, '2026-11-15 11:30:00');

        $first = $this->archiver()->runScheduled(['budget' => 3]);
        self::assertSame(3, $first['archived'], 'passage borné');
        self::assertCount(3, $this->store->archives);
        self::assertCount(3, $this->store->liveKeys());

        $second = $this->archiver()->runScheduled(['budget' => 3]);
        self::assertSame(2, $second['archived']);
        self::assertSame([self::T . ':1:marker:9'], $this->store->liveKeys(), 'le cycle courant n’est jamais touché');
        self::assertCount(5, $this->store->archives, 'une mission par cycle manqué');
        $labels = array_map(static fn (array $a): string => (string) $a['label'], $this->store->listArchives(self::T, [], 10, 0));
        self::assertContains('Mission du 01/11 12:00 → 02/11 12:00', $labels);
    }

    public function testYearsOfBacklogAreGroupedAndNeverProcessedAllAtOnce(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->store->addMarker(self::T, 1, $i, gmdate('Y-m-d H:i:s', $this->now - ($i * 40 + 100) * 86400));
        }
        $this->store->addMarker(self::T, 1, 50, '2026-11-14 14:00:00');
        $r = $this->archiver()->runScheduled(['budget' => 5]);
        self::assertSame(5, $r['archived'], 'le rattrapage prudent plafonne le nombre d’éléments par passage');
        self::assertCount(8, $this->store->liveKeys());
        $this->archiver()->runScheduled(['budget' => 100]);
        self::assertSame([], $this->store->liveKeys());
        $legacy = array_values(array_filter($this->store->archives, static fn (array $a): bool => $a['cycle_key'] === 'legacy'));
        self::assertCount(1, $legacy, 'l’existant ancien tient dans une seule mission de reprise');
        self::assertSame(12, $legacy[0]['markers_count']);
        self::assertSame('legacy', $legacy[0]['source']);
        self::assertStringContainsString('Missions antérieures au', (string) $legacy[0]['label']);
        self::assertCount(2, $this->store->archives, 'reprise + la veille');
    }

    public function testDisabledCommunityKeepsTheCurrentBehaviour(): void
    {
        $this->store->settingsByTenant[self::T] = ['enabled' => 0];
        $this->store->addMarker(self::T, 1, 1, '2020-01-01 10:00:00');
        $this->store->addMarker(8, 1, 2, '2026-11-10 10:00:00');
        $r = $this->archiver()->runScheduled();
        self::assertSame([self::T . ':1:marker:1'], array_values(array_filter($this->store->liveKeys(), static fn (string $k): bool => str_starts_with($k, self::T . ':'))), 'rien ne bouge pour la communauté désactivée');
        self::assertSame(1, $r['archived'], 'l’autre communauté est archivée');
        self::assertNull($this->cycles()->readCutoff(self::T));
    }

    public function testCommunitiesAndMapsAreIsolated(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 10:00:00');
        $this->store->addMarker(self::T, 2, 2, '2026-11-10 10:00:00');
        $this->store->addMarker(8, 1, 3, '2026-11-10 10:00:00');
        $this->archiver()->runScheduled(['tenant' => 8]);
        self::assertSame([self::T . ':1:marker:1', self::T . ':2:marker:2'], $this->store->liveKeys(), 'l’archivage d’une communauté ne touche pas les autres');

        $this->archiver()->runScheduled();
        self::assertCount(3, $this->store->archives);
        $mine = $this->store->listArchives(self::T, [], 10, 0);
        self::assertCount(2, $mine);
        foreach ($mine as $a) {
            self::assertSame(self::T, $a['tenant_id']);
        }
        $foreign = null;
        foreach ($this->store->archives as $a) {
            if ($a['tenant_id'] === 8) {
                $foreign = (int) $a['id'];
            }
        }
        self::assertNotNull($foreign);
        self::assertNull($this->archiver()->view(self::T, $foreign), 'consultation d’une archive d’une autre communauté refusée');
        self::assertNull($this->archiver()->export(self::T, $foreign));
        self::assertFalse($this->archiver()->deleteArchive(self::T, $foreign));
        $res = $this->archiver()->restore(self::T, $foreign);
        self::assertFalse($res['ok']);
        self::assertSame([], $this->store->liveKeys(), 'rien restauré');
        self::assertCount(1, $this->store->listArchives(8, [], 10, 0));
        self::assertCount(1, $this->store->listArchives(self::T, ['map' => 2], 10, 0));
    }

    public function testArchivedMissionsAreReadOnlyAndRestoreMakesCopies(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-14 14:00:00', ['web_permanent' => false, 'source' => 'arma'], 'arma_1');
        $this->store->addShape(self::T, 1, 2, '2026-11-14 15:00:00');
        $this->archiver()->runScheduled();
        $id = (int) array_key_first($this->store->archives);
        $before = json_encode([$this->store->archives, $this->store->items]);

        $view = $this->archiver()->view(self::T, $id);
        self::assertNotNull($view);
        self::assertFalse($view['truncated']);
        self::assertSame($before, json_encode([$this->store->archives, $this->store->items]), 'consulter ne change rien');
        self::assertSame([], $this->store->liveKeys(), 'consulter ne touche pas la carte');

        $res = $this->archiver()->restore(self::T, $id);
        self::assertTrue($res['ok']);
        self::assertSame(2, $res['restored']);
        self::assertCount(2, $this->store->live);
        self::assertSame($before, json_encode([$this->store->archives, $this->store->items]), 'l’archive reste intacte après restauration');
        foreach ($this->store->live as $row) {
            self::assertNotSame(1, $row['id']);
            if ($row['kind'] === 'marker') {
                self::assertNull($row['payload']['arma_name'], 'jamais relié au marqueur du jeu d’origine');
                $d = json_decode((string) $row['payload']['marker_data'], true);
                self::assertSame($id, $d['restored_from_archive']);
                self::assertTrue(empty($d['web_permanent']));
            } else {
                self::assertStringContainsString('arc' . $id, (string) $row['payload']['shape_uid']);
            }
        }
        // Les copies sont du cycle courant : la bascule suivante ne les archive pas tout de suite.
        $r = $this->archiver()->runScheduled();
        self::assertSame(0, $r['archived']);
        $export = $this->archiver()->export(self::T, $id);
        self::assertSame('comspec-atak-mission-archive', $export['format']);
        self::assertCount(2, $export['items']);
    }

    public function testRestorableRejectsUnreadableItems(): void
    {
        self::assertNull(AtakMarkerArchiveService::restorable('marker', ['marker_data' => 'pas du json'], 1, 1));
        self::assertNull(AtakMarkerArchiveService::restorable('zone', [], 1, 1));
    }

    public function testManualPurgeAndRetention(): void
    {
        $old = ['key' => '20240101T110000Z', 'label' => 'Mission du 01/01 12:00 → 02/01 12:00', 'start_utc' => '2024-01-01 11:00:00', 'end_utc' => '2024-01-02 11:00:00'];
        $this->store->writeArchive(self::T, 1, $old, 'auto', null, []);
        $this->store->writeArchive(8, 1, $old, 'auto', null, []);
        $this->store->addMarker(self::T, 1, 100, '2026-11-14 14:00:00');
        $this->store->settingsByTenant[self::T] = ['enabled' => 1, 'retention_days' => 365];
        $this->store->settingsByTenant[8] = ['enabled' => 1, 'retention_days' => 0];

        $r = $this->archiver()->runScheduled();

        self::assertSame(1, $r['purged'], 'la mission de 2024 dépasse la conservation d’un an');
        self::assertCount(1, $this->store->listArchives(self::T, [], 10, 0), 'la mission de la veille, toute récente, reste');
        self::assertCount(1, $this->store->listArchives(8, [], 10, 0), 'conservation sans limite (0) : rien n’est purgé');
        // Une mission tout juste archivée n'est jamais purgée dans le même passage, même pour un très vieux contenu.
        $this->store->addMarker(self::T, 1, 101, '2020-05-05 10:00:00');
        $this->archiver()->runScheduled();
        self::assertCount(1, array_filter($this->store->archives, static fn (array $a): bool => $a['cycle_key'] === 'legacy'));

        $keep = (int) $this->store->listArchives(self::T, [], 10, 0)[0]['id'];
        self::assertFalse($this->archiver()->deleteArchive(8, $keep), 'une purge manuelle reste dans sa communauté');
        self::assertTrue($this->archiver()->deleteArchive(self::T, $keep));
    }

    public function testSearchByDate(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 14:00:00');
        $this->store->addMarker(self::T, 1, 2, '2026-11-13 14:00:00');
        $this->archiver()->runScheduled();
        self::assertCount(2, $this->archiver()->listArchives(self::T));
        $hit = $this->archiver()->listArchives(self::T, ['from' => '2026-11-13 00:00:00', 'to' => '2026-11-13 23:59:59']);
        self::assertCount(1, $hit);
        self::assertSame('Mission du 13/11 12:00 → 14/11 12:00', $hit[0]['label']);
        self::assertCount(1, $this->archiver()->listArchives(self::T, ['q' => '10/11']));
        self::assertSame([], $this->archiver()->listArchives(self::T, ['q' => '01/01']));
    }

    public function testConcurrentRunIsRefusedAndLockIsReleased(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 10:00:00');
        $this->store->lockHeld = true;
        $r = $this->archiver()->runScheduled();
        self::assertTrue($r['skipped']);
        self::assertSame([self::T . ':1:marker:1'], $this->store->liveKeys());
        self::assertSame(0, $this->store->unlocks, 'le verrou d’un autre passage n’est pas relâché');

        $this->store->lockHeld = false;
        $this->archiver()->runScheduled();
        self::assertSame(1, $this->store->locks);
        self::assertSame(1, $this->store->unlocks);
        self::assertFalse($this->store->lockHeld);
    }

    public function testNotReadyAndDryRunChangeNothing(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 10:00:00');
        $this->store->ready = false;
        self::assertTrue($this->archiver()->runScheduled()['skipped']);
        $this->store->ready = true;
        $dry = $this->archiver()->runScheduled(['dry_run' => true]);
        self::assertSame(1, $dry['archived']);
        self::assertSame(0, $dry['removed']);
        self::assertSame([], $this->store->archives);
        self::assertSame([self::T . ':1:marker:1'], $this->store->liveKeys());
    }

    // ------------------------------------------------------------------ tâche planifiée, étape, sources

    public function testCronJobIsScheduledAndRegistered(): void
    {
        $this->store->addMarker(self::T, 1, 1, '2026-11-10 10:00:00');
        $job = new AtakMarkerCycleCronJob($this->archiver());
        self::assertSame('atak_marker_cycle', $job->key());
        $res = $job->run();
        self::assertTrue($res['ok']);
        self::assertStringContainsString('1 élément(s) archivé(s)', $res['summary']);
        self::assertSame(15, CronSchedule::intervalMinutes('atak_marker_cycle'));
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('self::get(\App\Services\Cron\Jobs\AtakMarkerCycleCronJob::class)', $container);
        self::assertStringContainsString('Jobs\AtakMarkerCycleCronJob::class => new', $container);
    }

    public function testMigrationStepSqlAndRollbackAreInPlace(): void
    {
        $registry = (string) file_get_contents(self::ROOT . '/database/steps/registry.php');
        self::assertStringContainsString("'id' => 'atak_marker_cycles'", $registry);
        self::assertStringContainsString("'file' => '4630_atak_marker_cycles.php'", $registry);
        self::assertTrue(is_file(self::ROOT . '/database/steps/4630_atak_marker_cycles.php'));
        $sql = (string) file_get_contents(self::ROOT . '/migrations/2026_11_15_130_atak_marker_cycles.sql');
        foreach (['atak_marker_cycle_settings', 'atak_marker_archives', 'atak_marker_archive_items', 'uq_atak_marker_archive_item', 'uq_atak_marker_archive_cycle'] as $needle) {
            self::assertStringContainsString($needle, $sql);
        }
        self::assertStringNotContainsString('DELETE FROM atak_markers', $sql, 'l’étape ne modifie aucune donnée existante');
        $down = (string) file_get_contents(self::ROOT . '/migrations/rollback/atak_marker_cycles_down.sql');
        self::assertStringContainsString('DROP TABLE IF EXISTS atak_marker_archive_items', $down);
        $ids = [];
        preg_match_all("/'id' => '([a-z0-9_]+)'/", $registry, $m);
        $ids = $m[1];
        self::assertSame(count($ids), count(array_unique($ids)), 'identifiants d’étapes uniques');
    }

    public function testArchiveIsWrittenBeforeAnyRemovalInTheSource(): void
    {
        $svc = (string) file_get_contents(self::ROOT . '/app/Services/Atak/MarkerCycle/AtakMarkerArchiveService.php');
        self::assertTrue(strpos($svc, 'writeArchive(') < strpos($svc, 'deleteLive('), 'écriture de l’instantané puis retrait');
        $store = (string) file_get_contents(self::ROOT . '/app/Services/Atak/MarkerCycle/PdoMarkerCycleStore.php');
        self::assertStringContainsString('beginTransaction()', $store);
        self::assertStringContainsString('->commit()', $store);
        self::assertStringContainsString('rollBack()', $store);
        self::assertStringContainsString('INSERT IGNORE INTO atak_marker_archive_items', $store);
        // Un seul endroit retire des éléments de la carte : deleteLive, appelé après l'instantané.
        self::assertSame(1, substr_count($store, 'DELETE FROM atak_markers '));
        self::assertSame(1, substr_count($store, 'DELETE FROM atak_map_shapes '));
    }
}
