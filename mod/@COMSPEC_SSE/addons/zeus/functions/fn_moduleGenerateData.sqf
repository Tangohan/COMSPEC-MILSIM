/*
    Module Zeus — Générer profil SSE
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith { true };
if (!isServer && {!hasInterface}) exitWith { true };

private _targets = synchronizedObjects _logic;
if (count _targets == 0) then {
    private _attached = _logic getVariable ["bis_fnc_curatorAttachObject_object", objNull];
    if (!isNull _attached) then { _targets = [_attached]; };
};
if (count _targets == 0 && {count _units > 0}) then { _targets = _units; };

private _profile = _logic getVariable ["Profile", "INSURGENT"];
private _complexity = _logic getVariable ["Complexity", "STANDARD"];
private _noisePct = _logic getVariable ["NoisePct", 25];

missionNamespace setVariable ["comspec_sse_noiseProbability", (_noisePct / 100) max 0 min 1];

if (count _targets == 0) exitWith {
    if (hasInterface) then { hint "Module SSE : aucune cible synchronisée."; };
    deleteVehicle _logic;
    false
};

// Ouvrir dialogue de confirmation côté Zeus si interface
if (hasInterface) then {
    missionNamespace setVariable ["comspec_sse_zeusPendingTargets", _targets];
    missionNamespace setVariable ["comspec_sse_zeusPendingProfile", _profile];
    missionNamespace setVariable ["comspec_sse_zeusPendingComplexity", _complexity];
    [_targets, _profile, _complexity] call comspec_sse_fnc_openGenerateDialog;
} else {
    {
        [_x, _profile, _complexity, "ZEUS"] call comspec_sse_fnc_generateData;
    } forEach _targets;
};

deleteVehicle _logic;
true
