/*
    Signature de la fiche par l’ATAK de l’opérateur.
    Vaut « procès-verbal » roleplay : l’identité de l’appareil et son porteur sont
    scellés dans la fiche. Aucune cryptographie — un identifiant d’appareil et un
    horodatage, comme le reste de la liaison.
*/
if (!hasInterface) exitWith {};

if (!([player] call comspec_overwatch_connect_fnc_hasTerminal)) exitWith {
    ["Aucun ATAK en votre possession — signature impossible.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _terminal = missionNamespace getVariable ["COMSPEC_TerminalUid", ""];
if (!(_terminal isEqualType "") || {_terminal isEqualTo ""}) then {
    _terminal = [] call comspec_overwatch_connect_fnc_getTerminalUid;
};

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = groupId (group player); };

private _atakId = missionNamespace getVariable ["COMSPEC_AtakId", ""];
if (!(_atakId isEqualType "")) then { _atakId = ""; };

private _stamp = [] call comspec_overwatch_connect_fnc_formatTimestamp;
if (!(_stamp isEqualType "") || {_stamp isEqualTo ""}) then {
    private _d = date;
    _stamp = format ["%1-%2-%3 %4:%5", _d select 0, _d select 1, _d select 2, _d select 3, floor (_d select 4)];
};

uiNamespace setVariable ["COMSPEC_SsePerson_Signature", [_callsign, _terminal, _atakId, _stamp]];

if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
    [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
};

[
    format ["Fiche signée — %1 · terminal %2", _callsign, _terminal],
    "tactical",
    "info"
] call comspec_overwatch_connect_fnc_announce;
