<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Les blocs d'une campagne de fiabilisation des données : ce que le membre relit, dans l'ordre du parcours.
 *
 * Chaque bloc dit aussi quels champs du formulaire de correction de fiche existant (PersonnelCorrectionRequestService)
 * le membre peut proposer directement ; pour tout le reste, il décrit l'erreur et l'encadrement la traite.
 * Aucune donnée d'identité d'emprunt, de couverture ni de papiers (SSE) n'est un bloc : elles n'ont rien à faire ici.
 */
final class DataCampaignBlocks
{
    public const IDENTITY = 'identity';
    public const IDENTIFIERS = 'identifiers';
    public const ASSIGNMENT = 'assignment';
    public const QUALIFICATIONS = 'qualifications';
    public const HOURS = 'hours';
    public const CONTACT = 'contact';

    /**
     * @var array<string, array{label: string, lead: string, fields: list<string>, volatile: bool}>
     *      fields : champs de correction de fiche proposables ; volatile : la valeur évolue seule (heures) et ne
     *      compte pas comme « modifiée depuis la confirmation »
     */
    public const CATALOG = [
        self::IDENTITY => [
            'label' => 'Identité',
            'lead' => 'Votre image, votre nom, votre prénom et votre indicatif tels qu’ils sont enregistrés.',
            'fields' => ['first_name', 'last_name', 'callsign', 'nickname_primary'],
            'volatile' => false,
        ],
        self::IDENTIFIERS => [
            'label' => 'Matricules et identifiants',
            'lead' => 'Vos numéros de membre, votre matricule, votre trigramme et vos indicatifs radio.',
            'fields' => ['extra_callsigns'],
            'volatile' => false,
        ],
        self::ASSIGNMENT => [
            'label' => 'Affectation et fonction',
            'lead' => 'Votre grade, votre ou vos unités, vos fonctions et votre date d’engagement.',
            'fields' => ['enlistment_date'],
            'volatile' => false,
        ],
        self::QUALIFICATIONS => [
            'label' => 'Qualifications et brevets',
            'lead' => 'Les qualifications et brevets inscrits à votre dossier, avec leur état.',
            'fields' => [],
            'volatile' => false,
        ],
        self::HOURS => [
            'label' => 'Heures',
            'lead' => 'Vos heures de jeu et, si vous pilotez, vos heures de vol, telles que le site les a comptées.',
            'fields' => [],
            'volatile' => true,
        ],
        self::CONTACT => [
            'label' => 'Coordonnées',
            'lead' => 'L’adresse e-mail de votre compte, utilisée pour vous prévenir.',
            'fields' => [],
            'volatile' => false,
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function isBlock(string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }

    public static function label(string $key): string
    {
        return self::CATALOG[$key]['label'] ?? $key;
    }

    /** @return list<string> */
    public static function correctionFields(string $key): array
    {
        return self::CATALOG[$key]['fields'] ?? [];
    }

    public static function isVolatile(string $key): bool
    {
        return !empty(self::CATALOG[$key]['volatile']);
    }

    /**
     * Sélection de blocs saisie ou relue en base : seuls les blocs connus, dans l'ordre du parcours, sans doublon.
     * Une sélection vide ou illisible donne tous les blocs.
     *
     * @return list<string>
     */
    public static function normalize(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        $wanted = is_array($raw) ? array_map('strval', $raw) : [];
        $out = array_values(array_filter(self::keys(), static fn (string $k): bool => in_array($k, $wanted, true)));

        return $out === [] ? self::keys() : $out;
    }
}
