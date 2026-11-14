<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\PersonnelCommandChainService;
use App\Services\Workflow\ApproverResolver;
use App\Services\Workflow\WorkflowCircuits;
use PHPUnit\Framework\TestCase;

/**
 * Qui donne l'avis de chaque étape : chef d'unité, échelon supérieur, personne désignée (chaîne de commandement).
 */
final class ApproverResolverTest extends TestCase
{
    private function resolver(): ApproverResolver
    {
        $chain = $this->createMock(PersonnelCommandChainService::class);
        $chain->method('workspace')->willReturn(['members' => [
            ['user_id' => 41, 'label' => 'Sgt Durand', 'unit_id' => 3, 'chain_ids' => [12, 20, 30]],
            ['user_id' => 12, 'label' => 'Cne Martin', 'unit_id' => 3, 'chain_ids' => [20, 30]],
            ['user_id' => 20, 'label' => 'Cdt Leroy', 'unit_id' => 1, 'chain_ids' => [30]],
            ['user_id' => 30, 'label' => 'Col Petit', 'unit_id' => 1, 'chain_ids' => []],
            ['user_id' => 55, 'label' => 'Adj S1', 'unit_id' => 1, 'chain_ids' => [30]],
        ]]);

        return new ApproverResolver($chain);
    }

    public function testCommanderThenUpperEchelonFollowTheChain(): void
    {
        $r = $this->resolver();

        self::assertSame(12, $r->commanderFor(7, 41)['user_id'] ?? null);
        self::assertSame('Sgt Durand', $r->commanderFor(7, 41)['member_label'] ?? null);
        self::assertSame(20, $r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_UPPER], 41, 41, [12])['user_id'] ?? null);
        self::assertSame(30, $r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_UPPER], 41, 41, [12, 20])['user_id'] ?? null);
        self::assertNull($r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_UPPER], 41, 41, [12, 20, 30]));
        // Sans étape précédente dans la chaîne, l'échelon supérieur est le deuxième chef.
        self::assertSame(20, $r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_UPPER], 41, 41, [55])['user_id'] ?? null);
    }

    public function testRequesterAndMemberAreNeverAsked(): void
    {
        $r = $this->resolver();

        // Le chef qui demande pour son subordonné fait remonter l'avis.
        self::assertSame(20, $r->commanderFor(7, 41, 12)['user_id'] ?? null);
        self::assertNull($r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_USER, 'user_id' => 41], 41, 41));
        self::assertNull($r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_USER, 'user_id' => 999], 41, 41), 'hors communauté');
        self::assertSame('Adj S1', $r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_USER, 'user_id' => 55], 41, 41)['label'] ?? null);
        self::assertNull($r->resolveStep(7, ['kind' => WorkflowCircuits::KIND_USER, 'user_id' => 55], 41, 41, [55]), 'déjà sollicité');
    }
}
