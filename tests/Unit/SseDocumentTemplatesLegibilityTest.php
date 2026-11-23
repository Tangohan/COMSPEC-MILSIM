<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseDocumentTemplates;
use PHPUnit\Framework\TestCase;

/**
 * Lisibilité des modèles de documents SSE : le thème sombre du portail donne aux th et td une couleur claire, des capitales et
 * une police mono ; sur la feuille blanche (éditeur, lecture, miniatures) tout doit être remis à zéro. Jamais de texte clair sur
 * fond clair : contraste d'au moins 4,5 pour 1 pour chaque couple texte et fond posé par un modèle et par la feuille.
 */
final class SseDocumentTemplatesLegibilityTest extends TestCase
{
    private const INK = '#0f172a';
    private const PAPER = '#ffffff';

    private static function css(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../public/assets/css/sse-doc-editor.css');
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $c = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        foreach ($c as $i => $v) {
            $v /= 255;
            $c[$i] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public function testContrastHelperIsSane(): void
    {
        self::assertGreaterThan(20.0, self::contrast('#000000', '#ffffff'));
        self::assertLessThan(1.1, self::contrast('#ffffff', '#f8fafc'));
    }

    public function testNoTemplateSetsLightTextOrDarkFill(): void
    {
        foreach (array_keys(SseDocumentTemplates::CATALOGUE) as $code) {
            $html = SseDocumentTemplates::body($code);
            self::assertDoesNotMatchRegularExpression('/color:\s*(#fff\b|#ffffff|white)/i', $html, $code . ' : pas de texte blanc');
            self::assertDoesNotMatchRegularExpression('/background-color:\s*#(0|1)[0-9a-f]{5}/i', $html, $code . ' : pas de fond sombre');
            preg_match_all('/<(?:td|th|p|span|h2|h3)\b[^>]*style="([^"]*)"/', $html, $m);
            foreach ($m[1] as $style) {
                $fg = preg_match('/(?<![-\w])color:\s*(#[0-9a-f]{6})/i', $style, $f) === 1 ? $f[1] : self::INK;
                $bg = preg_match('/background-color:\s*(#[0-9a-f]{6})/i', $style, $b) === 1 ? $b[1] : self::PAPER;
                self::assertGreaterThanOrEqual(4.5, self::contrast($fg, $bg), $code . ' : ' . $style . ' (' . $fg . ' sur ' . $bg . ')');
            }
        }
    }

    public function testHeaderLabelCellsAreShadedAndShort(): void
    {
        $html = SseDocumentTemplates::body('page_blanche');
        foreach (['Référence', 'Dossier', 'Date', 'Rédacteur'] as $label) {
            self::assertStringContainsString('<td style="background-color:#e2e8f0;"><strong>' . $label . '</strong></td>', $html, $label);
        }
    }

    public function testSheetCssResetsTheDarkPortalTableTheme(): void
    {
        $css = self::css();
        self::assertMatchesRegularExpression('~\.sse-ed__sheet th, \.sse-ed__sheet td, \.sse-rich th, \.sse-rich td \{[^}]*color: #0f172a;~', $css, 'texte sombre explicite');
        self::assertMatchesRegularExpression('~\.sse-ed__sheet th, \.sse-ed__sheet td, \.sse-rich th, \.sse-rich td \{[^}]*text-transform: none;~', $css, 'plus de capitales');
        self::assertMatchesRegularExpression('~\.sse-ed__sheet th, \.sse-ed__sheet td, \.sse-rich th, \.sse-rich td \{[^}]*font-family: inherit;~', $css, 'plus de police mono');
        self::assertMatchesRegularExpression('~\.sse-ed__sheet table, \.sse-rich table \{[^}]*min-width: 0;~', $css, 'pas de largeur minimale de tableau du portail');
        self::assertMatchesRegularExpression('~td\[style\*="#e2e8f0"\][^{]*\{[^}]*white-space: nowrap;~', $css, 'libellés jamais coupés');
        self::assertMatchesRegularExpression('~\.sse-ed__sheet \.sse-field[^{]*\{[^}]*background: none;[^}]*border-bottom: 1px dotted~', $css, 'champs : pointillé discret, pas de pastille');
    }

    public function testSheetCssNeverSetsLightTextOnTheSheetOrItsTables(): void
    {
        $css = self::css();
        $start = strpos($css, '/* ===== v3');
        self::assertNotFalse($start);
        $v3 = substr($css, (int) $start);
        self::assertDoesNotMatchRegularExpression('/(?<![-\w])color:\s*(#fff\b|#ffffff|white|var\()/i', $v3, 'aucune couleur claire ni variable de thème dans les règles de la feuille');
        self::assertGreaterThanOrEqual(4.5, self::contrast('#475569', '#f1f5f9'), 'texte d’aide');
        self::assertGreaterThanOrEqual(4.5, self::contrast(self::INK, '#e2e8f0'), 'libellés');
        self::assertGreaterThanOrEqual(4.5, self::contrast('#b91c1c', self::PAPER), 'ligne de classification');
    }
    /** @return array<string,string> sélecteur (un par ligne de la liste) => déclarations, pour les règles du bloc v4 */
    private static function v4Rules(): array
    {
        $css = self::css();
        $start = strpos($css, '/* ===== v4');
        self::assertNotFalse($start, 'bloc v4 présent');
        preg_match_all('/([^{}]+)\{([^}]*)\}/', substr($css, (int) $start), $m, PREG_SET_ORDER);
        $rules = [];
        foreach ($m as $r) {
            foreach (explode(',', (string) preg_replace('~/\*.*?\*/~s', '', $r[1])) as $sel) {
                $sel = trim((string) preg_replace('/\s+/', ' ', $sel));
                if ($sel !== '') {
                    $rules[$sel] = ($rules[$sel] ?? '') . $r[2];
                }
            }
        }

        return $rules;
    }

    public function testSheetRulesBeatTheShellRulesBySpecificity(): void
    {
        // La coque pose « body.sse-iw .sx-main td { color: rgba(238, 242, 240, .78) } » (spécificité 0,2,2) : une règle de feuille
        // sans ce préfixe perd. Chaque règle de texte de la feuille existe donc aussi avec le préfixe de la coque.
        $rules = self::v4Rules();
        foreach (['.sse-ed__sheet', '.sse-rich'] as $base) {
            foreach (['', ' td', ' th', ' p', ' strong', ' span', ' .sse-field', ' .sse-hint', ' h2'] as $tail) {
                self::assertArrayHasKey('body.sse-iw .sx-main ' . $base . $tail, $rules, $base . $tail . ' avec le préfixe de la coque');
                self::assertArrayHasKey($base . $tail, $rules, $base . $tail . ' sans préfixe');
            }
            foreach (['td', 'th'] as $cell) {
                $decl = $rules['body.sse-iw .sx-main ' . $base . ' ' . $cell];
                self::assertStringContainsString('color: #0f172a;', $decl, $cell . ' : couleur sombre explicite');
                self::assertStringContainsString('text-transform: none;', $decl);
                self::assertStringContainsString('white-space: normal;', $decl, $cell . ' : la coque pose nowrap sur les th');
            }
        }
    }

    public function testShellStylesheetsStillUseTheSelectorsTheSheetBeats(): void
    {
        // Garde-fou : si la coque change de sélecteur (plus spécifique), ce test dit de relire le bloc v4.
        $ui = (string) file_get_contents(__DIR__ . '/../../public/assets/css/sse_ui.css');
        self::assertStringContainsString('body.sse-iw .sx-main td {', $ui);
        self::assertStringContainsString('body.sse-iw .sx-main th {', $ui);
        self::assertStringNotContainsString('.sx-main td !important', $ui);
        self::assertDoesNotMatchRegularExpression('/\.sx-main\s+(td|th)[^{]*\{[^}]*!important/', $ui, 'aucun !important de la coque sur td ou th');
    }

    public function testAssetsAreVersionedByTheBuildSoAnOldSheetCannotStayCached(): void
    {
        foreach (['document_form', 'document_show', 'document_templates'] as $view) {
            $src = (string) file_get_contents(__DIR__ . '/../../views/atak/sse/' . $view . '.php');
            self::assertStringContainsString("asset_url('assets/css/sse-doc-editor.css')", $src, $view);
        }
        $helpers = (string) file_get_contents(__DIR__ . '/../../app/Support/helpers.php');
        self::assertStringContainsString("'v=' . rawurlencode(", $helpers, 'asset_url ajoute ?v= avec l’identifiant du déploiement');
        self::assertStringContainsString('BuildVersion::id()', $helpers);
    }
}
