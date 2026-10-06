/*
    Remontée des escouades et équipes de feu vers Athena (COMSPEC Link, commande Squad.Sync, POST /api/atak/squads/sync).
    Un seul téléphone par groupe envoie : le chef s'il est joueur, sinon le premier joueur du groupe.
    Envoi quand la composition change (au plus toutes les 20 s) et au moins toutes les 5 min.
    Params : [forcer (false) : envoyer même si rien n'a changé et même si je ne suis pas le rapporteur]
    État : missionNamespace COMSPEC_ATAK_SquadSyncLast = [heure, "OK" | "ERREUR …" | "SANS LIAISON", nb d'équipes].
*/
params [["_force", false]];
if (!hasInterface || {isNull player}) exitWith { false };
private _g = group player;
private _players = (units _g) select { isPlayer _x };
private _reporter = [_players param [0, objNull], leader _g] select (isPlayer leader _g);
if (!_force && {_reporter isNotEqualTo player}) exitWith { false };
private _snap = [_g] call comspec_atak_native_fnc_squadSnapshot;
private _sig = str [_snap get "squad", (_snap get "teams") apply { [_x get "name", _x get "color", _x get "icon", _x get "description", (_x get "members") apply { [_x get "uid", _x get "role", _x get "role_pref"] }] }, (_snap get "unassigned") apply { [_x get "uid", _x get "role", _x get "role_pref"] }, count (_snap get "custom_roles")];
(missionNamespace getVariable ["COMSPEC_ATAK_SquadSyncSig", ["", -1e9]]) params ["_last", "_at"];
private _age = diag_tickTime - _at;
if (!_force && {(_sig isEqualTo _last && {_age < 300}) || {_age < 20}}) exitWith { false };
missionNamespace setVariable ["COMSPEC_ATAK_SquadSyncSig", [_sig, diag_tickTime]];
private _n = count (_snap get "teams");
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    missionNamespace setVariable ["COMSPEC_ATAK_SquadSyncLast", [dayTime, "SANS LIAISON", _n]];
    false
};
private _json = [_snap] call comspec_atak_native_fnc_json;
[_json, _n] spawn {
    params ["_json", "_n"];
    private _r = "COMSPECExtension" callExtension ["Squad.Sync", [_json]];
    if (_r isEqualType []) then { _r = _r param [0, ""]; };
    // « OK|queued » : envoi en file. Vide : COMSPEC Link plus ancien que 2.0.63 (commande inconnue).
    private _state = switch (true) do {
        case ((_r select [0, 3]) isEqualTo "OK|"): { "OK" };
        case (_r isEqualTo ""): { "COMSPEC Link 2.0.63 requis" };
        case ((_r find "unauthorized") >= 0): { "NON CONNECTÉ À ATHENA" };
        default { "ERREUR " + (_r select [4]) };
    };
    missionNamespace setVariable ["COMSPEC_ATAK_SquadSyncLast", [dayTime, _state, _n]];
    ["INFO", "SQUAD", format ["Squad.Sync %1 équipe(s) : %2", _n, _state]] call comspec_atak_native_fnc_log;
};
true
