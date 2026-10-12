<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Le gabarit du bureau SSE n'affiche que $sseContent : une page qui remplit $content
 * s'affichait vide (cas de « Présentation des documents »).
 */
final class SsePortalViewsContentAssetTest extends TestCase
{
    public function testEveryPortalPageFillsTheLayoutContent(): void
    {
        $dir = dirname(__DIR__, 2) . '/views/atak/sse';
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);
            if (!str_contains($src, "require __DIR__ . '/_layout.php'")) {
                continue;
            }
            self::assertStringContainsString('$sseContent = ob_get_clean();', $src, basename($file));
        }
    }
}
