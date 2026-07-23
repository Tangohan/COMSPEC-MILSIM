/*
    Pont BDA Iceman → Athena (canal ALERTE TACTIQUE|BDA|…).
    Payload Iceman : [_sender, _pos, _bodyHtml, _time]
*/
params ["_sender", "_pos", ["_bodyHtml", ""], ["_time", ""]];

if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false]) exitWith {};

private _from = if (isNull _sender) then { "—" } else { name _sender };
private _grid = if ((count _pos) >= 2) then { mapGridPosition _pos } else { "" };
private _timeStr = if (_time isEqualTo "") then { [daytime, "HH:MM"] call BIS_fnc_timeToString } else { _time };
private _summary = _bodyHtml;
_summary = [_summary, "<br/>", " | "] call BIS_fnc_replaceString;
_summary = [_summary, "<br>", " | "] call BIS_fnc_replaceString;
_summary = [_summary, "<t color='#ffd36a'>BDA REPORT</t>", "BDA"] call BIS_fnc_replaceString;

private _isLocal = !isNull _sender && { _sender isEqualTo player };

if (_isLocal) then {
    if (isNil "comspec_overwatch_connect_fnc_sendTacticalAlert") exitWith {};
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    ["BDA", _summary, if ((count _pos) >= 2) then { _pos } else { getPos player }] call comspec_overwatch_connect_fnc_sendTacticalAlert;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
    if (!isNil "cTab_fnc_addNotification") then {
        ["ATHENA", "Bilan des dégâts transmis à Athena.", 4] call cTab_fnc_addNotification;
    };
} else {
    private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
    if (!(_inbox isEqualType [])) then { _inbox = []; };
    _inbox pushBack ["BDA", "Bilan des dégâts", _summary, _grid, _timeStr, _from];
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};
