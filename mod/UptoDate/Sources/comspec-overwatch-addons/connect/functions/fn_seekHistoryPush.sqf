/*
    Ajoute une interrogation SEEK au journal (téléphone BII-10).
    Params: [_target, _result, _confidence, _ref]
    _result : "none" | "possible" | "confirmed"

    Une seule ligne par sujet : interroger plusieurs fois la même personne met sa ligne à jour
    (dernier verdict, remontée en tête, compteur [10]) au lieu d'empiler des doublons dans
    le journal partagé du groupe.
*/
params [
    ["_target", objNull, [objNull]],
    ["_result", "none", [""]],
    ["_confidence", 0, [0]],
    ["_ref", "", [""]]
];

if (!hasInterface) exitWith {};

private _first = "";
private _last = "";
private _alias = "";
if (!isNull _target) then {
    _first = _target getVariable ["COMSPEC_SSE_FirstName", ""];
    _last = _target getVariable ["COMSPEC_SSE_LastName", ""];
    _alias = _target getVariable ["COMSPEC_SSE_Alias", ""];
    if (!(_first isEqualType "")) then { _first = ""; };
    if (!(_last isEqualType "")) then { _last = ""; };
    if (!(_alias isEqualType "")) then { _alias = ""; };
};
private _who = trim (format ["%1 %2", _first, _last]);
if (_who isEqualTo "") then {
    _who = if (!isNull _target) then { name _target } else { "Sujet" };
};
if (_who isEqualTo "" || {(toLower _who) in ["error: no unit", "error: no vehicle", ""]}) then {
    _who = "Sujet inconnu";
};

private _grid = if (!isNull _target) then { mapGridPosition _target } else { "—" };
private _clock = [daytime, "HH:MM"] call BIS_fnc_timeToString;
if (!(_clock isEqualType "") || {_clock isEqualTo ""}) then {
    _clock = str (round daytime);
};

private _status = switch (toLower _result) do {
    case "confirmed": { "Confirmé" };
    case "possible": { "Possible" };
    default { "Aucune correspondance" };
};

if (!(_confidence isEqualType 0)) then { _confidence = 0; };
if (!(_ref isEqualType "")) then { _ref = ""; };

// Clé du sujet : son netId (stable pendant la mission), à défaut le nom affiché.
private _key = if (isNull _target) then { "who:" + toLower _who } else { "net:" + netId _target };

private _entry = [
    diag_tickTime,
    _clock,
    _who,
    _alias,
    _status,
    round _confidence,
    _ref,
    _grid,
    toLower _result,
    _key,
    1
];

private _fnc_store = {
    params ["_arr", "_entry"];
    if (!(_arr isEqualType [])) then { _arr = []; };
    private _k = _entry select 9;
    private _i = _arr findIf { (_x isEqualType []) && {(_x param [9, ""]) isEqualTo _k} };
    private _e = +_entry;
    if (_i >= 0) then {
        _e set [10, (((_arr select _i) param [10, 1]) max 1) + 1];
        _arr = +_arr;
        _arr deleteAt _i;
    };
    _arr = [_e] + _arr;
    if ((count _arr) > 40) then { _arr = _arr select [0, 40]; };
    _arr
};

private _local = missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []];
_local = [_local, _entry] call _fnc_store;
missionNamespace setVariable ["COMSPEC_SeekQueryHistory", _local, false];

private _grp = group player;
if (!isNull _grp) then {
    private _shared = _grp getVariable ["COMSPEC_SeekQueryHistory", []];
    _shared = [_shared, _entry] call _fnc_store;
    _grp setVariable ["COMSPEC_SeekQueryHistory", _shared, true];
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_biiOnOpened") then {
    private _g = uiNamespace getVariable ["COMSPEC_ATAK_BII_group", controlNull];
    if (!isNull _g && {ctrlShown _g}) then {
        [_g, false, true, []] call comspec_overwatch_atak_athena_fnc_athena_biiOnOpened;
    };
};

_entry
