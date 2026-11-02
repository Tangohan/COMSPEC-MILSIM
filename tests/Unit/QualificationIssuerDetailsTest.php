<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\QualificationCatalog\IssuerDetails;
use PHPUnit\Framework\TestCase;

/**
 * Assistant « Créer un émetteur » : pays, localisation, site officiel et présentation anglaise ajoutés à la
 * présentation enregistrée, et pays relu pour la liste des émetteurs.
 */
final class QualificationIssuerDetailsTest extends TestCase
{
    public function testComposeAddsDetailLinesAfterTheFrenchDescription(): void
    {
        $text = IssuerDetails::compose([
            'description' => 'Forme les tireurs.',
            'description_en' => 'Trains marksmen.',
            'country' => 'fr',
            'location' => 'Canjuers',
            'website' => 'https://www.exemple.fr/tir',
        ]);
        self::assertSame("Forme les tireurs.\n\nPays : France\nLocalisation : Canjuers\nSite officiel : https://www.exemple.fr/tir\n\nEnglish: Trains marksmen.", $text);
        self::assertSame('FR', IssuerDetails::country($text));
        self::assertSame('', IssuerDetails::compose([]));
        self::assertSame('Pays : OTAN', IssuerDetails::compose(['country' => 'NATO', 'website' => 'javascript:alert(1)']), 'site invalide ignoré');
    }

    public function testCountryIsReadInBothLanguagesAndUnknownLabelsAreIgnored(): void
    {
        self::assertSame('DE', IssuerDetails::country("Texte\nCountry: Germany"));
        self::assertSame('US', IssuerDetails::country('Pays : États-Unis'));
        self::assertSame('', IssuerDetails::country('Pays : Belgique'));
        self::assertSame('', IssuerDetails::country('Un pays : la France'));
        self::assertTrue(IssuerDetails::validWebsite('http://ecole.example.org'));
        self::assertFalse(IssuerDetails::validWebsite('www.exemple.fr'));
    }
}
