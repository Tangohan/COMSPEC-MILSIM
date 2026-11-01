<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\TeamComposition;
use PHPUnit\Framework\TestCase;

/** Composition d’une unité : postes prévus (effectif prévu), pourvus et vacants. */
final class TeamCompositionTest extends TestCase
{
    public function testSeatsAreFilledByHoldersThenByMembersHoldingTheLinkedJob(): void
    {
        $billets = [
            ['id' => 1, 'title' => 'Chef d’équipe', 'authorized' => 1, 'job_role_id' => null, 'holders' => [['user_id' => 5, 'label' => 'Sgt. Mercer', 'occupancy_type' => 'primary']]],
            ['id' => 2, 'title' => 'Fusilier', 'authorized' => 3, 'job_role_id' => 40, 'holders' => []],
            ['id' => 3, 'title' => 'Tireur de précision', 'authorized' => 1, 'job_role_id' => 41, 'holders' => []],
            ['id' => 4, 'title' => 'Poste gelé', 'authorized' => 2, 'status' => 'frozen', 'holders' => []],
        ];
        $members = [
            ['user_id' => 5, 'label' => 'Sgt. Mercer', 'job_role_ids' => [40]],
            ['user_id' => 6, 'label' => 'PFC Anderson', 'job_role_ids' => [40]],
            ['user_id' => 7, 'label' => 'Pvt. Kowal', 'job_role_ids' => [40, 41]],
            ['user_id' => 8, 'label' => 'Spc. Nguyen', 'job_role_ids' => [77], 'job_label' => 'Secouriste'],
        ];

        $out = TeamComposition::compose($billets, $members, 6);

        self::assertSame(5, $out['authorized'], 'le poste gelé n’est pas compté');
        self::assertSame(3, $out['filled']);
        self::assertSame(2, $out['vacant']);
        $labels = array_map(static fn (array $s): string => $s['label'] . '|' . $s['title'], $out['seats']);
        self::assertSame([
            'Sgt. Mercer|Chef d’équipe',
            'PFC Anderson|Fusilier',
            'Pvt. Kowal|Fusilier',
            'Poste vacant|Fusilier',
            'Poste vacant|Tireur de précision',
        ], $labels, 'une même personne n’occupe qu’une place');
        self::assertTrue($out['seats'][1]['is_me']);
        self::assertSame([['user_id' => 8, 'label' => 'Spc. Nguyen', 'job_label' => 'Secouriste', 'is_me' => false]], $out['others']);
    }

    public function testAlternateHolderDoesNotFillTheSeat(): void
    {
        $billets = [['id' => 1, 'title' => 'Adjoint', 'authorized' => 1, 'holders' => [['user_id' => 3, 'label' => 'X', 'occupancy_type' => 'alternate']]]];

        $out = TeamComposition::compose($billets, [], 3);

        self::assertSame(0, $out['filled']);
        self::assertTrue($out['seats'][0]['vacant']);
    }

    public function testUnitWithoutPostsOnlyListsItsMembers(): void
    {
        $out = TeamComposition::compose([], [['user_id' => 2, 'label' => 'A', 'job_role_ids' => []]], 2);

        self::assertSame(0, $out['authorized']);
        self::assertSame([], $out['seats']);
        self::assertTrue($out['others'][0]['is_me']);
    }
}
