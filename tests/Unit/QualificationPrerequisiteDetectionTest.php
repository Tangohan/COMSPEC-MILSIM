<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeRepository;
use App\Repositories\PilotQualificationRuleRepository;
use App\Repositories\QualificationAwardRepository;
use App\Repositories\QualificationAwardRuleRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Repositories\QualificationReferentielRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\QualificationAwardRuleService;
use App\Services\Personnel\QualificationCriteriaEngine as Eng;
use App\Services\Personnel\QualificationPrerequisiteSuggester as Sug;
use App\Services\Personnel\QualificationStatusTransitionService;
use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;
use App\Support\QualificationCatalog\QualificationCatalog;
use App\Support\QualificationCriteriaCatalog as Cat;
use App\Support\QualificationPrerequisiteExtractor as X;
use App\Support\QualificationPrerequisiteResolver as R;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Prérequis détectés dans le texte des qualifications : extraction (phrases réelles des catalogues FR / EN, termes
 * allemands), résolution des qualifications citées, suggestions et ajout à la règle, nouvelles conditions (ancienneté
 * mesurée, attestations d'instructeur) dans la délivrance automatique, pré-calcul des catalogues et câblage.
 */
final class QualificationPrerequisiteDetectionTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * Phrases des catalogues (config/qualification_catalog) et ce qu'il faut y lire.
     *
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: array<string, mixed>|null}>
     */
    public static function phrases(): iterable
    {
        $pre = X::ORIGIN_PREREQUISITE;
        $desc = X::ORIGIN_DESCRIPTION;
        // [phrase, origine, pack, détection attendue (null : rien de chiffré ni de grade)]
        yield 'de 150 free-fall jumps' => ['At least 150 free-fall jumps before the course (free-fall team leader)', $pre, 'de', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 150.0, 'high' => true]];
        yield 'fr 150 sauts chute libre' => ['Au moins 150 sauts en chute libre avant le cours (Freifalltruppführer)', $pre, 'de', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 150.0, 'high' => true]];
        yield 'us 50 mff jumps' => ['At least one year as a free-fall parachutist and 50 military free-fall jumps', $pre, 'us', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 50.0, 'high' => true]];
        yield 'fr 50 sauts' => ['Au moins un an comme parachutiste de chute libre et 50 sauts en chute libre', $pre, 'us', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 50.0, 'high' => true]];
        yield 'fr bmp cinq suffisent' => ['La formation dure environ deux semaines à l’École des troupes aéroportées de Pau : à l’origine six sauts, aujourd’hui cinq suffisent, dont des sauts « lisses » sans armement.', $desc, 'fr', ['kind' => 'metric', 'metric' => 'halo_jumps', 'min' => 5.0, 'high' => true]];
        yield 'en bmp five enough' => ['Training lasts about two weeks at the Airborne Troops School in Pau: originally six jumps, now five are enough, including clean jumps without weapons.', $desc, 'fr', ['kind' => 'metric', 'metric' => 'halo_jumps', 'min' => 5.0, 'high' => true]];
        yield 'en six jumps a year' => ['The trade requires at least six parachute jumps a year.', $desc, 'fr', ['kind' => 'metric', 'metric' => 'halo_jumps', 'min' => 6.0, 'window_days' => 365, 'high' => true]];
        yield 'fr six sauts par an' => ['Le métier impose au moins six sauts en parachute par an.', $desc, 'fr', ['kind' => 'metric', 'metric' => 'halo_jumps', 'min' => 6.0, 'window_days' => 365, 'high' => true]];
        yield 'us five static-line' => ['Three-week course open to all Department of Defense services: students make five static-line jumps and receive the Parachutist Badge.', $desc, 'us', ['kind' => 'metric', 'metric' => 'halo_jumps', 'min' => 5.0, 'high' => true]];
        yield 'my 21 free fall' => ['The free fall parachutist badge requires the basic badge, this course and 21 free fall jumps, four of them with combat gear.', $desc, 'my', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 21.0, 'high' => true]];
        yield 'fr about 80 jumps' => ['Il sanctionne le stage de 13 semaines à l’ETAP : environ 80 sauts, à ouverture commandée.', $desc, 'fr', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 80.0, 'high' => false]];
        yield 'fr trois semaines de saut' => ['Le cycle enchaîne trois semaines de saut à ouverture commandée en individuel.', $desc, 'fr', null];
        yield 'de 170 diving hours' => ['Schwimmtaucher examination passed and 170 diving hours', $pre, 'de', ['kind' => 'attestation', 'key' => 'dive_hours', 'min' => 170.0, 'high' => true]];
        yield 'fr 170 heures de plongée' => ['Examen Schwimmtaucher réussi et 170 heures de plongée', $pre, 'de', ['kind' => 'attestation', 'key' => 'dive_hours', 'min' => 170.0, 'high' => true]];
        yield 'de two years of service' => ['Two years of prior service (enlisted and NCOs)', $pre, 'de', ['kind' => 'metric', 'metric' => 'seniority_days', 'min' => 730.0, 'high' => true]];
        yield 'fr deux ans de service' => ['Militaires du rang et sous-officiers : 32 ans au plus et au moins deux ans de service', $pre, 'de', ['kind' => 'metric', 'metric' => 'seniority_days', 'min' => 730.0, 'high' => true]];
        yield 'fr un an expérience montagne' => ['Au moins un an d’expérience en montagne', $pre, 'fr', ['kind' => 'metric', 'metric' => 'seniority_days', 'min' => 365.0, 'high' => false]];
        yield 'gb age not seniority' => ['Aged 17 to 37, UK or dual national with five years\' residence', $pre, 'gb', null];
        yield 'fr age not seniority' => ['Moins de 30 ans à la signature du contrat', $pre, 'fr', null];
        yield 'de rank feldwebel' => ['At least the rank of Feldwebel (per the job pack)', $pre, 'de', ['kind' => 'grade', 'nato' => 'OR-6', 'high' => true]];
        yield 'fr grade feldwebel' => ['Grade de Feldwebel au minimum (selon le pack d’emplois)', $pre, 'de', ['kind' => 'grade', 'nato' => 'OR-6', 'high' => true]];
        yield 'nato or range' => ['Rank OR-4 to OR-8 or OF-1 to OF-3 (or civilian equivalent)', $pre, 'nato', ['kind' => 'grade', 'nato' => 'OR-4', 'high' => true]];
        yield 'nato of or nco' => ['Officer OF-3 and above, or NCO OR-8 and above (or civilian equivalent)', $pre, 'nato', ['kind' => 'grade', 'nato' => 'OR-8', 'high' => true]];
        yield 'us e-6' => ['Active-duty senior NCO (E-6 and above), officer or warrant officer', $pre, 'us', ['kind' => 'grade', 'nato' => 'OR-6', 'high' => true]];
        yield 'gb corporal to captain' => ['Corporal to Captain', $pre, 'gb', ['kind' => 'grade', 'nato' => 'OR-4', 'high' => true]];
        yield 'my rank of major' => ['Rank of major or equivalent', $pre, 'my', ['kind' => 'grade', 'nato' => 'OF-3', 'high' => true]];
        yield 'fr lieutenant ou sous-officier' => ['Lieutenant issu d’une division d’application ou sous-officier supérieur', $pre, 'fr', ['kind' => 'grade', 'nato' => 'OR-6', 'high' => false]];
        yield 'de enlisted and officers' => ['Enlisted and junior NCOs: aged 32 or under with at least two years of service; senior NCOs: 35 or under; officers: Leutnant to Hauptmann (A11)', $pre, 'de', ['kind' => 'metric', 'metric' => 'seniority_days', 'min' => 730.0, 'high' => true]];
        yield 'us pst fitness' => ['Physical Screening Test (PST)', $pre, 'us', ['kind' => 'attestation', 'key' => 'fitness_test', 'high' => false]];
        yield 'fr test sportif' => ['Test sportif : 5 000 m en moins de 22 min, 1 000 m de nage en 24 min, 8 tractions', $pre, 'de', ['kind' => 'attestation', 'key' => 'fitness_test', 'high' => false]];
        yield 'en 200 flight hours helicopter' => ['At least 200 flight hours on helicopters as pilot in command', $pre, 'us', ['kind' => 'metric', 'metric' => 'flight_hours', 'scope' => 'kind:heli', 'min' => 200.0, 'high' => true]];
        yield 'fr 3 missions de combat' => ['Avoir effectué au moins 3 missions de combat', $pre, 'fr', ['kind' => 'metric', 'metric' => 'missions_played', 'min' => 3.0, 'high' => true]];
        yield 'en 17 weeks of mission' => ['then 15 to 17 weeks of mission qualification in various environments', $desc, 'us', null];
        yield 'en expert marksman' => ['Qualified as expert marksman on the service rifle', $pre, 'us', ['kind' => 'attestation', 'key' => 'marksmanship', 'high' => false]];
        // Termes allemands (intitulés officiels des packs)
        yield 'de mindestens 150 Freifallsprünge' => ['Mindestens 150 Freifallsprünge vor dem Lehrgang', $pre, 'de', ['kind' => 'attestation', 'key' => 'freefall_jumps', 'min' => 150.0, 'high' => true]];
        yield 'de zwei Jahre Dienstzeit' => ['Mindestens zwei Jahre Dienstzeit', $pre, 'de', ['kind' => 'metric', 'metric' => 'seniority_days', 'min' => 730.0, 'high' => true]];
        yield 'de 500 Flugstunden' => ['Mindestens 500 Flugstunden auf dem Hubschrauber', $pre, 'de', ['kind' => 'metric', 'metric' => 'flight_hours', 'scope' => 'kind:heli', 'min' => 500.0, 'high' => true]];
        yield 'de Dienstgrad Hauptfeldwebel' => ['Dienstgrad mindestens Hauptfeldwebel', $pre, 'de', ['kind' => 'grade', 'nato' => 'OR-7', 'high' => true]];
    }

    /** @param array<string, mixed>|null $want */
    #[DataProvider('phrases')]
    public function testExtractsMeasuredAttestedAndRankRequirementsFromRealPhrases(string $phrase, string $origin, string $pack, ?array $want): void
    {
        $found = array_values(array_filter(X::extract($origin === X::ORIGIN_PREREQUISITE ? [$phrase] : $phrase, $origin, $pack), static fn (array $d): bool => $d['kind'] !== 'reference'));
        if ($want === null) {
            self::assertSame([], array_values(array_filter($found, static fn (array $d): bool => in_array($d['kind'], ['metric', 'grade'], true) || in_array($d['key'] ?? '', ['freefall_jumps', 'dive_hours'], true))), $phrase);

            return;
        }
        $match = null;
        foreach ($found as $d) {
            if ($d['kind'] === $want['kind'] && (!isset($want['metric']) || $d['metric'] === $want['metric']) && (!isset($want['key']) || $d['key'] === $want['key'])) {
                $match = $d;
            }
        }
        self::assertNotNull($match, $phrase . ' : ' . json_encode($found, JSON_UNESCAPED_UNICODE));
        foreach (['min', 'nato', 'scope', 'window_days'] as $k) {
            if (isset($want[$k])) {
                self::assertEquals($want[$k], $match[$k], $phrase . ' (' . $k . ')');
            }
        }
        self::assertSame($want['high'], $match['confidence'] >= X::HIGH, $phrase . ' : confiance ' . $match['confidence']);
        self::assertSame($phrase, $match['source']);
    }

    public function testResolvesCitedQualificationsWithAcronymsAndAlternatives(): void
    {
        $r = R::fromCatalog('fr');
        $hit = static fn (string $s, string $self, string $pack): array => array_column($r->resolve($s, $self, $pack), 'confidence', 'id');
        self::assertGreaterThanOrEqual(X::HIGH, $hit('Military parachutist badge', 'fr-chuteur-operationnel', 'fr')['fr-brevet-parachutiste'] ?? 0);
        self::assertGreaterThanOrEqual(X::HIGH, $hit('Passed SFAS', 'us-sfqc', 'us')['us-sfas'] ?? 0);
        $mff = $hit('Basic Airborne Course, MFFPC and static-line Jumpmaster', 'us-mffjm', 'us');
        self::assertArrayHasKey('us-basic-airborne', $mff);
        self::assertArrayHasKey('us-mffpc', $mff);
        self::assertArrayHasKey('us-jumpmaster', $mff);
        // « P Company ou All Arms Commando Course » : l'une ou l'autre, à confirmer.
        self::assertLessThan(X::HIGH, $hit('Passed P Company or the All Arms Commando Course depending on posting', 'gb-basic-parachute-course', 'gb')['gb-aacc'] ?? 1);
        // Jamais elle-même ; un sigle commun à plusieurs fiches reste incertain.
        self::assertArrayNotHasKey('us-sfas', $hit('Passed SFAS', 'us-sfas', 'us'));
        self::assertLessThan(X::HIGH, max($hit('Service member nominated by their unit for CBRN defence', 'gb-cbrn', 'gb') ?: [0]));
    }

    public function testCatalogEntriesCarryReviewedHighConfidencePrerequisitesListedInTheReport(): void
    {
        $report = (string) file_get_contents(self::ROOT . '/docs/qualifications/prerequis-detectes.md');
        $total = 0;
        foreach (QualificationCatalog::rawEntries() as $e) {
            self::assertSame([], QualificationCatalog::problems($e), (string) $e['id']);
            foreach ($e['prerequisites_detected'] ?? [] as $p) {
                $total++;
                self::assertGreaterThanOrEqual(X::HIGH, (float) $p['confidence'], $e['id']);
                self::assertTrue(isset($p['source_index']) || ($p['source'] ?? '') === 'description', $e['id']);
                if (isset($p['source_index'])) {
                    self::assertArrayHasKey($p['source_index'], $e['prerequisites']['en'], $e['id']);
                }
                if ($p['kind'] === 'qualification') {
                    self::assertNotNull(QualificationCatalog::findRaw((string) $p['ref']), $e['id'] . ' → ' . $p['ref']);
                }
            }
            if (!empty($e['prerequisites_detected'])) {
                self::assertStringContainsString('`' . $e['id'] . '`', $report, 'ajout absent du rapport : ' . $e['id']);
            }
        }
        self::assertGreaterThan(100, $total);
        self::assertStringContainsString('**' . $total . '**', $report, 'synthèse du rapport à jour (relancer tools/qualifications/detect-prerequisites.php --write)');
        $bmp = QualificationCatalog::findRaw('fr-brevet-parachutiste');
        self::assertSame('halo_jumps', $bmp['prerequisites_detected'][0]['metric']);
        self::assertSame(5, $bmp['prerequisites_detected'][0]['min']);
        $ops = QualificationCatalog::findRaw('fr-chuteur-operationnel');
        self::assertContains('fr-brevet-parachutiste', array_column($ops['prerequisites_detected'], 'ref'));
    }

    /** @return array<string, mixed> contexte de QualificationPrerequisiteSuggester::suggest pour une communauté */
    private static function ctx(array $definitions, array $grades = []): array
    {
        $candidates = [];
        $byCode = [];
        foreach ($definitions as $d) {
            $byCode[strtoupper($d['code'])] = $d['id'];
            $candidates[] = ['type' => 'qualification', 'id' => $d['id'], 'label' => $d['name'], 'names' => [$d['name']], 'short' => [$d['short_name'] ?? '']];
        }
        $candidates[] = ['type' => 'course', 'id' => 'key:jtac', 'label' => 'JTAC — contrôleur aérien avancé', 'names' => ['JTAC — contrôleur aérien avancé']];

        return ['lang' => 'fr', 'resolver' => new R($candidates), 'catalog' => R::fromCatalog('fr'), 'by_code' => $byCode, 'grades' => $grades,
            'names' => array_column($definitions, 'name', 'id')];
    }

    public function testSuggestionsForAnImportedQualificationAreCheckedOnlyWhenSureAndAvailable(): void
    {
        $defs = [
            ['id' => 7, 'code' => 'FR-BMP', 'name' => 'Brevet militaire de parachutiste', 'short_name' => 'BMP'],
            ['id' => 8, 'code' => 'FR-CHUTEUR-OPS', 'name' => 'Chuteur opérationnel', 'short_name' => 'CHUT OPS'],
        ];
        $ops = QualificationCatalog::findRaw('fr-chuteur-operationnel');
        $defs[1]['code'] = (string) $ops['code'];
        $list = Sug::suggest($defs[1], ['rule' => [], 'prereq_grade_id' => null], [], self::ctx($defs));
        $byId = array_column($list, null, 'id');
        self::assertArrayHasKey('q:7', $byId, 'BMP importé : qualification à détenir, résolue vers la fiche de la communauté');
        self::assertTrue($byId['q:7']['checked']);
        self::assertSame('high', $byId['q:7']['level']);

        // BMP lui-même : « cinq sauts suffisent » → 5 sauts mesurés, cochés ; rien ne pointe vers lui-même.
        $defs[0]['code'] = (string) QualificationCatalog::findRaw('fr-brevet-parachutiste')['code'];
        $bmp = array_column(Sug::suggest($defs[0], ['rule' => []], [], self::ctx($defs)), null, 'id');
        self::assertSame(5.0, $bmp['m:halo_jumps::0']['value']);
        self::assertTrue($bmp['m:halo_jumps::0']['checked']);
        self::assertTrue($bmp['m:halo_jumps::0']['measured']);
        self::assertArrayNotHasKey('q:7', $bmp);

        // Déjà en place : plus proposé.
        $rule = Eng::normalize(['op' => 'all', 'groups' => [['op' => 'all', 'items' => [['type' => 'metric', 'metric' => 'halo_jumps', 'min' => 8]]]]]);
        self::assertArrayNotHasKey('m:halo_jumps::0', array_column(Sug::suggest($defs[0], ['rule' => $rule], [], self::ctx($defs)), null, 'id'));
        self::assertArrayNotHasKey('q:7', array_column(Sug::suggest($defs[1], ['rule' => []], [7], self::ctx($defs)), null, 'id'));

        // Qualification citée pas encore importée : proposée, désactivée.
        $only = [$defs[1]];
        $missing = array_column(Sug::suggest($defs[1], ['rule' => []], [], self::ctx($only)), null, 'id');
        self::assertFalse($missing['qc:fr-brevet-parachutiste']['available']);
        self::assertFalse($missing['qc:fr-brevet-parachutiste']['checked']);
    }

    public function testSuggestionsForACommunityQualificationReadItsDescription(): void
    {
        $defs = [
            ['id' => 3, 'code' => 'SC1', 'name' => 'Sauvetage au combat niveau 1', 'short_name' => 'SC1'],
            ['id' => 4, 'code' => 'HALO', 'name' => 'Chuteur HALO', 'short_name' => 'HALO',
                'description' => "Chuteur opérationnel de la communauté.\nPrérequis : Sauvetage au combat niveau 1 ; au moins 20 sauts en chute libre ; grade de sergent au minimum ; 2 ans de service ; 10 heures de vol"],
        ];
        $grades = [['id' => 90, 'label_short' => 'SGT', 'label_long' => 'Sergent', 'label_otan' => 'OR-5'], ['id' => 91, 'label_short' => 'CPL', 'label_long' => 'Caporal', 'label_otan' => 'OR-3']];
        $s = array_column(Sug::suggest($defs[1], ['rule' => [], 'prereq_grade_id' => null], [], self::ctx($defs, $grades)), null, 'id');
        self::assertSame(3, $s['q:3']['definition_id']);
        self::assertSame(20.0, $s['a:freefall_jumps']['value']);
        self::assertFalse($s['a:freefall_jumps']['measured'], 'chute libre : attestée par un instructeur');
        self::assertSame(90, $s['g']['grade_id']);
        self::assertSame(730.0, $s['m:seniority_days::0']['value']);
        self::assertSame(10.0, $s['m:flight_hours::0']['value']);
        foreach ($s as $one) {
            self::assertTrue($one['checked'], $one['id']);
        }
        // Grade introuvable dans le système de la communauté : proposé, pas cochable.
        $none = array_column(Sug::suggest($defs[1], ['rule' => []], [], self::ctx($defs, [])), null, 'id');
        self::assertFalse($none['g']['available']);
    }

    public function testMergeAddsRequirementsWithoutChangingAlternatives(): void
    {
        $item = ['type' => 'metric', 'metric' => 'halo_jumps', 'scope' => '', 'min' => 5.0, 'window_days' => 0];
        $merged = Sug::mergeIntoRule([], [$item]);
        self::assertSame('all', $merged['op']);
        self::assertSame([$item], $merged['groups'][0]['items']);
        $course = Eng::normalize(['op' => 'all', 'groups' => [['op' => 'any', 'items' => [['type' => 'academy', 'course_key' => 'jtac'], ['type' => 'academy', 'course_id' => 4]]]]]);
        $m = Sug::mergeIntoRule($course, [$item]);
        self::assertCount(2, $m['groups'], '« une de ces formations » ET 5 sauts');
        self::assertSame('any', $m['groups'][0]['op']);
        $alt = Eng::normalize(['op' => 'any', 'groups' => [['op' => 'all', 'items' => [['type' => 'academy', 'course_id' => 4]]], ['op' => 'all', 'items' => [['type' => 'metric', 'metric' => 'missions_played', 'min' => 10]]]]]);
        self::assertNull(Sug::mergeIntoRule($alt, [$item]), 'alternatives : ajout à faire à la main');
    }

    public function testAttestationCriteriaAreNormalizedEvaluatedAndDescribed(): void
    {
        self::assertNull(Eng::normalizeCriterion(['type' => 'attestation', 'key' => 'freefall_jumps', 'min' => 0]), 'nombre attesté sans seuil');
        $c = Eng::normalizeCriterion(['type' => 'attestation', 'key' => 'freefall_jumps', 'min' => '50', 'label' => '']);
        self::assertSame(['type' => 'attestation', 'key' => 'freefall_jumps', 'label' => 'Sauts en chute libre', 'min' => 50.0], $c);
        $test = Eng::normalizeCriterion(['type' => 'attestation', 'key' => 'inconnu', 'label' => 'Tir de qualification à 300 m']);
        self::assertSame('other', $test['key']);
        self::assertSame(0.0, $test['min']);
        $rule = Eng::normalize(['items' => [$c, $test]]);
        $eval = static fn (array $values): array => Eng::evaluate($rule, static fn (): float => 0.0, static fn (): bool => false, static fn (): bool => false,
            static fn (array $x): ?float => $values[Cat::attestationStoreKey($x, 12)] ?? null);
        self::assertFalse($eval([])['met']);
        self::assertSame(50, $eval(['freefall_jumps' => 25.0])['groups'][0]['items'][0]['pct']);
        self::assertTrue($eval(['freefall_jumps' => 60.0, Cat::attestationStoreKey($test, 12) => 1.0])['met']);
        self::assertStringStartsWith('q12:other:', Cat::attestationStoreKey($test, 12), 'épreuve propre à la qualification');
        self::assertSame('freefall_jumps', Cat::attestationStoreKey($c, 12), 'nombre de sauts propre au membre');
        self::assertSame('50 sauts — Sauts en chute libre (attesté par un instructeur)', Cat::describe($c));
        self::assertContains(Cat::MODE_AUTO, Eng::modes(['allow_manual' => 1, 'auto_enabled' => 1, 'rule_json' => json_encode($rule)], false));
        self::assertSame('Sauts en parachute', Cat::metricLabel('halo_jumps'));
        self::assertTrue(Cat::isMetric('seniority_days'));
    }

    /** Service de délivrance branché sur des dépôts simulés. */
    private function service(array $members, array $metric, array $attest, array $settingsRow, array &$awarded, array $grades = []): QualificationAwardRuleService
    {
        $repo = $this->createMock(QualificationAwardRuleRepository::class);
        $repo->method('schemaReady')->willReturn(true);
        $repo->method('attestationsReady')->willReturn(true);
        $repo->method('find')->willReturn($settingsRow);
        $repo->method('listForTenant')->willReturn([10 => $settingsRow]);
        $repo->method('memberDetails')->willReturn(array_map(static fn (array $m): array => ['unit_id' => 0, 'unit_name' => '', 'unit_short' => '', 'grade_id' => $m['grade_id'] ?? 0, 'steam_id' => ''], $members));
        $repo->method('attestationsByUser')->willReturn($attest);
        $repo->method('metricValues')->willReturnCallback(static fn (int $t, string $m): array => $metric[$m] ?? []);
        $defs = $this->createMock(QualificationDefinitionRepository::class);
        $defs->method('listForTenant')->willReturn([['id' => 10, 'name' => 'Chuteur opérationnel', 'code' => 'CHUT']]);
        $users = $this->createMock(UserRepository::class);
        $users->method('listForTenant')->willReturn(array_map(static fn (int $id): array => ['id' => $id, 'status' => 'active', 'display_name' => 'M' . $id], array_keys($members)));
        $transitions = $this->createMock(QualificationStatusTransitionService::class);
        $transitions->method('award')->willReturnCallback(static function (int $t, int $uid, array $data) use (&$awarded): int {
            $awarded[$uid] = $data;

            return 1;
        });
        $gradeRepo = $this->createMock(GradeRepository::class);
        $gradeRepo->method('listForTenant')->willReturn($grades);

        return new QualificationAwardRuleService($repo, $defs, $this->createMock(PilotQualificationRuleRepository::class), $transitions,
            $this->createMock(QualificationTemporalStatusService::class), $this->createMock(QualificationAwardRepository::class), $users, null, $gradeRepo, null);
    }

    public function testAutomaticAwardUsesJumpsSeniorityRankAndInstructorAttestations(): void
    {
        $rule = Eng::normalize(['op' => 'all', 'groups' => [['op' => 'all', 'items' => [
            ['type' => 'metric', 'metric' => 'halo_jumps', 'min' => 5],
            ['type' => 'metric', 'metric' => 'seniority_days', 'min' => 365],
            ['type' => 'attestation', 'key' => 'freefall_jumps', 'min' => 50],
        ]]]]);
        $row = ['definition_id' => 10, 'allow_manual' => 1, 'auto_enabled' => 1, 'auto_mode' => Cat::AWARD_DIRECT, 'rule_json' => json_encode($rule), 'prereq_grade_id' => 3, 'prereq_job_role_id' => null];
        $grades = [['id' => 2, 'label_short' => 'CPL', 'sort_order' => 2, 'grade_system_id' => 1], ['id' => 3, 'label_short' => 'SGC', 'sort_order' => 5, 'grade_system_id' => 1]];
        $members = [1 => ['grade_id' => 3], 2 => ['grade_id' => 3], 3 => ['grade_id' => 3], 4 => ['grade_id' => 2]];
        $metric = ['halo_jumps' => [1 => 6.0, 2 => 6.0, 3 => 6.0, 4 => 9.0], 'seniority_days' => [1 => 400.0, 2 => 400.0, 3 => 100.0, 4 => 900.0]];
        $attest = [1 => ['freefall_jumps' => ['value' => 60.0]], 3 => ['freefall_jumps' => ['value' => 70.0]], 4 => ['freefall_jumps' => ['value' => 80.0]]];
        $awarded = [];
        $svc = $this->service($members, $metric, $attest, $row, $awarded, $grades);
        $stats = $svc->runAuto(1, 10);
        self::assertSame([1], array_keys($awarded), '2 : pas d’attestation ; 3 : ancienneté trop courte ; 4 : grade trop bas');
        self::assertSame(1, $stats['awarded']);
        self::assertSame(QualificationAdminStatus::OBTAINED, $awarded[1]['admin_status']);
        self::assertStringContainsString('Sauts en chute libre (attesté par un instructeur)', $awarded[1]['notes']);

        $ev = $svc->evaluateMember(1, 10, $svc->settings(1, 10), 2);
        self::assertFalse($ev['met']);
        self::assertSame(0, $ev['eval']['groups'][0]['items'][2]['pct']);
        $list = $svc->attestationCriteria(1, 10);
        self::assertSame('freefall_jumps', $list[0]['store_key']);
        self::assertSame([1, 3, 4], array_keys($list[0]['attested']));
    }

    public function testSeniorityIsMeasuredFromEnlistmentThenArrival(): void
    {
        $now = strtotime('2026-11-03 12:00:00');
        self::assertSame(365, \App\Repositories\QualificationAwardRuleRepository::daysSince(['2025-11-03', '2020-01-01'], $now), 'date d’engagement d’abord');
        self::assertSame(2, \App\Repositories\QualificationAwardRuleRepository::daysSince([null, '0000-00-00', '2026-11-01 08:00:00'], $now));
        self::assertNull(\App\Repositories\QualificationAwardRuleRepository::daysSince([null, '2027-01-01'], $now));
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, created_at TEXT)');
        $pdo->exec('CREATE TABLE personnel_profiles (user_id INTEGER, enlistment_date TEXT)');
        $pdo->exec("INSERT INTO users VALUES (1, 1, '2024-01-01 10:00:00'), (2, 1, '2026-10-01 10:00:00'), (3, 2, '2020-01-01')");
        $pdo->exec("INSERT INTO personnel_profiles VALUES (1, '2025-11-03')");
        $days = (new QualificationAwardRuleRepository($pdo))->seniorityDays(1, $now);
        self::assertSame([1 => 365.0, 2 => 33.0], $days);
    }

    public function testApplyingSelectedSuggestionsUpdatesRuleRankAndPrerequisites(): void
    {
        $def = ['id' => 4, 'code' => 'HALO', 'name' => 'Chuteur HALO', 'short_name' => 'HALO',
            'description' => 'Prérequis : Sauvetage au combat niveau 1 ; au moins 20 sauts en chute libre ; grade de sergent au minimum ; 5 sauts'];
        $rules = $this->createMock(QualificationAwardRuleService::class);
        $rules->method('schemaReady')->willReturn(true);
        $rules->method('settings')->willReturn(QualificationAwardRuleService::defaults() + ['rule' => []]);
        $rules->method('names')->willReturn(['courses' => [], 'builtin' => [], 'definitions' => [3 => 'Sauvetage au combat niveau 1', 4 => 'Chuteur HALO']]);
        $saved = null;
        $rules->expects(self::once())->method('saveFromInput')->willReturnCallback(static function (int $t, int $id, array $in) use (&$saved): void {
            $saved = $in;
        });
        $defs = $this->createMock(QualificationDefinitionRepository::class);
        $defs->method('find')->willReturn($def);
        $defs->method('listForTenant')->willReturn([['id' => 3, 'code' => 'SC1', 'name' => 'Sauvetage au combat niveau 1', 'short_name' => 'SC1'], $def]);
        $ref = $this->createMock(QualificationReferentielRepository::class);
        $ref->expects(self::once())->method('addPrerequisite')->with(1, 4, 3, null, 'obtention');
        $grades = $this->createMock(GradeRepository::class);
        $grades->method('listForTenant')->willReturn([['id' => 90, 'label_short' => 'SGT', 'label_long' => 'Sergent', 'label_otan' => 'OR-5']]);
        $sug = new Sug($rules, $this->createMock(QualificationAwardRuleRepository::class), $defs, $ref, $grades);

        $r = $sug->apply(1, 4, ['a:freefall_jumps' => '30', 'g' => null, 'q:3' => null, 'm:halo_jumps::0' => '', 'inconnu' => '1'], 7);
        self::assertSame(['added' => 4, 'skipped' => 1], $r);
        self::assertSame(90, $saved['prereq_grade_id']);
        self::assertSame(1, $saved['auto_enabled']);
        self::assertSame(Cat::AWARD_PROPOSE, $saved['auto_mode'], 'sans règle avant : proposée, un responsable valide');
        self::assertSame(1, $saved['allow_manual']);
        $items = Eng::normalize($saved['rule_json'])['groups'][0]['items'];
        self::assertSame(30.0, $items[0]['min'], 'valeur corrigée à la main');
        self::assertSame('halo_jumps', $items[1]['metric']);
        self::assertSame(5.0, $items[1]['min'], 'valeur détectée gardée');
    }

    public function testMigrationCreatesTheAttestationTableOnce(): void
    {
        $pdo = new MysqlDdlSqlitePdo();
        $run = static function () use ($pdo): string {
            $root = self::ROOT;
            ob_start();
            require $root . '/database/steps/3865_qualification_attestations.php';

            return (string) ob_get_clean();
        };
        self::assertStringContainsString('créée', $run());
        self::assertStringContainsString('déjà présente', $run());
        $repo = new QualificationAwardRuleRepository($pdo);
        self::assertTrue($repo->attestationsReady());
        $repo->saveAttestation(1, 5, 'freefall_jumps', 40, 'Carnet de sauts', 9);
        $repo->saveAttestation(1, 5, 'freefall_jumps', 55, 'Carnet à jour', 9);
        $all = $repo->attestationsByUser(1);
        self::assertSame(55.0, $all[5]['freefall_jumps']['value'], 'une ligne par membre et par clé');
        self::assertSame('Carnet à jour', $all[5]['freefall_jumps']['note']);
        $repo->deleteAttestation(1, 5, 'freefall_jumps');
        self::assertSame([], $repo->attestationsByUser(1));
    }

    public function testRoutesContainerAndMigrationAreWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . '/routes/web.php');
        foreach (["qualifications/prerequis-detectes', [\\App\\Controllers\\Admin\\Organization\\QualificationPrerequisiteController::class, 'index']",
            "qualifications/prerequis-detectes', [\\App\\Controllers\\Admin\\Organization\\QualificationPrerequisiteController::class, 'applyMany']",
            "qualifications/{id}/prerequis-detectes'", "qualifications/{id}/attestations'"] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        // L'aperçu se déclare avant la fiche /{id} qui le masquerait.
        self::assertLessThan(strpos($routes, "QualificationRulesController::class, 'show'"), strpos($routes, "QualificationPrerequisiteController::class, 'index'"));
        $container = (string) file_get_contents(self::ROOT . '/app/Core/Container.php');
        self::assertStringContainsString('\App\Services\Personnel\QualificationPrerequisiteSuggester::class => new', $container);
        self::assertStringContainsString('\App\Controllers\Admin\Organization\QualificationPrerequisiteController::class => new', $container);
        $registry = require self::ROOT . '/database/steps/registry.php';
        $ids = array_column($registry, 'file', 'id');
        self::assertSame('3865_qualification_attestations.php', $ids['qualification_attestations'] ?? null);
        self::assertSame(1, count(array_filter(array_column($registry, 'file'), static fn (string $f): bool => str_starts_with($f, '3865_'))));
    }

    public function testEditorsStudioImportAndRuleBuilderOfferTheDetectedPrerequisites(): void
    {
        $form = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/form.php');
        self::assertStringContainsString('id="prerequis-detectes"', $form);
        self::assertStringContainsString("/_detected_prerequisites.php", $form);
        $studio = (string) file_get_contents(self::ROOT . '/views/academy/studio_credentials.php');
        self::assertStringContainsString("action/studio_cred_prereqs", $studio);
        self::assertStringContainsString('conditions[attestations]', $studio);
        $academy = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/AcademyController.php');
        self::assertStringContainsString("'studio_cred_prereqs'", $academy);
        $show = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/show.php');
        self::assertStringContainsString("/attestations'", $show);
        $index = (string) file_get_contents(self::ROOT . '/views/admin/organization/qualifications/index.php');
        self::assertStringContainsString('qualifications/prerequis-detectes', $index);
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/QualificationReferentielController.php');
        self::assertStringContainsString('apres_import=1', $ctrl);
        self::assertStringContainsString("'prerequisites_url'", $ctrl);
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/qualification-rule-builder.js');
        self::assertStringContainsString("type: 'attestation'", $js);
        self::assertStringContainsString('prerequisites_url', (string) file_get_contents(self::ROOT . '/public/assets/js/qualification-sheet.js'));
        // Studio : une condition attestée survit à l'enregistrement du formulaire.
        $rule = \App\Services\Academy\AcademyStudioCredentials::ruleFromForm(['attestations' => [['keep' => '1', 'key' => 'freefall_jumps', 'label' => '', 'min' => '50'], ['key' => 'marksmanship', 'label' => 'Tir']]]);
        self::assertSame([['type' => 'attestation', 'key' => 'freefall_jumps', 'label' => 'Sauts en chute libre', 'min' => 50.0]], $rule['groups'][0]['items']);
        self::assertCount(1, \App\Services\Academy\AcademyStudioCredentials::formFromRule($rule)['attestations']);
    }
}
