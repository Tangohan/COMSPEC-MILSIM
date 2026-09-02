<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Identity\UserIdentityMergeRules;
use PHPUnit\Framework\TestCase;

final class UserIdentityMergeRulesTest extends TestCase
{
    public function testPickSurvivorPrefersOldestWhenEquallyComplete(): void
    {
        $older = [
            'id' => 4,
            'email' => 'a@example.test',
            'password_hash' => 'hash',
            'steam_id' => '',
            'created_at' => '2024-01-01 10:00:00',
            'status' => 'active',
        ];
        $newer = [
            'id' => 9,
            'email' => 'a@example.test',
            'password_hash' => 'hash',
            'steam_id' => '',
            'created_at' => '2025-06-01 10:00:00',
            'status' => 'active',
        ];
        $survivor = UserIdentityMergeRules::pickSurvivor([$newer, $older]);
        self::assertSame(4, (int) $survivor['id']);
    }

    public function testPickSurvivorPrefersMoreCompleteOverOlderEmpty(): void
    {
        $olderEmpty = [
            'id' => 1,
            'password_hash' => '',
            'steam_id' => '',
            'created_at' => '2023-01-01 00:00:00',
            'status' => 'pending',
        ];
        $newerComplete = [
            'id' => 2,
            'password_hash' => 'argon',
            'steam_id' => '76561198000000000',
            'email_verified_at' => '2025-01-01 00:00:00',
            'last_login_at' => '2026-01-01 00:00:00',
            'created_at' => '2025-01-01 00:00:00',
            'status' => 'active',
        ];
        $survivor = UserIdentityMergeRules::pickSurvivor([$olderEmpty, $newerComplete]);
        self::assertSame(2, (int) $survivor['id']);
    }

    public function testMergeIdentityDoesNotInventValuesAndKeepsSurvivorPassword(): void
    {
        $survivor = [
            'id' => 1,
            'password_hash' => 'keep-me',
            'steam_id' => '',
            'avatar_url' => null,
        ];
        $absorbed = [[
            'id' => 2,
            'password_hash' => 'other',
            'steam_id' => '76561198000000000',
            'avatar_url' => 'https://example.test/a.png',
        ]];
        $merged = UserIdentityMergeRules::mergeIdentityOntoSurvivor($survivor, $absorbed);
        self::assertArrayNotHasKey('password_hash', $merged['fields']);
        self::assertSame('76561198000000000', $merged['fields']['steam_id']);
        self::assertSame('https://example.test/a.png', $merged['fields']['avatar_url']);
        self::assertSame([], $merged['steam_collisions']);
    }

    public function testSteamCollisionIsLoggedAndSurvivorSteamWins(): void
    {
        $survivor = ['id' => 1, 'steam_id' => '76561198000000001', 'password_hash' => 'a'];
        $absorbed = [['id' => 2, 'steam_id' => '76561198000000002', 'password_hash' => 'b']];
        $merged = UserIdentityMergeRules::mergeIdentityOntoSurvivor($survivor, $absorbed);
        self::assertArrayNotHasKey('steam_id', $merged['fields']);
        self::assertCount(1, $merged['steam_collisions']);
        self::assertSame(2, $merged['steam_collisions'][0]['absorbed_user_id']);
        self::assertSame('76561198000000002', $merged['steam_collisions'][0]['steam_id']);
    }

    public function testCommunityProfileKeepsTenantScopedFieldsOnly(): void
    {
        $row = [
            'id' => 8,
            'tenant_id' => 3,
            'email' => 'a@example.test',
            'password_hash' => 'secret',
            'steam_id' => '7656',
            'grade_id' => 12,
            'callsign' => 'TA1',
            'tenant_member_number' => 'M-004',
            'status' => 'active',
        ];
        $profile = UserIdentityMergeRules::communityProfileFromUserRow($row);
        self::assertSame(12, $profile['grade_id']);
        self::assertSame('TA1', $profile['callsign']);
        self::assertSame('M-004', $profile['tenant_member_number']);
        self::assertArrayNotHasKey('email', $profile);
        self::assertArrayNotHasKey('password_hash', $profile);
        self::assertArrayNotHasKey('steam_id', $profile);
    }

    public function testRhTablesStayTenantScopedAndIdentityTablesDoNotMixDossiers(): void
    {
        self::assertContains('personnel_profiles', UserIdentityMergeRules::RH_ONE_TO_ONE_TABLES);
        self::assertContains('personnel_extras', UserIdentityMergeRules::RH_ONE_TO_ONE_TABLES);
        self::assertContains('user_profiles', UserIdentityMergeRules::IDENTITY_ONE_TO_ONE_TABLES);
        self::assertNotContains('personnel_profiles', UserIdentityMergeRules::IDENTITY_ONE_TO_ONE_TABLES);
    }

