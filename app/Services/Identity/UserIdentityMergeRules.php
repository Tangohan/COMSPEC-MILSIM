<?php

declare(strict_types=1);

namespace App\Services\Identity;

/**
 * Règles pures de fusion d’identité (un e-mail = un compte).
 * Aucune invention de grade, matricule ou accusé : une valeur absente reste vide.
 */
final class UserIdentityMergeRules
{
    public const MERGED_EMAIL_DOMAIN = 'merged.invalid';

    /** Colonnes d’identité plateforme (partagées, jamais copiées depuis une autre communauté RH). */
    public const IDENTITY_FIELDS = [
        'email',
        'password_hash',
        'steam_id',
        'avatar_url',
        'profile_banner_url',
        'email_verified_at',
        'totp_enabled',
        'totp_secret',
        'totp_confirmed_at',
        'email_login_otp_enabled',
        'is_platform_admin',
        'is_super_admin',
    ];

    /** Colonnes de dossier communauté — restent scopées tenant. */
    public const COMMUNITY_PROFILE_FIELDS = [
        'display_name',
        'callsign',
        'profile_slug',
        'athena_identifier',
        'role_id',
        'grade_id',
        'status',
        'tenant_member_number',
        'nationality_code',
        'preferred_grade_format',
        'professional_category_code',
        'preferred_display_role_id',
    ];

    /** Tables 1:1 identité : on garde la ligne du survivant, on ne mélange pas. */
    public const IDENTITY_ONE_TO_ONE_TABLES = [
        'user_profiles',
        'user_profile_display_settings',
        'user_legal_identities',
        'user_notification_preferences',
        'user_ui_preferences',
        'password_resets',
        'user_login_devices',
        'user_alert_dismissals',
        'user_forum_stats',
        'user_totp_recovery_codes',
    ];

