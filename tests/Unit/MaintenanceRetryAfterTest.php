<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MaintenanceGuard;
use PHPUnit\Framework\TestCase;

/**
 * NET-006 : la maintenance annonçait toujours Retry-After 900. La DLL COMSPEC Link (plafond 600 s) et l'Overwatch
 * web suspendent leurs envois pendant ce délai : une maintenance de 2 min figeait les positions près de 10 min.
 */
final class MaintenanceRetryAfterTest extends TestCase
{
    public function testRetryAfterFollowsTheAnnouncedEnd(): void
    {
        $now = (int) strtotime('2026-10-26 14:00:00');

        self::assertSame(120, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:02:00'], $now));
        self::assertSame(900, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 16:00:00'], $now), 'fin lointaine : délai inchangé');
        self::assertSame(15, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:00:05'], $now), 'fin imminente : plancher');
        self::assertSame(15, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 13:59:00'], $now), 'fin déjà passée : plancher');
    }

    public function testWithoutAnnouncedEndTheDelayIsUnchanged(): void
    {
        foreach ([[], ['ends_at' => null], ['ends_at' => ''], ['ends_at' => 'pas une date']] as $maintenance) {
            self::assertSame(900, MaintenanceGuard::retryAfterSeconds($maintenance, 1_000_000));
        }
    }

    public function testJsonPayloadAnnouncesTheSameDelay(): void
    {
        $now = (int) strtotime('2026-10-26 14:00:00');
        $payload = MaintenanceGuard::jsonPayload(['title' => 'Mise à jour', 'ends_at' => '2026-10-26 14:02:00'], 'Retour à 14 h 02.', $now);

        self::assertSame(120, $payload['retry_after']);
        self::assertSame('maintenance', $payload['error']);
    }

    public function testResponseHeaderUsesTheComputedDelay(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/MaintenanceGuard.php');

        self::assertStringContainsString("header('Retry-After: ' . self::retryAfterSeconds(\$maintenance, \$now));", $src);
        self::assertStringContainsString('self::jsonPayload($maintenance, $message, $now)', $src);
        self::assertStringNotContainsString("header('Retry-After: ' . self::DEFAULT_RETRY_AFTER)", $src);
    }
}
