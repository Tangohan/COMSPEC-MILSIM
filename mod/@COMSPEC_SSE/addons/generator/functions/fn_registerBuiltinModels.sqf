/*
    Enregistre les modèles intégrés (builtins) avec IDs stables.
    [_forceRebuild] call comspec_sse_fnc_registerBuiltinModels
    - Par défaut : fusionne les IDs manquants (ne bloque plus sur un registre partiel).
    - _forceRebuild true : reconstruit entièrement la liste.
*/
params [
    ["_forceRebuild", false, [false]]
];

private _builtins = [];
if (!_forceRebuild && {!isNil "comspec_sse_models_builtin"} && {comspec_sse_models_builtin isEqualType []} && {count comspec_sse_models_builtin > 0}) then {
    _builtins = +comspec_sse_models_builtin;
};

private _mk = {
    params ["_id", "_name", "_ov"];
    private _existingIdx = _builtins findIf { (_x getOrDefault ["id", ""]) == _id };
    if (_existingIdx >= 0 && {!_forceRebuild}) exitWith {};
    private _m = [_name, _ov, "COMSPEC"] call comspec_sse_fnc_createModel;
    _m set ["source", "BUILTIN"];
    _m set ["id", _id];
    if (_existingIdx >= 0) then {
        _builtins set [_existingIdx, _m];
    } else {
        _builtins pushBack _m;
    };
};

[
    "builtin_cellule_insurgee_irak",
    "Cellule insurgée — Irak",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "DETAILED"], ["region", "IRAQ"],
        ["theme", "weapons_cache"], ["includeComputer", true], ["networkSize", 10],
        ["tags", ["irak", "cellule", "armes"]],
        ["aliasPool", ["ABU YASSIN", "ABU HAMZA", "AL SAQR"]],
        ["notes", "Cellule armement classique avec téléphone + PC"]
    ]
] call _mk;

[
    "builtin_chef_hvt",
    "Chef HVT",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "IRAQ"],
        ["theme", "meeting_alpha"], ["includeBiometrics", true], ["includeComputer", true],
        ["tags", ["hvt", "commandement"]],
        ["notes", "Cible de haute valeur — biométrie + digital riches"]
    ]
] call _mk;

[
    "builtin_reseau_courriers",
    "Réseau courriers",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "DETAILED"], ["region", "SYRIA"],
        ["theme", "courier_run"], ["includePhone", true], ["includeDocuments", true],
        ["tags", ["courrier", "logistique"]],
        ["contactPool", ["THE DRIVER", "WAREHOUSE", "ABU MARIAM", "RELAY-2"]]
    ]
] call _mk;

[
    "builtin_financier",
    "Financier",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "DETAILED"], ["region", "LEVANT"],
        ["theme", "finance_drop"], ["includeComputer", true],
        ["tags", ["finance"]],
        ["smsTemplates", [
            "Fonds disponibles après-midi.",
            "Utiliser le compte secondaire.",
            "Factures à brûler après lecture."
        ]]
    ]
] call _mk;

[
    "builtin_technicien_ied",
    "Technicien IED",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "DETAILED"], ["region", "IRAQ"],
        ["theme", "ied_cell"], ["includePhone", true], ["includeComputer", true],
        ["tags", ["ied", "technicien"]]
    ]
] call _mk;

[
    "builtin_safehouse",
    "Safehouse urbain",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "STANDARD"], ["region", "SYRIA"],
        ["theme", "safehouse"], ["includeDocuments", true],
        ["tags", ["planque", "site"]]
    ]
] call _mk;

[
    "builtin_contrebande_sahel",
    "Contrebande frontière",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "AFRICA_SAHEL"],
        ["theme", "smuggling"], ["includePhone", true],
        ["tags", ["contrebande", "sahel"]]
    ]
] call _mk;

[
    "builtin_civil_bruit",
    "Civil non pertinent (bruit)",
    createHashMapFromArray [
        ["profile", "CIVILIAN"], ["complexity", "LIGHT"], ["region", "IRAQ"],
        ["theme", "RANDOM"], ["noiseProbability", 0.7], ["falseLeadProbability", 0.1],
        ["includeBiometrics", false], ["includeComputer", false],
        ["tags", ["bruit", "civil"]],
        ["notes", "Génère surtout du bruit / vie quotidienne"]
    ]
] call _mk;

[
    "builtin_drone_isr",
    "Cellule drone / ISR",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "HIGH_VALUE"], ["region", "IRAQ"],
        ["theme", "drone_ops"], ["includeComputer", true],
        ["tags", ["drone", "isr"]]
    ]
] call _mk;

