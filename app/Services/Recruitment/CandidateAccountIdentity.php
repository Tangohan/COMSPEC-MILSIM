<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Services\Profile\CandidateRpSnapshotService;

/**
 * « Un compte, une identité » : l'identité d'une candidature vient toujours du compte connecté (fiche utilisateur et
 * profil), jamais d'un champ envoyé par le navigateur. Le formulaire ne redemande que ce que le profil ne connaît pas
 * encore ; ces réponses complètent alors le profil.
 */
final class CandidateAccountIdentity
{
    private const NAME_MAX = 100;

    /** Champs que le formulaire peut demander quand le profil les ignore (clé du profil => champ du formulaire). */
    public const FORM_FIELDS = [
        'first_name' => 'profile_first_name',
        'last_name' => 'profile_last_name',
        'birth_date' => 'profile_birth_date',
        'nationality' => 'profile_nationality',
        'discord_handle' => 'profile_discord_handle',
    ];

    /**
     * @param array<string, mixed> $user fiche du compte connecté
     * @param array<string, mixed>|null $profile ligne user_profiles
     * @return array{
     *   first_name: string, last_name: string, birth_date: string, nationality: string, discord_handle: string,
     *   email: string, display_name: string, age: ?int,
     *   suggest_first_name: string, suggest_last_name: string, missing: list<string>
     * }
     */
    public static function fromAccount(array $user, ?array $profile, bool $needsDiscordHandle = false, ?\DateTimeImmutable $now = null): array
    {
        $profile = $profile ?? [];
        $first = self::clean((string) ($profile['first_name'] ?? ''));
        $last = self::clean((string) ($profile['last_name'] ?? ''));
        $birth = CandidateRpSnapshotService::normalizeRpBirthDate((string) ($profile['birth_date'] ?? ''));
        $nationality = self::clean((string) ($profile['nationality'] ?? ''));
        $discord = self::clean((string) ($profile['discord_handle'] ?? ''));
        $displayName = self::clean((string) ($user['display_name'] ?? ''));

        $suggestFirst = '';
        $suggestLast = '';
        if ($first === '' && $last === '' && $displayName !== '') {
            $pos = strpos($displayName, ' ');
            $suggestFirst = $pos === false ? $displayName : substr($displayName, 0, $pos);
            $suggestLast = $pos === false ? '' : trim(substr($displayName, $pos));
        }

        $missing = [];
        if ($first === '') {
            $missing[] = 'first_name';
        }
        if ($last === '') {
            $missing[] = 'last_name';
        }
        if ($birth === '') {
            $missing[] = 'birth_date';
        }
        if ($nationality === '') {
            $missing[] = 'nationality';
        }
        if ($needsDiscordHandle && $discord === '') {
            $missing[] = 'discord_handle';
        }

        $shownName = trim($first . ' ' . $last);

        return [
            'first_name' => $first,
            'last_name' => $last,
            'birth_date' => $birth,
            'nationality' => $nationality,
            'discord_handle' => $discord,
            'email' => trim((string) ($user['email'] ?? '')),
            'display_name' => $shownName !== '' ? $shownName : $displayName,
            'age' => self::ageFrom($birth, $now),
            'suggest_first_name' => $suggestFirst,
            'suggest_last_name' => $suggestLast,
            'missing' => $missing,
        ];
    }

    /**
     * Complète l'identité avec les seules réponses aux champs manquants. Un champ que le profil connaît déjà est
     * ignoré, même s'il est présent dans la requête.
     *
     * @param array<string, mixed> $identity résultat de fromAccount()
     * @param array<string, mixed> $input champs du formulaire (profile_first_name, profile_last_name, …)
     * @return array{identity: array<string, mixed>, profile_updates: array<string, string>, error: ?string}
     */
    public static function complete(array $identity, array $input, ?\DateTimeImmutable $now = null): array
    {
        $updates = [];
        $error = null;
        foreach ((array) $identity['missing'] as $key) {
            $raw = self::clean(is_scalar($input[self::FORM_FIELDS[$key]] ?? null) ? (string) $input[self::FORM_FIELDS[$key]] : '');
            if ($key === 'birth_date') {
                if ($raw === '') {
                    continue;
                }
                $date = CandidateRpSnapshotService::normalizeRpBirthDate($raw);
                $age = self::ageFrom($date, $now);
                if ($date === '' || $age === null || $age < 0 || $age > 110) {
                    $error = 'La date de naissance n’est pas valide.';
                    break;
                }
                $raw = $date;
            } elseif ($raw === '') {
                if ($key === 'first_name') {
                    $error = 'Merci d’indiquer votre prénom.';
                    break;
                }
                if ($key === 'last_name') {
                    $error = 'Merci d’indiquer votre nom.';
                    break;
                }
                if ($key === 'discord_handle') {
                    $error = 'Merci d’indiquer votre pseudo Discord.';
                    break;
                }
                continue;
            }
            $updates[$key] = $raw;
            $identity[$key] = $raw;
        }
        if ($error !== null) {
            return ['identity' => $identity, 'profile_updates' => [], 'error' => $error];
        }
        $identity['age'] = self::ageFrom((string) $identity['birth_date'], $now);
        $shown = trim((string) $identity['first_name'] . ' ' . (string) $identity['last_name']);
        if ($shown !== '') {
            $identity['display_name'] = $shown;
        }
        $identity['missing'] = array_values(array_diff((array) $identity['missing'], array_keys($updates)));

        return ['identity' => $identity, 'profile_updates' => $updates, 'error' => null];
    }

    public static function ageFrom(string $birthDate, ?\DateTimeImmutable $now = null): ?int
    {
        if ($birthDate === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
        if ($d === false) {
            return null;
        }
        $now ??= new \DateTimeImmutable('now');
        if ($d > $now) {
            return -1;
        }

        return (int) $d->diff($now)->y;
    }

    private static function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return function_exists('mb_substr') ? mb_substr($value, 0, self::NAME_MAX) : substr($value, 0, self::NAME_MAX);
    }
}
