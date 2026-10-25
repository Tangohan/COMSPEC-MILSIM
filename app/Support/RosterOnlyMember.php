<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Fiche sans compte (profil « Bureau des effectifs ») : l’état-major tient la fiche d’un membre
 * qui ne se connecte jamais. La ligne `users` existe pour que grades, affectations, ORBAT et
 * export fonctionnent comme pour les autres, mais son adresse est réservée (domaine .invalid,
 * RFC 2606) : aucun e-mail n’y part et le mot de passe aléatoire n’est jamais communiqué.
 */
final class RosterOnlyMember
{
    public const EMAIL_DOMAIN = 'sans-compte.invalid';

    public static function syntheticEmail(): string
    {
        return 'fiche-' . bin2hex(random_bytes(8)) . '@' . self::EMAIL_DOMAIN;
    }

    public static function isRosterEmail(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && str_ends_with($email, '@' . self::EMAIL_DOMAIN);
    }

    /** Libellé affiché à la place de l’adresse. */
    public static function label(): string
    {
        return function_exists('i18n_phrase') ? i18n_phrase('nav', 'Fiche sans compte') : 'Fiche sans compte';
    }
}
