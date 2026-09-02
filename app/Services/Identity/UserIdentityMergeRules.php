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

    /** Colonnes fusionnables champ par champ si le survivant a déjà une ligne. */
    public const IDENTITY_TABLE_MERGE_FIELDS = [
        'user_profiles' => [
            'first_name', 'last_name', 'birth_date', 'nationality', 'country_of_residence',
            'public_flag_country_code', 'discord_handle', 'timezone', 'language', 'bio', 'phone',
        ],
        'user_legal_identities' => [
            'first_name', 'last_name', 'birth_date', 'nationality', 'phone',
        ],
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

    /**
     * Complète une ligne existante avec les champs non vides d’une autre (sans écraser).
     *
     * @param array<string, mixed> $survivorRow
     * @param array<string, mixed> $incomingRow
     * @param list<string>         $fields
     * @return array<string, mixed>
     */
    public static function mergeRowFieldsOntoSurvivor(array $survivorRow, array $incomingRow, array $fields): array
    {
        $fills = [];
        foreach ($fields as $key) {
            $current = $survivorRow[$key] ?? null;
            $incoming = $incomingRow[$key] ?? null;
            if (self::isEmptyIdentityValue($current) && !self::isEmptyIdentityValue($incoming)) {
                $fills[$key] = $incoming;
            }
        }

        return $fills;
    }

    /**
     * Profil communauté : ne retient que les champs utiles à combler sur une fiche existante.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public static function communityProfileFillEmpty(array $existing, array $incoming): array
    {
        $fills = [];
        foreach (self::COMMUNITY_PROFILE_FIELDS as $key) {
            if (!array_key_exists($key, $incoming)) {
                continue;
            }
            $current = $existing[$key] ?? null;
            $value = $incoming[$key];
            if (self::isEmptyIdentityValue($current) && !self::isEmptyIdentityValue($value)) {
                $fills[$key] = $value;
            }
        }

        return $fills;
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

    private static function isEmptyIdentityValue(mixed $value): bool
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

    /**
     * @param array<string, mixed> $row
     */
    private static function createdSortKey(array $row): string
    {
        $raw = trim((string) ($row['created_at'] ?? ''));

        return $raw !== '' ? $raw : '9999-12-31 23:59:59';
    }
}
