<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Academy\AcademyRichText;
use PHPUnit\Framework\TestCase;

/**
 * Texte riche des étapes Academy : chaque bloc, échappement strict (aucun HTML saisi n'atteint la page),
 * liens et images refusés hors règles, et rendu historique d'un texte brut.
 */
final class AcademyRichTextTest extends TestCase
{
    private static function r(string $text): string
    {
        return AcademyRichText::render($text, ['rt_analysis' => 'Analyse', 'rt_radio' => 'Échange radio', 'rt_image_blocked' => 'Image refusée', 'rt_tip' => 'Astuce']);
    }

    public function testPlainTextKeepsTheHistoricalParagraphsAndLineBreaks(): void
    {
        $text = "Événement 1 — La capture\nLe drone repère le convoi.\n\nAnalyse : la patrouille confirme.\nEXEMPLE DE JOURNAL";
        self::assertSame("<p>Événement 1 — La capture<br>\nLe drone repère le convoi.</p>\n<p>Analyse : la patrouille confirme.<br>\nEXEMPLE DE JOURNAL</p>", self::r($text));
        self::assertSame('', self::r("  \n\n "));
        self::assertSame('<p>Voodoo 1 : « Prêt. »</p>', self::r('Voodoo 1 : « Prêt. »'));
    }

    public function testHeadingsEmphasisCodeListsRuleAndQuote(): void
    {
        $html = self::r("## Titre\n### Sous-titre\n#### Petit\nUn **gras**, un *italique* et `9-line`.\n- un\n- deux\n  suite\n3. trois\n4. quatre\n---\n> cité\n> encore");
        self::assertStringContainsString('<h2 class="rt-h">Titre</h2>', $html);
        self::assertStringContainsString('<h3 class="rt-h">Sous-titre</h3>', $html);
        self::assertStringContainsString('<h4 class="rt-h">Petit</h4>', $html);
        self::assertStringContainsString('Un <strong>gras</strong>, un <em>italique</em> et <code>9-line</code>.', $html);
        self::assertStringContainsString('<ul class="rt-list"><li>un</li><li>deux<br>suite</li></ul>', $html);
        self::assertStringContainsString('<ol class="rt-list" start="3"><li>trois</li><li>quatre</li></ol>', $html);
        self::assertStringContainsString('<hr class="rt-hr">', $html);
        self::assertStringContainsString("<blockquote class=\"rt-quote\"><p>cité<br>\nencore</p></blockquote>", $html);
        // Un astérisque isolé ou dans un mot ne devient pas de l'italique.
        self::assertSame('<p>2 * 3 = 6 et note*bas</p>', self::r('2 * 3 = 6 et note*bas'));
    }

    public function testCalloutsInFrenchAndEnglishWithDefaultOrCustomTitle(): void
    {
        $html = self::r(":::analyse\nLe pilote **confirme**.\n- point\n:::\n:::tip À retenir\nTexte\n:::");
        self::assertStringContainsString('<aside class="rt-callout rt-callout--analysis"><p class="rt-callout__title"><span class="rt-ico" aria-hidden="true"></span>Analyse</p><div class="rt-callout__body"><p>Le pilote <strong>confirme</strong>.</p>', $html);
        self::assertStringContainsString('<ul class="rt-list"><li>point</li></ul></div></aside>', $html);
        self::assertStringContainsString('rt-callout--tip"><p class="rt-callout__title"><span class="rt-ico" aria-hidden="true"></span>À retenir</p>', $html);
        foreach (['note' => 'note', 'astuce' => 'tip', 'attention' => 'warning', 'warning' => 'warning', 'exemple' => 'example', 'analysis' => 'analysis'] as $keyword => $class) {
            self::assertStringContainsString('rt-callout--' . $class, self::r(":::{$keyword}\nx\n:::"), $keyword);
        }
        // Type inconnu : texte simple, rien n'est interprété.
        self::assertSame("<p>:::inconnu<br>\nx<br>\n:::</p>", self::r(":::inconnu\nx\n:::"));
        // Bloc non fermé : jusqu'à la fin du texte. Imbrication comptée.
        self::assertStringContainsString('rt-callout--note', self::r(":::note\nsans fin"));
        self::assertSame(1, substr_count(self::r(":::note\n:::attention\nA\n:::\nB\n:::"), 'rt-callout--note'));
    }

