<?php

declare(strict_types=1);

/**
 * Presets métier pour le dossier SSE (création rapide identité / pièce).
 * Libellés destinés à l’opérateur — pas de jargon technique. Le champ « circumstances » d'un modèle rapide est un code
 * de contexte d'acquisition (config/sse_acquisition_contexts.php).
 */
return [
    'identity_status' => [
        ['key' => 'civil', 'label' => 'Civil', 'hint' => 'Habitant / passant'],
        ['key' => 'combattant', 'label' => 'Combattant', 'hint' => 'Armé ou affilié'],
        ['key' => 'detenu', 'label' => 'Détenu', 'hint' => 'Détention tactique'],
        ['key' => 'prioritaire', 'label' => 'Prioritaire', 'hint' => 'Intérêt élevé'],
    ],
    'identity_quick' => [
        [
            'key' => 'inconnu_terrain',
            'label' => 'Inconnu du terrain',
            'alias' => 'INCONNU',
            'status' => 'civil',
            'circumstances' => 'point_controle_tactique',
        ],
        [
            'key' => 'detenus_capture',
            'label' => 'Capture / détention tactique',
            'alias' => '',
            'status' => 'detenu',
            'circumstances' => 'capture_champ_bataille',
        ],
        [
            'key' => 'hvt_suspect',
            'label' => 'Personne d’intérêt prioritaire',
            'alias' => '',
            'status' => 'prioritaire',
            'circumstances' => 'fouille_haute_valeur',
        ],
        [
            'key' => 'guide_local',
            'label' => 'Guide / contact local',
            'alias' => '',
            'status' => 'civil',
            'circumstances' => 'engagement_chef_local',
        ],
    ],
    'evidence' => [
        ['key' => 'phone', 'label' => 'Téléphone saisi', 'caption' => 'Appareil récupéré sur l’objectif (CELLEX)'],
        ['key' => 'id_doc', 'label' => 'Pièce d’identité', 'caption' => 'Document d’identité photographié ou saisi'],
        ['key' => 'weapon', 'label' => 'Armement', 'caption' => 'Arme ou munitions constatées'],
        ['key' => 'photo_face', 'label' => 'Photo faciale', 'caption' => 'Cliché visage pour identification'],
        ['key' => 'fingerprint', 'label' => 'Empreintes', 'caption' => 'Relevé biométrique terrain'],
        ['key' => 'usb', 'label' => 'Support numérique', 'caption' => 'Clé USB / carte / disque'],
        ['key' => 'radio', 'label' => 'Radio / satcom', 'caption' => 'Émetteur ou carnet de fréquences'],
        ['key' => 'document', 'label' => 'Document papier', 'caption' => 'Note, plan, carnet'],
        ['key' => 'vehicle', 'label' => 'Véhicule / plaque', 'caption' => 'Repère véhicule'],
        ['key' => 'other', 'label' => 'Autre pièce', 'caption' => ''],
    ],
];
