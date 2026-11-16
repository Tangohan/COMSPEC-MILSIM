<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Services\BackOffice\Menu\BadgeProvider;
use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacyEngine;
use App\Support\BackOfficeNavTree;
use App\Support\BackOfficeTabs;
use App\Support\CommandChainNav;
use PHPUnit\Framework\TestCase;

/**
 * Chaîne de commandement : sous-navigation (adéquation, échelons, référentiels liés), menu latéral, page refaite
 * (bande, cartes de poste, résumé d’adéquation, onglets) et non-régression des routes.
 */
final class CommandChainNavigationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private array $session;
    private array $get;

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $this->get = $_GET;
        $_SESSION = ['tenant_id' => 14, 'user_id' => 3];
        Gate::reset();
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
        $_GET = $this->get;
        Gate::reset();
    }

    private static function src(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . $path));
    }

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
        $render = static function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            try {
                require $__file;
            } finally {
                $out = (string) ob_get_clean();
            }

            return $out;
        };

        return $render(self::ROOT . 'views/admin/effectifs_workspace/shell.php', $vars);
    }

    /** @return array<string, mixed> */
    private static function chainVars(): array
    {
        $p = static fn (string $l, string $n, string $g): array => ['label' => $l, 'has_grade' => true, 'grade_label' => $g, 'grade_level' => GradeLevel::fromCode($n)];
        $adequacy = OrgAdequacyEngine::evaluate([
            'settings' => [],
            'profile' => EchelonStandards::PROFILE_US,
            'units' => [
                ['id' => 1, 'parent_id' => 0, 'name' => 'Escadron', 'commander_user_id' => 7, 'echelon' => 'compagnie'],
                ['id' => 2, 'parent_id' => 1, 'name' => 'Groupe 1', 'commander_user_id' => 8, 'echelon' => 'groupe'],
                ['id' => 3, 'parent_id' => 1, 'name' => 'Groupe 2', 'commander_user_id' => 0, 'echelon' => 'groupe'],
            ],
            'members_by_unit' => [1 => [7], 2 => [8], 3 => [9]],
            'primary_unit' => [7 => 1, 8 => 2, 9 => 3],
            'people' => [7 => $p('Mercer', 'OF-2', 'Captain'), 8 => $p('Hale', 'OR-4', 'Corporal'), 9 => $p('Reyes', 'OR-4', 'Corporal')],
        ]);
        $units = [
            ['id' => 1, 'name' => 'Escadron', 'type' => 'squadron', 'code' => 'ESC-1', 'echelon' => 'compagnie', 'depth' => 0, 'parent_id' => 0, 'parent_name' => '', 'commander_user_id' => 7, 'commander_label' => 'Mercer', 'visibility' => 'normal', 'member_count' => 1, 'member_ids' => [7]],
            ['id' => 2, 'name' => 'Groupe 1', 'type' => 'squad', 'code' => 'GR-1', 'echelon' => 'groupe', 'depth' => 1, 'parent_id' => 1, 'parent_name' => 'Escadron', 'commander_user_id' => 8, 'commander_label' => 'Hale', 'visibility' => 'normal', 'member_count' => 1, 'member_ids' => [8]],
            ['id' => 3, 'name' => 'Groupe 2', 'type' => 'squad', 'code' => '', 'echelon' => 'groupe', 'depth' => 1, 'parent_id' => 1, 'parent_name' => 'Escadron', 'commander_user_id' => 0, 'commander_label' => '', 'visibility' => 'normal', 'member_count' => 1, 'member_ids' => [9]],
        ];
        $members = [
            ['user_id' => 7, 'label' => 'Mercer', 'callsign' => 'BRAVO 6', 'unit_id' => 1, 'unit_name' => 'Escadron', 'role_name' => '', 'reports_to_id' => 0, 'reports_to_label' => '', 'chain_labels' => [], 'chain_ids' => [], 'commands' => ['Escadron']],
            ['user_id' => 8, 'label' => 'Hale', 'callsign' => '', 'unit_id' => 2, 'unit_name' => 'Groupe 1', 'role_name' => '', 'reports_to_id' => 7, 'reports_to_label' => 'Mercer', 'chain_labels' => ['Mercer'], 'chain_ids' => [7], 'commands' => ['Groupe 1']],
            ['user_id' => 9, 'label' => 'Reyes', 'callsign' => '', 'unit_id' => 3, 'unit_name' => 'Groupe 2', 'role_name' => '', 'reports_to_id' => 7, 'reports_to_label' => 'Mercer', 'chain_labels' => ['Mercer'], 'chain_ids' => [7], 'commands' => []],
        ];

        return [
            'effectifsNav' => 'chaine',
            'effectifsContent' => 'admin.effectifs_workspace.chaine',
            'chainUnits' => $units,
            'chainMembers' => $members,
            'chainMemberOptions' => [['id' => 7, 'label' => 'Mercer'], ['id' => 8, 'label' => 'Hale'], ['id' => 9, 'label' => 'Reyes']],
            'chainMissingCommanders' => 1,
            'commandChainMissingCount' => 1,
            'adequacyIssueCount' => count($adequacy['issues']),
            'chainAdequacy' => $adequacy,
            'canManageAssignments' => true,
            'csrfToken' => 'tok',
        ];
    }

    public function testSubnavLinksPointToExistingPagesAndFollowRights(): void
    {
        $routes = self::src('routes/web.php');
        foreach (CommandChainNav::PAGES as [$key, , $path]) {
            $served = str_contains($routes, "get('/" . $path . "'") || isset(BackOfficeTabs::FAMILIES[$path]);
            self::assertTrue($served, $key . ' : aucune page servie à ' . $path . ' (lien mort)');
        }

        $all = CommandChainNav::links('adequation', static fn (string $href): bool => true, ['chaine' => 2, 'adequation' => 5]);
        self::assertSame(['chaine', 'adequation', 'echelons', 'grades', 'qualifications', 'fonctions', 'doctrine'], array_column($all, 'key'));
        $current = array_values(array_filter($all, static fn (array $l): bool => $l['current']));
        self::assertCount(1, $current);
        self::assertSame('adequation', $current[0]['key']);
        self::assertSame(2, $all[0]['badge']);
        self::assertSame(5, $all[1]['badge']);
        self::assertStringEndsWith('chaine/adequation?onglet=echelons', $all[2]['href']);

        // Sans droit sur une page, son lien disparaît ; rien n’est ajouté au-delà des droits.
        $only = CommandChainNav::links('chaine', static fn (string $href): bool => str_contains($href, 'ressources/effectifs'));
        self::assertSame(['chaine', 'adequation', 'echelons'], array_column($only, 'key'));
        self::assertSame([], CommandChainNav::links('chaine', static fn (string $href): bool => false));

        self::assertSame('chaine', CommandChainNav::currentKey('back-office/ressources/effectifs/chaine'));
        self::assertSame('adequation', CommandChainNav::currentKey('/back-office/ressources/effectifs/chaine/adequation', 'regles'));
        self::assertSame('echelons', CommandChainNav::currentKey('back-office/ressources/effectifs/chaine/adequation', 'echelons'));
        self::assertSame('', CommandChainNav::currentKey('back-office/ressources/effectifs/roles'));
    }

    public function testShellShowsSubnavWithCurrentStateAndMenuAllowedLinks(): void
    {
        Gate::getInstance()->setPermissions(['admin.organization', 'admin.access', 'organization.effectifs.hub.view', 'personnel.assignments.manage']);
        $html = $this->render(self::chainVars());

        self::assertStringContainsString('<nav class="chn-subnav" aria-label="Chaîne de commandement" data-chn-subnav>', $html);
        self::assertMatchesRegularExpression('~class="chn-subnav__a is-current" href="[^"]*/effectifs/chaine" data-chn-subnav="chaine" aria-current="page"~', $html);
        self::assertSame(1, substr_count($html, 'class="chn-subnav__a is-current"'));
        self::assertMatchesRegularExpression('~data-chn-subnav="adequation"[^>]*>\s*<span>Adéquation</span>\s*<span class="chn-subnav__n" data-chn-subnav-n>~', $html, 'écarts d’adéquation en pastille');
        $allowed = CommandChainNav::menuAllowed();
        foreach (CommandChainNav::PAGES as [$key, $label, $path, $query]) {
            $href = url($path) . ($query !== '' ? '?' . $query : '');
            self::assertSame($allowed($href), str_contains($html, 'href="' . htmlspecialchars($href, ENT_QUOTES) . '"'), $key . ' : lien selon les droits du menu');
        }
        // Onglet de l’espace : l’adéquation est atteignable depuis « Plus › Organisation ».
        self::assertMatchesRegularExpression('~href="[^"]*/effectifs/chaine/adequation"[^>]*>\s*<span>Adéquation de l’organisation</span>~', $html);

        // Page Adéquation : son onglet est actif, la sous-navigation suit l’onglet « échelons ».
        $adq = $this->render(['effectifsNav' => 'adequation', 'effectifsContent' => 'admin.effectifs_workspace.vue-absente', 'adequacyTab' => 'echelons', 'adequacyIssueCount' => 3]);
        self::assertMatchesRegularExpression('~class="bo-eff-tab is-active" aria-current="page">\s*<span>Adéquation de l’organisation</span>~', $adq);
        self::assertMatchesRegularExpression('~is-current" href="[^"]*chaine/adequation\?onglet=echelons" data-chn-subnav="echelons" aria-current="page"~', $adq);

        // Ailleurs dans l’espace : pas de sous-navigation.
        $roles = $this->render(['effectifsNav' => 'positions', 'effectifsContent' => 'admin.effectifs_workspace.vue-absente']);
        self::assertStringNotContainsString('data-chn-subnav', $roles);
    }

    public function testSidebarDeclaresChainSectionWithActiveStates(): void
    {
        $nav = self::src('views/partials/ath_sidebar_nav.php');
        self::assertMatchesRegularExpression("~'label' => 'Chaîne de commandement',\n\s*'children' => \[~", $nav);
        foreach (["effectifs_workspace_url('chaine'), 'active' => \$p === \$effPath . '/chaine'",
            "effectifs_workspace_url('chaine/adequation'), 'active' => \$navAt(\$effPath . '/chaine/adequation') && \$navChainOnglet !== 'echelons'",
            "effectifs_workspace_url('chaine/adequation') . '?onglet=echelons', 'active' => \$navAt(\$effPath . '/chaine/adequation') && \$navChainOnglet === 'echelons'",
            "url('back-office/roles-functions'), 'active' => \$navDoctrineActive"] as $entry) {
            self::assertStringContainsString($entry, $nav);
        }
        // Les adresses du sous-menu sont relevées par le contrôle de couverture du menu.
        $aside = BackOfficeMenuCoverageTest::asidePaths();
        foreach (['back-office/ressources/effectifs/chaine', 'back-office/ressources/effectifs/chaine/adequation', 'back-office/roles-functions'] as $path) {
            self::assertArrayHasKey($path, $aside);
        }

        // Une seule page courante, la plus précise, et les droits retirent les entrées.
        $section = ['label' => 'Chaîne de commandement', 'children' => [
            ['label' => 'Vue de la chaîne', 'href' => '/back-office/ressources/effectifs/chaine', 'active' => false],
            ['label' => 'Adéquation', 'href' => '/back-office/ressources/effectifs/chaine/adequation', 'active' => true],
            ['label' => 'Échelons attendus', 'href' => '/back-office/ressources/effectifs/chaine/adequation?onglet=echelons', 'active' => false],
        ]];
        $groups = [['key' => 'effectifs', 'items' => [['label' => 'Effectifs', 'href' => '/back-office/ressources/effectifs', 'children' => [$section]]]]];
        $resolved = BackOfficeNavTree::resolve($groups, 'back-office/ressources/effectifs/chaine/adequation');
        $links = $resolved[0]['items'][0]['children'][0]['children'];
        self::assertSame([false, true, false], array_column($links, 'current'));
        $filtered = BackOfficeNavTree::filter($groups, static fn (string $href): bool => !str_contains($href, 'chaine'));
        self::assertSame([], $filtered[0]['items'][0]['children']);
    }

    public function testMenuBadgeMatchesTheTabCount(): void
    {
        $cfg = require self::ROOT . 'config/back_office_menu.php';
        $badge = $cfg['badges']['chaine_chefs'] ?? null;
        self::assertIsArray($badge);
        self::assertSame('back-office/ressources/effectifs/chaine', $badge['path']);
        self::assertTrue(is_subclass_of($badge['provider'], BadgeProvider::class));
        self::assertStringContainsString('countWithoutCommander', self::src('app/Services/BackOffice/Menu/Badges/CommandChainVacanciesBadge.php'));
        self::assertStringContainsString('countWithoutCommander', self::src('app/Controllers/Admin/EffectifsWorkspaceController.php'), 'même compte que l’onglet');
    }

    public function testChainPageHasBandCardsAdequacySummaryAndTabs(): void
    {
        $html = $this->render(self::chainVars());

        // En-tête en bande : utilité, chiffres clés, actions.
        self::assertStringContainsString('<header class="bo-band bo-band--hero bo-band--sky chn-hero" aria-labelledby="chn-title">', $html);
        self::assertMatchesRegularExpression('~<strong>2<small>/3</small></strong><span>postes de chef pourvus</span>~', $html);
        self::assertMatchesRegularExpression('~<p class="bo-figure is-hot"><strong>1</strong><span>à pourvoir</span>~', $html);
        self::assertStringContainsString('écarts d’adéquation</span>', $html);
        // Aucun surtitre : ni étiquette de bande, ni petit libellé au-dessus d’un titre.
        self::assertStringNotContainsString('bo-band__tag', $html);
        self::assertStringNotContainsString('effk-k', $html);
        self::assertStringNotContainsString('effk-page-head', $html);

        // Résumé d’adéquation à l’ancre conservée, par type, vers la page refaite.
        self::assertMatchesRegularExpression('~<section class="chn-adq" id="eff-adequacy"~', $html);
        self::assertStringContainsString('<ul class="chn-adq__types" aria-label="Écarts par type">', $html);
        self::assertStringContainsString('<span>Chef à désigner</span> <b>1</b>', $html);
        self::assertMatchesRegularExpression('~class="chn-btn chn-btn--primary" href="[^"]*/effectifs/chaine/adequation">Ouvrir l’adéquation~', $html);

        // Onglets ARIA : un seul sélectionné, chaque onglet relié à son panneau.
        self::assertStringContainsString('role="tablist" aria-label="Sections de la chaîne de commandement"', $html);
        self::assertSame(4, substr_count($html, 'role="tab"'));
        self::assertSame(1, substr_count($html, 'aria-selected="true"'));
        foreach (['organigramme', 'corriger', 'chefs', 'releve'] as $k) {
            self::assertStringContainsString('aria-controls="chn-panel-' . $k . '"', $html);
            self::assertMatchesRegularExpression('~role="tabpanel" id="chn-panel-' . $k . '" aria-labelledby="chn-tab-' . $k . '"~', $html);
        }
        self::assertStringContainsString('id="eff-chain-setup"', $html);
        self::assertStringContainsString('id="eff-chain-people-panel"', $html);

        // Cartes de poste : grade, nom, indicatif, état ; filtre par état et échelon ; détail en fenêtre.
        self::assertMatchesRegularExpression('~data-chn-card="1" data-chain-member="7" aria-haspopup="dialog">~', $html);
        self::assertStringContainsString('<span class="chn-card__grade" translate="no">Captain</span>', $html);
        self::assertStringContainsString('<span class="chn-card__cs" translate="no">BRAVO 6</span>', $html);
        self::assertMatchesRegularExpression('~data-chn-unit="3" data-chn-path="1 3" data-chn-echelon="groupe" data-chn-state="vacant[^"]*"~', $html);
        self::assertStringContainsString('<span class="chn-pill is-warn">À pourvoir</span>', $html);
        self::assertStringContainsString('<template data-chn-detail="3" data-chn-title="Groupe 2"', $html);
        self::assertStringContainsString('data-chn-act="pick" data-unit="3">Désigner un chef</button>', $html);
        self::assertStringContainsString('<span class="chn-crest" translate="no" aria-hidden="true">GR1</span>', $html);
        // Fenêtre : titre puis ligne de contexte, jamais l’inverse.
        self::assertMatchesRegularExpression('~<h2 id="eff-chain-dlg-title" data-chain-dlg-title></h2><p class="chn-dlg__sub"~', $html);

        // Actions inchangées : envoi commanders[unité], jeton, propositions et tableau.
        self::assertStringContainsString('name="commanders[3]"', $html);
        self::assertStringContainsString('name="expected_commanders[3]" value="0"', $html);
        self::assertStringContainsString('data-chain-bulk', $html);
        self::assertStringContainsString('eff-chain-page.js', $html);
    }

    public function testViewParameterAndReadOnly(): void
    {
        $vars = self::chainVars();
        $chefs = $this->render($vars + ['chainView' => 'chefs']);
        self::assertMatchesRegularExpression('~id="chn-tab-chefs" href="[^"]*\?vue=chefs"\s+aria-controls="chn-panel-chefs" aria-selected="true"~', $chefs);
        self::assertMatchesRegularExpression('~id="chn-panel-organigramme"[^>]*hidden>~', $chefs);
        self::assertMatchesRegularExpression('~id="chn-panel-chefs"[^>]*data-chn-panel="chefs">~', $chefs);

        $read = $this->render(array_replace($vars, ['canManageAssignments' => false]));
        self::assertStringNotContainsString('data-chn-act="pick"', $read);
        self::assertStringNotContainsString('data-chn-act="move"', $read);
        self::assertStringNotContainsString('name="commanders[', $read);
        self::assertStringContainsString('data-chn-act="follow"', $read, 'lecture : la fenêtre de détail reste ouverte à tous');
        self::assertStringContainsString('data-chain-dialog', $read);
    }

    public function testRoutesRightsAndAssetsAreUnchanged(): void
    {
        $routes = self::src('routes/web.php');
        foreach (["get('/back-office/ressources/effectifs/chaine', [EffectifsWorkspaceController::class, 'commandChain'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]",
            "post('/back-office/ressources/effectifs/chaine', [EffectifsWorkspaceController::class, 'saveCommandChain'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]",
            "get('/back-office/ressources/effectifs/chaine/adequation', [EffectifsWorkspaceController::class, 'adequacySettings'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]"] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        $ctrl = self::src('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString("'effectifsNav' => 'adequation'", $ctrl);
        self::assertStringContainsString("'gaps' => self::adequacyIssueCount(\$vars['chainAdequacy'])", $ctrl);

        $js = self::src('public/assets/js/eff-chain-page.js');
        foreach (["'ArrowRight'", "'ArrowLeft'", "'Home'", "'End'", 'chain:swapped', 'eff-adequacy'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        $chain = self::src('public/assets/js/eff-chain.js');
        self::assertStringContainsString('function openDetail(unitId)', $chain);
        self::assertStringContainsString("dispatchEvent(new CustomEvent('chain:swapped'))", $chain);
        $css = self::src('public/assets/css/eff-chain-page.css');
        self::assertDoesNotMatchRegularExpression('/sse-ds|\.sse-|Archivo/', $css . $js);
        self::assertStringContainsString('html[data-bo-theme="dark"] .chn', $css);
        self::assertStringContainsString('min-height: 44px', $css);
        self::assertFileExists(self::ROOT . 'docs/dev/EFFECTIFS-NAVIGATION-CHAINE.md');
    }
}