    public function testRadioExchangeStepsLogAndTable(): void
    {
        $html = self::r(":::transmission Fréquence CAS\nVoodoo 1 : Bastion, prêt.\nBastion : Ligne 1 : IP CARRIÈRE.\n(silence radio)\nVoodoo 1 : Reçu.\n:::");
        self::assertStringContainsString('<figure class="rt-radio"><figcaption><span class="rt-ico" aria-hidden="true"></span>Fréquence CAS</figcaption>', $html);
        self::assertStringContainsString('<li class="rt-tx rt-tx--s0"><b class="rt-tx__who">Voodoo 1</b><span class="rt-tx__msg">Bastion, prêt.</span></li>', $html);
        self::assertStringContainsString('<li class="rt-tx rt-tx--s1"><b class="rt-tx__who">Bastion</b><span class="rt-tx__msg">Ligne 1 : IP CARRIÈRE.</span></li>', $html);
        self::assertStringContainsString('<li class="rt-tx-note">(silence radio)</li>', $html);
        self::assertSame(2, substr_count($html, 'rt-tx--s0'), 'Même indicatif, même couleur');
        self::assertStringContainsString('Échange radio', self::r(":::radio\nA : b\n:::"));

        self::assertStringContainsString('<ol class="rt-steps" aria-label="Préparer"><li><span>Lire</span></li><li><span>Écrire</span></li></ol>', self::r(":::etapes Préparer\n1. Lire\n- Écrire\n:::"));
        self::assertSame('<figure class="rt-log"><figcaption>Exemple de journal</figcaption><pre class="rt-code"><code>12:00 **CAS** &lt;b&gt;' . "\n" . '12:05 BDA</code></pre></figure>', self::r("```Exemple de journal\n12:00 **CAS** <b>\n12:05 BDA\n```"));
        $table = self::r("| Ligne | Contenu |\n|---|:---:|\n| 1 | **IP** |\n| 2 |");
        self::assertSame('<div class="rt-table"><table><thead><tr><th>Ligne</th><th>Contenu</th></tr></thead><tbody><tr><td>1</td><td><strong>IP</strong></td></tr><tr><td>2</td><td></td></tr></tbody></table></div>', $table);
    }

    public function testRawHtmlIsAlwaysEscaped(): void
    {
        $attack = "<script>alert(1)</script>\n## <img src=x onerror=alert(1)>\n:::note <b>x</b>\n\" onmouseover=\"alert(1)\n:::\n:::transmission\n<i>A</i> : <svg onload=alert(1)>\n:::\n| <b>c</b> |\n`<code>`\n[<b>lien</b>](https://ok.example/\"><script>)";
        $html = self::r($attack);
        foreach (['<script', '<img', '<svg', '<b>', '<i>', '"><script>', '<code><code>', ' onmouseover="'] as $needle) {
            self::assertStringNotContainsString($needle, $html, $needle);
        }
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringContainsString('&quot; onmouseover=&quot;alert(1)', $html);
        self::assertStringContainsString('<code>&lt;code&gt;</code>', $html);
    }

    public function testLinksAreHttpOnlyAndOpenSafely(): void
    {
        self::assertSame('<p><a href="https://exemple.org/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer">doc <strong>OTAN</strong></a></p>', self::r('[doc **OTAN**](https://exemple.org/a?b=1&c=2)'));
        self::assertStringContainsString('<a href="http://exemple.org"', self::r('[x](http://exemple.org)'));
        foreach (['javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:x', '//evil.example/a', 'https://user:pass@evil.example/', 'ftp://exemple.org/f', '/academy/studio'] as $href) {
            $html = self::r('[cliquer](' . $href . ')');
            self::assertStringNotContainsString('<a ', $html, $href);
            self::assertStringContainsString('cliquer', $html, $href);
        }
    }

    public function testImagesOnlyFromStudioUploadsAcademyAssetsOrAllowedHosts(): void
    {
        $upload = self::r('![Carte de la zone](uploads/training-course-media/14/academy-0123456789abcdef.webp)');
        self::assertStringContainsString('<figure class="rt-figure"><a class="rt-figure__zoom" href="', $upload);
        self::assertStringContainsString('uploads/training-course-media/14/academy-0123456789abcdef.webp" alt="Carte de la zone" loading="lazy"', $upload);
        self::assertStringContainsString('data-rt-zoom', $upload);
        self::assertStringContainsString('<figcaption>Carte de la zone</figcaption>', $upload);
        self::assertStringContainsString('<img src="https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg"', self::r('![x](https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg)'));
        self::assertStringContainsString('assets/images/academy/cover-night.webp', self::r('![x](assets/images/academy/cover-night.webp)'));
        foreach ([
            'https://evil.example/a.png', 'http://upload.wikimedia.org/a.png', 'javascript:alert(1)', 'data:image/png;base64,AAAA',
            'uploads/training-course-media/14/../../../.env', 'uploads/training-course-media/14/thumbnail-0123456789abcdef.png',
            'uploads/training-course-media/14/academy-0123456789abcdef.svg', 'assets/images/academy/../../js/x.png',
            'https://upload.wikimedia.org.evil.example/a.png', 'https://u:p@upload.wikimedia.org/a.png',
        ] as $src) {
            $html = self::r('![x](' . $src . ')');
            self::assertStringNotContainsString('<img', $html, $src);
            self::assertStringContainsString('Image refusée', $html, $src);
        }
        self::assertTrue(AcademyRichText::imageAllowed('uploads/training-course-media/3/academy-abcdef0123456789.jpeg'));
        self::assertFalse(AcademyRichText::imageAllowed('uploads/training-course-media/3/academy-abcdef0123456789.php'));
    }

    public function testDepthIsBoundedAndLongInputStaysLinear(): void
    {
        $nested = str_repeat(":::note\n", 20) . 'fond' . str_repeat("\n:::", 20);
        self::assertLessThanOrEqual(3, substr_count(self::r($nested), '<aside'));
        $big = str_repeat("- **a** *b* `c` [d](https://e.example)\n", 500);
        $start = microtime(true);
        self::assertSame(500, substr_count(self::r($big), '<li>'));
        self::assertLessThan(2.0, microtime(true) - $start);
    }
}
