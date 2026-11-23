<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Identity\UserIdentityMergeRules;
use PHPUnit\Framework\TestCase;

/**
 * Régression : la colonne « Indicatif » du tableur des effectifs affichait users.callsign (compte d'origine)
 * alors que la fiche membre affichait l'indicatif de la communauté (user_community_profiles).
 * Données fictives.
 */
final class RosterCommunityIdentityOverlayTest extends TestCase
{
    public function testListShowsCommunityCallsignLikeTheMemberSheet(): void
    {
        $rows = [
            ['id' => 1, 'display_name' => 'Chef Alpha', 'callsign' => 'YA1'],
            ['id' => 2, 'display_name' => 'Chef Bravo', 'callsign' => 'YA1'],
            ['id' => 3, 'display_name' => 'Sans fiche', 'callsign' => 'ZZ9'],
        ];
        $profiles = [
            1 => ['user_id' => 1, 'display_name' => 'Chef Alpha', 'callsign' => 'YA1'],
            2 => ['user_id' => 2, 'display_name' => 'Chef Bravo', 'callsign' => 'YB1'],
        ];

        $out = UserIdentityMergeRules::overlayCommunityIdentity($rows, $profiles);

        self::assertSame('YA1', $out[0]['callsign']);
        self::assertSame('YB1', $out[1]['callsign']);
        self::assertSame('ZZ9', $out[2]['callsign']);
    }

    public function testEmptyCommunityCallsignKeepsAccountValue(): void
    {
        $out = UserIdentityMergeRules::overlayCommunityIdentity(
            [['id' => 5, 'display_name' => 'Membre', 'callsign' => 'YC1']],
            [5 => ['user_id' => 5, 'display_name' => '', 'callsign' => '  ']]
        );

        self::assertSame('YC1', $out[0]['callsign']);
        self::assertSame('Membre', $out[0]['display_name']);
    }

    public function testListQueryOverlaysAndSearchesCommunityIdentity(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php');
        self::assertStringContainsString('$rows = $this->withCommunityIdentity($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $tenantId);', $src);
        self::assertStringContainsString('UserIdentityMergeRules::overlayCommunityIdentity($rows, $byUser)', $src);
        self::assertStringContainsString('cps.callsign LIKE ?', $src);
    }
}
