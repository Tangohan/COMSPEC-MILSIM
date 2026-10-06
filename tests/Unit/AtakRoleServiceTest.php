<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\AtakRoleService;
use App\Services\Atak\SquadSyncService;
use PHPUnit\Framework\TestCase;

final class AtakRoleServiceTest extends TestCase
{
    public function testRolesQueryIsRecognisedInMapId(): void
    {
        self::assertNull(AtakRoleService::parseRolesQuery('3'));
        self::assertNull(AtakRoleService::parseRolesQuery(null));
        self::assertSame(['steam' => null], AtakRoleService::parseRolesQuery('roles'));
        self::assertSame(['steam' => '76561198000000001'], AtakRoleService::parseRolesQuery('roles:76561198000000001'));
    }

    public function testIconAndShortLabelAreGuessedFromAthenaFunction(): void
    {
        self::assertSame('MED', AtakRoleService::guessIcon('Infirmier de combat'));
        self::assertSame('DRN', AtakRoleService::guessIcon('Télépilote drone'));
        self::assertSame('PIL', AtakRoleService::guessIcon('Pilote hélicoptère'));
        self::assertSame('JTAC', AtakRoleService::guessIcon('JTAC'));
        self::assertSame('FM', AtakRoleService::guessIcon('Tireur FM'), '« FM » mot entier prime sur « tireur »');
        self::assertSame('TE', AtakRoleService::guessIcon('Tireur d\'élite'));
        self::assertSame('FUS', AtakRoleService::guessIcon('Recrue'));
        self::assertSame('CS', AtakRoleService::shortLabel('Chef de section'), 'mots outils ignorés');
        self::assertSame('MOR', AtakRoleService::shortLabel('Mortier'));
        self::assertSame('JTAC', AtakRoleService::shortLabel('JTAC'));
    }

    public function testCustomRolesFromSquadSyncAreValidated(): void
    {
        $roles = AtakRoleService::normalizeCustomRoles(['custom_roles' => [
            ['key' => 'C_MORTIER', 'label' => "Mortier\t60", 'short' => 'mor', 'icon' => 'JTAC', 'by' => '76561198000000001'],
            ['key' => 'FUS', 'label' => 'Intégré'],
            ['key' => 'C_X', 'label' => ''],
            ['key' => 'C_GUIDE', 'label' => 'Guide | pisteur', 'icon' => 'NOPE'],
        ]]);
        self::assertCount(2, $roles);
        self::assertSame('Mortier 60', $roles[0]['label']);
        self::assertSame('MOR', $roles[0]['short']);
        self::assertSame('JTAC', $roles[0]['icon']);
        self::assertSame('Guide pisteur', $roles[1]['label'], 'séparateurs DLL retirés');
        self::assertSame('FUS', $roles[1]['icon'], 'icône inconnue remplacée');
    }

    public function testPrefsComeFromRolePrefThenRoleAndNeverTeamLeader(): void
    {
        $snap = SquadSyncService::normalizePayload([
            'mission_key' => 'm', 'squad' => ['key' => 'A#WEST', 'name' => 'A'],
            'teams' => [['key' => 'FT1', 'name' => 'Alpha', 'members' => [
                ['callsign' => 'Chef', 'uid' => '76561198000000001', 'role' => 'CDE', 'role_pref' => 'jtac'],
                ['callsign' => 'Bis', 'uid' => '76561198000000002', 'role' => 'CDE'],
                ['callsign' => 'Ter', 'uid' => '76561198000000003', 'role' => 'MED', 'role_label' => 'Auxiliaire sanitaire'],
                ['callsign' => 'IA', 'role' => 'FUS'],
            ]]],
            'unassigned' => [['callsign' => 'Quatre', 'uid' => '76561198000000004', 'role' => 'C_MORTIER', 'role_pref' => 'C_MORTIER']],
        ]);
        self::assertNotNull($snap);
        $prefs = AtakRoleService::extractPrefs($snap);
        self::assertSame(['key' => 'JTAC', 'label' => ''], $prefs['76561198000000001']);
        self::assertArrayNotHasKey('76561198000000002', $prefs, 'chef d’équipe non mémorisé');
        self::assertSame(['key' => 'MED', 'label' => 'Auxiliaire sanitaire'], $prefs['76561198000000003']);
        self::assertSame('C_MORTIER', $prefs['76561198000000004']['key']);
        self::assertCount(3, $prefs);
    }

    public function testCatalogPayloadMatchesDllFireTeamsShape(): void
    {
        $job = AtakRoleService::jobRoleEntry(12, 'Infirmier de combat');
        self::assertNotNull($job);
        $p = AtakRoleService::catalogPayload(
            [$job, $job, ['key' => 'C_MORTIER', 'label' => 'Mortier', 'short' => 'MOR', 'icon' => 'JTAC', 'origin' => 'CUSTOM']],
            ['key' => 'C_MORTIER', 'label' => 'Mortier'],
            ['key' => 'A12', 'label' => 'Infirmier de combat']
        );
        self::assertCount(2, $p['fire_teams']);
        [$cat, $pref] = $p['fire_teams'];
        self::assertSame(1, $cat['id']);
        self::assertSame(2, $cat['member_count'], 'doublon retiré');
        self::assertSame(['callsign' => 'A12', 'role' => 'MED~IC~ATHENA', 'display_name' => 'Infirmier de combat'], $cat['members'][0]);
        self::assertSame('JTAC~MOR~CUSTOM', $cat['members'][1]['role']);
        self::assertSame(['PREF', 'JOB'], array_column($pref['members'], 'callsign'));
        self::assertSame('A12', $pref['members'][1]['role']);
    }
}
