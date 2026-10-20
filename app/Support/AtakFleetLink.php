<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parc ATAK (/admin/parc-atak) : le site retrouve-t-il le compte Athena du joueur ?
 * Corrélation fiche membre ↔ Steam vu en jeu, et accès que la formule de la communauté donne au jeu.
 * Mêmes règles que la connexion du jeu (GameAuthService::resolveAccountByLinkedSteam) : seule la fiche membre
 * (users.steam_id) fait la liaison, un Steam présent sur plusieurs fiches en choisit une au hasard.
 */
final class AtakFleetLink
{
    /**
     * États de corrélation, du plus bloquant au plus sain (ordre du tri « Compte Athena »).
     * [libellé, teinte, explication]
     */
    public const STATES = [
        'duplicate' => ['Steam en double', 'rose', 'Ce Steam est sur plusieurs fiches membres : la connexion du jeu prend l’une d’elles au hasard. Gardez une seule fiche.'],
        'no_account' => ['Aucun compte Athena', 'rose', 'Steam vu en jeu sans fiche membre qui le porte : la connexion du jeu est refusée tant que le joueur n’a pas lié son Steam (page « Lier le jeu »).'],
        'account_only' => ['Steam hors fiche', 'rose', 'Ce Steam est sur un compte Athena mais sur aucune fiche membre : le site ne le reconnaît pas, la connexion du jeu est refusée. Refaire la liaison.'],
        'disabled' => ['Compte désactivé', 'rose', 'La fiche membre liée à ce Steam est désactivée ou bannie : toute connexion du jeu est refusée.'],
        'other_tenant' => ['Autre communauté', 'amber', 'Ce Steam est lié à un membre d’une autre communauté : le jeu se connecte à celle-là, pas à celle du serveur.'],
        'steam_mismatch' => ['Steam différent en jeu', 'amber', 'Le Steam vu en jeu n’est pas celui de la fiche membre : le jeu ne retrouve pas ce compte.'],
        'no_steam' => ['Steam non lié', 'amber', 'La fiche membre n’a pas de Steam : le jeu ne peut pas se connecter par Steam, seulement par e-mail ou code.'],
        'bad_email' => ['E-mail invalide', 'amber', 'La fiche membre a un e-mail vide ou invalide : le compte Athena du jeu ne peut pas être retrouvé.'],
        'unlinked' => ['ATAK sans compte', 'slate', 'ATAK enregistré sans fiche membre (fiche supprimée ou liaison ancienne).'],
        'no_terminal' => ['Compte trouvé', 'blue', 'Compte Athena retrouvé à partir du Steam, mais aucun ATAK enregistré pour lui dans cette communauté.'],
        'ok' => ['Corrélé', 'mint', 'Fiche membre active, Steam identique en jeu : le site retrouve le compte.'],
    ];

    /** États qui font refuser la connexion du jeu. */
    public const DENYING = ['duplicate', 'no_account', 'account_only', 'disabled'];

    /** Accès au jeu que donne la formule de la communauté. [libellé, teinte] */
    public const PLAN_ACCESS = [
        'full' => ['Carte complète', 'mint'],
        'basic' => ['Liaison de base', 'amber'],
        'none' => ['Sans la carte', 'rose'],
        'unknown' => ['Communauté inconnue', 'slate'],
    ];

    /**
     * Corrélation d'une ligne du parc.
     *
     * @param array<string, mixed>|null $user fiche membre liée à l'ATAK (id, tenant_id, status, email, steam_id) ; null si aucune
     * @param list<array<string, mixed>> $steamUsers fiches membres qui portent le Steam de la ligne (toutes communautés)
     * @param string $gameSteam Steam vu en jeu pour ce joueur ('' si inconnu)
     * @param bool $accountSteam le Steam est sur un compte Athena (athena_accounts) sans fiche membre
     */
    public static function classify(bool $hasTerminal, int $tenantId, ?array $user, array $steamUsers, string $gameSteam = '', bool $accountSteam = false): string
    {
        if ($hasTerminal && $user !== null) {
            if (!self::active($user)) {
                return 'disabled';
            }
            $steam = SteamId::normalize((string) ($user['steam_id'] ?? '')) ?? '';
            if ($steam === '') {
                return 'no_steam';
            }
            if (count($steamUsers) > 1) {
                return 'duplicate';
            }
            if ($gameSteam !== '' && $gameSteam !== $steam) {
                return 'steam_mismatch';
            }

            return self::validEmail($user) ? 'ok' : 'bad_email';
        }
        if ($hasTerminal) {
            return 'unlinked';
        }
        // Joueur connu par son Steam seulement (vu en jeu ou par les rapports du mod).
        if (count($steamUsers) > 1) {
            return 'duplicate';
        }
        if ($steamUsers === []) {
            return $accountSteam ? 'account_only' : 'no_account';
        }
        $u = $steamUsers[0];
        if (!self::active($u)) {
            return 'disabled';
        }
        if ($tenantId > 0 && (int) ($u['tenant_id'] ?? 0) > 0 && (int) $u['tenant_id'] !== $tenantId) {
            return 'other_tenant';
        }
        if (!self::validEmail($u)) {
            return 'bad_email';
        }

        return 'no_terminal';
    }

    /** Rang pour le tri (0 = le plus bloquant). */
    public static function rank(string $state): int
    {
        $i = array_search($state, array_keys(self::STATES), true);

        return $i === false ? count(self::STATES) : (int) $i;
    }

    /**
     * Pourquoi le jeu de ce joueur serait refusé (vide : rien ne bloque côté site).
     *
     * @param array<string, mixed>|null $block blocage actif
     * @return list<string>
     */
    public static function denialReasons(string $link, string $planAccess, ?array $block): array
    {
        $out = [];
        if ($block !== null) {
            $out[] = 'ATAK bloqué' . ((int) ($block['tenant_id'] ?? 0) === 0 ? ' dans toutes les communautés' : '');
        }
        if (in_array($link, self::DENYING, true)) {
            $out[] = self::STATES[$link][0];
        }
        if ($planAccess === 'none') {
            $out[] = 'Formule sans la carte';
        }

        return $out;
    }

    /** @param array<string, mixed> $user */
    private static function active(array $user): bool
    {
        $status = strtolower(trim((string) ($user['status'] ?? 'active')));

        return $status === '' || $status === 'active';
    }

    /** @param array<string, mixed> $user */
    private static function validEmail(array $user): bool
    {
        return filter_var(trim((string) ($user['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
    }
}
