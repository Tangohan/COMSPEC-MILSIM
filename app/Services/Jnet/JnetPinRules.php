<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Règles pures des éléments épinglés de l'espace commun JNET (testées sans base).
 *
 * Épingler ne donne jamais de droit : une épingle est un simple renvoi (type + identifiant) que le serveur revérifie, à
 * chaque affichage, avec les droits du lecteur. Un élément que le lecteur ne voit pas s'affiche « Élément masqué », sans
 * titre ni type.
 */
final class JnetPinRules
{
    public const SCOPE = 'commun';

    /** Plafond d'épingles par communauté. */
    public const MAX = 25;

    /** Types épinglables : échange (annonce, ordre du jour), renseignement SSE, cible, unité, page d'unité. */
    public const TYPES = [
        'echange' => 'Annonce',
        'renseignement' => 'Renseignement',
        'cible' => 'Cible',
        'unite' => 'Unité',
        'page' => 'Page',
    ];

    /** Salon qui porte l'épingle en tête de liste. */
    private const SALON_OF = [
        'echange' => 'annonces',
        'renseignement' => 'renseignements',
        'cible' => 'cibles',
        'unite' => 'voie',
        'page' => 'taches',
    ];

    public static function salonOf(string $type): string
    {
        return self::SALON_OF[$type] ?? 'epingles';
    }

    /**
     * Identifiant valide pour ce type : entier pour la plupart ; « di-12 » ou « wl-5 » pour une cible ; « ev-3 » (événement)
     * ou « fn-8 » (fiche terrain) pour un renseignement.
     */
    public static function validId(string $type, string $id): bool
    {
        if (!isset(self::TYPES[$type])) {
            return false;
        }

        return match ($type) {
            'cible' => preg_match('/^(di|wl)-[1-9]\d{0,9}$/', $id) === 1,
            'renseignement' => preg_match('/^(ev|fn)-[1-9]\d{0,9}$/', $id) === 1,
            default => preg_match('/^[1-9]\d{0,9}$/', $id) === 1,
        };
    }

    /**
     * « cible:di-2 » devient ['type' => 'cible', 'id' => 'di-2'], ou null si la référence n'est pas valide.
     *
     * @return array{type: string, id: string}|null
     */
    public static function parseRef(string $ref): ?array
    {
        $parts = explode(':', trim($ref), 2);

        return count($parts) === 2 && self::validId($parts[0], $parts[1]) ? ['type' => $parts[0], 'id' => $parts[1]] : null;
    }

    public static function ref(string $type, string $id): string
    {
        return $type . ':' . $id;
    }

    /**
     * Droit d'épingler, de désépingler et d'ordonner : celui de publier au niveau de l'organisation.
     */
    public static function canManage(bool $canPublishInOrganization): bool
    {
        return $canPublishInOrganization;
    }

    /**
     * Ordre demandé (glisser-déposer) : doit reprendre exactement les épingles existantes, sans doublon ni ajout.
     *
     * @param list<int> $current identifiants dans l'ordre actuel
     * @param list<mixed> $wanted
     * @return list<int>|null le nouvel ordre, ou null s'il ne correspond pas
     */
    public static function reorder(array $current, array $wanted): ?array
    {
        $wanted = array_values(array_map('intval', $wanted));
        if (count($wanted) !== count($current) || count(array_unique($wanted)) !== count($wanted)) {
            return null;
        }
        $a = $current;
        $b = $wanted;
        sort($a);
        sort($b);

        return $a === $b ? $wanted : null;
    }

    /**
     * Échange de place avec la voisine ($direction -1 vers le haut, 1 vers le bas). Sans voisine : ordre inchangé.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public static function swap(array $ids, int $id, int $direction): array
    {
        $i = array_search($id, $ids, true);
        if ($i === false) {
            return $ids;
        }
        $j = $i + ($direction < 0 ? -1 : 1);
        if (!isset($ids[$j])) {
            return $ids;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];

        return $ids;
    }
}
