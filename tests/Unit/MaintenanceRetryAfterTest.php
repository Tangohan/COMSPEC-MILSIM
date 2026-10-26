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

    public function testReturnsAreSpreadAfterAnAnnouncedEnd(): void
    {
        $now = (int) strtotime('2026-10-26 14:00:00');

        self::assertSame(127, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:02:00'], $now, 7));
        self::assertSame(30, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 14:00:05'], $now, 15), 'plancher puis étalement');
        self::assertSame(900, MaintenanceGuard::retryAfterSeconds(['ends_at' => '2026-10-26 16:00:00'], $now, 15), 'plafond conservé');
        self::assertSame(900, MaintenanceGuard::retryAfterSeconds([], $now, 15), 'sans fin annoncée : 900, sans étalement');
    }

    public function testJsonPayloadAnnouncesTheSameDelay(): void
    {
        $payload = MaintenanceGuard::jsonPayload(['title' => 'Mise à jour', 'ends_at' => '2026-10-26 14:02:00'], 'Retour à 14 h 02.', 127);

        self::assertSame(127, $payload['retry_after']);
        self::assertSame('maintenance', $payload['error']);
        self::assertSame(900, MaintenanceGuard::jsonPayload(['title' => 'Mise à jour'], 'Retour bientôt.')['retry_after']);
    }

    public function testHeaderAndBodyAnnounceOneComputedDelay(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/MaintenanceGuard.php');

        self::assertStringContainsString('$retryAfter = self::retryAfterSeconds($maintenance, time(), random_int(0, self::RETURN_SPREAD));', $src);
        self::assertStringContainsString("header('Retry-After: ' . \$retryAfter);", $src);
        self::assertStringContainsString('self::jsonPayload($maintenance, $message, $retryAfter)', $src);
        self::assertStringNotContainsString("header('Retry-After: ' . self::DEFAULT_RETRY_AFTER)", $src);
    }
}
