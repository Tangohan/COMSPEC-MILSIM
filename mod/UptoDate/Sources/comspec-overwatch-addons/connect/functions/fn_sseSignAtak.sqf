/*
    Signature de la fiche (bouton SIGNER de la page ENVOI) avec l'identité du joueur.
    Vaut « procès-verbal » roleplay : l'opérateur et son appareil sont scellés dans la fiche.
    Aucune saisie ni téléphone requis : l'ATAK, s'il est porté, donne son identifiant de terminal ;
    sinon c'est le SEEK lui-même. Aucune cryptographie, un identifiant et un horodatage.
    La touche SIGN de l'appareil signe ET envoie (fn_seekSend).
*/
if (!hasInterface) exitWith {};
if !(["can"] call comspec_overwatch_connect_fnc_seekBattery) exitWith { ["render"] call comspec_overwatch_connect_fnc_seekBattery; };

private _terminal = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
if ((!(_terminal isEqualType "") || {_terminal isEqualTo ""}) && {[player] call comspec_overwatch_connect_fnc_hasTerminal}) then {
    _terminal = [] call comspec_overwatch_connect_fnc_getTerminalUid;
};
if (!(_terminal isEqualType "") || {_terminal isEqualTo ""}) then {
    private _uid = getPlayerUID player;
    _terminal = format ["SEEK-%1", _uid select [((count _uid) - 6) max 0]];
};

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (!(_callsign isEqualType "") || {_callsign isEqualTo ""}) then { _callsign = groupId (group player); };

private _atakId = missionNamespace getVariable ["COMSPEC_AtakId", ""];
if (!(_atakId isEqualType "")) then { _atakId = ""; };

private _stamp = [] call comspec_overwatch_connect_fnc_formatTimestamp;
if (!(_stamp isEqualType "") || {_stamp isEqualTo ""}) then {
    private _d = date;
    _stamp = format ["%1-%2-%3 %4:%5", _d select 0, _d select 1, _d select 2, _d select 3, floor (_d select 4)];
};

uiNamespace setVariable ["COMSPEC_SsePerson_Signature", [_callsign, _terminal, _atakId, _stamp]];
[uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull], "Procès-verbal signé", _stamp] call comspec_overwatch_connect_fnc_sseTeamLog;

if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
};