[
    "builtin_propagande",
    "Propagande / média",
    createHashMapFromArray [
        ["profile", "INTELLIGENCE"], ["complexity", "DETAILED"], ["region", "LEVANT"],
        ["theme", "propaganda"], ["includeComputer", true], ["includePhone", true],
        ["tags", ["media", "propagande"]]
    ]
] call _mk;

// —— Catalogue ère Irak 2010–2020 ——
[
    "builtin_iq_2010_2020_cache_armes",
    "Irak 2010-2020 — Cache d'armes",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "DETAILED"], ["region", "IRAQ"],
        ["theme", "weapons_cache"], ["includeComputer", false], ["networkSize", 9],
        ["noiseProbability", 0.18], ["falseLeadProbability", 0.22],
        ["tags", ["irak", "2010-2020", "cache", "armes"]],
        ["aliasPool", ["ABU YASSIN", "ABU HAMZA", "AL SAQR", "LE MAGASINIER", "BROTHER 7"]],
        ["contactPool", ["THE DRIVER", "WAREHOUSE", "ABU MARIAM", "RELAY-ANBAR", "SHADOW"]],
        ["smsTemplates", [
            "Les caisses sont au hangar OUEST.",
            "Ne déplacez rien avant la prière du soir.",
            "Checkpoint renforcé — passez par le canal.",
            "Confirmez le comptage des chargeurs."
        ]],
        ["documentTemplates", [
            "Inventaire armes — secteur Nord (brouillon)",
            "Plan manuscrit dépôt + grille",
            "Liste de contacts (prénoms seulement)",
            "Reçu carburant pickup blanc"
        ]],
        ["codewords", ["SABLE", "ORAGE", "LUNE", "PUITS"]],
        ["notes", "Modèle type Irak 2010–2020 : cellule armement, papier + SMS."]
    ]
] call _mk;

[
    "builtin_iq_2010_2020_ied",
    "Irak 2010-2020 — Cellule IED",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "HIGH_VALUE"], ["region", "IRAQ"],
        ["theme", "ied_cell"], ["includeComputer", true], ["networkSize", 7],
        ["tags", ["irak", "2010-2020", "ied"]],
        ["aliasPool", ["L INGENIEUR", "ABU FIL", "ECLAIR", "LE CHIMISTE"]],
        ["codewords", ["ECLAIR", "FIL", "CHARGE", "WADI"]],
        ["notes", "Atelier IED type période 2010–2020."]
    ]
] call _mk;

[
    "builtin_iq_2010_2020_hvt",
    "Irak 2010-2020 — Chef de secteur",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "IRAQ"],
        ["theme", "meeting_alpha"], ["includeBiometrics", true], ["includeComputer", true],
        ["networkSize", 14],
        ["tags", ["irak", "2010-2020", "hvt"]],
        ["aliasPool", ["ABU KARIM", "AL RASHID", "LE CONTREMAITRE", "EMIR NORD"]],
        ["codewords", ["ALPHA", "ORAGE", "CROISSANT", "NID"]],
        ["notes", "HVT commandement — réunion, réseau, biométrie."]
    ]
] call _mk;

[
    "builtin_iq_2010_2020_courrier",
    "Irak 2010-2020 — Courrier frontière",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "STANDARD"], ["region", "IRAQ"],
        ["theme", "courier_run"], ["networkSize", 5],
        ["noiseProbability", 0.25], ["falseLeadProbability", 0.35],
        ["tags", ["irak", "2010-2020", "courrier"]],
        ["aliasPool", ["LE CHAUFFEUR", "COLIS", "RELAIS-2", "SANDMAN"]]
    ]
] call _mk;

[
    "builtin_iq_2010_2020_financier",
    "Irak 2010-2020 — Relais financier",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "DETAILED"], ["region", "IRAQ"],
        ["theme", "finance_drop"], ["includeComputer", true], ["networkSize", 8],
        ["tags", ["irak", "2010-2020", "finance"]],
        ["aliasPool", ["LE CHANGEUR", "ABU CAISSE", "THE ACCOUNTANT"]]
    ]
] call _mk;

[
    "builtin_iq_2010_2020_safehouse",
    "Irak 2010-2020 — Planque urbaine",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "DETAILED"], ["region", "IRAQ"],
        ["theme", "safehouse"], ["includeDocuments", true], ["networkSize", 6],
        ["tags", ["irak", "2010-2020", "planque"]],
        ["aliasPool", ["NID", "MAISON BLEUE", "LE LOCATAIRE"]]
    ]
] call _mk;

