<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\SseCrossMatchService;
use App\Services\Sse\SseIdDocumentLookupService;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Contrôle N3 en ligne : verdicts du contrat (ras, signale, recherche, hvt, inconnu, revoque, vole, expire) et
 * jamais de vérité de jeu dans la réponse (couverture en règle, faux inconnu des fichiers, vraie identité tue).
 */
final class SseIdDocumentLookupServiceTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $persons = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseIdentitySqlite::open();
        $this->persons = [];
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private function service(): SseIdDocumentLookupService
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturnCallback(static fn (int $t): array => $t === 1 ? [
            ['id' => 4, 'last_name' => 'Haddad', 'first_name' => 'Karim', 'alias' => null, 'display_name' => 'Haddad Karim', 'threat_level' => 'prioritaire'],
            ['id' => 5, 'last_name' => 'Nowak', 'first_name' => 'Piotr', 'alias' => null, 'display_name' => 'Nowak Piotr', 'threat_level' => 'surveillance'],
        ] : []);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('findById')->willReturnCallback(fn (int $id, ?int $t = null): ?array => $this->persons[$id] ?? null);

        return new SseIdDocumentLookupService(new SseIdDocumentRepository(), new SseCrossMatchService($watch, $persons), $persons);
    }

    /** @param array<string, mixed> $doc */
    private function issue(string $last, string $first, string $number, array $doc = [], string $kind = 'civil', ?int $personId = null, int $tenant = 1): int
    {
        $identityId = (new SseIdentityRepository())->create($tenant, [
            'kind' => $kind, 'last_name' => $last, 'first_name' => $first, 'birth_date' => '1990-05-01', 'nationality' => 'ALT', 'person_id' => $personId,
        ]);
        $repo = new SseIdDocumentRepository();
        $id = $repo->create($tenant, $doc + [
            'identity_id' => $identityId, 'doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => $number,
            'issued_on' => '2024-01-01', 'expires_on' => '2034-01-01',
        ]);
        $repo->transition($id, $tenant, 'validated');
        $repo->transition($id, $tenant, 'issued');

        return $id;
    }

    /** @return array<string, mixed> */
    private function ask(string $number, string $last = '', string $first = '', string $dob = '', int $tenant = 1): array
    {
        return $this->service()->lookup($tenant, ['country' => 'ALT', 'type' => 'id_card', 'number' => $number, 'last' => $last, 'first' => $first, 'dob' => $dob]);
    }

    public function testCleanPaperAnswersRasWithTheContractFields(): void
    {
        $id = $this->issue('Papadopoulos', 'Nikos', 'ALK482913');
        $res = $this->ask('alk-482913', 'PAPADOPOULOS', 'nikos', '1990-05-01');

        self::assertSame(['found', 'verdict', 'ref', 'level', 'flags', 'updated_at'], array_keys($res));
        self::assertTrue($res['found']);
        self::assertSame('ras', $res['verdict']);
        self::assertSame('DOC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), $res['ref']);
        self::assertSame('', $res['level']);
        self::assertSame([], $res['flags']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $res['updated_at']);
    }

    public function testUnknownNumberAndForgeriesAreUnknownToTheFiles(): void
    {
        $this->issue('Papadopoulos', 'Nikos', 'ALX999999', ['st_origin' => 'forged', 'forgery_kind' => 'counterfeit'], 'forged');
        foreach (['ALX999999', 'ALK000000'] as $n) {
            $res = $this->ask($n, 'Papadopoulos', 'Nikos');
            self::assertFalse($res['found']);
            self::assertSame('inconnu', $res['verdict']);
            self::assertSame(['unknown_document'], $res['flags']);
        }
        // Inconnu, mais le nom déclaré est sur une liste.
        $res = $this->ask('ALK000000', 'Haddad', 'Karim');
        self::assertSame('recherche', $res['verdict']);
        self::assertSame('prioritaire', $res['level']);
        self::assertSame('WL-4', $res['ref']);
        self::assertFalse($this->ask('ALK482913', 'Papadopoulos', 'Nikos', '', 2)['found'], 'fichiers de la communauté seulement');
    }

    public function testAdministrativeVerdicts(): void
    {
        $revoked = $this->issue('Georgiou', 'Maria', 'ALK100001');
        (new SseIdDocumentRepository())->transition($revoked, 1, 'revoked', 1, 'Annulé');
        $this->issue('Georgiou', 'Eleni', 'ALK100002', ['st_report' => 'stolen']);
        $this->issue('Georgiou', 'Sofia', 'ALK100003', ['expires_on' => '2020-01-01']);
        $this->issue('Georgiou', 'Anna', 'ALK100004', ['st_report' => 'watch']);

        self::assertSame('revoque', $this->ask('ALK100001', 'Georgiou', 'Maria')['verdict']);
        self::assertSame(['stolen'], $this->ask('ALK100002', 'Georgiou', 'Eleni')['flags']);
        self::assertSame('vole', $this->ask('ALK100002', 'Georgiou', 'Eleni')['verdict']);
        self::assertSame('expire', $this->ask('ALK100003', 'Georgiou', 'Sofia')['verdict']);
        $watched = $this->ask('ALK100004', 'Georgiou', 'Anna');
        self::assertSame('signale', $watched['verdict']);
        self::assertSame('surveillance', $watched['level']);
    }

    public function testWatchlistAndPriorityPersonVerdicts(): void
    {
        $this->issue('Nowak', 'Piotr', 'LV1234567', ['country_code' => 'ALT']);
        $this->issue('Haddad', 'Karim', 'ALK200001');
        $this->persons[30] = ['id' => 30, 'status' => 'prioritaire'];
        $this->issue('Mansour', 'Omar', 'ALK200002', [], 'civil', 30);

        self::assertSame('signale', $this->ask('LV1234567', 'Nowak', 'Piotr')['verdict']);
        self::assertSame('recherche', $this->ask('ALK200001', 'Haddad', 'Karim')['verdict']);
        $hvt = $this->ask('ALK200002', 'Mansour', 'Omar');
        self::assertSame('hvt', $hvt['verdict']);
        self::assertSame('hvt', $hvt['level']);
        self::assertSame(['person_priority'], $hvt['flags']);
    }

    public function testCoverIdentityHoldsAndTheRealIdentityIsNeverDisclosed(): void
    {
        // Couverture préparée pour une cible prioritaire : les fichiers la voient en règle.
        $this->persons[31] = ['id' => 31, 'status' => 'prioritaire', 'last_name' => 'Haddad', 'first_name' => 'Karim'];
        $this->issue('Stavrou', 'Petros', 'ALK300001', ['st_origin' => 'cover'], 'cover', 31);

        $res = $this->ask('ALK300001', 'Stavrou', 'Petros');
        self::assertSame('ras', $res['verdict']);
        self::assertSame([], $res['flags']);
        $json = (string) json_encode($res);
        foreach (['Haddad', 'Karim', 'cover', 'forged', '"31"', 'person_id', 'kind', 'origin'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
    }

    public function testHolderMismatchGivesTheHolderNameOnlyWhenListed(): void
    {
        $this->issue('Haddad', 'Karim', 'ALK400001');
        $this->issue('Makris', 'Yannis', 'ALK400002');

        $listed = $this->ask('ALK400001', 'Pappas', 'Kostas');
        self::assertContains('holder_mismatch', $listed['flags']);
        self::assertSame('recherche', $listed['verdict']);
        self::assertSame(['last_name' => 'Haddad', 'first_name' => 'Karim', 'level' => 'prioritaire'], $listed['holder']);

        $plain = $this->ask('ALK400002', 'Pappas', 'Kostas', '1980-01-01');
        self::assertSame(['holder_mismatch', 'dob_mismatch'], $plain['flags']);
        self::assertSame('signale', $plain['verdict']);
        self::assertArrayNotHasKey('holder', $plain);
        self::assertStringNotContainsString('Makris', (string) json_encode($plain));
    }
}
