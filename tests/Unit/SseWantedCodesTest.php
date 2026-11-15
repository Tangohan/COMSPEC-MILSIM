<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\SseWantedSettingsRepository;
use App\Support\SseWantedCatalog;
use App\Support\SseWantedCodes;
use App\Support\SseWantedPhysical;
use PHPUnit\Framework\TestCase;

/**
 * Codification des avis de recherche : catalogue livré (config/sse_wanted_codes.php), composition du texte, personnalisation par
 * communauté (réglages, sans migration), forme stockée et exposée, signalement physique. Aucune base de données.
 */
final class SseWantedCodesTest extends TestCase
{
    protected function tearDown(): void
    {
        SseWantedCodes::useCatalogue(null);
    }

    /** @return array<string, mixed> */
    private static function cat(): array
    {
        return SseWantedCodes::catalogue();
    }

    // ------------------------------------------------------------------ catalogue

    public function testCatalogueHasAboutFortyReasonsAndTwentyFiveMeasuresWithStableUniqueCodes(): void
    {
        $cat = self::cat();
        self::assertGreaterThanOrEqual(40, count($cat['reasons']));
        self::assertGreaterThanOrEqual(25, count($cat['measures']));
        $all = array_merge(array_keys($cat['reasons']), array_keys($cat['measures']));
        self::assertSame(count($all), count(array_unique($all)), 'codes uniques');
        foreach (array_keys($cat['reasons']) as $code) {
            self::assertMatchesRegularExpression('/^M-[A-Z]{3}-\d{2}$/', $code);
            self::assertSame(SseWantedCodes::REASONS, SseWantedCodes::kindOf($code));
        }
        foreach (array_keys($cat['measures']) as $code) {
            self::assertMatchesRegularExpression('/^A-[A-Z]{3}$/', $code);
            self::assertSame(SseWantedCodes::MEASURES, SseWantedCodes::kindOf($code));
        }
        // Les codes cités par l'énoncé existent.
        foreach (['M-TER-01', 'M-TRF-02', 'M-ESP-01', 'M-VIO-03'] as $code) {
            self::assertArrayHasKey($code, $cat['reasons']);
        }
        foreach (['A-OBS', 'A-NOI', 'A-ARR', 'A-FRK', 'A-REP', 'A-DNG', 'A-EXF'] as $code) {
            self::assertArrayHasKey($code, $cat['measures']);
        }
    }

    public function testEveryCodeIsCompleteInBothLanguagesWithAFamilyAndAThreat(): void
    {
        $cat = self::cat();
        foreach (['reasons' => 'reason_families', 'measures' => 'measure_families'] as $kind => $families) {
            foreach ($cat[$kind] as $code => $c) {
                foreach (['fr', 'en', 'text_fr', 'text_en'] as $field) {
                    self::assertNotSame('', trim((string) ($c[$field] ?? '')), $code . ' : ' . $field);
                }
                self::assertArrayHasKey($c['family'], $cat[$families], $code . ' : famille inconnue');
                self::assertArrayHasKey($c['threat'], SseWantedCatalog::THREATS, $code . ' : menace');
                self::assertLessThanOrEqual(80, mb_strlen($c['fr']), $code);
                self::assertLessThanOrEqual(80, mb_strlen($c['en']), $code);
            }
        }
        foreach ($cat['reasons'] as $code => $c) {
            self::assertSame($c['family'], substr($code, 2, 3), 'la famille se lit dans le code ' . $code);
        }
        foreach (array_merge($cat['reason_families'], $cat['measure_families']) as $fam) {
            self::assertNotSame('', $fam['fr']);
            self::assertNotSame('', $fam['en']);
        }
        self::assertGreaterThanOrEqual(6, count($cat['reason_families']));
    }

    public function testNoCivilPoliceVocabularyAnywhereInTheCatalogue(): void
    {
        $banned = '/\b(police|policier|policiers|gendarm\w*|commissariat|garde à vue|interpell\w*|procureur|magistrat\w*|tribunal|tribunaux|infraction\w*|délit\w*|sheriff|cops?|law enforcement|police officer|detective|arrest warrant|mandat d’arrêt|prison|préfecture|brigade)\b/iu';
        $strings = [];
        $catalogue = self::cat();
        array_walk_recursive($catalogue, static function ($v) use (&$strings): void {
            if (is_string($v)) {
                $strings[] = $v;
            }
        });
        self::assertGreaterThan(200, count($strings));
        foreach ($strings as $s) {
            self::assertSame(0, preg_match($banned, $s), 'Vocabulaire de force de l’ordre civile : « ' . $s . ' »');
        }
        // Le vocabulaire des forces spéciales est bien là.
        $joined = implode(' ', $strings);
        foreach (['HVT', 'EEI', 'ISR', 'QRF', 'coalition'] as $word) {
            self::assertStringContainsString($word, $joined);
        }
    }

