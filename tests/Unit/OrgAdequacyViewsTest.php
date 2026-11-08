<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Organization\EchelonStandards;
use App\Services\Organization\GradeLevel;
use App\Services\Organization\OrgAdequacyEngine;
use App\Services\Organization\OrgAdequacySettings;
use App\Services\Organization\OrgAdequacyView;
use PHPUnit\Framework\TestCase;

/**
 * Affichage de l’adéquation : chaîne (pastilles, filtre « écarts »), fiche membre, réglages, ORBAT, câblage.
 */
final class OrgAdequacyViewsTest extends TestCase
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
    private function render(string $view, array $vars): string
    {
        $file = dirname(__DIR__, 2) . '/views/' . $view;
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

    private static function sample(): array
    {
        $p = static fn (string $l, ?string $n): array => ['label' => $l, 'has_grade' => $n !== null, 'grade_label' => (string) $n, 'grade_level' => $n !== null ? GradeLevel::fromCode($n) : null];

        return OrgAdequacyEngine::evaluate([
            'settings' => [],
            'profile' => EchelonStandards::PROFILE_US,
            'units' => [
                ['id' => 1, 'parent_id' => 0, 'name' => 'Escadron', 'commander_user_id' => 7, 'echelon' => 'compagnie'],
                ['id' => 2, 'parent_id' => 1, 'name' => 'Groupe <1>', 'commander_user_id' => 8, 'echelon' => 'groupe'],
            ],
            'members_by_unit' => [1 => [7], 2 => [8]],
            'primary_unit' => [7 => 1, 8 => 2],
            'people' => [7 => $p('Mercer', 'OF-2'), 8 => $p('Hale', 'OR-4'), 9 => $p('Sans Place', 'OR-3')],
        ]);
    }

    public function testCommandChainShowsGapsBadgesAndFilter(): void
    {
        $units = [
            ['id' => 1, 'name' => 'Escadron', 'type' => 'squadron', 'code' => '', 'depth' => 0, 'parent_id' => 0, 'parent_name' => '', 'commander_user_id' => 7, 'commander_label' => 'Mercer', 'visibility' => 'normal', 'member_count' => 1, 'member_ids' => [7]],
            ['id' => 2, 'name' => 'Groupe <1>', 'type' => 'squad', 'code' => '', 'depth' => 1, 'parent_id' => 1, 'parent_name' => 'Escadron', 'commander_user_id' => 8, 'commander_label' => 'Hale', 'visibility' => 'hidden', 'member_count' => 1, 'member_ids' => [8]],
        ];
        $members = [
            ['user_id' => 7, 'label' => 'Mercer', 'unit_id' => 1, 'unit_name' => 'Escadron', 'role_name' => '', 'reports_to_id' => 0, 'reports_to_label' => '', 'chain_labels' => [], 'chain_ids' => [], 'commands' => ['Escadron']],
            ['user_id' => 8, 'label' => 'Hale', 'unit_id' => 2, 'unit_name' => 'Groupe <1>', 'role_name' => '', 'reports_to_id' => 7, 'reports_to_label' => 'Mercer', 'chain_labels' => ['Mercer'], 'chain_ids' => [7], 'commands' => ['Groupe <1>']],
        ];
        $vars = [
            'effectifsNav' => 'chaine',
            'effectifsContent' => 'admin.effectifs_workspace.chaine',
            'chainUnits' => $units,
            'chainMembers' => $members,
            'chainMemberOptions' => [['id' => 7, 'label' => 'Mercer'], ['id' => 8, 'label' => 'Hale']],
            'chainMissingCommanders' => 0,
            'canManageAssignments' => true,
            'csrfToken' => 'tok',
            'chainAdequacy' => self::sample(),
        ];
        $html = $this->render('admin/effectifs_workspace/shell.php', $vars);
        self::assertStringContainsString('id="eff-adequacy"', $html);
        self::assertStringContainsString('data-adq-status="sous_capacite"', $html, 'chef de groupe caporal : sous-capacité');
        self::assertStringContainsString('data-adq-code="leader_grade_below"', $html);
        self::assertStringContainsString('Squad leader : poste tenu par Hale (OR-4)', $html);
        self::assertStringContainsString('data-adq-code="without_place"', $html);
        self::assertStringContainsString('Adjoint à nommer', $html, 'compagnie sans first sergeant ni XO');
        self::assertStringContainsString('Proposer à l’avancement', $html);
        self::assertStringContainsString('chaine/adequation', $html);
        self::assertStringContainsString('<th scope="col">Adéquation</th>', $html);
        self::assertStringContainsString('Sans Place', $html, 'personne sans affectation listée');
        self::assertStringContainsString('Groupe &lt;1&gt;', $html);
        self::assertStringNotContainsString('Groupe <1>', $html);
        // Confidentialité : pastille d’unité masquée conservée dans le panneau.
        self::assertStringContainsString('data-confidential="staff_hidden"', $html);

        $filtered = $this->render('admin/effectifs_workspace/shell.php', $vars + ['adequacyOnlyGaps' => true]);
        self::assertStringContainsString('Filtré : personnes avec un écart d’adéquation', $filtered);
        self::assertStringContainsString('id="eff-chain-people-panel" open', $filtered);
        // Mercer (capitaine, commandant de compagnie) sans écart propre : retiré du tableau filtré.
        self::assertSame(0, preg_match('~<tr>\s*<td>\s*<a href="[^"]*membres/7">Mercer</a>~', $filtered));
        self::assertSame(1, preg_match('~<tr>\s*<td>\s*<a href="[^"]*membres/8">Hale</a>~', $filtered));

        // Contrôle désactivé ou indisponible : la page reste celle d’avant.
        $plain = $this->render('admin/effectifs_workspace/shell.php', array_replace($vars, ['chainAdequacy' => null]));
        self::assertStringNotContainsString('id="eff-adequacy"', $plain);
        self::assertStringContainsString('Qui relève de qui', $plain);
    }

    public function testMemberFileShowsPlaceChiefDeputyAndFit(): void
    {
        $place = OrgAdequacyView::forPerson(self::sample(), 8);
        self::assertNotNull($place);
        self::assertSame('chef', $place['place']);
        self::assertSame(7, $place['chief_id']);
        self::assertSame('Mercer', $place['chief_label']);
        $redacted = OrgAdequacyView::forPerson(self::sample(), 8, static fn (int $id, string $known): string => $id === 7 ? 'Personnel anonymisé' : $known);
        self::assertSame('Personnel anonymisé', $redacted['chief_label']);

        $html = $this->render('admin/effectifs_workspace/partials/adequacy.php', []);
        self::assertSame('', trim($html));
        require_once dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/partials/adequacy.php';
        $list = adq_issue_list($place['issues'], $place['people'], static fn (int $id): string => '/m/' . $id, false);
        self::assertStringContainsString('Sous-capacité de commandement', $list);
        self::assertStringNotContainsString('adq-act', $list, 'sans actions pour le titulaire');
        self::assertStringContainsString('<span>Squad leader</span> · <span>Groupe &lt;1&gt;</span>', adq_roles_html($place['roles']));

        $member = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/member.php');
        self::assertStringContainsString('Place dans l’organisation', $member);
        self::assertStringContainsString('memberAdequacy', $member);
        $file = (string) file_get_contents(dirname(__DIR__, 2) . '/views/personnel/file.php');
        self::assertStringContainsString('Votre place dans l’organisation', $file);
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString('ConfidentialDisplay::personLabel', $ctrl);
        self::assertStringContainsString("'orgPlace' => \$orgPlace", $ctrl);
    }

    public function testSettingsPageListsReferentialAndCommunityOverrides(): void
    {
        $settings = OrgAdequacySettings::sanitize(['echelons' => ['groupe' => ['leader_grade_min' => 'OR-4', 'enabled' => '1']]]);
        self::assertSame(['leader_grade_min' => 'OR-4'], $settings['echelons']['groupe'], 'un échelon actif n’est pas stocké');
        $html = $this->render('admin/effectifs_workspace/shell.php', [
            'effectifsNav' => 'chaine',
            'effectifsContent' => 'admin.effectifs_workspace.adequation',
            'adequacySettings' => $settings,
            'adequacyProfile' => 'fr',
            'adequacyStandards' => OrgAdequacySettings::effectiveStandards($settings, 'fr'),
            'adequacyGradeLabels' => [4 => ['Caporal-chef'], 5 => ['Sergent'], 6 => ['Sergent-chef']],
            'adequacyQualifications' => [['id' => 3, 'label' => 'Brevet de chef de groupe']],
            'adequacyJobRoles' => [],
            'canEditAdequacy' => true,
            'csrfToken' => 'tok',
        ]);
        self::assertStringContainsString('name="org_adequacy[echelons][groupe][leader_grade_min]"', $html);
        self::assertStringContainsString('<option value="OR-4" selected>', $html);
        self::assertStringContainsString('Chef de groupe', $html);
        self::assertStringContainsString('Code de la défense, art. L4131-1', $html);
        self::assertStringContainsString('name="org_adequacy[rules][grade_above]"', $html);
        self::assertStringContainsString('Brevet de chef de groupe', $html);
        self::assertStringContainsString('name="_csrf_token" value="tok"', $html);
        self::assertStringContainsString('Rétablir les valeurs par défaut', $html);
    }

    public function testOrbatBadgesAndWiring(): void
    {
        $badges = OrgAdequacyView::orbatBadges(self::sample());
        self::assertSame('Sous-capacité', $badges[2]['label']);
        self::assertSame('warn', $badges[2]['tone']);
        self::assertNotSame([], $badges[2]['texts']);
        self::assertSame([], OrgAdequacyView::orbatBadges(OrgAdequacyEngine::evaluate(['settings' => ['enabled' => false]])));

        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/api/orbat/adequation', [OrbatApiController::class, 'adequacy']", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/chaine/adequation', [EffectifsWorkspaceController::class, 'adequacySettings']", $routes);
        $js = (string) file_get_contents($root . '/public/assets/js/orbat-app.js');
        self::assertStringContainsString("api('/api/orbat/adequation')", $js);
        self::assertStringContainsString('orb-pill--adq', $js);
        self::assertStringContainsString('S.previewAs', $js, 'jamais en aperçu « vu par un membre »');
        $api = (string) file_get_contents($root . '/app/Controllers/Api/OrbatApiController.php');
        self::assertStringContainsString("organization.orbat.manage')) {\n            return Response::json(['success' => false, 'message' => 'Droits insuffisants'], 403);", str_replace("\r\n", "\n", $api));
        $rh = (string) file_get_contents($root . '/app/Services/Effectifs/RhActionInboxService.php');
        self::assertStringContainsString("'adequacy' => [", $rh, 'les écarts d’organisation sont une carte de « À traiter »');
        self::assertStringContainsString('#eff-adequacy', $rh);
        $repo = (string) file_get_contents($root . '/app/Repositories/TenantAdminSettingsRepository.php');
        self::assertStringContainsString("'org_adequacy' => \\App\\Services\\Organization\\OrgAdequacySettings::sanitize(", $repo);
    }
}
