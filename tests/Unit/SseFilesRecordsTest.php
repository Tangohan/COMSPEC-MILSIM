<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Services\Sse\SseFileRecordService;
use App\Services\Sse\SseFileSearchService;
use App\Services\Sse\SseFileXrefService;
use App\Services\Sse\SseLoreService;
use App\Services\Sse\SsePhoneExtractionService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseFilesSqlite;

/**
 * Fichiers SSE sur une vraie base (sqlite) : recherche tolérante (alias, orthographe approchante, signalement, photo),
 * réponses « aucune inscription » et « correspondance possible », visibilité du lore, recoupements (prénom dans un
 * compte rendu, même téléphone, « ce n'est pas lui »), contacts antérieurs, extractions de téléphones (validation,
 * taille, compte, accord), lore de session (remontée, redescente, verrou, caché MJ) et isolation des communautés.
 */
final class SseFilesRecordsTest extends TestCase
{
    private PDO $pdo;
    private SseFileRecordRepository $records;
    private SseIdentityRepository $identities;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseFilesSqlite::open();
        $this->records = new SseFileRecordRepository();
        $this->identities = new SseIdentityRepository();
    }

    protected function tearDown(): void
    {
        SseFilesSqlite::close();
    }

    /** @param array<string, mixed> $profile */
    private function person(int $tenant, string $last, string $first, array $profile = [], string $dob = '1991-03-14', string $sex = 'M'): int
    {
        $id = $this->identities->create($tenant, ['last_name' => $last, 'first_name' => $first, 'birth_date' => $dob, 'sex' => $sex, 'nationality' => 'USA']);
        if ($profile !== []) {
            $this->records->saveProfile($tenant, $id, $profile, null);
        }

        return $id;
    }

    /** @param array<string, string> $p */
    private function search(int $tenant, array $p): array
    {
        return (new SseFileSearchService($this->records, $this->identities, new SseLoreRepository(), '2026-11-11'))->search($tenant, SseFileSearchService::cleanParams($p));
    }

    /** @return list<int> */
    private static function ids(array $result): array
    {
        return array_map(static fn (array $h): int => (int) $h['identity']['id'], $result['hits']);
    }

    public function testAliasAndSpellingToleranceFindTheSubject(): void
    {
        $karim = $this->person(1, 'Haddad', 'Karim');
        $this->records->addAlias(1, $karim, 'Abou Rami', 'nickname');
        $this->person(1, 'Walker', 'Daniel');
        $other = $this->person(2, 'Haddad', 'Karim');

        $r = $this->search(1, ['q' => 'Hadad']);
        self::assertSame([$karim], self::ids($r), 'orthographe approchante : Hadad trouve HADDAD');
        self::assertNotSame('exact', $r['hits'][0]['confidence']);

        $r = $this->search(1, ['q' => 'abou rami']);
        self::assertSame([$karim], self::ids($r), 'alias, sans accent ni majuscule');
        self::assertSame('Abou Rami', $r['hits'][0]['matched_alias']);

        self::assertSame([], self::ids($this->search(1, ['q' => 'abou rami', 'aliases' => '0'])), 'alias désactivés');
        self::assertSame([], self::ids($this->search(1, ['q' => 'Hadad', 'fuzzy' => '0'])), 'orthographe exacte demandée');

        $exact = $this->search(1, ['last' => 'HADDAD', 'first' => 'karim']);
        self::assertSame([$karim], self::ids($exact));
        self::assertSame('exact', $exact['hits'][0]['confidence']);
        self::assertNotContains($other, self::ids($exact), 'jamais une fiche d’une autre communauté');
    }

    public function testNoRecordAndForgedIdentitiesNeverAnswer(): void
    {
        $this->identities->create(1, ['last_name' => 'Faussaire', 'first_name' => 'Jean', 'kind' => 'forged']);
        self::assertSame(0, $this->search(1, ['q' => 'Faussaire'])['total'], 'un faux n’existe pas dans les fichiers');
        self::assertSame(0, $this->search(1, ['q' => 'Inconnu'])['total']);
        self::assertSame('name', SseFileSearchService::detect('Abou Rami')['type']);
        self::assertSame('phone', SseFileSearchService::detect('+1 (555) 014-2398')['type']);
        self::assertSame('number', SseFileSearchService::detect('AB-123-CD')['type']);
        self::assertSame('ref', SseFileSearchService::detect('IDT-0042')['type']);
        self::assertSame('1991-03-14', SseFileSearchService::normDate('03/14/1991'), 'jour en second au-delà de 12 : format américain');
        self::assertSame('1991-03-14', SseFileSearchService::normDate('14.03.1991'));
    }

    public function testHeightWeightAgeAndDescriptorFilters(): void
    {
        $tall = $this->person(1, 'Volkov', 'Dmitri', ['height_cm' => 191, 'weight_kg' => 96, 'hair' => 'bald', 'eyes' => 'gray', 'build' => 'heavy', 'marks' => 'Tatouage loup omoplate'], '1979-01-08');
        $mid = $this->person(1, 'Mercier', 'Julien', ['height_cm' => 176, 'weight_kg' => 81, 'hair' => 'brown', 'eyes' => 'blue'], '1988-11-02');
        $this->person(1, 'Azizi', 'Leila', ['height_cm' => 165], '1995-06-21', 'F');

        self::assertSame([$mid], self::ids($this->search(1, ['h_min' => '170', 'h_max' => '180'])));
        self::assertSame([$mid], self::ids($this->search(1, ['h_min' => "5'7", 'h_max' => "5'11"])), 'pieds-pouces acceptés');
        self::assertSame([$tall], self::ids($this->search(1, ['w_min' => '200 lb'])));
        self::assertSame([$tall], self::ids($this->search(1, ['age_min' => '45', 'sex' => 'M'])));
        self::assertSame([$tall], self::ids($this->search(1, ['marks' => 'loup'])));
        self::assertSame([$mid], self::ids($this->search(1, ['eyes' => 'blue', 'hair' => 'brown'])));
    }

    public function testPhotoBookKeepsOnlySubjectsWithAPhoto(): void
    {
        $with = $this->person(1, 'Brennan', 'Sarah', ['hair' => 'red'], '1990-12-05', 'F');
        $this->person(1, 'Nazari', 'Omid', ['hair' => 'black']);
        $this->records->addPhoto(1, ['identity_id' => $with, 'path' => 'uploads/sse-id-photos/1_x.jpg', 'mime' => 'image/jpeg', 'kind' => 'front'], 3);
        self::assertSame([$with], self::ids($this->search(1, ['photo' => '1'])));
        self::assertSame([$with], self::ids($this->search(1, ['photo' => '1', 'sex' => 'F', 'hair' => 'red'])));
        self::assertSame([], self::ids($this->search(1, ['photo' => '1', 'hair' => 'black'])));
    }

    public function testLoreVisibilityHidesWhatTheFilesDoNotKnow(): void
    {
        $lore = new SseLoreRepository();
        $sid = $lore->create(1, ['op_key' => 'OP-1', 'title' => 'Test'], 1);
        $boss = $this->person(1, 'Keller', 'Hans', ['lore_session_id' => $sid, 'height_cm' => 185,
            'visibility' => ['physical' => ['v' => 'known', 'phase' => 2]]]);
        $alias = $this->records->addAlias(1, $boss, 'Der Buchhalter', 'nickname', 'hidden');
        $ghost = $this->person(1, 'Schmidt', 'Anna', ['in_files' => 0]);

        self::assertSame([], self::ids($this->search(1, ['q' => 'Buchhalter'])), 'alias caché : introuvable');
        self::assertSame([], self::ids($this->search(1, ['h_min' => '180'])), 'taille révélée en phase 2 seulement');
        self::assertSame([], self::ids($this->search(1, ['q' => 'Schmidt'])), 'personne hors fichiers');
        $lore->update(1, $sid, ['phase' => 2]);
        self::assertSame([$boss], self::ids($this->search(1, ['h_min' => '180'])));
        $this->records->setAliasVisibility(1, (int) $alias, 'known', 0);
        self::assertSame([$boss], self::ids($this->search(1, ['q' => 'Buchhalter'])));

        $record = (new SseFileRecordService($this->records, $this->identities))->record(1, $ghost);
        self::assertNull($record, 'fiche hors fichiers : rien pour un opérateur');
        self::assertNotNull((new SseFileRecordService($this->records, $this->identities))->record(1, $ghost, ['gm' => true]));
    }

    public function testCrossReferencesFindAFirstNameInAReportAndASharedPhone(): void
    {
        $nikos = $this->person(1, 'Papadakis', 'Nikos');
        $this->records->addLink(1, $nikos, ['kind' => 'phone', 'value' => '+1 555 014 2398']);
        $this->pdo->exec("INSERT INTO atak_tactical_reports (tenant_id, report_type, report_number, summary, created_at) VALUES
            (1, 'SPOTREP', 'SPOTREP-003', 'Contact avec un certain Nikos près du garage, il conduit un pick-up.', '2026-11-10 08:00:00'),
            (1, 'SALUTE', 'SALUTE-004', 'Appel intercepté vers le 555-014-2398 à 09:12.', '2026-11-10 09:00:00'),
            (2, 'SPOTREP', 'SPOTREP-099', 'Nikos vu ici aussi (autre communauté).', '2026-11-10 10:00:00')");
        $notified = [];
        $xref = new SseFileXrefService($this->records, static function (int $t, int $u, array $p) use (&$notified): void {
            $notified[] = [$t, $u, $p];
        });
        self::assertGreaterThanOrEqual(2, $xref->catchUp(1));
        $xref->catchUp(2);
        $all = static fn (string $s): bool => true;

        $found = $xref->findFor(1, $nikos, ['last' => 'Papadakis', 'first' => 'Nikos', 'phones' => ['+1 555 014 2398']], $all);
        $byTitle = [];
        foreach ($found['items'] as $it) {
            $byTitle[$it['title']] = $it;
        }
        self::assertArrayHasKey('SPOTREP SPOTREP-003', $byTitle);
        self::assertSame('possible', $byTitle['SPOTREP SPOTREP-003']['confidence'], 'prénom seul : possible');
        self::assertStringContainsString("\x01Nikos\x02", $byTitle['SPOTREP SPOTREP-003']['snippet']);
        self::assertSame('exact', $byTitle['SALUTE SALUTE-004']['confidence'], 'même téléphone : exact');
        self::assertArrayNotHasKey('SPOTREP SPOTREP-099', $byTitle, 'jamais une source d’une autre communauté');

        $spot = $byTitle['SPOTREP SPOTREP-003'];
        self::assertTrue($xref->decide(1, $nikos, 'tactical', (int) $spot['id'], 'reject', 7));
        $again = $xref->findFor(1, $nikos, ['last' => 'Papadakis', 'first' => 'Nikos'], $all);
        self::assertNotContains((int) $spot['id'], array_map(static fn (array $i): int => (int) $i['id'], $again['items']), '« ce n’est pas lui » ne revient plus');

        $restricted = $xref->findFor(1, $nikos, ['phones' => ['5550142398']], static fn (string $s): bool => $s !== 'interne');
        self::assertSame([], $restricted['items'], 'portée : hors habilitation, rien');

        $xref->watch(1, 9, 'name', ['Nikos']);
        $this->pdo->exec("INSERT INTO sse_field_notes (tenant_id, reference_code, title, body, created_at) VALUES (1, 'FRS-1', 'Marché', 'Nikos a payé en liquide.', '2026-11-11 08:00:00')");
        $xref->catchUp(1);
        self::assertCount(1, $notified, 'la surveillance prévient une fois');
        self::assertSame(9, $notified[0][1]);
    }

    public function testAlreadyCheckedShowsHowLongAgo(): void
    {
        $svc = new SseFileRecordService($this->records, $this->identities, null, null, null, null, null, '2026-11-11 12:00:00');
        $c = $svc->priorContacts([
            ['ref' => 'CTRL-2', 'created_at' => '2026-11-08 11:00:00', 'grid' => '0234 5678', 'controller_callsign' => 'TA1', 'verdict_label' => 'RAS', 'game_time' => ''],
            ['ref' => 'CTRL-1', 'created_at' => '2026-10-01 11:00:00', 'grid' => '', 'controller_callsign' => '', 'verdict_label' => 'Signalé', 'game_time' => ''],
        ], [], []);
        self::assertSame(2, $c['total']);
        self::assertSame('il y a 3 jours', $c['last']['ago']);
        self::assertSame('TA1', $c['last']['who']);
        self::assertSame('il y a 2 h', SseFileRecordService::ago('2026-11-11 10:00:00', '2026-11-11 12:00:00'));
        self::assertSame('hier', SseFileRecordService::ago('2026-11-10 10:00:00', '2026-11-11 12:00:00'));
    }

    public function testPhoneExtractionIngestionIsValidatedAndOwned(): void
    {
        $svc = new SsePhoneExtractionService($this->records, new SseFileXrefService($this->records, static fn () => null));
        $body = [
            'kind' => 'phone_extraction', 'idempotency_key' => 'k-0001', 'extraction_key' => 'x-0001', 'consent' => true,
            'device' => ['uid' => 'EUD-4471', 'label' => 'EUD-4471', 'model' => 'Samsung S7'],
            'owner' => ['kind' => 'ai', 'label' => 'HADDAD Karim'],
            'data' => [
                'contacts' => [['name' => 'ABOU RAMI', 'number' => '+1 555 014 2398']],
                'messages' => [['at' => '08:41Z', 'from' => 'ABOU RAMI', 'to' => 'moi', 'body' => 'Le lot est au garage.']],
                'network' => ['carrier' => 'Verizon', 'wifi' => ['GARAGE-5G']],
            ],
        ];
        self::assertSame('unlinked', $svc->ingest(1, null, $body, 500)['error'] ?? null, 'compte du jeton de jeu obligatoire');
        self::assertSame('consent_required', $svc->ingest(1, 5, ['consent' => false] + $body, 500)['error'] ?? null);
        self::assertSame('too_large', $svc->ingest(1, 5, $body, SsePhoneExtractionService::MAX_BODY_BYTES + 1)['error'] ?? null);
        $tooMany = $body;
        $tooMany['data']['contacts'] = array_fill(0, SsePhoneExtractionService::MAX_PER_CATEGORY + 1, ['name' => 'X', 'number' => '1234567']);
        self::assertSame('too_large', $svc->ingest(1, 5, $tooMany, 500)['error'] ?? null);
        self::assertSame('invalid_device', $svc->ingest(1, 5, ['device' => ['uid' => '../../etc']] + $body, 500)['error'] ?? null);

        $ok = $svc->ingest(1, 5, $body, 500);
        self::assertArrayHasKey('id', $ok);
        self::assertSame('EXT-' . str_pad((string) $ok['id'], 6, '0', STR_PAD_LEFT), $ok['ref']);
        self::assertTrue($svc->ingest(1, 5, $body, 500)['duplicate'] ?? false, 'même partie : doublon');
        self::assertSame('not_owner', $svc->ingest(1, 6, ['idempotency_key' => 'k-0002', 'part' => [2, 2]] + $body, 500)['error'] ?? null, 'un autre compte ne complète pas l’extraction');

        $ext = $svc->find(1, (int) $ok['id'], false);
        self::assertNotNull($ext);
        self::assertSame(1, $ext['counts']['contacts']);
        self::assertSame('Verizon', $ext['network']['carrier']);
        self::assertNull($svc->find(2, (int) $ok['id'], true), 'invisible depuis une autre communauté');
        self::assertCount(1, $svc->listItems(1, (int) $ok['id'], '', 'garage'), 'recherche dans l’extraction');

        $dev = $svc->devices(1, 5)[0];
        self::assertTrue($svc->setConsent(1, 5, (int) $dev['id'], false));
        self::assertSame('consent_revoked', $svc->ingest(1, 5, ['idempotency_key' => 'k-0003', 'extraction_key' => 'x-0003'] + $body, 500)['error'] ?? null);
        self::assertFalse($svc->setConsent(1, 6, (int) $dev['id'], true), 'l’accord d’un autre membre ne se change pas');
    }

    public function testLorePushFillsOnlyEmptyFieldsAndPullHidesTheGmData(): void
    {
        $hidden = false;
        $svc = new SseLoreService(null, $this->records, $this->identities, null, static function (int $t, int $u) use (&$hidden): bool {
            return $hidden;
        });
        $person = ['uid' => 'VAR-altis-boss', 'last' => 'Walker', 'first' => 'Daniel', 'sex' => 'M', 'dob' => '1980-05-02', 'country' => 'USA',
            'aliases' => ['Danny'], 'height_cm' => 183, 'phones' => ['+1 555 010 0001'], 'knowledge' => 'Connaît le dépôt.',
            'answers' => ['answers' => ['Je livre des pièces.'], 'evoke' => 'auto', 'banned' => ['armed']], 'status' => ['wanted' => true]];
        $push = $svc->push(1, 1, ['op' => 'OP-2611', 'mission' => ['title' => 'Silver Lake'], 'persons' => [$person]], null);
        self::assertSame('ok;1;0;0;0', $push['ref']);
        $iid = $this->records->identityIdByAiUid(1, 'VAR-altis-boss');
        self::assertNotNull($iid);

        $this->identities->update((int) $iid, 1, ['profession' => 'Mécanicien'] + (array) $this->identities->find((int) $iid, 1));
        $again = $svc->push(1, 1, ['op' => 'OP-2611', 'persons' => [['uid' => 'VAR-altis-boss', 'last' => 'Autre', 'profession' => 'Chauffeur']]], null);
        self::assertSame('ok;0;1;0;0', $again['ref']);
        $i = $this->identities->find((int) $iid, 1);
        self::assertSame('Walker', $i['last_name'], 'le web n’est jamais écrasé par le jeu');
        self::assertSame('Mécanicien', $i['profession']);

        $this->records->saveProfile(1, (int) $iid, ['visibility' => ['physical' => ['v' => 'hidden', 'phase' => 0]]], null);
        $pull = $svc->pull(1, ['op' => 'OP-2611'], 42);
        self::assertStringContainsString('"Walker"', $pull['ref']);
        self::assertStringContainsString('"Je livre des pièces."', $pull['ref'], 'réponses de l’IA servies au jeu');
        self::assertStringNotContainsString('Connaît le dépôt', $pull['ref'], 'caché MJ : pas pour un compte sans le droit');
        self::assertStringNotContainsString('183', $pull['ref'], 'signalement caché');
        self::assertLessThanOrEqual(SseLoreService::PAGE_BYTES, strlen($pull['ref']));
        $hidden = true;
        self::assertStringContainsString('Connaît le dépôt', $svc->pull(1, ['op' => 'OP-2611'], 42)['ref']);

        self::assertSame('unknown_session', $svc->pull(2, ['op' => 'OP-2611'], null)['error'] ?? null, 'une communauté ne lit pas la session d’une autre');
        self::assertSame('unknown_session', $svc->pull(1, ['op' => 'OP-OTHER'], null)['error'] ?? null);

        $lore = new SseLoreRepository();
        $session = $lore->findByOp(1, 'OP-2611');
        $lore->setLocked(1, (int) $session['id'], true, 1);
        self::assertSame('ok;0;0;1;1', $svc->push(1, 1, ['op' => 'OP-2611', 'persons' => [['uid' => 'NEW-1', 'last' => 'Nouveau']]], null)['ref'], 'session verrouillée : rien ne change');
        self::assertNull($this->records->identityIdByAiUid(1, 'NEW-1'));
        self::assertSame('invalid_op', $svc->push(1, 1, ['op' => 'x y', 'persons' => [$person]], null)['error'] ?? null);
        self::assertSame('too_large', $svc->push(1, 1, ['op' => 'OP-2', 'persons' => array_fill(0, SseLoreService::MAX_BATCH + 1, $person)], null)['error'] ?? null);
    }

    public function testLoreImportExportRoundTrip(): void
    {
        $svc = new SseLoreService(null, $this->records, $this->identities, null, static fn (): bool => false);
        $lore = $svc->lore();
        $sid = $lore->create(1, ['op_key' => 'OP-CSV', 'title' => 'Import'], 1);
        $csv = "uid;last;first;sex;dob;country;aliases;height_cm\nweb-a1;Müller;Thomas;M;1985-02-03;DEU;Tom|Der Lange;190\n";
        $res = $svc->import(1, $sid, $csv, 'csv', 1);
        self::assertSame(1, $res['created']);
        $export = $svc->export(1, $sid);
        self::assertSame('Müller', $export['persons'][0]['last']);
        self::assertSame(['Tom', 'Der Lange'], $export['persons'][0]['aliases']);
        self::assertStringContainsString('Müller', $svc->exportCsv(1, $sid));
        $hits = $this->search(1, ['q' => 'muller']);
        self::assertCount(1, $hits['hits'], 'sans accent : Muller trouve Müller');
    }
}