    public function testTemplatesAndTypeDefaultsOnlyCiteExistingCodesAndTypes(): void
    {
        $cat = self::cat();
        $known = array_merge(array_keys($cat['reasons']), array_keys($cat['measures']));
        self::assertGreaterThanOrEqual(8, count($cat['templates']));
        foreach (['cell_financier', 'smuggler', 'cell_leader'] as $key) {
            self::assertArrayHasKey($key, $cat['templates']);
        }
        foreach ($cat['templates'] as $key => $t) {
            self::assertNotSame('', $t['fr']);
            self::assertNotSame('', $t['en']);
            self::assertArrayHasKey($t['threat'], SseWantedCatalog::THREATS, $key);
            self::assertArrayHasKey($t['armed'], SseWantedCatalog::ARMED, $key);
            self::assertArrayHasKey($t['type'], SseWantedCatalog::TYPES, $key);
            foreach (array_merge($t['reasons'], $t['measures']) as $code) {
                self::assertContains($code, $known, $key . ' cite ' . $code);
            }
            foreach ($t['reasons'] as $code) {
                self::assertSame(SseWantedCodes::REASONS, SseWantedCodes::kindOf($code));
            }
            foreach ($t['measures'] as $code) {
                self::assertSame(SseWantedCodes::MEASURES, SseWantedCodes::kindOf($code));
            }
        }
        foreach (array_keys(SseWantedCatalog::TYPES) as $type) {
            self::assertArrayHasKey($type, $cat['type_defaults'], 'valeurs par défaut pour ' . $type);
            foreach (array_merge($cat['type_defaults'][$type]['reasons'], $cat['type_defaults'][$type]['measures']) as $code) {
                self::assertContains($code, $known);
            }
        }
        // « Personne d'intérêt » préremplit un motif d'intérêt et A-OBS.
        $poi = SseWantedCodes::defaultsForType('poi', []);
        self::assertContains('A-OBS', $poi['measures']);
        self::assertSame(['M-INT-01'], $poi['reasons']);
    }

    // ------------------------------------------------------------------ préremplissage et concaténation

    public function testComposeConcatenatesSentencesCleanlyInTheRequestedLanguage(): void
    {
        $fr = SseWantedCodes::compose(['M-TER-01', 'M-INS-01'], SseWantedCodes::REASONS, 'fr', []);
        self::assertSame(
            'Planifie une attaque contre des forces de la coalition ou des civils : reconnaissance d’objectifs, répartition des rôles et choix du moment. Commande une cellule insurgée et donne les ordres d’opération à ses éléments.',
            $fr
        );
        self::assertStringNotContainsString('  ', $fr);
        $en = SseWantedCodes::compose(['A-OBS', 'A-NOI'], SseWantedCodes::MEASURES, 'en', []);
        self::assertSame('Observe without being detected and report to the requesting service. Do not engage alone: wait for reinforcements and orders from the chain of command.', $en);
        self::assertSame('', SseWantedCodes::compose([], SseWantedCodes::REASONS, 'fr', []));
        self::assertSame('', SseWantedCodes::compose(['M-ZZZ-99', 'n’importe quoi'], SseWantedCodes::REASONS, 'fr', []));
    }

    public function testComposeNeverCutsASentenceAndStaysUnderTheLimit(): void
    {
        $all = array_keys(self::cat()['reasons']);
        $text = SseWantedCodes::compose($all, SseWantedCodes::REASONS, 'fr', [], 300);
        self::assertLessThanOrEqual(300, mb_strlen($text));
        self::assertMatchesRegularExpression('/[.!?]$/u', $text);
        // Le texte de tous les motifs tient dans la limite d'un avis ou s'arrête proprement.
        $full = SseWantedCodes::compose($all, SseWantedCodes::REASONS, 'fr', []);
        self::assertLessThanOrEqual(SseWantedCatalog::REASON_MAX, mb_strlen($full));
        self::assertLessThanOrEqual(SseWantedCatalog::INSTRUCTIONS_MAX, mb_strlen(SseWantedCodes::compose(array_keys(self::cat()['measures']), SseWantedCodes::MEASURES, 'fr', [])));
    }

