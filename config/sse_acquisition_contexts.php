<?php

declare(strict_types=1);

/**
 * Contextes d'acquisition du portail SSE : COMMENT une information, une photo, un papier, une empreinte ou un support
 * a été obtenu (colonne sse_persons.circumstances et, côté jeu, le champ « Circonstances » de la fiche personne).
 *
 * Jeu par défaut « us_sof » : forces spéciales américaines en opération à l'étranger (vocabulaire USSOCOM, JP 3-31,
 * ATP 3-18.x : action directe, exploitation de site sensible, TCP/VCP, TQ, KLE, DOMEX/CELLEX, EPW, BATS/HIIDE/SEEK).
 * Jeu « police » : les quatre choix d'origine, gardés tels quels pour les communautés qui jouent des policiers.
 *
 * Les codes sont stables : ils sont stockés tels quels en base et envoyés par le mod. Ne jamais en renommer ni en
 * supprimer un ; l'ancien code reste lisible grâce à 'legacy' (code ancien => code actuel, pour l'AFFICHAGE seulement).
 * Pour un nouveau contexte : un nouveau code (minuscules, chiffres, souligné, 40 caractères au plus), FR et EN.
 */
return [
    'default_set' => 'us_sof',

    'groups' => [
        'action_objectif' => ['fr' => 'Action sur objectif', 'en' => 'Action on the objective'],
        'controle_fouille' => ['fr' => 'Contrôle et fouille', 'en' => 'Control and search'],
        'renseignement_humain' => ['fr' => 'Renseignement humain', 'en' => 'Human intelligence'],
        'biometrie_detention' => ['fr' => 'Biométrie et détention', 'en' => 'Biometrics and detention'],
        'exploitation_materiel' => ['fr' => 'Exploitation de matériel', 'en' => 'Materiel exploitation'],
        'reconnaissance_recuperation' => ['fr' => 'Reconnaissance et récupération', 'en' => 'Reconnaissance and recovery'],
        'autre' => ['fr' => 'Autre', 'en' => 'Other'],
        // Groupe unique du jeu « police ».
        'police' => ['fr' => 'Police et gendarmerie', 'en' => 'Police and gendarmerie'],
    ],

    'sets' => [
        'us_sof' => [
            'fr' => 'Forces spéciales US (défaut)',
            'en' => 'US special forces (default)',
        ],
        'police' => [
            'fr' => 'Police et gendarmerie (ancien)',
            'en' => 'Police and gendarmerie (legacy)',
        ],
    ],

    'contexts' => [
        // Action sur objectif
        [
            'code' => 'raid_action_directe', 'group' => 'action_objectif',
            'fr' => 'Action directe : assaut sur l’objectif',
            'en' => 'Direct action: assault on the objective',
            'desc_fr' => 'Pris pendant ou juste après l’assaut d’un raid (DA), avant la fouille méthodique.',
            'desc_en' => 'Obtained during or right after the assault of a raid (DA), before the methodical search.',
        ],
        [
            'code' => 'exploitation_site_sensible', 'group' => 'action_objectif',
            'fr' => 'Exploitation de site sensible (SSE)',
            'en' => 'Sensitive site exploitation (SSE)',
            'desc_fr' => 'Fouille méthodique de l’objectif une fois sécurisé : personnes, papiers, supports, matériel.',
            'desc_en' => 'Methodical search of the objective once secured: people, papers, media, materiel.',
        ],
        [
            'code' => 'interdiction_embuscade', 'group' => 'action_objectif',
            'fr' => 'Interdiction ou embuscade',
            'en' => 'Interdiction or ambush',
            'desc_fr' => 'Pris sur un convoi, un véhicule ou des personnes interceptés sur un axe.',
            'desc_en' => 'Obtained from a convoy, vehicle or people intercepted on a route.',
        ],

        // Contrôle et fouille
        [
            'code' => 'fouille_haute_valeur', 'group' => 'controle_fouille',
            'fr' => 'Fouille d’une cible à haute valeur (HVT / HVI) à la capture',
            'en' => 'Search of a high-value target (HVT / HVI) at capture',
            'desc_fr' => 'Fouille de la personne ciblée et de ses effets au moment de sa capture.',
            'desc_en' => 'Search of the targeted person and belongings at the time of capture.',
        ],
        [
            'code' => 'cordon_fouille', 'group' => 'controle_fouille',
            'fr' => 'Cordon et fouille (cordon and search)',
            'en' => 'Cordon and search',
            'desc_fr' => 'Quartier ou village bouclé, habitants identifiés puis fouillés maison par maison.',
            'desc_en' => 'Neighbourhood or village sealed, inhabitants identified then searched house by house.',
        ],
        [
            'code' => 'point_controle_tactique', 'group' => 'controle_fouille',
            'fr' => 'Point de contrôle tactique (TCP / VCP)',
            'en' => 'Tactical checkpoint (TCP / VCP)',
            'desc_fr' => 'Contrôle de personnes et de véhicules à un point de passage tenu par l’équipe.',
            'desc_en' => 'People and vehicles checked at a crossing point held by the team.',
        ],
        [
            'code' => 'fouille_patrouille', 'group' => 'controle_fouille',
            'fr' => 'Fouille opportuniste en patrouille (hasty search)',
            'en' => 'Hasty search on patrol',
            'desc_fr' => 'Véhicule, bâtiment ou personne fouillé rapidement au cours d’une patrouille.',
            'desc_en' => 'Vehicle, building or person searched quickly during a patrol.',
        ],

        // Renseignement humain
        [
            'code' => 'interrogatoire_tactique', 'group' => 'renseignement_humain',
            'fr' => 'Interrogatoire tactique sur le terrain (TQ)',
            'en' => 'Tactical questioning in the field (TQ)',
            'desc_fr' => 'Questions courtes posées sur place, avant toute remise à un centre de détention.',
            'desc_en' => 'Short questions asked on the spot, before any handover to a detention facility.',
        ],
        [
            'code' => 'engagement_chef_local', 'group' => 'renseignement_humain',
            'fr' => 'Rencontre avec un chef local (KLE)',
            'en' => 'Key leader engagement (KLE)',
            'desc_fr' => 'Information donnée lors d’une rencontre avec un chef coutumier, un maire ou un responsable local.',
            'desc_en' => 'Information given during a meeting with a tribal elder, mayor or local official.',
        ],
        [
            'code' => 'source_humaine', 'group' => 'renseignement_humain',
            'fr' => 'Collecte auprès d’une source humaine (HUMINT / CI)',
            'en' => 'Collection from a human source (HUMINT / CI)',
            'desc_fr' => 'Renseignement fourni par une source suivie ou un informateur.',
            'desc_en' => 'Intelligence provided by a handled source or an informant.',
        ],

        // Biométrie et détention
        [
            'code' => 'enrolement_biometrique', 'group' => 'biometrie_detention',
            'fr' => 'Enrôlement biométrique (BATS / HIIDE / SEEK)',
            'en' => 'Biometric enrolment (BATS / HIIDE / SEEK)',
            'desc_fr' => 'Empreintes, iris et visage relevés sur un détenu ou une personne d’intérêt.',
            'desc_en' => 'Fingerprints, iris and face collected from a detainee or person of interest.',
        ],
        [
            'code' => 'capture_champ_bataille', 'group' => 'biometrie_detention',
            'fr' => 'Reddition ou capture sur le champ de bataille (EPW / détenu)',
            'en' => 'Surrender or capture on the battlefield (EPW / detainee)',
            'desc_fr' => 'Personne qui se rend ou est capturée pendant le combat.',
            'desc_en' => 'Person who surrenders or is captured during the fight.',
        ],
        [
            'code' => 'remise_force_partenaire', 'group' => 'biometrie_detention',
            'fr' => 'Remise à une force partenaire (handover)',
            'en' => 'Handover to a partner force',
            'desc_fr' => 'Constaté lors de la remise d’un détenu ou de pièces à une force partenaire ou locale.',
            'desc_en' => 'Recorded when handing a detainee or items over to a partner or local force.',
        ],

        // Exploitation de matériel
        [
            'code' => 'materiel_ennemi_capture', 'group' => 'exploitation_materiel',
            'fr' => 'Matériel ennemi capturé (CEM)',
            'en' => 'Captured enemy materiel (CEM)',
            'desc_fr' => 'Armement, équipement, téléphone ou radio pris à l’adversaire.',
            'desc_en' => 'Weapons, equipment, phone or radio taken from the adversary.',
        ],
        [
            'code' => 'document_media_capture', 'group' => 'exploitation_materiel',
            'fr' => 'Document ou média capturé (DOMEX / CELLEX)',
            'en' => 'Captured document or media (DOMEX / CELLEX)',
            'desc_fr' => 'Papier, carnet, clé USB, disque ou téléphone exploité pour son contenu.',
            'desc_en' => 'Paper, notebook, USB key, disk or phone exploited for its content.',
        ],

        // Reconnaissance et récupération
        [
            'code' => 'reconnaissance_speciale', 'group' => 'reconnaissance_recuperation',
            'fr' => 'Reconnaissance spéciale (SR) et observation',
            'en' => 'Special reconnaissance (SR) and observation',
            'desc_fr' => 'Photo ou fait relevé à distance, sans contact avec la personne.',
            'desc_en' => 'Photo or fact collected from a distance, without contact with the person.',
        ],
        [
            'code' => 'recuperation_personnel', 'group' => 'reconnaissance_recuperation',
            'fr' => 'Récupération de personnel (sauvetage d’otage, évacuation sanitaire)',
            'en' => 'Personnel recovery (hostage rescue, medical evacuation)',
            'desc_fr' => 'Relevé pendant le sauvetage d’un otage ou l’évacuation d’un blessé.',
            'desc_en' => 'Recorded during a hostage rescue or the evacuation of a casualty.',
        ],

        // Autre
        [
            'code' => 'autre', 'group' => 'autre',
            'fr' => 'Autre (précisez)',
            'en' => 'Other (specify)',
            'desc_fr' => 'Contexte qui ne rentre dans aucune autre case : le préciser dans les déclarations.',
            'desc_en' => 'Context that fits no other box: specify it in the statements.',
        ],
    ],

    /**
     * Ancien code => code actuel, pour l'AFFICHAGE : le code stocké n'est jamais réécrit.
     * Le libellé ancien reste visible : « Point de contrôle tactique (ancien : Contrôle) ».
     */
    'legacy' => [
        'controle' => ['to' => 'point_controle_tactique', 'fr' => 'Contrôle', 'en' => 'Check'],
        'perquisition' => ['to' => 'exploitation_site_sensible', 'fr' => 'Perquisition', 'en' => 'Search of premises'],
        'reddition' => ['to' => 'capture_champ_bataille', 'fr' => 'Reddition', 'en' => 'Surrender'],
        'autre' => ['to' => 'autre', 'fr' => 'Autre', 'en' => 'Other'],
    ],

    /** Jeu « police » : les quatre choix d'origine, dans l'ordre d'origine. */
    'police_codes' => ['controle', 'perquisition', 'reddition', 'autre'],
];
