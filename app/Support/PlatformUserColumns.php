<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Colonnes du tableau des comptes (administration du site). La liste est fermée : ce qui vient de l’adresse
 * de la page n’est jamais utilisé tel quel, seules les clés de ce catalogue sont retenues.
 */
final class PlatformUserColumns
{
    /** Libellés affichés (clé => libellé). Le nom est toujours visible. */
    public const LABELS = [
        'nom' => 'Nom',
        'indicatif' => 'Indicatif',
        'email' => 'E-mail',
        'communautes' => 'Communautés',
        'etat' => 'État',
        'connexion_site' => 'Dernière connexion au site',
        'connexion_jeu' => 'Dernière connexion en jeu',
        'creation' => 'Création du compte',
        'volume' => 'Données reçues (30 jours)',
        'sanctions' => 'Sanctions passées',
    ];

    public const DEFAULT = ['nom', 'indicatif', 'email', 'communautes', 'etat', 'connexion_site', 'connexion_jeu', 'creation'];

    /** Anciennes clés restées valides (liens enregistrés) => clés actuelles. */
    public const ALIASES = ['connexion' => ['connexion_site', 'connexion_jeu']];

    /**
     * @param mixed $requested valeur brute de la requête (tableau de clés) ; null = colonnes par défaut
     * @return list<string> clés valides, dans l’ordre du catalogue, « nom » toujours présent
     */
    public static function normalize(mixed $requested): array
    {
        if (!is_array($requested)) {
            return self::DEFAULT;
        }
        $wanted = [];
        foreach ($requested as $key) {
            if (is_string($key) && isset(self::ALIASES[$key])) {
                foreach (self::ALIASES[$key] as $current) {
                    $wanted[$current] = true;
                }
            } elseif (is_string($key) && isset(self::LABELS[$key])) {
                $wanted[$key] = true;
            }
        }
        $wanted['nom'] = true;

        return array_values(array_filter(
            array_map('strval', array_keys(self::LABELS)),
            static fn (string $key): bool => isset($wanted[$key])
        ));
    }
}
