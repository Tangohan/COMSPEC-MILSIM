<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\JnetSpaceTree;
use PHPUnit\Framework\TestCase;

/**
 * Règles d'espaces et de diffusion JNET.
 *
 * Arbre : Organisation(0)
 *   ├─ JSOC(1) ─ 1st SFOD-D(2) ─┬─ B Squadron(3) ─┬─ Troop 1(5) ─ Équipe Alpha(7)
 *   │                           │                 └─ Troop 3(6)
 *   │                           └─ G Squadron(4)
 *   └─ AFSOC(10) ─ 160th SOAR(11)
 */
final class JnetSpaceTreeTest extends TestCase
{
    private function tree(): JnetSpaceTree
    {
        return JnetSpaceTree::fromRows([
            [1, 0, 'JSOC'],
            [2, 1, '1st SFOD-D'],
            [3, 2, 'B Squadron'],
            [4, 2, 'G Squadron'],
            [5, 3, 'Troop 1'],
            [6, 3, 'Troop 3'],
            [7, 5, 'Équipe Alpha'],
            [10, 0, 'AFSOC'],
            [11, 10, '160th SOAR'],
        ], 'Organisation');
    }

    public function testChainAndDescendants(): void
    {
        $t = $this->tree();
        self::assertSame([0, 1, 2, 3, 5], $t->chain(5));
        self::assertSame([5, 6, 7], $t->descendants(3));
        self::assertTrue($t->isWithin(7, 3));
        self::assertFalse($t->isWithin(4, 3));
        self::assertSame(0, $t->parentOf(1));
    }

    public function testOrdersFlowDownToSubordinates(): void
    {
        $t = $this->tree();
        // FRAGO de B Squadron vers Troop 1 : vu par Troop 1, son équipe, et la chaîne au-dessus.
        self::assertTrue($t->canSeeExchange([7], 3, [5]));
        self::assertTrue($t->canSeeExchange([2], 3, [5]));
        // Ni la branche latérale, ni une autre composante.
        self::assertFalse($t->canSeeExchange([4], 3, [5]));
        self::assertFalse($t->canSeeExchange([11], 3, [5]));
        self::assertSame(JnetSpaceTree::DIR_DOWN, $t->directionFor(5, 3, [5]));
        self::assertSame(JnetSpaceTree::DIR_DOWN, $t->directionFor(7, 3, [5]));
    }

    public function testReportsFlowUp(): void
    {
        $t = $this->tree();
        self::assertSame(JnetSpaceTree::DIR_UP, $t->directionFor(5, 5, [3]));
        // Un compte rendu d'une équipe vers B Squadron est aussi « remonté » vu de Troop 1.
        self::assertSame(JnetSpaceTree::DIR_UP, $t->directionFor(5, 7, [3]));
        // Vu de B Squadron, ce qui arrive de son sous-arbre est interne.
        self::assertSame(JnetSpaceTree::DIR_INTERNAL, $t->directionFor(3, 5, [3]));
    }

    public function testInternalAndLateral(): void
    {
        $t = $this->tree();
        self::assertSame(JnetSpaceTree::DIR_INTERNAL, $t->directionFor(5, 5, [5]));
        self::assertSame(JnetSpaceTree::DIR_INTERNAL, $t->directionFor(5, 5, [7]));
        // Coordination B Squadron → 160th SOAR, vue de B Squadron : latéral.
        self::assertSame(JnetSpaceTree::DIR_LATERAL, $t->directionFor(3, 3, [11]));
        // Le 160th voit ce qui lui est adressé.
        self::assertTrue($t->canSeeExchange([11], 3, [11]));
    }

    public function testOrganisationWideMessages(): void
    {
        $t = $this->tree();
        self::assertTrue($t->canSeeExchange([4], 0, [0]));
        self::assertTrue($t->canSeeExchange([], 0, [0]));
        self::assertSame(JnetSpaceTree::DIR_DOWN, $t->directionFor(5, 0, [0]));
        // Émis par l'Organisation vers une unité : seul son périmètre le voit.
        self::assertTrue($t->canSeeExchange([7], 0, [3]));
        self::assertFalse($t->canSeeExchange([4], 0, [3]));
        self::assertTrue($t->canSeeExchange([4], 0, [3], true));
    }

    public function testPostingRights(): void
    {
        $t = $this->tree();
        self::assertTrue($t->canPostIn([5], 5));
        self::assertTrue($t->canPostIn([3], 5), 'La chaîne de commandement peut publier chez ses subordonnés.');
        self::assertFalse($t->canPostIn([7], 5), 'Une équipe ne publie pas au nom de sa section.');
        self::assertFalse($t->canPostIn([4], 5));
        self::assertFalse($t->canPostIn([5], 0));
        self::assertTrue($t->canPostIn([], 0, true));
        self::assertSame([5, 3, 7], $t->allowedTargetsFrom(5));
    }

    public function testViewerSpacesFollowTheChain(): void
    {
        $t = $this->tree();
        self::assertSame([0, 1, 2, 3, 5, 7], $t->viewerSpaces([7]));
        self::assertSame([0], $t->viewerSpaces([999]));
    }

    public function testHiddenUnitsAreAbsent(): void
    {
        $t = $this->tree();
        self::assertFalse($t->has(999));
        self::assertFalse($t->canSeeExchange([5], 999, [999]));
    }

    public function testOrbatRootIsUnwrappedAndColorsAreSanitised(): void
    {
        $t = JnetSpaceTree::fromOrbat([
            'unitId' => 0,
            'label' => 'Command',
            'children' => [
                ['unitId' => 3, 'label' => 'B Squadron', 'accentColor' => '#C9A06B', 'members' => [['user_id' => 12], ['user_id' => 13]], 'children' => [
                    ['unitId' => 5, 'label' => 'Troop 1', 'accentColor' => 'red;background:url(x)', 'members' => [['user_id' => 14], ['user_id' => 12]], 'children' => []],
                ]],
            ],
        ], 'Org');
        self::assertSame(1, $t->node(3)['depth']);
        self::assertSame('#c9a06b', $t->node(3)['accent']);
        self::assertSame('', $t->node(5)['accent']);
        $members = $t->memberIdsInSubtree(3);
        sort($members);
        self::assertSame([12, 13, 14], $members);
    }
}
