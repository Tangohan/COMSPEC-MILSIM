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

comspec_sse_models_builtin = _builtins;
if (isServer) then { publicVariable "comspec_sse_models_builtin"; };

// WARN → toujours visible RPT (INFO est filtré sans comspec_sse_debug)
[format ["registerBuiltinModels: %1 modèles (force=%2)", count _builtins, _forceRebuild], "WARN"] call comspec_sse_fnc_log;
true