// —— Catalogue ère Russie / Est 2020–2024 ——
[
    "builtin_ru_2020_2024_recon",
    "Russie 2020-2024 — Reconnaissance",
    createHashMapFromArray [
        ["profile", "INTELLIGENCE"], ["complexity", "DETAILED"], ["region", "RUSSIA"],
        ["theme", "meeting_alpha"], ["includeComputer", true], ["networkSize", 8],
        ["tags", ["russie", "2020-2024", "recon"]],
        ["aliasPool", ["SOKOL", "BERKUT", "NAVIGATOR", "TIGR-2", "VOLGA"]],
        ["contactPool", ["BASE-NORTH", "RELAY-K", "DRIVER-7", "ANALYST-M", "LOG-12"]],
        ["smsTemplates", [
            "Point d'observation tenu jusqu'à 04h.",
            "Changement de grille — utiliser la carte B.",
            "Ne répondez pas aux numéros inconnus.",
            "Photo du carrefour envoyée sur le canal secondaire."
        ]],
        ["codewords", ["SOKOL", "ZARYA", "MOST", "TUMAN"]],
        ["notes", "Cellule ISR / observation théâtre Est 2020–2024."]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_logistics",
    "Russie 2020-2024 — Logistique",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "RUSSIA"],
        ["theme", "fuel_delivery"], ["includeDocuments", true], ["networkSize", 10],
        ["tags", ["russie", "2020-2024", "logistique"]],
        ["aliasPool", ["SKLAD", "CITERNE", "KONVOI", "MEKHANIK"]],
        ["codewords", ["SKLAD", "CITERNE", "DETROUR", "NUIT"]]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_command",
    "Russie 2020-2024 — Poste de commandement",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "RUSSIA"],
        ["theme", "meeting_alpha"], ["includeBiometrics", true], ["includeComputer", true],
        ["networkSize", 12],
        ["tags", ["russie", "2020-2024", "commandement", "hvt"]],
        ["aliasPool", ["KOMANDIR", "SEVER", "ORYOL", "SHTAB"]],
        ["codewords", ["ZARYA", "ORYOL", "SHTAB", "BAGAZH"]]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_drone",
    "Russie 2020-2024 — Cellule drone",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "HIGH_VALUE"], ["region", "RUSSIA"],
        ["theme", "drone_ops"], ["includeComputer", true], ["networkSize", 6],
        ["tags", ["russie", "2020-2024", "drone", "isr"]],
        ["aliasPool", ["PILOT", "KAMERA", "BPLA", "INZHENER"]],
        ["codewords", ["BPLA", "OKNO", "KARTA", "SVYAZ"]]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_ew",
    "Russie 2020-2024 — Radio / EW",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "DETAILED"], ["region", "RUSSIA"],
        ["theme", "courier_run"], ["includeComputer", true], ["networkSize", 5],
        ["tags", ["russie", "2020-2024", "radio", "ew"]],
        ["aliasPool", ["RADIST", "SHUM", "VOLNA", "ANTENA"]],
        ["codewords", ["SHUM", "VOLNA", "TISHINA", "MOST"]]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_infoops",
    "Russie 2020-2024 — Info ops",
    createHashMapFromArray [
        ["profile", "INTELLIGENCE"], ["complexity", "DETAILED"], ["region", "RUSSIA"],
        ["theme", "propaganda"], ["includeComputer", true], ["includePhone", true],
        ["networkSize", 9],
        ["tags", ["russie", "2020-2024", "propagande"]],
        ["aliasPool", ["REDAKTOR", "KANAL", "GOLOS", "MIRROR"]],
        ["codewords", ["KANAL", "ZERKALO", "GOLOS", "EFIR"]]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_courier",
    "Russie 2020-2024 — Courrier civil",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "STANDARD"], ["region", "RUSSIA"],
        ["theme", "courier_run"], ["networkSize", 4],
        ["noiseProbability", 0.3], ["falseLeadProbability", 0.3],
        ["tags", ["russie", "2020-2024", "courrier"]],
        ["aliasPool", ["KURIER", "SUMKA", "TAKTSI"]]
    ]
] call _mk;

[
    "builtin_ru_2020_2024_civil",
    "Russie 2020-2024 — Civil couverture",
    createHashMapFromArray [
        ["profile", "CIVILIAN"], ["complexity", "LIGHT"], ["region", "RUSSIA"],
        ["theme", "RANDOM"], ["noiseProbability", 0.6], ["falseLeadProbability", 0.12],
        ["includeBiometrics", false], ["includeComputer", false], ["networkSize", 7],
        ["tags", ["russie", "2020-2024", "bruit", "civil"]],
        ["notes", "Bruit de fond théâtre Est."]
    ]
] call _mk;

