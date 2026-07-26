/*
    Envoie un message destinataire HQ vers la messagerie Athena (TOC / journal web).
    Params: [_text]
*/
params [["_text", "", [""]]];

if (!hasInterface) exitWith { false };
_text = trim _text;
if (_text isEqualTo "") exitWith { false };

if (isNil "comspec_overwatch_connect_fnc_sendIntel") exitWith {
    ["Impossible d’envoyer à HQ — module messagerie indisponible.", "error", 5]
        call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    false
};

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

private _grid = mapGridPosition player;
private _formatted = [
    _cs,
    "COMMAND",
    "IMPORTANT",
    format ["%1 (grille %2)", _text, _grid],
    "HQ"
] call comspec_overwatch_connect_fnc_formatCommsMessage;

[player, "CHAT", _formatted, "", "INFANTRY", 0.85] call comspec_overwatch_connect_fnc_sendIntel;

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
_inbox pushBack [
    "HQ",
    "Message HQ",
    _text,
    _grid,
    [daytime, "HH:MM"] call BIS_fnc_timeToString,
    _cs
];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

["Message transmis à HQ.", "ok", 4]
    call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
["Message HQ envoyé vers Athena"] call comspec_overwatch_connect_fnc_appendModuleLog;

true
