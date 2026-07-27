<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Doctrine\DoctrineReferential;
use App\Services\Doctrine\EchelonCatalog;
use App\Services\Doctrine\OrderFormatCatalog;
use App\Services\Doctrine\TrainingPipelineCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Référentiel doctrinal : filtrage US / FR / les deux, et cohérence des catalogues.
 */
final class DoctrineReferentialTest extends TestCase
{
    public function testDefaultIsFrench(): void
    {
        self::assertSame(DoctrineReferential::FR, DoctrineReferential::DEFAULT);
        self::assertSame(DoctrineReferential::FR, DoctrineReferential::sanitize(null));
        self::assertSame(DoctrineReferential::FR, DoctrineReferential::sanitize('inconnu'));
    }

    public function testSanitizeAcceptsKnownKeysAndIsCaseInsensitive(): void
    {
        self::assertSame(DoctrineReferential::US, DoctrineReferential::sanitize('US'));
        self::assertSame(DoctrineReferential::BOTH, DoctrineReferential::sanitize('  both '));
    }

    public function testAcceptsMatchesOriginOrBoth(): void
    {
        self::assertTrue(DoctrineReferential::accepts(DoctrineReferential::US, DoctrineReferential::US));
        self::assertFalse(DoctrineReferential::accepts(DoctrineReferential::US, DoctrineReferential::FR));
        self::assertTrue(DoctrineReferential::accepts(DoctrineReferential::BOTH, DoctrineReferential::FR));
        self::assertTrue(DoctrineReferential::accepts(DoctrineReferential::BOTH, DoctrineReferential::US));
    }

    /** Une entrée sans origine reconnue est commune aux deux doctrines. */
    public function testEntriesWithoutOriginAreAlwaysKept(): void
    {
        self::assertTrue(DoctrineReferential::accepts(DoctrineReferential::FR, ''));
        self::assertTrue(DoctrineReferential::accepts(DoctrineReferential::US, 'commun'));
    }

    public function testFilterKeepsOnlyChosenReferential(): void
    {
        $entries = [
            ['origin' => DoctrineReferential::US, 'key' => 'a'],
            ['origin' => DoctrineReferential::FR, 'key' => 'b'],
            ['origin' => '', 'key' => 'c'],
        ];

        self::assertSame(['b', 'c'], array_column(DoctrineReferential::filter($entries, DoctrineReferential::FR), 'key'));
        self::assertSame(['a', 'c'], array_column(DoctrineReferential::filter($entries, DoctrineReferential::US), 'key'));
        self::assertSame(['a', 'b', 'c'], array_column(DoctrineReferential::filter($entries, DoctrineReferential::BOTH), 'key'));
    }

    public function testOrderFormatsCoverBothDoctrines(): void
    {
        $fr = array_column(OrderFormatCatalog::forReferential(DoctrineReferential::FR), 'code');
        $us = array_column(OrderFormatCatalog::forReferential(DoctrineReferential::US), 'code');

        self::assertContains('OPORD', $us);
        self::assertContains('WARNO', $us);
        self::assertContains('FRAGO', $us);
        self::assertContains('SALUTE', $us);
        self::assertContains('SITREP', $us);
        self::assertContains('MEDEVAC 9 lignes', $us);

        self::assertContains('Ordre initial', $fr);
        self::assertContains('Ordre de conduite', $fr);
        self::assertContains('Compte rendu', $fr);
        self::assertContains('Demande d’évacuation', $fr);

        // Aucun mélange : un référentiel ne propose pas les gabarits de l'autre.
        self::assertNotContains('OPORD', $fr);
        self::assertNotContains('Ordre initial', $us);
        self::assertCount(count($fr) + count($us), OrderFormatCatalog::forReferential(DoctrineReferential::BOTH));
    }

    public function testOpordHasFiveNumberedParagraphs(): void
    {
        $opord = OrderFormatCatalog::find('us_opord');
        self::assertNotNull($opord);
        self::assertCount(5, $opord['sections']);
        foreach ($opord['sections'] as $index => $section) {
            self::assertStringStartsWith((string) ($index + 1) . '.', $section['title']);
        }
    }

    public function testNineLineMedevacHasExactlyNineFields(): void
    {
        $medevac = OrderFormatCatalog::find('us_medevac_9line');
        self::assertNotNull($medevac);
        self::assertSame(9, OrderFormatCatalog::fieldCount($medevac));
    }