// —— Kits de contenu : Sahel ——
[
    "builtin_sahel_convoi_carburant",
    "Sahel — Convoi de carburant",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "AFRICA_SAHEL"],
        ["theme", "fuel_delivery"], ["networkSize", 9],
        ["tags", ["kit", "sahel", "africa_sahel", "fuel_delivery"]],
        ["aliasPool", ["LE TRANSITAIRE", "SEYDOU DIX-ROUES", "LE COXEUR", "TONTON"]],
        ["codewords", ["HARMATTAN", "PUITS", "BAOBAB", "CITERNE"]],
        ["notes", "Citernes et pistes : factures, itinéraires et points de rendez-vous."]
    ]
] call _mk;

[
    "builtin_sahel_cellule_brousse",
    "Sahel — Cellule de brousse",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "DETAILED"], ["region", "AFRICA_SAHEL"],
        ["theme", "weapons_cache"], ["networkSize", 10],
        ["tags", ["kit", "sahel", "africa_sahel", "weapons_cache"]],
        ["aliasPool", ["LE BERGER", "FENNEC", "CHACAL", "ABOU MOUSSA"]],
        ["codewords", ["SIROCCO", "DUNE", "ACACIA", "LUNE"]],
        ["notes", "Cellule rurale : cache, motos et guetteurs de piste."]
    ]
] call _mk;

[
    "builtin_sahel_chef_secteur",
    "Sahel — Chef de secteur",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "AFRICA_SAHEL"],
        ["theme", "meeting_alpha"], ["networkSize", 8],
        ["tags", ["kit", "sahel", "africa_sahel", "meeting_alpha"]],
        ["aliasPool", ["LE VIEUX", "LE CAÏD", "EL HADJ"]],
        ["codewords", ["TENTE", "PUITS", "MIDI", "OMBRE"]],
        ["notes", "Cible de haute valeur : réunions de nuit, téléphone satellite."]
    ]
] call _mk;

[
    "builtin_sahel_passeur",
    "Sahel — Passeur des pistes",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "STANDARD"], ["region", "AFRICA_SAHEL"],
        ["theme", "smuggling"], ["networkSize", 6],
        ["tags", ["kit", "sahel", "africa_sahel", "smuggling"]],
        ["aliasPool", ["LE PASSEUR", "LE CHAMELIER", "MOUSSA LA PISTE"]],
        ["codewords", ["GAZELLE", "OUED", "NUIT", "SEL"]],
        ["notes", "Passages de frontière hors des postes, de nuit."]
    ]
] call _mk;

[
    "builtin_sahel_collecteur",
    "Sahel — Collecteur de fonds",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "DETAILED"], ["region", "AFRICA_SAHEL"],
        ["theme", "finance_drop"], ["networkSize", 7],
        ["tags", ["kit", "sahel", "africa_sahel", "finance_drop"]],
        ["aliasPool", ["LE COMMIS", "LE PRÉFET", "LE DOUANIER"]],
        ["codewords", ["MARCHÉ", "BÉTAIL", "DIX", "SAC"]],
        ["notes", "Collecte sur les marchés à bétail et les sites d'orpaillage."]
    ]
] call _mk;

[
    "builtin_sahel_civil",
    "Sahel — Civil (bruit de fond)",
    createHashMapFromArray [
        ["profile", "CIVILIAN"], ["complexity", "LIGHT"], ["region", "AFRICA_SAHEL"],
        ["theme", "RANDOM"], ["networkSize", 6],
        ["noiseProbability", 0.65], ["falseLeadProbability", 0.1], ["includeBiometrics", false], ["includeComputer", false],
        ["tags", ["kit", "sahel", "africa_sahel", "random"]],
        ["notes", "Vie quotidienne d'un village du Sahel."]
    ]
] call _mk;

// —— Kits de contenu : Syrie ——
[
    "builtin_syrie_atelier",
    "Syrie — Atelier d'engins",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "DETAILED"], ["region", "SYRIA"],
        ["theme", "ied_cell"], ["networkSize", 6],
        ["tags", ["kit", "syrie", "syria", "ied_cell"]],
        ["aliasPool", ["AL NAJJAR", "L'INGÉNIEUR", "AL USTADH"]],
        ["codewords", ["FORGE", "CUIVRE", "ATELIER", "FIL"]],
        ["notes", "Atelier en sous-sol : composants, schémas et téléphones déclencheurs."]
    ]
] call _mk;

[
    "builtin_syrie_media",
    "Syrie — Cellule média",
    createHashMapFromArray [
        ["profile", "INTELLIGENCE"], ["complexity", "DETAILED"], ["region", "SYRIA"],
        ["theme", "propaganda"], ["networkSize", 6],
        ["tags", ["kit", "syrie", "syria", "propaganda"]],
        ["aliasPool", ["AL RAQIB", "ABU NOUR", "LE MAÎTRE D'ÉCOLE"]],
        ["codewords", ["ÉCRAN", "VOIX", "ANTENNE", "AUBE"]],
        ["notes", "Montage, diffusion et comptes relais."]
    ]
] call _mk;

