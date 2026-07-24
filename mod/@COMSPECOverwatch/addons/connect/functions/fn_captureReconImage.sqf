/*
    Capture recon image: take screenshot (or use provided path), collect metadata, send via UploadReconImage.
    Params: optional [path, caption]. If path omitted, uses A3 screenshot (if available) or placeholder.
*/
params [["_path", ""], ["_caption", ""]];
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _unit = player;
private _pos = getPosASL _unit;
private _dir = getDir _unit;
private _grid = mapGridPosition _unit;
private _author = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_author isEqualTo "") then { _author = name _unit };
private _sideStr = "WEST";
switch (side _unit) do {
    case east: { _sideStr = "EAST" };
    case independent: { _sideStr = "GUER" };
    case civilian: { _sideStr = "CIV" };
    default { _sideStr = "WEST" };
};
private _missionId = missionNamespace getVariable ["COMSPEC_MissionId", "op_1"];
private _device = "CTAB";
private _capturedAt = str (floor time);

if (_path isEqualTo "") then {
    _path = "A3\data_f\scripts\screenshot.jpg";
    if (!fileExists _path) then {
        _path = missionNamespace getVariable ["COMSPEC_LastScreenshotPath", ""];
    };
};
if (_path isEqualTo "") exitWith {
    ["COMSPEC_Warning", ["No image to send — take a photo from Overwatch terminal."]] call comspec_overwatch_connect_fnc_showNotification;
};

"COMSPECExtension" callExtension [
    "UploadReconImage",
    [
        _path,
        _author,
        str (_pos select 0),
        str (_pos select 1),
        str (_pos select 2),
        _grid,
        str _dir,
        str (_pos select 2),
        _caption,
        name _unit,
        _sideStr,
        _missionId,
        _device,
        _capturedAt
    ]
];
["COMSPEC_Info", ["Recon image sent"]] call comspec_overwatch_connect_fnc_showNotification;
