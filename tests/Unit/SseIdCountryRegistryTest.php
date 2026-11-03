<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseIdentityRegistryController;
use App\Repositories\SseIdCountryRepository;
use App\Repositories\SseIdDocumentRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseIdKitRepository;
use App\Repositories\SsePersonRepository;
use App\Repositories\SseWatchlistRepository;
use App\Services\Sse\MemberIdentityPapersService;
use App\Services\Sse\SseIdentityGeneratorService;
use App\Services\Sse\SseIdentityPackService;
use App\Support\SseIdCountryCatalog;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Pays émetteurs créés par la communauté : codes réels ou réservés refusés, format du numéro, création,
 * archivage (jamais de suppression), présence dans les formulaires et dans le pack du serveur de jeu.
 */
final class SseIdCountryRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        SseIdentitySqlite::open();
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    /** @return array<string, mixed> */
    private static function input(array $over = []): array
    {
        return $over + [
            'code' => 'krv', 'name' => 'République de Karvonie', 'name_en' => 'Republic of Karvonia', 'mrz_code' => '',
            'number_format' => 'kv@#####', 'authority' => 'Préfecture de Karvo', 'region' => 'LIVONIA', 'theme' => 'contemporary',
            'fictional' => true, 'languages' => ['fr', 'ru', 'xx'], 'doc_types' => ['id_card', 'passport'],
            'validity' => ['id_card' => 12, 'passport' => 7], 'color_primary' => '#1f4e79', 'color_secondary' => 'rouge',
        ];
    }

    private static function validate(array $input, ?array $existing = null): array
    {
        return SseIdentityRegistryController::validateCountry($input, $existing, static fn (string $c): bool => (new SseIdCountryRepository())->findByCode(1, $c) !== null);
    }

    public function testRealOrReservedCodesAreRefusedUnlessFictionalUniverse(): void
    {
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('FRA', 'contemporary'));
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('USA', 'historical'));
        self::assertSame('', SseIdCountryCatalog::codeProblem('FRA', 'star_wars'), 'univers imaginaire : pas de confusion possible');
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('XXA', 'scp'), 'code OACI réservé : toujours refusé');
        self::assertNotSame('', SseIdCountryCatalog::codeProblem('K1', 'contemporary'));
        foreach (SseIdCountryRepository::DEFAULTS as $d) {
            self::assertSame('', SseIdCountryCatalog::codeProblem($d['code'], 'contemporary'), $d['code']);
        }

        $res = self::validate(self::input(['code' => 'DEU']));
        self::assertCount(1, $res['errors']);
        self::assertStringContainsString('pays réel', $res['errors'][0]);
        self::assertSame([], self::validate(self::input(['code' => 'DEU', 'theme' => 'warhammer']))['errors']);
        self::assertNotSame([], self::validate(self::input(['code' => 'ALT']))['errors'], 'code déjà pris dans la communauté');
        self::assertNotSame([], self::validate(self::input(['fictional' => false]))['errors'], 'marquage fictif obligatoire');
        self::assertNotSame([], self::validate(self::input(['mrz_code' => 'RUS']))['errors'], 'code de bande réel');
    }

    public function testNumberFormatIsValidatedAndPreviewed(): void
    {
        self::assertSame('', SseIdCountryCatalog::formatProblem('KV@#####'));
        self::assertNotSame('', SseIdCountryCatalog::formatProblem('AB-123'), 'signe non imprimable');
        self::assertNotSame('', SseIdCountryCatalog::formatProblem('ABCD##'), 'trop peu de signes tirés');
        self::assertNotSame('', SseIdCountryCatalog::formatProblem(str_repeat('#', 21)));
        $n = SseIdentityGeneratorService::documentNumber('KV@#####', 424242);
        self::assertTrue(SseIdentityGeneratorService::matchesFormat($n, 'KV@#####'));
        self::assertNotSame([], self::validate(self::input(['number_format' => '##']))['errors']);
        self::assertNotSame([], self::validate(self::input(['validity' => ['id_card' => 90, 'passport' => 7]]))['errors']);
    }

    public function testCreatedCountryIsOfferedInFormsAndSentToTheGame(): void
    {
        $res = self::validate(self::input());
        self::assertSame([], $res['errors']);
        self::assertSame(['KRV', 'KRV', 'KV@#####', ['fr', 'ru'], '#1F4E79', null], [
            $res['data']['code'], $res['data']['mrz_code'], $res['data']['number_format'], $res['data']['languages'],
            $res['data']['color_primary'], $res['data']['color_secondary'],
        ]);
        $repo = new SseIdCountryRepository();
        $id = $repo->create(1, $res['data'], 5);
        $c = $repo->find($id, 1);
        self::assertSame(['id_card', 'passport'], $c['doc_types']);
        self::assertSame(12, SseIdCountryRepository::validityFor($c, 'id_card'));
        self::assertSame(7, SseIdCountryRepository::validityFor($c, 'passport'));
        self::assertTrue($c['fictional']);
        self::assertSame('contemporary', $c['theme']);
        self::assertContains('KRV', array_column($repo->listForTenant(1), 'code'));
        self::assertNotContains('KRV', array_column($repo->listForTenant(2), 'code'), 'cloison des communautés');
        self::assertSame(['KRV'], array_column($repo->search(1, 'karvo'), 'code'));

        // « Générer » : numéro au format du pays, durée selon le type.
        $s = new MemberIdentityPapersService(null, null, null, null, null, static function (): void {
        });
        $identity = (new SseIdentityRepository())->find((new SseIdentityRepository())->create(1, ['last_name' => 'Volkov', 'first_name' => 'Ivan', 'nationality' => 'KRV', 'user_id' => 3]), 1);
        $prop = $s->proposal(1, $identity, $c, 'passport', 99);
        self::assertTrue(SseIdentityGeneratorService::matchesFormat($prop['doc_number'], 'KV@#####'));
        self::assertSame(gmdate('Y-m-d', (int) strtotime('+7 years')), $prop['expires_on']);
        self::assertFalse($s->saveDocument(1, 3, $identity, null, ['doc_type' => 'driving_license', 'country_code' => 'KRV'], false, 1)['ok'], 'type non délivré');
        $docId = $s->saveDocument(1, 3, $identity, null, ['doc_type' => 'passport', 'country_code' => 'KRV'], false, 1)['id'];

        $pack = $this->pack();
        $krv = array_values(array_filter($pack['countries'], static fn (array $x): bool => $x['code'] === 'KRV'))[0];
        self::assertSame(['KRV', 'République de Karvonie', 'KV@#####', ['fr', 'ru'], ['id_card', 'passport'], ['#1F4E79'], true], [
            $krv['code'], $krv['name'], $krv['number_format'], $krv['languages'], $krv['doc_types'], $krv['colors'], $krv['active'],
        ]);

        // Utilisé par un papier : archivé (plus proposé), jamais supprimé ; toujours dans le pack.
        self::assertSame(1, (new SseIdDocumentRepository())->countForCountry(1, 'KRV'));
        self::assertTrue($repo->setArchived($id, 1, true));
        self::assertNotContains('KRV', array_column($repo->listForTenant(1), 'code'));
        self::assertFalse($s->saveDocument(1, 3, $identity, null, ['doc_type' => 'passport', 'country_code' => 'KRV'], false, 1)['ok'], 'pays archivé');
        self::assertNotNull((new SseIdDocumentRepository())->find($docId, 1));
        $krv = array_values(array_filter($this->pack()['countries'], static fn (array $x): bool => $x['code'] === 'KRV'))[0];
        self::assertFalse($krv['active']);
        // Le code ne change pas à la modification.
        $upd = self::validate(self::input(['code' => 'ZZZ', 'name' => 'Karvonie']), $c);
        self::assertSame('KRV', $upd['data']['code']);
        self::assertTrue($repo->update($id, 1, $upd['data']));
        self::assertSame('Karvonie', $repo->find($id, 1)['name']);
    }

    private function pack(): array
    {
        $watch = $this->createMock(SseWatchlistRepository::class);
        $watch->method('listActive')->willReturn([]);
        $persons = $this->createMock(SsePersonRepository::class);
        $persons->method('listForContext')->willReturn([]);

        return (new SseIdentityPackService(new SseIdDocumentRepository(), new SseIdentityRepository(), new SseIdCountryRepository(), $watch, $persons, new SseIdKitRepository()))->build(1, 1);
    }
}
