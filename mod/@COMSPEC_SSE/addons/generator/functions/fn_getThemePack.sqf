/*
    Pack narratif cohérent pour un thème.
    [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack
*/
params [
    ["_theme", "fuel_delivery", [""]],
    ["_seed", 0, [0]],
    ["_cluster", createHashMap, [createHashMap]],
    ["_pools", createHashMap, [createHashMap]]
];

private _grid = _cluster getOrDefault ["depotGrid", ""];
if (_grid isEqualTo "") then {
    private _gx = 40000 + (([_seed, "gx"] call comspec_sse_fnc_hash) mod 20000);
    private _gy = 90000 + (([_seed, "gy"] call comspec_sse_fnc_hash) mod 20000);
    _grid = format ["%1 %2", round _gx, round _gy];
};

private _codewords = ["ALPHA","BRAVO","SAND","OLIVE","NIGHT","RIVER","STONE","EAGLE"];
private _cw = [_seed, "cw", _codewords] call comspec_sse_fnc_pickFromSeed;
private _vehicle = [_seed, "veh", _pools getOrDefault ["vehicleTypes", ["camionnette"]]] call comspec_sse_fnc_pickFromSeed;
private _plate = format ["%1-%2",
    ([_seed, "plt1"] call comspec_sse_fnc_hash) mod 100,
    ([_seed, "plt2"] call comspec_sse_fnc_hash) mod 10000
];

