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

player setVariable ["COMSPEC_TerminalUid", _terminal, true];
player setVariable ["COMSPEC_AtakId", _atakId, true];
player setVariable ["COMSPEC_MilitaryId", _mid, true];
player setVariable ["COMSPEC_CallsignPublic", _callsign, true];
player setVariable ["COMSPEC_CertStatus", _cert, true];
player setVariable ["COMSPEC_LinkState", _link, true];
if (_atakState isEqualType createHashMap) then {
    player setVariable ["COMSPEC_AtakState", _atakState, true];
};
// Marqueur de fraîcheur : le panneau Zeus sait ainsi si une synchro a déjà abouti.
player setVariable ["COMSPEC_AtakSyncAt", diag_tickTime, true];
