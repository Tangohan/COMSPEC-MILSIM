<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Services\Sse\SseIdMassGeneratorService;
use App\Services\Sse\SseIdTransferService;
use App\Services\Sse\SseIdWorkshopService;
use App\Services\Sse\SseMrzService;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Atelier de documents (COMSPEC IDENTITY, Lot 6) : création d'un papier avec son identité, modèle d'impression et
 * champs imprimés, seul un brouillon se modifie, copie d'un papier, MRZ calculée en direct, vérité réservée à
 * l'habilitation Confidentiel, import / export CSV et JSON, cloison des communautés.
 */
final class SseIdWorkshopServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        SseIdMassGeneratorService::useAuditSink(static function (array $e): void {
        });
    }

    protected function tearDown(): void
    {
        SseIdMassGeneratorService::useAuditSink(null);
        SseIdentitySqlite::close();
    }

    /** @return array<string, mixed> */
    private function input(array $over = []): array
    {
        return $over + [
            'last_name' => 'Papadakis', 'first_name' => 'Eleni', 'sex' => 'F', 'birth_date' => '1990-04-12', 'birth_place' => 'Kavala',
            'nationality' => 'ALT', 'profession' => 'Enseignante', 'kind' => 'civil', 'doc_type' => 'passport', 'country_code' => 'ALT',
            'doc_number' => 'ALK123456', 'issued_on' => '2024-01-01', 'expires_on' => '2034-01-01', 'authority' => '', 'layout' => 'classic',
            'print' => ['height' => '1,68 m', 'signature' => 'E. Papadakis', 'bogus' => 'x'], 'st_report' => 'none',
        ];
    }

    public function testCreatesAPaperWithItsIdentityLayoutAndPrintedFields(): void
    {
        $svc = new SseIdWorkshopService();
        $res = $svc->save(1, $this->input(['st_origin' => 'forged']), null, null, false, 3);
        self::assertTrue($res['ok']);
        $doc = (new SseIdDocumentRepository())->find((int) $res['id'], 1);
        self::assertNotNull($doc);
        self::assertSame('draft', $doc['workflow']);
        self::assertSame('classic', $doc['layout']);
        self::assertSame(['height' => '1,68 m', 'signature' => 'E. Papadakis'], $doc['print']);
        self::assertSame('Préfecture de Kavala', $doc['authority'], 'autorité du pays par défaut');
        self::assertSame('official', $doc['st_origin'], 'sans habilitation Confidentiel, pas de faux');
        self::assertNull((new SseIdDocumentRepository())->find((int) $res['id'], 2));

        // Format du pays, unicité du numéro, pays fictif.
        self::assertFalse($svc->save(1, $this->input(['doc_number' => '12']), null, null, true, 3)['ok']);
        self::assertFalse($svc->save(1, $this->input(), null, null, true, 3)['ok'], 'numéro déjà attribué');
        self::assertFalse($svc->save(1, $this->input(['country_code' => 'FRA', 'doc_number' => '']), null, null, true, 3)['ok']);
        $forged = $svc->save(1, $this->input(['doc_number' => 'ALK654321', 'st_origin' => 'forged', 'forgery_kind' => 'photo_swap', 'forgery_quality' => '70']), null, null, true, 3);
        $f = (new SseIdDocumentRepository())->find((int) $forged['id'], 1);
        self::assertSame('forged', $f['st_origin']);
        self::assertSame(70, $f['forgery_quality']);
    }

    public function testOnlyDraftsAreEditableAndDuplicationLinksTheCopy(): void
    {
        $svc = new SseIdWorkshopService();
        $docs = new SseIdDocumentRepository();
        $id = (int) $svc->save(1, $this->input(), null, null, false, 3)['id'];
        $doc = $docs->find($id, 1);
        $identity = (new SseIdentityRepository())->find((int) $doc['identity_id'], 1);
        $edit = $svc->save(1, $this->input(['last_name' => 'Georgiou', 'layout' => 'compact', 'print' => ['eyes' => 'Marron']]), $identity, $doc, false, 3);
        self::assertTrue($edit['ok']);
        self::assertSame('compact', $docs->find($id, 1)['layout']);
        self::assertSame('Georgiou', (new SseIdentityRepository())->find((int) $doc['identity_id'], 1)['last_name']);

        self::assertTrue($docs->transition($id, 1, 'validated', 3));
        self::assertTrue($docs->transition($id, 1, 'issued', 3));
        $issued = $docs->find($id, 1);
        self::assertFalse($svc->save(1, $this->input(), $identity, $issued, false, 3)['ok']);
        self::assertTrue($svc->identityLocked(1, (int) $issued['identity_id']));

        self::assertTrue($docs->transition($id, 1, 'revoked', 3, 'Perdu en mission'));
        $copyId = $svc->duplicate(1, $docs->find($id, 1), 3);
        $copy = $docs->find((int) $copyId, 1);
        self::assertSame('draft', $copy['workflow']);
        self::assertSame($id, $copy['duplicated_from_id']);
        self::assertNotSame($issued['doc_number'], $copy['doc_number']);
        self::assertSame('compact', $copy['layout']);
        self::assertSame(['eyes' => 'Marron'], $copy['print']);
        self::assertSame([(int) $copyId], $docs->duplicatesOf($id, 1));
    }

    public function testMrzIsComputedLiveAndMatchesTheRegistry(): void
    {
        $svc = new SseIdWorkshopService();
        $mrz = $svc->mrzPreview(1, ['doc_type' => 'passport', 'country' => 'ALT', 'number' => 'alk123456', 'last' => 'Papadakis', 'first' => 'Eleni', 'dob' => '1990-04-12', 'sex' => 'F', 'nat' => 'ALT', 'expires' => '2034-01-01']);
        self::assertSame('TD3', $mrz['format']);
        self::assertTrue($mrz['valid']);
        self::assertStringStartsWith('P<ALTPAPADAKIS<<ELENI', $mrz['lines'][0]);
        $id = (int) $svc->save(1, $this->input(), null, null, false, 3)['id'];
        $doc = (new SseIdDocumentRepository())->find($id, 1);
        $identity = (new SseIdentityRepository())->find((int) $doc['identity_id'], 1);
        self::assertSame(SseMrzService::forDocument($doc, $identity, 'ALT'), $mrz['lines']);
        $card = $svc->mrzPreview(1, ['doc_type' => 'id_card', 'country' => 'ALT', 'number' => 'ALK123456', 'last' => 'X', 'first' => 'Y']);
        self::assertSame('TD1', $card['format']);
        self::assertCount(3, $card['lines']);

        $model = SseIdWorkshopService::previewModel($doc, $identity, null);
        self::assertSame('passport', $model['doc_type']);
        self::assertSame('classic', $model['layout']);
        self::assertCount(2, $model['country']['colors']);
        self::assertNull($model['identity']['photo_url']);
    }

    public function testImportAndExportKeepTheTruthBehindTheClearance(): void
    {
        $svc = new SseIdWorkshopService();
        $svc->save(1, $this->input(['kind' => 'cover', 'doc_number' => 'ALK000111']), null, null, true, 3);
        $svc->save(1, $this->input(['last_name' => 'Kyriakou', 'doc_number' => 'ALK000222', 'doc_type' => 'id_card']), null, null, true, 3);
        $transfer = new SseIdTransferService();
        $all = $transfer->rows(1, true);
        self::assertCount(2, $all);
        $public = $transfer->rows(1, false);
        self::assertCount(1, $public, 'la couverture reste cachée sans habilitation Confidentiel');
        self::assertSame('', $public[0]['kind']);
        self::assertSame('', $public[0]['origin']);
        self::assertSame([], $transfer->rows(2, true));

        $csv = SseIdTransferService::toCsv($all);
        self::assertStringStartsWith("\u{FEFF}identity_ref;kind;", $csv);
        $rows = SseIdTransferService::parse($csv, 'export.csv');
        self::assertCount(2, $rows);
        // Import dans l'autre communauté : tout arrive en brouillon, la couverture devient civile sans habilitation.
        $res = $transfer->import(2, $rows, false, 9);
        self::assertSame(2, $res['identities']);
        self::assertSame(2, $res['documents']);
        $kinds = $this->pdo->query('SELECT kind FROM sse_identities WHERE tenant_id = 2')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['civil', 'civil'], $kinds);
        self::assertSame(['draft'], array_values(array_unique($this->pdo->query('SELECT workflow FROM sse_id_documents WHERE tenant_id = 2')->fetchAll(PDO::FETCH_COLUMN))));
        // Réimport : numéros déjà attribués, lignes écartées.
        $again = $transfer->import(2, $rows, false, 9);
        self::assertSame(0, $again['documents']);
        self::assertSame(2, $again['skipped']);

        $json = SseIdTransferService::toJson($all);
        self::assertTrue(json_decode($json, true)['simulation']);
        self::assertCount(2, (array) SseIdTransferService::parse($json, 'x.json'));
        self::assertNull(SseIdTransferService::parse('{oops', 'x.json'));
    }
}
