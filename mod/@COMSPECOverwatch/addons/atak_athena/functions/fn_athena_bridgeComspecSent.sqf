/*
    Miroir Athena → Iceman : diffuse l’alerte / BDA aux appareils cTab en jeu.
*/
params [
    ["_kind", "TIC", [""]],
    ["_body", "", [""]],
    ["_pos", [], [[]]],
    ["_label", "Alerte", [""]],
    ["_callsign", "", [""]]
];

if (!hasInterface) exitWith {};
if (!(["comspec_mirror"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false]) exitWith {};

if ((count _pos) < 2) then { _pos = getPos player; };

private _kindKey = toUpper _kind;
private _time = if (!isNil "cTab_fnc_currentTime") then { call cTab_fnc_currentTime } else { [daytime, "HH:MM"] call BIS_fnc_timeToString };
private _grid = mapGridPosition _pos;
private _from = if (_callsign isEqualTo "") then { name player } else { _callsign };

if (_kindKey isEqualTo "BDA") exitWith {
    if (isNil "Iceman_fnc_bda_receive" && {isNil "Iceman_fnc_bda_send"}) exitWith {};
    private _msgBody = if (_body isEqualTo "") then {
        format ["<t color='#ffd36a'>BDA REPORT</t><br/>Observer: %1<br/>Grid: %2<br/>Time: %3", _from, _grid, _time]
    } else {
        format ["<t color='#ffd36a'>BDA REPORT</t><br/>Observer: %1<br/>Grid: %2<br/>Time: %3<br/><br/>%4", _from, _grid, _time, _body]
    };
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    ["Iceman_ATAK_BDA", [player, _pos, _msgBody, _time]] call CBA_fnc_globalEvent;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
};

if (isNil "Iceman_fnc_alerts_receive" && {isNil "Iceman_fnc_alerts_send"}) exitWith {};

private _kindText = switch (_kindKey) do {
    case "TIC": { "TIC" };
    case "TIC_CLEAR": { "TIC CLEAR" };
    case "FRAGO": { "FRAGO" };
    case "SALUTE": { "SALUTE" };
    case "EAGLE_DOWN": { "EAGLE DOWN" };
    default { _kindKey };
};

private _msgBody = if (_body isEqualTo "") then {
    format ["%1<br/>From: %2<br/>Grid: %3<br/>Time: %4", _kindText, _from, _grid, _time]
} else {
    format ["%1<br/>From: %2<br/>Grid: %3<br/>Time: %4<br/><br/>%5", _kindText, _from, _grid, _time, _body]
};

missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
["Iceman_ATAK_Alerts", [_kindKey, player, _pos, _msgBody, _time, _kindText]] call CBA_fnc_globalEvent;
missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
