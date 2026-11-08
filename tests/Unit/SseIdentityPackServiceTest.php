<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseIdentityPackService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Pack d'identité du serveur de jeu : forme du contrat, seulement les papiers émis, pagination, cloison des
 * communautés, migration rejouable. Dépôts réels sur sqlite (migration du registre rendue pour sqlite).
 */
final class SseIdentityPackServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private function service(): SseIdentityPackService
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([
            ['id' => 4, 'last_name' => 'Haddad', 'first_name' => 'Karim', 'alias' => 'ABU HAMZA', 'threat_level' => 'prioritaire'],
        ]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([
            ['id' => 12, 'last_name' => 'Mansour', 'first_name' => 'Omar', 'alias' => null, 'status' => 'prioritaire'],
        ]);

        return new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons);
    }

    /** @return int identifiant du papier */
    private function document(int $tenant, string $number, string $workflow, ?int $map = null, string $kind = 'civil'): int
    {
        $identityId = (new SseIdentityRepository())->create($tenant, [
            'kind' => $kind, 'last_name' => 'Papadopoulos', 'first_name' => 'Nikos', 'sex' => 'M',
            'birth_date' => '1990-05-01', 'nationality' => 'ALT', 'person_id' => 7,
        ]);
        $docs = new SseIdDocumentRepository();
        $id = $docs->create($tenant, [
            'identity_id' => $identityId, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => $number,
            'issued_on' => '2024-01-01', 'expires_on' => '2034-01-01', 'context_id' => $map,
            'st_origin' => $kind === 'forged' ? 'forged' : 'official',
        ]);
        foreach (['validated', 'issued', 'revoked'] as $step) {
            if ($workflow === 'draft' || ($workflow === 'validated' && $step !== 'validated')) {
                break;
            }
            self::assertTrue($docs->transition($id, $tenant, $step, 1));
            if ($step === $workflow) {
                break;
            }
        }

        return $id;
    }

    public function testPackFollowsTheContractAndOnlyCarriesIssuedPapers(): void
    {
        $issued = $this->document(1, 'ALK482913', 'issued');
        $this->document(1, 'ALK000001', 'draft');
        $this->document(1, 'ALK000002', 'validated');
        $this->document(1, 'ALK000003', 'revoked');
        $forged = $this->document(1, 'ALX999999', 'issued', null, 'forged');

        $pack = $this->service()->build(1, 1);

        self::assertSame(['version', 'generated_at', 'map', 'op', 'countries', 'identities', 'documents', 'watch', 'kits', 'population', 'wanted', 'briefs', 'settings', 'paging'], array_keys($pack));
        self::assertSame(1, $pack['version']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $pack['generated_at']);
        self::assertSame(['ALT', 'LVN', 'MLD', 'TNA'], array_column($pack['countries'], 'code'));
        self::assertSame(['code', 'name', 'mrz_code', 'number_format', 'fictional', 'name_en', 'authority', 'theme', 'languages', 'doc_types', 'colors', 'active', 'real_sim', 'zone', 'name_native', 'validity'], array_keys($pack['countries'][0]));
        self::assertTrue($pack['countries'][0]['fictional']);

        self::assertSame([$issued, $forged], array_column($pack['documents'], 'id'));
        self::assertSame(
            ['id', 'identity_id', 'holder_person_id', 'type', 'country', 'number', 'issued_on', 'expires_on', 'authority', 'chip',
                'origin', 'integrity', 'validity', 'report', 'forgery_kind', 'forgery_quality'],
            array_keys($pack['documents'][0])
        );
        self::assertSame('ALK482913', $pack['documents'][0]['number']);
        self::assertSame(7, $pack['documents'][0]['holder_person_id']);
        // Vérité de jeu : le serveur sait qu'il s'agit d'un faux.
        self::assertSame('forged', $pack['documents'][1]['origin']);
        self::assertSame('counterfeit', $pack['documents'][1]['forgery_kind']);

        self::assertSame(
            ['id', 'person_id', 'kind', 'last_name', 'first_name', 'sex', 'birth_date', 'birth_place', 'nationality', 'arma_key', 'profession'],
            array_keys($pack['identities'][0])
        );
        self::assertCount(2, $pack['identities']);
        // Champs du contrat d'abord ; ensuite (lots 4 et 5) l'origine en jeu, ignorée par un ancien mod.
        self::assertSame(['ref', 'last_name', 'first_name', 'alias', 'level', 'person_id', 'game_ref', 'bio_key', 'known_docs', 'status'], array_keys($pack['watch'][0]));
        self::assertSame(array_keys($pack['watch'][0]), array_keys($pack['watch'][1]));
        self::assertSame(['prioritaire', 'hvt'], array_column($pack['watch'], 'level'));
        self::assertSame(['offset' => 0, 'limit' => 200, 'total' => 2, 'next_offset' => null], $pack['paging']);
    }

    public function testPackIsPagedAndScopedToTheMapAndTheCommunity(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->document(1, sprintf('ALK%06d', $i), 'issued');
        }
        $this->document(1, 'ALK777777', 'issued', 3);
        $this->document(2, 'ALK000001', 'issued');

        $service = $this->service();
        $first = $service->build(1, 1, null, null, 0, 2);
        self::assertSame(array_slice($ids, 0, 2), array_column($first['documents'], 'id'));
        self::assertSame(2, $first['paging']['next_offset']);
        self::assertSame(5, $first['paging']['total']);
        self::assertNotSame([], $first['watch']);

        $last = $service->build(1, 1, null, null, 4, 2);
        self::assertSame([$ids[4]], array_column($last['documents'], 'id'));
        self::assertNull($last['paging']['next_offset']);
        self::assertSame([], $last['watch'], 'listes envoyées une seule fois, avec la première page');

        self::assertCount(6, $service->build(1, 3)['documents'], 'papiers de toutes les cartes + ceux de la carte 3');
        $other = $service->build(2, 1);
        self::assertSame(['ALK000001'], array_column($other['documents'], 'number'));
        self::assertNotContains($ids[0], array_column($other['documents'], 'id'));
    }

    public function testIncrementalPackListsRevokedPapers(): void
    {
        $id = $this->document(1, 'ALK482913', 'issued');
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        (new SseIdDocumentRepository())->transition($id, 1, 'revoked', 1, 'Volé');

        $pack = $this->service()->build(1, 1, null, $since);
        self::assertSame([], $pack['documents']);
        self::assertSame([$id], $pack['revoked']);
        self::assertNull(SseIdentityPackService::normalizeSince('pas une date'));
    }

    public function testMigrationRunsTwiceWithoutChangingAnything(): void
    {
        $second = SseIdentitySqlite::migrate($this->pdo);
        self::assertStringContainsString('sse_id_checks (déjà présente)', $second);
        self::assertStringContainsString('0 ajout(s)', $second);
        self::assertSame(8, (int) $this->pdo->query('SELECT COUNT(*) FROM sse_id_countries')->fetchColumn());

        // Numéro unique par communauté, pays et type.
        $identityId = (new SseIdentityRepository())->create(1, ['last_name' => 'A', 'first_name' => 'B']);
        $docs = new SseIdDocumentRepository();
        $docs->create(1, ['identity_id' => $identityId, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'ALK1']);
        $this->expectException(\PDOException::class);
        $docs->create(1, ['identity_id' => $identityId, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'alk-1']);
    }

    public function testWorkflowOnlyMovesForward(): void
    {
        $identityId = (new SseIdentityRepository())->create(1, ['last_name' => 'A', 'first_name' => 'B']);
        $docs = new SseIdDocumentRepository();
        $id = $docs->create(1, ['identity_id' => $identityId, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'ALK2']);

        self::assertFalse($docs->transition($id, 1, 'issued'), 'un brouillon doit être validé avant émission');
        self::assertFalse($docs->transition($id, 2, 'validated'), 'autre communauté');
        self::assertTrue($docs->transition($id, 1, 'validated', 9));
        self::assertTrue($docs->transition($id, 1, 'issued'));
        self::assertFalse($docs->transition($id, 1, 'draft'));
        self::assertTrue($docs->transition($id, 1, 'revoked', 9, 'Perdu'));
        $doc = $docs->find($id, 1);
        self::assertSame('revoked', $doc['workflow']);
        self::assertSame('revoked', $doc['st_validity']);
        self::assertSame([], $docs->transition($id, 1, 'issued') ? ['x'] : []);
    }
}
