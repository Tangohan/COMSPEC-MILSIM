<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseRecordsGenerator;
use App\Support\SseIdRealCountryCatalog;
use App\Support\SseMeasure;
use App\Support\SseRecordsCountryPacks;
use App\Support\SseRecordsFormat;
use PHPUnit\Framework\TestCase;

final class SseRecordsCountryPacksTest extends TestCase
{
    private const PACK_KEYS = ['code', 'label', 'label_en', 'fictional', 'units', 'date_format', 'date_hint', 'phone', 'plate', 'ids',
        'postal', 'address_format', 'records', 'names', 'cities', 'streets', 'carriers', 'mail_domains', 'vehicles', 'colors'];

    private const RECORD_KEYS = ['system', 'systems', 'vehicle_system', 'no_record', 'hit', 'possible', 'wants_label', 'status',
        'caution', 'resident_status'];

    private const STATUS_KEYS = ['armed_dangerous', 'warrant', 'wanted', 'missing', 'flagged', 'caution', 'watchlist', 'hvt'];

    private const CAUTION_KEYS = ['officer_safety', 'armed', 'violent', 'flight_risk', 'mental_health', 'medical', 'gang', 'explosives'];

    public function testCodesOrderAndFictionalPackIsOptional(): void
    {
        $real = ['USA', 'DEU', 'FRA', 'GBR', 'NLD', 'BEL', 'ITA', 'ESP', 'POL', 'UKR', 'IRQ', 'AFG', 'MLI'];
        self::assertSame($real, SseRecordsCountryPacks::codes(false));
        self::assertSame([...$real, 'ALT'], SseRecordsCountryPacks::codes());
        self::assertSame(['USA', 'DEU', 'FRA'], SseRecordsCountryPacks::PRIORITY);
    }

    public function testDefaultIsUsaAndNeverAltis(): void
    {
        self::assertSame('USA', SseRecordsCountryPacks::DEFAULT);
        self::assertSame('USA', SseRecordsCountryPacks::normalize(null));
        self::assertSame('USA', SseRecordsCountryPacks::normalize(''));
        self::assertSame('USA', SseRecordsCountryPacks::normalize('XYZ'));
        self::assertSame('DEU', SseRecordsCountryPacks::normalize(' deu '));
        self::assertSame('ALT', SseRecordsCountryPacks::normalize('alt'));
        self::assertSame('USA', SseRecordsCountryPacks::get('ZZZ')['code']);
        self::assertTrue(SseRecordsCountryPacks::get('ALT')['fictional']);
        self::assertFalse(SseRecordsCountryPacks::get('USA')['fictional']);
        self::assertTrue(SseRecordsCountryPacks::exists('fra'));
        self::assertFalse(SseRecordsCountryPacks::exists('GRC'));
        self::assertSame('Allemagne', SseRecordsCountryPacks::label('DEU'));
        self::assertSame('États-Unis', SseRecordsCountryPacks::label('nope'));
    }

