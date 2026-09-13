/*
    Si le compte Athena est lié, récupère l’indicatif Effectifs (GetPlayerAvatarInfo)
    et l’applique localement quand le profil jeu est encore vide ou invalide.
    Ne reprend jamais le nom de communauté ni le nom affiché.
*/
params [["_force", false, [true]]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

private _local = [true] call comspec_overwatch_connect_fnc_getCallsign;

private _info = [] call comspec_overwatch_connect_fnc_getPlayerAvatarInfo;
if (count _info < 2) exitWith { false };
private _callsign = trim (_info select 1);
private _atakId = if (count _info >= 5) then { trim (_info select 4) } else { "" };
private _mid = if (count _info >= 8) then { trim (_info select 7) } else { "" };
if (_mid != "") then {
    missionNamespace setVariable ["COMSPEC_MilitaryId", _mid, false];
    profileNamespace setVariable ["COMSPEC_MilitaryId", _mid];
};
if (_atakId != "") then {
    missionNamespace setVariable ["COMSPEC_AtakId", _atakId, false];
};
if (!([_callsign] call comspec_overwatch_connect_fnc_isUsableCallsign)) then { _callsign = ""; };
if (_callsign isEqualTo "") exitWith { false };

if (_mid != "") then {
    missionNamespace setVariable ["COMSPEC_BftId", _mid, false];
};

if (!_force && {_local isNotEqualTo ""} && {[_local] call comspec_overwatch_connect_fnc_isUsableCallsign}) exitWith {
    false
};

[_callsign, true, "athena"] call comspec_overwatch_connect_fnc_setCallsign;
0 spawn {
    uiSleep 0.5;
    ["", true] call comspec_overwatch_connect_fnc_syncAtakRealism;
};
true
