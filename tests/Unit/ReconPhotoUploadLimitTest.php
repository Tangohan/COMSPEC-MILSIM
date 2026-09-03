<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReconPhotoUploadLimitTest extends TestCase
{
    public function testPhpLimitsAcceptNativeArmaCaptures(): void
    {
        $ini = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.user.ini');

        self::assertMatchesRegularExpression('/^upload_max_filesize\s*=\s*96M$/m', $ini);
        self::assertMatchesRegularExpression('/^post_max_size\s*=\s*100M$/m', $ini);
        self::assertStringContainsString('captures Arma PNG', $ini);
    }
}
