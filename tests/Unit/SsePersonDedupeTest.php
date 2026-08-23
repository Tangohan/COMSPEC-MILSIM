<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SsePersonDedupe;
use PHPUnit\Framework\TestCase;

final class SsePersonDedupeTest extends TestCase
{
    public function testSameNameAndAliasShareAnIdentityKey(): void
    {
        $a = SsePersonDedupe::identityKey('NomZul', 'PrenomUlu', 'ZulluUlu le fou');
        $b = SsePersonDedupe::identityKey('nomzul', 'prenomulu', 'ZulluUlu   le fou');

        self::assertNotSame('', $a);
        self::assertSame($a, $b);
    }

    public function testNewIrisIsAFreshModality(): void
    {
        $fresh = SsePersonDedupe::newModalities(
            [['kind' => 'empreintes']],
            [['kind' => 'iris'], ['kind' => 'empreintes']]
        );

        self::assertSame(['iris'], $fresh);
    }

    public function testGenericBiometricsIsNotNewWhenFingerprintsExist(): void
    {
        $fresh = SsePersonDedupe::newModalities(
            ['empreintes'],
            [],
            true
        );

        self::assertSame([], $fresh);
    }

    public function testCollapseKeepsTheOldestCard(): void
    {
        $collapsed = SsePersonDedupe::collapseList([
            ['id' => 12, 'last_name' => 'NomZul', 'first_name' => 'PrenomUlu', 'alias' => 'Zullu'],
            ['id' => 7, 'last_name' => 'NomZul', 'first_name' => 'PrenomUlu', 'alias' => 'Zullu'],
            ['id' => 9, 'last_name' => 'Jawadi', 'first_name' => 'Khalil', 'alias' => ''],
        ]);

        self::assertCount(2, $collapsed);
        self::assertSame(7, $collapsed[0]['id']);
        self::assertSame(9, $collapsed[1]['id']);
    }

    public function testCollapseMergesBiometricKindsFromDuplicates(): void
    {
        $collapsed = SsePersonDedupe::collapseList([
            [
                'id' => 12,
                'last_name' => 'NomZul',
                'first_name' => 'PrenomUlu',
                'alias' => 'Zullu',
                'biometric_kinds' => [['kind' => 'iris', 'kind_label' => 'Iris']],
            ],
            [
                'id' => 7,
                'last_name' => 'NomZul',
                'first_name' => 'PrenomUlu',
                'alias' => 'Zullu',
                'biometric_kinds' => [['kind' => 'empreintes', 'kind_label' => 'Empreintes']],
            ],
        ]);

        self::assertCount(1, $collapsed);
        self::assertSame(7, $collapsed[0]['id']);
        $kinds = array_column($collapsed[0]['biometric_kinds'], 'kind');
        sort($kinds);
        self::assertSame(['empreintes', 'iris'], $kinds);
    }

    public function testGenericBiometricsIsNewWhenNothingExists(): void
    {
        $fresh = SsePersonDedupe::newModalities([], [], true);

        self::assertSame(['empreintes'], $fresh);
    }
}
