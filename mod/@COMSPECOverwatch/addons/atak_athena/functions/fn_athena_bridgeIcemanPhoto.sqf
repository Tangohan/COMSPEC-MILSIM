/*
    Capture BCE / Photo Library → upload Athena (UploadReconImage).
    Params EH bce_took_screenshot : [_filePath, _fileName]
*/
params [["_filePath", ""], ["_fileName", ""]];

if (!hasInterface) exitWith {};
if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (_filePath isEqualTo "") exitWith {};
if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {};

// Éviter double-upload si déjà traité dans la seconde
private _last = missionNamespace getVariable ["COMSPEC_Athena_LastPhotoUpload", ["", 0]];
if ((_last select 0) isEqualTo _filePath && { (diag_tickTime - (_last select 1)) < 2 }) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_LastPhotoUpload", [_filePath, diag_tickTime], false];

private _grid = mapGridPosition player;
private _caption = format ["Photo ATAK Enhanced — grille %1", _grid];
if (_fileName isNotEqualTo "") then {
    _caption = _caption + format [" (%1)", _fileName];
};

[_filePath, _caption] call comspec_overwatch_connect_fnc_captureReconImage;
[format ["Photo envoyee vers Athena (%1)", _fileName]] call comspec_overwatch_connect_fnc_appendModuleLog;

private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];
if (!(_inbox isEqualType [])) then { _inbox = []; };
private _cs = [];
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };
_inbox pushBack [
    "PHOTO",
    "Photo remontée",
    format ["Fichier : %1 — %2", _fileName, _caption],
    _grid,
    [daytime, "HH:MM"] call BIS_fnc_timeToString,
    _cs
];
while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

if (!isNil "cTab_fnc_addNotification") then {
    ["ATHENA", "Photo envoyée vers Athena.", 4] call cTab_fnc_addNotification;
};