[
    "builtin_syrie_planque",
    "Syrie — Planque urbaine",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "STANDARD"], ["region", "SYRIA"],
        ["theme", "safehouse"], ["networkSize", 5],
        ["tags", ["kit", "syrie", "syria", "safehouse"]],
        ["aliasPool", ["LE LOCATAIRE", "ABU RAMI", "UMM KHALID"]],
        ["codewords", ["CLÉ", "ESCALIER", "TOIT", "COUR"]],
        ["notes", "Appartement de passage, rotations de garde."]
    ]
] call _mk;

// —— Kits de contenu : Levant ——
[
    "builtin_levant_changeur",
    "Levant — Bureau de change",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "HIGH_VALUE"], ["region", "LEVANT"],
        ["theme", "finance_drop"], ["networkSize", 8],
        ["tags", ["kit", "levant", "levant", "finance_drop"]],
        ["aliasPool", ["LE CHANGEUR", "AL SARRAF", "LE COUSIN"]],
        ["codewords", ["CÈDRE", "PORT", "LIVRE", "BALANCE"]],
        ["notes", "Double comptabilité, transferts fractionnés et sociétés d'import-export."]
    ]
] call _mk;

[
    "builtin_levant_association",
    "Levant — Association de façade",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "LEVANT"],
        ["theme", "medical_logistics"], ["networkSize", 8],
        ["tags", ["kit", "levant", "levant", "medical_logistics"]],
        ["aliasPool", ["LE PHARMACIEN", "AL TABIB", "UMM NOUR"]],
        ["codewords", ["COLIS", "CLINIQUE", "DON", "CAMION"]],
        ["notes", "Dépôt d'aide : bordereaux, bénévoles sincères et colis hors liste."]
    ]
] call _mk;

// —— Kits de contenu : Afghanistan ——
[
    "builtin_afg_commandant",
    "Afghanistan — Commandant de vallée",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "AFGHANISTAN"],
        ["theme", "meeting_alpha"], ["networkSize", 9],
        ["tags", ["kit", "afg", "afghanistan", "meeting_alpha"]],
        ["aliasPool", ["KOMANDAN", "HAJI SAHIB", "SHER KHAN"]],
        ["codewords", ["DARYA", "KOH", "BAGH", "SHAB"]],
        ["notes", "Chef local : choura de nuit, courriers à moto."]
    ]
] call _mk;

[
    "builtin_afg_hawala",
    "Afghanistan — Hawala du bazar",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "DETAILED"], ["region", "AFGHANISTAN"],
        ["theme", "finance_drop"], ["networkSize", 7],
        ["tags", ["kit", "afg", "afghanistan", "finance_drop"]],
        ["aliasPool", ["LE CHANGEUR", "AGHA JAN", "KAKA"]],
        ["codewords", ["SARAI", "ZAR", "DAFTAR", "MIZAN"]],
        ["notes", "Registre à deux colonnes, reçus et codes de retrait."]
    ]
] call _mk;

[
    "builtin_afg_engins",
    "Afghanistan — Poseur d'engins",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "DETAILED"], ["region", "AFGHANISTAN"],
        ["theme", "ied_cell"], ["networkSize", 6],
        ["tags", ["kit", "afg", "afghanistan", "ied_cell"]],
        ["aliasPool", ["ENGINEER", "USTAD", "BARQ"]],
        ["codewords", ["SIM", "BATTERY", "SARAK", "PUL"]],
        ["notes", "Composants, repérages de route et téléphones jetables."]
    ]
] call _mk;

[
    "builtin_afg_courrier",
    "Afghanistan — Courrier des cols",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "STANDARD"], ["region", "AFGHANISTAN"],
        ["theme", "courier_run"], ["networkSize", 5],
        ["tags", ["kit", "afg", "afghanistan", "courier_run"]],
        ["aliasPool", ["KARWAN", "LALA", "LE NEVEU"]],
        ["codewords", ["KOTAL", "CHAI", "SOBH", "ASP"]],
        ["notes", "Messages écrits portés d'une vallée à l'autre."]
    ]
] call _mk;

[
    "builtin_afg_civil",
    "Afghanistan — Villageois (bruit de fond)",
    createHashMapFromArray [
        ["profile", "CIVILIAN"], ["complexity", "LIGHT"], ["region", "AFGHANISTAN"],
        ["theme", "RANDOM"], ["networkSize", 6],
        ["noiseProbability", 0.65], ["falseLeadProbability", 0.1], ["includeBiometrics", false], ["includeComputer", false],
        ["tags", ["kit", "afg", "afghanistan", "random"]],
        ["notes", "Vie quotidienne d'un village de vallée."]
    ]
] call _mk;