    public function testSelectionKeepsKnownActiveUniqueCodesAndIsBounded(): void
    {
        $config = ['codes' => ['disabled' => ['M-TER-06']]];
        $sel = SseWantedCodes::normalizeSelection(['reasons' => ['m-ter-01', 'M-TER-01', 'M-XXX-99', 'M-TER-06', 'A-OBS', 42], 'measures' => 'A-OBS, A-NOI;A-REP A-OBS'], $config);
        self::assertSame(['M-TER-01'], $sel['reasons'], 'inconnu, doublon, désactivé et mauvaise catégorie écartés');
        self::assertSame(['A-OBS', 'A-NOI', 'A-REP'], $sel['measures']);
        // Un code désactivé depuis reste lisible dans l'avis qui le portait déjà.
        $kept = SseWantedCodes::normalizeSelection(['reasons' => ['M-TER-06']], $config, ['reasons' => ['M-TER-06']]);
        self::assertSame(['M-TER-06'], $kept['reasons']);
        $many = SseWantedCodes::normalizeSelection(['reasons' => array_keys(self::cat()['reasons'])], []);
        self::assertCount(SseWantedCodes::MAX_SELECTED, $many['reasons']);
    }

    // ------------------------------------------------------------------ personnalisation par communauté

    public function testCommunityCanDisableRenameReorderAndAddItsOwnCodes(): void
    {
        $form = SseWantedCodes::fromForm([
            'present' => '1',
            'rows' => ['M-TER-01', 'M-TER-02', 'M-INS-01', 'A-OBS', 'A-NOI'],
            'enabled' => ['M-TER-01', 'M-INS-01', 'A-OBS', 'A-NOI'],
            'fr' => ['M-TER-01' => 'Projet d’attentat', 'A-OBS' => 'Surveiller et rapporter'],
            'en' => ['M-TER-01' => 'Bomb plot', 'A-OBS' => 'Watch and report'],
            'rank' => ['M-INS-01' => '1', 'M-TER-01' => '1'],
            'custom_codes' => "M-TER-90|Réseau de passeurs|Smuggling ring|Anime un réseau de passeurs.|Runs a smuggling ring.|high\nA-ZZZ|Vérifier le laissez-passer|Check the pass",
            'defaults_reasons' => ['poi' => 'M-INS-01, M-TER-90'],
            'defaults_measures' => ['poi' => 'A-ZZZ A-NOI'],
            'templates_enabled' => ['smuggler'],
            'custom_templates' => 'courrier|Courrier de cellule|Cell courier|surveillance|medium|unknown|M-TER-90|A-OBS A-ZZZ',
        ]);
        self::assertSame([], $form['errors']);
        $config = SseWantedSettingsRepository::normalize(['codes' => $form['settings']]);

        $reasons = SseWantedCodes::active($config, SseWantedCodes::REASONS);
        self::assertArrayNotHasKey('M-TER-02', $reasons, 'désactivé');
        self::assertArrayHasKey('M-TER-90', $reasons, 'code propre ajouté');
        self::assertSame('Projet d’attentat', $reasons['M-TER-01']['fr']);
        self::assertSame('Bomb plot', $reasons['M-TER-01']['en']);
        self::assertStringStartsWith('Planifie', $reasons['M-TER-01']['text_fr'], 'seul le libellé change, pas le texte');
        self::assertSame('M-INS-01', array_key_first($reasons), 'rang explicite : passe devant à égalité');
        self::assertSame('M-TER-01', array_keys($reasons)[1]);
        self::assertSame('Surveiller et rapporter', SseWantedCodes::find('A-OBS', $config)['fr']);
        self::assertSame('Vérifier le laissez-passer', SseWantedCodes::active($config, SseWantedCodes::MEASURES)['A-ZZZ']['fr']);
        self::assertSame('Check the pass', SseWantedCodes::find('A-ZZZ', $config)['en']);
        self::assertFalse(SseWantedCodes::find('M-TER-02', $config)['enabled'], 'toujours lisible');

        $defaults = SseWantedCodes::defaultsForType('poi', $config);
        self::assertSame(['M-INS-01', 'M-TER-90'], $defaults['reasons']);
        self::assertSame(['A-ZZZ', 'A-NOI'], $defaults['measures']);
        self::assertSame(['M-INT-01'], SseWantedCodes::defaultsForType('poi', [])['reasons'], 'autre communauté : catalogue livré');

        $templates = SseWantedCodes::templates($config);
        self::assertSame(['smuggler', 'courrier'], array_keys($templates));
        self::assertSame(['M-TER-90'], $templates['courrier']['reasons']);

        // Le texte se compose avec le texte du code propre.
        self::assertSame('Anime un réseau de passeurs.', SseWantedCodes::compose(['M-TER-90'], SseWantedCodes::REASONS, 'fr', $config));
        // Les réglages font l'aller-retour par la colonne JSON existante.
        $again = SseWantedSettingsRepository::normalize(json_decode((string) json_encode($config, JSON_UNESCAPED_UNICODE), true));
        self::assertSame($config['codes'], $again['codes']);
    }