    /** Tables RH 1:1 : scoper par tenant puis rattacher au survivant. */
    public const RH_ONE_TO_ONE_TABLES = [
        'personnel_profiles',
        'personnel_extras',
    ];

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function pickSurvivor(array $rows): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Aucune fiche à fusionner.');
        }
        usort($rows, static function (array $a, array $b): int {
            $score = self::completenessScore($b) <=> self::completenessScore($a);
            if ($score !== 0) {
                return $score;
            }
            $ca = self::createdSortKey($a);
            $cb = self::createdSortKey($b);
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function completenessScore(array $row): int
    {
        $score = 0;
        if (trim((string) ($row['password_hash'] ?? '')) !== '') {
            $score += 8;
        }
        if (trim((string) ($row['steam_id'] ?? '')) !== '') {
            $score += 4;
        }
        if (!empty($row['email_verified_at'])) {
            $score += 3;
        }
        if (trim((string) ($row['display_name'] ?? '')) !== '') {
            $score += 1;
        }
        if (!empty($row['last_login_at'])) {
            $score += 2;
        }
        if (!empty($row['is_platform_admin']) || !empty($row['is_super_admin'])) {
            $score += 2;
        }
        if (($row['status'] ?? '') === 'active') {
            $score += 1;
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $survivor
     * @param list<array<string, mixed>> $absorbed
     * @return array{fields: array<string, mixed>, steam_collisions: list<array{absorbed_user_id: int, steam_id: string}>}
     */
    public static function mergeIdentityOntoSurvivor(array $survivor, array $absorbed): array
    {
        $fields = [];
        $collisions = [];
        $survivorSteam = trim((string) ($survivor['steam_id'] ?? ''));
        foreach ($absorbed as $row) {
            foreach (self::IDENTITY_FIELDS as $key) {
                if ($key === 'email' || $key === 'steam_id') {
                    continue;
                }
                $current = $fields[$key] ?? ($survivor[$key] ?? null);
                $incoming = $row[$key] ?? null;
                if (self::isEmptyIdentityValue($current) && !self::isEmptyIdentityValue($incoming)) {
                    $fields[$key] = $incoming;
                }
            }
            if (!empty($row['is_platform_admin'])) {
                $fields['is_platform_admin'] = 1;
            }
            if (!empty($row['is_super_admin'])) {
                $fields['is_super_admin'] = 1;
            }
            $otherSteam = trim((string) ($row['steam_id'] ?? ''));
            if ($otherSteam === '') {
                continue;
            }
            if ($survivorSteam === '' && trim((string) ($fields['steam_id'] ?? '')) === '') {
                $fields['steam_id'] = $otherSteam;
                $survivorSteam = $otherSteam;
                continue;
            }
            if ($survivorSteam !== '' && $otherSteam !== $survivorSteam) {
                $collisions[] = [
                    'absorbed_user_id' => (int) ($row['id'] ?? 0),
                    'steam_id' => $otherSteam,
                ];
            }
        }

        return ['fields' => $fields, 'steam_collisions' => $collisions];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function communityProfileFromUserRow(array $row): array
    {
        $out = [];
        foreach (self::COMMUNITY_PROFILE_FIELDS as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }

    public static function mergedStubEmail(int $absorbedUserId): string
    {
        return 'merged+' . $absorbedUserId . '@' . self::MERGED_EMAIL_DOMAIN;
    }

    public static function isServiceAccount(array $row): bool
    {
        return !empty($row['is_service_account']);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isLiveHumanEmail(string $email): bool
    {
        $email = self::normalizeEmail($email);
        if ($email === '' || !str_contains($email, '@')) {
            return false;
        }
        if (str_ends_with($email, '@deleted.invalid') || str_ends_with($email, '@' . self::MERGED_EMAIL_DOMAIN)) {
            return false;
        }

        return true;
    }

    public static function isEmptyIdentityValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        if (is_int($value) || is_float($value)) {
            return false;
        }

        return $value === false;
    }

    public static function isMergedStubDisplayName(string $name): bool
    {
        $normalized = strtolower(trim($name));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'compte fusionn');
    }

    public static function communityProfileHasSubstance(array $profile): bool
    {
        foreach (['display_name', 'callsign', 'profile_slug', 'athena_identifier', 'tenant_member_number'] as $key) {
            $value = $profile[$key] ?? null;
            if ($key === 'display_name' && is_string($value) && self::isMergedStubDisplayName($value)) {
                continue;
            }
            if (!self::isEmptyIdentityValue($value)) {
                return true;
            }
        }
        foreach (['role_id', 'grade_id', 'preferred_display_role_id'] as $key) {
            if ((int) ($profile[$key] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $user
     */
    public static function shouldOverlayCommunityField(string $key, mixed $incoming, array $profile, array $user): bool
    {
        if ($key === 'status') {
            $incomingStatus = strtolower(trim((string) ($incoming ?? '')));
            if ($incomingStatus === '') {
                return false;
            }
            if ($incomingStatus === 'pending' && !self::communityProfileHasSubstance($profile)) {
                return false;
            }

            return true;
        }
        if (in_array($key, ['role_id', 'grade_id', 'preferred_display_role_id'], true)) {
            return (int) $incoming > 0;
        }
        if ($key === 'display_name' && is_string($incoming) && self::isMergedStubDisplayName($incoming)) {
            return false;
        }

        return !self::isEmptyIdentityValue($incoming);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    public static function pickPreferredDossierRow(array $rows, int $preferredTenantId = 0): ?array
    {
        if ($rows === []) {
            return null;
        }
        usort($rows, static function (array $a, array $b) use ($preferredTenantId): int {
            $score = self::dossierCompletenessScore($b) <=> self::dossierCompletenessScore($a);
            if ($score !== 0) {
                return $score;
            }
            if ($preferredTenantId > 0) {
                $aPref = (int) ($a['tenant_id'] ?? 0) === $preferredTenantId ? 1 : 0;
                $bPref = (int) ($b['tenant_id'] ?? 0) === $preferredTenantId ? 1 : 0;
                if ($aPref !== $bPref) {
                    return $bPref <=> $aPref;
                }
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows[0];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function dossierCompletenessScore(array $row): int
    {
        $skip = ['id', 'user_id', 'tenant_id', 'created_at', 'updated_at'];
        $score = 0;
        foreach ($row as $key => $value) {
            if (in_array((string) $key, $skip, true)) {
                continue;
            }
            if ($key === 'display_name' && is_string($value) && self::isMergedStubDisplayName($value)) {
                continue;
            }
            if (self::isEmptyIdentityValue($value)) {
                continue;
            }
            $score++;
            if (in_array((string) $key, ['character_name', 'callsign', 'character_portrait_path', 'first_name', 'last_name', 'display_name', 'bio'], true)) {
                $score += 4;
            }
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public static function fillEmptyKeys(array $target, array $source): array
    {
        $out = [];
        foreach ($source as $key => $value) {
            $key = (string) $key;
            if (in_array($key, ['id', 'user_id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if ($key === 'display_name' && is_string($value) && self::isMergedStubDisplayName($value)) {
                continue;
            }
            if (self::isEmptyIdentityValue($target[$key] ?? null) && !self::isEmptyIdentityValue($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function createdSortKey(array $row): string
    {
        $raw = trim((string) ($row['created_at'] ?? ''));

        return $raw !== '' ? $raw : '9999-12-31 23:59:59';
    }
}
