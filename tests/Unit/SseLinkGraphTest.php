<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\Links\SseLinkGraph;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySseLinkViewer;
use Tests\Support\SseLinksFixture;
use Tests\Support\SseLinksSqlite;

/**
 * Chemin de liaison d'un élément SSE : un graphe réaliste (une personne reliée à trois contrôles, une identité du registre, deux
 * papiers, un avis de recherche, deux documents émis, quatre photos, un dossier classé automatiquement, un compte rendu JNET), le
 * parcours (chemins, cycles, bornes de profondeur et de nœuds, déduplication, requêtes sans N+1) et le filtre de confidentialité
 * (identité de membre, identité de couverture, dossier classifié, rôle sans droit, communautés).
 */
final class SseLinkGraphTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseLinksSqlite::open();
        $this->ids = SseLinksFixture::seed($this->pdo);
    }

    protected function tearDown(): void
    {
        SseLinksSqlite::close();
    }

    private function graph(?ArraySseLinkViewer $viewer = null, bool $jnet = true): SseLinkGraph
    {
        return new SseLinkGraph($viewer ?? ArraySseLinkViewer::full(), null, null, null, $jnet ? SseLinksFixture::jnetBridge('case:' . $this->ids['case']) : null);
    }

    /** @return array<string, int> nombre de nœuds par type */
    private static function typesOf(array $g): array
    {
        $n = [];
        foreach ($g['nodes'] as $node) {
            $n[$node['type']] = ($n[$node['type']] ?? 0) + 1;
        }

        return $n;
    }

    public function testThePersonPathCoversEveryKindOfElementOfTheRealisticGraph(): void
    {
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        self::assertTrue($g['found']);
        $t = self::typesOf($g);
        self::assertSame(1, $t['person']);
        self::assertSame(3, $t['check']);
        self::assertSame(2, $t['paper']);
        self::assertSame(4, $t['photo']);
        self::assertSame(2, $t['doc_issue']);
        self::assertSame(1, $t['notice']);
        self::assertSame(1, $t['seek']);
        self::assertSame(2, $t['case']);
        self::assertSame(1, $t['note']);
        self::assertSame(1, $t['transmission']);
        self::assertSame(1, $t['document']);
        self::assertSame(1, $t['jnet'], 'le compte rendu JNET qui cite le dossier est une feuille du chemin');
        self::assertSame('person:' . $this->ids['person'], $g['root']);
        self::assertSame(0, $g['nodes'][$g['root']]['depth']);
    }

    public function testFromAControlThePathTellsWhereItComesFromAndWhereItLeads(): void
    {
        $g = $this->graph()->resolve('check', (string) $this->ids['check3']);
        $keys = array_keys($g['nodes']);
        foreach (['identity:' . $this->ids['identity'], 'person:' . $this->ids['person'], 'case:' . $this->ids['case'], 'note:' . $this->ids['note']] as $k) {
            self::assertContains($k, $keys);
        }
        $chains = implode("\n", $g['chains']);
        self::assertStringContainsString('CTRL-' . $this->ids['check3'] . ' → IDN-00001 → SSE-2026-0001 → ', $chains);
        // Le dossier mène au compte rendu : le chemin passe par la personne, le dossier, puis le compte rendu JNET.
        self::assertStringContainsString('SSE-2026-0001 → Compte rendu du 4 novembre', $chains);
        self::assertLessThanOrEqual(6, $g['depth']);
    }

    public function testEveryLinkSaysWhyWhenAndByWhomAndFromWhichTable(): void
    {
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        $byPair = [];
        foreach ($g['edges'] as $e) {
            $byPair[$e['from'] . '>' . $e['to']] = $e;
        }
        // Lien enregistré par Cerbère : règle, motif, acteur système, date.
        $caseEdge = $byPair['person:' . $this->ids['person'] . '>case:' . $this->ids['case']];
        self::assertSame('cerbere', $caseEdge['origin']);
        self::assertSame('cerbere.link.case_single_open', $caseEdge['rule']);
        self::assertSame('Fiche classée au seul dossier ouvert.', $caseEdge['reason']);
        self::assertSame('Cerbère-Liaison (règle cerbere.link.case_single_open)', $caseEdge['actor']);
        self::assertSame('2026-11-03 08:01:00', $caseEdge['at']);
        self::assertSame('sse_entity_links', $caseEdge['source']);
        self::assertSame('Cerbère-Liaison', $caseEdge['treatment']);
        // Lien manuel : l'auteur est une personne, le motif est celui qu'elle a écrit, il se retire.
        $manual = $byPair['note:' . $this->ids['note'] . '>person:' . $this->ids['person']];
        self::assertSame('manual', $manual['origin']);
        self::assertSame('Analyste Sept', $manual['actor']);
        self::assertSame('La fiche décrit son passage au pont nord.', $manual['reason']);
        self::assertTrue($manual['removable']);
        self::assertNotNull($manual['link_id']);
        // Lien déduit d'une colonne : la table est donnée, le motif n'a pas été enregistré (badge « Déduit »).
        $derived = $byPair['identity:' . $this->ids['identity'] . '>person:' . $this->ids['person']];
        self::assertSame('derived', $derived['origin']);
        self::assertSame('sse_identities.person_id', $derived['source']);
        self::assertFalse($derived['removable']);
        self::assertSame('Déduit', $derived['origin_label']);
    }

    public function testAnAutomaticLinkIsNeverRemovable(): void
    {
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        foreach ($g['edges'] as $e) {
            if ($e['origin'] === 'cerbere' || $e['origin'] === 'derived') {
                self::assertFalse($e['removable'], $e['key']);
            }
        }
    }

    public function testARecordedLinkAndItsColumnAreShownOnce(): void
    {
        // La fiche classée au dossier existe dans sse_case_persons ET dans sse_entity_links : un seul lien, celui qui explique.
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        $pair = 'case:' . $this->ids['case'] . '|person:' . $this->ids['person'];
        $n = 0;
        foreach ($g['edges'] as $e) {
            if ($e['key'] === $pair) {
                ++$n;
                self::assertSame('cerbere.link.case_single_open', $e['rule']);
            }
        }
        self::assertSame(1, $n);
    }

    public function testCyclesDoNotLoopAndTheReturnLinkIsKeptOnce(): void
    {
        // identité → personne existe en colonne ; on pose aussi personne → identité dans la table : un cycle de deux éléments.
        $this->pdo->prepare("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, 'person', ?, 'identity', ?, 'related', 'Retour', 'manual.link', 'user', '2026-11-12 10:00:00')")
            ->execute([(string) $this->ids['person'], (string) $this->ids['identity']]);
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        $pair = 'identity:' . $this->ids['identity'] . '|person:' . $this->ids['person'];
        $n = count(array_filter($g['edges'], static fn (array $e): bool => $e['key'] === $pair));
        self::assertSame(1, $n);
        self::assertSame(1, count(array_filter(array_keys($g['nodes']), fn (string $k): bool => $k === 'identity:' . $this->ids['identity'])));
    }

    public function testDepthIsBounded(): void
    {
        $chain = $this->personChain(10);
        $g = $this->graph()->resolve('person', (string) $chain[0]);
        // Profondeur maximale 6 : la septième personne de la chaîne est la dernière atteinte.
        self::assertSame(6, $g['depth']);
        $persons = array_filter($g['nodes'], static fn (array $n): bool => $n['type'] === 'person');
        self::assertCount(7, $persons);
        $g3 = $this->graph()->resolve('person', (string) $chain[0], 3);
        self::assertSame(3, $g3['depth']);
        self::assertCount(4, array_filter($g3['nodes'], static fn (array $n): bool => $n['type'] === 'person'));
    }

    public function testNodeCountIsBoundedAndTheCutIsAnnounced(): void
    {
        $hub = $this->personChain(1)[0];
        for ($i = 0; $i < 30; ++$i) {
            $this->pdo->exec("INSERT INTO sse_persons (tenant_id, last_name, first_name, created_at) VALUES (1, 'Satellite" . $i . "', 'X', '2026-11-02 10:00:00')");
            $p = (int) $this->pdo->lastInsertId();
            $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, 'person', '{$hub}', 'person', '{$p}', 'related', 'Satellite', 'manual.link', 'user', '2026-11-02 10:00:00')");
        }
        $g = $this->graph()->resolve('person', (string) $hub, 6, 12);
        self::assertLessThanOrEqual(12, count($g['nodes']));
        self::assertTrue($g['truncated']);
        $all = $this->graph()->resolve('person', (string) $hub);
        self::assertLessThanOrEqual(200, count($all['nodes']));
    }

    public function testTheNumberOfQueriesDoesNotGrowWithTheNumberOfElements(): void
    {
        $before = $this->graph()->resolve('person', (string) $this->ids['person'])['queries'];
        for ($i = 0; $i < 60; ++$i) {
            $this->pdo->prepare("INSERT INTO sse_file_photos (tenant_id, identity_id, path, mime, kind, source, caption, visibility, created_at) VALUES (1, ?, 'x.jpg', 'image/jpeg', 'field', 'upload', 'Cliché', 'known', '2026-11-04 12:00:00')")->execute([$this->ids['identity']]);
        }
        $after = $this->graph()->resolve('person', (string) $this->ids['person']);
        self::assertSame($before, $after['queries'], 'une requête par source et par type, jamais une par élément');
        self::assertLessThanOrEqual(40, $after['queries']);
        self::assertCount(64, array_filter($after['nodes'], static fn (array $n): bool => $n['type'] === 'photo'));
    }

    public function testTheDirectLinksAreGroupedByType(): void
    {
        $g = $this->graph()->resolve('identity', (string) $this->ids['identity']);
        self::assertSame(4, count($g['direct']['photo']));
        self::assertSame(2, count($g['direct']['paper']));
        self::assertSame(1, count($g['direct']['notice']));
        self::assertSame(1, count($g['direct']['person']));
        self::assertSame(3, count($g['direct']['check']));
        self::assertSame(1, count($g['direct']['seek']));
        self::assertSame(2, count($g['direct']['doc_issue']));
        self::assertSame(1, count($g['direct']['attribute']));
        self::assertSame(1, count($g['direct']['identity']), 'l’identité de couverture, lisible avec l’habilitation Confidentiel');
        self::assertSame(1, count($g['direct']['restricted']), 'l’identité du membre');
        self::assertSame(17, $g['counts']['direct']);
        // Du plus récent au plus ancien dans un groupe.
        $dates = array_map(static fn (array $it): string => (string) $g['edges'][$it['edge']]['at'], $g['direct']['check']);
        $sorted = $dates;
        rsort($sorted);
        self::assertSame($sorted, $dates);
    }

    public function testAMemberIdentityIsRestrictedWithoutRightsAndNothingLeaks(): void
    {
        $g = $this->graph(ArraySseLinkViewer::full())->resolve('person', (string) $this->ids['person']);
        $restricted = array_filter($g['nodes'], static fn (array $n): bool => $n['restricted']);
        self::assertCount(1, $restricted, 'l’identité du membre est restreinte (pas le titulaire, ni sa hiérarchie, ni habilité)');
        $r = array_values($restricted)[0];
        self::assertSame('Élément restreint', $r['title']);
        self::assertSame('', $r['ref']);
        self::assertSame('', $r['href']);
        self::assertSame('restricted', $r['type']);
        $json = json_encode($g, JSON_UNESCAPED_UNICODE);
        foreach (['Dupuis', 'Marc', 'IDT-000002'] as $secret) {
            self::assertStringNotContainsString($secret, (string) $json, 'aucun indice sur le membre : ' . $secret);
        }
        // Le lien qui y mène ne dit ni motif, ni auteur, ni date.
        foreach ($g['edges'] as $e) {
            if (str_starts_with($e['from'], 'r') || str_starts_with($e['to'], 'r')) {
                self::assertSame('', $e['reason']);
                self::assertSame('', $e['actor']);
                self::assertSame('', $e['at']);
                self::assertSame('hidden', $e['origin']);
            }
        }
        // Le titulaire lui-même, ou quelqu'un de sa hiérarchie, le voit.
        $holder = $this->graph(ArraySseLinkViewer::full()->with(['members' => [9]]))->resolve('person', (string) $this->ids['person']);
        self::assertSame([], array_filter($holder['nodes'], static fn (array $n): bool => $n['restricted']));
        self::assertStringContainsString('Marc Dupuis', (string) json_encode($holder, JSON_UNESCAPED_UNICODE));
    }

    public function testACoverIdentityIsCutFromThePathWithoutTheConfidentialClearance(): void
    {
        $withTruth = $this->graph(ArraySseLinkViewer::full())->resolve('identity', (string) $this->ids['identity']);
        self::assertContains('identity:' . $this->ids['cover_identity'], array_keys($withTruth['nodes']));
        $without = $this->graph(ArraySseLinkViewer::full()->with(['truth' => false]))->resolve('identity', (string) $this->ids['identity']);
        self::assertNotContains('identity:' . $this->ids['cover_identity'], array_keys($without['nodes']));
        $json = (string) json_encode($without, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Faux-Nom', $json);
        self::assertStringNotContainsString('Léon', $json);
        // Coupées, pas restreintes : aucun « Élément restreint » ne trahit la présence de l'identité de couverture ni de l'alias du membre.
        self::assertCount(0, array_filter($without['nodes'], static fn (array $n): bool => $n['restricted']));
        // Et le lien qui y menait n'existe plus.
        foreach ($without['edges'] as $e) {
            self::assertStringNotContainsString('identity:' . $this->ids['cover_identity'], $e['key']);
        }
    }

    public function testAClassifiedCaseIsRestrictedBelowItsLevelAndVisibleAbove(): void
    {
        $low = $this->graph(ArraySseLinkViewer::full()->with(['clearance' => 'encadrement']))->resolve('person', (string) $this->ids['person']);
        self::assertNotContains('case:' . $this->ids['case_secret'], array_keys($low['nodes']));
        self::assertStringNotContainsString('Dossier confidentiel', (string) json_encode($low, JSON_UNESCAPED_UNICODE));
        $high = $this->graph(ArraySseLinkViewer::full()->with(['clearance' => 'confidentiel']))->resolve('person', (string) $this->ids['person']);
        self::assertContains('case:' . $this->ids['case_secret'], array_keys($high['nodes']));
        self::assertSame('Dossier confidentiel', $high['nodes']['case:' . $this->ids['case_secret']]['title']);
    }

    public function testACaseOutsideTheAccessCodeScopeIsRestricted(): void
    {
        $g = $this->graph(ArraySseLinkViewer::full()->with(['caseScope' => [$this->ids['case']]]))->resolve('person', (string) $this->ids['person']);
        self::assertContains('case:' . $this->ids['case'], array_keys($g['nodes']));
        self::assertStringNotContainsString('SSE-2026-0002', (string) json_encode($g, JSON_UNESCAPED_UNICODE));
    }

    public function testARoleWithoutTheCasesSectionSeesRestrictedStubsAndTheWayStopsThere(): void
    {
        $viewer = ArraySseLinkViewer::full()->with(['read' => ['fichiers', 'papiers', 'photos'], 'write' => []]);
        $g = $this->graph($viewer)->resolve('person', (string) $this->ids['person']);
        foreach (['case', 'document', 'transmission', 'note'] as $type) {
            self::assertArrayNotHasKey($type, self::typesOf($g), $type . ' n’est pas lisible : il n’apparaît pas');
        }
        self::assertArrayNotHasKey('jnet', self::typesOf($g), 'le compte rendu JNET est derrière le dossier restreint : le chemin ne le traverse pas');
        $restricted = array_filter($g['nodes'], static fn (array $n): bool => $n['restricted']);
        self::assertGreaterThanOrEqual(3, count($restricted));
        $json = (string) json_encode($g, JSON_UNESCAPED_UNICODE);
        foreach (['Réseau des passeurs', 'SSE-2026-0001', 'Observation du pont', 'Identité relevée au pont nord'] as $leak) {
            self::assertStringNotContainsString($leak, $json, $leak);
        }
    }

    public function testTheRootItselfIsNotDescribedToAReaderWhoCannotOpenIt(): void
    {
        $g = $this->graph(ArraySseLinkViewer::full()->with(['read' => ['papiers']]))->resolve('person', (string) $this->ids['person']);
        self::assertFalse($g['found']);
        self::assertTrue($g['restricted']);
        self::assertSame([], $g['nodes']);
        $gone = $this->graph()->resolve('person', '999999');
        self::assertFalse($gone['found']);
        self::assertFalse($gone['restricted'], 'inexistant et illisible ne se distinguent que par le drapeau interne, jamais par du contenu');
    }

    public function testCommunitiesAreIsolated(): void
    {
        // Vue de la communauté 2 : la personne de la communauté 1 n'existe pas, même avec son numéro.
        $other = ArraySseLinkViewer::full(2);
        self::assertFalse($this->graph($other)->resolve('person', (string) $this->ids['person'])['found']);
        // Vue de la communauté 1 : le lien posé dans la communauté 2 sur la même personne n'apparaît pas.
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        self::assertNotContains('person:' . $this->ids['person_t2'], array_keys($g['nodes']));
        self::assertStringNotContainsString('Communauté 2', (string) json_encode($g, JSON_UNESCAPED_UNICODE));
        // Et dans l'autre sens.
        $g2 = $this->graph($other)->resolve('person', (string) $this->ids['person_t2']);
        self::assertTrue($g2['found']);
        self::assertSame([], array_diff(array_keys($g2['nodes']), ['person:' . $this->ids['person_t2']]));
    }

    public function testTheCommunityIsTheViewersAndNeverTheRequestsOwn(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/Links/SseLinkGraph.php')
            . (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SseLinksController.php')
            . (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/Links/SseLinksWidget.php');
        self::assertDoesNotMatchRegularExpression('/\$_(GET|POST|REQUEST)\b|->(input|query)\(\s*[\'"]tenant/', $src);
        self::assertStringContainsString('$this->viewer->tenantId()', $src);
    }

    public function testSearchOnlyProposesWhatTheReaderMayRead(): void
    {
        $full = $this->graph()->search('case', 'SSE-2026', 10);
        self::assertCount(2, $full);
        $low = $this->graph(ArraySseLinkViewer::full()->with(['clearance' => 'encadrement']))->search('case', 'SSE-2026', 10);
        self::assertCount(1, $low);
        self::assertSame('SSE-2026-0001', $low[0]['ref']);
        $none = $this->graph(ArraySseLinkViewer::full()->with(['read' => ['fichiers']]))->search('case', 'SSE-2026', 10);
        self::assertSame([], $none);
        self::assertSame([], $this->graph()->search('case', 'S', 10), 'deux caractères au moins');
        self::assertSame([], $this->graph(ArraySseLinkViewer::full(2))->search('case', 'SSE-2026', 10), 'autre communauté');
    }

    public function testWithoutTheProvenanceTableThePathIsDeducedFromTheColumnsAndSaysSo(): void
    {
        SseLinksSqlite::close();
        $this->pdo = SseLinksSqlite::open(false);
        $this->ids = SseLinksFixture::seed($this->pdo, false);
        $g = $this->graph()->resolve('person', (string) $this->ids['person']);
        self::assertTrue($g['found']);
        self::assertTrue($g['degraded']);
        self::assertSame(2, self::typesOf($g)['identity'] ?? 0, 'les liens déduits des colonnes restent lisibles (identité et couverture, lecteur habilité)');
        foreach ($g['edges'] as $e) {
            self::assertNotSame('sse_entity_links', $e['source']);
            self::assertFalse($e['removable']);
        }
        // Sans la table, l'ancien classement automatique se reconnaît à sa note, sans règle enregistrée.
        $case = $g['edges']['case:' . $this->ids['case'] . '|person:' . $this->ids['person']];
        self::assertSame('cerbere', $case['origin']);
        self::assertNull($case['rule']);
        self::assertSame('Cerbère', $case['actor']);
    }

    /**
     * Chaîne de personnes reliées de proche en proche (liens manuels).
     *
     * @return list<int>
     */
    private function personChain(int $length): array
    {
        $ids = [];
        for ($i = 0; $i < $length; ++$i) {
            $this->pdo->exec("INSERT INTO sse_persons (tenant_id, last_name, first_name, created_at) VALUES (1, 'Chaine" . $i . "', 'X', '2026-11-0" . (1 + $i % 9) . " 10:00:00')");
            $ids[] = (int) $this->pdo->lastInsertId();
        }
        for ($i = 0; $i + 1 < $length; ++$i) {
            $this->pdo->exec("INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_at) VALUES (1, 'person', '{$ids[$i]}', 'person', '" . $ids[$i + 1] . "', 'related', 'Maillon', 'manual.link', 'user', '2026-11-02 10:00:00')");
        }

        return $ids;
    }
}
