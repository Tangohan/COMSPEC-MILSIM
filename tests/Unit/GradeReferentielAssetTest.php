<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GradeReferentielAssetTest extends TestCase
{
    public function testControllerReadsTabFromQueryStringAndPreservesCountryAfterActions(): void
    {
        $controller = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/GradeReferentielController.php'
        );

        self::assertStringContainsString("query('tab', 'fr')", $controller);
        self::assertStringNotContainsString("input('tab')", $controller);
        self::assertStringContainsString("indexUrl(\$returnTab)", $controller);
        self::assertStringContainsString("indexUrl(\$this->tabForSystemId(\$systemId))", $controller);
        self::assertStringContainsString("indexUrl(\$this->tabForGrade(\$grade))", $controller);
    }

    public function testListExposesWorkingCreateEditAndDeleteControls(): void
    {
        $view = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/organization/referentiels/grades/index.php'
        );
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString("grades/create') ?>?tab=", $view);
        self::assertStringContainsString("/' . \$g['id'] . '/edit", $view);
        self::assertStringContainsString("/' . \$g['id'] . '/deactivate", $view);
        self::assertStringContainsString('>Supprimer</button>', $view);
        self::assertStringContainsString("/{id}/edit'", $routes);
        self::assertStringContainsString("/{id}/update'", $routes);
        self::assertStringContainsString("/{id}/deactivate'", $routes);
    }
}
