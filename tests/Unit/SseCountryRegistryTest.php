<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseMrzService;
use PHPUnit\Framework\TestCase;

/**
 * Registre des pays des papiers du mod SSE (tools/countries/countries.json -> CfgComspecCountries.hpp) : clés, codes ISO
 * 3166 (alpha-3 et alpha-2 vérifiés contre une table indépendante), noms FR et EN, types de documents connus, et numéros
 * tirés par le générateur (examples.json) relus par des validateurs PHP écrits à part (clés NIR, registre national belge,
 * BSN, AVS, SVNR, codice fiscale, DNI, NIE, PESEL, dowód, SSN fictif) ; MRZ relues avec SseMrzService (OACI 9303).
 */
final class SseCountryRegistryTest extends TestCase
{
    /** Table de référence ISO 3166-1 (alpha-3 => alpha-2) des pays réels attendus. */
    private const ISO = [
        'FRA' => 'FR', 'DEU' => 'DE', 'USA' => 'US', 'GBR' => 'GB', 'BEL' => 'BE', 'AUT' => 'AT', 'CHE' => 'CH', 'NLD' => 'NL', 'LUX' => 'LU',
        'ITA' => 'IT', 'ESP' => 'ES', 'PRT' => 'PT', 'POL' => 'PL', 'CZE' => 'CZ', 'SVK' => 'SK', 'HUN' => 'HU', 'ROU' => 'RO', 'BGR' => 'BG',
        'GRC' => 'GR', 'TUR' => 'TR', 'UKR' => 'UA', 'RUS' => 'RU', 'BLR' => 'BY', 'DNK' => 'DK', 'SWE' => 'SE', 'NOR' => 'NO', 'FIN' => 'FI',
        'EST' => 'EE', 'LVA' => 'LV', 'LTU' => 'LT', 'HRV' => 'HR', 'SRB' => 'RS', 'CAN' => 'CA', 'AUS' => 'AU', 'ISR' => 'IL', 'IRQ' => 'IQ',
        'AFG' => 'AF', 'SYR' => 'SY', 'MLI' => 'ML', 'NER' => 'NE', 'TCD' => 'TD', 'SOM' => 'SO', 'LBY' => 'LY', 'YEM' => 'YE', 'COL' => 'CO',
    ];

    private const FULL = ['FRA', 'DEU', 'USA', 'GBR', 'BEL', 'AUT', 'CHE', 'NLD', 'ITA', 'ESP', 'POL'];

    private const DOC_TYPES = ['id_card', 'passport', 'driving_license', 'residence_permit', 'vehicle_registration', 'military_id'];

    private static function path(string $p): string
    {
        return dirname(__DIR__, 2) . '/mod/@COMSPEC_SSE/' . $p;
    }

