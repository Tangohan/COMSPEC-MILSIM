<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\SseApiController;
use PHPUnit\Framework\TestCase;

/** Le pack @COMSPEC_SSE envoie « case_reference », SEEK et l'ATAK natif « case_code ». */
final class SseCaseReferenceKeyTest extends TestCase
{
    public function testCaseReferenceFillsCaseCode(): void
    {
        $body = SseApiController::withCaseCode(['case_reference' => ' SSE-2026-0007 ']);
        self::assertSame('SSE-2026-0007', $body['case_code']);
    }

    public function testCaseCodeWins(): void
    {
        $body = SseApiController::withCaseCode(['case_code' => 'SSE-2026-0001', 'case_reference' => 'SSE-2026-0007']);
        self::assertSame('SSE-2026-0001', $body['case_code']);
    }

    public function testNothingToFill(): void
    {
        self::assertArrayNotHasKey('case_code', SseApiController::withCaseCode(['alias' => 'X']));
    }
}
