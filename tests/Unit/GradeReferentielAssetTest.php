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
        self::assertStringContainsString('listBySystemCodeForTenantAdmin', $controller);
        self::assertStringContainsString('TenantGradeOverrideRepository', $controller);
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

        // Les grades du catalogue se masquent / se réaffichent (rien n'est supprimé) ; les grades
        // créés par la communauté sont des grades d'avancement, présentés à part.
        self::assertStringContainsString("url('back-office/referentiels/grades/create')", $view);
        self::assertStringContainsString("/' . \$gid . '/edit", $view);
        self::assertStringContainsString("/' . \$gid . '/deactivate", $view);
        self::assertStringContainsString("/' . \$gid . '/reactivate", $view);
        self::assertStringContainsString('>Masquer</button>', $view);
        self::assertStringContainsString('>Réafficher</button>', $view);
        self::assertStringNotContainsString('>Supprimer</button>', $view);
        self::assertStringContainsString('Grades d’avancement créés par la communauté', $view);
        self::assertStringContainsString("/{id}/edit'", $routes);
        self::assertStringContainsString("/{id}/update'", $routes);
        self::assertStringContainsString("/{id}/deactivate'", $routes);
        self::assertStringContainsString("/{id}/reactivate'", $routes);
    }
}
