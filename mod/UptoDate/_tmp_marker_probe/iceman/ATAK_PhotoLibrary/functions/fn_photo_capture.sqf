params [
    ["_filePath", ""],
    ["_fileName", ""]
];

if (!hasInterface || {_filePath == ""}) exitWith {false};

private _originAGL = positionCameraToWorld [0,0,0];
private _forwardAGL = positionCameraToWorld [0,0,1];
private _upAGL = positionCameraToWorld [0,1,0];
private _dir = _originAGL vectorFromTo _forwardAGL;
private _up = _originAGL vectorFromTo _upAGL;

if ((vectorMagnitude _dir) < 0.1) then {
    _dir = getCameraViewDirection player;
};
if ((vectorMagnitude _up) < 0.1 || {abs (_dir vectorDotProduct _up) > 0.95}) then {
    _up = [0,0,1];
};

private _fov = getObjectFOV cameraOn;
if !(_fov isEqualType 0) then {_fov = 0.75};
_fov = (_fov max 0.02) min 1.2;

private _uid = getPlayerUID player;
private _id = if (isNil "CBA_fnc_createUUID") then {
    format ["%1_%2_%3", _uid, floor (diag_tickTime * 1000), floor random 1000000]
} else {
    call CBA_fnc_createUUID
};

private _posASL = AGLToASL _originAGL;
private _record = [
    _id,
    "local",
    _filePath,
    _fileName,
    profileName,
    _uid,
    systemTime,
    +date,
    mapGridPosition _posASL,
    worldName,
    _posASL,
    _dir,
    _up,
    _fov,
    ""
];

private _records = call Iceman_fnc_photo_getRecords;
_records pushBack _record;
[_records] call Iceman_fnc_photo_storeRecords;

missionNamespace setVariable ["Iceman_PhotoLibrary_selected", _id];
missionNamespace setVariable ["Iceman_PhotoLibrary_previewMode", "original"];

if !(isNil "cTab_fnc_addNotification") then {
    ["PHOTOS", "Picture saved to Photo Library.", 4] call cTab_fnc_addNotification;
};

call Iceman_fnc_photo_refresh;
true
