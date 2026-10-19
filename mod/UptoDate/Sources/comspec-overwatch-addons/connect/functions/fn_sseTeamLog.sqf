/*
    Journal d'équipe d'un sujet SSE : ce que chacun de nos joueurs a fait sur la même personne
    (empreintes, iris, ADN, photo du visage, interrogation SEEK, signature, fiche transmise).
    Gardé sur l'unité visée (variable publique COMSPEC_SSE_TeamLog) : tous les terminaux SEEK et l'app SSE
    du téléphone le voient, hors liaison Athena comprise.

    Params : [sujet, action, détail]          → ajoute une ligne (la même action du même joueur dans la minute
                                                 remplace la précédente : une recapture ne s'empile pas)
             ["get", sujet]                    → [[heure, indicatif, uid, action, détail, temps de mission]...], plus récent en tête
    Une ligne : heure HH:MM, indicatif de l'opérateur, UID Steam, action, détail, time.
*/
params [["_a", objNull], ["_b", ""], ["_c", ""]];
if (_a isEqualType "" && {_a isEqualTo "get"}) exitWith {
    if (isNull _b) exitWith { [] };
    private _log = _b getVariable ["COMSPEC_SSE_TeamLog", []];
    if !(_log isEqualType []) then { _log = []; };
    _log
};
private _target = _a;
if (isNull _target || {!hasInterface} || {_b isEqualTo ""}) exitWith { [] };
private _callsign = if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then { [] call comspec_overwatch_connect_fnc_getCallsign } else { "" };
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then { _callsign = name player; };
private _uid = getPlayerUID player;
private _st = systemTime;
private _two = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
private _clock = format ["%1:%2", [_st select 3] call _two, [_st select 4] call _two];
private _log = _target getVariable ["COMSPEC_SSE_TeamLog", []];
if !(_log isEqualType []) then { _log = []; };
_log = +_log;
private _i = _log findIf { (_x isEqualType []) && {(_x param [2, ""]) isEqualTo _uid} && {(_x param [3, ""]) isEqualTo _b} && {(time - (_x param [5, -1e9])) < 60} };
if (_i >= 0) then { _log deleteAt _i; };
_log = [[_clock, _callsign, _uid, _b, _c, time]] + _log;
if ((count _log) > 20) then { _log = _log select [0, 20]; };
_target setVariable ["COMSPEC_SSE_TeamLog", _log, true];
_log
