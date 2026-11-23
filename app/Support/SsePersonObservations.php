<?php

declare(strict_types=1);

namespace App\Support;

/** Présentation des champs transmis, après application des droits de lecture de la fiche personne. */
final class SsePersonObservations
{
    public static function rows(array $person): array
    {
        $rows = [];
        foreach ([
            'display_name' => 'Identité déclarée', 'alias' => 'Alias déclaré',
            'status_label' => 'Statut', 'sex_apparent' => 'Sexe apparent', 'age_estimated' => 'Âge estimé',
            'birth_date' => 'Date de naissance déclarée', 'birth_place' => 'Lieu de naissance déclaré',
            'nationality' => 'Nationalité déclarée', 'language_spoken' => 'Langue parlée',
            'id_document_type' => 'Type de papier constaté', 'id_document_number' => 'Numéro constaté',
            'distinguishing_marks' => 'Signes particuliers', 'affiliation' => 'Affiliation déclarée',
            'circumstances_label' => 'Circonstances', 'statements' => 'Déclarations et notes',
            'confidence_level' => 'Confiance', 'grid_reference' => 'Coordonnées de collecte',
            'location_description' => 'Lieu de collecte', 'submitter_callsign' => 'Transmis par',
            'created_at' => 'Enregistré le', 'updated_at' => 'Dernière mise à jour',
        ] as $field => $label) {
            $value = $person[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $rows[] = [$label, (string) $value];
            }
        }
        foreach (['weapons' => 'Armes relevées', 'equipment' => 'Équipement relevé'] as $field => $label) {
            $values = array_filter((array) ($person[$field] ?? []), 'is_scalar');
            if ($values !== []) {
                $rows[] = [$label, implode(' · ', array_map('strval', $values))];
            }
        }

        return $rows;
    }
}
