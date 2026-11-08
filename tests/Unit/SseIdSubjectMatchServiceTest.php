<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdSubjectMatchService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Recoupement des contrôles par clé biométrique : lien automatique sans ambiguïté, suggestions sinon, jamais de fusion ;
 * recherche exacte du numéro parmi les résultats ; cloison des communautés.
 */
final class SseIdSubjectMatchServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        $this->pdo->exec('CREATE TABLE sse_persons (id INTEGER PRIMARY KEY, tenant_id INTEGER, context_id INTEGER, last_name TEXT, first_name TEXT, target_unit_netid TEXT, bio_key TEXT)');
        $this->pdo->exec("INSERT INTO sse_persons (id, tenant_id, context_id, last_name, first_name) VALUES (40, 1, 1, 'Kostas', 'Yannis'), (41, 1, 1, 'Markos', 'Petros'), (50, 2, 1, 'Autre', 'Communauté')");
        $this->pdo->exec("INSERT INTO users (id, tenant_id, steam_id, display_name) VALUES (10, 1, '76561198000000010', 'Joueur')");
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private function identity(int $tenant, string $last, string $kind = 'civil', ?int $user = null): int
    {
        $id = (new SseIdentityRepository())->create($tenant, ['kind' => $kind, 'last_name' => $last, 'first_name' => 'X', 'user_id' => $user]);
        if ($user !== null) {
            (new SseIdentityRepository())->linkUser($id, $tenant, $user);
        }

        return $id;
    }

    private function setKey(string $table, int $id, string $key): void
    {
        Database::getInstance()->execute('UPDATE ' . $table . ' SET bio_key = :k WHERE id = :i', ['k' => $key, 'i' => $id]);
    }

    /** @param array<string, mixed> $extra */
    private function submit(int $tenant, string $key, array $extra = []): array
    {
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('findById')->willReturnCallback(static fn (int $id, ?int $t = null): ?array => in_array($id, [40, 41], true) && $t === 1 ? ['id' => $id] : null);
        $persons->method('findLikelyDuplicate')->willReturn(null);
        $service = new SseIdCheckService(new SseIdCheckRepository(), new SseIdDocumentRepository(), $persons);
        $stored = $service->store($tenant, 1, $extra + ['idempotency_key' => 'k-' . $key . '-' . uniqid(), 'subject' => ['bio_key' => $key]]);

        return (new SseIdCheckRepository())->find($stored['id'], $tenant);
    }

    public function testCheckLinksToTheOnlyPersonOfThatKeyOnTheMap(): void
    {
        $this->setKey('sse_persons', 40, 'HVT-1');
        self::assertSame(40, $this->submit(1, 'HVT-1')['person_id']);
        self::assertNull($this->submit(2, 'HVT-1')['person_id'], 'clé d’une autre communauté');
        self::assertNull($this->submit(1, 'HVT-2')['person_id'], 'clé inconnue');
    }

    public function testTwoPersonsSharingAKeyAreSuggestedNeverMerged(): void
    {
        $this->setKey('sse_persons', 40, 'K1');
        $this->setKey('sse_persons', 41, 'K1');
        self::assertNull($this->submit(1, 'K1')['person_id'], 'ambigu : aucun lien automatique');

        $suggestions = (new SseIdSubjectMatchService())->suggestionsForPerson(1, 40);
        self::assertSame([['person', 41]], array_map(static fn (array $s): array => [$s['type'], $s['id']], $suggestions));
        $rows = Database::getInstance()->fetchAll('SELECT id FROM sse_persons WHERE tenant_id = 1');
        self::assertCount(2, $rows, 'les deux fiches restent');
    }

    public function testIdentityOfTheKeyIsLinkedAndItsPersonFollows(): void
    {
        $identity = $this->identity(1, 'Dupont');
        Database::getInstance()->execute('UPDATE sse_identities SET person_id = 41 WHERE id = :i', ['i' => $identity]);
        $this->setKey('sse_identities', $identity, 'K2');

        $check = $this->submit(1, 'K2', ['presented_name' => ['last' => 'Dupont']]);
        self::assertSame($identity, $check['identity_id']);
        self::assertSame(41, $check['person_id']);
    }

    public function testSeveralIdentitiesOfAKeyAreDecidedByThePresentedName(): void
    {
        $civil = $this->identity(1, 'Dupont');
        $cover = $this->identity(1, 'Markos', 'cover');
        $this->setKey('sse_identities', $civil, 'K3');
        $this->setKey('sse_identities', $cover, 'K3');

        self::assertSame($cover, $this->submit(1, 'K3', ['presented_name' => ['last' => 'MARKOS']])['identity_id']);
        self::assertNull($this->submit(1, 'K3', ['presented_name' => ['last' => 'Inconnu']])['identity_id']);
    }

    public function testKnownPersonTeachesItsKeyAndBacklinksEarlierChecks(): void
    {
        $earlier = $this->submit(1, 'K4');
        self::assertNull($earlier['person_id']);

        $this->submit(1, 'K4', ['subject' => ['bio_key' => 'K4', 'person_id' => 40]]);

        self::assertSame(40, (new SseIdCheckRepository())->find($earlier['id'], 1)['person_id'], 'contrôle précédent rattaché');
        self::assertSame(40, $this->submit(1, 'K4')['person_id'], 'clé apprise');
    }

    public function testAStolenPaperDoesNotTeachTheKeyToItsHolder(): void
    {
        $identity = $this->identity(1, 'Dupont', 'civil', 10);
        $doc = (new SseIdDocumentRepository())->create(1, ['identity_id' => $identity, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'ALK900']);

        $this->submit(1, 'THIEF', ['document' => ['id' => $doc], 'subject' => ['bio_key' => 'THIEF', 'steam_id' => '76561198000000099']]);
        self::assertNull((new SseIdentityRepository())->find($identity, 1)['bio_key']);

        $this->submit(1, 'OWNER', ['document' => ['id' => $doc], 'subject' => ['bio_key' => 'OWNER', 'steam_id' => '76561198000000010']]);
        self::assertSame('OWNER', (new SseIdentityRepository())->find($identity, 1)['bio_key']);
    }

    public function testExactNumberIsFoundAmongPartialMatches(): void
    {
        $identity = $this->identity(1, 'Dupont');
        $docs = new SseIdDocumentRepository();
        $exact = $docs->create(1, ['identity_id' => $identity, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'ALK1']);
        // Créés après : remontés en premier par la recherche partielle.
        foreach (['ALK12', 'ALK13'] as $n) {
            $docs->create(1, ['identity_id' => $identity, 'doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => $n]);
        }
        $check = $this->submit(1, 'K5', ['document' => ['type' => 'passport', 'country' => 'ALT', 'number' => 'ALK1']]);
        self::assertSame($exact, $check['document_id']);
    }
}