// —— Kits de contenu : Ukraine ——
[
    "builtin_ukr_drone",
    "Ukraine — Équipe drone",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "HIGH_VALUE"], ["region", "UKRAINE"],
        ["theme", "drone_ops"], ["networkSize", 6],
        ["tags", ["kit", "ukr", "ukraine", "drone_ops"]],
        ["aliasPool", ["PILOT", "KAMERA", "INZHENER", "YASTREB"]],
        ["codewords", ["STEP", "ZORIA", "MAIAK", "VITER"]],
        ["notes", "Carnets de vol, vidéos et points de lancement."]
    ]
] call _mk;

[
    "builtin_ukr_logistique",
    "Ukraine — Logistique de volontaires",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "UKRAINE"],
        ["theme", "fuel_delivery"], ["networkSize", 9],
        ["tags", ["kit", "ukr", "ukraine", "fuel_delivery"]],
        ["aliasPool", ["ZAVHOZ", "VODITEL", "SKLAD"]],
        ["codewords", ["DNIPRO", "MIST", "SKLAD", "NICH"]],
        ["notes", "Fourgons, dépôts et bons de livraison."]
    ]
] call _mk;

[
    "builtin_ukr_poste",
    "Ukraine — Poste de commandement de milice",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "UKRAINE"],
        ["theme", "meeting_alpha"], ["networkSize", 8],
        ["tags", ["kit", "ukr", "ukraine", "meeting_alpha"]],
        ["aliasPool", ["KOMANDIR", "SHTAB", "BATYA"]],
        ["codewords", ["HRIM", "SOKIL", "RUBIZH", "TYSHA"]],
        ["notes", "Tableaux d'effectifs, radios et cartes annotées."]
    ]
] call _mk;

// —— Kits de contenu : Balkans ——
[
    "builtin_balkans_contrebande",
    "Balkans — Filière de contrebande",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "BALKANS"],
        ["theme", "smuggling"], ["networkSize", 9],
        ["tags", ["kit", "balkans", "balkans", "smuggling"]],
        ["aliasPool", ["GAZDA", "ŠOFER", "CARINIK", "SHOFERI"]],
        ["codewords", ["DUNAV", "MOST", "MAGLA", "KAMEN"]],
        ["notes", "Caches dans les planchers, voiture ouvreuse, postes de frontière."]
    ]
] call _mk;

[
    "builtin_balkans_faussaire",
    "Balkans — Faussaire",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "DETAILED"], ["region", "BALKANS"],
        ["theme", "smuggling"], ["networkSize", 5],
        ["tags", ["kit", "balkans", "balkans", "smuggling"]],
        ["aliasPool", ["MAJSTOR", "PROFESOR", "USTAI"]],
        ["codewords", ["PEČAT", "PAPIR", "MASTILO", "ŽIG"]],
        ["notes", "Imprimerie de quartier : tampons, papiers vierges, photos d'identité."]
    ]
] call _mk;

[
    "builtin_balkans_milice",
    "Balkans — Milice locale",
    createHashMapFromArray [
        ["profile", "MILITARY"], ["complexity", "STANDARD"], ["region", "BALKANS"],
        ["theme", "weapons_cache"], ["networkSize", 8],
        ["tags", ["kit", "balkans", "balkans", "weapons_cache"]],
        ["aliasPool", ["KAPETAN", "MAJOR", "VUK"]],
        ["codewords", ["HRAST", "STENA", "GROM", "ZORA"]],
        ["notes", "Dépôt d'armes légères et rotations de garde."]
    ]
] call _mk;

// —— Kits de contenu : Amérique latine ——
[
    "builtin_latam_patron",
    "Amérique latine — Patron de plaza",
    createHashMapFromArray [
        ["profile", "COMMANDER"], ["complexity", "HIGH_VALUE"], ["region", "LATAM"],
        ["theme", "meeting_alpha"], ["networkSize", 9],
        ["tags", ["kit", "latam", "latam", "meeting_alpha"]],
        ["aliasPool", ["EL VIEJO", "EL COMANDANTE", "DON BETO"]],
        ["codewords", ["FINCA", "GALLO", "RÍO", "NOCHE"]],
        ["notes", "Cible de haute valeur : propriété isolée, deux téléphones, garde rapprochée."]
    ]
] call _mk;

