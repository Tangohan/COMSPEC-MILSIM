<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyBuiltInCourses;
use App\Services\Training\CompetencyPathwayGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Générateur de parcours de compétence : déterministe, ordonné (prérequis → cours → modules → exercices →
 * qualifications → validation), alimenté seulement par les données de la communauté.
 */
final class CompetencyPathwayGeneratorTest extends TestCase
{
    /** @return array<string, list<array<string, mixed>>> */
    private static function catalog(): array
    {
        return [
            'competencies' => [
                ['id' => 1, 'label' => 'Discipline radio', 'palier' => 'Formation initiale', 'palier_order' => 1, 'sort_order' => 0, 'acquisition_level' => 'Basique', 'validation_mode' => '', 'links' => []],
                ['id' => 2, 'label' => 'Tir de combat avancé', 'palier' => 'Formation initiale', 'palier_order' => 1, 'sort_order' => 1, 'acquisition_level' => 'Avancé', 'validation_mode' => '', 'links' => []],
                ['id' => 3, 'label' => 'Guidage JTAC', 'palier' => 'Spécialisation', 'palier_order' => 2, 'sort_order' => 0, 'acquisition_level' => 'Spécifique', 'validation_mode' => 'academy',
                    'links' => [['type' => 'academy_course', 'ref' => AcademyBuiltInCourses::JTAC_KEY], ['type' => 'module', 'ref' => '12'], ['type' => 'qualification', 'ref' => '7'], ['type' => 'job_role', 'ref' => '5']]],
                ['id' => 4, 'label' => 'Chef d’équipe', 'palier' => 'Encadrement', 'palier_order' => 3, 'sort_order' => 0, 'acquisition_level' => 'Intermédiaire', 'validation_mode' => 'instructor', 'links' => []],
            ],
            'academy_courses' => [
                ['ref' => AcademyBuiltInCourses::JTAC_KEY, 'title' => 'JTAC — Licence ATH-ACADEMY', 'duration_min' => 180, 'url' => '/academy/classroom?course=jtac'],
                ['ref' => 'course-medic', 'title' => 'Secourisme au combat', 'duration_min' => 60, 'url' => '/academy/classroom?course=course-medic'],
            ],
            'training_courses' => [
                ['ref' => '40', 'title' => 'Procédures radio', 'duration_min' => null, 'url' => '/academy/formations/radio'],
            ],
            'modules' => [
                ['ref' => '10', 'code' => 'A-RAD', 'title' => 'Radio de base', 'phase' => 'ALPHA', 'prereq_refs' => [], 'duration_min' => 30],
                ['ref' => '11', 'code' => 'B-CAS', 'title' => 'Appui aérien : théorie', 'phase' => 'BRAVO', 'prereq_refs' => ['10'], 'duration_min' => 45],
                ['ref' => '12', 'code' => 'D-CAS', 'title' => 'Évaluation CAS', 'phase' => 'DELTA', 'prereq_refs' => ['11'], 'duration_min' => 60],
            ],
            'qualifications' => [
                ['ref' => '6', 'code' => 'RADIO', 'title' => 'Opérateur radio', 'prereq_refs' => []],
                ['ref' => '7', 'code' => 'JTAC', 'title' => 'Qualification JTAC', 'prereq_refs' => ['6'], 'requires_exam' => true],
            ],
            'job_roles' => [
                ['ref' => '5', 'title' => 'Contrôleur JTAC'],
                ['ref' => '8', 'title' => 'Chef d’équipe'],
            ],
        ];
    }

    /** @param list<array<string, mixed>> $steps */
    private static function titles(array $steps): array
    {
        return array_map(static fn (array $s): string => (string) $s['title'], $steps);
    }

