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
    // Poste détruit / planté / brouillé : la file reste en place (sinon le repère bloqué partait 5 s plus tard).
    if (!isNil "comspec_overwatch_connect_fnc_canTransmit"
        && {!(([true] call comspec_overwatch_connect_fnc_canTransmit) getOrDefault ["can_transmit", true])}) exitWith { 0 };
    private _n = 0;
    // File bornée en taille (80) et en âge (30 min). Un envoi retenu (compte refusé ou non lié : fn_linkGate, plus de
    // téléphone : fn_uplinkHold) laisse le reste en file au lieu de la vider : elle repart à la reprise.
    private _keep = [];
    private _held = false;
    {
        _x params ["_name", "_body", "_del", ["_at", diag_tickTime]];
        if (_name isEqualTo "") then { continue };
        if ((diag_tickTime - _at) > 1800) then { continue };
        if (!_held && {(!isNil "comspec_overwatch_connect_fnc_linkGate" && {["blocks", "SendMarker"] call comspec_overwatch_connect_fnc_linkGate}) || {["SendMarker"] call comspec_overwatch_connect_fnc_uplinkHold}}) then { _held = true; };
        if (_held) then { _keep pushBack _x; continue };
        private _delFlag = if (_del) then { "1" } else { "0" };
        private _payload = if (_del) then { "{}" } else { _body };
        (["COMSPECExtension", "SendMarker", [_name, _payload, "1", _delFlag]] call comspec_overwatch_connect_fnc_extDirect);
        _n = _n + 1;
    } forEach _queue;
    missionNamespace setVariable ["COMSPEC_PendingMarkers", _keep, false];
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
_next pushBack [_armaName, _json, _deleted, diag_tickTime];
if ((count _next) > 80) then {
    _next = _next select [(count _next) - 80, 80];
};
missionNamespace setVariable ["COMSPEC_PendingMarkers", _next, false];
true
