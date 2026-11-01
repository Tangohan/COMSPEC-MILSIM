<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Menu principal et pieds de page : plus d'entrée « Formations », « ATHENA Academy » devient « Academy ». */
final class AcademyNavigationRenameTest extends TestCase
{
    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testPortalMenuHasNoFormationsEntryAndNamesAcademy(): void
    {
        $header = $this->read('views/partials/athena_caverne_header.php');
        self::assertStringNotContainsString("'key' => 'formations'", $header);
        self::assertStringContainsString("['key' => 'academy', 'label' => 'Academy', 'href' => url('academy')", $header);
        // Les raccourcis « Formations » / « Mes formations » mènent à l'Academy et suivent les règles du module formation.
        self::assertStringContainsString("'href' => url('academy/catalogue'), 'path' => 'academy/formations'", $header);
        self::assertStringContainsString("'href' => url('academy/courses'), 'path' => 'academy/formations'", $header);
        self::assertStringNotContainsString("url('formations", $header);
    }

    public function testNoAthenaAcademyLabelRemainsInMenusAndFooters(): void
    {
        foreach (['views/partials/athena_caverne_header.php', 'views/partials/athena_header_guest.php', 'views/partials/home_nav_drawer.php',
            'views/layout/marketing.php', 'views/partials/portal_footer.php', 'views/academy/footer.php', 'views/academy/space.php'] as $file) {
            $source = $this->read($file);
            self::assertDoesNotMatchRegularExpression('/ATHENA[ ·]+Academy/i', $source, $file);
        }
        self::assertStringContainsString(">Academy</a>", $this->read('views/partials/portal_footer.php'));
        self::assertStringNotContainsString("url('formations')", $this->read('views/partials/portal_footer.php'));
        foreach (['fr', 'en'] as $language) {
            $copy = require dirname(__DIR__, 2) . '/lang/' . $language . '/academy.php';
            self::assertSame('ACADEMY', $copy['brand']);
        }
    }
}