    public function testCompetencyPathwayFollowsLinksPrerequisitesAndStageOrder(): void
    {
        $result = (new CompetencyPathwayGenerator())->generate(self::catalog(), 'competency', '3');
        self::assertTrue($result['ok']);
        $titles = self::titles($result['steps']);
        $kinds = array_column($result['steps'], 'kind');

        // Prérequis du palier précédent (socle « Basique ») ; la compétence avancée d'un autre domaine n'est pas imposée.
        self::assertContains('Discipline radio', $titles);
        self::assertNotContains('Tir de combat avancé', $titles);
        // Éléments liés + fermeture des prérequis des modules et des qualifications.
        foreach (['JTAC — Licence ATH-ACADEMY', 'Radio de base', 'Appui aérien : théorie', 'Évaluation CAS', 'Opérateur radio', 'Qualification JTAC'] as $expected) {
            self::assertContains($expected, $titles);
        }
        self::assertLessThan(array_search('Appui aérien : théorie', $titles, true), array_search('Radio de base', $titles, true));
        self::assertLessThan(array_search('Évaluation CAS', $titles, true), array_search('Appui aérien : théorie', $titles, true));
        self::assertLessThan(array_search('Qualification JTAC', $titles, true), array_search('Opérateur radio', $titles, true));
        // Ordre des étapes : prérequis d'abord, validation en dernier, positions continues.
        self::assertSame('prerequisite', $kinds[0]);
        self::assertSame('validation', end($kinds));
        self::assertContains('exercise', $kinds);
        self::assertSame(range(1, count($result['steps'])), array_column($result['steps'], 'position'));
        self::assertStringContainsString('Licence Academy', (string) end($titles));
        // Sources : liens du référentiel distingués des prérequis ajoutés.
        $bySource = array_column($result['steps'], 'source', 'title');
        self::assertSame('lien', $bySource['Évaluation CAS']);
        self::assertSame('prerequis', $bySource['Radio de base']);
        self::assertSame(315, $result['stats']['duration_min']);
    }

    public function testGenerationIsDeterministic(): void
    {
        $gen = new CompetencyPathwayGenerator();
        foreach ([['competency', '3'], ['template', 'jtac'], ['job_role', '8'], ['template', 'medic']] as [$kind, $ref]) {
            self::assertSame($gen->generate(self::catalog(), $kind, $ref), $gen->generate(self::catalog(), $kind, $ref), $kind . ':' . $ref);
        }
    }

    public function testTemplatesCoverTheProposedModelsAndUseExistingData(): void
    {
        self::assertSame(['fusilier_chef_equipe', 'jtac', 'pilote', 'medic'], array_keys(CompetencyPathwayGenerator::templates()));

        $jtac = (new CompetencyPathwayGenerator())->generate(self::catalog(), 'template', 'jtac');
        self::assertTrue($jtac['ok']);
        $titles = self::titles($jtac['steps']);
        self::assertContains('JTAC — Licence ATH-ACADEMY', $titles);
        self::assertContains('Qualification JTAC', $titles);
        self::assertContains('Validation de la licence JTAC', $titles);
        self::assertNotContains('Secourisme au combat', $titles);

        $medic = (new CompetencyPathwayGenerator())->generate(self::catalog(), 'template', 'medic');
        self::assertContains('Secourisme au combat', self::titles($medic['steps']));
        self::assertSame('suggestion', array_column($medic['steps'], 'source', 'title')['Secourisme au combat']);

        // Sans aucune donnée, le modèle reste exploitable (prérequis, exercices, validation types).
        $empty = (new CompetencyPathwayGenerator())->generate([], 'template', 'pilote');
        self::assertTrue($empty['ok']);
        self::assertSame(['prerequisite', 'exercise', 'exercise', 'validation'], array_column($empty['steps'], 'kind'));
    }

    public function testJobRoleUsesLinkedCompetenciesThenNameMatches(): void
    {
        $gen = new CompetencyPathwayGenerator();
        $jtac = $gen->generate(self::catalog(), 'job_role', '5');
        self::assertContains('Évaluation CAS', self::titles($jtac['steps']));

        $lead = $gen->generate(self::catalog(), 'job_role', '8');
        self::assertTrue($lead['ok']);
        self::assertStringContainsString('Chef d’équipe', (string) end($lead['steps'])['title']);
        self::assertSame('validation', end($lead['steps'])['kind']);
    }

