<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseKitContentGenerator;
use App\Services\Sse\SseKitContentService;
use App\Support\SseKitCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Générateur de contenu SSE par kits : même graine = même jeu, réseau cohérent (familles qui partagent nom et adresse,
 * hiérarchie des organisations, papiers aux dates plausibles ou volontairement discordants pour les faux), formats du
 * pays (plaques, téléphones, numéros), aucune valeur en double, plafond par lot, récits tous différents.
 */
final class SseKitContentGeneratorTest extends TestCase
{
    private const TODAY = '2026-11-15';

    /**
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private static function params(array $over = []): array
    {
        return SseKitContentService::normalizeParams($over + [
            'kits' => ['IRQ', 'RUS', 'COL'], 'themes' => ['insurgent_network', 'militia', 'cartel'], 'people' => 90, 'civil_pct' => 60,
            'link_density' => 50, 'forged_pct' => 40, 'alias_pct' => 50, 'wanted_pct' => 30, 'vehicles_pp' => 6, 'docs_pp' => 3, 'lang' => 'fr',
        ]);
    }

    /**
     * @param array<string, mixed> $over
     * @return array<string, mixed>
     */
    private static function set(array $over = [], int $seed = 4242): array
    {
        return (new SseKitContentGenerator())->generate(self::params($over), $seed, self::TODAY);
    }

    /** Motif du kit (« # » chiffre, « @ » lettre, « {r} » code de région) en expression régulière. */
    private static function regex(string $pattern, string $letters, array $regions = []): string
    {
        $out = '';
        foreach (preg_split('/(\{r\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ($part === '{r}') {
                $out .= '(?:' . implode('|', array_map(static fn (string $r): string => preg_quote($r, '/'), $regions)) . ')?';

                continue;
            }
            foreach (mb_str_split($part) as $ch) {
                $out .= match ($ch) {
                    '#' => '\d',
                    '@' => '[' . preg_quote($letters, '/') . ']',
                    default => preg_quote($ch, '/'),
                };
            }
        }

        return '/^\s*' . $out . '\s*$/u';
    }

    public function testSameSeedGivesTheSameSetAndAnotherSeedADifferentOne(): void
    {
        $a = self::set();
        $b = self::set();
        $c = self::set([], 4243);

        self::assertSame($a, $b, 'même graine, mêmes paramètres, même date : même jeu');
        self::assertNotSame(array_column($a['people'], 'last_name'), array_column($c['people'], 'last_name'));
        self::assertCount(90, $a['people']);
        self::assertSame(90, $a['summary']['people']);
    }

    public function testCiviliansAreTheMajorityByDefaultAndAloneWithoutANetwork(): void
    {
        $default = (new SseKitContentGenerator())->generate(
            SseKitContentService::normalizeParams(['kits' => ['MLI'], 'themes' => ['smuggling_network'], 'people' => 50]),
            7,
            self::TODAY
        );
        self::assertGreaterThan(25, $default['summary']['by_theme'][SseKitCatalog::CIVIL_THEME], 'population civile majoritaire par défaut');
        self::assertSame(SseKitContentService::DEFAULTS['civil_pct'], 70);

        $civil = (new SseKitContentGenerator())->generate(SseKitContentService::normalizeParams(['kits' => ['SOM'], 'people' => 30, 'civil_pct' => 10]), 7, self::TODAY);
        self::assertSame([SseKitCatalog::CIVIL_THEME => 30], $civil['summary']['by_theme'], 'sans réseau choisi, tout le lot est civil');
        self::assertSame(0, $civil['summary']['wanted']);
        self::assertSame(0, $civil['summary']['by_flag']['forged']);
        self::assertSame([], $civil['orgs']);
    }

