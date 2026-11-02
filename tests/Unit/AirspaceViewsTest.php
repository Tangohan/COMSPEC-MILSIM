<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\AirspaceController;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

/** Espace aérien : chaque vue (?vue=…) est lue dans la query string, a son onglet et sa section. */
final class AirspaceViewsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testGetParametersAreReadFromQueryStringNotPostBody(): void
    {
        $src = (string) file_get_contents(self::ROOT . '/app/Controllers/Admin/Organization/AirspaceController.php');
        foreach (['vue', 'carnet', 'filiere'] as $param) {
            self::assertStringNotContainsString("input('" . $param . "'", $src, "?{$param}= est un paramètre GET : input() ne lit que le corps POST.");
            self::assertStringContainsString("queryString('" . $param . "'", $src);
        }

        $_GET = ['vue' => 'flotte'];
        $_POST = [];
        $request = new Request();
        self::assertSame('flotte', $request->queryString('vue', 'ligne'));
        self::assertSame('ligne', (string) $request->input('vue', 'ligne'));
        $_GET = [];
    }

    public function testEveryViewHasATabAndASection(): void
    {
        $hero = (string) file_get_contents(self::ROOT . '/views/admin/airspace/_hero.php');
        $index = (string) file_get_contents(self::ROOT . '/views/admin/airspace/index.php');
        foreach (AirspaceController::VIEWS as $view) {
            if ($view === 'ligne') {
                self::assertStringContainsString("['ligne', ", $hero);
                self::assertStringContainsString("\$aeroView === 'ligne'", $index);
                continue;
            }
            self::assertStringContainsString("'?vue=" . $view . "'", $hero, "Onglet manquant pour ?vue={$view}");
            if ($view !== 'flotte') {
                self::assertStringContainsString("\$aeroView === '" . $view . "'", $index, "Section manquante pour ?vue={$view}");
            }
        }
        // La flotte est la branche « else » finale.
        self::assertSame('flotte', AirspaceController::VIEWS[count(AirspaceController::VIEWS) - 1]);
        self::assertStringContainsString('<h2>Flotte et filières</h2>', $index);
    }
}
