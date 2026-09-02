<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MiniArticleHtml;
use PHPUnit\Framework\TestCase;

final class MiniArticleHtmlTest extends TestCase
{
    public function testSanitizeStripsScriptsAndKeepsSafeMarkup(): void
    {
        $html = MiniArticleHtml::sanitize('<p>OK</p><script>alert(1)</script><a href="https://example.com">Lien</a>');
        self::assertStringContainsString('<p>OK</p>', $html);
        self::assertStringContainsString('https://example.com', $html);
        self::assertStringNotContainsString('script', $html);
    }

    public function testParseTagsNormalizesAndLimits(): void
    {
        $tags = MiniArticleHtml::parseTags('Ops, RH; formation #Doctrine,  ');
        self::assertSame(['ops', 'rh', 'formation', 'doctrine'], $tags);
    }

    public function testSlugifyProducesStableSlug(): void
    {
        self::assertSame('doctrine-radio', MiniArticleHtml::slugify('Doctrine radio'));
        self::assertSame('article', MiniArticleHtml::slugify('@@@'));
    }
}
