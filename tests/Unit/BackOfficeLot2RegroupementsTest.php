<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BackOfficeLegacyRedirects;
use App\Support\BackOfficeNavTree;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 2 « regroupements d’écrans » : liens d’onglets, groupe Configuration, Discipline, grades. */
final class BackOfficeLot2RegroupementsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private static function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $path));
    }

    /** @return array<string, string> ancienne adresse => onglet qui la remplace */
    public static function tabLinks(): array
    {
        return [
            'back-office/atak/temps-ecran' => 'back-office/metriques-joueurs?onglet=temps-ecran',
            'back-office/atak/suivi-cr' => 'back-office/atak/comptes-rendus?onglet=suivi',
            'back-office/documents/compliance' => 'back-office/doctrine?onglet=conformite',
            'back-office/planification' => 'back-office/missions?onglet=planification',
            'back-office/roleplay/immersion' => 'back-office/roleplay-followup?onglet=immersion',
            'back-office/roleplay/regles-phases' => 'back-office/roleplay-followup?onglet=phases',
        ];
    }

    public function testMegaMenuLinksGoToTheTabAndKeepTheLegacyPermissionKey(): void
    {
        $menu = self::src('app/Support/navigation_menu.php');
        // Le lien suit l’onglet, la clé `path` (règles de droits, profil de communauté) reste l’ancienne adresse.
        self::assertStringContainsString("'href' => \\App\\Support\\BackOfficeTabs::urlFor(\$pathFragment),", $menu);
        self::assertStringContainsString("'path' => \$routePath,", $menu);

        $config = self::src('config/navigation.php');
        $map = BackOfficeTabs::legacyRedirects();
        foreach (self::tabLinks() as $legacy => $tab) {
            self::assertSame($tab, $map[$legacy], $legacy);
            self::assertStringContainsString("'path' => '" . $legacy . "'", $config, $legacy . ' : la clé de droits doit rester');
        }
    }

    public function testEffectifsOrbatShortcutUsesTheSingleOrbatPage(): void
    {
        $shell = self::src('views/admin/effectifs_workspace/shell.php');
        self::assertStringContainsString("['Ouvrir l’ORBAT', orbat_page_url(), '']", $shell);
        self::assertStringNotContainsString("url('back-office/organisation/structure')", $shell);
    }

    public function testSidebarSmallFixes(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        // Condition dupliquée retirée.
        self::assertStringContainsString("\$navAtakRoleplayActive = str_starts_with(\$p, 'back-office/atak/roleplay');", $nav);
        self::assertStringNotContainsString("roleplay') || str_starts_with(\$p, 'back-office/atak/roleplay')", $nav);
        // Overwatch : actif sur sa page, dans les deux menus.
        self::assertStringContainsString("\$navOverwatchActive = \$p === 'atak';", $nav);
        self::assertSame(2, substr_count($nav, "'label' => 'Overwatch', 'href' => url('atak')"));
        self::assertSame(2, substr_count($nav, "'active' => \$navOverwatchActive"));
        // Départs : masqué sans le droit que la page exige.
        self::assertStringContainsString('$canManageMemberStatus = \\App\\Support\\EffectifsLmsAccess::canManageStatus($gate);', $nav);
        self::assertMatchesRegularExpression(
            "/\\\$canManageMemberStatus\n\s+\? \['label' => 'Anciens membres', 'href' => effectifs_workspace_url\('departs'\)/",
            $nav
        );
    }

    public function testDashboardLinksAreCalledHomeAndPortal(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        self::assertStringNotContainsString("'label' => 'Tableau de bord'", $nav);
        self::assertSame(2, substr_count($nav, "'label' => 'Accueil', 'href' => url('back-office')"));
        $topbar = self::src('views/partials/back_office_topbar.php');
        self::assertStringContainsString("i18n_phrase('nav', 'Portail')", $topbar);
        $sidebar = self::src('views/partials/back_office_sidebar.php');
        self::assertStringContainsString('Retour au portail', $sidebar);
        self::assertStringNotContainsString('Retour au tableau de bord', $sidebar);
    }

    public function testConfigurationGroupIsLastCollapsedAndHoldsTheRareSettings(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        [, $config] = explode("'key' => 'configuration',", $nav, 2);
        [$config] = explode('    ];' . "\n" . '}', $config, 2);
        self::assertStringContainsString("'collapsed' => true,", $config);
        $order = ["'label' => 'Configuration', 'href' => url('back-office/configuration')", "'label' => 'Organisation'", "'label' => 'Référentiels'", "'label' => 'Droits et accès'",
            "'label' => 'ATAK'", "'label' => 'Intégrations'", '$rhSettingsItem', "'label' => 'Configuration initiale'", "'label' => 'Mises à jour'"];
        $last = -1;
        foreach ($order as $needle) {
            $pos = strpos($config, $needle);
            self::assertIsInt($pos, $needle);
            self::assertGreaterThan($last, $pos, $needle);
            $last = $pos;
        }
        // Les anciens groupes séparés n’existent plus ; aucune page n’a disparu du menu.
        self::assertStringNotContainsString("'key' => 'acces'", $nav);
        self::assertStringNotContainsString("'key' => 'systeme',\n            'label' => 'RÉGLAGES ET INTÉGRATIONS',\n            'items' => array_values", $nav);
        self::assertStringNotContainsString("'label' => 'DROITS ET ACCÈS'", $nav);
        foreach (['Registre des droits', 'Circuits de validation', 'Positions administratives', 'Sécurité et blocages', 'Journal d’audit', 'Réglages RH', 'Catalogue de l’organisation'] as $page) {
            self::assertSame(1, substr_count($nav, "'label' => '" . $page . "'"), $page);
        }
        // L’organigramme reste au quotidien, dans Effectifs.
        self::assertStringContainsString("['label' => 'Organigramme', 'href' => orbat_page_url()", $nav);
    }

    public function testConfigurationGroupIsClosedByDefaultUnlessItHoldsTheCurrentPage(): void
    {
        $sidebar = self::src('views/partials/back_office_sidebar.php');
        self::assertStringContainsString("if (!empty(\$navGroup['collapsed'])) {", $sidebar);
        self::assertStringContainsString("<?= \$navGroupOpen ? ' is-open' : '' ?>", $sidebar);
        self::assertStringContainsString('data-ath-default-closed', $sidebar);
        self::assertStringContainsString("aria-expanded=\"<?= \$navGroupOpen ? 'true' : 'false' ?>\"", $sidebar);

        $js = self::src('public/assets/js/back-office-sidebar.js');
        self::assertStringContainsString("group.hasAttribute('data-ath-default-closed')", $js);
        self::assertStringContainsString('closedByDefault ? stored === true : stored !== false', $js);
        // Un groupe qui contient la page courante reste ouvert.
        self::assertStringContainsString("var open = hasCurrent || (", $js);
    }

    public function testCollapsedFlagSurvivesFilteringAndPermissionsStayPerPage(): void
    {
        $tree = [[
            'key' => 'configuration', 'label' => 'CONFIGURATION', 'collapsed' => true,
            'items' => [
                ['label' => 'Droits et accès', 'href' => 'back-office/roles', 'children' => [
                    ['label' => 'Rôles', 'href' => 'back-office/roles', 'active' => false],
                    ['label' => 'Journal d’audit', 'href' => 'back-office/audit', 'active' => false],
                ]],
            ],
        ]];
        $filtered = BackOfficeNavTree::filter($tree, static fn (string $href): bool => $href === 'back-office/audit');
        self::assertCount(1, $filtered);
        self::assertTrue($filtered[0]['collapsed']);
        // Sans droit sur la page d’accueil du groupe, l’entrée pointe vers la première page autorisée.
        self::assertSame('back-office/audit', $filtered[0]['items'][0]['href']);
        self::assertCount(1, $filtered[0]['items'][0]['children']);
        $resolved = BackOfficeNavTree::resolve($filtered, 'back-office/audit');
        self::assertTrue($resolved[0]['collapsed']);
        self::assertTrue($resolved[0]['items'][0]['active']);
        self::assertSame([], BackOfficeNavTree::filter($tree, static fn (string $href): bool => false));
    }

    public function testConfigurationPageTellsWhereTheEntriesWent(): void
    {
        $view = self::src('views/admin/configuration.php');
        self::assertStringContainsString('class="bo-cfg-where"', $view);
        self::assertStringContainsString('Où sont passés les réglages du menu ?', $view);
        self::assertStringContainsString('Aucune page n’a changé d’adresse.', $view);
        self::assertStringContainsString('.bo-cfg-where', self::src('public/assets/css/back-office-configuration.css'));
    }

    public function testDisciplineGroupsSanctionsReportsAndQuarantine(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        [, $discipline] = explode('$disciplineChildren = array_values(array_filter([', $nav, 2);
        [$discipline] = explode('static fn', $discipline, 2);
        foreach (["url('back-office/moderation')", "url('back-office/signalements')", "url('back-office/content-moderation')"] as $href) {
            self::assertStringContainsString($href, $discipline, $href);
        }
        // Les sanctions gardent leur condition de droit.
        self::assertStringContainsString('$canMemberModeration', $discipline);
        self::assertStringContainsString("'label' => 'Discipline',", $nav);
        self::assertStringNotContainsString("'label' => 'Modération',", $nav);
        // Plus de doublon : les sanctions ne sont plus aussi listées sous Effectifs.
        self::assertSame(1, substr_count($nav, "'label' => 'Sanctions & absences'"));
    }

    public function testGradesReferentialHasTabsAndOldAddressesRedirect(): void
    {
        $family = BackOfficeTabs::FAMILIES['back-office/referentiels/grades'] ?? null;
        self::assertIsArray($family);
        self::assertTrue($family['bar']);
        self::assertSame(['avancement', 'catalogue'], array_keys($family['tabs']));
        self::assertSame('back-office/organisation/grades', $family['tabs']['avancement']['legacy']);
        self::assertSame('back-office/referentiels/grades/catalogue', $family['tabs']['catalogue']['legacy']);
        // Même middlewares que les anciennes routes : les droits ne bougent pas.
        foreach ($family['tabs'] as $tab) {
            self::assertSame($family['middleware'], $tab['middleware']);
        }
        self::assertSame('back-office/referentiels/grades?onglet=avancement', BackOfficeLegacyRedirects::target('back-office/organisation/grades'));
        self::assertSame('back-office/referentiels/grades?onglet=catalogue', BackOfficeLegacyRedirects::target('back-office/referentiels/grades/catalogue'));

        $routes = self::src('routes/web.php');
        // Les anciennes pages ne sont plus servies en direct (la redirection répond), les formulaires gardent leurs adresses.
        foreach (['/back-office/referentiels/grades', '/back-office/referentiels/grades/catalogue', '/back-office/organisation/grades'] as $path) {
            self::assertStringNotContainsString("\$router->get('" . $path . "',", $routes, $path);
        }
        foreach ([
            "\$router->post('/back-office/referentiels/grades/catalogue/audit'",
            "\$router->post('/back-office/organisation/grades/ordre'",
            "\$router->get('/back-office/organisation/grades/create'",
            "\$router->get('/back-office/organisation/grades/{id}/edit'",
            "\$router->get('/back-office/referentiels/grades/create'",
        ] as $kept) {
            self::assertStringContainsString($kept, $routes, $kept);
        }
    }

    public function testGradeControllersAndViewsLinkStraightToTheTab(): void
    {
        foreach ([
            'app/Controllers/Admin/Organization/AdvancementAdminController.php',
            'app/Controllers/Admin/Organization/RankCatalogAdminController.php',
            'views/admin/advancement/campaigns_index.php',
            'views/admin/advancement/campaign_form.php',
            'views/admin/advancement/campaign_show.php',
            'views/admin/advancement/grade_form.php',
        ] as $file) {
            $code = self::src($file);
            self::assertStringContainsString('BackOfficeTabs::urlFor(', $code, $file);
            self::assertStringNotContainsString("redirect(url('back-office/organisation/grades'))", $code, $file);
            self::assertStringNotContainsString("adv_h(url('back-office/organisation/grades'))", $code, $file);
        }
    }

    public function testNoPopupAndNoEyebrowInTheNewCopy(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        $view = self::src('views/admin/configuration.php');
        foreach ([$nav, $view] as $code) {
            self::assertStringNotContainsString('systemChat', $code);
            self::assertStringNotContainsString('data-toast', $code);
        }
        // Pas de petit libellé au-dessus du titre pour la ligne « où est passé… ».
        self::assertStringNotContainsString('bo-cfg-where__kicker', $view);
    }
}
