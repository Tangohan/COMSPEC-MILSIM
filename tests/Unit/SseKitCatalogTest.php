<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseIdentityGeneratorService;
use App\Support\SseDocumentTypes;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdRealCountryCatalog;
use App\Support\SseIdWorkshopCatalog;
use App\Support\SseKitCatalog;
use App\Support\SseWantedRealNames;
use PHPUnit\Framework\TestCase;

/**
 * Kits de contenu SSE (config/sse_kits) : grands viviers sans doublon, thèmes de réseau en français et en anglais,
 * garde-fous de contenu (aucune organisation réelle, homonymes de personnalités écartés), et alignement avec le mod :
 * mêmes codes pays et mêmes viviers que le registre des papiers, régions narratives, modèles intégrés, version.
 */
final class SseKitCatalogTest extends TestCase
{
    /** Organisations armées, criminelles ou de sécurité réelles : aucun texte des kits ne les cite. */
    private const REAL_ORGANISATIONS = [
        'al-qaida', 'al qaida', 'al-qaeda', 'al qaeda', 'aqmi', 'aqim', 'daech', 'daesh', 'isis', 'etat islamique', 'islamic state', 'boko haram',
        'hezbollah', 'hamas', 'taliban', 'taleban', 'wagner', 'sinaloa', 'jalisco', 'zetas', 'farc', 'eln', 'abu sayyaf', 'al-shabaab', 'al shabaab',
        'shebab', 'houthi', 'jnim', 'gsim', 'azov', 'nusra', 'tahrir al-sham', 'pkk', 'clan del golfo', 'medellin cartel', 'cartel de medellin',
        'cartel de cali', 'camorra', 'ndrangheta', 'cosa nostra', 'blackwater', 'academi', 'haqqani', 'lashkar', 'ansar dine', 'ansar al', 'mujao',
        'macina', 'seleka', 'janjaweed', 'janjawid', 'rsf', 'kata\'ib', 'kataeb', 'asaib', 'badr', 'peshmerga',
    ];

    private static function path(string $p): string
    {
        return dirname(__DIR__, 2) . '/' . $p;
    }

