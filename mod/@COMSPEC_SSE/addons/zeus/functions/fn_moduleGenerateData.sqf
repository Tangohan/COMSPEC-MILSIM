/*
    Module « Générer un profil SSE ».
    Zeus : dialogue de génération sur la cible attachée.
    Eden : génération au lancement avec les attributs du module.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _targets = _ctx get "targets";
private _profile = _logic getVariable ["Profile", "INSURGENT"];
private _complexity = _logic getVariable ["Complexity", "STANDARD"];
private _noise = _logic getVariable ["NoisePct", 25];
if (_noise isEqualType "") then { _noise = parseNumber _noise; };

if (_targets isEqualTo []) exitWith {
    ["Générer un profil : aucune cible.\nPosez le module sur une personne ou un objet (Zeus), ou synchronisez-le (Eden).", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

if (_ctx get "zeus") then {
    [_targets, _profile, _complexity, _noise] call comspec_sse_fnc_openGenerateDialog;
} else {
    private _options = createHashMapFromArray [
        ["identity", _logic getVariable ["WantIdentity", true]],
        ["phone", _logic getVariable ["WantPhone", true]],
        ["documents", _logic getVariable ["WantDocuments", true]],
        ["bio", _logic getVariable ["WantBio", true]],
        ["network", _logic getVariable ["WantNetwork", true]],
        ["noise", _noise]
    ];
    private _n = [_targets, _profile, _complexity, _options] call comspec_sse_fnc_zeusGenerateTargets;
    [format ["Profil SSE en file sur %1 cible(s) — %2 / %3", _n, _profile, _complexity]] call comspec_sse_fnc_zeusNotify;
};

deleteVehicle _logic;
true
