<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Graphiques tactiques sémantiques (mesures de contrôle, manœuvre, appuis).
 * Les identifiants internes restent techniques ; l’interface n’expose que les libellés.
 */
final class TacticalGraphicsCatalog
{
    /**
     * @return list<array{id: string, group: string, group_label: string, label: string, geometry: string, search: string}>
     */
    public static function all(): array
    {
        $rows = [];
        foreach (self::groups() as $groupId => $group) {
            foreach ($group['items'] as $item) {
                $rows[] = [
                    'id' => $item['id'],
                    'group' => $groupId,
                    'group_label' => $group['label'],
                    'label' => $item['label'],
                    'geometry' => $item['geometry'],
                    'search' => strtolower($item['label'] . ' ' . ($item['search'] ?? '') . ' ' . $group['label']),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, items: list<array{id: string, label: string, geometry: string, search?: string}>}>
     */
    public static function groups(): array
    {
        return [
            'maneuver' => [
                'label' => 'Manœuvre',
                'items' => [
                    ['id' => 'axis', 'label' => 'Axe', 'geometry' => 'polyline', 'search' => 'axis'],
                    ['id' => 'attack', 'label' => 'Attaque', 'geometry' => 'arrow', 'search' => 'attack'],
                    ['id' => 'sbf', 'label' => 'Appui par le feu', 'geometry' => 'area', 'search' => 'support by fire sbf'],
                    ['id' => 'ambush', 'label' => 'Embuscade', 'geometry' => 'area', 'search' => 'ambush'],
                    ['id' => 'objective', 'label' => 'Objectif', 'geometry' => 'area', 'search' => 'obj objective lion'],
                    ['id' => 'phase_line', 'label' => 'Ligne de phase', 'geometry' => 'polyline', 'search' => 'pl phase line'],
                    ['id' => 'route', 'label' => 'Itinéraire', 'geometry' => 'polyline', 'search' => 'route'],
                    ['id' => 'assembly', 'label' => 'Zone de rassemblement', 'geometry' => 'area', 'search' => 'aa assembly'],
                    ['id' => 'infiltration', 'label' => 'Infiltration', 'geometry' => 'polyline', 'search' => 'infil'],
                ],
            ],
            'fire_support' => [
                'label' => 'Appuis-feux',
                'items' => [
                    ['id' => 'fpf', 'label' => 'Feu de protection final', 'geometry' => 'line', 'search' => 'fpf'],
                    ['id' => 'tgt', 'label' => 'Objectif feu', 'geometry' => 'point', 'search' => 'tgt target'],
                    ['id' => 'nfa', 'label' => 'Zone interdite aux feux', 'geometry' => 'area', 'search' => 'nfa'],
                    ['id' => 'cfl', 'label' => 'Ligne de coordination des feux', 'geometry' => 'polyline', 'search' => 'cfl'],
                    ['id' => 'fscl', 'label' => 'Limite d’appui-feu', 'geometry' => 'polyline', 'search' => 'fscl'],
                ],
            ],
            'control' => [
                'label' => 'Mesures de contrôle',
                'items' => [
                    ['id' => 'boundary', 'label' => 'Limite', 'geometry' => 'polyline', 'search' => 'boundary'],
                    ['id' => 'loa', 'label' => 'Limite d’avance', 'geometry' => 'polyline', 'search' => 'loa'],
                    ['id' => 'ld', 'label' => 'Ligne de départ', 'geometry' => 'polyline', 'search' => 'ld'],
                    ['id' => 'checkpoint', 'label' => 'Point de contrôle', 'geometry' => 'point', 'search' => 'cp'],
                    ['id' => 'contact_point', 'label' => 'Point de contact', 'geometry' => 'point', 'search' => 'contact'],
                ],
            ],
            'mobility' => [
                'label' => 'Mobilité et contre-mobilité',
                'items' => [
                    ['id' => 'breach', 'label' => 'Brèche', 'geometry' => 'point', 'search' => 'breach'],
                    ['id' => 'obstacle', 'label' => 'Obstacle', 'geometry' => 'area', 'search' => 'obstacle'],
                    ['id' => 'minefield', 'label' => 'Champ de mines', 'geometry' => 'area', 'search' => 'mine'],
                ],
            ],
            'airspace' => [
                'label' => 'Espace aérien',
                'items' => [
                    ['id' => 'lz', 'label' => 'Zone de poser', 'geometry' => 'area', 'search' => 'lz falcon'],
                    ['id' => 'farp', 'label' => 'Point de ravitaillement aérien', 'geometry' => 'area', 'search' => 'farp'],
                    ['id' => 'hlz', 'label' => 'Zone d’atterrissage hélico', 'geometry' => 'area', 'search' => 'hlz'],
                    ['id' => 'no_fly', 'label' => 'Zone interdite de vol', 'geometry' => 'area', 'search' => 'nfz'],
                ],
            ],
            'logistics' => [
                'label' => 'Logistique',
                'items' => [
                    ['id' => 'cache', 'label' => 'Cache', 'geometry' => 'point', 'search' => 'cache'],
                    ['id' => 'ccp', 'label' => 'Point de rassemblement blessés', 'geometry' => 'point', 'search' => 'ccp casualty'],
                    ['id' => 'supply', 'label' => 'Point de ravitaillement', 'geometry' => 'point', 'search' => 'supply'],
                ],
            ],
            'intelligence' => [
                'label' => 'Renseignement',
                'items' => [
                    ['id' => 'nai', 'label' => 'Zone d’intérêt nommé', 'geometry' => 'area', 'search' => 'nai'],
                    ['id' => 'tai', 'label' => 'Zone d’intérêt cible', 'geometry' => 'area', 'search' => 'tai'],
                    ['id' => 'hide', 'label' => 'Planque', 'geometry' => 'point', 'search' => 'hide site'],
                    ['id' => 'obs_post', 'label' => 'Poste d’observation', 'geometry' => 'point', 'search' => 'op observation'],
                ],
            ],
            'units' => [
                'label' => 'Unités',
                'items' => [
                    ['id' => 'unit_friendly', 'label' => 'Unité amie', 'geometry' => 'point', 'search' => 'friendly unit'],
                    ['id' => 'unit_hostile', 'label' => 'Unité ennemie', 'geometry' => 'point', 'search' => 'enemy hostile'],
                    ['id' => 'unit_unknown', 'label' => 'Unité inconnue', 'geometry' => 'point', 'search' => 'unknown'],
                    ['id' => 'unit_neutral', 'label' => 'Unité neutre', 'geometry' => 'point', 'search' => 'neutral'],
                ],
            ],
            'draw' => [
                'label' => 'Dessin',
                'items' => [
                    ['id' => 'point', 'label' => 'Point', 'geometry' => 'point'],
                    ['id' => 'line', 'label' => 'Ligne', 'geometry' => 'line'],
                    ['id' => 'polyline', 'label' => 'Polyligne', 'geometry' => 'polyline'],
                    ['id' => 'polygon', 'label' => 'Polygone', 'geometry' => 'polygon'],
                    ['id' => 'rectangle', 'label' => 'Rectangle', 'geometry' => 'rectangle'],
                    ['id' => 'circle', 'label' => 'Cercle', 'geometry' => 'circle'],
                    ['id' => 'ellipse', 'label' => 'Ellipse', 'geometry' => 'ellipse'],
                    ['id' => 'arrow', 'label' => 'Flèche', 'geometry' => 'arrow'],
                    ['id' => 'text', 'label' => 'Texte', 'geometry' => 'point'],
                    ['id' => 'callout', 'label' => 'Légende', 'geometry' => 'point'],
                    ['id' => 'image', 'label' => 'Image', 'geometry' => 'rectangle'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultOverlayKinds(): array
    {
        return ['maneuver', 'fire_support', 'intelligence', 'friendly', 'enemy'];
    }
}