    public function testPeopleAreCoherentAgesDatesAndPapers(): void
    {
        $set = self::set();
        $today = new \DateTimeImmutable(self::TODAY);
        foreach ($set['people'] as $p) {
            self::assertTrue($p['fictional']);
            self::assertGreaterThanOrEqual(SseKitContentGenerator::MIN_AGE, $p['age']);
            $birth = new \DateTimeImmutable($p['birth_date']);
            self::assertSame($p['age'], $birth->diff($today)->y, 'âge et date de naissance concordent');
            self::assertContains($p['sex'], ['M', 'F']);
            self::assertNotSame('', $p['first_name']);
            self::assertNotSame('', $p['last_name']);
            self::assertNotSame('', $p['address']);
            self::assertNotSame('', $p['profession']);
            self::assertFalse(SseKitCatalog::isBlocked($p['first_name'], $p['last_name']));
            self::assertGreaterThanOrEqual(140, $p['height_cm']);
            self::assertLessThanOrEqual(200, $p['height_cm']);
            self::assertNotEmpty($p['documents'], 'chacun porte au moins une pièce');
            self::assertLessThanOrEqual(3, count($p['documents']));
            self::assertSame('id_card', $p['documents'][0]['doc_type']);
            $kit = SseKitCatalog::kit($p['kit']);
            $forgedDocs = 0;
            foreach ($p['documents'] as $d) {
                self::assertContains($d['doc_type'], $kit['docTypes']);
                self::assertSame($p['kit'], $d['country_code']);
                $issued = new \DateTimeImmutable($d['issued_on']);
                $years = (int) $kit['validity'][$d['doc_type']];
                if ($years > 0) {
                    self::assertSame($issued->modify('+' . $years . ' years')->format('Y-m-d'), $d['expires_on'], 'expiration = délivrance + validité du pays');
                } else {
                    self::assertNull($d['expires_on']);
                }
                $minAge = in_array($d['doc_type'], ['driving_license', 'vehicle_registration', 'military_id'], true) ? 18 : 16;
                $legal = $birth->modify('+' . $minAge . ' years');
                if ($d['flag'] === 'forged') {
                    $forgedDocs++;
                    self::assertSame('forged', $d['st_origin']);
                    self::assertNotNull($d['forgery_kind']);
                    self::assertStringContainsString('Discordance voulue', $d['note']);
                    self::assertNotSame('ok', $d['chip_status']);
                } else {
                    self::assertSame('generated', $d['st_origin']);
                    self::assertGreaterThanOrEqual($legal, $issued, $d['doc_type'] . ' délivré après l’âge légal');
                    self::assertLessThanOrEqual($today, $issued);
                    self::assertMatchesRegularExpression('/^' . $kit['iso2'] . '[A-Z]\d{6}$/', $d['doc_number']);
                    self::assertSame('', $d['note']);
                }
                if ($d['flag'] === 'expired') {
                    self::assertLessThan(self::TODAY, $d['expires_on']);
                    self::assertSame('expired', $d['st_validity']);
                }
            }
            self::assertSame($p['forged'] ? 1 : 0, $forgedDocs, 'un faux papier par porteur de faux, aucun pour les autres');
            if ($p['theme'] === SseKitCatalog::CIVIL_THEME) {
                self::assertSame('civilian', $p['category']);
                self::assertSame('', $p['org']);
                self::assertNull($p['wanted_level']);
                self::assertFalse($p['forged']);
                self::assertSame('', $p['alias']);
            } else {
                self::assertNotSame('', $p['org']);
            }
        }
    }

    public function testForgedDatesAreImpossibleOnPurpose(): void
    {
        $seen = [];
        foreach (range(1, 6) as $seed) {
            foreach (self::set(['forged_pct' => 100, 'people' => 60], $seed)['people'] as $p) {
                foreach ($p['documents'] as $d) {
                    if ($d['flag'] !== 'forged') {
                        continue;
                    }
                    $seen[$d['forgery_kind']] = true;
                    if (str_contains($d['note'], 'âge légal')) {
                        self::assertLessThan((new \DateTimeImmutable($p['birth_date']))->modify('+16 years'), new \DateTimeImmutable($d['issued_on']));
                    }
                    if (str_contains($d['note'], 'format du pays')) {
                        self::assertStringStartsWith('XX', $d['doc_number']);
                    }
                    if (str_contains($d['note'], 'au nom de')) {
                        self::assertNotSame('', $p['false_name']);
                        self::assertStringContainsString($p['false_name'], $d['note']);
                        self::assertNotSame($p['first_name'] . ' ' . $p['last_name'], $p['false_name']);
                    }
                }
            }
        }
        self::assertEqualsCanonicalizing(['counterfeit', 'data_alteration', 'photo_swap', 'stolen_blank'], array_keys($seen));
    }

    public function testPhonesPlatesAndImsiFollowTheKitFormats(): void
    {
        $set = self::set(['kits' => SseKitCatalog::codes(), 'people' => 200, 'vehicles_pp' => 12]);
        self::assertGreaterThan(150, $set['summary']['vehicles']);
        foreach ($set['people'] as $p) {
            $kit = SseKitCatalog::kit($p['kit']);
            foreach ($p['phones'] as $ph) {
                $ok = false;
                foreach ($kit['phones'] as $pattern) {
                    $ok = $ok || preg_match(self::regex($pattern, ''), $ph['number']) === 1;
                }
                self::assertTrue($ok, $p['kit'] . ' téléphone ' . $ph['number']);
                self::assertMatchesRegularExpression('/^999\d{12}$/', $ph['imsi'], 'IMSI fictif de quinze chiffres en 999');
            }
            foreach ($p['vehicles'] as $v) {
                $ok = false;
                foreach ($kit['plates'] as $pattern) {
                    $ok = $ok || preg_match(self::regex($pattern, $kit['plateLetters'], $kit['plateRegions']), $v['plate']) === 1;
                }
                self::assertTrue($ok, $p['kit'] . ' plaque ' . $v['plate']);
                self::assertContains(['make' => $v['make'], 'model' => $v['model'], 'kind' => $v['kind']], $kit['vehicles']);
                self::assertGreaterThanOrEqual(18, $p['age'], 'un véhicule appartient à un majeur');
            }
        }
    }

