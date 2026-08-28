<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseFieldNoteCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Le référentiel des fiches est dupliqué côté jeu (fn_intelNoteCatalog.sqf) :
 * ces tests verrouillent ce que les deux surfaces de saisie doivent partager.
 */
final class SseFieldNoteCatalogTest extends TestCase
{
    public function testNormalizeThemesKeepsOnlyKnownCodesWithoutDuplicates(): void
    {
        $themes = SseFieldNoteCatalog::normalizeThemes([
            'securite_publique',
            'SECUR',
            'code_inexistant',
            'trafics',
            'TERROR',
        ]);

        self::assertSame(['SECUR', 'FINANCE', 'TERROR'], $themes);
    }

    public function testNormalizeThemesCapsAtCatalogLimit(): void
    {
        $themes = SseFieldNoteCatalog::normalizeThemes(array_keys(SseFieldNoteCatalog::THEMES));

        self::assertCount(SseFieldNoteCatalog::THEMES_MAX, $themes);
    }

    public function testNormalizeThemesAcceptsJsonAndCommaSeparatedInput(): void
    {
        self::assertSame(
            ['INSURG', 'GENERAL'],
            SseFieldNoteCatalog::normalizeThemes('["ordre_public","divers"]')
        );
        self::assertSame(
            ['INSURG', 'GENERAL'],
            SseFieldNoteCatalog::normalizeThemes('ordre_public, divers')
        );
        self::assertSame([], SseFieldNoteCatalog::normalizeThemes('   '));
    }

    public function testNormalizeBodyTruncatesAtDisplayedLimit(): void
    {
        $body = SseFieldNoteCatalog::normalizeBody(str_repeat('é', SseFieldNoteCatalog::BODY_MAX_LENGTH + 50));

        self::assertSame(SseFieldNoteCatalog::BODY_MAX_LENGTH, mb_strlen($body));
    }

    public function testNormalizeBodyUnifiesLineEndings(): void
    {
        self::assertSame("a\nb\nc", SseFieldNoteCatalog::normalizeBody("a\r\nb\rc"));
    }

    public function testUnknownCodesFallBackToDefaults(): void
    {
        self::assertSame(SseFieldNoteCatalog::DEFAULT_KIND, SseFieldNoteCatalog::normalizeKind('inconnu'));
        self::assertSame(SseFieldNoteCatalog::DEFAULT_URGENCY, SseFieldNoteCatalog::normalizeUrgency('inconnu'));
        self::assertSame(SseFieldNoteCatalog::DEFAULT_STATUS, SseFieldNoteCatalog::normalizeStatus('inconnu'));
    }

    public function testKindCodesAreNormalizedCaseInsensitively(): void
    {
        self::assertSame('FRA', SseFieldNoteCatalog::normalizeKind('fra'));
        self::assertSame('urgent', SseFieldNoteCatalog::normalizeUrgency('PRIORITE'));
        self::assertSame('critique', SseFieldNoteCatalog::normalizeUrgency('immediate'));
        self::assertSame('HUMINT', SseFieldNoteCatalog::normalizeSource('humint'));
        self::assertSame('', SseFieldNoteCatalog::normalizeSource('inconnu'));
    }

    /**
     * Les libellés partent dans l'ATAK : pas de clé technique ni de sigle nu.
     */
    public function testLabelsAreWrittenForOperators(): void
    {
        foreach (SseFieldNoteCatalog::KINDS as $code => $definition) {
            self::assertMatchesRegularExpression('/^[A-Z]{3}$/', $code, 'sigle de type de fiche');
            self::assertNotSame('', trim($definition['label']));
            self::assertNotSame('', trim($definition['hint']));
            self::assertStringNotContainsString('_', $definition['label']);
        }

        foreach (SseFieldNoteCatalog::THEMES as $code => $definition) {
            self::assertMatchesRegularExpression('/^[A-Z]{3,12}$/', $code, 'sigle de thème');
            self::assertStringNotContainsString('_', $definition['label'], $code);
            self::assertNotSame('', trim($definition['hint'] ?? ''), $code);
            self::assertContains(
                $definition['tone'],
                SseFieldNoteCatalog::TONES,
                $code . ' doit porter une couleur connue'
            );
        }

        self::assertCount(17, SseFieldNoteCatalog::THEMES);
        self::assertSame('critical', SseFieldNoteCatalog::themeTone('TERROR'));
        self::assertSame('warning', SseFieldNoteCatalog::themeTone('ARMEMENT'));
        self::assertSame('caution', SseFieldNoteCatalog::themeTone('LOGIST'));
        self::assertSame('stable', SseFieldNoteCatalog::themeTone('INFRA'));
        self::assertSame('info', SseFieldNoteCatalog::themeTone('CIVIL'));
        self::assertSame('SECUR', SseFieldNoteCatalog::normalizeThemeCode('securite_publique'));

        foreach (SseFieldNoteCatalog::STATUSES as $code => $label) {
            self::assertStringNotContainsString('_', $label, $code);
        }
    }

