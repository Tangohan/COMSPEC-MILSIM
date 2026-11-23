<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseRichText;
use PHPUnit\Framework\TestCase;

/**
 * Assainisseur du texte riche des documents SSE : liste blanche, attaques XSS classiques, idempotence.
 */
final class SseRichTextTest extends TestCase
{
    /** @return array<string,array{0:string}> */
    public static function attacks(): array
    {
        return [
            'script' => ['<p>a</p><script>alert(1)</script>'],
            'script casse' => ['<ScRiPt>alert(1)</sCrIpT>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'svg onload' => ['<svg onload=alert(1)><circle/></svg>'],
            'iframe' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'lien javascript' => ['<a href="javascript:alert(1)">x</a>'],
            'lien entites' => ['<a href="jav&#x09;ascript:alert(1)">x</a>'],
            'onclick' => ['<p onclick="alert(1)">x</p>'],
            'onmouseover span' => ['<span onmouseover="alert(1)">x</span>'],
            'style expression' => ['<p style="width:expression(alert(1))">x</p>'],
            'style url' => ['<p style="background:url(javascript:alert(1))">x</p>'],
            'style import' => ['<p style="color:red;@import \'http://evil\'">x</p>'],
            'form action' => ['<form action="http://evil"><input name=a></form>'],
            'object embed' => ['<object data="x"></object><embed src="x">'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=http://evil">'],
            'base' => ['<base href="http://evil/">'],
            'math' => ['<math><mi xlink:href="javascript:alert(1)">x</mi></math>'],
            'balise cassee' => ['<scr<script>ipt>alert(1)</scr</script>ipt>'],
            'commentaire' => ['<!--<script>alert(1)</script>-->'],
            'details ontoggle' => ['<details open ontoggle=alert(1)>x</details>'],
            'table onmouseover' => ['<table onmouseover="alert(1)"><tr><td>x</td></tr></table>'],
            'data uri' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>'],
            'id et name' => ['<p id="x" name="y">x</p>'],
            'classe inconnue' => ['<p class="evil">x</p>'],
            'champ inconnu' => ['<span class="sse-field" data-field="x&quot; onmouseover=&quot;alert(1)">x</span>'],
        ];
    }

    public function testEveryAttackIsNeutralised(): void
    {
        foreach (self::attacks() as $label => [$payload]) {
            $this->assertNeutral($label, $payload);
        }
    }

    private function assertNeutral(string $label, string $payload): void
    {
        $out = SseRichText::sanitize($payload);
        self::assertDoesNotMatchRegularExpression('/<\s*(script|img|svg|iframe|object|embed|form|meta|base|math|a|input|details)\b/i', $out);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $out);
        self::assertStringNotContainsString('javascript:', strtolower($out));
        self::assertStringNotContainsString('expression', strtolower($out));
        self::assertStringNotContainsString('url(', strtolower($out));
        self::assertStringNotContainsString('<!--', $out);
        self::assertStringNotContainsString('class="evil"', $out);
        self::assertStringNotContainsString(' id=', $out);
        self::assertSame($out, SseRichText::sanitize($out), 'idempotence : ' . $label);
    }

    public function testSafeFormattingIsKept(): void
    {
        $in = '<h2>Titre</h2><p style="text-align:center;line-height:1.5;">a <strong>b</strong> <em>c</em> <u>d</u> <s>e</s></p>'
            . '<ul><li>un</li></ul><ol><li>deux</li></ol><blockquote>q</blockquote><hr>'
            . '<table><tbody><tr><td colspan="2">x</td></tr></tbody></table>'
            . '<div class="sse-pagebreak"></div><span class="sse-field" data-field="grade">Capitaine</span>';
        self::assertSame($in, SseRichText::sanitize($in));
    }

    public function testNormalisation(): void
    {
        self::assertSame('<strong>a</strong><em>b</em><s>c</s>', SseRichText::sanitize('<b>a</b><i>b</i><strike>c</strike>'));
        self::assertSame('<h2>a</h2><h4>b</h4>', SseRichText::sanitize('<h1>a</h1><h6>b</h6>'));
        self::assertSame('<p>texte</p>', SseRichText::sanitize('<p><font color="red">texte</font></p>'));
        self::assertSame('<p>&lt;script&gt;</p>', SseRichText::sanitize('<p>&lt;script&gt;</p>'));
    }

    public function testColorsLimitedToPalette(): void
    {
        self::assertSame('<span style="color:#b91c1c;">a</span>', SseRichText::sanitize('<span style="color: rgb(185, 28, 28)">a</span>'));
        self::assertSame('a', SseRichText::sanitize('<span style="color:#ff00ff">a</span>'));
        self::assertSame('<span style="background-color:#fef08a;">a</span>', SseRichText::sanitize('<span style="background-color:#fef08a">a</span>'));
        self::assertSame('<p>a</p>', SseRichText::sanitize('<p style="line-height:99">a</p>'));
        self::assertSame('<p>a</p>', SseRichText::sanitize('<p style="position:fixed;top:0">a</p>'));
    }

    public function testWrapAndDetection(): void
    {
        $body = SseRichText::wrap('<p>Bonjour</p><script>x</script>');
        self::assertTrue(SseRichText::isRich($body));
        self::assertSame('<p>Bonjour</p>', SseRichText::htmlOf($body));
        self::assertSame('Bonjour', SseRichText::plainText($body));
        self::assertFalse(SseRichText::isRich("TITRE\ntexte"));
        self::assertSame('', SseRichText::plainText(SseRichText::wrap('<script>x</script>')));
    }

    public function testLegacyTextIsConverted(): void
    {
        $html = SseRichText::fromLegacyText("NOTE D’ANALYSE\n\nObjet: test\n• un\n• deux\n\n<script>x</script>");
        self::assertStringContainsString('<h2>NOTE D’ANALYSE</h2>', $html);
        self::assertStringContainsString('<ul><li>un</li><li>deux</li></ul>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testPaperRenderRefreshesFieldsAndRedacts(): void
    {
        $body = SseRichText::wrap('<p>Dossier <span class="sse-field" data-field="dossier">ancien</span> [[secret]]</p>');
        $html = SseRichText::toPaperHtml($body, ['dossier' => 'SSE-2026-01 <b>']);
        self::assertStringContainsString('SSE-2026-01 &lt;b&gt;', $html);
        self::assertStringContainsString('sse-doc-paper__redact', $html);
    }

    public function testPdfHtmlHasPageBreakAndBorderedTables(): void
    {
        $pdf = SseRichText::toPdfHtml(SseRichText::wrap('<div class="sse-pagebreak"></div><table><tbody><tr><td>x</td></tr></tbody></table>'));
        self::assertStringContainsString('<br pagebreak="true"/>', $pdf);
        self::assertStringContainsString('border="1"', $pdf);
    }
}
