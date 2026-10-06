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
        $inbox = file_get_contents(base_path('views/messages/partials/inbox.php'));
        $css = file_get_contents(base_path('public/assets/css/back-office-messages.css'));
        $js = file_get_contents(base_path('public/assets/js/messages.js'));
        $pages = file_get_contents(base_path('config/back_office_pages.php'));

        foreach ([$controller, $layout, $index, $thread, $inbox, $css, $js, $pages] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString("'messagesPage' => true", $controller);
        self::assertStringContainsString("'backOfficePageCss' => ['back-office-messages.css']", $controller);
        self::assertStringContainsString('listInboxThreadsForUser', $controller);
        self::assertStringContainsString('markThreadRead', $controller);
        self::assertStringContainsString('assets/js/messages.js', $layout);
        self::assertStringContainsString("'path' => 'messages'", $pages);

        // Deux colonnes : la liste est partagée par l’accueil et le fil.
        self::assertStringContainsString("partials/inbox.php", $index);
        self::assertStringContainsString("partials/inbox.php", $thread);
        self::assertStringContainsString('data-msg-search', $inbox);
        self::assertStringContainsString('data-msg-filter="unread"', $inbox);
        self::assertStringContainsString('data-msg-compose', $inbox);
        self::assertStringContainsString('data-msg-stream', $thread);
        self::assertStringContainsString('msgx-back', $thread);

        // CSRF conservé sur les deux formulaires.
        self::assertStringContainsString('Csrf::field()', $index);
        self::assertStringContainsString('Csrf::field()', $thread);
        self::assertStringContainsString("url('messages/' . \$threadId . '/reply')", $thread);

        self::assertStringContainsString('.msgx-frame', $css);
        self::assertStringContainsString('@media (max-width: 760px)', $css);
        self::assertStringContainsString('html[data-bo-theme="dark"] .msgx', $css);
        self::assertStringContainsString("normalize('NFD')", $js);
        self::assertStringContainsString('e.ctrlKey || e.metaKey', $js);
        self::assertStringContainsString('data-msg-unread', $js);
    }
}
