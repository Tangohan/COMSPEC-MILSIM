<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PersonnelCorrectionsQueueAssetTest extends TestCase
{
    public function testQueueUsesLightBackOfficeChrome(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/personnel/corrections_queue.php');
        $pages = (string) file_get_contents($root . '/config/back_office_pages.php');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-corrections.css');

        self::assertStringContainsString("'path' => 'back-office/personnel/corrections'", $pages);
        self::assertStringContainsString('Corrections RH', $pages);
        self::assertStringContainsString('back-office-corrections.css', $pages);
        self::assertStringContainsString('rh-corr__empty', $view);
        self::assertStringContainsString('Confirmer', $view);
        self::assertStringContainsString('Refuser', $view);
        self::assertStringNotContainsString('text-white', $view);
        self::assertStringNotContainsString('bg-slate-900', $view);
        self::assertStringContainsString('#0f172a', $css);
        self::assertStringContainsString('.rh-corr__empty', $css);
    }
}
