/*
    Module « Poser un objet de renseignement » (nouveau, V0.8).
    Crée un objet vanilla (ordinateur, téléphone, dossier, radio, caisse…) à la
    position du module et le rend exploitable SSE. L'objet est ajouté aux objets
    éditables des Zeus.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_class", "_profile", "_complexity", "_generateNow"];

    private _types = createHashMapFromArray [
        ["land_laptop_unfolded_f", "COMPUTER"],
        ["land_mobilephone_smart_f", "PHONE"],
        ["land_mobilephone_old_f", "PHONE"],
        ["land_satellitephone_f", "PHONE"],
        ["land_portablelongrangeradio_f", "RADIO"],
        ["land_file1_f", "DOCUMENT"],
        ["land_filephotos_f", "DOCUMENT"],
        ["land_suitcase_f", "CONTAINER"],
        ["box_fia_wps_f", "CONTAINER"]
    ];

    if !(isClass (configFile >> "CfgVehicles" >> _class)) exitWith {
        [format ["Objet introuvable : %1", _class], "error"] call comspec_sse_fnc_zeusNotify;
    };

    private _obj = createVehicle [_class, _pos, [], 0, "CAN_COLLIDE"];
    if (isNull _obj) exitWith {
        [format ["Création impossible : %1", _class], "error"] call comspec_sse_fnc_zeusNotify;
    };
    _obj setPosATL _pos;

    private _type = _types getOrDefault [toLower _class, "OBJECT"];
    _obj setVariable ["comspec_sse_forcedType", _type, true];
    [_obj, _type, _profile, _complexity] call comspec_sse_fnc_makeSearchable;

    // Éditable par tous les Zeus (commande serveur).
    {
        [_x, [[_obj], true]] remoteExecCall ["addCuratorEditableObjects", 2];
    } forEach allCurators;

    private _delay = 1;
    if (_generateNow) then {
        [[_obj], _profile, _complexity] call comspec_sse_fnc_zeusGenerateTargets;
        _delay = 2;
    } else {
        [[_obj], _delay] call comspec_sse_fnc_zeusBroadcastEnabled;
    };

    [format [
        "%1 posé (%2) — exploitable SSE\n%3",
        getText (configFile >> "CfgVehicles" >> _class >> "displayName"),
        _type,
        if (_generateNow) then { "Contenu généré maintenant." } else { "Contenu généré au premier examen." }
    ]] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _class = _logic getVariable ["ObjectClass", "Land_Laptop_unfolded_F"];
private _profile = _logic getVariable ["Profile", "INSURGENT"];
private _complexity = _logic getVariable ["Complexity", "STANDARD"];
private _now = _logic getVariable ["GenerateNow", false];

if (_ctx get "zeus") then {
    [
        "Poser un objet de renseignement",
        [
            ["COMBO", "Objet", "Objet vanilla créé à la position du module.", ["evidence", _class] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Profil", "Profil narratif du contenu.", ["profile", _profile] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Richesse", "Quantité d'indices.", ["complexity", _complexity] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Générer le contenu maintenant", "Décoché : le contenu est généré au premier examen (plus léger).", _now]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply],
        format ["Position : grille %1.", mapGridPosition _pos]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _class, _profile, _complexity, _now] call _apply;
};

deleteVehicle _logic;
true
