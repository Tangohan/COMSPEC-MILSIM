<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Les traitements automatiques du portail SSE s'affichent sous le nom de Cerbère, jamais « Suggestions du moteur ». */
final class SseNoMoteurLabelTest extends TestCase
{
    public function testNoMoteurSuggestionLabelInSseViews(): void
    {
        $root = dirname(__DIR__, 2);
        $files = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/views/atak/sse')), '/\.php$/');
        $hits = [];
        foreach ($files as $f) {
            if (str_contains((string) file_get_contents($f->getPathname()), 'Suggestions du moteur')) {
                $hits[] = $f->getPathname();
            }
        }
        self::assertSame([], $hits);
        self::assertStringNotContainsString("'label' => 'Suggestions du moteur'", (string) file_get_contents($root . '/app/Services/Sse/SseTriageInbox.php'));
    }
}