[
    "builtin_latam_laboratoire",
    "Amérique latine — Laboratoire clandestin",
    createHashMapFromArray [
        ["profile", "TECHNICIAN"], ["complexity", "DETAILED"], ["region", "LATAM"],
        ["theme", "medical_logistics"], ["networkSize", 6],
        ["tags", ["kit", "latam", "latam", "medical_logistics"]],
        ["aliasPool", ["EL DOCTOR", "EL PROFE", "LA DOCTORA"]],
        ["codewords", ["COCINA", "BIDÓN", "MONTE", "HUMO"]],
        ["notes", "Bidons, carnets de dosage et livraisons de précurseurs."]
    ]
] call _mk;

[
    "builtin_latam_comptable",
    "Amérique latine — Comptable de l'organisation",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "HIGH_VALUE"], ["region", "LATAM"],
        ["theme", "finance_drop"], ["networkSize", 7],
        ["tags", ["kit", "latam", "latam", "finance_drop"]],
        ["aliasPool", ["EL CONTADOR", "LA MADRINA", "EL PRIMO"]],
        ["codewords", ["LIBRO", "CAJA", "NÓMINA", "FACTURA"]],
        ["notes", "Livres de comptes, prête-noms et paiements en espèces."]
    ]
] call _mk;

[
    "builtin_latam_guetteur",
    "Amérique latine — Guetteur",
    createHashMapFromArray [
        ["profile", "INTELLIGENCE"], ["complexity", "LIGHT"], ["region", "LATAM"],
        ["theme", "courier_run"], ["networkSize", 5],
        ["tags", ["kit", "latam", "latam", "courier_run"]],
        ["aliasPool", ["EL HALCÓN", "EL NIÑO", "LA FLACA"]],
        ["codewords", ["PUNTO", "RADIO", "ESQUINA", "LUZ"]],
        ["notes", "Radio de poche, relevés de patrouilles et taxis complices."]
    ]
] call _mk;

[
    "builtin_latam_transporteur",
    "Amérique latine — Transporteur",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "DETAILED"], ["region", "LATAM"],
        ["theme", "smuggling"], ["networkSize", 7],
        ["tags", ["kit", "latam", "latam", "smuggling"]],
        ["aliasPool", ["EL CHOFER", "EL PILOTO", "EL MARINO"]],
        ["codewords", ["CARGA", "PISTA", "LANCHA", "CRUCE"]],
        ["notes", "Camions, pistes clandestines et lanches rapides."]
    ]
] call _mk;

// —— Kits de contenu : Asie du Sud-Est ——
[
    "builtin_asie_trafic_maritime",
    "Asie du Sud-Est — Trafic maritime",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "SE_ASIA"],
        ["theme", "smuggling"], ["networkSize", 9],
        ["tags", ["kit", "asie", "se_asia", "smuggling"]],
        ["aliasPool", ["KAPITAN", "TIMONEL", "BOSSING", "PATING"]],
        ["codewords", ["ALON", "PAROLA", "HABAGAT", "PULO"]],
        ["notes", "Bancas à moteur, transbordements de nuit et courtiers de port."]
    ]
] call _mk;

[
    "builtin_asie_capitaine",
    "Asie du Sud-Est — Capitaine de banca",
    createHashMapFromArray [
        ["profile", "COURIER"], ["complexity", "STANDARD"], ["region", "SE_ASIA"],
        ["theme", "courier_run"], ["networkSize", 5],
        ["tags", ["kit", "asie", "se_asia", "courier_run"]],
        ["aliasPool", ["KAPITAN", "MANGINGISDA", "LAWIN"]],
        ["codewords", ["DAGAT", "BITUIN", "LAMBAT", "TALA"]],
        ["notes", "Carnet de bord, GPS de poche et téléphone satellite."]
    ]
] call _mk;

// —— Kits de contenu : Corne de l'Afrique ——
[
    "builtin_corne_boutre",
    "Corne de l'Afrique — Boutre de contrebande",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "HORN_AFRICA"],
        ["theme", "smuggling"], ["networkSize", 8],
        ["tags", ["kit", "corne", "horn_africa", "smuggling"]],
        ["aliasPool", ["NAAKHUUDE", "KABTAN", "BADMAAX"]],
        ["codewords", ["BAD", "DEKED", "DUUFAAN", "XEEB"]],
        ["notes", "Cargaisons déclarées en poisson séché, balise coupée au large."]
    ]
] call _mk;

[
    "builtin_corne_transfert",
    "Corne de l'Afrique — Agent de transfert",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "DETAILED"], ["region", "HORN_AFRICA"],
        ["theme", "finance_drop"], ["networkSize", 6],
        ["tags", ["kit", "corne", "horn_africa", "finance_drop"]],
        ["aliasPool", ["LE COMPTABLE", "LE NÉGOCIANT", "MACALLIN"]],
        ["codewords", ["SUUQ", "BUUG", "LACAG", "FURE"]],
        ["notes", "Transferts de la diaspora, reçus et carnets à deux colonnes."]
    ]
] call _mk;

