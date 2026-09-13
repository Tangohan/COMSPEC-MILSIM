/*
    Indicatif tactique du joueur local (colonne Indicatif des Effectifs).
    Jamais le nom de communauté, jamais le groupe Arma.
    Params: [_allowEmpty (défaut false — « Operateur » si rien)]
*/
params [["_allowEmpty", false, [true]]];

if (!hasInterface) exitWith { if (_allowEmpty) then { "" } else { "Operateur" } };

private _fncTake = {
    params ["_raw"];
    if (!(_raw isEqualType "")) then { _raw = str _raw; };
    _raw = trim _raw;
    if ([_raw] call comspec_overwatch_connect_fnc_isUsableCallsign) then { _raw } else { "" };
};

private _cs = [missionNamespace getVariable ["COMSPEC_Callsign", ""]] call _fncTake;
if (_cs isEqualTo "") then {
    _cs = [profileNamespace getVariable ["COMSPEC_Callsign", ""]] call _fncTake;
};
if (_cs isEqualTo "") then {
    _cs = [missionNamespace getVariable ["comspec_profile_callsign", ""]] call _fncTake;
};

if (_cs isEqualTo "") then {
    if (_allowEmpty) then { "" } else { "Operateur" }
} else {
    _cs
}