    public function testNothingIsDuplicatedInASet(): void
    {
        $set = self::set(['kits' => ['SOM', 'TCD'], 'people' => 200, 'vehicles_pp' => 15, 'docs_pp' => 4]);
        $names = $phones = $plates = $numbers = $stories = [];
        foreach ($set['people'] as $p) {
            $names[] = mb_strtolower($p['first_name'] . ' ' . $p['last_name']);
            $stories[] = $p['story'];
            foreach ($p['phones'] as $ph) {
                $phones[] = SseKitContentGenerator::compact($ph['number']);
            }
            foreach ($p['vehicles'] as $v) {
                $plates[] = SseKitContentGenerator::compact($v['plate']);
            }
            foreach ($p['documents'] as $d) {
                $numbers[] = $d['country_code'] . $d['doc_type'] . $d['doc_number'];
            }
        }
        foreach (['noms' => $names, 'téléphones' => $phones, 'plaques' => $plates, 'numéros de papiers' => $numbers, 'récits' => $stories] as $what => $list) {
            self::assertSame(count($list), count(array_unique($list)), 'aucun doublon : ' . $what);
        }
    }

    public function testValuesAlreadyInTheRegistryAreNeverDrawnAgain(): void
    {
        $first = self::set();
        $taken = ['names' => [], 'phones' => [], 'plates' => []];
        foreach ($first['people'] as $p) {
            $taken['names'][] = mb_strtolower($p['first_name'] . ' ' . $p['last_name']);
            foreach ($p['phones'] as $ph) {
                $taken['phones'][] = $ph['number'];
            }
            foreach ($p['vehicles'] as $v) {
                $taken['plates'][] = $v['plate'];
            }
        }
        $second = (new SseKitContentGenerator())->generate(self::params(), 4242, self::TODAY, $taken);
        foreach ($second['people'] as $p) {
            self::assertNotContains(mb_strtolower($p['first_name'] . ' ' . $p['last_name']), $taken['names']);
            foreach ($p['phones'] as $ph) {
                self::assertNotContains($ph['number'], $taken['phones']);
            }
            foreach ($p['vehicles'] as $v) {
                self::assertNotContains($v['plate'], $taken['plates']);
            }
        }
    }

    public function testHouseholdsShareTheirAddressAndFamilyName(): void
    {
        // Russie : le nom de famille s'accorde au féminin ; Colombie : double nom, l'enfant porte le premier nom de chaque parent.
        $ru = self::set(['kits' => ['RUS'], 'themes' => [], 'people' => 80]);
        $kit = SseKitCatalog::kit('RUS');
        $byHouse = [];
        foreach ($ru['people'] as $p) {
            $byHouse[$p['household']][] = $p;
        }
        self::assertSame(count($byHouse), $ru['households']);
        $shared = 0;
        foreach ($byHouse as $members) {
            $roots = [];
            foreach ($members as $m) {
                self::assertSame($members[0]['address'], $m['address'], 'même foyer, même adresse');
                $i = array_search($m['last_name'], $m['sex'] === 'F' ? $kit['lastF'] : $kit['last'], true);
                self::assertNotFalse($i, $m['last_name'] . ' : nom accordé au sexe');
                $roots[] = $kit['last'][$i];
            }
            self::assertCount(1, array_unique($roots), 'même foyer, même nom de famille');
            $shared += count($members) > 1 ? 1 : 0;
        }
        self::assertGreaterThan(10, $shared);

        $co = self::set(['kits' => ['COL'], 'themes' => [], 'people' => 80]);
        $index = array_column($co['people'], null, 'index');
        $children = 0;
        foreach ($co['links'] as $l) {
            self::assertSame($index[$l['from']]['household'], $index[$l['to']]['household'], 'un lien de parenté relie deux membres du même foyer');
            self::assertContains($l['relation'], ['family', 'spouse']);
            if (($l['kin'] ?? '') === 'child') {
                $child = explode(' ', $index[$l['from']]['last_name']);
                $parent = explode(' ', $index[$l['to']]['last_name']);
                self::assertContains($parent[0], [$child[0], end($child)], 'l’enfant porte le premier nom de son parent');
                $children++;
            }
        }
        self::assertGreaterThan(5, $children);
    }