    public function testEveryPackHasExactlyTheExpectedKeysAndValidExamples(): void
    {
        foreach (SseRecordsCountryPacks::codes() as $code) {
            $p = SseRecordsCountryPacks::get($code);
            self::assertSame(self::PACK_KEYS, array_keys($p), $code);
            self::assertSame($code, $p['code']);
            self::assertContains($p['units'], ['imperial', 'metric'], $code);
            self::assertSame(['prefix', 'pattern', 'example', 'national_format'], array_keys($p['phone']), $code);
            self::assertSame(['label', 'pattern', 'example', 'hint'], array_keys($p['plate']), $code);
            self::assertSame(['label', 'pattern', 'example'], array_keys($p['postal']), $code);
            self::assertSame(self::RECORD_KEYS, array_keys($p['records']), $code);
            self::assertSame(self::STATUS_KEYS, array_keys($p['records']['status']), $code);
            self::assertSame(self::CAUTION_KEYS, array_keys($p['records']['caution']), $code);
            self::assertArrayHasKey($p['records']['system'], $p['records']['systems'], $code);
            self::assertArrayHasKey($p['records']['vehicle_system'], $p['records']['systems'], $code);
            foreach ($p['records']['status'] as $label) {
                self::assertSame(mb_strtoupper($label, 'UTF-8'), $label, $code);
            }
            self::assertNotEmpty($p['ids'], $code);
            self::assertArrayHasKey('passport', $p['ids'], $code);
            foreach ($p['ids'] as $kind => $id) {
                self::assertContains($kind, ['national_id', 'id_card', 'passport', 'driving_license'], $code);
                self::assertSame(['label', 'short', 'pattern', 'example'], array_keys($id), $code . ' ' . $kind);
                self::assertTrue(SseRecordsFormat::validate($kind, $id['example'], $code), $code . ' ' . $kind . ' ' . $id['example']);
            }
            foreach (['phone', 'plate', 'postal'] as $kind) {
                self::assertTrue(SseRecordsFormat::validate($kind, $p[$kind]['example'], $code), $code . ' ' . $kind);
            }
            $min = in_array($code, SseRecordsCountryPacks::PRIORITY, true) ? [30, 30, 40, 12] : [12, 12, 12, 5];
            self::assertGreaterThanOrEqual($min[0], count($p['names']['male']), $code);
            self::assertGreaterThanOrEqual($min[1], count($p['names']['female']), $code);
            self::assertGreaterThanOrEqual($min[2], count($p['names']['last']), $code);
            self::assertGreaterThanOrEqual($min[3], count($p['cities']), $code);
            foreach ($p['cities'] as $city) {
                self::assertSame(['name', 'region', 'postal'], array_keys($city), $code);
                self::assertTrue(SseRecordsFormat::validate('postal', $city['postal'], $code), $code . ' ' . $city['name']);
            }
            self::assertGreaterThanOrEqual(15, count($p['streets']), $code);
            self::assertGreaterThanOrEqual(10, count($p['vehicles']), $code);
            self::assertNotEmpty($p['colors'], $code);
            self::assertNotEmpty($p['carriers'], $code);
            foreach ($p['mail_domains'] as $domain) {
                self::assertMatchesRegularExpression('/\.(example|test)$/', $domain, $code);
            }
            foreach (['{number}', '{street}', '{city}'] as $ph) {
                self::assertStringContainsString($ph, $p['address_format'], $code);
            }
        }
    }

    public function testPrioritySystemsAndTerminalTexts(): void
    {
        $us = SseRecordsCountryPacks::get('USA')['records'];
        self::assertSame('NCIC', $us['system']);
        self::assertSame(['NCIC', 'NLETS', 'DMV', 'III'], array_keys($us['systems']));
        self::assertSame('NO RECORD', $us['no_record']);
        self::assertSame('HIGH VALUE TARGET', $us['status']['hvt']);
        $de = SseRecordsCountryPacks::get('DEU')['records'];
        self::assertSame('INPOL', $de['system']);
        self::assertSame('ZEVIS', $de['vehicle_system']);
        self::assertSame('KEIN TREFFER', $de['no_record']);
        self::assertSame('BEWAFFNET UND GEFÄHRLICH', $de['status']['armed_dangerous']);
        self::assertNotEmpty($de['resident_status']);
        $fr = SseRecordsCountryPacks::get('FRA')['records'];
        self::assertSame('FPR', $fr['system']);
        self::assertSame(['FPR', 'TAJ', 'FNPC', 'SIV', 'FOVeS'], array_keys($fr['systems']));
        self::assertSame('NÉANT', $fr['no_record']);
        self::assertSame('FICHE S (FICTIVE)', $fr['status']['watchlist']);
    }

