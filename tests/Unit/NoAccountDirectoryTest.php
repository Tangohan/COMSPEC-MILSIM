<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\NoAccountDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Personnes sans compte : statut, filtres, et garde-fous des actions (CSRF, droits, périmètre, migrations).
 */
final class NoAccountDirectoryTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testClassify(): void
    {
        $fiche = ['email' => 'fiche-ab12@sans-compte.invalid', 'status' => 'active'];
        self::assertSame('fiche', NoAccountDirectory::classify($fiche));
        self::assertSame('invitee', NoAccountDirectory::classify($fiche, 'opened'));
        self::assertSame('invitee', NoAccountDirectory::classify($fiche, 'active'));
        self::assertSame('fiche', NoAccountDirectory::classify($fiche, 'expired'), 'un lien expiré ne compte plus');
        self::assertSame('fiche', NoAccountDirectory::classify($fiche, 'completed'));

        self::assertSame('en_attente', NoAccountDirectory::classify(['email' => 'a@b.fr', 'status' => 'pending_verification', 'last_login_at' => null]));
        self::assertNull(NoAccountDirectory::classify(['email' => 'a@b.fr', 'status' => 'pending_verification', 'last_login_at' => '2026-01-01 10:00:00']));
        self::assertNull(NoAccountDirectory::classify(['email' => 'a@b.fr', 'status' => 'active', 'last_login_at' => null]));
        self::assertNull(NoAccountDirectory::classify(['email' => 'merged+4@merged.invalid', 'status' => 'merged']));
        self::assertNull(NoAccountDirectory::classify(['email' => 'fiche-x@sans-compte.invalid', 'status' => 'merged']), 'une fiche rattachée disparaît de la liste');
        self::assertNull(NoAccountDirectory::classify(['email' => 'fiche-x@sans-compte.invalid', 'status' => 'left']));
    }

    public function testFilterAndSort(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Zoé', 'callsign' => 'Faucon', 'status' => 'fiche', 'source' => 'manual', 'last_activity' => '2026-11-01 10:00:00'],
            ['id' => 2, 'name' => 'Adam', 'callsign' => '', 'status' => 'invitee', 'source' => 'import', 'last_activity' => '2026-11-05 10:00:00'],
            ['id' => 3, 'name' => 'Léa', 'callsign' => 'Zéphyr', 'status' => 'fiche', 'source' => 'import', 'last_activity' => '2026-10-01 10:00:00'],
        ];
        self::assertSame([2, 1, 3], array_column(NoAccountDirectory::filter($rows, []), 'id'), 'dernière activité d’abord');
        self::assertSame(2, NoAccountDirectory::filter($rows, ['sort' => 'nom'])[0]['id']);
        self::assertSame([1, 3], array_column(NoAccountDirectory::filter($rows, ['status' => 'fiche']), 'id'));
        self::assertSame([2, 3], array_column(NoAccountDirectory::filter($rows, ['source' => 'import']), 'id'));
        self::assertSame([3], array_column(NoAccountDirectory::filter($rows, ['q' => 'ZÉPH']), 'id'));
        self::assertSame('Fiche existante', NoAccountDirectory::sourceLabel(''));
        self::assertSame('Import de tableur', NoAccountDirectory::sourceLabel('import'));
    }

    public function testActionsCheckRightsCsrfAndTenant(): void
    {
        $src = (string) file_get_contents(self::root() . '/app/Controllers/Admin/EffectifsSansComptesController.php');
        self::assertStringContainsString('Csrf::validate', $src);
        self::assertStringContainsString('EffectifsLmsAccess::canCreateMembers', $src);
        self::assertStringContainsString('EffectifsLmsAccess::canEditProfiles', $src);
        self::assertStringContainsString("Session::get('tenant_id')", $src);
        self::assertStringContainsString('findById($id, $tenantId)', $src);
        self::assertStringNotContainsString('$request->input(\'tenant_id\'', $src, 'la communauté vient toujours de la session');

        $tables = (string) file_get_contents(self::root() . '/app/Controllers/Admin/EffectifsTablesController.php');
        self::assertStringContainsString('Csrf::validate', $tables);
        self::assertStringContainsString('DataTableSources::allowed', $tables);
        self::assertStringNotContainsString("input('tenant_id'", $tables);
    }

    public function testRoutesAndMenuReachNewPages(): void
    {
        $routes = (string) file_get_contents(self::root() . '/routes/web.php');
        foreach (['effectifs/sans-comptes\'', 'sans-comptes/creer', 'sans-comptes/{id}/rattacher', 'sans-comptes/importer', 'effectifs/tableaux\'', 'tableaux/export.csv', 'tableaux/enregistrer', 'tableaux/tableur'] as $needle) {
            self::assertStringContainsString($needle, $routes, $needle);
        }
        $menu = (string) file_get_contents(self::root() . '/views/partials/ath_sidebar_nav.php');
        self::assertStringContainsString("effectifs_workspace_url('sans-comptes')", $menu);
        self::assertStringContainsString("effectifs_workspace_url('tableaux')", $menu);
    }

    public function testMigrationsAreRegisteredRerunnableAndUseUnsignedIds(): void
    {
        $registry = (string) file_get_contents(self::root() . '/database/steps/registry.php');
        foreach (['4200_roster_fiche_events.php', '4210_bo_table_defs.php'] as $file) {
            self::assertMatchesRegularExpression('/\'file\' => \'' . preg_quote($file, '/') . '\', \'rerun\' => true\]/', $registry);
            $sql = (string) file_get_contents(self::root() . '/database/steps/' . $file);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS', $sql);
            self::assertStringContainsString('tenant_id INT UNSIGNED NOT NULL', $sql);
            self::assertStringNotContainsString('tenant_id INT NOT NULL', $sql);
        }
    }
}
