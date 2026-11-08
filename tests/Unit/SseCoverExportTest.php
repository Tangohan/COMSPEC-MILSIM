<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseCoverExportRepository;
use App\Repositories\SseIdCountryRepository;
use App\Services\Sse\SseCoverExportService;
use App\Services\Sse\SseCoverGeneratorService;
use App\Services\Sse\SseIdentityGeneratorService;
use PHPUnit\Framework\TestCase;
use Tests\Support\SseIdentitySqlite;

/**
 * Couverture générée sur Athena puis téléchargée : cohérence, unicité, aller-retour fichier, fichiers malformés refusés,
 * limite de générations, isolation par communauté et par membre.
 */
final class SseCoverExportTest extends TestCase
{
    private \PDO $pdo;
    private \DateTimeImmutable $now;
    /** @var list<array<string, mixed>> */
    private array $audit = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = SseIdentitySqlite::open();
        (require dirname(__DIR__, 2) . '/bootstrap/sse_cover_exports_migration.php')($this->pdo);
        $this->now = new \DateTimeImmutable('2026-11-09 12:00:00', new \DateTimeZone('UTC'));
        $this->audit = [];
    }

    protected function tearDown(): void
    {
        SseIdentitySqlite::close();
    }

    private function service(): SseCoverExportService
    {
        return new SseCoverExportService(null, null, null, function (array $e): void {
            $this->audit[] = $e;
        }, 'test-key');
    }

    /** @return array<string, mixed> */
    private function country(string $code = 'MLD'): array
    {
        $found = (new SseIdCountryRepository())->findByCode(1, $code);
        self::assertNotNull($found, 'pays de test ' . $code);

        return $found;
    }

    public function testCoverIsCompleteAndCoherent(): void
    {
        $country = $this->country();
        $gen = new SseCoverGeneratorService();
        $c = $gen->generate($country, 123456, 'F', 'fr', $this->now);

        foreach (['name', 'dob', 'pob', 'address', 'city', 'profession', 'employer', 'phone', 'travel', 'family'] as $k) {
            self::assertNotSame('', trim((string) $c[$k]), $k);
        }
        self::assertSame($c['first_name'] . ' ' . $c['last_name'], $c['name']);
        self::assertMatchesRegularExpression('/^555-\d{4}$/', $c['phone']);
        self::assertStringContainsString($c['city'], $c['address']);
        // Âge adulte.
        $age = (int) (new \DateTimeImmutable($c['dob']))->diff($this->now)->y;
        self::assertGreaterThanOrEqual(18, $age);
        // Le papier suit le format du pays et sa durée de validité.
        $doc = $c['docs'][0];
        self::assertTrue(SseIdentityGeneratorService::matchesFormat($doc['number'], (string) $country['number_format']));
        self::assertGreaterThan($doc['issued'], $doc['expires']);
        self::assertLessThanOrEqual($this->now->format('Y-m-d'), $doc['issued']);
        // Qui peut confirmer : trois proches ou collègues avec un téléphone fictif.
        self::assertCount(3, $c['contacts']);
        foreach ($c['contacts'] as $contact) {
            self::assertNotSame('', $contact[0]);
            self::assertMatchesRegularExpression('/^555-\d{4}$/', $contact[2]);
        }
        // Le proche cité dans la famille est un des contacts.
        $names = array_column($c['contacts'], 0);
        self::assertTrue(count(array_filter($names, static fn (string $n): bool => str_contains($c['family'], $n))) >= 1);
        // L'employeur suit le métier : un boulanger travaille dans une boulangerie.
        $baker = null;
        for ($s = 1; $s < 400 && $baker === null; $s++) {
            $x = $gen->generate($country, $s, null, 'fr', $this->now);
            $baker = $x['profession'] === 'Boulanger' ? $x : null;
        }
        self::assertNotNull($baker);
        self::assertStringStartsWith('Boulangerie', $baker['employer']);
    }

    public function testSameSeedSameCoverAndEnglishTexts(): void
    {
        $gen = new SseCoverGeneratorService();
        $country = $this->country();
        self::assertSame($gen->generate($country, 99, 'M', 'fr', $this->now), $gen->generate($country, 99, 'M', 'fr', $this->now));
        $fr = $gen->generate($country, 99, 'M', 'fr', $this->now);
        $en = $gen->generate($country, 99, 'M', 'en', $this->now);
        self::assertSame($fr['name'], $en['name']);
        self::assertSame($fr['docs'][0]['number'], $en['docs'][0]['number']);
        self::assertNotSame($fr['travel'], $en['travel']);
        self::assertSame('en', $en['lang']);
    }

    public function testUniqueAvoidsTakenNamesAndNumbers(): void
    {
        $gen = new SseCoverGeneratorService();
        $country = $this->country();
        $first = $gen->generate($country, 5000, null, 'fr', $this->now);
        $taken = [mb_strtolower($first['name']) => true];
        $numbers = [strtoupper($first['docs'][0]['number']) => true];
        $next = $gen->generateUnique($country, $taken, $numbers, 5000, null, 'fr', 12, $this->now);
        self::assertNotNull($next);
        self::assertNotSame($first['name'], $next['name']);
        self::assertNotSame($first['docs'][0]['number'], $next['docs'][0]['number']);

        // Tout est pris : aucune couverture, pas de doublon silencieux.
        $all = [];
        for ($i = 0; $i < 12; $i++) {
            $all[mb_strtolower($gen->generate($country, (5000 + $i * 7919) % 16777216, null, 'fr', $this->now)['name'])] = true;
        }
        self::assertNull($gen->generateUnique($country, $all, [], 5000, null, 'fr', 12, $this->now));
    }

    public function testGenerateStoresHistoryAndRoundTrips(): void
    {
        $svc = $this->service();
        $res = $svc->generate(1, 10, 10, 'MLD', 'F', 'fr', null, false, $this->now);
        self::assertTrue($res['ok']);
        $dl = $svc->download(1, $res['id'], 10, false, $this->now);
        self::assertNotNull($dl);

        $parsed = SseCoverExportService::parse($dl['json'], $this->now);
        self::assertTrue($parsed['ok'], (string) ($parsed['error'] ?? ''));
        self::assertSame($res['name'], $parsed['cover']['name']);
        self::assertSame('F', $parsed['cover']['sex']);
        self::assertSame($res['ref'], $parsed['file']['ref']);
        self::assertTrue($svc->verifySignature($parsed['file']));
        self::assertSame($svc->accountRef(1, 10), $parsed['file']['account_ref']);
        // Le fichier ne contient ni identifiant de compte ni de communauté.
        self::assertStringNotContainsString('tenant', $dl['json']);
        self::assertStringNotContainsString('user_id', $dl['json']);

        $history = (new SseCoverExportRepository())->listForUser(1, 10);
        self::assertCount(1, $history);
        self::assertNotEmpty($this->audit);
        // Re-téléchargement : même fichier, même signature.
        self::assertSame($dl['json'], $svc->download(1, $res['id'], 10, false, $this->now)['json']);
    }

    public function testPhotoIsEmbeddedAndChecked(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD requis');
        }
        $img = imagecreatetruecolor(300, 400);
        imagefilledrectangle($img, 0, 0, 300, 400, 0x336699);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        $res = $this->service()->generate(1, 10, 10, 'MLD', null, 'fr', $png, false, $this->now);
        self::assertTrue($res['ok']);
        $json = $this->service()->download(1, $res['id'], 10, false, $this->now)['json'];
        $parsed = SseCoverExportService::parse($json, $this->now);
        self::assertTrue($parsed['ok']);
        self::assertSame('image/jpeg', $parsed['cover']['photo']['mime']);
        self::assertStringStartsWith("\xFF\xD8\xFF", base64_decode($parsed['cover']['photo']['b64'], true));

        // Fichier qui n'est pas une image : refusé, rien n'est enregistré.
        $bad = $this->service()->generate(1, 11, 11, 'MLD', null, 'fr', '<?php echo 1;', false, $this->now);
        self::assertSame(['ok' => false, 'error' => 'photo'], $bad);
        self::assertSame([], (new SseCoverExportRepository())->listForUser(1, 11));
    }

    public function testLimitPerPersonAndPerDay(): void
    {
        $svc = $this->service();
        for ($i = 0; $i < SseCoverExportService::LIMIT_SELF; $i++) {
            self::assertTrue($svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now)['ok']);
        }
        self::assertSame(['ok' => false, 'error' => 'limit'], $svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now));
        // Un manager qui génère pour un autre a sa propre limite, plus large.
        self::assertTrue($svc->generate(1, 10, 99, 'MLD', null, 'fr', null, true, $this->now)['ok']);
        // Un autre membre n'est pas bloqué par la limite de celui-ci.
        self::assertTrue($svc->generate(1, 12, 12, 'MLD', null, 'fr', null, false, $this->now)['ok']);
        // Vingt-quatre heures plus tard, c'est de nouveau possible.
        self::assertTrue($svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now->modify('+25 hours'))['ok']);
        self::assertSame(0, $svc->panel(1, 12, false, true)['remaining'] - 2, 'panneau : 2 restantes pour un membre qui en a utilisé une');
    }

    public function testDownloadIsolation(): void
    {
        $svc = $this->service();
        $res = $svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now);
        self::assertNotNull($svc->download(1, $res['id'], 10, false, $this->now));
        // Un autre joueur ne lit pas la couverture d'autrui.
        self::assertNull($svc->download(1, $res['id'], 11, false, $this->now));
        // Un autre tenant non plus, même gestionnaire.
        self::assertNull($svc->download(2, $res['id'], 20, true, $this->now));
        // Un gestionnaire de la communauté la lit, mais pas à travers l'adresse d'un autre membre.
        self::assertNotNull($svc->download(1, $res['id'], 50, true, $this->now, 10));
        self::assertNull($svc->download(1, $res['id'], 50, true, $this->now, 11));
        self::assertNull($svc->download(1, 9999, 10, false, $this->now));
    }

    public function testUnknownOrArchivedCountryIsRefused(): void
    {
        self::assertSame(['ok' => false, 'error' => 'country'], $this->service()->generate(1, 10, 10, 'ZZZ', null, 'fr', null, false, $this->now));
    }

    /** @return array<string, array{0: callable(string): string, 1: string}> */
    public static function malformed(): array
    {
        $swap = static fn (string $from, string $to): callable => static fn (string $j): string => str_replace($from, $to, $j);

        return [
            'vide' => [static fn (string $j): string => '', 'size'],
            'trop gros' => [static fn (string $j): string => str_repeat(' ', SseCoverExportService::MAX_BYTES) . $j, 'size'],
            'pas du json' => [static fn (string $j): string => '{"format":', 'json'],
            'liste' => [static fn (string $j): string => '[1,2]', 'keys'],
            'clé inconnue en tête' => [$swap('{"format"', '{"exec":"calc","format"'), 'keys'],
            'autre format' => [$swap('comspec-cover', 'autre'), 'format'],
            'version future' => [$swap('"version":1', '"version":2'), 'version'],
            'référence abîmée' => [$swap('"ref":"CVR-', '"ref":"XXX-'), 'header'],
            'signature courte' => [static fn (string $j): string => (string) preg_replace('/"sig":"[0-9a-f]{64}"/', '"sig":"abc"', $j), 'header'],
            'nom retouché' => [static fn (string $j): string => (string) preg_replace('/("name":")[^"]+/', '${1}Pirate', $j, 1), 'hash'],
            'clé inconnue dans la couverture' => [$swap('"cover":{', '"cover":{"script":"x",'), 'cover'],
            'octets non UTF-8' => [static fn (string $j): string => $j . "\xff", 'json'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformed')]
    public function testMalformedFilesAreRejected(callable $break, string $error): void
    {
        $svc = $this->service();
        $res = $svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now);
        $json = $svc->download(1, $res['id'], 10, false, $this->now)['json'];
        self::assertTrue(SseCoverExportService::parse($json, $this->now)['ok']);
        $out = SseCoverExportService::parse($break($json), $this->now);
        self::assertFalse($out['ok']);
        self::assertSame($error, $out['error']);
    }

    public function testExpiredAndBadContentAreRejected(): void
    {
        $svc = $this->service();
        $res = $svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now);
        $json = $svc->download(1, $res['id'], 10, false, $this->now)['json'];
        $late = $this->now->modify('+' . (SseCoverExportService::VALID_DAYS + 1) . ' days');
        self::assertSame('expired', SseCoverExportService::parse($json, $late)['error']);

        // Fichiers refabriqués avec une empreinte juste mais un contenu invalide : refus sur le contenu.
        $make = function (array $cover) use ($svc): string {
            return $svc->buildFile($cover, 1, 10, 'CVR-AABBCCDD', $this->now)['json'];
        };
        $good = (new SseCoverGeneratorService())->generate($this->country(), 7, null, 'fr', $this->now) + ['photo' => null];
        self::assertTrue(SseCoverExportService::parse($make($good), $this->now)['ok']);
        $cases = [
            'contrôle' => ['name' => "Jean\x07 Dupont"] + $good,
            'nom vide' => ['name' => ''] + $good,
            'nom trop long' => ['name' => str_repeat('a', 161)] + $good,
            'date' => ['dob' => '01/02/1990'] + $good,
            'sexe' => ['sex' => 'Z'] + $good,
            'type de papier' => ['docs' => [['type' => 'badge', 'country' => 'X', 'number' => '1', 'issued' => '2026-01-01', 'expires' => '2030-01-01']]] + $good,
            'champ de papier inconnu' => ['docs' => [['type' => 'id_card', 'country' => 'X', 'number' => '1', 'issued' => '2026-01-01', 'expires' => '2030-01-01', 'cmd' => 'x']]] + $good,
            'trop de contacts' => ['contacts' => array_fill(0, 11, ['A', 'B', '555-0000'])] + $good,
            'contact mal formé' => ['contacts' => [['A', 'B']]] + $good,
            'photo non JPEG' => ['photo' => ['mime' => 'image/jpeg', 'b64' => base64_encode('GIF89a-pas-un-jpeg')]] + $good,
            'photo mime' => ['photo' => ['mime' => 'image/svg+xml', 'b64' => base64_encode("\xFF\xD8\xFF")]] + $good,
            'photo trop lourde' => ['photo' => ['mime' => 'image/jpeg', 'b64' => str_repeat('A', SseCoverExportService::MAX_PHOTO_B64 + 4)]] + $good,
        ];
        foreach ($cases as $label => $cover) {
            $out = SseCoverExportService::parse($make($cover), $this->now);
            self::assertFalse($out['ok'], $label);
            self::assertSame('cover', $out['error'], $label);
        }
    }

    public function testHtmlInTextsIsKeptAsDataNotInterpreted(): void
    {
        // Les textes restent des données : le fichier est accepté, c'est le mod qui échappe l'affichage.
        $good = (new SseCoverGeneratorService())->generate($this->country(), 8, null, 'fr', $this->now) + ['photo' => null];
        $good['employer'] = '<b>Garage</b> & fils';
        $json = $this->service()->buildFile($good, 1, 10, 'CVR-AABBCCDD', $this->now)['json'];
        $parsed = SseCoverExportService::parse($json, $this->now);
        self::assertTrue($parsed['ok']);
        self::assertSame('<b>Garage</b> & fils', $parsed['cover']['employer']);
    }

    public function testSignatureDetectsTampering(): void
    {
        $svc = $this->service();
        $res = $svc->generate(1, 10, 10, 'MLD', null, 'fr', null, false, $this->now);
        $file = SseCoverExportService::parse($svc->download(1, $res['id'], 10, false, $this->now)['json'], $this->now)['file'];
        self::assertTrue($svc->verifySignature($file));
        $file['account_ref'] = str_repeat('0', 16);
        self::assertFalse($svc->verifySignature($file));
        $other = new SseCoverExportService(null, null, null, static function (array $e): void {
        }, 'autre-cle');
        $file['account_ref'] = $svc->accountRef(1, 10);
        self::assertFalse($other->verifySignature($file));
    }

    public function testMigrationIsIdempotentAndRegistered(): void
    {
        $migrate = require dirname(__DIR__, 2) . '/bootstrap/sse_cover_exports_migration.php';
        $migrate($this->pdo);
        $migrate($this->pdo);
        $registry = (string) file_get_contents(dirname(__DIR__, 2) . '/database/steps/registry.php');
        self::assertMatchesRegularExpression("/'id' => 'sse_cover_exports'.*4220_sse_cover_exports\\.php.*'rerun' => true/", $registry);
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/4220_sse_cover_exports.php');
    }
}
