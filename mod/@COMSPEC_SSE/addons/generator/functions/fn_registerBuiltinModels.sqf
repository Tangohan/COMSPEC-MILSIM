/*
    Enregistre les modèles intégrés (builtins) avec IDs stables.
*/
if (!isNil "comspec_sse_models_builtin" && {count comspec_sse_models_builtin > 0}) exitWith { true };

private _builtins = [];

private _mk = {
    params ["_id", "_name", "_ov"];
    private _m = [_name, _ov, "COMSPEC"] call comspec_sse_fnc_createModel;
    _m set ["source", "BUILTIN"];
    _m set ["id", _id];
    _builtins pushBack _m;
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

comspec_sse_models_builtin = _builtins;
if (isServer) then { publicVariable "comspec_sse_models_builtin"; };

[format ["registerBuiltinModels: %1 modèles", count _builtins]] call comspec_sse_fnc_log;
true