    public function testMergedStubEmailReleasesTheAddress(): void
    {
        $email = UserIdentityMergeRules::mergedStubEmail(42);
        self::assertSame('merged+42@merged.invalid', $email);
        self::assertFalse(UserIdentityMergeRules::isLiveHumanEmail($email));
        self::assertTrue(UserIdentityMergeRules::isLiveHumanEmail('pilot@example.test'));
    }

    public function testMergeRowFieldsOnlyFillsEmptySurvivorColumns(): void
    {
        $survivor = ['first_name' => 'Jean', 'last_name' => '', 'phone' => null];
        $incoming = ['first_name' => 'Jacques', 'last_name' => 'Dupont', 'phone' => '0600000000'];
        $fills = UserIdentityMergeRules::mergeRowFieldsOntoSurvivor(
            $survivor,
            $incoming,
            ['first_name', 'last_name', 'phone']
        );
        self::assertArrayNotHasKey('first_name', $fills);
        self::assertSame('Dupont', $fills['last_name']);
        self::assertSame('0600000000', $fills['phone']);
    }

    public function testCommunityProfileFillEmptySkipsAlreadySetValues(): void
    {
        $existing = ['callsign' => 'ALPHA', 'grade_id' => 5];
        $incoming = ['callsign' => 'BRAVO', 'grade_id' => 9, 'display_name' => 'Opérateur'];
        $fills = UserIdentityMergeRules::communityProfileFillEmpty($existing, $incoming);
        self::assertArrayNotHasKey('callsign', $fills);
        self::assertArrayNotHasKey('grade_id', $fills);
        self::assertSame('Opérateur', $fills['display_name']);
    }

    public function testPickPreferredDossierPrefersSessionTenantThenRichestRow(): void
    {
        $empty = [
            'id' => 1,
            'user_id' => 10,
            'tenant_id' => 1,
            'character_name' => '',
            'callsign' => '',
        ];
        $filled = [
            'id' => 2,
            'user_id' => 10,
            'tenant_id' => 7,
            'character_name' => 'Jean Dupont',
            'callsign' => 'FALCON',
        ];
        $pickedTenant = UserIdentityMergeRules::pickPreferredDossierRow([$empty, $filled], 7);
        self::assertNotNull($pickedTenant);
        self::assertSame(2, (int) $pickedTenant['id']);

        $skippedEmptyPreferred = UserIdentityMergeRules::pickPreferredDossierRow([$empty, $filled], 1);
        self::assertNotNull($skippedEmptyPreferred);
        self::assertSame(2, (int) $skippedEmptyPreferred['id']);

        $pickedRichest = UserIdentityMergeRules::pickPreferredDossierRow([$empty, $filled], 99);
        self::assertNotNull($pickedRichest);
        self::assertSame(2, (int) $pickedRichest['id']);
    }

    public function testFillEmptyKeysNeverCopiesMergedStubNameOrOverwritesFilledFields(): void
    {
        $target = ['first_name' => '', 'last_name' => 'Survivant', 'display_name' => ''];
        $source = [
            'first_name' => 'Marie',
            'last_name' => 'Absorbee',
            'display_name' => 'Compte fusionné',
            'bio' => 'Ancien dossier',
        ];
        $fill = UserIdentityMergeRules::fillEmptyKeys($target, $source);
        self::assertSame('Marie', $fill['first_name']);
        self::assertArrayNotHasKey('last_name', $fill);
        self::assertArrayNotHasKey('display_name', $fill);
        self::assertSame('Ancien dossier', $fill['bio']);
    }

    public function testOverlaySkipsEmptyCommunityFieldsAndPendingShell(): void
    {
        $user = ['status' => 'active', 'callsign' => 'WOLF', 'role_id' => 4];
        $emptyProfile = [
            'display_name' => '',
            'callsign' => null,
            'role_id' => 0,
            'status' => 'pending',
        ];
        self::assertFalse(UserIdentityMergeRules::shouldOverlayCommunityField('callsign', null, $emptyProfile, $user));
        self::assertFalse(UserIdentityMergeRules::shouldOverlayCommunityField('role_id', 0, $emptyProfile, $user));
        self::assertFalse(UserIdentityMergeRules::shouldOverlayCommunityField('status', 'pending', $emptyProfile, $user));
        self::assertTrue(UserIdentityMergeRules::shouldOverlayCommunityField(
            'callsign',
            'EAGLE',
            ['callsign' => 'EAGLE', 'status' => 'active'],
            $user
        ));
    }
}