    private static function read(string $p): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::path($p)));
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower($s);

        return strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c', '’' => "'"]);
    }

    public function testEveryKitHasLargePoolsWithoutDuplicates(): void
    {
        $kits = SseKitCatalog::kits();
        self::assertGreaterThanOrEqual(17, count($kits));
        foreach (['IRQ', 'RUS', 'SYR', 'AFG', 'MLI', 'UKR', 'COL', 'LBN', 'MEX', 'PHL', 'ALB'] as $code) {
            self::assertArrayHasKey($code, $kits);
        }
        $orders = [];
        foreach ($kits as $code => $kit) {
            self::assertSame($code, $kit['code']);
            self::assertMatchesRegularExpression('/^[A-Z]{3}$/', $code);
            $orders[] = $kit['order'];
            $sizes = SseKitCatalog::sizes($code);
            self::assertGreaterThanOrEqual(90, $sizes['male'], $code . ' prénoms masculins');
            self::assertGreaterThanOrEqual(75, $sizes['female'], $code . ' prénoms féminins');
            self::assertGreaterThanOrEqual(90, $sizes['last'], $code . ' noms');
            self::assertGreaterThanOrEqual(30, $sizes['nicknames'], $code . ' surnoms');
            self::assertGreaterThanOrEqual(14, $sizes['vehicles'], $code . ' véhicules');
            self::assertGreaterThanOrEqual(5, $sizes['documents'], $code . ' types de papiers');
            self::assertGreaterThanOrEqual(190, $sizes['places'], $code . ' localités fictives possibles');
            self::assertGreaterThanOrEqual(40, $sizes['streets'], $code . ' rues possibles');
            // Combinatoire : des dizaines de milliers d'identités différentes par kit.
            self::assertGreaterThan(15000, ($sizes['male'] + $sizes['female']) * $sizes['last'], $code);
            foreach (['male', 'female', 'last', 'nicknames', 'placeA', 'placeB', 'quarters', 'streetB', 'orgWords', 'cities'] as $pool) {
                $lower = array_map('mb_strtolower', $kit[$pool]);
                self::assertSame(count($lower), count(array_unique($lower)), $code . ' ' . $pool . ' sans doublon');
                foreach ($kit[$pool] as $v) {
                    self::assertNotSame('', trim((string) $v));
                }
            }
            foreach (['fr', 'en'] as $lang) {
                self::assertNotSame('', SseKitCatalog::text($kit['name'], $lang));
                self::assertGreaterThan(40, mb_strlen(SseKitCatalog::text($kit['summary'], $lang)));
                self::assertNotSame('', $kit['nationality'][$lang]);
            }
            if ($kit['lastF'] !== []) {
                self::assertCount(count($kit['last']), $kit['lastF'], $code . ' : noms au féminin alignés');
            }
            foreach ($kit['docTypes'] as $type) {
                self::assertArrayHasKey($type, SseIdentityCatalog::DOC_TYPES);
                self::assertNotSame('', (string) $kit['authorities'][$type]);
                self::assertArrayHasKey($type, $kit['validity']);
            }
            foreach ($kit['phones'] as $pattern) {
                self::assertStringContainsString('00', $pattern, $code . ' : bloc fictif « 00 » dans le numéro');
                self::assertGreaterThanOrEqual(3, substr_count($pattern, '#'), $code . ' : assez de numéros de téléphone');
                self::assertStringStartsWith('+', $pattern);
            }
            foreach ($kit['plates'] as $pattern) {
                self::assertGreaterThanOrEqual(4, substr_count($pattern, '#') + substr_count($pattern, '@'), $code . ' plaque ' . $pattern);
            }
        }
        self::assertSame(count($orders), count(array_unique($orders)));
        self::assertNull(SseKitCatalog::kit('ZZZ'));
    }

    public function testThemesCoverTenUniversesInFrenchAndEnglish(): void
    {
        $themes = SseKitCatalog::themes();
        self::assertGreaterThanOrEqual(10, count($themes));
        self::assertSame(SseKitCatalog::CIVIL_THEME, array_key_first($themes), 'la population civile ouvre la liste');
        self::assertCount(count($themes) - 1, SseKitCatalog::networkThemes());
        $catalog = SseKitCatalog::catalog();
        $known = ['first', 'last', 'age', 'job', 'place', 'place2', 'birth', 'quarter', 'year', 'role', 'org', 'deOrg', 'aOrg', 'chief', 'alias', 'relative',
            'falseName', 'vehicle', 'color', 'plate'];
        $check = static function (array $line, string $where) use ($known): void {
            foreach (['fr', 'en'] as $lang) {
                $text = (string) $line[$lang];
                self::assertNotSame('', $text, $where);
                self::assertMatchesRegularExpression('/[.!?»"]$/u', $text, $where . ' : phrase terminée');
                preg_match_all('/\{(\w+)\}/', $text, $m);
                foreach ($m[1] as $var) {
                    self::assertContains($var, $known, $where . ' : variable inconnue {' . $var . '}');
                }
                // Accords « [masculin|féminin] » bien formés.
                self::assertSame(substr_count($text, '['), substr_count($text, ']'), $where);
                self::assertSame(substr_count($text, '['), substr_count($text, '|'), $where);
            }
        };
        foreach ($themes as $key => $theme) {
            foreach (['fr', 'en'] as $lang) {
                self::assertNotSame('', SseKitCatalog::text($theme['name'], $lang));
                self::assertGreaterThan(60, mb_strlen(SseKitCatalog::text($theme['summary'], $lang)));
            }
            foreach (['open' => 8, 'mid' => 8, 'close' => 6] as $part => $min) {
                self::assertGreaterThanOrEqual($min, count($theme['narrative'][$part]), $key . ' ' . $part);
                foreach ($theme['narrative'][$part] as $n => $line) {
                    $check($line, $key . '.' . $part . '.' . $n);
                }
                $fr = array_column($theme['narrative'][$part], 'fr');
                self::assertSame(count($fr), count(array_unique($fr)), $key . ' ' . $part . ' : gabarits tous différents');
            }
            foreach ($theme['kits'] as $code) {
                self::assertNotNull(SseKitCatalog::kit($code), $key . ' renvoie à un kit inconnu ' . $code);
            }
            if ($key === SseKitCatalog::CIVIL_THEME) {
                self::assertTrue($theme['civil']);
                self::assertCount(1, $theme['roles']);

                continue;
            }
            self::assertFalse($theme['civil']);
            self::assertGreaterThanOrEqual(8, count($theme['roles']), $key . ' rôles');
            self::assertCount(1, array_filter($theme['roles'], static fn (array $r): bool => !empty($r['leader'])), $key . ' : un seul chef');
            self::assertCount(6, $theme['orgPatterns']['fr']);
            self::assertCount(6, $theme['orgPatterns']['en']);
            foreach ($theme['roles'] as $role) {
                self::assertArrayHasKey($role['category'], SseIdWorkshopCatalog::POP_CATEGORIES, $key . ' ' . $role['key']);
                self::assertArrayHasKey($role['side'], SseIdWorkshopCatalog::SIDES);
                self::assertGreaterThanOrEqual(1, $role['threat']);
                self::assertLessThanOrEqual(5, $role['threat']);
                self::assertLessThan($role['ageMax'], $role['ageMin']);
                self::assertNotEmpty($role['cover']);
                foreach (['fr', 'frF', 'en'] as $k) {
                    self::assertNotSame('', $role['label'][$k]);
                }
            }
        }
        foreach ($catalog['shared'] as $part => $lines) {
            self::assertGreaterThanOrEqual(4, count($lines), $part);
            foreach ($lines as $n => $line) {
                $check($line, 'shared.' . $part . '.' . $n);
            }
        }
        self::assertGreaterThanOrEqual(70, count($catalog['professions']));
        self::assertGreaterThanOrEqual(35, count($catalog['marks']));
        self::assertGreaterThanOrEqual(10, count($catalog['pocket']));
    }

    public function testContentIsFictionalNoRealOrganisationAndNoNotoriousHomonym(): void
    {
        $catalog = SseKitCatalog::catalog();
        self::assertStringContainsString('fictif', mb_strtolower($catalog['notice']['fr']));
        self::assertStringContainsString('fictional', mb_strtolower($catalog['notice']['en']));

        // Tout ce qui sert à nommer une organisation ou un surnom, et tous les récits.
        $texts = [];
        foreach (SseKitCatalog::kits() as $kit) {
            $texts = array_merge($texts, $kit['orgWords'], $kit['nicknames'], [SseKitCatalog::text($kit['summary'], 'fr'), SseKitCatalog::text($kit['summary'], 'en')]);
        }
        foreach (SseKitCatalog::themes() as $theme) {
            foreach (['fr', 'en'] as $lang) {
                $texts[] = SseKitCatalog::text($theme['name'], $lang);
                $texts[] = SseKitCatalog::text($theme['summary'], $lang);
                foreach ($theme['orgPatterns'][$lang] as $p) {
                    $texts[] = (string) $p['name'];
                }
                foreach (['open', 'mid', 'close'] as $part) {
                    foreach ($theme['narrative'][$part] as $line) {
                        $texts[] = (string) $line[$lang];
                    }
                }
            }
        }
        foreach ($texts as $text) {
            $n = self::norm((string) $text);
            self::assertNull(SseWantedRealNames::find((string) $text), 'service réel cité : ' . $text);
            foreach (self::REAL_ORGANISATIONS as $org) {
                self::assertDoesNotMatchRegularExpression('/(?<![a-z])' . preg_quote($org, '/') . '(?![a-z])/', $n, 'organisation réelle citée : ' . $text);
            }
        }
        // Chaque thème de réseau annonce qu'il est inventé.
        foreach (SseKitCatalog::networkThemes() as $key) {
            self::assertMatchesRegularExpression('/invent/u', SseKitCatalog::text(SseKitCatalog::theme($key)['summary'], 'fr'), $key);
            self::assertMatchesRegularExpression('/invented/u', SseKitCatalog::text(SseKitCatalog::theme($key)['summary'], 'en'), $key);
        }

        // Homonymes de personnalités : jamais tirés, y compris avec un double nom de famille.
        self::assertGreaterThan(40, count($catalog['blocked']));
        self::assertTrue(SseKitCatalog::isBlocked('Dmitry', 'Medvedev'));
        self::assertTrue(SseKitCatalog::isBlocked('juan manuel', 'Santos Calderón'));
        self::assertTrue(SseKitCatalog::isBlocked('Ibrahim', 'Keïta'));
        self::assertFalse(SseKitCatalog::isBlocked('Dmitry', 'Sokolov'));
        // Noms de famille trop liés à une personnalité, une dynastie ou un chef de groupe réels : hors des viviers.
        $allLast = [];
        foreach (SseKitCatalog::kits() as $kit) {
            $allLast = array_merge($allLast, $kit['last']);
        }
        foreach (['al-Awlaqi', 'Guzmán', 'Duterte', 'Tymoshenko', 'Thaçi', 'al-Maliki', 'Jumblatt', 'Bazoum', 'Itno', 'Ag Boula', 'Castaño', 'Barre'] as $name) {
            self::assertNotContains($name, $allLast, $name);
        }
    }

    public function testKitsMatchTheModCountryRegistry(): void
    {
        $registry = json_decode(self::read('mod/@COMSPEC_SSE/tools/countries/countries.json'), true, 512, JSON_THROW_ON_ERROR);
        $hpp = self::read('mod/@COMSPEC_SSE/addons/identity/CfgComspecCountries.hpp');
        $realSqf = self::read('mod/@COMSPEC_SSE/addons/identity/functions/fn_idRealCountries.sqf');
        foreach (SseKitCatalog::kits() as $code => $kit) {
            self::assertArrayHasKey($code, $registry, $code . ' : même code que le registre des papiers du mod');
            $c = $registry[$code];
            self::assertSame($kit['male'], $c['first'], $code . ' : mêmes prénoms en jeu et sur le site');
            self::assertSame($kit['female'], $c['firstF'], $code);
            self::assertSame($kit['last'], $c['last'], $code);
            self::assertSame($kit['lastF'], $c['lastF'] ?? [], $code);
            self::assertSame($kit['docTypes'], $c['docTypes'], $code);
            self::assertSame($kit['iso2'], $c['iso2']);
            foreach (['residence_permit', 'vehicle_registration', 'military_id'] as $type) {
                self::assertContains($type, $c['docTypes'], $code . ' délivre ' . $type);
                self::assertCount(3, $c['docNames'][$type]);
                self::assertNotSame('', $c['numbers'][$type]);
            }
            self::assertStringContainsString('    class ' . $code . ' {', $hpp);
            self::assertSame($kit['docTypes'], SseDocumentTypes::servedBy($code), $code . ' : miroir des papiers à jour (tools/sse/sync_document_types.php)');

            // Pays réel stylisé importable par la communauté, avec les mêmes viviers.
            self::assertTrue(SseIdRealCountryCatalog::has($code), $code);
            $real = SseIdRealCountryCatalog::COUNTRIES[$code];
            $pool = SseIdRealCountryCatalog::POOLS[$real['region']];
            self::assertSame($kit['male'], $pool['m'], $code);
            self::assertSame($kit['female'], $pool['f'], $code);
            self::assertSame($kit['iso2'] . '@######', SseIdRealCountryCatalog::numberFormat($code));
            self::assertStringContainsString('["' . $code . '", ', $realSqf);
            if (($pool['f_suffix'] ?? '') === '') {
                self::assertSame($kit['last'], $pool['last'], $code);
            } else {
                foreach ($pool['last'] as $n) {
                    self::assertContains($n . $pool['f_suffix'], $kit['lastF'], $code . ' ' . $n);
                }
            }
        }
        foreach (['LBN', 'ALB', 'MEX', 'PHL'] as $new) {
            self::assertSame('generic', $registry[$new]['tier']);
            self::assertSame('new', SseKitCatalog::kit($new)['status']);
        }
    }

    public function testNarrativeRegionsAndBuiltInModelsFollowTheKits(): void
    {
        // Chaque kit nourrit une région, chaque région a ses kits.
        $covered = array_merge(...array_values(SseKitCatalog::REGIONS));
        self::assertEqualsCanonicalizing(SseKitCatalog::codes(), $covered);
        foreach (SseKitCatalog::kits() as $code => $kit) {
            self::assertContains($code, SseKitCatalog::REGIONS[$kit['region']], $code);
        }
        self::assertSame(
            SseKitCatalog::narrativeSqf(static fn (string $r): array => SseIdentityGeneratorService::regionPool($r)),
            self::read('mod/@COMSPEC_SSE/addons/generator/functions/fn_kitPools.sqf'),
            'fn_kitPools.sqf à régénérer : php mod/@COMSPEC_SSE/tools/gen_kit_pools.php'
        );
        foreach (array_keys(SseKitCatalog::REGIONS) as $region) {
            $pool = SseIdentityGeneratorService::regionPool($region);
            self::assertGreaterThanOrEqual(90, count($pool['first']), $region);
            self::assertGreaterThanOrEqual(90, count($pool['last']), $region);
            self::assertSame(count($pool['first']), count(array_unique($pool['first'])));
        }
        // Les noms d'origine d'une région restent en tête : ils étaient déjà en jeu.
        self::assertSame('Karim', SseIdentityGeneratorService::regionPool('LEVANT')['first'][0]);
        self::assertSame('Ivan', SseIdentityGeneratorService::regionPool('RUSSIA')['first'][0]);
        self::assertSame('Karim', SseIdentityGeneratorService::regionPool('GENERIC')['first'][0]);
        self::assertSame('Karim', SseIdentityGeneratorService::regionPool('INCONNUE')['first'][0], 'région inconnue : vivier générique');

        $pools = self::read('mod/@COMSPEC_SSE/addons/generator/functions/fn_getNarrativePools.sqf');
        self::assertStringContainsString('comspec_sse_fnc_kitPools', $pools);
        self::assertStringContainsString('comspec_sse_narrativePoolsCache', $pools);
        self::assertStringContainsString('class kitPools {};', self::read('mod/@COMSPEC_SSE/addons/generator/config.cpp'));
        $eden = self::read('mod/@COMSPEC_SSE/addons/eden/config.cpp');
        $strings = self::read('mod/@COMSPEC_SSE/addons/main/stringtable.xml');
        $dict = self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt');
        foreach (SseKitCatalog::REGION_LABELS as $region => [$fr, $en]) {
            self::assertArrayHasKey($region, SseKitCatalog::REGIONS);
            self::assertNotContains($region, SseKitCatalog::LEGACY_REGIONS);
            $frGame = str_replace('’', "'", $fr);
            $key = 'STR_COMSPEC_SSE_CFG_' . strtoupper(substr(sha1($frGame), 0, 10));
            self::assertStringContainsString('class ' . $region . ' { name = "$' . $key . '"; value = "' . $region . '"; };', $eden, $region);
            self::assertStringContainsString('<Key ID="' . $key . '">', $strings);
            self::assertStringContainsString('["' . $frGame . '", "' . $en . '"]', $dict, 'traduction anglaise de ' . $fr);
        }

        // Modèles intégrés : ceux du fichier, ceux de la liste du module Zeus, tous sur une région connue.
        $models = self::read('mod/@COMSPEC_SSE/addons/generator/functions/fn_registerBuiltinModels.sqf');
        preg_match_all('/^\[\n    "(builtin_[a-z0-9_]+)",\n    "([^"]+)",/m', $models, $m);
        self::assertGreaterThanOrEqual(61, count($m[1]));
        self::assertSame(count($m[1]), count(array_unique($m[1])), 'identifiants de modèle uniques');
        $zeus = self::read('mod/@COMSPEC_SSE/addons/zeus/config.cpp');
        foreach ($m[1] as $i => $id) {
            self::assertStringContainsString('value = "' . $id . '"; };', $zeus, $id . ' proposé par le module « Appliquer un modèle »');
            if ($i >= 24) {
                $key = 'STR_COMSPEC_SSE_CFG_' . strtoupper(substr(sha1($m[2][$i]), 0, 10));
                self::assertStringContainsString('<Key ID="' . $key . '">', $strings, $m[2][$i]);
                self::assertStringContainsString('["' . $m[2][$i] . '", "', $dict, $m[2][$i]);
            }
        }
        preg_match_all('/\["region", "([A-Z_]+)"\]/', $models, $r);
        foreach (array_unique($r[1]) as $region) {
            self::assertTrue($region === 'GENERIC' || isset(SseKitCatalog::REGIONS[$region]), $region);
        }
        foreach (array_keys(SseKitCatalog::REGIONS) as $region) {
            self::assertContains($region, $r[1], 'au moins un modèle intégré pour ' . $region);
        }
    }

    public function testTheSsePackVersionIsBumpedWithItsNotes(): void
    {
        $header = self::read('mod/@COMSPEC_SSE/addons/main/script_mod.hpp');
        self::assertStringContainsString('versionStr = "1.10.0"; versionAr[] = {1,10,0,0}', $header);
        foreach (['generator', 'identity', 'eden', 'zeus'] as $addon) {
            self::assertStringContainsString('versionStr = "1.10.0"', self::read('mod/@COMSPEC_SSE/addons/' . $addon . '/script_mod.hpp'), $addon);
        }
        $steam = self::read('mod/@COMSPEC_SSE/STEAM_CHANGELOG.txt');
        self::assertStringStartsWith('[h1]COMSPEC SSE 1.10.0', $steam);
        self::assertStringContainsString('[h2]English[/h2]', substr($steam, 0, 4000));
        self::assertStringContainsString('## 1.10.0', self::read('mod/@COMSPEC_SSE/docs/CHANGELOG.md'));
        // Dictionnaire anglais : tableau clos, une entrée par ligne, la dernière sans virgule.
        $dict = rtrim(self::read('mod/COMSPEC_ATAK_Native/Sources/addons/main/i18n/en.txt'));
        self::assertStringStartsWith("[\n[", $dict);
        self::assertStringEndsWith("\"]\n]", $dict);
        self::assertSame(0, preg_match('/^\[.*\]\s*$(?<!\],)\n\[/m', $dict), 'chaque entrée se termine par une virgule, sauf la dernière');
    }
}
