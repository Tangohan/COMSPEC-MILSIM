<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseIdentityGeneratorService;
use App\Services\Sse\SseMrzService;
use PHPUnit\Framework\TestCase;

/**
 * Bande MRZ ICAO 9303 : spécimens officiels du document 9303 (passeport TD3 et carte TD1 d'Anna Maria Eriksson,
 * État fictif « UTO »), et tirage déterministe partagé avec le générateur du mod SSE.
 */
final class SseMrzServiceTest extends TestCase
{
    public function testCheckDigitsOfTheIcaoSpecimen(): void
    {
        self::assertSame(6, SseMrzService::checkDigit('L898902C3'));
        self::assertSame(2, SseMrzService::checkDigit('740812'));
        self::assertSame(9, SseMrzService::checkDigit('120415'));
        self::assertSame(1, SseMrzService::checkDigit('ZE184226B<<<<<'));
        self::assertSame(0, SseMrzService::checkDigit('L898902C3674081221204159ZE184226B<<<<<1'));
        self::assertSame(7, SseMrzService::checkDigit('D23145890'));
    }

    public function testPassportTd3MatchesTheIcaoSpecimen(): void
    {
        $lines = SseMrzService::td3([
            'issuer' => 'UTO',
            'last_name' => 'Eriksson',
            'first_name' => 'Anna Maria',
            'number' => 'L898902C3',
            'nationality' => 'UTO',
            'birth_date' => '1974-08-12',
            'sex' => 'F',
            'expires_on' => '2012-04-15',
            'personal_number' => 'ZE184226B',
        ]);

        self::assertSame('P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<', $lines[0]);
        self::assertSame('L898902C36UTO7408122F1204159ZE184226B<<<<<10', $lines[1]);
        self::assertSame(['format' => 'TD3', 'valid' => true], array_intersect_key(SseMrzService::verify($lines), ['format' => 1, 'valid' => 1]));
    }

    public function testIdCardTd1MatchesTheIcaoSpecimen(): void
    {
        $lines = SseMrzService::td1([
            'issuer' => 'UTO',
            'last_name' => 'Eriksson',
            'first_name' => 'Anna Maria',
            'number' => 'D23145890',
            'nationality' => 'UTO',
            'birth_date' => '1974-08-12',
            'sex' => 'F',
            'expires_on' => '2012-04-15',
        ]);

        self::assertSame('I<UTOD231458907<<<<<<<<<<<<<<<', $lines[0]);
        self::assertSame('7408122F1204159UTO<<<<<<<<<<<6', $lines[1]);
        self::assertSame('ERIKSSON<<ANNA<MARIA<<<<<<<<<<', $lines[2]);
        self::assertTrue(SseMrzService::verify($lines)['valid']);
    }

    public function testATamperedBandFailsItsCheckDigits(): void
    {
        $lines = ['P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<', 'L898902C36UTO7408122F1204159ZE184226B<<<<<10'];
        $lines[1][0] = 'M';
        $res = SseMrzService::verify($lines);

        self::assertFalse($res['valid']);
        self::assertFalse($res['checks']['number']);
        self::assertFalse($res['checks']['composite']);
    }

    public function testNamesLoseTheirAccentsAndPunctuation(): void
    {
        self::assertSame('LEFEVRE<<ETIENNE<<<<<<<<<<<<<<', SseMrzService::nameField('Lefèvre', 'Étienne', 30));
        self::assertSame('WOZNIAK<<LUKASZ<<<<<<<<<<<<<<<', SseMrzService::nameField('Woźniak', 'Łukasz', 30));
        self::assertSame('O<NEIL<SMITH<<JEAN<ERIC<<<<<<<', SseMrzService::nameField("O'Neil-Smith", 'Jean Éric', 30));
        self::assertSame('<<<<<<', SseMrzService::date(null));
    }

    public function testDocumentBandFollowsTheDocumentType(): void
    {
        $identity = ['last_name' => 'Papadopoulos', 'first_name' => 'Nikos', 'nationality' => 'ALT', 'birth_date' => '1990-05-01', 'sex' => 'M'];
        $passport = SseMrzService::forDocument(['doc_type' => 'passport', 'country_code' => 'ALT', 'doc_number' => 'ALK482913', 'expires_on' => '2034-01-01'], $identity);
        $card = SseMrzService::forDocument(['doc_type' => 'id_card', 'country_code' => 'ALT', 'doc_number' => 'ALK482913', 'expires_on' => '2034-01-01'], $identity);
        $licence = SseMrzService::forDocument(['doc_type' => 'driving_license', 'country_code' => 'ALT', 'doc_number' => 'ALK482913', 'expires_on' => '2034-01-01'], $identity);

        self::assertCount(2, $passport);
        self::assertStringStartsWith('P<ALTPAPADOPOULOS<<NIKOS', $passport[0]);
        self::assertCount(3, $card);
        self::assertStringStartsWith('I<ALTALK482913', $card[0]);
        self::assertStringStartsWith('D<ALT', $licence[0]);
        self::assertTrue(SseMrzService::verify($passport)['valid']);
        self::assertTrue(SseMrzService::verify($card)['valid']);
    }

    public function testGeneratorHashIsTheSameAsTheSqfOne(): void
    {
        // Valeurs de comspec_sse_fnc_hash recalculées à la main (deux voies ×31 mod 524287 et ×33 mod 508361).
        $a = ((7 % 524287) * 31 + 102) % 524287;
        $a = ($a * 31 + 110) % 524287;
        $b = ((7 % 508361) * 33 + 102) % 508361;
        $b = ($b * 33 + 110) % 508361;
        self::assertSame($a * 32 + $b % 32, SseIdentityGeneratorService::hash(7, 'fn'));
        self::assertLessThanOrEqual(16777215, SseIdentityGeneratorService::hash(16777215, 'zzzzzzzzzz'));

        $country = ['code' => 'ALT', 'name' => 'République d’Altis', 'region' => 'ALTIS'];
        $gen = new SseIdentityGeneratorService();
        self::assertSame($gen->generate($country, 4242), $gen->generate($country, 4242));
        $names = [];
        foreach ([1, 2, 3, 4, 5, 6] as $seed) {
            $g = $gen->generate($country, $seed);
            $names[] = $g['last_name'] . ' ' . $g['first_name'];
            self::assertSame('ALT', $g['nationality']);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $g['birth_date']);
        }
        self::assertGreaterThan(1, count(array_unique($names)));

        $number = SseIdentityGeneratorService::documentNumber('AL@######', 4242);
        self::assertMatchesRegularExpression('/^AL[A-Z][0-9]{6}$/', $number);
        self::assertTrue(SseIdentityGeneratorService::matchesFormat($number, 'AL@######'));
        self::assertFalse(SseIdentityGeneratorService::matchesFormat('XX1234567', 'AL@######'));
    }
}
