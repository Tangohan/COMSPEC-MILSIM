<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class InboxUnifiedAssetTest extends TestCase
{
    public function testInboxAggregatesSourcesAndOffersReadActions(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/InboxController.php');
        $view = (string) file_get_contents($root . '/views/portal/inbox.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $activity = (string) file_get_contents($root . '/app/Controllers/Web/ActivityHubController.php');
        $messages = (string) file_get_contents($root . '/app/Repositories/TenantMessageRepository.php');

        foreach (['listActivityThreadsForUser', 'listRecentForUser', 'activityFeedForCurrentRequest', 'markThreadRead', 'markReadForDocument'] as $call) {
            self::assertStringContainsString($call, $ctrl);
        }
        self::assertStringContainsString("'/boite-reception/lu'", $routes);
        foreach (['data-ibx-tab="', 'data-ibx-q', 'data-ibx-bulk', 'data-ibx-markall', 'Tout marquer comme lu', 'Marquer la sélection comme lue'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
        // /activite est fusionnée dans la boîte de réception.
        self::assertStringContainsString("Response::redirect(url('boite-reception'))", $activity);
        // Une seule définition du « non lu » pour les conversations : un message d’autrui après la dernière lecture.
        self::assertStringNotContainsString("t.updated_at > COALESCE(tu.last_read_at", $messages);
    }
}
