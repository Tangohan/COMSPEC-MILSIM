<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Media\MissingUserMediaScanner;
use PHPUnit\Framework\TestCase;

final class MissingUserMediaScannerTest extends TestCase
{
    public function testExternalUrlsAreNotFlagged(): void
    {
        $scanner = new MissingUserMediaScanner();
        self::assertFalse($scanner->isBrokenLocalPath('https://steamcdn.example/avatar.jpg'));
        self::assertFalse($scanner->isBrokenLocalPath(''));
        self::assertFalse($scanner->isBrokenLocalPath('not-an-upload/path.png'));
    }

    public function testMissingLocalUploadIsFlagged(): void
    {
        $scanner = new MissingUserMediaScanner();
        self::assertTrue($scanner->isBrokenLocalPath('uploads/avatars/missing-user-99999.png'));
    }
}
