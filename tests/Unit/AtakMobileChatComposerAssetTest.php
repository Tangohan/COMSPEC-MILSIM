<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakMobileChatComposerAssetTest extends TestCase
{
    public function testChatComposerIsNotRebuiltOnPollAndMessagesAreHumanReadable(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/atak/mobile.php');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-mobile/atak-mobile.js');
        $css = (string) file_get_contents($root . '/public/assets/css/atak-mobile.css');

        self::assertStringContainsString('id="am-chat-form"', $view);
        self::assertStringContainsString('<textarea id="am-chat-input"', $view);
        self::assertStringContainsString('id="am-chat-list"', $view);
        self::assertStringNotContainsString('id="am-screen-chat" hidden></section>', $view);

        self::assertStringContainsString('chatSignature', $js);
        self::assertStringContainsString('chatDraft', $js);
        self::assertStringContainsString("getElementById('am-chat-list')", $js);
        self::assertStringNotContainsString("getElementById('am-screen-chat')", $js);
        self::assertStringContainsString("cut.toUpperCase().indexOf('GROUPE|')", $js);
        self::assertStringContainsString('Alerte médicale', $js);
        self::assertStringContainsString('am-msg--med', $js);
        self::assertStringContainsString('am-msg--group', $js);
        self::assertStringContainsString('every(\'chat\', 3000, loadChat)', $js);

        self::assertStringContainsString('.am-msg--med', $css);
        self::assertStringContainsString('.am-msg--group', $css);
        self::assertStringContainsString('.am-chat__composer textarea', $css);
        self::assertStringContainsString('font-size: 0.98rem', $css);

        self::assertFileExists($root . '/docs/bugs/2026-09-01-atak-mobile-tchat-saisie.md');
    }
}
