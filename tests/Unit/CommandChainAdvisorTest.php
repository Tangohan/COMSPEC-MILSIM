<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\CommandChainAdvisor;
use App\Services\Effectifs\PersonnelCommandChainService;
use PHPUnit\Framework\TestCase;

/**
 * Page « Chaîne de commandement » : alertes, propositions tirées des seules données, enregistrement tout ou rien,
 * et câblage (droits, jeton, zone vivante, repli sans JavaScript).
 */
final class CommandChainAdvisorTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function unit(int $id, int $parent, int $chief, array $members = [], string $name = ''): array
    {
        return [
            'id' => $id,
            'name' => $name !== '' ? $name : 'Unité ' . $id,
            'parent_id' => $parent,
            'commander_user_id' => $chief,
            'commander_label' => $chief > 0 ? 'Chef ' . $chief : '',
            'member_count' => count($members),
            'member_ids' => $members,
        ];
    }

    /** @return array<string, mixed> */
    private static function member(int $id, int $unit, int $reportsTo = 0, array $commands = []): array
    {
        return ['user_id' => $id, 'label' => 'Membre ' . $id, 'unit_id' => $unit, 'unit_name' => $unit > 0 ? 'Unité ' . $unit : '', 'reports_to_id' => $reportsTo, 'commands' => $commands];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function codes(array $rows): array
    {
        return array_map(static fn (array $r): string => (string) $r['code'], $rows);
    }

    public function testCleanOrganisationRaisesNothingAndProposesNothing(): void
    {
        $advice = CommandChainAdvisor::analyze(
            [self::unit(1, 0, 7, [7, 8])],
            [self::member(7, 1, 0, ['Unité 1']), self::member(8, 1, 7)],
            null,
            [7, 8]
        );

        self::assertSame([], $advice['alerts']);
        self::assertSame([], $advice['proposals'], 'aucune donnée : aucune proposition inventée');
    }

    public function testVacantUnitAlertSaysWhatWhereAndWhatToDo(): void
    {
        $advice = CommandChainAdvisor::analyze(
            [self::unit(1, 0, 7, [7], 'Compagnie'), self::unit(2, 1, 0, [8, 9], 'Groupe A'), self::unit(3, 0, 0, [10, 11], 'Isolée')],
            [self::member(7, 1, 0, ['Compagnie']), self::member(8, 2, 7), self::member(9, 2, 7), self::member(10, 3), self::member(11, 3)],
            null,
            [7, 8, 9, 10, 11]
        );

        self::assertSame(['chief_vacant', 'chief_vacant'], self::codes($advice['alerts']));
        // D’abord l’unité dont les membres n’ont plus aucun chef.
        self::assertSame(3, $advice['alerts'][0]['unit_id']);
        self::assertStringContainsString('2 membres n’ont aucun chef', $advice['alerts'][0]['text']);
        self::assertSame('Chef à désigner : Groupe A', $advice['alerts'][1]['title']);
        self::assertSame('Rattachée à Compagnie', $advice['alerts'][1]['where']);
        self::assertStringContainsString('remonte directement à Chef 7', $advice['alerts'][1]['text']);
    }

    public function testInactiveCommanderAndMembersWithoutUnitAreFlagged(): void
    {
        $advice = CommandChainAdvisor::analyze(
            [self::unit(1, 0, 99, [8])],
            [self::member(8, 1, 99), self::member(99, 1), self::member(20, 0), self::member(21, 0)],
            null,
            [8, 20]
        );

        self::assertSame(['commander_inactive', 'members_without_unit'], self::codes($advice['alerts']));
        self::assertSame('danger', $advice['alerts'][0]['tone']);
        // Le membre 21 n’est plus actif : ni compté, ni proposé à l’affectation.
        self::assertSame([['id' => 20, 'label' => 'Membre 20']], $advice['alerts'][1]['people']);
        self::assertSame('Un membre sans unité', $advice['alerts'][1]['title']);

        $unknown = CommandChainAdvisor::analyze([self::unit(1, 0, 99, [8])], [self::member(8, 1, 99)], null, []);
        self::assertSame([], $unknown['alerts'], 'liste des actifs indisponible : pas de fausse alerte');
    }

    /** @return array<string, mixed> */
    private static function adequacy(array $issues, array $grades): array
    {
        $people = [];
        foreach ($grades as $id => $grade) {
            $people[$id] = ['label' => 'Membre ' . $id, 'grade_label' => $grade];
        }

        return ['enabled' => true, 'issues' => $issues, 'people' => $people];
    }

    public function testCandidateFromEngineBecomesProposalOncePerPerson(): void
    {
        $units = [self::unit(1, 0, 0, [8]), self::unit(2, 0, 0, [], 'Autre'), self::unit(3, 0, 0, [], 'Tierce')];
        $members = [self::member(8, 1), self::member(9, 0)];
        $adequacy = self::adequacy([
            ['code' => 'leader_vacant', 'unit_id' => 1, 'candidates' => [8, 9]],
            ['code' => 'leader_vacant', 'unit_id' => 2, 'candidates' => [8, 9]],
            ['code' => 'leader_vacant', 'unit_id' => 3, 'candidates' => [8]],
        ], [8 => 'Sergent', 9 => 'Sergent-chef']);

        $advice = CommandChainAdvisor::analyze($units, $members, $adequacy, [8, 9]);

        self::assertSame(['designate-1-8', 'designate-2-9'], array_column($advice['proposals'], 'id'));
        self::assertStringContainsString('déjà membre de l’unité', $advice['proposals'][0]['reason']);
        self::assertStringContainsString('Sans unité', $advice['proposals'][1]['reason']);
        self::assertSame([8, 9], $advice['suggested'][1], 'le choix du chef met les candidats en avant');
    }

    public function testInactiveCandidateIsNeverProposedAndNoCandidateMeansNoProposal(): void
    {
        $units = [self::unit(1, 0, 0, [8, 9])];
        $members = [self::member(8, 1), self::member(9, 1)];
        $adequacy = self::adequacy([['code' => 'leader_vacant', 'unit_id' => 1, 'candidates' => [9]]], [9 => 'Sergent']);

        $advice = CommandChainAdvisor::analyze($units, $members, $adequacy, [8]);
        self::assertSame([], $advice['proposals']);
        self::assertSame([], $advice['suggested']);

        // Adéquation désactivée : ses candidats ne comptent pas.
        $off = CommandChainAdvisor::analyze($units, $members, ['enabled' => false] + $adequacy, [8, 9]);
        self::assertSame([], $off['proposals']);
    }

    public function testSoleMemberAndHomelessCommanderProposals(): void
    {
        $advice = CommandChainAdvisor::analyze(
            [self::unit(1, 0, 0, [8]), self::unit(2, 0, 30, []), self::unit(3, 0, 0, [40])],
            [self::member(8, 1), self::member(30, 0, 0, ['Unité 2']), self::member(40, 3, 0, ['Unité 4']), self::member(41, 4)],
            null,
            [8, 30, 40, 41]
        );

        $ids = array_column($advice['proposals'], 'id');
        self::assertContains('designate-1-8', $ids, 'seul membre de son unité, et il ne commande rien');
        self::assertContains('assign-30-2', $ids, 'chef sans affectation : son unité est celle qu’il commande');
        $assign = $advice['proposals'][array_search('assign-30-2', $ids, true)];
        self::assertSame(CommandChainAdvisor::KIND_ASSIGN, $assign['kind']);
        self::assertStringContainsString('Unité 2', $assign['confirm']);
    }

    public function testReplacementNamesBothPeopleAndCarriesCurrentChief(): void
    {
        $advice = CommandChainAdvisor::analyze(
            [self::unit(1, 0, 7, [7, 8])],
            [self::member(7, 1, 0, ['Unité 1']), self::member(8, 1, 7)],
            self::adequacy([['code' => 'leader_grade_below', 'unit_id' => 1, 'candidates' => [8]]], [7 => 'Caporal', 8 => 'Sergent']),
            [7, 8]
        );

        self::assertCount(1, $advice['proposals']);
        $p = $advice['proposals'][0];
        self::assertSame(CommandChainAdvisor::KIND_REPLACE, $p['kind']);
        self::assertSame(7, $p['current_id']);
        self::assertStringContainsString('Caporal', $p['reason']);
        self::assertStringContainsString('Membre 7 garde son affectation', $p['confirm']);
    }

    private function serviceWithUnits(array $rows, array &$writes): PersonnelCommandChainService
    {
        $repo = $this->createMock(UnitRepository::class);
        $repo->method('allForTenant')->willReturn($rows);
        $repo->method('update')->willReturnCallback(static function (int $id, int $tenant, array $data) use (&$writes): bool {
            $writes[] = [$id, $tenant, $data['commander_user_id']];

            return true;
        });

        return new PersonnelCommandChainService($repo, $this->createMock(UserRepository::class), $this->createMock(PersonnelAssignmentRepository::class));
    }

    public function testRefusedChiefWritesNothingEvenIfOtherChoicesWereValid(): void
    {
        $writes = [];
        $service = $this->serviceWithUnits([['id' => 1, 'name' => 'A', 'commander_user_id' => 0], ['id' => 2, 'name' => 'B', 'commander_user_id' => 0]], $writes);

        $result = $service->saveCommanders(14, [1 => 7, 2 => 999], [7]);

        self::assertFalse($result['ok']);
        self::assertSame(0, $result['updated']);
        self::assertSame([], $writes, 'jamais de demi-enregistrement annoncé comme un échec');
    }

    public function testStaleChiefIsAConflictAndWritesNothing(): void
    {
        $writes = [];
        $service = $this->serviceWithUnits([['id' => 1, 'name' => 'A', 'commander_user_id' => 5]], $writes);

        $result = $service->saveCommanders(14, [1 => 7], [5, 7], [1 => 0]);

        self::assertFalse($result['ok']);
        self::assertTrue($result['conflict']);
        self::assertStringContainsString('Le chef de A a changé', $result['message']);
        self::assertSame([], $writes);
    }

    public function testUnknownUnitIsIgnoredAndValidSaveWritesForTheSessionTenant(): void
    {
        $writes = [];
        $service = $this->serviceWithUnits([['id' => 1, 'name' => 'A', 'commander_user_id' => 5]], $writes);

        $result = $service->saveCommanders(14, [1 => 7, 777 => 7, 'x' => ['y']], [5, 7], [1 => 5]);

        self::assertTrue($result['ok']);
        self::assertSame(1, $result['updated']);
        self::assertSame([[1, 14, 7]], $writes, 'unité d’une autre communauté ignorée');
    }

    public function testMemberWithoutUnitIsCountedEvenWhenEveryChiefHasAUnit(): void
    {
        $units = $this->createMock(UnitRepository::class);
        $units->method('allForTenant')->willReturn([['id' => 1, 'name' => 'A', 'parent_id' => 0, 'commander_user_id' => 7]]);
        $units->method('hierarchyMetaByUnitId')->willReturn([]);
        $users = $this->createMock(UserRepository::class);
        $users->method('listForTenant')->willReturn([['id' => 7, 'display_name' => 'Mercer'], ['id' => 20, 'display_name' => 'Sans Place']]);
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->method('listActiveMembersByUnitForTenant')->willReturn([1 => [['user_id' => 7, 'display_name' => 'Mercer']]]);
        $assignments->method('primaryUnitIdByUserForTenant')->willReturn([7 => 1]);

        $data = (new PersonnelCommandChainService($units, $users, $assignments))->workspace(14);

        self::assertSame(1, $data['without_unit']);
        self::assertContains(['id' => 20, 'label' => 'Sans Place'], $data['memberOptions'], 'nommable comme chef');
    }

    public function testControllerGuardsEveryChainWrite(): void
    {
        $root = dirname(__DIR__, 2);
        $ctrl = str_replace("\r\n", "\n", (string) file_get_contents($root . '/app/Controllers/Admin/EffectifsWorkspaceController.php'));
        $save = substr($ctrl, (int) strpos($ctrl, 'function saveCommandChain'), 2200);
        self::assertStringContainsString('denyUnlessAccess', $save);
        self::assertStringContainsString("Csrf::validate((string) \$request->input('_csrf_token'))", $save);
        self::assertStringContainsString('EffectifsLmsAccess::canManageAssignments($gate)', $save);
        self::assertStringContainsString("(int) Session::get('tenant_id')", $save);
        self::assertLessThan(strpos($save, 'saveCommanders'), strpos($save, 'canManageAssignments'), 'droit vérifié avant l’écriture');
        self::assertStringContainsString("'admin.effectifs_workspace.chaine', \$vars + ['chainFragment' => true]", $ctrl);
        self::assertStringContainsString('$pdo->rollBack()', $save);

        $quick = substr($ctrl, (int) strpos($ctrl, 'function quickAssignment'), 2800);
        self::assertStringContainsString('canManageAssignments', $quick);
        self::assertStringContainsString('Csrf::validate', $quick);
        self::assertStringContainsString('findById($id, $tenantId)', $quick);
        self::assertStringContainsString('unitRepository->findById($unitId, $tenantId)', $quick);
    }

    public function testViewKeepsFallbackFormsAndScriptNeverClaimsSuccessOnFailure(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/chaine.php');
        $js = (string) file_get_contents($root . '/public/assets/js/eff-chain.js');

        // Repli : de vrais formulaires vers les routes habituelles, avec jeton.
        self::assertStringContainsString('data-chain-proposal', $view);
        self::assertStringContainsString('data-chain-bulk', $view);
        self::assertStringContainsString('name="expected_commanders[', $view);
        self::assertStringContainsString('<input type="hidden" name="_csrf_token"', $view);

        self::assertStringNotContainsString('confirm(', str_replace(['showDialog', 'onConfirm(', 'confirmChief(', 'confirmProposal(', 'confirmBulk('], '', $js));
        self::assertStringNotContainsString('location.reload', $js);
        // Réussite annoncée uniquement après une réponse positive du serveur.
        self::assertStringContainsString('json.ok !== true', $js);
        self::assertStringContainsString("'application/json'", $js);
        self::assertStringContainsString("toast(r.saved.updated === 0 ? 'info' : 'success', r.saved.message)", $js);
        self::assertStringContainsString('setError(err.message, err.reload)', $js);
    }
}
