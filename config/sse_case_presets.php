<?php

declare(strict_types=1);

/**
 * Presets métier pour le dossier SSE (création rapide identité / pièce).
 * Libellés destinés à l’opérateur — pas de jargon technique.
 */
return [
    'identity_status' => [
        ['key' => 'civil', 'label' => 'Civil', 'hint' => 'Habitant / passant'],
        ['key' => 'combattant', 'label' => 'Combattant', 'hint' => 'Armé ou affilié'],
        ['key' => 'detenu', 'label' => 'Détenu', 'hint' => 'Sous garde'],
        ['key' => 'prioritaire', 'label' => 'Prioritaire', 'hint' => 'Intérêt élevé'],
    ],
    'identity_quick' => [
        [
            'key' => 'inconnu_terrain',
            'label' => 'Inconnu du terrain',
            'alias' => 'INCONNU',
            'status' => 'civil',
            'circumstances' => 'Contrôle / contact terrain — identité à confirmer.',
        ],
        [
            'key' => 'detenus_capture',
            'label' => 'Capture / détention',
            'alias' => '',
            'status' => 'detenu',
            'circumstances' => 'Personne placée sous garde après intervention.',
        ],
        [
            'key' => 'hvt_suspect',
            'label' => 'Suspect prioritaire',
            'alias' => '',
            'status' => 'prioritaire',
            'circumstances' => 'Signalement prioritaire — à croiser avec les listes.',
        ],
        [
            'key' => 'guide_local',
            'label' => 'Guide / contact local',
            'alias' => '',
            'status' => 'civil',
            'circumstances' => 'Interlocuteur local — rôle et fiabilité à préciser.',
        ],
    ],
    'evidence' => [
        ['key' => 'phone', 'label' => 'Téléphone saisi', 'caption' => 'Appareil récupéré sur le terrain'],
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
