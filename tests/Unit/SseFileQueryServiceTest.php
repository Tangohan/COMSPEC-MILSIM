<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRepository;
use App\Repositories\SseIdCheckRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SsePersonRepository;
use App\Services\Sse\SseCrossMatchService;
use App\Services\Sse\SseFileQueryService;
use App\Services\Sse\SseIdCheckService;
use App\Services\Sse\SseIdDocumentLookupService;
use App\Support\SseFileCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Passage aux fichiers : QW / QV / QA / QB au format de la DLL (forme inchangée), codes ajoutés et triés, journal, envois
 * du jeu (file_query, seek_record, id_record), et jamais de vérité de jeu (couverture, faux) dans une réponse.
 */
final class SseFileQueryServiceTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $logged = [];

    /** @var list<array<string, mixed>> */
    private array $identities = [];

    /** @var list<array<string, mixed>> */
    private array $documents = [];

    /** @var list<array<string, mixed>> */
    private array $mentions = [];

    /** @var list<array<string, mixed>> */
    private array $checksRows = [];

    /** @var array<int, array<string, mixed>> */
    private array $persons = [];

    /** @var list<array<string, mixed>> */
    private array $seek = [];

    /** @var array<string, mixed>|null */
    private ?array $base = null;

    /** @var list<array<string, mixed>> */
    private array $checkCalls = [];

    /** @var list<array<string, mixed>> */
    private array $links = [];

    protected function setUp(): void
    {
        $this->logged = $this->identities = $this->documents = $this->mentions = $this->checksRows = $this->seek = $this->checkCalls = $this->links = [];
        $this->persons = [];
        $this->base = null;
    }

    /** @return array<string, mixed> */
    private static function identity(int $id, string $last, string $first, string $kind = 'civil', ?string $dob = '1990-05-01', ?int $personId = null): array
    {
        return [
            'id' => $id, 'ref' => 'IDT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'kind' => $kind, 'last_name' => $last, 'first_name' => $first,
            'display_name' => mb_strtoupper($last) . ' ' . $first, 'sex' => 'M', 'birth_date' => $dob, 'birth_place' => null, 'nationality' => 'ALT',
            'profession' => null, 'person_id' => $personId, 'created_at' => '2026-11-01 10:00:00', 'updated_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private static function doc(int $id, int $identityId, string $number, array $over = [], string $kind = 'civil'): array
    {
        return $over + [
            'id' => $id, 'ref' => 'DOC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT), 'identity_id' => $identityId, 'doc_type' => 'id_card',
            'doc_type_label' => 'Carte d’identité', 'country_code' => 'ALT', 'doc_number' => $number, 'issued_on' => '2024-01-01',
            'expires_on' => '2034-01-01', 'expired' => false, 'st_origin' => 'official', 'st_validity' => 'valid', 'st_report' => 'none',
            'workflow' => 'issued', 'identity' => ['last_name' => 'Papadopoulos', 'first_name' => 'Nikos', 'kind' => $kind, 'person_id' => null, 'birth_date' => '1990-05-01'],
        ];
    }

    private function service(): SseFileQueryService
    {
        $files = $this->createMock(SseFileRepository::class);
        $files->method('logQuery')->willReturnCallback(function (int $t, array $row): array {
            $this->logged[] = $row;
            $id = count($this->logged);

            return ['id' => $id, 'qrn' => $row['qrn'] ?? SseFileCatalog::qrn($id, '2026-11-10 12:00:00'), 'duplicate' => false];
        });
        $files->method('activeMentionsFor')->willReturnCallback(fn (int $t, array $i, array $d, array $s = []): array => array_values(array_filter(
            $this->mentions,
            static fn (array $m): bool => in_array($m['identity_id'] ?? 0, $i, true) || in_array($m['document_id'] ?? 0, $d, true) || in_array($m['seek_record_id'] ?? 0, $s, true)
        )));
        $files->method('seekFor')->willReturnCallback(fn (int $t, array $by): array => array_values(array_filter(
            $this->seek,
            static fn (array $s): bool => (($by['bio_key'] ?? '') !== '' && $s['bio_key'] === $by['bio_key'])
                || (($by['doc_number'] ?? '') !== '' && $s['doc_number'] === $by['doc_number'])
                || in_array($s['identity_id'], $by['identity_ids'] ?? [], true)
        )));
        $files->method('findSeekByRec')->willReturnCallback(fn (int $t, string $rec): ?array => array_values(array_filter($this->seek, static fn (array $s): bool => $s['rec'] === $rec))[0] ?? null);
        $files->method('identitiesByBioKey')->willReturn([]);
        $files->method('findSeekByKey')->willReturn(null);
        $files->method('upsertSeek')->willReturnCallback(static fn (int $t, array $row): array => ['id' => 7, 'rec' => $row['rec'] !== '' ? $row['rec'] : 'REC-000007', 'created' => true]);
        $files->method('linkSeek')->willReturnCallback(function (int $id, int $t, ?int $i, ?int $p, ?int $d, ?int $c): void {
            $this->links[] = [$id, $i, $p, $d, $c];
        });

        $docs = $this->createMock(SseIdDocumentRepository::class);
        $docs->method('findOfficialByNumber')->willReturnCallback(fn (int $t, string $c, string $type, string $n): ?array => array_values(array_filter(
            $this->documents,
            static fn (array $d): bool => $d['doc_number'] === $n && $d['country_code'] === $c && $d['st_origin'] !== 'forged'
        ))[0] ?? null);
        $docs->method('list')->willReturnCallback(fn (int $t, array $f): array => array_values(array_filter(
            $this->documents,
            static fn (array $d): bool => (($f['identity_id'] ?? 0) === 0 || $d['identity_id'] === $f['identity_id'])
                && (($f['q'] ?? '') === '' || str_contains($d['doc_number'], (string) $f['q']))
        )));
        $ids = $this->createMock(SseIdentityRepository::class);
        $ids->method('list')->willReturnCallback(fn (): array => $this->identities);
        $ids->method('find')->willReturnCallback(fn (int $id): ?array => array_values(array_filter($this->identities, static fn (array $i): bool => $i['id'] === $id))[0] ?? null);
        $checks = $this->createMock(SseIdCheckRepository::class);
        $checks->method('list')->willReturnCallback(fn (): array => $this->checksRows);
        $checks->method('listForSubject')->willReturnCallback(fn (): array => $this->checksRows);
        $checks->method('find')->willReturn(['id' => 12, 'identity_id' => 3, 'person_id' => null, 'document_id' => 5, 'doc_number' => 'ALK482913', 'country_code' => 'ALT', 'doc_type' => 'id_card']);
        $cross = $this->createMock(SseCrossMatchService::class);
        $cross->method('matchOne')->willReturnCallback(static fn (array $p): array => ($p['last_name'] ?? '') === 'Haddad'
            ? [['entry' => ['id' => 4, 'last_name' => 'Haddad', 'first_name' => 'Karim', 'display_name' => 'Haddad Karim', 'threat_level' => 'prioritaire'], 'score' => 90, 'reason' => '']]
            : []);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('findById')->willReturnCallback(fn (int $id): ?array => $this->persons[$id] ?? null);
        $lookup = $this->createMock(SseIdDocumentLookupService::class);
        $lookup->method('lookup')->willReturnCallback(fn (int $t, array $q): array => $this->base ?? ['found' => false, 'verdict' => 'inconnu', 'ref' => '', 'level' => '', 'flags' => ['unknown_document'], 'updated_at' => null]);
        $checkService = $this->createMock(SseIdCheckService::class);
        $checkService->method('store')->willReturnCallback(function (int $t, int $m, array $body): array {
            $this->checkCalls[] = $body;

            return ['id' => 12, 'ref' => 'CTRL-12', 'duplicate' => false];
        });

        return new SseFileQueryService($files, $lookup, $docs, $ids, $checks, $cross, $persons, $checkService);
    }

    public function testGameParamsMapToQueryTypesAndKeepTheDocumentLookupForAnyOtherType(): void
    {
        self::assertSame('QW', SseFileQueryService::fromGameParams(['type' => 'qw', 'last' => 'X'])[0]);
        self::assertSame('QB', SseFileQueryService::fromGameParams(['type' => 'QB'])[0]);
        self::assertSame('QV', SseFileQueryService::fromGameParams(['type' => 'QV'])[0]);
        [$t, $c] = SseFileQueryService::fromGameParams(['type' => 'passport', 'country' => 'alt', 'number' => 'X1']);
        self::assertSame(['QA', 'passport', 'ALT'], [$t, $c['doc_type'], $c['country']]);
        self::assertSame(['QA', ''], [SseFileQueryService::fromGameParams(['type' => ''])[0], SseFileQueryService::fromGameParams(['type' => 'QA'])[1]['doc_type']]);
    }

    public function testDocumentLookupKeepsTheN3AnswerAndAddsCodesQrnAndJournal(): void
    {
        $this->identities = [self::identity(3, 'Papadopoulos', 'Nikos')];
        $this->documents = [self::doc(5, 3, 'ALK482913'), self::doc(6, 3, 'P1234567', ['doc_type' => 'passport', 'st_report' => 'stolen'])];
        $this->base = ['found' => true, 'verdict' => 'ras', 'ref' => 'DOC-000005', 'level' => '', 'flags' => [], 'updated_at' => '2026-11-01T10:00:00Z'];
        $this->checksRows = [['id' => 9, 'created_at' => '2026-11-09 08:00:00']];

        $res = $this->service()->gameLookup(1, ['country' => 'ALT', 'type' => 'id_card', 'number' => 'alk-482913', 'last' => 'Papadopoulos', 'first' => 'Nikos', 'dob' => ''], 42);

        self::assertSame(['found', 'verdict', 'ref', 'level', 'flags', 'updated_at'], array_keys($res));
        self::assertTrue($res['found']);
        self::assertSame('ras', $res['verdict']);
        self::assertSame('DOC-000005', $res['ref']);
        self::assertContains('doc:ALT|id_card|ALK482913|ok', $res['flags']);
        self::assertContains('doc:ALT|passport|P1234567|stolen', $res['flags']);
        self::assertContains('checks:1', $res['flags']);
        self::assertContains('lastcheck:2026-11-09T08:00:00Z', $res['flags']);
        self::assertMatchesRegularExpression('/^qrn:FQ-\d{8}-000001$/', (string) end($res['flags']));
        self::assertCount(1, $this->logged);
        self::assertSame(['QA', 'game', 42, true], [$this->logged[0]['query_type'], $this->logged[0]['source'], $this->logged[0]['operator_user_id'], $this->logged[0]['hit']]);
    }

    public function testNumberWithoutCountryIsSearchedEverywhere(): void
    {
        $this->identities = [self::identity(3, 'Papadopoulos', 'Nikos')];
        $this->documents = [self::doc(5, 3, 'ALK482913', ['country_code' => 'BRV'])];
        $this->base = ['found' => true, 'verdict' => 'ras', 'ref' => 'DOC-000005', 'level' => '', 'flags' => [], 'updated_at' => null];

        $res = $this->service()->run(1, 'QA', ['number' => 'ALK482913'], ['source' => 'web']);
        self::assertTrue($res['report']['hit']);
        self::assertSame('BRV', $res['report']['documents'][0]['country_code']);
    }

    public function testActiveMentionsComeFirstAndRaiseTheVerdict(): void
    {
        $this->identities = [self::identity(3, 'Papadopoulos', 'Nikos')];
        $this->documents = [self::doc(5, 3, 'ALK482913')];
        $this->base = ['found' => true, 'verdict' => 'ras', 'ref' => 'DOC-000005', 'level' => '', 'flags' => ['expired'], 'updated_at' => null];
        $this->mentions = [
            ['id' => 1, 'mention' => 'caution', 'identity_id' => 3],
            ['id' => 2, 'mention' => 'armed_dangerous', 'identity_id' => 3],
        ];

        $res = $this->service()->gameLookup(1, ['country' => 'ALT', 'type' => '', 'number' => 'ALK482913'], null);
        self::assertSame('recherche', $res['verdict']);
        self::assertSame('prioritaire', $res['level']);
        self::assertSame('armed_dangerous', $res['flags'][0]);
        self::assertLessThan(array_search('expired', $res['flags'], true), array_search('caution', $res['flags'], true));
    }

    public function testPersonQueryMatchesNameAndBirthDateButNeverAForgery(): void
    {
        $this->identities = [
            self::identity(3, 'Papadopoulos', 'Nikos'),
            self::identity(4, 'Papadopoulos', 'Nikos', 'forged'),
            self::identity(8, 'Papadopoulos', 'Nikos', 'civil', '1971-01-01'),
        ];
        $this->documents = [self::doc(5, 3, 'ALK482913')];

        $res = $this->service()->gameLookup(1, ['type' => 'QW', 'last' => 'PAPADOPOULOS', 'first' => 'nikos', 'dob' => '01/05/1990', 'number' => '', 'country' => ''], null);
        self::assertTrue($res['found']);
        self::assertSame('IDT-000003', $res['ref']);
        self::assertSame(['last_name' => 'Papadopoulos', 'first_name' => 'Nikos', 'level' => ''], $res['holder']);
        self::assertContains('dob:1990-05-01', $res['flags']);
        self::assertContains('doc:ALT|id_card|ALK482913|ok', $res['flags']);

        $none = $this->service()->gameLookup(1, ['type' => 'QW', 'last' => 'Inconnu'], null);
        self::assertFalse($none['found']);
        self::assertSame('inconnu', $none['verdict']);
    }

    public function testPersonOnAWatchlistIsAHitEvenWithoutAnIdentity(): void
    {
        $res = $this->service()->gameLookup(1, ['type' => 'QW', 'last' => 'Haddad', 'first' => 'Karim'], null);
        self::assertTrue($res['found']);
        self::assertSame(['recherche', 'WL-4', 'prioritaire'], [$res['verdict'], $res['ref'], $res['level']]);
        self::assertContains('watchlist', $res['flags']);
    }

    public function testCoverIdentityIsInOrderAndNeverLinkedToTheRealPerson(): void
    {
        $this->persons[31] = ['id' => 31, 'status' => 'prioritaire', 'last_name' => 'Haddad'];
        $this->identities = [self::identity(3, 'Stavrou', 'Petros', 'cover', '1990-05-01', 31)];

        $res = $this->service()->gameLookup(1, ['type' => 'QW', 'last' => 'Stavrou', 'first' => 'Petros'], null);
        self::assertSame('ras', $res['verdict']);
        self::assertNotContains('person_priority', $res['flags']);
        $json = (string) json_encode($this->service()->run(1, 'QW', ['last' => 'Stavrou'])['report']);
        foreach (['cover', 'Haddad', 'kind', 'person_id'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
    }

    public function testVehicleQueryIsACleanNoHit(): void
    {
        $res = $this->service()->gameLookup(1, ['type' => 'QV', 'number' => 'AB-123-CD'], null);
        self::assertFalse($res['found']);
        self::assertSame('inconnu', $res['verdict']);
        self::assertSame('no_vehicle_file', $res['flags'][0]);
        self::assertStringStartsWith('qrn:', (string) $res['flags'][1]);
        self::assertSame('QV', $this->logged[0]['query_type']);
    }

    public function testBiometricQueryByRecGivesTheDeclaredHolder(): void
    {
        $this->seek = [[
            'id' => 7, 'rec' => 'REC-20261110-0001', 'bio_key' => 'S12AB', 'last_name' => 'Nowak', 'first_name' => 'Piotr', 'birth_date' => '1985-02-03',
            'subject_unknown' => false, 'doc_number' => null, 'verdict' => 'concordant', 'identity_id' => null, 'created_at' => '2026-11-10 09:00:00', 'updated_at' => null,
        ]];

        $res = $this->service()->gameLookup(1, ['type' => 'QB', 'number' => 'rec-20261110-0001'], null);
        self::assertTrue($res['found']);
        self::assertSame('REC-20261110-0001', $res['ref']);
        self::assertSame('Nowak', $res['holder']['last_name']);
        self::assertContains('seek:REC-20261110-0001', $res['flags']);
        self::assertContains('dob:1985-02-03', $res['flags']);
    }

    public function testDeferredQueryIsLoggedAsDeferredWithItsGameQrn(): void
    {
        $res = $this->service()->storeDeferred(1, [
            'idempotency_key' => 'fq-abc-001', 'qrn' => 'FQ-OFF-0001', 'type' => 'qw', 'query' => ['last' => 'Nowak', 'first' => 'Piotr'],
            'hit' => true, 'codes' => ['wanted', 'dob:1985-02-03'], 'source' => 'phone', 'grid' => '045123', 'controller' => ['callsign' => 'Alpha 1', 'steam_id' => '7656-1198'],
        ], 5);
        self::assertSame('FQ-OFF-0001', $res['ref']);
        $row = $this->logged[0];
        self::assertTrue($row['deferred']);
        self::assertSame(['QW', 'phone', true, 'fq-abc-001', '76561198', 'Alpha 1'], [$row['query_type'], $row['source'], $row['hit'], $row['idempotency_key'], $row['operator_steam_id'], $row['operator_label']]);
        self::assertSame(['error' => 'idempotency_key_required'], $this->service()->storeDeferred(1, ['idempotency_key' => 'x'], null));
    }

    public function testSeekRecordStoresAnN4CheckAndLinksTheRegistry(): void
    {
        $res = $this->service()->storeSeek(1, 2, [
            'idempotency_key' => 'seek-0001', 'rec' => 'REC-20261110-0001', 'bio_key' => 'S12AB', 'verdict' => 'discordant',
            'subject' => ['last' => 'Nowak', 'first' => 'Piotr', 'dob' => '1985-02-03', 'steam_id' => '765611'],
            'document' => ['type' => 'id_card', 'country' => 'alt', 'number' => 'alk-482913'],
            'modalities' => ['fingers' => true, 'iris' => false, 'face' => true], 'quality' => ['fp' => 88],
            'controller' => ['callsign' => 'Bravo'],
        ]);
        self::assertSame(['id' => 7, 'ref' => 'REC-20261110-0001', 'check_ref' => 'CTRL-12', 'duplicate' => false], $res);
        $call = $this->checkCalls[0];
        self::assertSame(['N4'], $call['levels']);
        self::assertSame('signale', $call['verdict']);
        self::assertSame('seek', $call['results']['N4']['source']);
        self::assertSame('S12AB', $call['subject']['bio_key']);
        self::assertSame('seek:seek-0001', $call['idempotency_key']);
        self::assertSame([[7, 3, null, 5, 12]], $this->links);
    }

    public function testIdRecordIsAnN0CheckAndAJournalLine(): void
    {
        $res = $this->service()->storeRecord(1, 2, [
            'idempotency_key' => 'rel-0001', 'document' => ['type' => 'id_card', 'country' => 'ALT', 'number' => 'ALK482913', 'local_id' => 'L1'],
            'presented_name' => ['last' => 'Papadopoulos'], 'visual' => 'doute', 'levels' => ['N0'], 'controller' => ['callsign' => 'Bravo'],
        ], null);
        self::assertSame(['id' => 12, 'ref' => 'CTRL-12'], ['id' => $res['id'], 'ref' => $res['ref']]);
        self::assertSame('signale', $this->checkCalls[0]['verdict']);
        self::assertSame('releve', $this->checkCalls[0]['results']['N0']['source']);
        self::assertSame(['QA', 'idcheck', true], [$this->logged[0]['query_type'], $this->logged[0]['source'], $this->logged[0]['hit']]);
    }

    public function testFlagsAreOrderedCappedAndKeepTheQrn(): void
    {
        $flags = ['dob:1990-01-01', 'expired', 'caution', 'warrant', 'checks:3'];
        for ($i = 0; $i < 20; $i++) {
            $flags[] = 'doc:ALT|id_card|N' . $i . '|ok';
        }
        $out = SseFileCatalog::orderFlags($flags, 'FQ-20261110-000123');
        self::assertCount(16, $out);
        self::assertSame(['warrant', 'caution', 'expired'], array_slice($out, 0, 3));
        self::assertSame('qrn:FQ-20261110-000123', $out[15]);
        self::assertSame('FQ-20261110-000123', SseFileCatalog::qrn(123, '2026-11-10 08:00:00'));
        self::assertSame('Armé et dangereux', SseFileCatalog::flagLabel('armed_dangerous'));
    }
}
