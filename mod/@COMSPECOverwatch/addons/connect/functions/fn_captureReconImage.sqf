/*
    Capture recon : chemin fourni (Photo Library / BCE) OU capture d’écran via l’extension.
    Params: [path, caption, deviceType, feedId]
*/
params [
    ["_path", ""],
    ["_caption", ""],
    ["_deviceType", "CTAB"],
    ["_feedId", ""]
];
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
private _device = toUpper _deviceType;
if (_device isEqualTo "") then { _device = "CTAB"; };
private _capturedAt = str (floor time);
private _unitName = if (_feedId isEqualTo "") then { name _unit } else { _feedId };

if (_path isEqualTo "") then {
    _path = missionNamespace getVariable ["COMSPEC_LastScreenshotPath", ""];
};

// Chemin fourni (souvent absolu Windows — ne pas tester avec fileExists Arma)
if (_path isNotEqualTo "") exitWith {
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
            _unitName,
            _sideStr,
            _missionId,
            _device,
            _capturedAt,
            _feedId
        ]
    ];
    ["COMSPEC_Info", ["Image de recon envoyée"]] call comspec_overwatch_connect_fnc_showNotification;
};

// Pas de fichier : capture d’écran joueur puis upload de la plus récente
screenshot "COMSPEC_AthenaFeed";
[_author, _device, _caption, _feedId, _pos, _grid, _dir, _sideStr, _missionId, _unitName] spawn {
    params ["_author", "_device", "_caption", "_feedId", "_pos", "_grid", "_dir", "_sideStr", "_missionId", "_unitName"];
    uiSleep 0.9;
    "COMSPECExtension" callExtension [
        "UploadLatestScreenshot",
        [
            _author,
            _device,
            _caption,
            _feedId,
            str (_pos select 0),
            str (_pos select 1),
            str (_pos select 2),
            _grid,
            str _dir,
            _unitName,
            _sideStr,
            _missionId,
            "60"
        ]
    ];
    ["COMSPEC_Info", ["Aperçu envoyé vers Athena"]] call comspec_overwatch_connect_fnc_showNotification;
};
