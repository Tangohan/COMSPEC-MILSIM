<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdOperationRepository;
use App\Repositories\SseKitBatchRepository;
use App\Repositories\SseLoreRepository;
use App\Services\Sse\SseIdMassGeneratorService;
use App\Services\Sse\SseKitContentService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/**
 * Portail SSE › Générer du contenu, sur une vraie base (sqlite, vraies migrations) : l'aperçu n'écrit rien, la création
 * pose un lot identifiable dans les tables existantes, tout est marqué fictif et généré, les identités entrent dans la
 * population que le jeu charge, deux lots ne partagent aucune valeur, les communautés sont cloisonnées, les plafonds
 * bloquent sans rien écrire, et un lot se supprime en bloc sans toucher au reste.
 */
final class SseKitContentServiceTest extends TestCase
{
    private const TODAY = '2026-11-15';

    private PDO $pdo;

    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseFilesSqlite::open();
        $this->audit = [];
        SseIdMassGeneratorService::useAuditSink(function (array $e): void {
            $this->audit[] = $e;
        });
    }

    protected function tearDown(): void
    {
        SseIdMassGeneratorService::useAuditSink(null);
        SseFilesSqlite::close();
    }

    /**
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private static function params(array $over = []): array
    {
        return SseKitContentService::normalizeParams($over + [
            'kits' => ['IRQ', 'MLI'], 'themes' => ['insurgent_network', 'finance_cell'], 'people' => 40, 'civil_pct' => 60, 'link_density' => 60,
            'forged_pct' => 40, 'alias_pct' => 60, 'wanted_pct' => 40, 'vehicles_pp' => 6, 'docs_pp' => 2, 'label' => 'Vallée nord',
        ]);
    }

    private function rows(string $table, int $tenant, string $extra = ''): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE tenant_id = {$tenant}{$extra}")->fetchColumn();
    }

    /** @return array<string, int> lignes par table pour une communauté */
    private function snapshot(int $tenant): array
    {
        $out = [];
        foreach (['sse_identities', 'sse_id_documents', 'sse_id_batches', 'sse_id_op_wanted', 'sse_file_profiles', 'sse_file_aliases', 'sse_file_links', 'sse_lore_members'] as $t) {
            $out[$t] = $this->rows($t, $tenant);
        }

        return $out;
    }

    public function testPreviewWritesNothingAndMatchesWhatIsCreated(): void
    {
        $svc = new SseKitContentService();
        $before = $this->snapshot(1);
        $preview = $svc->preview(1, self::params(), 4242, self::TODAY);

        self::assertSame($before, $this->snapshot(1), 'l’aperçu n’écrit rien');
        self::assertSame(40, $preview['summary']['people']);
        self::assertSame([], $preview['blockers']);
        self::assertNotEmpty($preview['sample']);
        self::assertLessThanOrEqual(SseKitContentService::PREVIEW_SAMPLE, count($preview['sample']));
        self::assertTrue($preview['sample'][0]['leader'], 'les chefs d’organisation ouvrent l’échantillon');
        self::assertSame(SseKitContentService::MAX_COMMUNITY, $preview['room']['community']);
        self::assertSame($preview, $svc->preview(1, self::params(), 4242, self::TODAY), 'même graine : même aperçu');

        $res = $svc->commit(1, self::params(), 4242, 7, 'CNE Dupont', self::TODAY);
        self::assertTrue($res['ok']);
        self::assertSame($preview['summary']['people'], $res['identities']);
        self::assertSame($preview['summary']['documents'], $res['documents'], 'le lot créé est celui de l’aperçu');
        self::assertSame($preview['summary']['wanted'], $res['wanted']);
        $names = array_map(static fn (array $p): string => $p['last_name'], $preview['sample']);
        $stored = $this->pdo->query('SELECT last_name FROM sse_identities WHERE tenant_id = 1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $n) {
            self::assertContains($n, $stored);
        }
    }

    public function testABatchIsIdentifiableMarkedFictionalAndReachesTheGame(): void
    {
        $svc = new SseKitContentService();
        $res = $svc->commit(1, self::params(), 4242, 7, 'CNE Dupont', self::TODAY);
        self::assertTrue($res['ok']);
        $batchId = $res['batch_id'];

        $batches = $svc->listBatches(1);
        self::assertCount(1, $batches);
        self::assertSame($batchId, $batches[0]['id']);
        self::assertStringStartsWith(SseKitContentService::MARK, $batches[0]['label']);
        self::assertStringContainsString('Vallée nord', $batches[0]['label']);
        self::assertSame(4242, $batches[0]['seed']);
        self::assertSame(SseKitBatchRepository::GENERATOR, $batches[0]['params']['generator']);
        self::assertTrue($batches[0]['params']['fictional']);
        self::assertSame('CNE Dupont', $batches[0]['params']['author']);
        self::assertSame(['IRQ', 'MLI'], $batches[0]['params']['kits']);
        self::assertSame(40, $batches[0]['identities_count']);
        self::assertSame($res['documents'], $batches[0]['documents_count']);
        self::assertNotSame('', $batches[0]['created_at']);
        self::assertSame(7, (int) $this->pdo->query("SELECT created_by_user_id FROM sse_id_batches WHERE id = {$batchId}")->fetchColumn());

        // Tout est marqué fictif et généré, et rattaché au lot.
        self::assertSame(40, $this->rows('sse_identities', 1, " AND kind = 'generated' AND batch_id = {$batchId} AND created_by_user_id = 7"));
        self::assertSame(40, $this->rows('sse_identities', 1, " AND notes LIKE '" . SseKitContentService::MARK . "%'"));
        self::assertSame($res['documents'], $this->rows('sse_id_documents', 1, " AND batch_id = {$batchId} AND workflow = 'issued' AND notes LIKE '" . SseKitContentService::MARK . "%'"));
        self::assertSame(0, $this->rows('sse_id_documents', 1, " AND st_origin NOT IN ('generated', 'forged')"));
        self::assertGreaterThan(0, $this->rows('sse_id_documents', 1, " AND st_origin = 'forged' AND notes LIKE '%Discordance voulue%'"));
        self::assertSame(40, $this->rows('sse_file_profiles', 1, ' AND in_files = 1 AND height_cm IS NOT NULL'));
        self::assertSame(40, $this->rows('sse_file_profiles', 1, " AND knowledge LIKE '" . SseKitContentService::MARK . "%'"));
        self::assertSame(40, $this->rows('sse_file_links', 1, " AND kind = 'address'"));
        self::assertGreaterThan(20, $this->rows('sse_file_links', 1, " AND kind = 'phone'"));
        self::assertGreaterThan(10, $this->rows('sse_file_links', 1, " AND kind = 'vehicle'"));
        self::assertGreaterThan(20, $this->rows('sse_file_links', 1, " AND kind = 'associate' AND linked_identity_id IS NOT NULL"));
        self::assertGreaterThan(0, $this->rows('sse_file_aliases', 1, " AND kind = 'nickname'"));
        self::assertSame($res['wanted'], $this->rows('sse_id_op_wanted', 1, " AND batch_id = {$batchId}"));
        self::assertGreaterThan(0, $res['wanted']);

        // Récit : deux ou trois phrases dans les notes de l'identité, jamais deux fois le même.
        $notes = $this->pdo->query('SELECT notes FROM sse_identities WHERE tenant_id = 1')->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(40, array_unique($notes));

        // Les pays des kits sont entrés au registre de la communauté, au format de numéro du jeu.
        $countries = array_column((new SseIdCountryRepository())->listForTenant(1), null, 'code');
        self::assertSame('IQ@######', $countries['IRQ']['number_format']);
        self::assertSame('ML@######', $countries['MLI']['number_format']);
        self::assertTrue($countries['MLI']['real_sim']);
        $numbers = $this->pdo->query("SELECT doc_number FROM sse_id_documents WHERE tenant_id = 1 AND country_code = 'MLI' AND st_origin = 'generated'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotEmpty($numbers);
        foreach ($numbers as $n) {
            self::assertMatchesRegularExpression('/^ML[A-Z]\d{6}$/', (string) $n);
        }

        // Ce que le jeu récupère déjà : la population du paquet de n'importe quelle carte, avec ses papiers émis.
        $population = (new SseIdOperationRepository())->populationForPack(1, 3, null);
        self::assertCount(40, $population);
        self::assertSame($res['documents'], array_sum(array_map(static fn (array $p): int => count($p['documents']), $population)));
        self::assertContains('civilian', array_column($population, 'category'));
        self::assertContains('guer', array_column($population, 'side'));
        self::assertNotEmpty((new SseIdOperationRepository())->listWanted(1, null, null));

        // Journal.
        self::assertSame('kit_batch.create', $this->audit[0]['action']);
        self::assertSame($batchId, $this->audit[0]['object_id']);
        self::assertSame(7, $this->audit[0]['actor_user_id']);
        self::assertSame(1, $this->audit[0]['tenant_id']);
        self::assertSame(40, $this->audit[0]['after']['identities']);
    }

    public function testTwoBatchesNeverShareANameAPhoneAPlateOrANumber(): void
    {
        $svc = new SseKitContentService();
        // Même graine et mêmes paramètres : sans la mémoire du registre, le second lot serait la copie du premier.
        self::assertTrue($svc->commit(1, self::params(), 4242, 7, '', self::TODAY)['ok']);
        self::assertTrue($svc->commit(1, self::params(), 4242, 7, '', self::TODAY)['ok']);

        self::assertSame(80, $this->rows('sse_identities', 1));
        foreach ([
            'noms' => "SELECT first_name || ' ' || last_name FROM sse_identities WHERE tenant_id = 1",
            'téléphones' => "SELECT value_norm FROM sse_file_links WHERE tenant_id = 1 AND kind = 'phone'",
            'plaques' => "SELECT value_norm FROM sse_file_links WHERE tenant_id = 1 AND kind = 'vehicle'",
            'numéros' => "SELECT country_code || doc_type || doc_number FROM sse_id_documents WHERE tenant_id = 1",
        ] as $what => $sql) {
            $values = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            self::assertNotEmpty($values);
            self::assertSame(count($values), count(array_unique($values)), 'aucun doublon avec l’existant : ' . $what);
        }
    }

    public function testCommunitiesAreIsolated(): void
    {
        $svc = new SseKitContentService();
        $one = $svc->commit(1, self::params(), 4242, 7, '', self::TODAY);
        $two = $svc->commit(2, self::params(['people' => 12]), 4242, 9, '', self::TODAY);
        self::assertTrue($one['ok']);
        self::assertTrue($two['ok']);

        self::assertSame(40, $this->rows('sse_identities', 1));
        self::assertSame(12, $this->rows('sse_identities', 2));
        self::assertCount(1, $svc->listBatches(1));
        self::assertCount(1, $svc->listBatches(2));
        self::assertSame($one['batch_id'], $svc->listBatches(1)[0]['id']);
        self::assertCount(12, (new SseIdOperationRepository())->populationForPack(2, 1, null));
        self::assertSame(0, $this->rows('sse_file_links', 2, ' AND linked_identity_id IN (SELECT id FROM sse_identities WHERE tenant_id = 1)'));

        // Une communauté ne supprime pas le lot d'une autre.
        $before = $this->snapshot(1);
        self::assertNull($svc->delete(2, $one['batch_id'], 9));
        self::assertNull((new SseKitBatchRepository())->purge(2, $one['batch_id']));
        self::assertSame($before, $this->snapshot(1));
        self::assertSame(0, $svc->commit(0, self::params(), 1, 7, '', self::TODAY)['ok'] ? 1 : 0, 'communauté inconnue : refus');
    }

    public function testDeletingABatchRemovesItAllAndNothingElse(): void
    {
        $svc = new SseKitContentService();
        // Le reste du registre : une identité saisie à la main avec un papier et une fiche, et un lot de préparation de mission.
        $manual = (new SseIdentityRepository())->create(1, ['kind' => 'civil', 'last_name' => 'Morel', 'first_name' => 'Claire', 'sex' => 'F'], 7);
        (new SseIdDocumentRepository())->create(1, ['identity_id' => $manual, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'AL1234567'], 7);
        $records = new SseFileRecordRepository();
        $records->saveProfile(1, $manual, ['height_cm' => 170, 'in_files' => 1], 7);
        $prep = (new SseIdOperationRepository())->createBatch(1, null, null, 'Population OP-1', ['countries' => ['ALT']], 5, [], 7);
        $prepIdentity = (new SseIdentityRepository())->create(1, ['kind' => 'generated', 'last_name' => 'Petit', 'first_name' => 'Jean', 'batch_id' => $prep], 7);

        $first = $svc->commit(1, self::params(), 4242, 7, '', self::TODAY);
        $second = $svc->commit(1, self::params(['people' => 15, 'label' => 'Port sud']), 99, 7, '', self::TODAY);
        self::assertTrue($first['ok'] && $second['ok']);
        // Une fiche hors lot renvoie vers une personne du lot : le lien reste, le renvoi tombe avec le lot.
        $target = (int) $this->pdo->query("SELECT id FROM sse_identities WHERE tenant_id = 1 AND batch_id = {$first['batch_id']} LIMIT 1")->fetchColumn();
        $records->addLink(1, $manual, ['kind' => 'associate', 'value' => 'Connaissance du marché', 'linked_identity_id' => $target, 'relation' => 'contact'], 7);

        // Un lot de préparation de mission n'est pas un lot de contenu généré : il ne se supprime pas ici.
        self::assertNull($svc->delete(1, $prep, 7));
        self::assertNull($svc->delete(1, 987654, 7));

        $done = $svc->delete(1, $first['batch_id'], 7);
        self::assertNotNull($done);
        self::assertSame(40, $done['identities']);
        self::assertSame($first['documents'], $done['documents']);
        self::assertGreaterThan(40, $done['links']);

        self::assertSame(0, $this->rows('sse_identities', 1, " AND batch_id = {$first['batch_id']}"));
        self::assertSame(0, $this->rows('sse_id_documents', 1, " AND batch_id = {$first['batch_id']}"));
        self::assertSame(0, $this->rows('sse_id_op_wanted', 1, " AND batch_id = {$first['batch_id']}"));
        self::assertSame(0, $this->rows('sse_id_batches', 1, " AND id = {$first['batch_id']}"));
        foreach (['sse_file_profiles', 'sse_file_aliases', 'sse_file_links', 'sse_id_documents', 'sse_id_op_wanted'] as $table) {
            self::assertSame(0, $this->rows($table, 1, ' AND identity_id NOT IN (SELECT id FROM sse_identities WHERE tenant_id = 1)'), $table . ' : aucune ligne orpheline');
        }

        // Le reste est intact : identité manuelle et sa fiche, lot de préparation, second lot de contenu.
        self::assertSame(1, $this->rows('sse_identities', 1, " AND id = {$manual} AND last_name = 'Morel'"));
        self::assertSame(1, $this->rows('sse_id_documents', 1, " AND identity_id = {$manual} AND doc_number = 'AL1234567'"));
        self::assertSame(1, $this->rows('sse_file_profiles', 1, " AND identity_id = {$manual} AND height_cm = 170"));
        self::assertSame(1, $this->rows('sse_file_links', 1, " AND identity_id = {$manual} AND linked_identity_id IS NULL AND value = 'Connaissance du marché'"));
        self::assertSame(1, $this->rows('sse_id_batches', 1, " AND id = {$prep}"));
        self::assertSame(1, $this->rows('sse_identities', 1, " AND id = {$prepIdentity}"));
        self::assertSame(15, $this->rows('sse_identities', 1, " AND batch_id = {$second['batch_id']}"));
        self::assertSame($second['documents'], $this->rows('sse_id_documents', 1, " AND batch_id = {$second['batch_id']}"));
        self::assertCount(1, $svc->listBatches(1));
        // Paquet du jeu : le second lot de contenu et la personne du lot de préparation de mission.
        self::assertCount(16, (new SseIdOperationRepository())->populationForPack(1, 1, null));

        $last = end($this->audit);
        self::assertSame('kit_batch.delete', $last['action']);
        self::assertSame($first['batch_id'], $last['object_id']);
        self::assertSame(40, $last['after']['identities']);
        // Supprimer deux fois : rien à faire.
        self::assertNull($svc->delete(1, $first['batch_id'], 7));
    }

    public function testCeilingsBlockWithoutWritingAnything(): void
    {
        $svc = new SseKitContentService();
        $before = $this->snapshot(1);

        // Paquet du jeu : 500 papiers au plus par opération.
        $big = self::params(['people' => 200, 'docs_pp' => 4, 'vehicles_pp' => 20]);
        $preview = $svc->preview(1, $big, 5, self::TODAY);
        self::assertGreaterThan(SseIdDocumentRepository::PACK_MAX_PER_MAP, $preview['summary']['documents']);
        self::assertNotEmpty($preview['blockers']);
        self::assertStringContainsString('500', $preview['blockers'][0]);
        $res = $svc->commit(1, $big, 5, 7, '', self::TODAY);
        self::assertFalse($res['ok']);
        self::assertStringContainsString('papiers', (string) $res['error']);
        self::assertSame($before, $this->snapshot(1), 'refus : rien n’est écrit');
        self::assertSame([], $this->audit);

        // Plafond de la communauté : 1 500 personnes générées en même temps.
        $batch = (new SseIdOperationRepository())->createBatch(1, null, 'AUTRE-OP', 'Lot plein', ['generator' => SseKitBatchRepository::GENERATOR], 1, [], 7);
        $insert = $this->pdo->prepare("INSERT INTO sse_identities (tenant_id, kind, last_name, first_name, sex, batch_id, created_at) VALUES (1, 'generated', ?, 'X', 'M', ?, '2026-11-15 00:00:00')");
        $this->pdo->beginTransaction();
        for ($i = 0; $i < SseKitContentService::MAX_COMMUNITY - 10; $i++) {
            $insert->execute(['Plein' . $i, $batch]);
        }
        $this->pdo->commit();
        $small = self::params(['people' => 30]);
        $room = $svc->room(1, $small);
        self::assertSame(10, $room['community']);
        $res = $svc->commit(1, $small, 6, 7, '', self::TODAY);
        self::assertFalse($res['ok']);
        self::assertStringContainsString((string) SseKitContentService::MAX_COMMUNITY, (string) $res['error']);
        self::assertTrue($svc->commit(1, self::params(['people' => 10]), 6, 7, '', self::TODAY)['ok'], 'dix places restantes : un lot de dix passe');
        self::assertSame(0, $svc->room(1, $small)['community']);
        // Une autre communauté garde tout son plafond.
        self::assertSame(SseKitContentService::MAX_COMMUNITY, $svc->room(2, $small)['community']);
    }

    public function testAnOperationCodeScopesTheBatchAndALoreSessionReceivesThePeople(): void
    {
        $svc = new SseKitContentService();
        $lore = new SseLoreRepository();
        $sessionId = $lore->create(1, ['op_key' => 'OP-2611-07', 'title' => 'Silver Lake', 'summary' => '', 'country' => null], 7);
        $foreign = $lore->create(2, ['op_key' => 'OP-B', 'title' => 'Autre communauté', 'summary' => '', 'country' => null], 9);

        $p = self::params(['people' => 20, 'op_key' => 'OP-2611-07', 'lore_session_id' => $sessionId]);
        $res = $svc->commit(1, $p, 31, 7, '', self::TODAY);
        self::assertTrue($res['ok']);
        self::assertSame(20, $lore->membersCount(1, $sessionId));
        self::assertSame(20, $this->rows('sse_file_profiles', 1, " AND lore_session_id = {$sessionId}"));
        self::assertSame(20, $this->rows('sse_identities', 1, " AND op_key = 'OP-2611-07'"));
        // Avec un code d'opération : seulement le paquet de cette mission.
        $ops = new SseIdOperationRepository();
        self::assertCount(20, $ops->populationForPack(1, 1, 'OP-2611-07'));
        self::assertCount(0, $ops->populationForPack(1, 1, null));
        self::assertCount(0, $ops->populationForPack(1, 1, 'AUTRE'));

        // La session de lore d'une autre communauté est refusée, sans rien écrire.
        $before = $this->snapshot(1);
        $bad = $svc->commit(1, self::params(['people' => 5, 'lore_session_id' => $foreign]), 32, 7, '', self::TODAY);
        self::assertFalse($bad['ok']);
        self::assertStringContainsString('lore', (string) $bad['error']);
        self::assertSame($before, $this->snapshot(1));

        // Le lot supprimé quitte aussi la session de lore.
        self::assertNotNull($svc->delete(1, $res['batch_id'], 7));
        self::assertSame(0, $lore->membersCount(1, $sessionId));
        self::assertNotNull($lore->find(1, $sessionId), 'la session elle-même reste');
    }
}
