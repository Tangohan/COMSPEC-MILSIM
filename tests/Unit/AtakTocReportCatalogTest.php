<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AtakToc\TocReportCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue des rapports lus par le poste TOC web : familles, résultat d'un BDA quel que soit l'émetteur, fiche lisible de
 * chaque type (champs manquants, textes longs, accents, clés inconnues), niveaux de lecture américains.
 */
final class AtakTocReportCatalogTest extends TestCase
{
    public function testEveryReportTypeBelongsToAFamilyOfThePanel(): void
    {
        $expected = [
            'BDA' => 'bda', 'SITREP' => 'sitrep', 'TIC' => 'cr', 'CONTACT' => 'cr', 'SALUTE' => 'cr', 'SPOTREP' => 'cr', 'PATROLREP' => 'cr',
            'DEBRIEF' => 'cr', 'EAGLE_DOWN' => 'cr', 'LOGREP' => 'other', 'UXO' => 'other', 'NBC1' => 'other', 'FRAGO' => 'other', 'OTHER' => 'other',
        ];
        foreach ($expected as $type => $family) {
            self::assertSame($family, TocReportCatalog::familyOfType($type), $type);
            self::assertContains($family, array_keys(TocReportCatalog::FAMILIES));
            self::assertNotSame('', TocReportCatalog::typeLabel($type));
            self::assertNotSame('', TocReportCatalog::icon($type));
        }
        // Alias du jeu et type inconnu : jamais perdus.
        self::assertSame('bda', TocReportCatalog::familyOfType('bda_report'));
        self::assertSame('other', TocReportCatalog::familyOfType('QUELQUE_CHOSE'));
        self::assertSame('Compte rendu libre', TocReportCatalog::typeLabel('QUELQUE_CHOSE'));
    }

    public function testBdaResultIsReadFromEveryShapeTheGameSends(): void
    {
        // App Comptes rendus : result en majuscules accentuées ; app BDA : desc en minuscules ; Iceman : rating.
        self::assertSame('destroyed', TocReportCatalog::bdaResult(['result' => 'DÉTRUIT'])['code']);
        self::assertSame('damaged', TocReportCatalog::bdaResult(['result' => 'ENDOMMAGÉ'])['code']);
        self::assertSame('neutralized', TocReportCatalog::bdaResult(['desc' => 'neutralisé'])['code']);
        self::assertSame('none', TocReportCatalog::bdaResult(['result' => 'SANS EFFET'])['code']);
        self::assertSame('none', TocReportCatalog::bdaResult(['desc' => 'Aucun effet observé'])['code']);
        self::assertSame('destroyed', TocReportCatalog::bdaResult(['rating' => 'Destroyed'])['code']);
        self::assertSame('damaged', TocReportCatalog::bdaResult(['damage' => 'Damaged, 40 %'])['code']);
        $unknown = TocReportCatalog::bdaResult(['desc' => 'à confirmer (fumée dense)']);
        self::assertSame('unknown', $unknown['code']);
        self::assertSame('à confirmer (fumée dense)', $unknown['label'], 'le texte du jeu est gardé');
        $none = TocReportCatalog::bdaResult([]);
        self::assertSame('unknown', $none['code']);
        self::assertSame('Non communiqué', $none['label']);
        self::assertSame('accent', TocReportCatalog::bdaResult(['result' => 'DÉTRUIT'])['tone']);
        self::assertSame('amber', TocReportCatalog::bdaResult(['result' => 'ENDOMMAGÉ'])['tone']);
        self::assertSame('red', TocReportCatalog::bdaResult(['result' => 'SANS EFFET'])['tone']);
    }

    public function testBdaFicheShowsTargetResultMunitionCollateralAndKeepsLongRemarksAsABlock(): void
    {
        $doc = TocReportCatalog::render('BDA', [
            'kind' => 'bda', 'type' => 'VÉHICULE', 'grid' => '0412 1873', 'result' => 'DÉTRUIT', 'ekia' => '3', 'equip' => '1 BMP-2, 2 PKM',
            'ordnance' => '2 x 81 mm HE', 'collateral' => 'AUCUN', 'reattack' => 'REPRISE REQUISE', 'remarks' => str_repeat('Le BMP brûle encore. ', 12),
        ]);
        $facts = array_column($doc['facts'], 'value', 'label');
        self::assertSame('VÉHICULE', $facts['Cible']);
        self::assertSame('Détruit', $facts['Résultat']);
        self::assertSame('2 x 81 mm HE', $facts['Munition / moyen']);
        self::assertSame('AUCUN', $facts['Dommages collatéraux']);
        self::assertSame('accent', array_column($doc['facts'], 'tone', 'label')['Résultat']);
        self::assertSame('red', array_column($doc['facts'], 'tone', 'label')['Nouvelle frappe'], 'reprise requise en rouge');
        self::assertSame('Remarques', $doc['blocks'][0]['label']);
        self::assertGreaterThan(200, mb_strlen($doc['blocks'][0]['text']));
        self::assertSame([], $doc['extra'], 'kind est de la mécanique du jeu, pas une information');
    }

    public function testBdaFromTheBdaAppFallsBackOnDescForTheResult(): void
    {
        $doc = TocReportCatalog::render('BDA', ['kind' => 'bda', 'type' => 'Personnel', 'desc' => 'neutralisé', 'ekia' => '4', 'ordnance' => '12,7 mm']);
        $facts = array_column($doc['facts'], 'value', 'label');
        self::assertSame('Neutralisé', $facts['Résultat']);
        self::assertSame('Personnel', $facts['Cible']);
        self::assertArrayNotHasKey('desc', array_column($doc['extra'], 'value', 'label'));
    }

