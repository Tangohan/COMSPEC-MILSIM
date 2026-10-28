<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakChatToastAssetTest extends TestCase
{
    public function testIncomingChatShowsToastAfterHistorySeedNotOnLoad(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-chat.js');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');

        self::assertStringContainsString('function notifyIncomingChat', $js);
        self::assertStringContainsString("notifyIncomingChat(list, { notify: prevFp !== '' })", $js);
        self::assertStringContainsString('incomingHistorySeeded', $js);
        self::assertStringContainsString('if (!opts.notify || !incomingHistorySeeded)', $js);
        self::assertStringContainsString('isOwnChatMessage', $js);
        self::assertStringContainsString('window.ATAKShowNotification(msg, { silent: silent })', $js);
        self::assertStringContainsString('nouveaux messages', $js);
        self::assertStringContainsString('isSilentMode', $js);
        self::assertStringContainsString("id=\"atak-notification-toast\"", $view);
        self::assertStringContainsString('window.ATAKShowNotification = function', $view);
    }

    public function testChatToastSkipsOwnMessagesAndInitialHistory(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-chat.js');

        self::assertStringContainsString('function isOwnChatMessage', $js);
        self::assertStringContainsString('getMyCallsigns()', $js);
        self::assertStringContainsString('lastIncomingSeenId', $js);
        self::assertStringContainsString('if (id > 0 && id > lastIncomingSeenId)', $js);
        // Premier chargement : mémoriser le dernier id vu sans toast (le commentaire a disparu, le code reste).
        self::assertMatchesRegularExpression(
            '/lastIncomingSeenId = newestId;\\s*if \\(!opts\\.notify \\|\\| !incomingHistorySeeded\\) \\{\\s*incomingHistorySeeded = true;\\s*return;\\s*\\}/',
            $js
        );
    }
}
