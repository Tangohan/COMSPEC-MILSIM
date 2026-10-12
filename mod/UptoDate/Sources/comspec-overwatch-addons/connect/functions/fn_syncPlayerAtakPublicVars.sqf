/*
    Synchronise les identifiants ATAK / Athena sur l’unité joueur (lecture Zeus).
*/
if (!hasInterface) exitWith {};
if (isNull player) exitWith {};

private _terminal = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
if (!(_terminal isEqualType "") || {_terminal isEqualTo ""}) then {
    _terminal = [] call comspec_overwatch_connect_fnc_getTerminalUid;
};

private _atakId = missionNamespace getVariable ["COMSPEC_AtakId", ""];
private _mid = missionNamespace getVariable ["COMSPEC_MilitaryId", ""];
if (_mid isEqualTo "") then {
    _mid = profileNamespace getVariable ["COMSPEC_MilitaryId", ""];
};
private _callsign = missionNamespace getVariable ["COMSPEC_Callsign", ""];
if (_callsign isEqualTo "") then {
    _callsign = profileNamespace getVariable ["COMSPEC_Callsign", ""];
};
private _cert = missionNamespace getVariable ["COMSPEC_CertStatus", ""];
private _link = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
private _atakState = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];

// Diffusion réseau seulement si la valeur a changé : appelé à chaque envoi de position et toutes les 60 s,
// chaque setVariable public partait vers tous les clients (+ file JIP) même identique.
// Empreinte (str) gardée en local sur l'unité : une nouvelle unité (respawn) rediffuse tout ; l'empreinte évite
// le piège du hashmap AtakState partagé par référence (toujours « égal » à lui-même une fois modifié sur place).
private _changed = false;
private _fnc_pub = {
    params ["_key", "_val"];
    private _sigKey = "COMSPEC_PubSig_" + _key;
    private _sig = str _val;
    // Valeur courante comparée aussi : d'autres scripts (refreshLinkState, effets Zeus) écrivent la même clé.
    private _cur = player getVariable _key;
    if (!isNil "_cur" && {(str _cur) isEqualTo _sig} && {(player getVariable [_sigKey, ""]) isEqualTo _sig}) exitWith {};
    player setVariable [_sigKey, _sig, false];
    player setVariable [_key, _val, true];
    _changed = true;
};
["COMSPEC_TerminalUid", _terminal] call _fnc_pub;
["COMSPEC_AtakId", _atakId] call _fnc_pub;
["COMSPEC_MilitaryId", _mid] call _fnc_pub;
["COMSPEC_CallsignPublic", _callsign] call _fnc_pub;
["COMSPEC_CertStatus", _cert] call _fnc_pub;
["COMSPEC_LinkState", _link] call _fnc_pub;
if (_atakState isEqualType createHashMap) then {
    ["COMSPEC_AtakState", _atakState] call _fnc_pub;
};
// Marqueur de fraîcheur : le panneau Zeus sait ainsi si une synchro a déjà abouti.
if (_changed || {(player getVariable ["COMSPEC_AtakSyncAt", -1]) < 0}) then {
    player setVariable ["COMSPEC_AtakSyncAt", diag_tickTime, true];
};