    public function testMissingFieldsAreSimplyAbsentAndAnEmptyFicheFallsBackOnTheDetail(): void
    {
        $doc = TocReportCatalog::render('SALUTE', ['size' => '6 hommes']);
        self::assertSame([['key' => 'size', 'label' => 'Volume (Size)', 'value' => '6 hommes', 'tone' => '']], $doc['facts']);
        self::assertSame([], $doc['blocks']);
        $empty = TocReportCatalog::render('TIC', [], 'Prise de contact, rien d’autre n’a été transmis.');
        self::assertSame([], $empty['facts']);
        self::assertSame('Détail', $empty['blocks'][0]['label']);
        self::assertStringContainsString('Prise de contact', $empty['blocks'][0]['text']);
    }

    public function testUnknownKeysAreKeptAndLongOnesBecomeBlocks(): void
    {
        $doc = TocReportCatalog::render('DEBRIEF', [
            'debrief_event' => 'submitted', 'mode' => 'fill', 'label' => 'Questionnaire de fin d’opération', 'a_1' => 'Bien', 'a_2' => str_repeat('Très long retour d’expérience. ', 6),
            'nested' => ['x' => 1, 'liste' => ['a', 'b']],
        ]);
        $extra = array_column($doc['extra'], 'value', 'label');
        self::assertSame('Bien', $extra['Réponse 1']);
        self::assertSame(['Réponse 2', 'Nested'], array_column($doc['blocks'], 'label'));
        self::assertSame("X : 1\nListe : a, b", $doc['blocks'][1]['text'], 'un tableau imbriqué se lit ligne par ligne');
        self::assertArrayNotHasKey('Debrief event', $extra);
        self::assertSame('Questionnaire de fin d’opération', $extra['Questionnaire']);
    }

    public function testNineLineLabelsAndGridLines(): void
    {
        self::assertCount(9, TocReportCatalog::NINE_LINES['cas']);
        self::assertCount(9, TocReportCatalog::NINE_LINES['medevac']);
        self::assertCount(5, TocReportCatalog::NINE_LINES['fiveline']);
        self::assertSame(6, TocReportCatalog::NINE_GRID_LINE['cas']);
        self::assertSame(1, TocReportCatalog::NINE_GRID_LINE['medevac']);
        self::assertSame('MEDEVAC / CASEVAC (9 lignes)', TocReportCatalog::nineKindLabel('medevac'));
        [$label, $closed] = TocReportCatalog::statusInfo('ON_SCENE');
        self::assertSame('Sur zone', $label);
        self::assertFalse($closed);
        self::assertTrue(TocReportCatalog::statusInfo('COMPLETE')[1]);
        self::assertTrue(TocReportCatalog::statusInfo('ABORTED')[1]);
    }

    public function testClassificationUsesTheAmericanLevelsOfTheSseScale(): void
    {
        self::assertSame('interne', TocReportCatalog::requiredLevel('UNCLASSIFIED'));
        self::assertSame('interne', TocReportCatalog::requiredLevel('RESTRICTED'));
        self::assertSame('encadrement', TocReportCatalog::requiredLevel('CONFIDENTIAL'));
        self::assertSame('confidentiel', TocReportCatalog::requiredLevel('SECRET'));
        // La visibilité resserre : un rapport non classifié « encadrement seulement » exige le niveau C.
        self::assertSame('encadrement', TocReportCatalog::requiredLevel('UNCLASSIFIED', 'COMMAND_ONLY'));
        self::assertSame('confidentiel', TocReportCatalog::requiredLevel('CONFIDENTIAL', 'RESTRICTED'));
        self::assertSame('UNCLASSIFIED', TocReportCatalog::banner('UNCLASSIFIED'));
        self::assertSame('CONFIDENTIAL', TocReportCatalog::banner('CONFIDENTIAL'));
        self::assertSame('SECRET//NOFORN', TocReportCatalog::banner('SECRET'));
        self::assertSame('CONFIDENTIAL', TocReportCatalog::banner('UNCLASSIFIED', 'COMMAND_ONLY'));
        self::assertSame(0, TocReportCatalog::rank('interne'));
        self::assertSame(3, TocReportCatalog::rank('tres_restreint'));
    }

    public function testSummaryLeadIsStrippedOnlyWhenItIsTheTypeLabel(): void
    {
        self::assertSame('0419 1883 · TIRS DIRECTS', TocReportCatalog::stripLead('Contact (TIC) · 0419 1883 · TIRS DIRECTS', 'TIC'));
        self::assertSame('1553 1720 · Patrouille', TocReportCatalog::stripLead('SALUTE · 1553 1720 · Patrouille', 'SALUTE'));
        self::assertSame('Point radio · du soir', TocReportCatalog::stripLead('Point radio · du soir', 'OTHER'), 'un autre début est gardé');
        self::assertSame('Contact (TIC)', TocReportCatalog::stripLead('Contact (TIC)', 'TIC'));
    }

    public function testPriorityRankAndLabels(): void
    {
        self::assertSame(3, TocReportCatalog::priorityRank('FLASH'));
        self::assertSame(2, TocReportCatalog::priorityRank('immediate'));
        self::assertSame(0, TocReportCatalog::priorityRank('autre'));
        self::assertSame('Prioritaire', TocReportCatalog::priorityLabel('PRIORITY'));
        self::assertSame('Routine', TocReportCatalog::priorityLabel('???'));
    }
}
