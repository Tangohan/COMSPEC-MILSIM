<?php

declare(strict_types=1);

/**
 * Contrat d’échange « pack dossier SSE » (Athena ↔ IA ↔ Arma).
 * Les libellés UI restent métier ; ce fichier décrit le schéma technique.
 */
return [
    'format' => 'comspec_sse_case_bundle',
    'format_version' => 1,
    'arma_format' => 'comspec_sse_mission_pack',
    'classifications' => ['interne', 'encadrement', 'confidentiel', 'tres_restreint'],
    'case_statuses' => ['ouvert', 'en_cours', 'clos', 'archive'],
    'person_statuses' => ['civil', 'combattant', 'detenu', 'prioritaire'],
    'site_types' => ['habitation', 'depot', 'poste_ennemi', 'cache', 'vehicule', 'autre'],
    'seizure_categories' => ['arme', 'munition', 'document', 'radio', 'medical', 'numerique', 'valeur', 'autre'],
    'evidence_presets' => ['phone', 'id_doc', 'weapon', 'photo_face', 'fingerprint', 'usb', 'radio', 'document', 'vehicle', 'other'],
    'arma_profiles' => [
        'CIVILIAN', 'INSURGENT', 'MILITARY', 'COMMANDER', 'COURIER',
        'FINANCIER', 'TECHNICIAN', 'INTELLIGENCE', 'LOGISTICS', 'RANDOM',
    ],
    'arma_complexity' => ['LIGHT', 'STANDARD', 'DETAILED', 'HIGH_VALUE'],
];
