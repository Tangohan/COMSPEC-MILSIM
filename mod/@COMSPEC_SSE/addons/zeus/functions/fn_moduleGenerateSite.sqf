/*
    Module « Créer un site SSE complet ».
    Tague les personnes, véhicules et objets du rayon comme un même réseau.
    Zeus : formulaire à la pose. Eden : attributs (ou zone du module).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_radius", "_profile", "_complexity", "_maxObjects", "_digital", "_documents", "_network"];
    private _targets = [
        _pos, _radius, _profile, _complexity,
        createHashMapFromArray [
            ["maxobjects", _maxObjects],
            ["digital", _digital],
            ["documents", _documents],
            ["network", _network]
        ]
    ] call comspec_sse_fnc_generateSite;
    if (_targets isEqualTo []) exitWith {
        [format ["Site SSE : rien d'exploitable dans un rayon de %1 m.\nPlacez d'abord personnes, véhicules ou objets.", _radius], "warn"] call comspec_sse_fnc_zeusNotify;
    };
    [_targets, (count _targets) * 0.28 + 2] call comspec_sse_fnc_zeusBroadcastEnabled;
    [format ["Site SSE en génération : %1 entité(s)\nRayon %2 m · %3 / %4", count _targets, _radius, _profile, _complexity]] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _radius = _logic getVariable ["Radius", 35];
private _area = _logic getVariable ["objectarea", []];
if (_area isEqualType [] && {count _area >= 1} && {(_area select 0) isEqualType 0} && {(_area select 0) > 0}) then {
    _radius = _area select 0;
};
private _profile = _logic getVariable ["Profile", "INSURGENT"];
private _complexity = _logic getVariable ["Complexity", "DETAILED"];
private _maxObjects = _logic getVariable ["MaxObjects", 8];

if (_ctx get "zeus") then {
    [
        "Créer un site SSE complet",
        [
            ["SLIDER", "Rayon (m)", "Tout ce qui se trouve dans ce rayon est relié au même réseau.", [10, 150, _radius, 0]],
            ["COMBO", "Type de site", "Oriente le réseau, les thèmes et les documents.", ["siteProfile", _profile] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Richesse", "Quantité d'indices générés par entité.", ["complexity", _complexity] call comspec_sse_fnc_zeusComboData],
            ["SLIDER", "Objets exploitables (max)", "Nombre maximum d'objets (caisses, sacs…) intégrés au site.", [0, 30, _maxObjects, 0]],
            ["CHECK", "Supports numériques", "Téléphones / ordinateurs sur le site.", true],
            ["CHECK", "Documents", "Documents papier liés au réseau.", true],
            ["CHECK", "Liens réseau", "Relie les personnes du site entre elles (graphe).", true]
        ],
        {
            params ["_values", "_args"];
            _args params ["_pos", "_apply"];
            ([_pos] + _values) call _apply;
        },
        [_pos, _apply],
        "Pose le site autour du module. Les éléments déjà SSE gardent leurs données."
    ] call comspec_sse_fnc_uiForm;
} else {
    [
        _pos, _radius, _profile, _complexity, _maxObjects,
        _logic getVariable ["WantDigital", true],
        _logic getVariable ["WantDocuments", true],
        _logic getVariable ["WantNetwork", true]
    ] call _apply;
};

deleteVehicle _logic;
true
