<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Portal\UnifiedActionDigestService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class UnifiedActionDigestPresentationTest extends TestCase
{
    public function testAgendaItemsExposeInlineRsvpMetadata(): void
    {
        $service = file_get_contents(__DIR__ . '/../../app/Services/Portal/UnifiedActionDigestService.php');

        self::assertIsString($service);
        self::assertStringContainsString("'event_id' => (int) (\$event['id'] ?? 0)", $service);
        self::assertStringContainsString("'rsvp_status' => \$rsvp", $service);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function rsvpHints(): iterable
    {
        yield 'answer required' => ['', 'Réponse attendue', 'Camp principal'];
        yield 'confirmed' => ['yes', 'Présence confirmée', 'Camp principal'];
        yield 'absent' => ['no', 'Absence signalée', 'Camp principal'];
        yield 'maybe' => ['maybe', 'Présence incertaine', 'Camp principal'];
    }

    #[DataProvider('rsvpHints')]
    public function testAgendaHintExplainsDateLocationAndRsvp(string $rsvp, string $expected, string $location): void
    {
        $method = new ReflectionMethod(UnifiedActionDigestService::class, 'formatAgendaHint');
        $timestamp = strtotime('tomorrow 20:30');
        self::assertIsInt($timestamp);

        $hint = $method->invoke(null, $timestamp, $location, $rsvp);

        self::assertIsString($hint);
        self::assertStringContainsString($expected, $hint);
        self::assertStringContainsString($location, $hint);
    }
}
