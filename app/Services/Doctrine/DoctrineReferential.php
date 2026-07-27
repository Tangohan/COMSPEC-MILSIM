<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

/**
 * Référentiel doctrinal choisi par une communauté : américain, français, ou les deux.
 *
 * Le choix ne change rien aux données déjà saisies. Il filtre uniquement ce que les
 * catalogues proposent : gabarits d’ordres et de comptes rendus, échelons de commandement,
 * parcours de formation. Une communauté qui bascule de FR à US ne perd donc aucun document.
 *
 * Portée du réglage : **un seul choix pour toute la communauté**. Un réglage par module
 * (ordres en US, structure en FR…) a été écarté : il multiplie les combinaisons incohérentes
 * pour un gain rare, une unité adoptant en pratique une doctrine d’ensemble.
 */
final class DoctrineReferential
{
    public const US = 'us';
    public const FR = 'fr';
    public const BOTH = 'both';

    /** Défaut : français. Le produit est francophone et l’existant est rédigé en français. */
    public const DEFAULT = self::FR;

    /** @return list<string> */
    public static function keys(): array
    {
        return [self::FR, self::US, self::BOTH];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::FR => 'Doctrine française',
            self::US => 'Doctrine américaine',
            self::BOTH => 'Les deux référentiels',
        ];
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return [
            self::FR => 'Ordre initial et ordre de conduite, compte rendu, demande d’évacuation. Groupe, section, compagnie. Formation générale puis certificats et brevets.',
            self::US => 'OPORD en cinq paragraphes, WARNO, FRAGO, SALUTE, SITREP, MEDEVAC 9 lignes. Fire team, squad, platoon, company. Formation initiale puis AIT, écoles et badges.',
            self::BOTH => 'Les deux jeux de gabarits, d’échelons et de parcours sont proposés côte à côte, chacun étiqueté par son origine.',
        ];
    }

    public static function sanitize(mixed $raw): string
    {
        $value = strtolower(trim((string) ($raw ?? '')));

        return in_array($value, self::keys(), true) ? $value : self::DEFAULT;
    }

    public static function label(string $referential): string
    {
        return self::labels()[self::sanitize($referential)] ?? self::labels()[self::DEFAULT];
    }

    /**
     * Étiquette d’origine d’une entrée de catalogue.
     */
    public static function originLabel(string $origin): string
    {
        return match (strtolower(trim($origin))) {
            self::US => 'US',
            self::FR => 'FR',
            default => 'Commun',
        };
    }

    /**
     * Une entrée d’origine `$origin` est-elle retenue pour le référentiel `$referential` ?
     *
     * Les entrées marquées « commun » (origine vide ou inconnue) sont toujours retenues :
     * elles décrivent des pratiques partagées par les deux doctrines.
     */
    public static function accepts(string $referential, string $origin): bool
    {
        $referential = self::sanitize($referential);
        $origin = strtolower(trim($origin));

        if ($origin !== self::US && $origin !== self::FR) {
            return true;
        }
        if ($referential === self::BOTH) {
            return true;
        }

        return $referential === $origin;
    }

    /**
     * Filtre une liste d’entrées de catalogue sur leur clé `origin`.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public static function filter(array $entries, string $referential): array
    {
        $kept = [];
        foreach ($entries as $entry) {
            if (self::accepts($referential, (string) ($entry['origin'] ?? ''))) {
                $kept[] = $entry;
            }
        }

        return array_values($kept);
    }
}