    public function testUnknownTargetsFailCleanly(): void
    {
        $gen = new CompetencyPathwayGenerator();
        foreach ([['competency', '99'], ['job_role', '99'], ['template', 'astronaute'], ['other', 'x']] as [$kind, $ref]) {
            $r = $gen->generate(self::catalog(), $kind, $ref);
            self::assertFalse($r['ok'], $kind);
            self::assertNotSame('', (string) $r['error']);
            self::assertSame([], $r['steps']);
        }
    }

    public function testPrerequisiteCyclesDoNotLoopForever(): void
    {
        $catalog = self::catalog();
        $catalog['modules'][0]['prereq_refs'] = ['12'];
        $r = (new CompetencyPathwayGenerator())->generate($catalog, 'competency', '3');
        self::assertTrue($r['ok']);
        self::assertCount(count(array_unique(self::titles($r['steps']))), $r['steps']);
    }

    public function testTrainerAdjustmentsAreNormalized(): void
    {
        $steps = CompetencyPathwayGenerator::normalizeSteps([
            ['keep' => '1', 'position' => '3', 'kind' => 'validation', 'title' => '  Validation   finale ', 'detail' => 'ok', 'ref_type' => 'competency', 'ref' => '3'],
            ['keep' => '1', 'position' => '1', 'kind' => 'hack', 'title' => 'Étape libre', 'ref_type' => 'evil', 'ref' => '<script>'],
            ['position' => '2', 'kind' => 'course', 'title' => 'Retirée (décochée)'],
            ['keep' => '1', 'position' => '2', 'kind' => 'course', 'title' => '   '],
            'not-an-array',
        ]);
        self::assertSame([
            ['kind' => 'custom', 'title' => 'Étape libre', 'detail' => '', 'ref_type' => '', 'ref' => ''],
            ['kind' => 'validation', 'title' => 'Validation finale', 'detail' => 'ok', 'ref_type' => 'competency', 'ref' => '3'],
        ], $steps);
    }

    public function testLinksAreResolvedAndSuggestionsSkipExistingLinks(): void
    {
        $catalog = self::catalog();
        $resolved = CompetencyPathwayGenerator::resolveLinks($catalog['competencies'][2], $catalog);
        self::assertSame(['academy_course', 'module', 'qualification', 'job_role'], array_keys($resolved));
        self::assertSame('Contrôleur JTAC', $resolved['job_role'][0]['title']);

        $suggested = CompetencyPathwayGenerator::suggestLinks($catalog['competencies'][2], $catalog);
        self::assertNotContains(['type' => 'qualification', 'ref' => '7', 'title' => 'Qualification JTAC'], $suggested);
        self::assertSame([], CompetencyPathwayGenerator::suggestLinks(['label' => 'Module de base', 'links' => []], $catalog));

        $withUrls = CompetencyPathwayGenerator::attachUrls([['ref_type' => 'training_course', 'ref' => '40'], ['ref_type' => 'competency', 'ref' => '1']], $catalog);
        self::assertSame('/academy/formations/radio', $withUrls[0]['url']);
        self::assertNull($withUrls[1]['url']);
    }

    public function testKeywordMatchingIgnoresAccentsAndPartialShortWords(): void
    {
        self::assertTrue(CompetencyPathwayGenerator::matches('Évacuation sanitaire — MEDEVAC', ['medevac']));
        self::assertTrue(CompetencyPathwayGenerator::matches('Secourisme de combat', ['secour']));
        self::assertTrue(CompetencyPathwayGenerator::matches('Chef d’équipe', ['chef d equipe']));
        self::assertFalse(CompetencyPathwayGenerator::matches('Cassage de porte', ['cas']));
        self::assertSame(['guidage', 'jtac'], CompetencyPathwayGenerator::tokens('Guidage JTAC — module de base'));
    }
}
