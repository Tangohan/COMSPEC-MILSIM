<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Rendu des pages Effectifs : barre d'onglets, menu « Plus », raccourcis, recherche, Affectations.
 */
final class EffectifsShellRenderTest extends TestCase
{
    private array $session;

    protected function setUp(): void
    {
        $this->session = $_SESSION ?? [];
        $_SESSION = ['tenant_id' => 14, 'user_id' => 3];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->session;
    }

    /** @param array<string, mixed> $vars */
    private function render(array $vars): string
    {
        $file = dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/shell.php';
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

        return $render($file, $vars);
    }

    private function matrix(): array
    {
        $unit = ['id' => 3, 'name' => 'Groupe 1', 'type' => 'squad', 'own_code' => '', 'parent_id' => 2, 'path' => 'Escadron B / Troop A / Groupe 1',
            'depth' => 2, 'code' => 'SOAR-B-A-1', 'segment' => '1', 'admin_id' => '0002-0003', 'commander' => 'SSG Mercer', 'children' => 0];
        $member = ['id' => 7, 'name' => 'Ryan Mercer', 'callsign' => 'Viper', 'character_name' => '', 'grade' => 'SSG', 'unit_id' => 3,
            'unit_name' => 'Groupe 1', 'unit_path' => $unit['path'], 'unit_code' => 'SOAR-B-A-1', 'unit_admin_id' => '0002-0003',
            'role' => 'Chef de groupe', 'jobs' => [['name' => 'Infirmier de combat', 'detail' => '', 'primary' => true]], 'access' => ['Officier']];
        $unit['holders'] = [$member];

        return [
            'community' => ['code' => 'SOAR', 'admin_number' => 2, 'admin_id' => '0002', 'is_custom' => false],
            'members' => [$member, ['id' => 8, 'name' => 'Owen <Hale>', 'callsign' => '', 'character_name' => '', 'grade' => '', 'unit_id' => null,
                'unit_name' => '', 'unit_path' => '', 'unit_code' => '', 'unit_admin_id' => '', 'role' => '', 'jobs' => [], 'access' => []]],
            'units' => [$unit],
            'filters' => ['jobs' => ['Infirmier de combat'], 'roles' => ['Chef de groupe'], 'access' => ['Officier']],
            'stats' => ['members' => 2, 'assigned' => 1, 'without_unit' => 1, 'without_job' => 1, 'empty_units' => 0, 'truncated' => false],
        ];
    }

    public function testAffectationsPageShowsWhoHoldsWhatWithUnitCodes(): void
    {
        $html = $this->render([
            'effectifsNav' => 'affectations',
            'effectifsContent' => 'admin.effectifs_workspace.affectations',
            'rosterCounts' => ['total' => 2, 'active' => 2, 'no_unit' => 1],
            'rhInboxActionCount' => 3,
            'roleplayDueCount' => 2,
            'affMatrix' => $this->matrix(),
            'canManageAssignments' => true,
            'canEditCommunityCode' => true,
            'csrfToken' => 'tok',
            'communityName' => 'SOAR',
        ]);

        // Onglets : l'onglet actif est marqué, le menu « Plus » regroupe le reste et cumule les pastilles.
        self::assertMatchesRegularExpression('/class="bo-eff-tab is-active" aria-current="page">\s*<span>Affectations/', $html);
        self::assertStringContainsString('class="bo-eff-tabs__more" data-eff-menu>', $html);
        self::assertStringNotContainsString('data-eff-menu open', $html);
        self::assertStringContainsString('Suivi des dossiers', $html);
        self::assertStringContainsString('>Roleplay<', $html);
        // Raccourcis et fenêtre de recherche.
        self::assertStringContainsString('data-eff-search-open', $html);
        self::assertStringContainsString('<dialog class="eff-cmdk"', $html);
        self::assertStringContainsString('/recherche"', $html);
        self::assertStringContainsString('Ajouter un membre', $html);
        self::assertStringContainsString('Ouvrir l’ORBAT', $html);
        // Corrélation membre ↔ unité ↔ rôle ↔ emploi ↔ accès, avec code et identifiant.
        self::assertStringContainsString('<span class="eff-ucode__seg is-root">SOAR</span><span class="eff-ucode__seg">B</span>', $html);
        self::assertStringContainsString('0002-0003', $html);
        self::assertStringContainsString('Chef de groupe', $html);
        self::assertStringContainsString('Infirmier de combat', $html);
        self::assertStringContainsString('data-code="SOAR-B-A-1"', $html);
        self::assertStringContainsString('name="community_code" value="SOAR"', $html);
        self::assertStringContainsString('/codes/unites/3', $html);
        self::assertStringContainsString('Owen &lt;Hale&gt;', $html);
        self::assertStringNotContainsString('Owen <Hale>', $html);
        self::assertStringContainsString('Sans unité', $html);
    }

    public function testCommunityCodeFormIsHiddenWithoutAdministrationRight(): void
    {
        $html = $this->render([
            'effectifsNav' => 'affectations',
            'effectifsContent' => 'admin.effectifs_workspace.affectations',
            'affMatrix' => $this->matrix(),
            'canManageAssignments' => false,
            'canEditCommunityCode' => false,
        ]);

        self::assertStringNotContainsString('name="community_code"', $html);
        self::assertStringNotContainsString('/codes/unites/', $html);
        self::assertStringContainsString('SOAR-B-A-1', $html);
    }

    public function testShellAssetsAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/eff-workspace-shell.js');
        $css = (string) file_get_contents($root . '/public/assets/css/back-office-effectifs-maquette.css');
        $routes = (string) file_get_contents($root . '/routes/web.php');

        // Fermeture du menu au clic extérieur, à Échap, au retour arrière ; navigation au clavier.
        self::assertStringContainsString("addEventListener('pointerdown'", $js);
        self::assertStringContainsString("e.key === 'Escape'", $js);
        self::assertStringContainsString("'pageshow'", $js);
        self::assertStringContainsString("e.key === 'ArrowDown'", $js);
        self::assertStringContainsString("String(e.key).toLowerCase() === 'k'", $js);
        // Le menu n'est plus dans un conteneur qui défile (il était coupé).
        self::assertStringContainsString('.bo-eff-tabs__scroll', $css);
        self::assertStringNotContainsString(':has(.bo-eff-tabs__more[open])', (string) file_get_contents($root . '/public/assets/css/back-office-effectifs-refonte.css'));
        self::assertStringContainsString("/back-office/ressources/effectifs/recherche', [EffectifsDirectoryController::class, 'search']", $routes);
    }
}
