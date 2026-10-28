<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DocumentManuscript;
use App\Support\MiniArticleHtml;
use App\Support\SafeHtml;
use PHPUnit\Framework\TestCase;

final class SafeHtmlTest extends TestCase
{
    public function testUnquotedEventHandlersAreRemoved(): void
    {
        $html = MiniArticleHtml::sanitize('<div onmouseover=alert(1) style=position:fixed;inset:0>x</div>');
        self::assertSame('<div>x</div>', $html);

        $fm = DocumentManuscript::sanitizeHtml('<p onmouseover=alert(document.cookie) style=position:fixed;inset:0>x</p>');
        self::assertSame('<p>x</p>', $fm);
    }

    public function testUnsafeUrlsAndTagsAreDropped(): void
    {
        $html = MiniArticleHtml::sanitize('<a href=" java&#9;script:alert(1)">x</a><img src=x onerror=alert(1)><svg><script>1</script></svg>');
        self::assertStringNotContainsString('javascript', $html);
        self::assertStringNotContainsString('onerror', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('script', $html);
    }

    public function testDocumentProfileKeepsLayoutAndPlaceholders(): void
    {
        $html = SafeHtml::clean(
            '<p class="text-right" style="text-align: right; position: fixed">{{user.name}} [[REDACT]]a[[/REDACT]]</p>',
            SafeHtml::documentProfile()
        );
        self::assertSame('<p class="text-right" style="text-align: right">{{user.name}} [[REDACT]]a[[/REDACT]]</p>', $html);
    }
}
