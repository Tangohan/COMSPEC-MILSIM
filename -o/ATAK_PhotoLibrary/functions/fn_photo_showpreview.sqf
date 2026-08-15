call Iceman_fnc_photo_cleanupPreview;

private _controls = call Iceman_fnc_photo_findControls;
private _preview = _controls getOrDefault ["9420", controlNull];
private _metadata = _controls getOrDefault ["9421", controlNull];
if (isNull _preview || {isNull _metadata}) exitWith {false};

private _record = call Iceman_fnc_photo_getSelectedRecord;
if (_record isEqualTo []) exitWith {
    _preview ctrlSetText "\ATAK_PhotoLibrary\data\photo_library_icon_ca.paa";
    _metadata ctrlSetStructuredText parseText "<t align='center' color='#9fb0b4'>No pictures in this library.</t>";
    false
};

private _source = _record param [1, "received"];
private _filePath = _record param [2, ""];
private _fileName = _record param [3, "Picture"];
private _author = _record param [4, "Unknown"];
private _timestamp = _record param [6, []];
private _grid = _record param [8, "Unknown"];
private _terrain = _record param [9, ""];
private _posASL = _record param [10, []];
private _dir = _record param [11, [0,1,0]];
private _up = _record param [12, [0,0,1]];
private _fov = _record param [13, 0.75];
private _sharedBy = _record param [14, ""];
private _previewMode = missionNamespace getVariable ["Iceman_PhotoLibrary_previewMode", "live"];
private _showOriginal = _source == "local" && {_filePath != ""} && {_previewMode == "original"};
private _viewLabel = "Live snapshot";

if (_showOriginal) then {
    _preview ctrlSetText _filePath;
    _viewLabel = "Saved JPEG";
} else {
    private _canRender = _terrain == worldName
        && {_posASL isEqualType [] && {count _posASL >= 3}}
        && {_dir isEqualType [] && {count _dir >= 3}}
        && {_up isEqualType [] && {count _up >= 3}};

    if (_canRender) then {
        private _cam = "camera" camCreate (ASLToAGL _posASL);
        _cam setPosASL _posASL;
        _cam setVectorDirAndUp [_dir, _up];
        _cam camSetFov ((_fov max 0.02) min 1.2);
        _cam camCommit 0;
        _cam cameraEffect ["INTERNAL", "BACK", "Iceman_PhotoLibrary_preview"];
        "Iceman_PhotoLibrary_preview" setPiPEffect [0];
        uiNamespace setVariable ["Iceman_PhotoLibrary_previewCamera", _cam];
        _preview ctrlSetText "#(argb,1024,512,1)r2t(Iceman_PhotoLibrary_preview,2.0)";
    } else {
        _preview ctrlSetText "\ATAK_PhotoLibrary\data\photo_library_icon_ca.paa";
        _viewLabel = format ["%1 terrain", _terrain];
    };
};

private _timeText = [_timestamp] call Iceman_fnc_photo_formatTimestamp;
private _shareText = if (_sharedBy == "") then {""} else {format [" | Shared by %1", _sharedBy]};
_metadata ctrlSetStructuredText parseText format [
    "<t color='#b8e8ef'>%1</t><br/><t size='0.82'>%2 | %3 | Grid %4%5</t>",
    _fileName,
    _author,
    _timeText,
    _grid,
    _shareText
];
_metadata ctrlSetTooltip _viewLabel;

true
