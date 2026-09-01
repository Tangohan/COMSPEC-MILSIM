<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakChatJournalAlignmentAssetTest extends TestCase
{
    public function testRadioJournalMessagesShareTheSameLeftEdge(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');

        self::assertStringContainsString('padding: 0.5rem 0.65rem', $css);
        self::assertStringContainsString('.atak-chat-input-wrap {', $css);
        self::assertStringContainsString('padding: 0.45rem 0.65rem', $css);
        self::assertStringNotContainsString('margin-left: 0.85rem', $css);
        self::assertStringContainsString('.atak-chat-msg--pc', $css);
        self::assertStringContainsString('margin-left: 0', $css);
        self::assertStringContainsString('.atak-chat-group-text', $css);
        self::assertStringContainsString('background: transparent', $css);
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/bugs/2026-09-01-atak-journal-radio-alignement.md');
    }
}
