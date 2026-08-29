<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DevDispatchCatalog;
use PHPUnit\Framework\TestCase;

final class DispatchArticleViewAssetTest extends TestCase
{
    public function testPartialDefaultsHeadingTagBeforeUse(): void
    {
        $partial = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dispatch_article.php');
        $dispatchView = (string) file_get_contents(dirname(__DIR__, 2) . '/views/site/dispatch.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SitePagesController.php');

        self::assertMatchesRegularExpression(
            '/\$dispatchHeadingTag\s*=\s*\$dispatchHeadingTag\s*\?\?\s*[\'"]h1[\'"]/',
            $partial
        );
        self::assertDoesNotMatchRegularExpression(
            '/in_array\(\s*\$dispatchHeadingTag\s*\?\?/',
            $partial
        );
        self::assertMatchesRegularExpression(
            '/\$dispatchHeadingTag\s*=\s*\$dispatchHeadingTag\s*\?\?\s*[\'"]h1[\'"]/',
            $dispatchView
        );
        self::assertMatchesRegularExpression(
            "/'dispatchHeadingTag'\s*=>\s*'h1'/",
            $controller
        );
    }

    public function testPartialRendersH1WhenHeadingTagIsOmitted(): void
    {
        $dispatch = DevDispatchCatalog::find('update', '00233');
        self::assertNotNull($dispatch);

        $html = $this->renderPartial($dispatch, null);

        self::assertStringContainsString('<h1 class="tr__title">', $html);
        self::assertStringNotContainsString('<h2 class="tr__title">', $html);
        self::assertStringNotContainsString('<h3 class="tr__title">', $html);
    }

    public function testPartialHonoursExplicitHeadingTags(): void
    {
        $dispatch = DevDispatchCatalog::find('update', '00233');
        self::assertNotNull($dispatch);

        $h2 = $this->renderPartial($dispatch, 'h2');
        $h3 = $this->renderPartial($dispatch, 'h3');

        self::assertStringContainsString('<h2 class="tr__title">', $h2);
        self::assertStringContainsString('<h3 class="tr__title">', $h3);
    }

    public function testControllerSetsH1ForDedicatedUpdateArticle(): void
    {
        $method = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SitePagesController.php');
        $dispatchFn = '';
        if (preg_match('/public function dispatch\(.*?^\s{4}\}/ms', $method, $m)) {
            $dispatchFn = $m[0];
        }
        self::assertNotSame('', $dispatchFn);
        self::assertStringContainsString("'content' => 'site.dispatch'", $dispatchFn);
        self::assertStringContainsString("'dispatchHeadingTag' => 'h1'", $dispatchFn);
    }

    /**
     * @param array<string, mixed> $dispatch
     */
    private function renderPartial(array $dispatch, ?string $headingTag): string
    {
        if ($headingTag !== null) {
            $dispatchHeadingTag = $headingTag;
        }

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            ob_start();
            require dirname(__DIR__, 2) . '/views/partials/dispatch_article.php';

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw $e;
        } finally {
            restore_error_handler();
        }
    }
}