// —— Kits de contenu : Yémen ——
[
    "builtin_yemen_depot",
    "Yémen — Dépôt d'armes",
    createHashMapFromArray [
        ["profile", "INSURGENT"], ["complexity", "DETAILED"], ["region", "YEMEN"],
        ["theme", "weapons_cache"], ["networkSize", 9],
        ["tags", ["kit", "yemen", "yemen", "weapons_cache"]],
        ["aliasPool", ["AL SAQR", "ABU SALEH", "AL DALIL"]],
        ["codewords", ["JABAL", "BUN", "MINA", "QASR"]],
        ["notes", "Dépôt de montagne, convois de pick-up et registres de distribution."]
    ]
] call _mk;

// —— Kits de contenu : Libye ——
[
    "builtin_libye_carburant",
    "Libye — Trafic de carburant",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "LIBYA"],
        ["theme", "fuel_delivery"], ["networkSize", 9],
        ["tags", ["kit", "libye", "libya", "fuel_delivery"]],
        ["aliasPool", ["LE CHAUFFEUR", "AL BARQ", "ABU TALAL"]],
        ["codewords", ["WAHA", "SAHIL", "NAKHLA", "RIMAL"]],
        ["notes", "Citernes, stations fantômes et factures au double."]
    ]
] call _mk;

[
    "builtin_libye_milice",
    "Libye — Milice de quartier",
    createHashMapFromArray [
        ["profile", "MILITARY"], ["complexity", "STANDARD"], ["region", "LIBYA"],
        ["theme", "meeting_alpha"], ["networkSize", 8],
        ["tags", ["kit", "libye", "libya", "meeting_alpha"]],
        ["aliasPool", ["AL NIMR", "ABU FIRAS", "AL TAWIL"]],
        ["codewords", ["SOUQ", "BAHR", "MATAR", "QALAA"]],
        ["notes", "Poste de contrôle, tableaux de garde et groupes de discussion."]
    ]
] call _mk;

// —— Kits de contenu : Organisation ——
[
    "builtin_smp_equipe",
    "Organisation — Société militaire privée",
    createHashMapFromArray [
        ["profile", "MILITARY"], ["complexity", "DETAILED"], ["region", "GENERIC"],
        ["theme", "meeting_alpha"], ["networkSize", 8],
        ["tags", ["kit", "transverse", "generic", "meeting_alpha"]],
        ["aliasPool", ["GRANIT", "KAZAK", "HUNTER", "RAVEN"]],
        ["codewords", ["CONTRAT", "BADGE", "ROTATION", "SITE"]],
        ["notes", "Équipe sous contrat : badges, rotations et itinéraires de la société."]
    ]
] call _mk;

[
    "builtin_admin_corrompue",
    "Organisation — Administration corrompue",
    createHashMapFromArray [
        ["profile", "FINANCIER"], ["complexity", "DETAILED"], ["region", "GENERIC"],
        ["theme", "finance_drop"], ["networkSize", 7],
        ["tags", ["kit", "transverse", "generic", "finance_drop"]],
        ["aliasPool", ["LE PRÉFET", "LE COMMIS", "LE COUSIN"]],
        ["codewords", ["TAMPON", "REGISTRE", "MARCHÉ", "CAHIER"]],
        ["notes", "Service des titres : registres, tampons et papiers de complaisance."]
    ]
] call _mk;

[
    "builtin_ong_facade",
    "Organisation — ONG de façade",
    createHashMapFromArray [
        ["profile", "LOGISTICS"], ["complexity", "DETAILED"], ["region", "GENERIC"],
        ["theme", "medical_logistics"], ["networkSize", 8],
        ["tags", ["kit", "transverse", "generic", "medical_logistics"]],
        ["aliasPool", ["LE DOCTEUR", "THE COURIER", "LE LOGISTICIEN"]],
        ["codewords", ["COLIS", "DON", "CONVOI", "BORDEREAU"]],
        ["notes", "Dépôt d'aide : convois, double comptabilité et bénévoles sincères."]
    ]
] call _mk;

comspec_sse_models_builtin = _builtins;
// Pas de diffusion : chaque machine construit déjà cette table au chargement (generator XEH_preInit).

// WARN → toujours visible RPT (INFO est filtré sans comspec_sse_debug)
[format ["registerBuiltinModels: %1 modèles (force=%2)", count _builtins, _forceRebuild], "WARN"] call comspec_sse_fnc_log;
true