    public function testSettingsFormRefusesBadCustomCodesAndRealOrganisations(): void
    {
        $bad = SseWantedCodes::fromForm([
            'present' => '1',
            'custom_codes' => "X-1|Mauvais\nM-TER-01|Doublon du catalogue|x\nM-ABC-01|\nM-ABC-02|Réseau soutenu par Interpol|Interpol network|texte|text|low",
            'defaults_reasons' => ['poi' => 'M-NOPE-01'],
            'custom_templates' => 'Mauvaise clé|x',
        ]);
        self::assertGreaterThanOrEqual(5, count($bad['errors']));
        $config = SseWantedSettingsRepository::normalize(['codes' => $bad['settings']]);
        self::assertArrayNotHasKey('M-ABC-01', SseWantedCodes::all($config, SseWantedCodes::REASONS));

        // Sans l'onglet (ancien formulaire), rien n'est perdu : les réglages en place sont conservés par l'écran.
        self::assertSame([], SseWantedSettingsRepository::normalize([])['codes']['disabled']);
        $kept = SseWantedSettingsRepository::normalize(['codes' => ['disabled' => ['M-TER-02'], 'custom' => ['M-ABC-05' => ['fr' => 'Mon code', 'en' => 'My code']]]]);
        self::assertSame(['M-TER-02'], $kept['codes']['disabled']);
        self::assertSame('Mon code', $kept['codes']['custom']['M-ABC-05']['fr']);
        self::assertSame('Mon code.', $kept['codes']['custom']['M-ABC-05']['text_fr'], 'texte par défaut : le libellé');
    }

    // ------------------------------------------------------------------ forme stockée et exposée

    public function testSnapshotKeepsTheLabelsOfThePlacementDayAndExportIsStable(): void
    {
        $config = SseWantedSettingsRepository::normalize(['codes' => ['renamed' => ['M-TER-01' => ['Ancien libellé', 'Old label']]]]);
        $snap = SseWantedCodes::snapshot(['reasons' => ['M-TER-01'], 'measures' => ['A-OBS']], $config, 'cell_leader');
        self::assertSame(1, $snap['v']);
        self::assertSame([['code' => 'M-TER-01', 'fr' => 'Ancien libellé', 'en' => 'Old label']], $snap['reasons']);
        self::assertSame('cell_leader', $snap['template']);

        // Renommé ou supprimé plus tard : l'avis garde son libellé.
        $json = (string) json_encode($snap, JSON_UNESCAPED_UNICODE);
        $export = SseWantedCodes::export($json);
        self::assertSame(['reasons', 'measures'], array_keys($export));
        self::assertSame('Ancien libellé', $export['reasons'][0]['fr']);
        self::assertSame(['code', 'fr', 'en'], array_keys($export['measures'][0]));

        // Valeur absente ou abîmée : listes vides, jamais d'erreur.
        foreach ([null, '', 'pas du json', '{"reasons": "x"}', '[1,2]'] as $broken) {
            self::assertSame(['reasons' => [], 'measures' => []], SseWantedCodes::export($broken));
        }
        // Ancien format sans libellés : les codes seuls suffisent.
        self::assertSame('M-TER-01', SseWantedCodes::export('{"reasons":["M-TER-01","ZZZ"]}')['reasons'][0]['fr']);
    }

    public function testClientDataCarriesEveryActiveCodeWithBothLanguages(): void
    {
        $config = SseWantedSettingsRepository::normalize(['codes' => ['disabled' => ['M-TER-02']]]);
        $data = SseWantedCodes::clientData($config, 'fr');
        $codes = array_column($data['items'], 'c');
        self::assertContains('M-TER-01', $codes);
        self::assertNotContains('M-TER-02', $codes);
        $item = $data['items'][0];
        foreach (['k', 'c', 'f', 'fr', 'en', 'tf', 'te', 't'] as $key) {
            self::assertArrayHasKey($key, $item);
        }
        self::assertArrayHasKey('hvt', $data['defaults']);
        self::assertContains('cell_leader', array_column($data['templates'], 'key'));
        self::assertSame(['M-INT-01'], $data['defaults']['poi']['reasons']);
        self::assertSame(SseWantedCatalog::REASON_MAX, $data['reasonMax']);
    }

