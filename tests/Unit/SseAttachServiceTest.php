<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseAttachmentRepository;
use App\Services\Sse\Attach\SseAttachService;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkWriter;
use App\Support\SseAttachCatalog as A;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseAttachFixture;
use Tests\Support\SseAttachSqlite;

/**
 * Fenêtre « Rattacher » universelle, sur une vraie base sqlite (aucun bouchon) : matrice pièce → cibles, recherche sans
 * accents, tri, filtres, droits par section, habilitation (une cible invisible est introuvable), cloisonnement entre
 * communautés, alignement de la confidentialité (hausse seulement, plafond d'habilitation, jamais de baisse implicite,
 * journal), colonnes historiques, modifier, détacher, déplacer, lot, annulation rapide, mode dégradé.
 */
final class SseAttachServiceTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseAttachSqlite::open();
        $this->ids = SseAttachFixture::seed($this->pdo);
    }

    protected function tearDown(): void
    {
        SseAttachSqlite::close();
    }

    private function operator(): SseAttachService
    {
        return new SseAttachService(SseAttachFixture::operator());
    }

    private function manager(): SseAttachService
    {
        return new SseAttachService(SseAttachFixture::manager());
    }

    private function ref(string $type, string $key): string
    {
        return $type . ':' . $this->ids[$key];
    }

    private function scalar(string $sql): mixed
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    private function audit(string $action): array
    {
        $st = $this->pdo->prepare('SELECT * FROM sse_audit_log WHERE action = ? ORDER BY id');
        $st->execute([$action]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ Catalogue et matrice

    public function testMatrixOffersSensibleTargetsPerPieceAndEveryScopeIsALinkType(): void
    {
        foreach (array_keys(A::SCOPES) as $scope) {
            self::assertTrue(\App\Support\SseLinkCatalog::hasType($scope), $scope);
            self::assertNotNull((new SseLinkGraph(SseAttachFixture::manager()))->adapters()->get($scope), 'adaptateur ' . $scope);
            self::assertNotSame([], A::naturesFor($scope));
        }
        foreach (['case', 'interest', 'person', 'identity', 'paper', 'document', 'lead', 'mesh', 'site', 'organization', 'vehicle', 'equipment', 'campaign', 'notice', 'note', 'transmission'] as $required) {
            self::assertArrayHasKey($required, A::SCOPES, $required);
        }
        foreach (A::MATRIX as $source => $targets) {
            self::assertNotContains($source === 'campaign' ? 'x' : '', $targets);
            foreach ($targets as $t) {
                self::assertArrayHasKey($t, A::SCOPES, $source . ' → ' . $t);
                self::assertNotSame($source, $t, 'une pièce ne se rattache pas à son propre type : ' . $source);
            }
        }
        self::assertSame('case', A::targetsFor('note')[0]);
        self::assertSame('identity', A::targetsFor('paper')[0]);
        self::assertTrue(A::allows('note', 'lead'));
        self::assertFalse(A::allows('paper', 'campaign'));
        self::assertFalse(A::isSource('campaign'), 'une campagne est une cible, pas une pièce');
        // Lot : périmètres communs seulement.
        self::assertSame(['case', 'interest', 'lead', 'notice'], array_values(array_intersect(A::commonTargets(['note', 'paper']), ['case', 'interest', 'lead', 'notice', 'campaign', 'mesh'])));
        self::assertSame('filed_in', A::naturesFor('case')[0]);
        self::assertSame('concerns', A::naturesFor('person')[0]);
        foreach (['filed_in', 'concerns', 'evidence_of', 'mentions', 'created_from', 'verify_with'] as $nature) {
            self::assertArrayHasKey($nature, A::NATURES);
            self::assertArrayHasKey($nature, \App\Support\SseLinkCatalog::RELATIONS);
        }
    }

    public function testContextListsPiecesScopesTagsAndRights(): void
    {
        $ctx = $this->operator()->context([$this->ref('note', 'note_free')]);
        self::assertTrue($ctx['found']);
        self::assertTrue($ctx['ready']);
        self::assertTrue($ctx['can_attach']);
        self::assertSame('FR-2026-0012', $ctx['sources'][0]['ref']);
        self::assertSame('interne', $ctx['sources'][0]['level']);
        self::assertSame(A::targetsFor('note'), $ctx['scopes']);
        self::assertCount(6, $ctx['tags']);
        self::assertSame(['case', 'lead'], $ctx['can_create']);
        self::assertSame('encadrement', $ctx['viewer_level']);
        self::assertFalse($ctx['always_align']);
        // Le lecteur lit, ne rattache pas, et la fenêtre le dit.
        $reader = (new SseAttachService(SseAttachFixture::reader()))->context([$this->ref('note', 'note_free')]);
        self::assertTrue($reader['found']);
        self::assertFalse($reader['can_attach']);
        self::assertSame(SseAttachService::DENY_RIGHT, $reader['deny']);
        self::assertSame([], $reader['can_create']);
    }

    // ------------------------------------------------------------------ Recherche, tri, filtres

    public function testSearchIgnoresAccentsAndCaseAndCountsEveryScope(): void
    {
        $svc = $this->operator();
        $src = $svc->directory()->sources([$this->ref('note', 'note_free')]);
        $res = $svc->directory()->search($src, ['q' => 'RESEAU']);
        self::assertSame('case', $res['scope']);
        $titles = array_column($res['items'], 'title');
        self::assertContains('Réseau des passeurs', $titles);
        self::assertContains('Réseau des passeurs du col nord', $titles);
        self::assertNotContains('Réseau de guetteurs du port', $titles, 'dossier « Secret » : au-dessus de l’habilitation de l’opérateur');
        foreach ($titles as $t) {
            self::assertStringContainsString('seau', $t);
        }
        $counts = array_column($res['scopes'], 'count', 'key');
        self::assertSame(count($res['items']), $counts['case']);
        self::assertSame(0, $counts['campaign']);
        // Par alias, par référence, et par étiquette posée sur la cible.
        self::assertSame(1, $svc->directory()->search($src, ['scope' => 'person', 'q' => 'chouette'])['total']);
        self::assertSame('SSE-2026-0007', $svc->directory()->search($src, ['q' => 'sse-2026-0007'])['items'][0]['ref']);
        self::assertSame('SSE-2026-0001', $svc->directory()->search($src, ['q' => 'piece a conviction'])['items'][0]['ref']);
        self::assertSame(0, $svc->directory()->search($src, ['q' => 'zzzz introuvable'])['total']);
    }

    public function testSortFiltersAndPagination(): void
    {
        $svc = $this->manager();
        $src = $svc->directory()->sources([$this->ref('note', 'note_free')]);
        $dir = $svc->directory();
        $all = $dir->search($src, ['scope' => 'case']);
        self::assertSame(40, $all['total'], '40 dossiers de la communauté (pas celui de l’autre)');
        self::assertCount(A::PAGE_SIZE, $all['items']);
        self::assertTrue($all['more']);
        $page2 = $dir->search($src, ['scope' => 'case', 'page' => 2]);
        self::assertSame([], array_intersect(array_column($all['items'], 'key'), array_column($page2['items'], 'key')));
        self::assertFalse($dir->search($src, ['scope' => 'case', 'page' => 2])['more']);

        $alpha = array_column($dir->search($src, ['scope' => 'case', 'sort' => 'alpha'])['items'], 'title');
        $sorted = $alpha;
        usort($sorted, static fn (string $a, string $b): int => A::fold($a) <=> A::fold($b));
        self::assertSame($sorted, $alpha);
        self::assertSame('tres_restreint', $dir->search($src, ['scope' => 'case', 'sort' => 'class'])['items'][0]['level']);
        self::assertGreaterThan(0, $dir->search($src, ['scope' => 'case', 'sort' => 'linked'])['items'][0]['count']);

        foreach ($dir->search($src, ['scope' => 'case', 'status' => 'closed'])['items'] as $it) {
            self::assertSame('closed', $it['tone']);
        }
        foreach ($dir->search($src, ['scope' => 'case', 'class' => 'confidentiel'])['items'] as $it) {
            self::assertSame('confidentiel', $it['level']);
        }
        $tagged = $dir->search($src, ['scope' => 'case', 'tag' => $this->ids['tag1']]);
        self::assertSame(['SSE-2026-0001'], array_column($tagged['items'], 'ref'));
        $mine = $dir->search($src, ['scope' => 'case', 'mine' => true]);
        self::assertGreaterThan(0, $mine['total']);
        self::assertLessThan(40, $mine['total']);
        foreach ($mine['items'] as $it) {
            self::assertSame('Chef de bureau Morel', $it['owner']);
        }
    }

    public function testAlreadyAttachedTargetsAreFlaggedFromLinksAndLegacyColumns(): void
    {
        $svc = $this->operator();
        // La fiche du graphe est classée au dossier 1 par sa colonne case_id ET par un rattachement.
        $src = $svc->directory()->sources([$this->ref('note', 'note')]);
        $hit = $svc->directory()->search($src, ['q' => 'SSE-2026-0001'])['items'][0];
        self::assertTrue($hit['linked_all']);
        self::assertSame(1, $hit['linked']);
        self::assertGreaterThanOrEqual(4, $hit['count']);
        $other = $svc->directory()->search($src, ['q' => 'SSE-2026-0003'])['items'][0];
        self::assertFalse($other['linked_all']);
    }

    public function testCerbereSuggestsTargetsOneHopAwayAndSamePlace(): void
    {
        $svc = $this->operator();
        $src = $svc->directory()->sources([$this->ref('note', 'note_free')]);
        $sug = $svc->directory()->suggestions($src);
        self::assertNotSame([], $sug);
        self::assertLessThanOrEqual(5, count($sug));
        $byRef = array_column($sug, 'why', 'ref');
        self::assertArrayHasKey('SSE-2026-0001', $byRef, 'le dossier de la personne citée, et de la fiche relevée au même endroit');
        foreach ($sug as $s) {
            self::assertNotSame('', $s['why']);
            self::assertNotSame('SSE-2026-0002', $s['ref'], 'jamais une cible au-dessus de l’habilitation');
        }
        // Les cibles récentes ne reviennent que si le lecteur les voit encore.
        $recents = $svc->directory()->byKeys($src, [$this->ref('case', 'case'), $this->ref('case', 'case_secret'), $this->ref('case', 'case_t2'), 'case:999999']);
        self::assertSame(['SSE-2026-0001'], array_column($recents, 'ref'));
    }

    // ------------------------------------------------------------------ Habilitation, cloisonnement

    public function testTargetsAboveClearanceAreNeitherListedNorGuessable(): void
    {
        $svc = $this->operator();
        $src = $svc->directory()->sources([$this->ref('note', 'note_free')]);
        $dir = $svc->directory();
        // L'opérateur est habilité C : ni les dossiers S ou TS, ni la fiche S.
        foreach ($dir->search($src, ['scope' => 'case', 'page' => 1])['items'] as $it) {
            self::assertContains($it['level'], ['interne', 'encadrement']);
        }
        self::assertSame(0, $dir->search($src, ['q' => 'Dossier confidentiel'])['total']);
        self::assertLessThan(40, $dir->search($src, ['scope' => 'case'])['total']);
        // Détail : même réponse pour « au-dessus de l'habilitation », « autre communauté » et « n'existe pas ».
        self::assertNull($dir->detail($src, 'case', (string) $this->ids['case_secret']));
        self::assertNull($dir->detail($src, 'case', (string) $this->ids['case_t2']));
        self::assertNull($dir->detail($src, 'case', '999999'));
        self::assertNotNull($dir->detail($src, 'case', (string) $this->ids['case']));
        // Rattacher à une cible invisible : « introuvable », rien n'est écrit.
        foreach ([$this->ref('case', 'case_secret'), $this->ref('case', 'case_t2'), 'case:999999'] as $target) {
            $res = $svc->attach([$this->ref('note', 'note_free')], [$target], ['reason' => 'Motif suffisant']);
            self::assertFalse($res['ok']);
            self::assertSame(SseAttachService::NOT_FOUND, $res['error']);
        }
        // Une pièce invisible (fiche « Secret ») : même réponse.
        $res = $svc->attach([$this->ref('note', 'note_secret')], [$this->ref('case', 'case')], ['reason' => 'Motif suffisant']);
        self::assertSame(SseAttachService::NOT_FOUND, $res['error']);
        self::assertFalse($svc->context([$this->ref('note', 'note_secret')])['found']);
        self::assertSame(5, (int) $this->scalar('SELECT COUNT(*) FROM sse_link_attachments'));
        // Identités de membres et de couverture : jamais proposées sans le droit.
        $titles = array_column($dir->search($src, ['scope' => 'identity', 'q' => ''])['items'], 'title');
        self::assertNotContains('Marc Dupuis', $titles);
        self::assertNotContains('Léon Faux-Nom', $titles);
        // Fiche personne classifiée au-dessus de l'habilitation : absente.
        $classified = (int) $this->scalar("SELECT COUNT(*) FROM sse_persons WHERE tenant_id = 1 AND classification = 'confidentiel'");
        self::assertGreaterThan(0, $classified);
        self::assertSame(30 - $classified, $dir->search($src, ['scope' => 'person'])['total']);
    }

    public function testRightsBySectionGuestAndMatrixAreEnforced(): void
    {
        $note = $this->ref('note', 'note_free');
        $case = $this->ref('case', 'case3');
        $reader = new SseAttachService(SseAttachFixture::reader());
        self::assertSame(SseAttachService::DENY_RIGHT, $reader->attach([$note], [$case], ['reason' => 'Motif suffisant'])['error']);
        $guest = new SseAttachService(SseAttachFixture::operator()->with(['guest' => true]));
        self::assertSame(SseAttachService::DENY_GUEST, $guest->attach([$note], [$case], ['reason' => 'Motif suffisant'])['error']);
        // Écrit dans « terrain » seulement : rattache une fiche à une piste, mais ne la classe pas dans un dossier (la colonne
        // historique du dossier serait écrite : il faut pouvoir écrire dans « Dossiers d'enquête ») et ne rattache pas un papier.
        $field = new SseAttachService(SseAttachFixture::operator()->with(['write' => ['terrain']]));
        self::assertStringContainsString('section de la cible', (string) $field->attach([$note], [$case], ['reason' => 'Motif suffisant'])['error']);
        self::assertNull($this->pdo->query('SELECT case_id FROM sse_field_notes WHERE id = ' . $this->ids['note_free'])->fetchColumn() ?: null);
        self::assertTrue($field->attach([$note], [$this->ref('lead', 'lead2')], ['reason' => 'Motif suffisant'])['ok']);
        self::assertSame(SseAttachService::DENY_RIGHT, $field->attach([$this->ref('paper', 'paper1')], [$case], ['reason' => 'Motif suffisant'])['error']);
        self::assertSame([], $field->context([$note])['can_create']);
        // Matrice : un papier ne se rattache pas à une campagne.
        $res = $this->operator()->attach([$this->ref('paper', 'paper1')], [$this->ref('campaign', 'campaign1')], ['reason' => 'Motif suffisant']);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('ne se rattache pas', (string) $res['error']);
        // Motif obligatoire.
        self::assertSame(SseAttachService::REASON_REQUIRED, $this->operator()->attach([$note], [$this->ref('case', 'case5')], ['reason' => 'non'])['error']);
    }

    // ------------------------------------------------------------------ Rattacher : lien enrichi + colonne historique

    public function testAttachCreatesOneEnrichedLinkAndWritesTheLegacyColumn(): void
    {
        $svc = $this->operator();
        $res = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3'), $this->ref('lead', 'lead2')], [
            'reason' => 'Relevé au même endroit', 'label' => 'Mouvement de nuit', 'note' => 'À recouper', 'tags' => ['#' . $this->ids['tag2'], 'Pont nord|blue'], 'nature' => 'evidence_of',
        ]);
        self::assertTrue($res['ok'], (string) $res['error']);
        self::assertSame(2, $res['count']);
        self::assertCount(2, $res['created']);
        // Un seul système : le rattachement EST un lien de sse_entity_links (le widget « Liaisons » le montre).
        $link = $this->pdo->query("SELECT * FROM sse_entity_links WHERE source_type = 'note' AND source_id = '{$this->ids['note_free']}' AND target_type = 'case'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('evidence_of', $link['relation']);
        self::assertSame('Relevé au même endroit', $link['reason']);
        self::assertSame('manual.link', $link['rule_code']);
        $att = $this->pdo->query('SELECT * FROM sse_link_attachments WHERE id = ' . (int) $res['created'][0])->fetch(PDO::FETCH_ASSOC);
        self::assertSame((int) $link['id'], (int) $att['link_id']);
        self::assertSame('Mouvement de nuit', $att['label']);
        self::assertSame('À recouper', $att['note']);
        self::assertSame('active', $att['status']);
        self::assertSame(22, (int) $att['created_by_user_id']);
        self::assertCount(2, SseAttachmentRepository::unpackTags((string) $att['tags']));
        // Étiquette créée à la volée dans le catalogue de la communauté.
        self::assertSame('blue', $this->scalar("SELECT color FROM sse_attach_tags WHERE tenant_id = 1 AND slug = 'pont-nord'"));
        // Colonne historique : listes, compteurs, PDF et exports existants restent justes.
        self::assertSame((int) $this->ids['case3'], (int) $this->scalar('SELECT case_id FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        // La piste n'a pas de colonne : lien seul.
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM sse_entity_links WHERE target_type = 'lead' AND target_id = '{$this->ids['lead2']}'"));
        self::assertCount(2, $this->audit('attach.add'));
        // Le chemin de liaison de la fiche passe par le dossier : pas de doublon entre « rattachements » et « liaisons ».
        $g = (new SseLinkGraph(SseAttachFixture::operator()))->resolve('note', (string) $this->ids['note_free'], 1);
        self::assertArrayHasKey('case:' . $this->ids['case3'], $g['nodes']);
        // Déjà rattaché : refusé, pas de doublon.
        $again = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3')], ['reason' => 'Relevé au même endroit']);
        self::assertFalse($again['ok']);
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM sse_entity_links WHERE source_type = 'note' AND source_id = '{$this->ids['note_free']}' AND target_type = 'case'"));
        // Un second dossier est un lien de plus : la colonne garde le premier.
        self::assertTrue($svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case5')], ['reason' => 'Autre affaire liée'])['ok']);
        self::assertSame((int) $this->ids['case3'], (int) $this->scalar('SELECT case_id FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
    }

    public function testLegacyTablesFollowForPersonsDocumentsTransmissionsAndInterestCases(): void
    {
        $svc = $this->operator();
        self::assertTrue($svc->attach([$this->ref('person', 'person5')], [$this->ref('case', 'case3')], ['reason' => 'Vu avec le guide'])['ok']);
        self::assertSame('Vu avec le guide', $this->scalar("SELECT note FROM sse_case_persons WHERE case_id = {$this->ids['case3']} AND person_id = {$this->ids['person5']}"));
        self::assertTrue($svc->attach([$this->ref('document', 'document2')], [$this->ref('case', 'case5')], ['reason' => 'Pièce du dossier'])['ok']);
        // Le document portait déjà un dossier : la colonne ne bouge pas, le lien est posé.
        self::assertSame((int) $this->ids['case3'], (int) $this->scalar('SELECT case_id FROM sse_documents WHERE id = ' . $this->ids['document2']));
        $svc->attach([$this->ref('transmission', 'transmission')], [$this->ref('interest', 'interest1')], ['reason' => 'Même signalement']);
        self::assertSame((int) $this->ids['interest1'], (int) $this->scalar('SELECT interest_case_id FROM sse_intel_events WHERE id = ' . $this->ids['transmission']));
        // Éléments liés du dossier d'intérêt : la table historique suit.
        self::assertSame('transmission', $this->scalar("SELECT link_type FROM sse_interest_case_links WHERE interest_case_id = {$this->ids['interest1']} AND target_id = '{$this->ids['transmission']}'"));
        // Un lien Cerbère existant est enrichi, pas doublé.
        $before = (int) $this->scalar('SELECT COUNT(*) FROM sse_entity_links');
        $res = $svc->attach([$this->ref('person', 'person')], [$this->ref('case', 'case')], ['reason' => 'Confirmé par l’analyste', 'label' => 'La Chouette']);
        self::assertTrue($res['ok'], (string) $res['error']);
        self::assertSame($before, (int) $this->scalar('SELECT COUNT(*) FROM sse_entity_links'));
        self::assertSame('cerbere.link.case_single_open', $this->scalar('SELECT rule_code FROM sse_entity_links WHERE id = ' . $this->ids['link_case']));
    }

    public function testBatchAttachesEveryPieceToEveryTarget(): void
    {
        $svc = $this->operator();
        $pieces = [$this->ref('person', 'person3'), $this->ref('person', 'person4'), $this->ref('person', 'person5')];
        $preview = $svc->preview($pieces, [$this->ref('case', 'case3'), $this->ref('lead', 'lead2')], ['reason' => 'Même réseau']);
        self::assertTrue($preview['ok']);
        self::assertCount(6, $preview['rows']);
        self::assertSame(6, $preview['new']);
        self::assertSame(5, (int) $this->scalar('SELECT COUNT(*) FROM sse_link_attachments'), 'l’aperçu n’écrit rien');
        $res = $svc->attach($pieces, [$this->ref('case', 'case3'), $this->ref('lead', 'lead2')], ['reason' => 'Même réseau', 'label' => 'ignoré en lot']);
        self::assertSame(6, $res['count']);
        self::assertSame(3, (int) $this->scalar('SELECT COUNT(*) FROM sse_case_persons WHERE case_id = ' . $this->ids['case3']));
        self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM sse_link_attachments WHERE label = 'ignoré en lot'"), 'le libellé ne vaut que pour une pièce');
        $many = array_map(static fn (int $i): string => 'person:' . $i, range(1, A::BATCH_SOURCES + 1));
        self::assertFalse($svc->attach($many, [$this->ref('case', 'case3')], ['reason' => 'Même réseau'])['ok']);
    }

    // ------------------------------------------------------------------ Alignement de la confidentialité

    public function testAlignmentOnlyRaisesAndIsLogged(): void
    {
        $svc = $this->operator();
        $note = $this->ref('note', 'note_free');
        // Sans la case : rien ne change, la fenêtre l'explique.
        $preview = $svc->preview([$note], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant']);
        self::assertSame('target_higher', $preview['rows'][0]['effect']);
        self::assertSame([], $preview['effects']);
        // Avec la case : la pièce prend le niveau de la cible (CUI → C), et l'aperçu le dit AVANT.
        $preview = $svc->preview([$note], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertSame('raise', $preview['rows'][0]['effect']);
        self::assertSame(['CUI', 'C'], [$preview['effects'][0]['from'], $preview['effects'][0]['to']]);
        self::assertNotSame('', $preview['effects'][0]['lose']);
        self::assertSame('interne', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']), 'l’aperçu ne change rien');
        $res = $svc->attach([$note], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertTrue($res['ok']);
        self::assertSame([['source' => 'FR-2026-0012', 'from' => 'CUI', 'to' => 'C']], $res['aligned']);
        self::assertSame('encadrement', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        $log = $this->audit('attach.align');
        self::assertCount(1, $log);
        self::assertSame('{"classification":"interne"}', $log[0]['before_json']);
        self::assertSame('{"classification":"encadrement"}', $log[0]['after_json']);
        self::assertSame('sse_field_notes', $log[0]['object_type']);
        self::assertSame(22, (int) $log[0]['actor_user_id']);
        $att = $this->pdo->query('SELECT * FROM sse_link_attachments WHERE id = ' . (int) $res['created'][0])->fetch(PDO::FETCH_ASSOC);
        self::assertSame([1, 'interne', 'encadrement'], [(int) $att['align_requested'], $att['align_from'], $att['align_to']]);
    }

    public function testALowerTargetNeverLowersThePieceAndTheWindowWarnsBeforehand(): void
    {
        $svc = $this->manager();
        $secret = $this->ref('note', 'note_secret');
        $preview = $svc->preview([$secret], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertSame('target_lower', $preview['rows'][0]['effect']);
        self::assertStringContainsString('moins protégée que la pièce', $preview['rows'][0]['message']);
        self::assertStringContainsString('Élément restreint', $preview['rows'][0]['message']);
        self::assertSame([], $preview['effects']);
        self::assertTrue($svc->attach([$secret], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant', 'align' => true])['ok']);
        self::assertSame('confidentiel', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_secret']));
        self::assertSame([], $this->audit('attach.align'));
        // Dans la cible, la pièce reste masquée pour un lecteur sans l'habilitation : ni nom, ni référence, ni motif.
        $seen = $this->operator()->attached('case', (string) $this->ids['case4']);
        self::assertCount(1, $seen['items']);
        self::assertSame(['restricted' => true, 'id' => 0], $seen['items'][0]);
        self::assertFalse($this->manager()->attached('case', (string) $this->ids['case4'])['items'][0]['restricted']);
        // Détacher n'abaisse rien non plus.
        $id = (int) $this->scalar("SELECT id FROM sse_link_attachments WHERE source_id = '{$this->ids['note_secret']}' AND target_id = '{$this->ids['case4']}'");
        self::assertTrue($svc->detach($id, 'Erreur de dossier')['ok']);
        self::assertSame('confidentiel', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_secret']));
    }

    public function testAlignmentNeverExceedsTheOperatorsClearanceAndSkipsUnclassifiedTypes(): void
    {
        // Une cible plus haute que l'habilitation n'existe pas pour l'opérateur : l'alignement ne peut donc pas y mener.
        $res = $this->operator()->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case6')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertSame('confidentiel', $this->scalar('SELECT classification FROM sse_cases WHERE id = ' . $this->ids['case6']));
        self::assertSame(SseAttachService::NOT_FOUND, $res['error']);
        self::assertSame('interne', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        // Un contrôle ne porte pas de classification : rien à aligner, et la fenêtre le dit.
        $preview = $this->operator()->preview([$this->ref('check', 'check1')], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertSame('unclassifiable', $preview['rows'][0]['effect']);
        self::assertSame([], $preview['effects']);
        // Plusieurs cibles : la pièce prend le plus haut niveau, une seule entrée de journal.
        $res = $this->manager()->attach([$this->ref('note', 'note_low')], [$this->ref('case', 'case4'), $this->ref('case', 'case6')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertTrue($res['ok']);
        self::assertSame('confidentiel', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_low']));
        self::assertCount(1, $this->audit('attach.align'));
    }

    public function testCommunitySettingAlwaysAlignsUpwardAndIsOffByDefault(): void
    {
        $svc = $this->operator();
        self::assertFalse($svc->alwaysAlign());
        $this->pdo->exec("INSERT INTO sse_portal_settings (tenant_id, setting_key, setting_value) VALUES (1, '" . A::SETTING_ALWAYS_ALIGN . "', '1')");
        self::assertTrue($svc->alwaysAlign());
        self::assertTrue($svc->context([$this->ref('note', 'note_free')])['always_align']);
        $res = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant']);
        self::assertSame('encadrement', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        self::assertCount(1, $res['aligned']);
        // Le réglage d'une autre communauté ne vaut pas ici.
        $this->pdo->exec("UPDATE sse_portal_settings SET tenant_id = 2 WHERE setting_key = '" . A::SETTING_ALWAYS_ALIGN . "'");
        self::assertFalse($svc->alwaysAlign());
    }

    public function testReclassifiedTargetProposesAlignmentAndNeverAppliesItSilently(): void
    {
        $svc = $this->manager();
        // La fiche du graphe a demandé l'alignement sur le dossier 1 (CUI) : rien à proposer tant que les niveaux sont égaux.
        self::assertSame([], $svc->proposals('case', (string) $this->ids['case']));
        // Le dossier est reclassifié plus haut, après coup.
        $this->pdo->exec("UPDATE sse_cases SET classification = 'confidentiel' WHERE id = " . $this->ids['case']);
        $props = $svc->proposals('case', (string) $this->ids['case']);
        self::assertCount(1, $props);
        self::assertSame(['FR-2026-0004', 'CUI', 'S'], [$props[0]['source'], $props[0]['from'], $props[0]['to']]);
        self::assertSame('interne', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note']), 'jamais silencieux');
        self::assertCount(1, $svc->attached('case', (string) $this->ids['case'])['proposals']);
        // L'opérateur (habilité C) ne voit plus ce dossier : aucune proposition ne lui parvient.
        self::assertSame([], $this->operator()->proposals('case', (string) $this->ids['case']));
        self::assertSame(SseAttachService::NOT_FOUND, $this->operator()->applyAlign($this->ids['att_note'])['error']);
        // Une pièce qui n'avait pas demandé l'alignement n'est pas proposée.
        self::assertSame(SseAttachService::NOT_FOUND, $svc->applyAlign($this->ids['att_doc'])['error']);
        self::assertTrue($svc->applyAlign($this->ids['att_note'])['ok']);
        self::assertSame('confidentiel', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note']));
        self::assertCount(1, $this->audit('attach.align'));
        self::assertSame([], $svc->proposals('case', (string) $this->ids['case']));
    }

    // ------------------------------------------------------------------ Modifier, détacher, déplacer, annuler

    public function testUpdateChangesTheAttachmentNotThePiece(): void
    {
        $svc = $this->operator();
        $res = $svc->update($this->ids['att_doc'], ['label' => 'CR du 4 (version signée)', 'reason' => 'Version signée reçue', 'tags' => ['Vérifié', 'Nouvelle étiquette'], 'note' => 'Voir annexe']);
        self::assertTrue($res['ok'], (string) $res['error']);
        $att = $this->pdo->query('SELECT * FROM sse_link_attachments WHERE id = ' . $this->ids['att_doc'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('CR du 4 (version signée)', $att['label']);
        self::assertSame('Version signée reçue', $att['reason']);
        self::assertCount(2, SseAttachmentRepository::unpackTags((string) $att['tags']));
        self::assertSame('Compte rendu du 4 novembre', $this->scalar('SELECT title FROM sse_documents WHERE id = ' . $this->ids['document']), 'la pièce garde son titre');
        self::assertCount(1, $this->audit('attach.update'));
        self::assertSame(SseAttachService::REASON_REQUIRED, $svc->update($this->ids['att_doc'], ['reason' => 'x'])['error']);
        // Sans droit d'écrire et sans en être l'auteur : refus. Rattachement d'une pièce invisible, inexistant ou d'ailleurs : introuvable.
        self::assertSame(SseAttachService::DENY_RIGHT, (new SseAttachService(SseAttachFixture::reader()))->update($this->ids['att_doc'], ['label' => 'x'])['error']);
        self::assertSame(SseAttachService::NOT_FOUND, $svc->update($this->ids['att_secret'], ['label' => 'x'])['error']);
        self::assertSame(SseAttachService::NOT_FOUND, $svc->update(999999, ['label' => 'x'])['error']);
        self::assertSame(SseAttachService::NOT_FOUND, (new SseAttachService(SseAttachFixture::manager()->with(['tenant' => 2])))->update($this->ids['att_doc'], ['label' => 'x'])['error']);
    }

    public function testDetachKeepsHistoryRemovesTheLinkAndClearsTheLegacyColumn(): void
    {
        $svc = $this->operator();
        $made = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3')], ['reason' => 'Motif suffisant']);
        $id = (int) $made['created'][0];
        self::assertSame('Le motif du détachement est obligatoire (5 caractères au moins).', $svc->detach($id, '')['error']);
        self::assertTrue($svc->detach($id, 'Mauvais dossier')['ok']);
        $att = $this->pdo->query('SELECT * FROM sse_link_attachments WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['removed', 'Mauvais dossier', null], [$att['status'], $att['removed_reason'], $att['link_id']]);
        self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM sse_entity_links WHERE source_type = 'note' AND source_id = '{$this->ids['note_free']}' AND target_type = 'case'"));
        self::assertNull($this->pdo->query('SELECT case_id FROM sse_field_notes WHERE id = ' . $this->ids['note_free'])->fetchColumn() ?: null);
        self::assertCount(1, $this->audit('attach.remove'));
        self::assertSame(SseAttachService::NOT_FOUND, $svc->detach($id, 'Encore une fois')['error']);
        // Un lien posé par Cerbère garde sa trace : seul l'enrichissement est retiré.
        $enriched = $svc->attach([$this->ref('person', 'person')], [$this->ref('case', 'case')], ['reason' => 'Confirmé par l’analyste']);
        self::assertTrue($svc->detach((int) $enriched['created'][0], 'Enrichissement inutile')['ok']);
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM sse_entity_links WHERE id = ' . $this->ids['link_case']));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM sse_case_persons WHERE case_id = {$this->ids['case']} AND person_id = {$this->ids['person']}"));
    }

    public function testRemovingTheLinkFromTheLiaisonsWidgetClosesTheAttachment(): void
    {
        $viewer = SseAttachFixture::operator();
        $made = (new SseAttachService($viewer))->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3')], ['reason' => 'Motif suffisant']);
        $link = (int) $this->scalar('SELECT link_id FROM sse_link_attachments WHERE id = ' . (int) $made['created'][0]);
        self::assertTrue((new SseLinkWriter($viewer, new SseLinkGraph($viewer)))->remove($link)['ok']);
        self::assertSame('removed', $this->scalar('SELECT status FROM sse_link_attachments WHERE id = ' . (int) $made['created'][0]));
        self::assertSame(0, (int) $this->scalar('SELECT COALESCE(case_id, 0) FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
    }

    public function testMoveCarriesLabelAndTagsToTheNewTargetAndTheColumnFollows(): void
    {
        $svc = $this->operator();
        $made = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3')], ['reason' => 'Motif suffisant', 'label' => 'Mouvement', 'tags' => ['#' . $this->ids['tag4']]]);
        $res = $svc->move((int) $made['created'][0], $this->ref('case', 'case5'), 'Relève d’une autre affaire');
        self::assertTrue($res['ok'], (string) $res['error']);
        $new = $this->pdo->query('SELECT * FROM sse_link_attachments WHERE id = ' . (int) $res['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['Mouvement', (string) $this->ids['case5'], 'active'], [$new['label'], $new['target_id'], $new['status']]);
        self::assertSame([$this->ids['tag4']], SseAttachmentRepository::unpackTags((string) $new['tags']));
        self::assertSame('removed', $this->scalar('SELECT status FROM sse_link_attachments WHERE id = ' . (int) $made['created'][0]));
        self::assertSame((int) $this->ids['case5'], (int) $this->scalar('SELECT case_id FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        self::assertCount(1, $this->audit('attach.move'));
        self::assertSame(SseAttachService::NOT_FOUND, $svc->move((int) $res['id'], $this->ref('case', 'case_secret'), 'Vers un dossier invisible')['error']);
    }

    public function testQuickUndoIsForTheAuthorOnlyAndRestoresTheAlignedLevel(): void
    {
        $svc = $this->operator();
        $made = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case4')], ['reason' => 'Motif suffisant', 'align' => true]);
        self::assertSame('encadrement', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        self::assertFalse($this->manager()->undo($made['created'])['ok'], 'seul l’auteur annule');
        $undo = $svc->undo($made['created']);
        self::assertTrue($undo['ok']);
        self::assertSame('interne', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        self::assertCount(1, $this->audit('attach.undo'));
        self::assertCount(1, $this->audit('attach.align_undo'));
        // Passé le délai : plus d'annulation rapide.
        $made = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3')], ['reason' => 'Motif suffisant']);
        $this->pdo->exec("UPDATE sse_link_attachments SET created_at = '2020-01-01 00:00:00' WHERE id = " . (int) $made['created'][0]);
        self::assertFalse($svc->undo($made['created'])['ok']);
    }

    public function testCreateTargetOnTheFlyRespectsRightsAndClearance(): void
    {
        $svc = $this->operator();
        $res = $svc->createTarget('lead', ['title' => 'Guetteur du pont nord', 'description' => 'À vérifier', 'classification' => 'encadrement']);
        self::assertTrue($res['ok'], (string) $res['error']);
        $lead = $this->pdo->query('SELECT * FROM sse_leads WHERE id = ' . (int) substr((string) $res['key'], 5))->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['PST-' . gmdate('Y') . '-0011', 'ouverte', 22, 1], [$lead['reference_code'], $lead['status'], (int) $lead['owner_user_id'], (int) $lead['tenant_id']]);
        self::assertTrue($svc->attach([$this->ref('note', 'note_free')], [(string) $res['key']], ['reason' => 'Motif suffisant'])['ok']);
        self::assertSame('Vous ne pouvez pas créer au-dessus de votre propre habilitation.', $svc->createTarget('lead', ['title' => 'Trop haut', 'classification' => 'tres_restreint'])['error']);
        self::assertFalse($svc->createTarget('lead', ['title' => 'x'])['ok']);
        self::assertSame(SseAttachService::NOT_FOUND, $svc->createTarget('person', ['title' => 'Pas ici'])['error']);
        self::assertFalse((new SseAttachService(SseAttachFixture::reader()))->createTarget('lead', ['title' => 'Sans droit'])['ok']);
        $case = $svc->createTarget('case', ['title' => 'Affaire du pont nord', 'classification' => 'interne']);
        self::assertTrue($case['ok'], (string) $case['error']);
        self::assertSame('Affaire du pont nord', $this->scalar('SELECT title FROM sse_cases WHERE id = ' . (int) substr((string) $case['key'], 5)));
    }

    // ------------------------------------------------------------------ Section « Éléments rattachés »

    public function testAttachedSectionShowsLabelNatureReasonAuthorAndMasksWhatTheReaderCannotSee(): void
    {
        $as = $this->operator()->attached('case', (string) $this->ids['case']);
        self::assertSame('SSE-2026-0001', $as['target']['ref']);
        self::assertCount(5, $as['items']);
        $visible = array_values(array_filter($as['items'], static fn (array $i): bool => !$i['restricted']));
        self::assertCount(4, $visible, 'la fiche « Secret » est un élément restreint pour l’opérateur');
        $note = array_values(array_filter($visible, static fn (array $i): bool => $i['ref'] === 'FR-2026-0004'))[0];
        self::assertSame(['Observation du pont (nuit du 9)', 'Classé dans', 'Recueilli dans le cadre de cette affaire', 'Analyste Sept', '09/11/2026'], [$note['label'], $note['nature_label'], $note['reason'], $note['author'], $note['date']]);
        self::assertSame(['Terrain', 'À recouper'], array_column($note['tags'], 'name'));
        self::assertTrue($note['can_edit']);
        self::assertArrayHasKey('note', $as['types']);
        self::assertArrayHasKey('evidence_of', $as['natures']);
        // Aucune fuite dans l'élément restreint.
        $hidden = array_values(array_filter($as['items'], static fn (array $i): bool => $i['restricted']))[0];
        self::assertSame(['restricted', 'id'], array_keys($hidden));
        self::assertStringNotContainsString('Source', (string) json_encode($as, JSON_UNESCAPED_UNICODE));
        // Le lecteur ne modifie rien ; le gestionnaire voit tout.
        foreach ((new SseAttachService(SseAttachFixture::reader()))->attached('case', (string) $this->ids['case'])['items'] as $it) {
            self::assertFalse($it['restricted'] ? false : $it['can_edit']);
        }
        self::assertCount(5, array_filter($this->manager()->attached('case', (string) $this->ids['case'])['items'], static fn (array $i): bool => !$i['restricted']));
        // Cible invisible : pas de section du tout.
        self::assertNull($this->operator()->attached('case', (string) $this->ids['case_secret']));
        self::assertNull($this->operator()->attached('case', (string) $this->ids['case_t2']));
    }

    // ------------------------------------------------------------------ Mode dégradé

    public function testWithoutTheMigrationOnlyTheLegacyCaseAttachmentWorks(): void
    {
        SseAttachSqlite::close();
        $this->pdo = SseAttachSqlite::open(false);
        $this->ids = SseAttachFixture::seed($this->pdo, false);
        $svc = $this->operator();
        self::assertFalse(SseAttachService::ready());
        $ctx = $svc->context([$this->ref('note', 'note_free')]);
        self::assertFalse($ctx['ready']);
        self::assertSame([], $ctx['tags']);
        self::assertSame([], $ctx['can_create']);
        $src = $svc->directory()->sources([$this->ref('note', 'note_free')]);
        $tabs = array_column($svc->directory()->search($src, [])['scopes'], 'enabled', 'key');
        self::assertTrue($tabs['case']);
        self::assertFalse($tabs['lead']);
        self::assertFalse($tabs['person']);
        foreach ($svc->directory()->suggestions($src) as $s) {
            self::assertSame('case', $s['type']);
        }
        // Lecture : la recherche fonctionne. Rattachement historique à un dossier : la colonne est écrite, sans lien enrichi.
        self::assertGreaterThan(0, $svc->directory()->search($src, ['q' => 'reseau'])['total']);
        $res = $svc->attach([$this->ref('note', 'note_free')], [$this->ref('case', 'case3')], ['reason' => 'Motif suffisant', 'label' => 'ignoré', 'align' => true]);
        self::assertTrue($res['ok'], (string) $res['error']);
        self::assertTrue($res['degraded']);
        self::assertSame([], $res['created']);
        self::assertSame((int) $this->ids['case3'], (int) $this->scalar('SELECT case_id FROM sse_field_notes WHERE id = ' . $this->ids['note_free']));
        self::assertSame('interne', $this->scalar('SELECT classification FROM sse_field_notes WHERE id = ' . $this->ids['note_free']), 'pas d’alignement sans la base à jour');
        // Le reste est désactivé avec un message clair.
        self::assertSame(SseAttachService::NOT_READY, $svc->attach([$this->ref('note', 'note_free')], [$this->ref('person', 'person')], ['reason' => 'Motif suffisant'])['error']);
        self::assertSame(SseAttachService::NOT_READY, $svc->createTarget('lead', ['title' => 'Pas encore'])['error']);
        self::assertSame(SseAttachService::NOT_READY, $svc->detach(1, 'Motif suffisant')['error']);
        self::assertSame([], $svc->proposals());
        self::assertFalse($svc->attached('case', (string) $this->ids['case'])['ready']);
    }
}
