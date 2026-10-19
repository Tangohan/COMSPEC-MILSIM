<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReconPhotoUploadLimitTest extends TestCase
{
    public function testPhpLimitsAcceptNativeArmaCaptures(): void
    {
        $ini = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.user.ini');

        // Les captures terrain (PNG natifs Arma) ne sont plus plafonnées : 0 = pas de limite PHP.
        self::assertMatchesRegularExpression('/^upload_max_filesize\s*=\s*0$/m', $ini);
        self::assertMatchesRegularExpression('/^post_max_size\s*=\s*0$/m', $ini);
        self::assertStringContainsString('captures terrain', $ini);

        // Le garde-fou côté portail traite 0 comme « illimité » (pas de refus 413 sur une grosse capture).
        $helper = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/TerrainUploadedImage.php');
        self::assertStringContainsString('$postMax > 0 && $declared > $postMax', $helper);
    }
}
