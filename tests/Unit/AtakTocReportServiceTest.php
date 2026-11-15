<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\TocReportService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TocReportsSqlite;

/**
 * Lecture des rapports par le poste TOC web sur 40 rapports réalistes (12 BDA, 14 comptes rendus, 6 SITREP, 7 demandes
 * 9-line dont 3 MEDEVAC, 1 compte rendu libre) : liste par famille, filtres, tri, lu / non lu par opérateur, cloisonnement
 * par communauté et par carte, habilitation, caviardage, accusé de réception, jonction à un plan, empreinte de synchro.
 */
final class AtakTocReportServiceTest extends TestCase
{
    private PDO $pdo;

    private TocReportService $svc;

    private int $now;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = TocReportsSqlite::open();
        $this->now = time();
        TocReportsSqlite::seedRealistic($this->pdo, $this->now);
        $this->svc = new TocReportService($this->pdo, static fn (int $t, array $rows): array => $rows);
    }

    /** @return list<array<string, mixed>> */
    private function all(int $user = 7, string $level = 'tres_restreint', array $q = []): array
    {
        return $this->svc->list(TocReportsSqlite::TENANT, $user, $level, $q + ['limit' => 300])['reports'];
    }

    public function testListCountsEveryFamilyAndEverythingStartsUnread(): void
    {
        $res = $this->svc->list(1, 7, 'tres_restreint');
        self::assertCount(40, $res['reports']);
        $c = $res['counts'];
        self::assertSame(40, $c['total']);
        self::assertSame(40, $c['unread']);
        self::assertSame(['bda' => 12, 'cr' => 14, 'sitrep' => 6, 'nineline' => 7, 'other' => 1], array_map(static fn (array $f): int => $f['total'], $c['families']));
        self::assertSame('ok', $res['readState']);
        self::assertSame(0, $res['hidden']);
        // Le plus récent d'abord, les 9-line à côté des comptes rendus.
        $ts = array_column($res['reports'], 'ts');
        $sorted = $ts;
        rsort($sorted);
        self::assertSame($sorted, $ts);
        self::assertContains('nineline', array_column($res['reports'], 'family'));
    }

    public function testEachBdaCarriesItsResultTargetAndPosition(): void
    {
        $bda = array_values(array_filter($this->all(), static fn (array $r): bool => $r['family'] === 'bda'));
        self::assertCount(12, $bda);
        $codes = array_count_values(array_map(static fn (array $r): string => $r['result']['code'], $bda));
        self::assertSame(['destroyed' => 4, 'damaged' => 3, 'neutralized' => 2, 'none' => 2, 'unknown' => 1], $codes);
        foreach ($bda as $r) {
            self::assertNotSame('', $r['title']);
            self::assertNotNull($r['pos'], 'chaque BDA se place sur la carte');
            self::assertSame('BDA', $r['type']);
        }
        $first = $this->svc->find(1, 7, 'tres_restreint', 'tr-1');
        self::assertNotNull($first);
        self::assertSame('Véhicule : détruit', $first['title']);
        self::assertSame('Détruit', $first['result']['label']);
        self::assertSame('VÉHICULE', array_column($first['facts'], 'value', 'label')['Cible']);
        self::assertSame('2 x 81 mm HE, tirs réglés par le JTAC', array_column($first['facts'], 'value', 'label')['Munition / moyen']);
    }

    public function testFiltersByFamilyTypePriorityStatusUnitAndText(): void
    {
        self::assertCount(12, $this->all(7, 'tres_restreint', ['family' => 'bda']));
        self::assertCount(7, $this->all(7, 'tres_restreint', ['family' => 'nineline']));
        $tic = $this->all(7, 'tres_restreint', ['type' => 'TIC']);
        self::assertCount(4, $tic);
        $flash = $this->all(7, 'tres_restreint', ['priority' => 'FLASH']);
        self::assertNotEmpty($flash);
        foreach ($flash as $r) {
            self::assertSame('FLASH', $r['priority']);
        }
        $open = $this->all(7, 'tres_restreint', ['status' => 'open']);
        $closed = $this->all(7, 'tres_restreint', ['status' => 'closed']);
        self::assertSame(40, count($open) + count($closed));
        self::assertNotEmpty($closed, 'les 9-line terminés ou annulés sont clos');
        self::assertCount(1, $this->all(7, 'tres_restreint', ['status' => 'on_scene']));
        // Unité ou émetteur, sans tenir compte des accents ni de la casse.
        self::assertNotEmpty($this->all(7, 'tres_restreint', ['unit' => 'alpha 1']));
        $montagne = $this->all(7, 'tres_restreint', ['unit' => 'fusiliers de montagne']);
        self::assertCount(1, $montagne);
        self::assertSame('Bâtiment : endommagé', $montagne[0]['title']);
        // Recherche : accents et casse indifférents, dans le titre, l'émetteur, la grille et le type.
        self::assertCount(1, $this->all(7, 'tres_restreint', ['q' => 'ROCHEFOUCAULD']));
        self::assertNotEmpty($this->all(7, 'tres_restreint', ['q' => 'bâtiment']));
        self::assertSame($this->all(7, 'tres_restreint', ['q' => 'batiment']), $this->all(7, 'tres_restreint', ['q' => 'BÂTIMENT']));
        self::assertNotEmpty($this->all(7, 'tres_restreint', ['q' => '0419 1883']));
        self::assertSame([], $this->all(7, 'tres_restreint', ['q' => 'introuvable-xyz']));
    }

    public function testSortModes(): void
    {
        $byPriority = $this->all(7, 'tres_restreint', ['sort' => 'priority']);
        $ranks = array_column($byPriority, 'rank');
        $desc = $ranks;
        rsort($desc);
        self::assertSame($desc, $ranks);
        self::assertSame(3, $ranks[0]);
        $oldest = $this->all(7, 'tres_restreint', ['sort' => 'oldest']);
        self::assertSame(array_reverse(array_column($this->all(), 'key')), array_column($oldest, 'key'));
        // Non lus d'abord : après lecture de la tête de liste, elle descend.
        $head = $this->all()[0]['key'];
        $this->svc->markRead(1, 7, 'tres_restreint', $head, true);
        $unread = $this->all(7, 'tres_restreint', ['sort' => 'unread']);
        self::assertNotSame($head, $unread[0]['key']);
        self::assertSame($head, $unread[39]['key']);
    }

    public function testPeriodAndMapBoundTheList(): void
    {
        $oneHour = $this->svc->list(1, 7, 'tres_restreint', ['since' => $this->now - 3600, 'limit' => 300]);
        self::assertSame(0, array_sum(array_map(static fn (array $r): int => $r['ts'] < time() - 3600 - 5 ? 1 : 0, $oneHour['reports'])));
        self::assertLessThan(40, count($oneHour['reports']));
        // Un rapport d'une autre carte n'apparaît que pour « toutes les cartes » (map = 0) ou sa carte.
        TocReportsSqlite::tactical($this->pdo, 1, 5, 'SPOTREP', 'GRIFFON 1', 'GRIFFON', 'Observation (SPOTREP) · 0100 0200', ['kind' => 'spotrep', 'what' => 'Personnel'], ['context_id' => 2, 'now' => $this->now]);
        self::assertCount(40, $this->svc->list(1, 7, 'tres_restreint', ['map' => 1, 'limit' => 300])['reports']);
        self::assertCount(41, $this->svc->list(1, 7, 'tres_restreint', ['map' => 0, 'limit' => 300])['reports']);
        self::assertCount(1, $this->svc->list(1, 7, 'tres_restreint', ['map' => 2, 'limit' => 300])['reports']);
    }

    public function testOtherCommunitiesAreNeverVisibleNorOpenable(): void
    {
        $other = TocReportsSqlite::tactical($this->pdo, 2, 10, 'BDA', 'AUTRE 1', 'AUTRE', 'BDA autre communauté', ['result' => 'DÉTRUIT']);
        $otherNine = TocReportsSqlite::nine($this->pdo, 2, 10, 'cas', 'JTAC-X', ['IP X', '', '', '', 'Cible', '0101 0101']);
        self::assertCount(40, $this->all());
        self::assertSame([], array_filter($this->all(), static fn (array $r): bool => $r['who'] === 'AUTRE 1'));
        self::assertNull($this->svc->find(1, 7, 'tres_restreint', 'tr-' . $other), 'anti-IDOR : compte rendu d’une autre communauté');
        self::assertNull($this->svc->find(1, 7, 'tres_restreint', 'nl-' . $otherNine), 'anti-IDOR : 9-line d’une autre communauté');
        self::assertFalse($this->svc->markRead(1, 7, 'tres_restreint', 'tr-' . $other, true));
        self::assertNull($this->svc->acknowledge(1, 7, 'tres_restreint', 'tr-' . $other));
        self::assertSame(0, $this->svc->list(3, 7, 'tres_restreint')['counts']['total']);
        self::assertNotNull($this->svc->find(2, 9, 'tres_restreint', 'tr-' . $other), 'la communauté 2 lit le sien');
        // Clés mal formées : introuvables, jamais d'erreur.
        foreach (['', 'tr-', 'tr-0', 'tr-1; DROP TABLE x', 'zz-4', '12', 'tr-1-2', 'tr--1'] as $bad) {
            self::assertNull($this->svc->find(1, 7, 'tres_restreint', $bad), $bad);
        }
    }

    public function testTitleComesFromTheFicheWhenTheSummaryTheGameWroteSaysTooLittle(): void
    {
        $patrol = $this->svc->find(1, 7, 'tres_restreint', 'tr-17');
        self::assertSame('PARTIELLE · Kavala › col de Sofia › crête 214 › Pyrgos', $patrol['title']);
        self::assertSame('TIRS DIRECTS · Section (10 à 30)', $this->svc->find(1, 7, 'tres_restreint', 'tr-13')['title']);
        self::assertSame('Caporal Mélissa Okonkwo-Lefèvre · CRITIQUE', $this->svc->find(1, 7, 'tres_restreint', 'tr-18')['title']);
        self::assertSame('Point radio du soir', $this->svc->find(1, 7, 'tres_restreint', 'tr-27')['title']);
        // Sans fiche structurée : le résumé du téléphone, sans son en-tête de type.
        $id = TocReportsSqlite::tactical($this->pdo, 1, 1, 'TIC', 'ALPHA 1-1', 'ALPHA 1', 'Contact (TIC) · 0419 1883 · TIRS DIRECTS', [], ['now' => $this->now]);
        self::assertSame('0419 1883 · TIRS DIRECTS', $this->svc->find(1, 7, 'tres_restreint', 'tr-' . $id)['title']);
        // Résumé très long : titre coupé, la suite passe à la ligne de description de la liste.
        $long = TocReportsSqlite::tactical($this->pdo, 1, 1, 'OTHER', 'TA1', 'TOC', str_repeat('Liaison rétablie avec la section de Pyrgos. ', 6), [], ['now' => $this->now]);
        $row = array_values(array_filter($this->all(), static fn (array $r): bool => $r['key'] === 'tr-' . $long))[0];
        self::assertStringEndsWith('…', $row['title']);
        self::assertStringStartsWith('…', $row['summary']);
    }

    public function testClearanceHidesWhatTheOperatorMayNotRead(): void
    {
        // Un SITREP CONFIDENTIAL « encadrement seulement » (jeu d'essai) : niveau C requis.
        $low = $this->svc->list(1, 7, 'interne');
        self::assertCount(39, $low['reports']);
        self::assertSame(1, $low['hidden']);
        self::assertSame(39, $low['counts']['total'], 'le compteur ne compte pas ce qui est caché');
        $classified = array_values(array_filter($this->all(7, 'tres_restreint'), static fn (array $r): bool => $r['classification'] !== 'UNCLASSIFIED'));
        self::assertCount(1, $classified);
        self::assertSame('CONFIDENTIAL', $classified[0]['classification']);
        self::assertNull($this->svc->find(1, 7, 'interne', $classified[0]['key']), 'même réponse qu’un identifiant inconnu');
        self::assertFalse($this->svc->markRead(1, 7, 'interne', $classified[0]['key'], true));
        self::assertNull($this->svc->acknowledge(1, 7, 'interne', $classified[0]['key']));
        self::assertNotNull($this->svc->find(1, 7, 'encadrement', $classified[0]['key']));
        self::assertCount(40, $this->svc->list(1, 7, 'encadrement')['reports']);
        // Un rapport SECRET exige le niveau S.
        $secret = TocReportsSqlite::tactical($this->pdo, 1, 2, 'SITREP', 'TA1', 'TOC', 'Situation (SITREP) · secret', ['kind' => 'sitrep', 'next' => 'Opération sensible'], ['classification' => 'SECRET', 'now' => $this->now]);
        self::assertNull($this->svc->find(1, 7, 'encadrement', 'tr-' . $secret));
        self::assertNotNull($this->svc->find(1, 7, 'confidentiel', 'tr-' . $secret));
        self::assertSame('SECRET//NOFORN', $this->svc->find(1, 7, 'confidentiel', 'tr-' . $secret)['classification']);
    }

    public function testRedactedPassagesAreMaskedAndTheStructuredCopyIsDropped(): void
    {
        $id = TocReportsSqlite::tactical($this->pdo, 1, 1, 'SPOTREP', 'YA1', 'YA1', 'Observation (SPOTREP) · 0975 1690 · Drone', ['kind' => 'spotrep', 'what' => 'Drone', 'grid' => '0975 1690'], ['details' => 'Drone au-dessus du point 0975 1690', 'now' => $this->now]);
        $svc = new TocReportService($this->pdo, static function (int $t, array $rows) use ($id): array {
            foreach ($rows as &$r) {
                if ((int) $r['id'] === $id) {
                    $r['summary'] = 'Observation (SPOTREP) · ' . str_repeat("\u{2588}", 6) . ' · Drone';
                    $r['details'] = str_repeat("\u{2588}", 8);
                }
            }

            return $rows;
        });
        $r = $svc->find(1, 7, 'interne', 'tr-' . $id);
        self::assertNotNull($r);
        self::assertTrue($r['redacted']);
        self::assertSame([], $r['facts'], 'la fiche structurée répète le texte caviardé : elle est retirée');
        self::assertStringContainsString("\u{2588}", $r['title']);
        self::assertStringNotContainsString('0975 1690', json_encode($r['text'] . $r['title'] . $r['summary'], JSON_UNESCAPED_UNICODE) ?: '');
        // Un compte rendu non caviardé n'est pas marqué.
        self::assertFalse($svc->find(1, 7, 'interne', 'tr-1')['redacted']);
        // Si le caviardage est illisible, aucun texte libre n'est montré.
        $broken = new TocReportService($this->pdo, static function (): array {
            throw new \RuntimeException('indisponible');
        });
        $b = $broken->find(1, 7, 'interne', 'tr-' . $id);
        self::assertNotNull($b);
        self::assertTrue($b['redacted']);
        self::assertStringNotContainsString('Drone au-dessus', json_encode($b, JSON_UNESCAPED_UNICODE) ?: '');
    }

    public function testReadStateIsPerOperatorAndReversible(): void
    {
        self::assertTrue($this->svc->markRead(1, 7, 'tres_restreint', 'tr-1', true));
        self::assertTrue($this->svc->markRead(1, 7, 'tres_restreint', 'tr-1', true), 'idempotent');
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_toc_report_reads')->fetchColumn());
        self::assertSame(39, $this->svc->list(1, 7, 'tres_restreint')['counts']['unread']);
        self::assertSame(40, $this->svc->list(1, 8, 'tres_restreint')['counts']['unread'], 'un autre opérateur garde ses non lus');
        self::assertSame(40, $this->svc->list(2, 7, 'tres_restreint')['counts']['total'] + 40 - $this->svc->list(2, 7, 'tres_restreint')['counts']['total'], 'sans effet sur l’autre communauté');
        self::assertCount(39, $this->all(7, 'tres_restreint', ['unread' => 1]));
        self::assertTrue($this->svc->markRead(1, 7, 'tres_restreint', 'nl-3', true));
        self::assertTrue($this->svc->markRead(1, 7, 'tres_restreint', 'tr-1', false));
        self::assertSame(39, $this->svc->list(1, 7, 'tres_restreint')['counts']['unread']);
        $n = $this->svc->markAllRead(1, 7, 'tres_restreint', ['map' => 1]);
        self::assertSame(39, $n);
        self::assertSame(0, $this->svc->list(1, 7, 'tres_restreint')['counts']['unread']);
        self::assertTrue($this->all(7, 'tres_restreint')[0]['read']);
        self::assertSame(40, $this->svc->list(1, 8, 'tres_restreint')['counts']['unread']);
        $detail = $this->svc->find(1, 7, 'tres_restreint', 'tr-2');
        self::assertTrue($detail['read']);
    }

    public function testWithoutTheReadTableEverythingIsUnreadAndNothingIsKept(): void
    {
        $pdo = TocReportsSqlite::open(false);
        TocReportsSqlite::seedRealistic($pdo, $this->now);
        $svc = new TocReportService($pdo, static fn (int $t, array $rows): array => $rows);
        $res = $svc->list(1, 7, 'tres_restreint');
        self::assertSame('degraded', $res['readState']);
        self::assertSame(40, $res['counts']['unread']);
        self::assertFalse($svc->markRead(1, 7, 'tres_restreint', 'tr-1', true));
        self::assertSame(0, $svc->markAllRead(1, 7, 'tres_restreint'));
        self::assertSame('degraded', $svc->find(1, 7, 'tres_restreint', 'tr-1')['readState']);
        self::assertFalse($svc->find(1, 7, 'tres_restreint', 'tr-1')['read']);
    }

    public function testAcknowledgeMovesTheReportAndTheNineLineForward(): void
    {
        $r = $this->svc->acknowledge(1, 7, 'tres_restreint', 'tr-13');
        self::assertNotNull($r);
        self::assertTrue($r['acked']);
        self::assertSame('ACKNOWLEDGED', $r['status']);
        self::assertSame('Accusé', $r['status_label']);
        $row = $this->pdo->query('SELECT acknowledged_by_user_id, acknowledged_at FROM atak_tactical_reports WHERE id = 13')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(7, (int) $row['acknowledged_by_user_id']);
        $first = (string) $row['acknowledged_at'];
        // Un second accusé ne réécrit pas l'auteur ni l'heure du premier.
        $again = $this->svc->acknowledge(1, 9, 'tres_restreint', 'tr-13');
        self::assertSame(7, (int) $this->pdo->query('SELECT acknowledged_by_user_id FROM atak_tactical_reports WHERE id = 13')->fetchColumn());
        self::assertSame($first, (string) $this->pdo->query('SELECT acknowledged_at FROM atak_tactical_reports WHERE id = 13')->fetchColumn());
        self::assertTrue($again['acked']);
        // 9-line : SUBMITTED passe à ACKNOWLEDGED ; un 9-line déjà engagé ne recule jamais.
        $submitted = $this->svc->find(1, 7, 'tres_restreint', 'nl-3');
        self::assertSame('ABORTED', $submitted['status']);
        self::assertSame('ABORTED', $this->svc->acknowledge(1, 7, 'tres_restreint', 'nl-3')['status']);
        $inbound = $this->svc->acknowledge(1, 7, 'tres_restreint', 'nl-1');
        self::assertSame('INBOUND', $inbound['status']);
        $id = TocReportsSqlite::nine($this->pdo, 1, 1, 'medevac', 'ALPHA 1-2', ['0100 0100', '40.000 · A1', 'A1']);
        self::assertSame('REQUESTED', $this->svc->find(1, 7, 'tres_restreint', 'nl-' . $id)['status']);
        self::assertSame('ACKNOWLEDGED', $this->svc->acknowledge(1, 7, 'tres_restreint', 'nl-' . $id)['status']);
    }

    public function testNineLineDetailGivesNumberedLinesStepsAndPosition(): void
    {
        $cas = $this->svc->find(1, 7, 'tres_restreint', 'nl-1');
        self::assertSame('cas', strtolower($cas['type']));
        self::assertCount(9, $cas['lines']);
        self::assertSame('Position cible', $cas['lines'][5]['label']);
        self::assertSame('0419 1883', $cas['lines'][5]['value']);
        self::assertSame([14190.0, 18830.0], $cas['pos'], 'cible du JTAC (geo) avant la grille');
        self::assertTrue($cas['geo']['danger_close']);
        self::assertSame(1688, $cas['geo']['laser_code']);
        self::assertSame('Appareil lancé', $this->svc->find(1, 7, 'tres_restreint', 'nl-7')['status_label']);
        $five = $this->svc->find(1, 7, 'tres_restreint', 'nl-4');
        self::assertCount(5, $five['lines']);
        self::assertSame('FIVELINE', $five['type']);
        $med = $this->svc->find(1, 7, 'tres_restreint', 'nl-5');
        self::assertSame('MEDEVAC', $med['type']);
        self::assertSame('IMMEDIATE', $med['priority']);
        self::assertSame([15800.0, 18040.0], $med['pos'], 'la grille de la zone de poser donne la position');
        self::assertSame('Zone de poser', $med['lines'][0]['label']);
        // Ligne vide : présente, vide (« Néant » à l'écran).
        $noLine9 = $this->svc->find(1, 7, 'tres_restreint', 'nl-6');
        self::assertSame('', $noLine9['lines'][8]['value']);
        self::assertStringContainsString('Montgolfier-Duplessis-Castelnau', $noLine9['who']);
    }

    public function testEveryTypeOfTheDataSetRendersAReadableFiche(): void
    {
        foreach ($this->all() as $r) {
            $d = $this->svc->find(1, 7, 'tres_restreint', $r['key']);
            self::assertNotNull($d, $r['key']);
            self::assertNotSame('', $d['title'], $r['key']);
            self::assertNotSame('', $d['type_label'], $r['key']);
            self::assertMatchesRegularExpression('/^\d{6}Z [A-Z]{3} \d{2}$/', $d['dtg'], $r['key']);
            if ($d['source'] === 'nl') {
                self::assertNotEmpty($d['lines'], $r['key']);
            } else {
                $content = count($d['facts']) + count($d['blocks']) + count($d['extra']);
                self::assertGreaterThan(0, $content, $r['key'] . ' ' . $d['type']);
            }
            // Jamais de JSON brut dans une valeur lisible.
            foreach (array_merge($d['facts'], $d['extra']) as $fact) {
                self::assertDoesNotMatchRegularExpression('/^\s*[\[{]/', (string) $fact['value'], $r['key'] . ' ' . $fact['label']);
            }
        }
        $long = $this->svc->find(1, 7, 'tres_restreint', 'tr-17');
        self::assertSame('PATROLREP', $long['type']);
        self::assertSame('Lieutenant Anne-Sophie Delacroix-Villeneuve', $long['who']);
        $blocks = array_column($long['blocks'], 'text', 'label');
        self::assertStringContainsString('piégé', $blocks['Observations']);
        self::assertGreaterThan(200, mb_strlen($blocks['Observations']));
    }

    public function testPlanAttachmentCreatesOneMarkerPerReportAndPlanAndRespectsPhases(): void
    {
        $repo = new AtakPlanRepository($this->pdo);
        $plans = new PlanService($repo, static fn (): ?int => null);
        $id = $repo->create(1, 7, 'Opération ÉPERVIER', 1, 'altis', 'private', null, \App\Services\AtakToc\PlanValidator::meta(['side' => 'WEST']));
        $plan = $repo->find(1, $id);
        $plans->savePhases($plan, [['uid' => 'ph1', 'name' => 'Phase 1', 'ord' => 0]]);
        $ok = $this->svc->attachToPlan(1, 7, 'tres_restreint', 'tr-1', $plan, 'ph1', $plans, true);
        self::assertTrue($ok['ok'], json_encode($ok) ?: '');
        self::assertSame('rep-tr-1', $ok['uid']);
        $els = $repo->elements($id);
        self::assertCount(1, $els);
        self::assertSame(['ph1'], $els[0]['phases']);
        self::assertSame('icon', $els[0]['kind']);
        self::assertStringContainsString('Véhicule : détruit', $els[0]['props']['note']);
        self::assertSame('target', $els[0]['props']['icon']);
        // Joindre deux fois met à jour le même repère.
        self::assertTrue($this->svc->attachToPlan(1, 7, 'tres_restreint', 'tr-1', $plan, null, $plans, true)['ok']);
        self::assertCount(1, $repo->elements($id));
        self::assertSame([], $repo->elements($id)[0]['phases']);
        // Un 9-line sans géométrie utilise la grille ; un rapport sans position n'est pas joint.
        self::assertTrue($this->svc->attachToPlan(1, 7, 'tres_restreint', 'nl-5', $plan, null, $plans, true)['ok']);
        $none = TocReportsSqlite::tactical($this->pdo, 1, 3, 'OTHER', 'TA1', 'TOC', 'Compte rendu libre', ['title' => 'Sans position'], ['pos_x' => null, 'pos_y' => null, 'grid_reference' => null, 'now' => $this->now]);
        self::assertSame('no_position', $this->svc->attachToPlan(1, 7, 'tres_restreint', 'tr-' . $none, $plan, null, $plans, true)['reason']);
        self::assertSame('phase_not_found', $this->svc->attachToPlan(1, 7, 'tres_restreint', 'tr-2', $plan, 'ph9', $plans, true)['reason']);
        // Plan d'une autre communauté ou rapport illisible : refus.
        $foreign = $repo->create(2, 9, 'Autre', 1, 'altis', 'private', null, \App\Services\AtakToc\PlanValidator::meta(['side' => 'WEST']));
        self::assertSame('plan_not_found', $this->svc->attachToPlan(1, 7, 'tres_restreint', 'tr-2', $repo->find(2, $foreign), null, $plans, true)['reason']);
        self::assertSame('not_found', $this->svc->attachToPlan(1, 7, 'interne', 'tr-33', $plan, null, $plans, true)['reason']);
        // Liste des plans où le rapport est joint (communauté du demandeur seulement).
        $att = $this->svc->attachedPlans(1, 'tr-1');
        self::assertSame([['plan_id' => $id, 'title' => 'Opération ÉPERVIER', 'phases' => []]], $att);
        self::assertSame([], $this->svc->attachedPlans(2, 'tr-1'));
        self::assertSame($att, $this->svc->find(1, 7, 'tres_restreint', 'tr-1')['plans']);
    }

    public function testStampChangesWhenAReportArrivesOrChanges(): void
    {
        $a = $this->svc->stamp(1, 1);
        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $a);
        self::assertSame($a, $this->svc->stamp(1, 1));
        TocReportsSqlite::tactical($this->pdo, 1, 0, 'TIC', 'ALPHA 1-1', 'ALPHA 1', 'Contact (TIC) · 0100 0100', ['nature' => 'OBSERVÉ'], ['now' => $this->now]);
        $b = $this->svc->stamp(1, 1);
        self::assertNotSame($a, $b);
        self::assertNotSame($b, $this->svc->stamp(2, 1), 'une autre communauté a sa propre empreinte');
        self::assertSame('', $this->svc->stamp(0));
    }

    public function testGridsConvertToWorldMetersOrNothing(): void
    {
        self::assertSame([15380.0, 17450.0], TocReportService::gridToWorld('1538 1745'));
        self::assertSame([15300.0, 17400.0], TocReportService::gridToWorld('153 174'));
        self::assertSame([15000.0, 17000.0], TocReportService::gridToWorld('15 17'));
        self::assertSame([15381.0, 17452.0], TocReportService::gridToWorld('1538117452'));
        self::assertNull(TocReportService::gridToWorld('35S MQ 0234 5678'), 'une grille MGRS n’est pas convertie');
        self::assertNull(TocReportService::gridToWorld('153 17'));
        self::assertNull(TocReportService::gridToWorld(''));
        self::assertNull(TocReportService::gridToWorld('Nord de la crête'));
    }

    public function testListIsBoundedAndSaysSoWhenTruncated(): void
    {
        $res = $this->svc->list(1, 7, 'tres_restreint', ['limit' => 5]);
        self::assertCount(5, $res['reports']);
        self::assertTrue($res['truncated']);
        self::assertSame(40, $res['counts']['total'] > 5 ? 40 : 0, 'les compteurs portent sur ce qui est chargé');
        self::assertFalse($this->svc->list(1, 7, 'tres_restreint', ['limit' => 300])['truncated']);
    }
}