    public function testMatchingFindsCodesByCodeOrLabelInBothLanguages(): void
    {
        self::assertContains('M-TRF-02', SseWantedCodes::matching('passeur', []));
        self::assertContains('M-TRF-02', SseWantedCodes::matching('smuggler', []));
        self::assertContains('A-OBS', SseWantedCodes::matching('a-obs', []));
        self::assertSame([], SseWantedCodes::matching('x', []), 'une lettre : trop court');
        self::assertSame([], SseWantedCodes::matching('zzzzqqq', []));
    }

    // ------------------------------------------------------------------ signalement physique

    public function testPhysicalIsNormalizedBoundedAndReadable(): void
    {
        $p = SseWantedPhysical::normalize([
            'height_cm' => '182', 'weight_kg' => '9999', 'sex' => 'male', 'age' => '26_35', 'build' => 'athletic', 'hair' => 'inconnu',
            'eyes' => 'green', 'marks' => str_repeat('é', 500), 'tattoos' => "Dragon\navant-bras", 'equipment' => ['rifle', 'rifle', 'canon', 'radio'],
        ], '2026-11-15 10:00:00');
        self::assertSame(182, $p['height_cm']);
        self::assertArrayNotHasKey('weight_kg', $p, 'hors bornes');
        self::assertArrayNotHasKey('hair', $p, 'valeur hors liste');
        self::assertSame(240, mb_strlen($p['marks']));
        self::assertSame('Dragon avant-bras', $p['tattoos']);
        self::assertSame(['rifle', 'radio'], $p['equipment']);
        self::assertSame('2026-11-15 10:00:00', $p['snapshot_at']);
        self::assertNull(SseWantedPhysical::normalize(['hair' => 'x', 'height_cm' => '']));

        $line = SseWantedPhysical::shortLine($p, 'fr');
        self::assertSame('Homme · 26 à 35 ans · 182 cm · Athlétique · yeux verts', $line);
        self::assertSame('Male · 26 to 35 · 182 cm · Athletic · eyes green', SseWantedPhysical::shortLine($p, 'en'));
        $rows = SseWantedPhysical::rows($p, 'en');
        self::assertContains('Height', array_column($rows, 'label'));
        self::assertContains('Rifle, Handheld radio', array_column($rows, 'value'));
        $game = SseWantedPhysical::export($p);
        self::assertSame(80, mb_strlen($game['marks']), 'champs courts pour le jeu');
        self::assertSame($line, $game['summary_fr']);
        self::assertSame([], SseWantedPhysical::export([]));
        self::assertSame($p, SseWantedPhysical::read((string) json_encode($p)));
        self::assertSame([], SseWantedPhysical::read('abîmé'));
    }

    public function testPhysicalPrefillComesFromTheRecordButNeverForAMember(): void
    {
        $profile = ['height_cm' => 178, 'weight_kg' => 80, 'build' => 'medium', 'hair' => 'brown', 'eyes' => 'hazel', 'complexion' => 'olive', 'marks' => 'Cicatrice au sourcil gauche', 'country' => 'TAK'];
        $prefill = SseWantedPhysical::fromProfile($profile, false);
        self::assertSame(['height_cm' => 178, 'weight_kg' => 80, 'build' => 'medium', 'hair' => 'brown', 'eyes' => 'hazel', 'complexion' => 'olive', 'marks' => 'Cicatrice au sourcil gauche'], $prefill);
        self::assertSame([], SseWantedPhysical::fromProfile($profile, true), 'identité de membre : rien n’est copié');
        self::assertSame([], SseWantedPhysical::fromProfile(null, false));
        // Aucun état civil dans les champs du signalement.
        $fields = array_merge(['height_cm', 'weight_kg'], array_keys(SseWantedPhysical::lists()), array_keys(SseWantedPhysical::FREE), ['equipment']);
        foreach (['name', 'last_name', 'first_name', 'birth_date', 'address', 'nationality', 'user_id', 'steam_id'] as $civil) {
            self::assertNotContains($civil, $fields);
        }
    }
}
