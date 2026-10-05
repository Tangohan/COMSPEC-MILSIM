<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakScreenTimeRepository;
use App\Services\Atak\SquadSyncService;
use PHPUnit\Framework\TestCase;

final class AtakSquadsScreenTimeTest extends TestCase
{
    public function testSquadPayloadIsCleaned(): void
    {
        $snap = SquadSyncService::normalizePayload([
            'mission_key' => 'Op_Rubicon@Altis#202610051230',
            'squad' => ['key' => 'Alpha 1-1#WEST', 'name' => 'Alpha 1-1', 'side' => 'west', 'type' => 'inf', 'members' => 9],
            'teams' => [
                [
                    'key' => 'FT1', 'name' => 'Alpha', 'color' => '#e5483a', 'icon' => 'inf', 'description' => "Appui\nnord",
                    'members' => [
                        ['callsign' => 'Bravo 6', 'uid' => '76561198000000001', 'role' => 'cde', 'role_label' => 'Chef d\'équipe', 'leader' => true],
                        ['name' => 'Dupont', 'role' => 'FM'],
                        ['role' => 'FUS'],
                    ],
                ],
                ['key' => 'FT2', 'name' => 'Bravo', 'color' => 'red'],
                ['key' => '', 'name' => 'Sans clé'],
            ],
            'unassigned' => [['callsign' => 'Charlie 2']],
        ]);

        self::assertNotNull($snap);
        self::assertSame('WEST', $snap['squad']['side']);
        self::assertSame('INF', $snap['squad']['type']);
        self::assertCount(2, $snap['teams']);
        self::assertSame('#E5483A', $snap['teams'][0]['color']);
        self::assertSame('Appui nord', $snap['teams'][0]['description']);
        self::assertSame('#64748B', $snap['teams'][1]['color'], 'couleur invalide remplacée');
        self::assertCount(2, $snap['teams'][0]['members'], 'membre sans nom ni indicatif ignoré');
        self::assertSame('CDE', $snap['teams'][0]['members'][0]['role']);
        self::assertTrue($snap['teams'][0]['members'][0]['leader']);
        self::assertSame('Dupont', $snap['teams'][0]['members'][1]['callsign']);
        self::assertCount(1, $snap['unassigned']);
    }

    public function testSquadPayloadNeedsMissionAndSquad(): void
    {
        self::assertNull(SquadSyncService::normalizePayload(['squad' => ['key' => 'A', 'name' => 'A']]));
        self::assertNull(SquadSyncService::normalizePayload(['mission_key' => 'm', 'squad' => ['key' => 'A']]));
    }

    public function testScreenTimeItemsAreValidatedAndMerged(): void
    {
        $items = AtakScreenTimeRepository::normalizeItems([
            ['kind' => 'screen', 'key' => 'total', 'label' => 'Écran allumé', 'seconds' => 300],
            ['kind' => 'app', 'key' => 'map', 'label' => 'Carte', 'seconds' => 120.6],
            ['kind' => 'app', 'key' => 'MAP', 'label' => 'Carte', 'seconds' => 30],
            ['kind' => 'role', 'key' => 'SLOT:Fusilier', 'label' => 'Fusilier', 'seconds' => 99999],
            ['kind' => 'other', 'key' => 'x', 'seconds' => 10],
            ['kind' => 'app', 'key' => '', 'seconds' => 10],
            ['kind' => 'app', 'key' => 'CHAT', 'seconds' => 0],
        ]);

        self::assertCount(3, $items);
        self::assertSame(['kind' => 'screen', 'key' => 'TOTAL', 'label' => 'Écran allumé', 'seconds' => 300], $items[0]);
        self::assertSame(151, $items[1]['seconds']);
        self::assertSame('SLOT:Fusilier', $items[2]['key'], 'les codes de rôle gardent leur casse');
        self::assertSame(7200, $items[2]['seconds'], 'plafond de 2 h par envoi');
        self::assertSame([], AtakScreenTimeRepository::normalizeItems('nope'));
    }
}
