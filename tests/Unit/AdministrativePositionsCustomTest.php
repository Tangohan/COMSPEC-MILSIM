<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdministrativePositionsController as P;
use App\Services\Personnel\PersonnelDutyPositionService as Duty;
use App\Support\AdministrativePositionCatalog as Catalog;
use PHPUnit\Framework\TestCase;

/** Positions administratives créées par une communauté, en plus des quatre du site. */
final class AdministrativePositionsCustomTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    protected function tearDown(): void
    {
        Catalog::forget();
        parent::tearDown();
    }

    public function testCustomPositionsJoinTheCatalogOfTheirCommunityOnly(): void
    {
        Catalog::setCustomForTests(7, [['id' => 3, 'slug' => 'status_custom_ab12', 'label' => 'En permission', 'summary' => 'Absent jusqu’à une date prévue.', 'tone' => 'warn']]);
        self::assertSame(5, count(Catalog::all(7)));
        self::assertSame('En permission', Catalog::label('status_custom_ab12', 7));
        self::assertTrue(Catalog::isKnown('status_custom_ab12', 7));
        self::assertFalse(Catalog::isKnown('status_custom_ab12', 8));
        self::assertSame('status_custom_ab12', Catalog::slugs(7)[4]);
        self::assertSame(4, count(Catalog::builtIn()));
        // Hors session (ligne de commande) et sans communauté : les quatre positions du site.
        self::assertSame(4, count(Catalog::all()));
        self::assertTrue(Catalog::isCustomSlug('status_custom_ab12'));
        self::assertFalse(Catalog::isCustomSlug(Catalog::ACTIVE));
        // Une couleur inconnue retombe sur le gris.
        Catalog::setCustomForTests(9, [['id' => 1, 'slug' => 'status_custom_x', 'label' => 'X', 'tone' => 'rainbow']]);
        self::assertSame('muted', Catalog::find('status_custom_x', 9)['tone']);
    }

    public function testCustomPositionIsNeverChangedAutomatically(): void
    {
        // Fin d'accueil ou rattrapage : un membre placé dans une position de la communauté la garde.
        self::assertSame('status_custom_ab12', Duty::decideSlug('status_custom_ab12', false, false, false));
        self::assertSame('status_custom_ab12', Duty::decideSlug('status_custom_ab12', true, false, false));
        self::assertSame(Duty::SLUG_ACTIVE, Duty::decideSlug('status_custom_ab12', false, true, false));
        $service = (string) file_get_contents(self::ROOT . 'app/Services/Personnel/PersonnelDutyPositionService.php');
        self::assertStringContainsString('$current !== null && $current !== self::SLUG_TRAINING && $current !== self::SLUG_ACTIVE', $service);
        self::assertStringContainsString("self::allSlugs(\$tenantId)", $service);
    }

    public function testFormValuesAreCleaned(): void
    {
        [$values, $error] = P::clean(['label' => '  <b>En   permission</b> ', 'tone' => 'pink', 'summary' => str_repeat('a', 400)], ['en service actif']);
        self::assertSame('', $error);
        self::assertSame('En permission', $values['label']);
        self::assertSame('muted', $values['tone']);
        self::assertSame(P::SUMMARY_MAX, mb_strlen($values['summary']));
        self::assertNull(P::clean(['label' => 'x'], [])[0]);
        // Un nom déjà pris (y compris une position du site) est refusé.
        self::assertSame('Une position porte déjà ce nom.', P::clean(['label' => 'En service actif'], ['en service actif'])[1]);
    }

    public function testPageIsWired(): void
    {
        $routes = (string) file_get_contents(self::ROOT . 'routes/web.php');
        self::assertStringContainsString("get('/back-office/ressources/effectifs/positions', [EffectifsWorkspaceController::class, 'positions']", $routes);
        self::assertStringContainsString("post('/back-office/ressources/effectifs/positions/archiver', [\\App\\Controllers\\Admin\\AdministrativePositionsController::class, 'archive']", $routes);
        self::assertStringContainsString("effectifs_workspace_url('positions')", (string) file_get_contents(self::ROOT . 'views/partials/ath_sidebar_nav.php'));
        self::assertStringContainsString("['positions', 'Positions administratives', 'positions', 0, 'Organisation']", (string) file_get_contents(self::ROOT . 'views/admin/effectifs_workspace/shell.php'));
        $registry = (string) file_get_contents(self::ROOT . 'database/steps/registry.php');
        self::assertStringContainsString("'file' => '4510_tenant_administrative_positions.php'", $registry);
        // Archivage refusé tant qu'un membre porte la position.
        $ctrl = (string) file_get_contents(self::ROOT . 'app/Controllers/Admin/AdministrativePositionsController.php');
        self::assertStringContainsString('Changez d’abord leur position sur leur fiche.', $ctrl);
    }
}
