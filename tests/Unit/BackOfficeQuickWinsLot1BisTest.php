<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 1 « améliorations rapides » (suite) : 1.1, 1.7, 1.8, 1.10, 1.12, 1.14, 1.16, 1.18, 1.19. */
final class BackOfficeQuickWinsLot1BisTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $path));
    }

    private static function method(string $src, string $signature): string
    {
        $start = strpos($src, $signature);
        self::assertTrue($start !== false, $signature);
        $end = strpos($src, "\n    }\n", (int) $start);

        return substr($src, (int) $start, (int) $end - (int) $start);
    }

    public function testDeadRightsViewIsGone(): void
    {
        self::assertFalse(is_file(self::ROOT . 'views/admin/effectifs_workspace/droits.php'));
        $ctrl = self::src('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("return Response::redirect(effectifs_workspace_url('roles'));", self::method($ctrl, 'public function droits('));
    }

    public function testCsvExportKeepsEveryRosterFilter(): void
    {
        $ctrl = self::src('app/Controllers/Admin/EffectifsWorkspaceController.php');
        $export = self::method($ctrl, 'public function exportCsv(');
        foreach (["'langue'", "'fonction'", "'etiquette_fonction'", "'unite'", "'categorie'"] as $key) {
            self::assertStringContainsString($key, $export);
        }
        self::assertStringContainsString('applyRosterExtraFilters(', $export);
        // Le tableur et l'export partagent le même filtre : ils ne peuvent plus diverger.
        self::assertStringContainsString('applyRosterExtraFilters(', self::method($ctrl, 'public function roster('));
        self::assertStringContainsString('private function applyRosterExtraFilters(', $ctrl);

        $view = self::src('views/admin/effectifs_workspace/roster.php');
        $start = (int) strpos($view, '$exportQuery = array_filter([');
        $block = substr($view, $start, (int) strpos($view, '$exportUrl', $start) - $start);
        foreach (["'langue'", "'fonction'", "'etiquette_fonction'", "'unite'", "'categorie'"] as $key) {
            self::assertStringContainsString($key, $block);
        }
    }

    public function testBulkAssignmentSendsTheStructureChangeNotification(): void
    {
        $ctrl = self::src('app/Controllers/Admin/EffectifsWorkspaceController.php');
        $bulk = self::method($ctrl, 'public function bulkAssignment(');
        self::assertStringContainsString('structureChangeNotification->snapshot(', $bulk);
        self::assertStringContainsString('structureChangeNotification->notifyFromSnapshots(', $bulk);
        // Le droit reste vérifié côté serveur avant tout changement.
        self::assertTrue(strpos($bulk, 'canManageAssignments') < strpos($bulk, 'notifyFromSnapshots'));
    }

    public function testMonServiceShowsPlainLabelsAndBackLink(): void
    {
        $view = self::src('views/portal/action_center.php');
        self::assertStringContainsString("'open' => 'À traiter'", $view);
        self::assertStringContainsString("'normal' => 'Priorité normale'", $view);
        self::assertStringContainsString('$statusLabels[$status] ?? $status', $view);
        self::assertStringContainsString('$priorityLabels[$prio] ?? $prio', $view);
        self::assertStringContainsString("url('back-office')", $view);
    }

    public function testNoEyebrowInMobileBarNorPageConfig(): void
    {
        $layout = self::src('views/layout/main.php');
        self::assertStringNotContainsString('ath-mobile-bar__kicker', $layout);
        self::assertStringContainsString('$shellMobilePage !== \'\' ? $shellMobilePage : $adminSidebarShellMobileTitle', $layout);

        self::assertStringNotContainsString("'kicker'", self::src('config/back_office_pages.php'));
        $context = self::src('app/Support/BackOfficePageContext.php');
        self::assertStringNotContainsString("'kicker'", $context);
        self::assertStringNotContainsString('boPageKicker', $context);
    }

    public function testBadgesStopAtNinetyNinePlusEverywhere(): void
    {
        self::assertStringContainsString("\$n > 99 ? '99+'", self::src('app/Services/BackOffice/Menu/BackOfficeMenuBadges.php'));
        self::assertStringContainsString("n > 99 ? '99+'", self::src('public/assets/js/back-office-menu.js'));
        self::assertStringNotContainsString("'9+'", self::src('public/assets/js/back-office-menu.js'));
    }

    public function testSearchFieldOnlyWhereItWorksAndShortcutFollowsTheSystem(): void
    {
        $top = self::src('views/partials/back_office_topbar.php');
        $field = (int) strpos($top, '<label class="ath-topbar__search"');
        self::assertTrue($field > 0);
        self::assertStringContainsString('<?php if (!empty($isBackOfficeShell)): ?>', substr($top, max(0, $field - 120), 140));
        self::assertStringContainsString('data-ath-search-kbd', $top);

        $js = self::src('public/assets/js/back-office-search.js');
        self::assertStringContainsString('data-ath-search-kbd', $js);
        self::assertStringContainsString("isMac ? '⌘K' : 'Ctrl K'", $js);

        // Le shell plateforme ne charge ni la fenêtre ni le script : le champ y serait mort.
        $layout = self::src('views/layout/main.php');
        self::assertStringContainsString("!empty(\$isBackOfficeShell) && !empty(\\App\\Core\\Session::get('user_id'))", $layout);
    }

    public function testWardrobePageHasTheSameNameInMenuTitleAndSearch(): void
    {
        self::assertStringContainsString("'label' => 'Tenues mises en avant'", self::src('views/partials/ath_sidebar_nav.php'));
        foreach ([
            'app/Controllers/Admin/Organization/DashboardWardrobePinsAdminController.php',
            'app/Services/Portal/BackOfficeSearchService.php',
            'config/back_office_pages.php',
        ] as $file) {
            self::assertStringNotContainsString("'title' => 'Tenues du tableau de bord'", self::src($file), $file);
        }
        self::assertStringContainsString("'title' => 'Tenues mises en avant'", self::src('config/back_office_pages.php'));
    }

    public function testPlatformBarLinksTheSteamDuplicatesPage(): void
    {
        $bar = self::src('views/partials/platform_admin_sidebar.php');
        self::assertStringContainsString("'path' => 'admin/steam-doublons', 'label' => 'Steam en double'", $bar);
        // La page reste réservée à l'administration du site côté serveur.
        self::assertMatchesRegularExpression("~'/admin/steam-doublons'[^\n]*SystemAdminMiddleware~", self::src('routes/web.php'));
    }
}
