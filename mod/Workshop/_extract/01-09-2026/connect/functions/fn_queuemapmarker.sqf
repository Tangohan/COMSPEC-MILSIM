/*
    File d’attente marqueurs si Athena pas encore prêt / envoi différé.
    Params (push): [_armaName, _json, _deleted]
    Params (flush): [] — envoie tout le tampon
*/
params [
    ["_armaName", "", [""]],
    ["_json", "", [""]],
    ["_deleted", false, [true]]
];

private _queue = missionNamespace getVariable ["COMSPEC_PendingMarkers", []];
if (!(_queue isEqualType [])) then { _queue = []; };

// Mode flush
if (_armaName isEqualTo "") exitWith {
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { 0 };
    private _n = 0;
    {
        _x params ["_name", "_body", "_del"];
        if (_name isEqualTo "") then { continue };
        private _delFlag = if (_del) then { "1" } else { "0" };
        private _payload = if (_del) then { "{}" } else { _body };
        "COMSPECExtension" callExtension ["SendMarker", [_name, _payload, "1", _delFlag]];
        _n = _n + 1;
    } forEach _queue;
    missionNamespace setVariable ["COMSPEC_PendingMarkers", [], false];
    if (_n > 0) then {
        ["SendMarker", "attempt", format ["file d’attente ×%1", _n], nil, false, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        ["INFO", "Markers", format ["File d’attente marqueurs vidée (%1)", _n]] call comspec_overwatch_connect_fnc_log;
    };
    _n
};

// Mode push — dédup par nom (dernier gagne)
private _next = [];
{
    _x params ["_n"];
    if (_n isNotEqualTo _armaName) then { _next pushBack _x; };
} forEach _queue;
_next pushBack [_armaName, _json, _deleted];
if ((count _next) > 80) then {
    _next = _next select [(count _next) - 80, 80];
};
missionNamespace setVariable ["COMSPEC_PendingMarkers", _next, false];
true
