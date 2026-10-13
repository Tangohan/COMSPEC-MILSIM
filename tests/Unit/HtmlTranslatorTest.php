<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HtmlTranslator;
use PHPUnit\Framework\TestCase;

final class HtmlTranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        HtmlTranslator::useDictionary([
            'Nouveau sujet' => 'New topic',
            'Bienvenue, %1 !' => 'Welcome, %1!',
            'Recherche : « %1 »' => 'Search: “%1”',
            'Statut' => 'Status',
            'Actif' => 'Active',
            'Rechercher' => 'Search',
            'Envoyer' => 'Send',
            'Fermer' => 'Close',
            'il y a %1 min' => '%1 min ago',
            'Membres' => 'Members',
            'le %1' => 'on %1',
            '%1 le %2' => '%1 on %2',
            'Équipe %1-%2-%3' => 'Team %1-%2-%3',
            '%1 personne%2' => '%1 person%2',
            'Présence' => 'Attendance',
        ]);
    }

    protected function tearDown(): void
    {
        HtmlTranslator::reset();
    }

    public function testTranslatesTextNodesKeepingWhitespaceAndTags(): void
    {
        $html = "<h1>\n    Nouveau sujet\n</h1><p>Bienvenue, <strong>Jean</strong> !</p><p>Bienvenue, Jean !</p>";
        $out = HtmlTranslator::translate($html);
        self::assertStringContainsString("<h1>\n    New topic\n</h1>", $out);
        self::assertStringContainsString('<p>Welcome, Jean!</p>', $out);
    }

    public function testTemplatesEntitiesAndLabelValue(): void
    {
        $out = HtmlTranslator::translate('<span>Recherche : &laquo; radio &raquo;</span><dd>Statut : Actif</dd><i>il y a 5 min</i>');
        self::assertStringContainsString('<span>Search: “radio”</span>', $out);
        self::assertStringContainsString('<dd>Status: Active</dd>', $out);
        self::assertStringContainsString('<i>5 min ago</i>', $out);
    }

    public function testAttributesAndButtonsButNotUserInput(): void
    {
        $out = HtmlTranslator::translate('<input type="text" placeholder="Rechercher" value="Fermer"><input type="submit" value="Envoyer"><button title="Fermer" aria-label="Fermer">x</button>');
        self::assertStringContainsString('placeholder="Search" value="Fermer"', $out);
        self::assertStringContainsString('type="submit" value="Send"', $out);
        self::assertStringContainsString('title="Close" aria-label="Close"', $out);
    }

    public function testSkipsScriptsTextareasAndTranslateNo(): void
    {
        $html = '<script>var a = "Membres";</script><textarea>Membres</textarea><div translate="no"><p>Membres</p><div>Membres</div></div><p>Membres</p>';
        $out = HtmlTranslator::translate($html);
        self::assertSame('<script>var a = "Membres";</script><textarea>Membres</textarea><div translate="no"><p>Membres</p><div>Membres</div></div><p>Members</p>', $out);
    }

    public function testUnknownTextStaysFrench(): void
    {
        $html = '<p>Texte inconnu</p><!-- Membres -->';
        self::assertSame($html, HtmlTranslator::translate($html));
    }

    public function testTranslatesPlainTextLineByLine(): void
    {
        $text = "Bienvenue, Jean !\n\n  Statut : Actif\nLigne inconnue\n";
        self::assertSame("Welcome, Jean!\n\n  Status: Active\nLigne inconnue\n", HtmlTranslator::translateText($text));
    }

    public function testTranslatesJsonMessagesButNotData(): void
    {
        $json = '{"ok":false,"error":"Fermer","data":{"name":"Fermer","items":[{"label":"Statut : Actif","n":1.0}]},"empty":{}}';
        self::assertSame(
            '{"ok":false,"error":"Close","data":{"name":"Fermer","items":[{"label":"Status: Active","n":1.0}]},"empty":{}}',
            HtmlTranslator::translateJson($json)
        );
        self::assertSame('{"message":"Texte inconnu"}', HtmlTranslator::translateJson('{"message":"Texte inconnu"}'));
        self::assertSame('pas du json', HtmlTranslator::translateJson('pas du json'));
    }

    public function testWeakTemplatesOnlyWrapShortValues(): void
    {
        self::assertSame('<p>on 12 mars 2026</p>', HtmlTranslator::translate('<p>le 12 mars 2026</p>'));
        $sentence = '<p>Organisation fictive pour presenter le portail en conditions réalistes.</p>';
        self::assertSame($sentence, HtmlTranslator::translate($sentence));
    }

    public function testTemplateWhoseMiddlePartWouldStartPastTheEndDoesNotThrow(): void
    {
        // Ancienne erreur : strpos() avec un décalage hors de la chaîne levait une ValueError et toute la page
        // restait en français.
        self::assertSame('<p>Équipe A-</p>', HtmlTranslator::translate('<p>Équipe A-</p>'));
        self::assertSame('<p>Team A-B-C</p>', HtmlTranslator::translate('<p>Équipe A-B-C</p>'));
    }

    public function testPluralSuffixTemplatesDoNotCutWords(): void
    {
        self::assertSame('<p>3 persons</p>', HtmlTranslator::translate('<p>3 personnes</p>'));
        $sentence = '<p>compte personnel, espaces de communauté</p>';
        self::assertSame($sentence, HtmlTranslator::translate($sentence));
    }

    public function testDecoratedAndUppercaseTexts(): void
    {
        self::assertSame('<b>ATTENDANCE</b><i>🇫🇷 Members</i>', HtmlTranslator::translate('<b>PRÉSENCE</b><i>🇫🇷 Membres</i>'));
    }
}