private _pack = switch (toLower _theme) do {
    case "fuel_delivery": {
        createHashMapFromArray [
            ["label", "Livraison carburant"],
            ["documentTitle", "Facture de carburant"],
            ["summary", format ["Livraison %1 — plaque %2 — point ALPHA", _vehicle, _plate]],
            ["sms", [
                "Livraison demain après la prière.",
                format ["Le %1 passe par le point ALPHA. Grid %2.", _vehicle, _grid],
                "Paiement carburant reçu. Ne pas appeler.",
                "Changer d'itinéraire — checkpoint actif.",
                format ["Code : %1. Confirmer au dépôt.", _cw],
                format ["Citerne pleine. Plaque %1.", _plate]
            ]],
            ["locations", [
                ["Dépôt carburant", _grid],
                ["Point ALPHA", "048100 094500"],
                ["Station-relais", "047850 094300"]
            ]],
            ["intel", [
                format ["Réseau logistique carburant — code %1", _cw],
                format ["Véhicule d'intérêt : %1 (%2)", _vehicle, _plate]
            ]],
            ["computerFiles", [
                "livraisons_carburant.xls",
                "itineraires_alpha.txt",
                "paiements_cache.pdf"
            ]]
        ]
    };
    case "weapons_cache": {
        createHashMapFromArray [
            ["label", "Cache d'armes"],
            ["documentTitle", "Inventaire dépôt"],
            ["summary", format ["Cache armes — grid %1 — code %2", _grid, _cw]],
            ["sms", [
                "Le dépôt est prêt. Ne venez pas avant minuit.",
                format ["Coordonnées cache : %1", _grid],
                "Deux caisses supplémentaires ce soir.",
                "ABU YASSIN confirmera le transfert.",
                format ["Mot de passe : %1", _cw],
                "Ne photographier aucune caisse."
            ]],
            ["locations", [
                ["Cache principale", _grid],
                ["Point de transfert", "048200 094600"],
                ["Atelier", "047700 094100"]
            ]],
            ["intel", [
                "Cellule armement active",
                format ["Cache référencée %1", _grid]
            ]],
            ["computerFiles", [
                "inventaire_armes.doc",
                "photos_cache.zip",
                "contacts_fournisseurs.txt"
            ]]
        ]
    };
    case "meeting_alpha": {
        createHashMapFromArray [
            ["label", "Réunion point ALPHA"],
            ["documentTitle", "Notes de réunion"],
            ["summary", format ["Réunion ALPHA — code %1", _cw]],
            ["sms", [
                "Rendez-vous point ALPHA, 21h.",
                "Amener seulement FARID.",
                format ["Nouveau lieu si compromis : %1", _grid],
                "Pas de téléphone demain.",
                format ["Signal : %1", _cw]
            ]],
            ["locations", [["Point ALPHA", "048100 094500"], ["Lieu de repli", _grid]]],
            ["intel", ["Réunion de coordination prévue"]],
            ["computerFiles", ["ordre_du_jour.txt", "liste_presents.doc"]]
        ]
    };
    case "courier_run": {
        createHashMapFromArray [
            ["label", "Course de courrier"],
            ["documentTitle", "Bordereau de livraison"],
            ["summary", format ["Colis via %1 — plaque %2", _vehicle, _plate]],
            ["sms", [
                "Colis prêt. Même chauffeur.",
                "Ne pas ouvrir le sac.",
                format ["Point de remise %1", _grid],
                "Confirmer réception par SMS unique.",
                format ["Véhicule %1 — %2", _vehicle, _plate]
            ]],
            ["locations", [["Remise", _grid], ["Relais 1", "048050 094480"]]],
            ["intel", ["Chaîne de courriers active"]],
            ["computerFiles", ["tracking_colis.csv", "chauffeurs.txt"]]
        ]
    };
    case "finance_drop": {
        createHashMapFromArray [
            ["label", "Drop financier"],
            ["documentTitle", "Relevé de comptes"],
            ["summary", format ["Fonds — rencontre grid %1", _grid]],
            ["sms", [
                "Fonds disponibles après-midi.",
                "Utiliser le compte secondaire.",
                format ["Rencontre comptable grid %1", _grid],
                "Factures à brûler après lecture.",
                format ["Réf transfert : %1-%2", _cw, ([_seed,"fin"] call comspec_sse_fnc_hash) mod 9999]
            ]],
            ["locations", [["Changeur", _grid], ["Café comptable", "047900 094250"]]],
            ["intel", ["Circuit de financement local"]],
            ["computerFiles", ["comptes_secondaires.xls", "transferts_mois.pdf"]]
        ]
    };
    case "ied_cell": {
        createHashMapFromArray [
            ["label", "Cellule IED"],
            ["documentTitle", "Liste composants"],
            ["summary", format ["Atelier IED — %1", _grid]],
            ["sms", [
                "Composants reçus. Atelier prêt.",
                format ["Ne pas approcher grid %1 avant 22h", _grid],
                "Le technicien confirme le délai.",
                format ["Code chantier : %1", _cw],
                "Photos interdites dans l'atelier."
            ]],
            ["locations", [["Atelier", _grid], ["Point test", "048300 094700"]]],
            ["intel", ["Activité engins explosifs improvisés"]],
            ["computerFiles", ["schemas_ied.pdf", "fournisseurs_composants.txt"]]
        ]
    };
    case "safehouse": {
        createHashMapFromArray [
            ["label", "Planque"],
            ["documentTitle", "Plan annoté"],
            ["summary", format ["Safehouse %1", _grid]],
            ["sms", [
                "La maison est libre ce soir.",
                format ["Adresse grid %1 — porte arrière", _grid],
                "Changer les draps. Effacer traces.",
                format ["Hôte : code %1", _cw]
            ]],
            ["locations", [["Safehouse", _grid], ["Issue de secours", "048120 094520"]]],
            ["intel", ["Planque opérationnelle identifiée"]],
            ["computerFiles", ["plan_maison.jpg", "rotations_garde.txt"]]
        ]
    };
    case "recruitment": {
        createHashMapFromArray [
            ["label", "Recrutement"],
            ["documentTitle", "Liste de candidats"],
            ["summary", "Réseau de recrutement"],
            ["sms", [
                "Trois nouveaux intéressés.",
                "Entretien discrètement au marché.",
                format ["Lieu screening : %1", _grid],
                "Ne pas parler d'armes au premier contact."
            ]],
            ["locations", [["Marché", "047900 094200"], ["Salle entretien", _grid]]],
            ["intel", ["Pipeline de recrutement"]],
            ["computerFiles", ["candidats.doc", "propagande_scripts.txt"]]
        ]
    };
    case "smuggling": {
        createHashMapFromArray [
            ["label", "Contrebande"],
            ["documentTitle", "Manifeste de charge"],
            ["summary", format ["Trafic via %1 (%2)", _vehicle, _plate]],
            ["sms", [
                "Frontière calme cette nuit.",
                format ["Charge dans le %1 — plaque %2", _vehicle, _plate],
                format ["Point passage %1", _grid],
                format ["Signal radio : %1", _cw]
            ]],
            ["locations", [["Passage", _grid], ["Entrepôt", "048400 094800"]]],
            ["intel", ["Itinéraire de contrebande"]],
            ["computerFiles", ["routes_frontieres.kml", "manifestes.csv"]]
        ]
    };
    case "drone_ops": {
        createHashMapFromArray [
            ["label", "Ops drone"],
            ["documentTitle", "Carnet de vols"],
            ["summary", format ["Zone de vol %1", _grid]],
            ["sms", [
                "Batteries chargées.",
                format ["Zone de vol ce soir : %1", _grid],
                "Vidéo à transmettre chiffrée.",
                format ["Indicatif : %1", _cw]
            ]],
            ["locations", [["Lancement", _grid], ["Observation", "048180 094550"]]],
            ["intel", ["Capacité ISR/drone locale"]],
            ["computerFiles", ["logs_vol.csv", "cartes_zone.png", "firmware_notes.txt"]]
        ]
    };
    case "propaganda": {
        createHashMapFromArray [
            ["label", "Propagande"],
            ["documentTitle", "Script média"],
            ["summary", "Cellule média / propagande"],
            ["sms", [
                "Vidéo montée. Attendre validation.",
                "Nouveau slogan prêt.",
                format ["Studio temporaire grid %1", _grid],
                "Supprimer les rushes bruts."
            ]],
            ["locations", [["Studio", _grid], ["Point diffusion", "047950 094280"]]],
            ["intel", ["Production de contenus de propagande"]],
            ["computerFiles", ["rushes_bruts", "script_v3.doc", "comptes_diffusion.txt"]]
        ]
    };
    case "medical_logistics": {
        createHashMapFromArray [
            ["label", "Logistique médicale"],
            ["documentTitle", "Bon de livraison médicale"],
            ["summary", format ["Médicaments — %1", _grid]],
            ["sms", [
                "Caisse médicale arrivée.",
                format ["Distribuer discrètement grid %1", _grid],
                "Besoin d'antibiotiques supplémentaires.",
                "Ne pas mélanger avec le reste."
            ]],
            ["locations", [["Infirmerie clandestine", _grid], ["Dépôt med", "048060 094410"]]],
            ["intel", ["Réseau soutien médical parallèle"]],
            ["computerFiles", ["stocks_med.xls", "besoins_semaine.txt"]]
        ]
    };
    default {
        createHashMapFromArray [
            ["label", "Activité générique"],
            ["documentTitle", "Notes diverses"],
            ["summary", format ["Activité — grid %1", _grid]],
            ["sms", ["Confirmer demain.", format ["Lieu : %1", _grid], format ["Code %1", _cw]]],
            ["locations", [["Lieu principal", _grid]]],
            ["intel", ["Activité à caractériser"]],
            ["computerFiles", ["notes.txt"]]
        ]
    };
};

_pack set ["grid", _grid];
_pack set ["codeword", _cw];
_pack set ["vehicle", _vehicle];
_pack set ["plate", _plate];
_pack set ["theme", toLower _theme];
_pack
