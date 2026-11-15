<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseUiSystemAssetTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testLayoutLoadsTheSharedUiSystemLast(): void
    {
        $layout = $this->read('views/atak/sse/_layout.php');
        self::assertStringContainsString('assets/css/sse_ui.css', $layout);
        self::assertStringContainsString('assets/js/sse-ui.js', $layout);
        self::assertGreaterThan(strpos($layout, 'sse_shell.css'), strpos($layout, 'sse_ui.css'));
    }

    public function testTablesStackAsCardsOnPhoneWithoutEyebrowLabels(): void
    {
        $css = $this->read('public/assets/css/sse_ui.css');
        self::assertStringContainsString('table[data-sse-stack]', $css);
        self::assertStringContainsString('attr(data-label)', $css);
        self::assertStringNotContainsString('eyebrow', $css);
        self::assertStringContainsString('data-label', $this->read('public/assets/js/sse-ui.js'));
    }

    public function testNoPortalTextIsSmallerThanElevenPixels(): void
    {
        foreach (['sse_portal.css', 'sse_workspace.css', 'sse_shell.css', 'sse_field_note.css'] as $f) {
            $css = $this->read('public/assets/css/' . $f);
            $css = (string) preg_replace('/@media[^{]*print[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);
            self::assertSame(0, preg_match('/font-size:\s*(?:[0-9]|10)(?:\.\d+)?px/', $css), $f);
            self::assertSame(0, preg_match('/font-size:\s*0?\.[0-6]\d*rem/', $css), $f);
        }
    }
}