    public function testSaluteHasSixFieldsInOrder(): void
    {
        $salute = OrderFormatCatalog::find('us_salute');
        self::assertNotNull($salute);
        self::assertSame(6, OrderFormatCatalog::fieldCount($salute));
        $initials = array_map(
            static fn (string $field): string => mb_substr($field, 0, 1),
            $salute['sections'][0]['fields']
        );
        self::assertSame(['S', 'A', 'L', 'U', 'T', 'E'], $initials);
    }

    public function testEveryFormatHasAtLeastOneImposedField(): void
    {
        foreach (OrderFormatCatalog::all() as $format) {
            self::assertGreaterThan(
                0,
                OrderFormatCatalog::fieldCount($format),
                'Gabarit sans champ imposé : ' . $format['key']
            );
        }
    }

    public function testEchelonsAreOrderedFromSmallestToLargest(): void
    {
        foreach ([DoctrineReferential::FR, DoctrineReferential::US, DoctrineReferential::BOTH] as $referential) {
            $levels = array_column(EchelonCatalog::forReferential($referential), 'level');
            $sorted = $levels;
            sort($sorted);
            self::assertSame($sorted, $levels, 'Échelons désordonnés pour ' . $referential);
        }
    }

    public function testEchelonStrengthRangesAreCoherent(): void
    {
        foreach (EchelonCatalog::all() as $echelon) {
            self::assertGreaterThan(0, $echelon['strength_min'], $echelon['key']);
            self::assertGreaterThanOrEqual($echelon['strength_min'], $echelon['strength_max'], $echelon['key']);
            self::assertNotSame([], $echelon['functions'], 'Échelon sans fonction : ' . $echelon['key']);
        }
    }

    public function testEchelonStrengthLabelReadsAsARange(): void
    {
        self::assertSame('8 à 10', EchelonCatalog::strengthLabel(['strength_min' => 8, 'strength_max' => 10]));
        self::assertSame('4', EchelonCatalog::strengthLabel(['strength_min' => 4, 'strength_max' => 4]));
        self::assertSame('—', EchelonCatalog::strengthLabel([]));
    }

    public function testBothReferentialExposesTwoEchelonsPerLevel(): void
    {
        $byLevel = [];
        foreach (EchelonCatalog::forReferential(DoctrineReferential::BOTH) as $echelon) {
            $byLevel[$echelon['level']][] = $echelon['origin'];
        }
        foreach ($byLevel as $level => $origins) {
            self::assertSame(
                [DoctrineReferential::FR, DoctrineReferential::US],
                $origins,
                'Niveau ' . $level . ' : les deux doctrines devraient être présentes, dans un ordre stable.'
            );
        }
    }

    public function testTrainingSeparatesStagesFromQualifications(): void
    {
        $stages = 0;
        $qualifications = 0;
        foreach (TrainingPipelineCatalog::all() as $entry) {
            if ($entry['type'] === TrainingPipelineCatalog::TYPE_STAGE) {
                $stages++;
                // Une étape est acquise définitivement.
                self::assertNull($entry['validity_months'], 'Étape avec échéance : ' . $entry['key']);
                continue;
            }
            $qualifications++;
            // Une qualification à entretenir doit porter une échéance et un mode de recyclage.
            self::assertNotNull($entry['validity_months'], 'Qualification sans échéance : ' . $entry['key']);
            self::assertNotNull($entry['recycling'], 'Qualification sans recyclage : ' . $entry['key']);
        }

        self::assertGreaterThan(0, $stages);
        self::assertGreaterThan(0, $qualifications);
    }

    public function testValidityLabelReadsInYearsWhenPossible(): void
    {
        self::assertSame('Sans échéance', TrainingPipelineCatalog::validityLabel(['validity_months' => null]));
        self::assertSame('1 an', TrainingPipelineCatalog::validityLabel(['validity_months' => 12]));
        self::assertSame('2 ans', TrainingPipelineCatalog::validityLabel(['validity_months' => 24]));
        self::assertSame('6 mois', TrainingPipelineCatalog::validityLabel(['validity_months' => 6]));
    }

    public function testCatalogKeysAreUniqueAcrossEachCatalog(): void
    {
        foreach ([
            'ordres' => array_column(OrderFormatCatalog::all(), 'key'),
            'échelons' => array_column(EchelonCatalog::all(), 'key'),
            'formation' => array_column(TrainingPipelineCatalog::all(), 'key'),
        ] as $catalog => $keys) {
            self::assertSame(count($keys), count(array_unique($keys)), 'Clés dupliquées dans ' . $catalog);
        }
    }
}
