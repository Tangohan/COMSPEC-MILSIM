<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BoAgendaCalendarAssetTest extends TestCase
{
    public function testAgendaDefaultsToCalendarMonthView(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/CommunityEventsAdminController.php'
        );
        $repo = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Repositories/CommunityEventRepository.php'
        );
        $view = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/partials/ath_events_ops.php'
        );
        $css = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/css/back-office-events.css'
        );
        $nav = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/partials/ath_sidebar_nav.php'
        );

        self::assertStringContainsString("query('vue', 'calendrier')", $controller);
        self::assertStringContainsString('buildCalendarMonth', $controller);
        self::assertStringContainsString('eventsCalendarMonth', $controller);
        self::assertStringContainsString("'calendrier'", $repo);
        self::assertStringContainsString('ce.starts_at < ?', $repo);
        self::assertStringContainsString('ath-cal__grid', $view);
        self::assertStringContainsString('vue=calendrier', $view);
        self::assertStringContainsString('.ath-cal__grid', $css);
        self::assertStringContainsString('vue=calendrier', $nav);
    }
}
