/*

    Capture BCE / Photo Library → upload Athena (UploadReconImage).

    Params EH bce_took_screenshot : [_filePath, _fileName]

    Tag HELMET / DRONE selon le contexte opérateur.

*/

params [["_filePath", ""], ["_fileName", ""]];



if (!hasInterface) exitWith {};

if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};

if (_filePath isEqualTo "") exitWith {};

if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {};



// Éviter double-upload si déjà traité dans la seconde

private _last = missionNamespace getVariable ["COMSPEC_Athena_LastPhotoUpload", ["", 0]];

if ((_last select 0) isEqualTo _filePath && { (diag_tickTime - (_last select 1)) < 5 }) exitWith {};

missionNamespace setVariable ["COMSPEC_Athena_LastPhotoUpload", [_filePath, diag_tickTime], false];



private _grid = mapGridPosition player;

private _caption = format ["Photo ATAK Enhanced — grille %1", _grid];

if (_fileName isNotEqualTo "") then {

    _caption = _caption + format [" (%1)", _fileName];

};



private _device = "CTAB";

private _feedId = "";



private _droneState = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];

private _drone = objNull;

if (_droneState isEqualType createHashMap) then {

    _drone = _droneState getOrDefault ["drone", objNull];

};

if (isNull _drone) then {

    private _uav = getConnectedUAV player;

    if (!isNull _uav && {alive _uav}) then { _drone = _uav; };

};

if (!isNull _drone && {alive _drone}) then {

    _device = "DRONE";

    private _netId = netId _drone;

    if (_netId isEqualTo "") then { _netId = str _drone; };

    _feedId = format ["drone:%1", _netId];

    _caption = format ["Photo drone — grille %1", mapGridPosition _drone];

    if (_fileName isNotEqualTo "") then { _caption = _caption + format [" (%1)", _fileName]; };

} else {

    private _hasHcam = ("ItemcTabHCam" in (items player + assignedItems player))

        || {((headgear player) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))};

    if (_hasHcam) then {

        _device = "HELMET";

        _feedId = format ["helmet:%1", getPlayerUID player];

        _caption = format ["Photo casque — grille %1", _grid];

        if (_fileName isNotEqualTo "") then { _caption = _caption + format [" (%1)", _fileName]; };

    };

};



[_filePath, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;

[format ["Photo envoyée (%1)", _fileName]] call comspec_overwatch_connect_fnc_appendModuleLog;



private _inbox = missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []];

if (!(_inbox isEqualType [])) then { _inbox = []; };

private _cs = "";

if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {

    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;

};

if (_cs isEqualTo "") then { _cs = name player; };



private _summary = switch (_device) do {

    case "DRONE": {

        if (_fileName isEqualTo "") then {

            format ["Vue drone — grille %1", _grid]

        } else {

            format ["Vue drone — %1 (grille %2)", _fileName, _grid]

        };

    };

    case "HELMET": {

        if (_fileName isEqualTo "") then {

            format ["Vue casque — grille %1", _grid]

        } else {

            format ["Vue casque — %1 (grille %2)", _fileName, _grid]

        };

    };

    default {

        if (_fileName isEqualTo "") then {

            format ["Remontée depuis ATAK — grille %1", _grid]

        } else {

            format ["Remontée depuis ATAK — %1 (grille %2)", _fileName, _grid]

        };

    };

};



private _dupPhoto = false;

if ((count _inbox) > 0) then {

    private _prev = _inbox select ((count _inbox) - 1);

    _dupPhoto = (_prev select 0) isEqualTo "PHOTO" && {(_prev select 2) isEqualTo _summary};

};

if (!_dupPhoto) then {

    _inbox pushBack [

        "PHOTO",

        "Photo remontée",

        _summary,

        _grid,

        [daytime, "HH:MM"] call BIS_fnc_timeToString,

        _cs

    ];

    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };

    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];

    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;

};



[

    "Photo envoyée vers Athena.",

    "ok",

    5

] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;

