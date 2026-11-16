<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\Detection\SseDetectionEngine;
use App\Services\Sse\Detection\SseDetectionLexicon;
use App\Services\Sse\Detection\SseTextNormalizer;
use App\Support\SseFieldNoteCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Cerbère-Lecture, le moteur seul, avec le VRAI lexique livré (config/sse_detection_lexicon.php) : mots entiers (jamais
 * « otage » dans « sabotage »), formes déclarées, faux amis, exclusions, contexte requis, négations et hypothèses, accents,
 * casse, ponctuation collée, apostrophes typographiques, anglais, seuils d'urgence, réglages d'une communauté, performance.
 */
final class SseDetectionEngineTest extends TestCase
{
    /** Le texte de la fiche signalée : « otage » était trouvé dans « sabotage ». */
    public const CAPTURE_TITLE = 'Panne de UGV Stomper RCWS · Drone';

    public const CAPTURE_BODY = 'Découverte de trace de manipulation pouvant être lié à du sabotage.';

    private static function engine(array $settings = []): SseDetectionEngine
    {
        return new SseDetectionEngine(new SseDetectionLexicon($settings));
    }

    /** @return array<string, array<string, mixed>> « cible:valeur » => détection */
    private static function detect(string $body, string $title = '', array $context = [], array $settings = []): array
    {
        $out = [];
        foreach (self::engine($settings)->detect(['title' => $title, 'body' => $body], $context)['detections'] as $d) {
            $out[$d['target'] . ':' . $d['value']] = $d;
        }

        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    private static function rules(string $body): array
    {
        return self::engine()->analyze(['body' => $body])['rules'];
    }

    public function testSabotageNeverTriggersHostage(): void
    {
        foreach (['sabotage', 'Sabotage du pont.', 'SABOTAGE!', 'un acte de sabotage', 'sabotages répétés'] as $text) {
            $rules = self::rules($text);
            self::assertArrayNotHasKey('R-03', $rules, $text);
            self::assertArrayNotHasKey('U-02', $rules, $text);
            self::assertArrayNotHasKey('theme:TERROR', self::detect($text), $text);
        }
    }

    public function testHostageWordsTriggerTheHostageRule(): void
    {
        foreach (['Prise d\'otages au marché', 'Otage détenu dans la grange', 'Ils gardent l’otage au sous-sol.', 'deux prises d’otages', 'Libération d\'otages', 'hostage taken near the bridge'] as $text) {
            $d = self::detect($text);
            self::assertArrayHasKey('theme:TERROR', $d, $text);
            self::assertContains('R-03', array_column($d['theme:TERROR']['rules'], 'id'), $text);
            self::assertSame('visible', $d['theme:TERROR']['tier'], $text);
        }
        // L'expression complète l'emporte sur le mot seul et pèse plus.
        $hits = self::rules('prise d’otages')['R-03']['hits'];
        self::assertCount(1, $hits);
        self::assertSame('prise d’otage', $hits[0]['term']);
        self::assertSame('prise d’otages', $hits[0]['snippet']['match']);
    }

    public function testFalseFriendsTriggerNothing(): void
    {
        foreach (['bagage', 'étage', 'pylône', 'ménage', 'potager', 'L’armée a perdu ses bagages au troisième étage du pylône.', 'Le surveillant du dortoir'] as $text) {
            $d = self::detect($text);
            self::assertArrayNotHasKey('theme:TERROR', $d, $text);
            self::assertArrayNotHasKey('theme:ARMEMENT', $d, $text);
            self::assertArrayNotHasKey('theme:INFRA', $d, $text);
            self::assertFalse(isset($d['urgency:critique']) || isset($d['urgency:urgent']), $text);
        }
        // « armée » n'est pas une « arme », avec ou sans accent.
        foreach (['armée', 'armee', 'ARMÉE', 'Les armées alliées'] as $text) {
            self::assertArrayNotHasKey('R-07', self::rules($text), $text);
        }
        // Une racine déclarée ne prend jamais un faux ami : « surveill* » prend surveillance, pas surveillant.
        self::assertSame(['surveillant'], self::rules('le surveillant')['R-15']['false_friends']);
        self::assertSame([], self::rules('le surveillant')['R-15']['hits']);
        self::assertNotEmpty(self::rules('surveillance du carrefour')['R-15']['hits']);
    }

    public function testNegationAndExerciseNeverRaiseUrgencyButMayProposeTheThemeWeakly(): void
    {
        foreach (['Pas de prise d\'otage signalée.', 'Aucun otage.', 'Exercice de prise d’otages demain matin.', 'Simulation d\'otage au camp.', 'no hostage seen'] as $text) {
            $d = self::detect($text);
            self::assertArrayNotHasKey('urgency:critique', $d, $text);
            self::assertArrayNotHasKey('urgency:urgent', $d, $text);
            self::assertTrue(!isset($d['theme:TERROR']) || $d['theme:TERROR']['tier'] !== 'visible', $text . ' : jamais une proposition sûre');
        }
        $hit = self::rules('Pas de prise d\'otage signalée.')['R-03']['hits'][0];
        self::assertSame('negation', $hit['context']);
        self::assertSame('pas de', $hit['cue']);
        self::assertSame('review', self::detect('Exercice de prise d’otages demain.')['theme:TERROR']['tier']);
        // La négation ne traverse pas une phrase.
        $d = self::detect('Pas de blessé. Prise d’otages en cours au village !');
        self::assertArrayHasKey('urgency:critique', $d);
    }

    public function testAccentsCasePunctuationApostrophesAndSpaces(): void
    {
        foreach (['Armes et munitions', 'ARMES, MUNITIONS.', '(armes)/munitions!', 'armes ; munitions'] as $text) {
            self::assertArrayHasKey('R-07', self::rules($text), $text);
        }
        self::assertArrayHasKey('R-10', self::rules('Dépôt de carburant'));
        self::assertArrayHasKey('R-10', self::rules('depot de carburant'));
        // Apostrophe typographique, apostrophe droite, espace insécable, accents décomposés.
        foreach (["prise d\u{2019}otages\u{00A0}!", "prise d'otages", "prise\u{00A0}d’otages", "Prise d\u{2019}OTAGES"] as $text) {
            self::assertArrayHasKey('R-03', self::rules($text), $text);
        }
        self::assertSame('armee', SseTextNormalizer::foldWord("arme\u{0301}e"));
        self::assertSame(['l', 'otage', 'a', 'l', 'etage'], array_column(SseTextNormalizer::tokens('L’otage à l\'étage'), 'w'));
        self::assertSame(['prise', 'd', 'otage(s)'], SseTextNormalizer::patternWords('prise d’otage(s)'));
    }

    public function testTheReportedCardProposesNeitherTerrorismNorCriticalButSabotage(): void
    {
        $d = self::detect(self::CAPTURE_BODY, self::CAPTURE_TITLE, ['kind' => 'FRT', 'themes' => []]);
        self::assertArrayNotHasKey('theme:TERROR', $d);
        self::assertArrayNotHasKey('urgency:critique', $d);
        self::assertArrayNotHasKey('urgency:urgent', $d);
        self::assertArrayHasKey('theme:SECUR', $d, 'le lexique classe le sabotage en Sécurité / Protection');
        self::assertSame('Sécurité / Protection', SseFieldNoteCatalog::themeLabel('SECUR'));
        self::assertSame(['R-14'], array_column($d['theme:SECUR']['rules'], 'id'));
        $matches = array_map(static fn (array $h): string => $h['snippet']['match'], $d['theme:SECUR']['rules'][0]['hits']);
        self::assertContains('sabotage', $matches);
        self::assertContains('trace de manipulation', $matches);
        self::assertGreaterThanOrEqual(55, $d['theme:SECUR']['confidence']);
    }

    public function testCriticalUrgencyNeedsAnImmediateSignalAndASecondOne(): void
    {
        self::assertArrayHasKey('urgency:urgent', self::detect('Attaque imminente sur le poste.'));
        self::assertArrayNotHasKey('urgency:critique', self::detect('Attaque imminente sur le poste.'));
        $d = self::detect('Attaque imminente sur le poste, deux blessés.');
        self::assertArrayHasKey('urgency:critique', $d);
        self::assertSame(['U-01', 'U-04'], array_values(array_intersect(['U-01', 'U-04'], array_column($d['urgency:critique']['rules'], 'id'))));
        self::assertArrayHasKey('urgency:critique', self::detect('Prise d’otages en cours au village'));
        self::assertArrayHasKey('urgency:critique', self::detect('Troops in contact, under fire, two wounded'));
        // Un thème grave seul ne fait pas une urgence.
        self::assertArrayNotHasKey('urgency:critique', self::detect('Rapport sur le terrorisme régional.'));
        self::assertArrayNotHasKey('urgency:urgent', self::detect('Rapport sur le terrorisme régional.'));
    }

    public function testExclusionsAndRequiredContext(): void
    {
        $r = self::rules('Barrage routier tenu par la milice.');
        self::assertSame([], $r['R-12']['hits']);
        self::assertSame('barrage routier', $r['R-12']['excluded'][0]['by']);
        self::assertNotEmpty($r['R-15']['hits'], 'le barrage routier est un point de contrôle');
        self::assertArrayNotHasKey('theme:CBRNE', self::detect('Livraison de bouteilles de gaz au village.'));
        $gas = self::rules('Odeur de gaz près du puits.')['R-06'];
        self::assertTrue($gas['missing_context']);
        self::assertSame(0, $gas['confidence']);
        self::assertArrayHasKey('theme:CBRNE', self::detect('Nuage de gaz toxique, les habitants portent un masque.'));
    }

    public function testShippedLexiconIsReadableData(): void
    {
        $shipped = SseDetectionLexicon::shipped();
        self::assertNotSame('', $shipped['version']);
        self::assertGreaterThan($shipped['thresholds']['review'], $shipped['thresholds']['visible']);
        self::assertArrayHasKey('sabotage', $shipped['false_friends']);
        foreach ($shipped['rules'] as $id => $r) {
            self::assertMatchesRegularExpression('/^[RUK]-\d{2}$/', $id);
            self::assertContains($r['target'], SseDetectionLexicon::TARGETS, $id);
            self::assertNotSame('', $r['explain'], $id);
            self::assertNotEmpty($r['terms'], $id);
            self::assertTrue($r['weight'] >= 0 && $r['weight'] <= 100, $id);
            match ($r['target']) {
                'theme' => self::assertArrayHasKey($r['value'], SseFieldNoteCatalog::THEMES, $id),
                'kind' => self::assertArrayHasKey($r['value'], SseFieldNoteCatalog::KINDS, $id),
                'urgency' => self::assertContains($r['level'], ['critique', 'urgent'], $id),
            };
            foreach (array_keys($r['terms']) as $term) {
                self::assertNotSame([], SseDetectionLexicon::compile($term), $id . ' : ' . $term);
            }
        }
    }

    public function testCommunitySettingsDisableReweightAndExclude(): void
    {
        $base = self::detect('Sabotage du groupe électrogène.')['theme:SECUR']['confidence'];
        self::assertArrayNotHasKey('theme:SECUR', self::detect('Sabotage du groupe électrogène.', '', [], ['R-14' => ['enabled' => false]]));
        $lower = self::detect('Sabotage du groupe électrogène.', '', [], ['R-14' => ['weight' => 30]]);
        self::assertTrue(!isset($lower['theme:SECUR']) || $lower['theme:SECUR']['confidence'] < $base);
        $excl = self::engine(['R-14' => ['exclusions' => ['sabotage du groupe']]])->analyze(['body' => 'Sabotage du groupe électrogène.'])['rules']['R-14'];
        self::assertSame([], $excl['hits']);
        self::assertSame('sabotage du groupe', $excl['excluded'][0]['by']);
        self::assertSame(['otage' => null, 'prise d’otage' => 85], SseDetectionLexicon::parseTerms("otage\n\nprise d’otage = 85\notage\n   "));
    }

    public function testTwentyThousandCharactersAreReadUnderFiftyMilliseconds(): void
    {
        $sentence = 'Le convoi se dirige vers le pont nord ; des hommes armés surveillent l’accès. Sabotage possible du dépôt de carburant, pas d’otage. ';
        $text = mb_substr(str_repeat($sentence, (int) ceil(20000 / mb_strlen($sentence))), 0, 20000);
        self::assertSame(20000, mb_strlen($text));
        $engine = self::engine();
        $engine->detect(['body' => 'chauffe']);
        $best = INF;
        for ($i = 0; $i < 3; $i++) {
            $t = hrtime(true);
            $r = $engine->detect(['body' => $text]);
            $best = min($best, (hrtime(true) - $t) / 1e6);
        }
        self::assertNotEmpty($r['detections']);
        self::assertLessThan(50.0, $best, sprintf('lecture de 20 000 caractères : %.1f ms', $best));
    }
}