    /**
     * Le client ATAK lit ce contrat : la forme ne doit pas bouger sans le mod.
     */
    public function testClientCatalogExposesLimitsAndFlatLists(): void
    {
        $catalog = SseFieldNoteCatalog::clientCatalog();

        self::assertSame(SseFieldNoteCatalog::BODY_MAX_LENGTH, $catalog['body_max_length']);
        self::assertSame(SseFieldNoteCatalog::ATTACHMENTS_MAX, $catalog['attachments_max']);
        self::assertSame(SseFieldNoteCatalog::THEMES_MAX, $catalog['themes_max']);
        self::assertCount(count(SseFieldNoteCatalog::KINDS), $catalog['kinds']);
        self::assertCount(count(SseFieldNoteCatalog::THEMES), $catalog['themes']);
        self::assertCount(count(SseFieldNoteCatalog::URGENCIES), $catalog['urgencies']);

        foreach ($catalog['themes'] as $theme) {
            self::assertArrayHasKey('code', $theme);
            self::assertArrayHasKey('label', $theme);
            self::assertArrayHasKey('tone', $theme);
            self::assertArrayHasKey('color', $theme);
            self::assertArrayHasKey('hint', $theme);
        }
        self::assertArrayHasKey('sources', $catalog);
        self::assertCount(count(SseFieldNoteCatalog::SOURCES), $catalog['sources']);
        self::assertSame(SseFieldNoteCatalog::DEFAULT_URGENCY, $catalog['default_urgency']);
    }

    /**
     * Le mod embarque une copie du référentiel pour rester lisible hors liaison.
     * Si les deux divergent, l'opérateur voit des libellés différents selon
     * qu'il rédige dans l'ATAK ou dans le portail.
     */
    public function testGameCatalogMirrorsServerCatalog(): void
    {
        $sqf = dirname(__DIR__, 2)
            . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteCatalog.sqf';
        if (!is_file($sqf)) {
            self::markTestSkipped('Sources du mod absentes de cette copie de travail.');
        }
        $contents = (string) file_get_contents($sqf);

        self::assertStringContainsString(
            '["body_max", ' . SseFieldNoteCatalog::BODY_MAX_LENGTH . ']',
            $contents,
            'longueur maximale du texte'
        );
        self::assertStringContainsString(
            '["pieces_max", ' . SseFieldNoteCatalog::ATTACHMENTS_MAX . ']',
            $contents,
            'nombre de pièces jointes'
        );
        self::assertStringContainsString(
            '["themes_max", ' . SseFieldNoteCatalog::THEMES_MAX . ']',
            $contents,
            'nombre de thèmes'
        );

        foreach (array_keys(SseFieldNoteCatalog::KINDS) as $code) {
            self::assertStringContainsString('["' . $code . '"', $contents, 'type ' . $code);
        }
        foreach (SseFieldNoteCatalog::THEMES as $code => $definition) {
            self::assertStringContainsString('["' . $code . '"', $contents, 'thème ' . $code);
            self::assertStringContainsString($definition['label'], $contents, 'libellé de ' . $code);
        }
        foreach (array_keys(SseFieldNoteCatalog::URGENCIES) as $code) {
            self::assertStringContainsString('["' . $code . '"', $contents, 'urgence ' . $code);
        }

        // L'ordre compte : chaque bascule de thème du rédacteur ATAK est câblée
        // sur un rang fixe du référentiel, pas sur un code.
        preg_match('/\["themes", \[(.*?)\n    \]\]/s', $contents, $matches);
        self::assertNotEmpty($matches, 'bloc des thèmes introuvable dans le référentiel du mod');
        preg_match_all('/\["([A-Z]{3,12})",/', $matches[1], $codes);
        self::assertSame(
            array_keys(SseFieldNoteCatalog::THEMES),
            $codes[1],
            'l’ordre des thèmes doit être identique côté serveur et côté jeu'
        );
    }
}
