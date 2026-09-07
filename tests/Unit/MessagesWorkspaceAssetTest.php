<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MessagesWorkspaceAssetTest extends TestCase
{
    public function testMessagingViewsLoadDedicatedWorkspaceAssetsAndInteractions(): void
    {
        $controller = file_get_contents(base_path('app/Controllers/Web/TenantMessagesController.php'));
        $layout = file_get_contents(base_path('views/layout/main.php'));
        $index = file_get_contents(base_path('views/messages/index.php'));
        $thread = file_get_contents(base_path('views/messages/thread.php'));
        $css = file_get_contents(base_path('public/assets/css/messages.css'));
        $js = file_get_contents(base_path('public/assets/js/messages.js'));

        self::assertIsString($controller);
        self::assertIsString($layout);
        self::assertIsString($index);
        self::assertIsString($thread);
        self::assertIsString($css);
        self::assertIsString($js);
        self::assertStringContainsString("'messagesPage' => true", $controller);
        self::assertStringContainsString('assets/css/messages.css', $layout);
        self::assertStringContainsString('assets/js/messages.js', $layout);
        self::assertStringContainsString('data-msg-search', $index);
        self::assertStringContainsString('data-msg-compose', $index);
        self::assertStringContainsString('data-msg-stream', $thread);
        self::assertStringContainsString('.msg-hero', $css);
        self::assertStringContainsString('@media(max-width:600px)', $css);
        self::assertStringContainsString("normalize('NFD')", $js);
        self::assertStringContainsString('scrollIntoView', $js);
    }
}
