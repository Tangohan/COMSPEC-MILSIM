/*
    Reprise post-déconnexion / CTD — pattern HandleDisconnect JSOC.
    Serveur : mémorise état ATAK par UID ; client JIP : restaure via API.
*/
if (!isServer) exitWith {};

if (missionNamespace getVariable ["COMSPEC_CrashRecoveryInited", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_CrashRecoveryInited", true, true];

COMSPEC_DisconnectedAtakState = createHashMap;

addMissionEventHandler ["HandleDisconnect", {
    params ["_unit", "_id", "_uid", "_name"];
    if (_uid isEqualTo "" || {isNull _unit}) exitWith { false };

    // Indicatif publié par le client sur son unité (fn_syncPlayerAtakPublicVars) : le missionNamespace du serveur
    // donnait celui de l'hôte (ou rien en dédié) et le repli groupId (« Alpha 1-1 ») écrasait le vrai indicatif
    // au retour JIP. Vide → fn_restoreAtakSession se rabat sur le portail.
    private _callsign = _unit getVariable ["COMSPEC_CallsignPublic", ""];
    _callsign = if (_callsign isEqualType "") then { trim _callsign } else { "" };

    private _state = createHashMap;
    _state set ["callsign", _callsign];
    _state set ["atak_state", _unit getVariable ["COMSPEC_AtakState", createHashMap]];
    // Jamais « linked » au retour : aucune liaison n'existe encore, refreshLinkState recalcule l'état réel.
    _state set ["link_state", "offline"];
    _state set ["position", getPosATL _unit];
    _state set ["direction", getDir _unit];
    _state set ["time", time];

    COMSPEC_DisconnectedAtakState set [_uid, _state];
    // Plus de publicVariable : seul le serveur lit cette table (restauration JIP via remoteExec) ;
    // la diffuser envoyait tout l'état ATAK des déconnectés à chaque client à chaque départ.
    false
}];

addMissionEventHandler ["PlayerConnected", {
    params ["_id", "_uid", "_name", "_jip", "_owner"];
    if (!_jip) exitWith {};
    if (_uid isEqualTo "") exitWith {};

    private _saved = COMSPEC_DisconnectedAtakState getOrDefault [_uid, createHashMap];
    if (_saved isEqualTo createHashMap) exitWith {};

    [_uid, _saved, _owner] remoteExecCall ["comspec_overwatch_connect_fnc_restoreAtakSession", _owner];
}];

true