    /** @return array<string, array<string, mixed>> */
    private static function registry(): array
    {
        return json_decode((string) file_get_contents(self::path('tools/countries/countries.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testEveryRequestedCountryIsThereWithItsIsoCodes(): void
    {
        $reg = self::registry();
        foreach (self::ISO as $a3 => $a2) {
            self::assertArrayHasKey($a3, $reg, $a3);
            self::assertSame($a3, $reg[$a3]['code']);
            self::assertSame($a2, $reg[$a3]['iso2'], $a3);
            self::assertSame($a3 === 'DEU' ? 'D' : $a3, $reg[$a3]['mrz'], 'code MRZ OACI ' . $a3);
        }
        foreach (['ALT', 'TNA', 'LVN', 'MLD'] as $f) {
            self::assertSame('fictional', $reg[$f]['tier'], 'pays fictifs gardés pour les missions existantes');
        }
        $tiers = array_count_values(array_column($reg, 'tier'));
        self::assertSame(count(self::FULL), $tiers['full']);
        foreach (self::FULL as $c) {
            self::assertSame('full', $reg[$c]['tier'], $c);
        }
        $iso2 = array_filter(array_column($reg, 'iso2'));
        self::assertSame(count($iso2), count(array_unique($iso2)), 'alpha-2 uniques');
    }

    public function testEveryCountryHasTheRequiredKeys(): void
    {
        foreach (self::registry() as $code => $c) {
            foreach (['fr', 'en'] as $l) {
                self::assertNotSame('', $c['name'][$l] ?? '', "$code nom $l");
            }
            self::assertNotEmpty($c['cities'], $code);
            self::assertGreaterThanOrEqual(10, count($c['first']), $code);
            self::assertGreaterThanOrEqual(10, count($c['firstF']), $code);
            self::assertGreaterThanOrEqual(12, count($c['last']), $code);
            self::assertSame([], array_diff($c['docTypes'], self::DOC_TYPES), $code);
            self::assertContains($c['primary'], $c['docTypes'], $code);
            if ($c['tier'] === 'fictional') {
                continue;
            }
            foreach (['dateFormat', 'height', 'currency', 'plate', 'address', 'zone', 'colors', 'flag', 'labels', 'idCheck', 'police', 'validity'] as $k) {
                self::assertArrayHasKey($k, $c, "$code $k");
            }
            foreach ($c['docTypes'] as $t) {
                self::assertArrayHasKey($t, $c['numbers'], "$code numéro $t");
                self::assertCount(3, $c['docNames'][$t], "$code nom du papier $t [langue, FR, EN]");
            }
            foreach (['last', 'first', 'dob', 'number', 'expires'] as $k) {
                self::assertNotSame('', $c['labels'][$k] ?? '', "$code étiquette $k");
            }
            if (isset($c['lastF'])) {
                self::assertCount(count($c['last']), $c['lastF'], "$code noms au féminin alignés");
            }
        }
        self::assertSame('ftin', self::registry()['USA']['height']);
        self::assertSame('mm/dd/yyyy', self::registry()['USA']['dateFormat']);
        self::assertNotContains('id_card', self::registry()['GBR']['docTypes'], 'pas de carte nationale au Royaume-Uni');
    }

    public function testGeneratedConfigIsInSyncAndDocumentTypesExist(): void
    {
        $hpp = str_replace("\r\n", "\n", (string) file_get_contents(self::path('addons/identity/CfgComspecCountries.hpp')));
        self::assertStringStartsWith('// GÉNÉRÉ par tools/countries/gen_countries.py', $hpp);
        foreach (array_keys(self::registry()) as $code) {
            self::assertMatchesRegularExpression('/^    class ' . $code . ' \{$/m', $hpp, $code);
        }
        $docs = (string) file_get_contents(self::path('addons/identity/CfgComspecDocuments.hpp'));
        foreach (self::DOC_TYPES as $t) {
            self::assertMatchesRegularExpression('/^    class ' . $t . '\b/m', $docs, $t);
        }
        // countries[] d'un type : codes du registre seulement.
        preg_match_all('/countries\[\]\s*=\s*\{([^}]*)\}/', $docs, $m);
        foreach ($m[1] as $list) {
            foreach (array_filter(array_map(static fn (string $s): string => trim($s, " \"\t\n"), explode(',', $list))) as $c) {
                self::assertArrayHasKey(strtoupper($c), self::registry(), "countries[] : $c");
            }
        }
        $cfg = (string) file_get_contents(self::path('addons/identity/config.cpp'));
        self::assertStringContainsString('#include "CfgComspecCountries.hpp"', $cfg);
        foreach (['idNumbers', 'idCountryChrome', 'idCountrySelfTest'] as $f) {
            self::assertStringContainsString("class $f {};", $cfg);
            self::assertFileExists(self::path("addons/identity/functions/fn_$f.sqf"));
        }
        // Une carte du jeu ne choisit jamais deux pays.
        $maps = [];
        foreach (self::registry() as $code => $c) {
            foreach ($c['maps'] ?? [] as $w) {
                self::assertArrayNotHasKey($w, $maps, "carte $w");
                $maps[$w] = $code;
            }
        }
        self::assertSame('ALT', $maps['altis'] ?? null);
        self::assertSame('ALT', $maps['stratis'] ?? null);
    }

    public function testGeneratedNumbersPassIndependentValidators(): void
    {
        $ex = json_decode((string) file_get_contents(self::path('tools/countries/examples.json')), true, 512, JSON_THROW_ON_ERROR);
        $checked = 0;
        foreach ($ex as $code => $e) {
            foreach ($e['numbers'] as $rows) {
                foreach ($rows as $row) {
                    if (!str_starts_with($row['spec'], 'scheme:')) {
                        continue;
                    }
                    $scheme = substr($row['spec'], 7);
                    self::assertTrue(self::valid($scheme, $row['value'], $row['ctx'] ?? []), "$code $scheme {$row['value']}");
                    $checked++;
                }
            }
            foreach ($e['mrz'] as $lines) {
                self::assertTrue(self::mrzOk($lines), "$code MRZ " . implode(' / ', $lines));
            }
        }
        self::assertGreaterThan(60, $checked);
        self::assertStringStartsWith('IDD<<', $ex['DEU']['mrz']['td1'][0], 'Allemagne : « IDD<< »');
    }

    public function testValidatorsAcceptPublicExamplesAndRejectWrongKeys(): void
    {
        self::assertTrue(self::valid('es_dni', '12345678Z'));
        self::assertFalse(self::valid('es_dni', '12345678A'));
        self::assertTrue(self::valid('es_nie', 'X1234567L'));
        self::assertTrue(self::valid('pl_pesel', '44051401359'));
        self::assertTrue(self::valid('nl_bsn', '111222333'));
        self::assertFalse(self::valid('nl_bsn', '111222334'));
        self::assertTrue(self::valid('ch_ahv', '756.9217.0769.85'));
        self::assertTrue(self::valid('at_svnr', '1237 010180'));
        self::assertTrue(self::valid('it_cf', 'RSSMRA85T10A562S'));
        self::assertTrue(self::valid('pl_idcard', 'ABA300000'));
        self::assertTrue(self::mrzOk(['P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<', 'L898902C36UTO7408122F1204159ZE184226B<<<<<10']));
        self::assertTrue(self::mrzOk(['I<UTOD231458907<<<<<<<<<<<<<<<', '7408122F1204159UTO<<<<<<<<<<<6', 'ERIKSSON<<ANNA<MARIA<<<<<<<<<<']));
    }

    public function testModUsesTheRegistryEverywhere(): void
    {
        $countries = (string) file_get_contents(self::path('addons/identity/functions/fn_idCountries.sqf'));
        self::assertStringContainsString('configFile >> "CfgComspecCountries"', $countries);
        self::assertStringNotContainsString('comspec_sse_id_realSim', $countries, 'pays réels proposés d’office');
        $settings = (string) file_get_contents(self::path('addons/identity/functions/fn_idInitSettings.sqf'));
        self::assertStringContainsString('"comspec_sse_id_countryFallback", "LIST"', $settings);
        $eden = (string) file_get_contents(self::path('addons/eden/attributes_identity.hpp'));
        self::assertStringContainsString('control = "COMSPEC_SSE_CountryCombo";', $eden);
        $ws = (string) file_get_contents(self::path('addons/ia_rp/functions/fn_iarpWorkshop.sqf'));
        self::assertStringContainsString('["ordered"] call comspec_sse_fnc_idCountries', $ws);
        $profile = (string) file_get_contents(self::path('addons/ia_rp/functions/fn_iarpProfile.sqf'));
        self::assertStringContainsString('["recountry", [_u, _p get "country"]]', $profile);
        // Pas d'alerte Arma ajoutée par ces fichiers.
        foreach (['addons/identity/functions/fn_idCountryChrome.sqf', 'addons/identity/functions/fn_idNumbers.sqf', 'addons/eden/functions/fn_edenCountries.sqf'] as $f) {
            $src = (string) file_get_contents(self::path($f));
            foreach (['hint ', 'systemChat', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'fnc_toast'] as $bad) {
                self::assertStringNotContainsString($bad, $src, "$f : $bad");
            }
        }
        foreach (['xml/main' => 'addons/main/stringtable.xml', 'xml/ia_rp' => 'addons/ia_rp/stringtable.xml'] as $what => $f) {
            $xml = simplexml_load_file(self::path($f));
            self::assertNotFalse($xml, $what);
            foreach ($xml->xpath('//Key[starts-with(@ID, "STR_COMSPEC_SSE_ID_") or starts-with(@ID, "STR_COMSPEC_IARP_W_Country")]') as $k) {
                self::assertNotSame('', trim((string) $k->French), (string) $k['ID']);
                self::assertNotSame('', trim((string) $k->English), (string) $k['ID']);
            }
        }
    }

    // ------------------------------------------------------------------------------------------------ validateurs PHP
    /** @param array<string, string> $ctx */
    private static function valid(string $scheme, string $v, array $ctx = []): bool
    {
        $d = preg_replace('/\D/', '', $v) ?? '';
        $mod = static function (string $digits, int $m): int {
            $r = 0;
            foreach (str_split($digits) as $c) {
                $r = ($r * 10 + (int) $c) % $m;
            }

            return $r;
        };
        switch ($scheme) {
            case 'fr_nir':
                return strlen($d) === 15 && (int) substr($d, 13) === 97 - $mod(substr($d, 0, 13), 97) && substr($d, 10, 3) === '000'
                    && $d[0] === (($ctx['sex'] ?? 'M') === 'F' ? '2' : '1');
            case 'be_rrn':
                $k = (int) substr($d, 9);

                return strlen($d) === 11 && ($k === 97 - $mod(substr($d, 0, 9), 97) || $k === 97 - $mod('2' . substr($d, 0, 9), 97));
            case 'be_idcard':
                $m = $mod(substr($d, 0, 10), 97);

                return strlen($d) === 12 && (int) substr($d, 10) === ($m === 0 ? 97 : $m);
            case 'us_ssn':
                return (bool) preg_match('/^9\d\d-(0[1-9]|[1-4]\d)-\d{4}$/', $v) && substr($v, -4) !== '0000';
            case 'nl_bsn':
                if (strlen($d) !== 9) {
                    return false;
                }
                $s = 0;
                for ($i = 0; $i < 8; $i++) {
                    $s += (int) $d[$i] * (9 - $i);
                }

                return ($s - (int) $d[8]) % 11 === 0;
            case 'ch_ahv':
                $s = 0;
                for ($i = 0; $i < 12; $i++) {
                    $s += (int) $d[$i] * ($i % 2 === 0 ? 1 : 3);
                }

                return strlen($d) === 13 && str_starts_with($d, '756') && (int) $d[12] === (10 - $s % 10) % 10;
            case 'at_svnr':
                $w = [3, 7, 9, 0, 5, 8, 4, 2, 1, 6];
                $s = 0;
                for ($i = 0; $i < 10; $i++) {
                    $s += (int) ($d[$i] ?? 0) * $w[$i];
                }

                return strlen($d) === 10 && $s % 11 === (int) $d[3];
            case 'it_cf':
                $odd = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];
                $t = 0;
                for ($i = 0; $i < 15; $i++) {
                    $c = $v[$i];
                    $n = ctype_digit($c) ? (int) $c : ord($c) - 65;
                    $t += $i % 2 === 0 ? $odd[$n] : $n;
                }

                return strlen($v) === 16 && $v[15] === chr(65 + $t % 26);
            case 'es_dni':
                return (bool) preg_match('/^\d{8}[A-Z]$/', $v) && $v[8] === 'TRWAGMYFPDXBNJZSQVHLCKE'[$mod(substr($v, 0, 8), 23)];
            case 'es_nie':
                return (bool) preg_match('/^[XYZ]\d{7}[A-Z]$/', $v) && $v[8] === 'TRWAGMYFPDXBNJZSQVHLCKE'[$mod(strpos('XYZ', $v[0]) . substr($v, 1, 7), 23)];
            case 'pl_pesel':
                $w = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
                $s = 0;
                for ($i = 0; $i < 10; $i++) {
                    $s += (int) $d[$i] * $w[$i];
                }

                return strlen($d) === 11 && (10 - $s % 10) % 10 === (int) $d[10];
            case 'pl_idcard':
                $w = [7, 3, 1, 9, 7, 3, 1, 7, 3];
                $s = 0;
                foreach (str_split($v) as $i => $c) {
                    $s += (ctype_digit($c) ? (int) $c : ord($c) - 55) * $w[$i];
                }

                return strlen($v) === 9 && $s % 10 === 0;
            case 'gb_dln':
                return strlen($v) === 16 && ctype_digit(substr($v, 5, 6)) && in_array((int) substr($v, 6, 2) % 50, range(1, 12), true);
            case 'it_cie':
                return (bool) preg_match('/^C[A-Z]\d{5}[A-Z]{2}$/', $v);
            default:
                return false;
        }
    }

    /** @param list<string> $l */
    private static function mrzOk(array $l): bool
    {
        $ck = static fn (string $s): string => (string) SseMrzService::checkDigit($s);
        if (count($l) === 3) {
            [$a, $b] = $l;
            if (strlen($a) !== 30 || strlen($b) !== 30) {
                return false;
            }
            if ($a[14] === '<' && $a[15] !== '<') {
                $seg = explode('<', substr($a, 15))[0];
                if ($ck(substr($a, 5, 9) . substr($seg, 0, -1)) !== substr($seg, -1)) {
                    return false;
                }
            } elseif ($ck(substr($a, 5, 9)) !== $a[14]) {
                return false;
            }

            return $ck(substr($b, 0, 6)) === $b[6] && $ck(substr($b, 8, 6)) === $b[14]
                && $ck(substr($a, 5, 25) . substr($b, 0, 7) . substr($b, 8, 7) . substr($b, 18, 11)) === $b[29];
        }
        [, $b] = $l;

        return strlen($b) === 44 && $ck(substr($b, 0, 9)) === $b[9] && $ck(substr($b, 13, 6)) === $b[19] && $ck(substr($b, 21, 6)) === $b[27]
            && $ck(substr($b, 0, 10) . substr($b, 13, 7) . substr($b, 21, 22)) === $b[43];
    }
}
