<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Agrandissement des images Academy : seul un lien d'image marqué data-rt-zoom ouvre la fenêtre.
 * Régression : le <body> portait data-rt-zoom, tout clic (ex. cartes « Explorer le catalogue »)
 * ouvrait une fenêtre vide.
 */
final class AcademyRichTextZoomWiringTest extends TestCase
{
    public function testZoomIsBoundToImageLinksOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/views/academy/header.php');
        $js = (string) file_get_contents($root . '/public/assets/js/academy-richtext.js');

        self::assertDoesNotMatchRegularExpression('/<body[^>]*data-rt-zoom/', $header);
        self::assertStringContainsString("closest?.('a[data-rt-zoom]')", $js);
        self::assertStringNotContainsString("closest?.('[data-rt-zoom]')", $js);
    }
}
