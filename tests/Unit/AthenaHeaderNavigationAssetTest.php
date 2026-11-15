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
        // Administrators see "Back-office"; managers without admin rights see their own "Mon back-office"; a plain member
        // goes to their JNET space and never through the back-office.
        self::assertStringContainsString('\'label\' => $canAdmin ? \'Back-office\' : ($boPlainMember ? \'Mon espace JNET\' : \'Mon back-office\')', $header);
        self::assertStringContainsString("\$boHref = url('back-office');", $header);
        self::assertStringContainsString("'href' => \$boHref", $header);
        self::assertStringContainsString('PortalAccessChoice::plainMemberHome()', $header);
    }

    public function testQuickNavigationMenuIsNotRendered(): void
    {
        $header = $this->headerSource();

        self::assertStringNotContainsString('data-athena-toggle="quick"', $header);
        self::assertStringNotContainsString('athena-header__panel--quick', $header);
        self::assertStringNotContainsString('$quickLinks', $header);
    }

    private function headerSource(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/athena_caverne_header.php');
    }
}