    public function testDateFormatsByCountry(): void
    {
        self::assertSame('03/14/1991', SseRecordsFormat::date('1991-03-14', 'USA'));
        self::assertSame('14.03.1991', SseRecordsFormat::date('1991-03-14', 'DEU'));
        self::assertSame('14/03/1991', SseRecordsFormat::date('1991-03-14', 'FRA'));
        self::assertSame('14/03/1991', SseRecordsFormat::date('1991-03-14 08:00:00', 'FRA'));
        self::assertSame('', SseRecordsFormat::date(null, 'USA'));
        self::assertSame('', SseRecordsFormat::date('1991-02-30', 'USA'));
        self::assertSame('', SseRecordsFormat::date('hier', 'USA'));
    }

    public function testValidateKnownValues(): void
    {
        self::assertTrue(SseRecordsFormat::validate('national_id', '912-48-3071', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('national_id', '912483071', 'USA'));
        self::assertFalse(SseRecordsFormat::validate('national_id', '000-12-3456', 'USA'));
        self::assertFalse(SseRecordsFormat::validate('national_id', '666-12-3456', 'USA'));
        self::assertFalse(SseRecordsFormat::validate('national_id', '12-345', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('plate', 'AB-123-CD', 'FRA'));
        self::assertTrue(SseRecordsFormat::validate('plate', 'ab 123 cd', 'FRA'));
        self::assertFalse(SseRecordsFormat::validate('plate', 'ABC', 'FRA'));
        self::assertFalse(SseRecordsFormat::validate('plate', 'AB-000-CD', 'FRA'));
        self::assertFalse(SseRecordsFormat::validate('plate', 'IO-123-CD', 'FRA'));
        self::assertTrue(SseRecordsFormat::validate('plate', 'B-AB 1234', 'DEU'));
        self::assertTrue(SseRecordsFormat::validate('plate', '7ABC123', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('postal', '10115', 'DEU'));
        self::assertFalse(SseRecordsFormat::validate('postal', '1011', 'DEU'));
        self::assertTrue(SseRecordsFormat::validate('postal', '90210', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('postal', '90210-1234', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('phone', '(213) 555-0147', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('phone', '+1 213 555 0147', 'USA'));
        self::assertTrue(SseRecordsFormat::validate('phone', '+33 6 39 98 12 34', 'FRA'));
        self::assertTrue(SseRecordsFormat::validate('phone', '+49 151 2048317', 'DEU'));
        self::assertFalse(SseRecordsFormat::validate('phone', '12', 'FRA'));
        // NIR : clé contrôlée ; Personalausweis sans voyelles.
        self::assertTrue(SseRecordsFormat::validate('national_id', '1 85 07 75 115 423 87', 'FRA'));
        self::assertFalse(SseRecordsFormat::validate('national_id', '1 85 07 75 115 423 88', 'FRA'));
        // Corse : 2A compté comme 19 pour la clé.
        self::assertSame(str_pad((string) (97 - (1850719004123 % 97)), 2, '0', STR_PAD_LEFT), SseRecordsFormat::nirKey('185072A004123'));
        self::assertTrue(SseRecordsFormat::validate('national_id', '1 85 07 2A 004 123 ' . SseRecordsFormat::nirKey('185072A004123'), 'FRA'));
        self::assertTrue(SseRecordsFormat::validate('id_card', 'LH4R7C2N9', 'DEU'));
        self::assertFalse(SseRecordsFormat::validate('id_card', 'LA4R7C2N9', 'DEU'));
        self::assertTrue(SseRecordsFormat::validate('id_card', '880692310285', 'FRA'));
        self::assertTrue(SseRecordsFormat::validate('national_id', '48213907L', 'ESP'));
        self::assertFalse(SseRecordsFormat::validate('national_id', '48213907A', 'ESP'));
        // Pas de motif pour ce type dans ce pays → vrai.
        self::assertTrue(SseRecordsFormat::validate('national_id', 'n’importe quoi', 'DEU'));
        self::assertSame('AB-123-CD', SseRecordsFormat::example('plate', 'FRA'));
        self::assertSame('', SseRecordsFormat::example('national_id', 'DEU'));
        self::assertSame('AB123CD', SseRecordsFormat::normalizePlate(' ab-123 cd '));
        self::assertSame('33639981234', SseRecordsFormat::normalizePhone('+33 6 39 98 12 34'));
    }

    public function testConversionsAndDisplay(): void
    {
        self::assertSame([5, 11], SseMeasure::cmToFeetInches(180));
        self::assertSame(180, SseMeasure::feetInchesToCm(5, 11));
        self::assertSame(172, SseMeasure::kgToLb(78));
        self::assertSame(78, SseMeasure::lbToKg(172));
        self::assertSame('5\'11" (180 cm)', SseRecordsFormat::height(180, 'USA'));
        self::assertSame('180 cm', SseRecordsFormat::height(180, 'DEU'));
        self::assertSame('172 lb (78 kg)', SseRecordsFormat::weight(78, 'USA'));
        self::assertSame('78 kg', SseRecordsFormat::weight(78, 'FRA'));
        self::assertSame('', SseRecordsFormat::height(null, 'USA'));
        self::assertSame('', SseRecordsFormat::weight(null, 'USA'));
    }

    public function testParseHeightAcceptsAllFormats(): void
    {
        $cases = [
            '180' => 180, '180cm' => 180, '180 cm' => 180, '1,80 m' => 180, '1.80' => 180, '1m80' => 180,
            "5'11" => 180, "5'11\"" => 180, '5′11″' => 180, '5 ft 11 in' => 180, '5ft11' => 180, '5-11' => 180,
            '71in' => 180, '71 in' => 180, "6'" => 183,
        ];
        foreach ($cases as $input => $cm) {
            self::assertSame($cm, SseMeasure::parseHeight((string) $input), (string) $input);
        }
        self::assertNull(SseMeasure::parseHeight(''));
        self::assertNull(SseMeasure::parseHeight('grand'));
        self::assertNull(SseMeasure::parseHeight('90'));
        self::assertNull(SseMeasure::parseHeight('300 cm'));
        self::assertNull(SseMeasure::parseHeight("5'13"));
    }

    public function testParseWeight(): void
    {
        self::assertSame(78, SseMeasure::parseWeight('78'));
        self::assertSame(78, SseMeasure::parseWeight('78kg'));
        self::assertSame(78, SseMeasure::parseWeight('78 kg'));
        self::assertSame(78, SseMeasure::parseWeight('172lb'));
        self::assertSame(78, SseMeasure::parseWeight('172 lbs'));
        self::assertSame(78, SseMeasure::parseWeight('172 pounds'));
        self::assertNull(SseMeasure::parseWeight('10 kg'));
        self::assertNull(SseMeasure::parseWeight('lourd'));
    }

    public function testGenerationIsDeterministic(): void
    {
        self::assertSame(SseRecordsGenerator::person('FRA', 42), SseRecordsGenerator::person('FRA', 42));
        self::assertSame(SseRecordsGenerator::batch('USA', 5, 7), SseRecordsGenerator::batch('USA', 5, 7));
        self::assertNotSame(SseRecordsGenerator::person('FRA', 42), SseRecordsGenerator::person('FRA', 43));
        self::assertSame('F', SseRecordsGenerator::person('DEU', 1, 'F')['sex']);
        self::assertCount(200, SseRecordsGenerator::batch('USA', 500, 1));
        self::assertSame([], SseRecordsGenerator::batch('USA', -3, 1));
        self::assertSame('USA', SseRecordsGenerator::person('ZZZ', 1)['country']);

        // L'état global de PHP n'est pas touché.
        mt_srand(1234);
        $expected = mt_rand();
        mt_srand(1234);
        SseRecordsGenerator::person('USA', 99);
        self::assertSame($expected, mt_rand());
    }

    public function testGeneratedValuesAreValidAndLocal(): void
    {
        $greek = SseIdRealCountryCatalog::POOLS['GREECE']['last'];
        $ref = new \DateTimeImmutable('2026-01-01');
        foreach (SseRecordsCountryPacks::codes() as $code) {
            $pack = SseRecordsCountryPacks::get($code);
            foreach (SseRecordsGenerator::batch($code, 40, 2026) as $p) {
                $label = $code . ' ' . $p['first_name'] . ' ' . $p['last_name'];
                self::assertSame($code, $p['country']);
                self::assertContains($p['last_name'], $pack['names']['last'], $label);
                self::assertContains($p['first_name'], $pack['names'][$p['sex'] === 'M' ? 'male' : 'female'], $label);
                if (in_array($code, SseRecordsCountryPacks::PRIORITY, true)) {
                    self::assertNotContains($p['last_name'], $greek, $label);
                }
                $age = (new \DateTimeImmutable($p['birth_date']))->diff($ref)->y;
                self::assertGreaterThanOrEqual(18, $age, $label);
                self::assertLessThanOrEqual(65, $age, $label);
                self::assertGreaterThanOrEqual(100, $p['height_cm']);
                self::assertLessThanOrEqual(250, $p['height_cm']);
                self::assertGreaterThanOrEqual(30, $p['weight_kg']);
                self::assertContains($p['build'], SseRecordsGenerator::BUILDS);
                self::assertContains($p['hair'], SseRecordsGenerator::HAIR);
                self::assertContains($p['eyes'], SseRecordsGenerator::EYES);
                self::assertTrue(SseRecordsFormat::validate('phone', $p['phone'], $code), $label . ' ' . $p['phone']);
                self::assertTrue(SseRecordsFormat::validate('plate', $p['vehicle']['plate'], $code), $label . ' ' . $p['vehicle']['plate']);
                self::assertTrue(SseRecordsFormat::validate('postal', $p['address']['postal'], $code), $label);
                self::assertSame(array_keys($pack['ids']), array_keys($p['ids']), $label);
                foreach ($p['ids'] as $kind => $value) {
                    self::assertTrue(SseRecordsFormat::validate($kind, $value, $code), $label . ' ' . $kind . ' ' . $value);
                }
                self::assertMatchesRegularExpression('/^[a-z0-9-]+\.[a-z0-9-]+@[a-z0-9.-]+\.(example|test)$/', $p['email'], $label);
                self::assertStringContainsString($p['address']['city'], $p['address']['line'], $label);
            }
        }
    }

    public function testFictionalRangesAndLocalFormats(): void
    {
        foreach (SseRecordsGenerator::batch('USA', 30, 5) as $p) {
            self::assertMatchesRegularExpression('/^9\d{2}-\d{2}-\d{4}$/', $p['ids']['national_id']);
            self::assertMatchesRegularExpression('/ 555-01\d{2}$/', $p['phone']);
        }
        foreach (SseRecordsGenerator::batch('FRA', 30, 5) as $p) {
            self::assertMatchesRegularExpression('/^[A-HJ-NP-TV-Z]{2}-\d{3}-[A-HJ-NP-TV-Z]{2}$/', $p['vehicle']['plate']);
            self::assertStringStartsWith('06 39 98 ', $p['phone']);
            self::assertSame($p['sex'] === 'M' ? '1' : '2', $p['ids']['national_id'][0]);
        }
        foreach (SseRecordsGenerator::batch('DEU', 30, 5) as $p) {
            self::assertMatchesRegularExpression('/^[A-Z]{1,3}-[A-Z]{1,2} [1-9]\d{0,3}$/', $p['vehicle']['plate']);
            self::assertStringStartsWith('0151 2', $p['phone']);
            self::assertStringEndsWith(', ' . $p['address']['postal'] . ' ' . $p['address']['city'], $p['address']['line']);
        }
    }
}
