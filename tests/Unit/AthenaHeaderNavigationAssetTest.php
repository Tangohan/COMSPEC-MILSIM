<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AthenaHeaderNavigationAssetTest extends TestCase
{
    public function testSpacesExposeTheBackOfficeToAdministrators(): void
    {
        $header = $this->headerSource();

        self::assertStringContainsString("'abbr' => 'BO'", $header);
        self::assertStringContainsString("'label' => 'Back-office'", $header);
        self::assertStringContainsString("'href' => url('back-office')", $header);
    }

    public function testQuickMenuIsAnIconPlacedAfterTheProfile(): void
    {
        $header = $this->headerSource();
        $profilePosition = strpos($header, 'data-athena-toggle="profile"');
        $quickMenuPosition = strpos($header, 'data-athena-toggle="quick"');

        self::assertIsInt($profilePosition);
        self::assertIsInt($quickMenuPosition);
        self::assertGreaterThan($profilePosition, $quickMenuPosition);
        self::assertStringNotContainsString('athena-header__menu-label', $header);
    }

    private function headerSource(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/athena_caverne_header.php');
    }
}