    public function testOrganisationsHaveALeaderAndEveryMemberReportsToHim(): void
    {
        $set = self::set();
        $index = array_column($set['people'], null, 'index');
        self::assertNotEmpty($set['orgs']);
        self::assertCount(count($set['orgs']), array_unique(array_column($set['orgs'], 'name')), 'noms d’organisation tous différents');
        foreach ($set['orgs'] as $n => $org) {
            $leader = $index[$org['leader']];
            self::assertTrue($leader['leader']);
            self::assertSame($org['name'], $leader['org']);
            $members = array_filter($set['people'], static fn (array $p): bool => $p['org_index'] === $n);
            self::assertCount($org['members'], $members);
            foreach ($members as $m) {
                self::assertSame($org['kit'], $m['kit'], 'une organisation, un kit');
                self::assertSame($org['theme'], $m['theme']);
                if ($m['index'] === $org['leader']) {
                    continue;
                }
                $reports = array_filter($set['links'], static fn (array $l): bool => $l['from'] === $m['index'] && $l['to'] === $org['leader'] && in_array($l['relation'], ['cell', 'employer'], true));
                self::assertCount(1, $reports, $m['first_name'] . ' rend compte au chef');
            }
        }
        foreach ($set['links'] as $l) {
            self::assertArrayHasKey($l['from'], $index);
            self::assertArrayHasKey($l['to'], $index);
            self::assertNotSame($l['from'], $l['to']);
            self::assertContains($l['relation'], ['family', 'spouse', 'cell', 'employer', 'associate', 'contact']);
        }
        // Recherchés : les plus menaçants d'abord, les chefs en « hvt ».
        $wanted = array_filter($set['people'], static fn (array $p): bool => $p['wanted_level'] !== null);
        self::assertCount($set['summary']['wanted'], $wanted);
        self::assertNotEmpty($wanted);
        foreach ($wanted as $p) {
            self::assertContains($p['wanted_level'], ['surveillance', 'recherche', 'hvt']);
            if ($p['leader']) {
                self::assertSame('hvt', $p['wanted_level']);
            }
        }
    }

    public function testStoriesAreAssembledResolvedAndAvailableInEnglish(): void
    {
        foreach (['fr', 'en'] as $lang) {
            $set = self::set(['lang' => $lang, 'kits' => ['UKR', 'MEX', 'PHL', 'LBN'], 'themes' => SseKitCatalog::networkThemes(), 'people' => 200, 'civil_pct' => 40]);
            self::assertCount(9, array_unique(array_column($set['orgs'], 'theme')), 'tous les réseaux sont représentés');
            foreach ($set['people'] as $p) {
                self::assertDoesNotMatchRegularExpression('/[{}\[\]|]/', $p['story'], 'accords et variables résolus');
                self::assertStringContainsString($p['last_name'], $p['story']);
                self::assertGreaterThanOrEqual(2, preg_match_all('/[.!?](\s|$)/', $p['story']), 'deux ou trois phrases');
                self::assertLessThan(700, mb_strlen($p['story']));
                foreach ($p['pocket'] as $line) {
                    self::assertDoesNotMatchRegularExpression('/[#@]/', $line);
                }
            }
            $leader = array_values(array_filter($set['people'], static fn (array $p): bool => $p['leader']))[0];
            self::assertSame($lang === 'en' ? 'en' : 'fr', $lang);
            self::assertNotSame('', $leader['role_label']);
        }
        $fr = self::set(['lang' => 'fr'], 99)['people'];
        $en = self::set(['lang' => 'en'], 99)['people'];
        self::assertSame(array_column($fr, 'last_name'), array_column($en, 'last_name'), 'la langue ne change pas le tirage des personnes');
        self::assertNotSame(array_column($fr, 'story'), array_column($en, 'story'));
    }

    public function testParametersAreClampedToTheBatchCeiling(): void
    {
        $p = SseKitContentService::normalizeParams([
            'kits' => ['IRQ', 'ZZZ', 'irq', 'RUS'], 'themes' => ['cartel', 'civil_population', 'nope'], 'people' => 99999, 'civil_pct' => 250, 'docs_pp' => 12,
            'vehicles_pp' => -4, 'lang' => 'de', 'op_key' => 'bad code !', 'label' => str_repeat('x', 300),
        ]);
        self::assertSame(['IRQ', 'RUS'], $p['kits']);
        self::assertSame(['cartel'], $p['themes']);
        self::assertSame(SseKitContentService::MAX_PEOPLE, $p['people']);
        self::assertSame(200, SseKitContentService::MAX_PEOPLE);
        self::assertSame(100, $p['civil_pct']);
        self::assertSame(4, $p['docs_pp']);
        self::assertSame(0, $p['vehicles_pp']);
        self::assertSame('fr', $p['lang']);
        self::assertSame('', $p['op_key']);
        self::assertSame(90, mb_strlen($p['label']));
        self::assertCount(200, (new SseKitContentGenerator())->generate(['people' => 5000] + $p, 1, self::TODAY)['people']);
    }
}
