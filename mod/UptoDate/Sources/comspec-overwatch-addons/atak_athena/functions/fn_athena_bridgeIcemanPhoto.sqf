/*
    Capture BCE / Photo Library → signal upload Athena (NotifyNewPhoto / queue DLL).

    Params: [_filePath, _fileName, _silent, _force]
    _force : renvoi manuel — ignore / purge Dead+Failed pour retenter.
    Retour: true si l’extension a accepté le signal (OK|queued / OK|duplicate), false sinon.
*/
params [
    ["_filePath", "", [""]],
    ["_fileName", "", [""]],
    ["_silent", false, [false]],
    ["_force", false, [false]]
];

if (!hasInterface) exitWith { false };
if (!(["iceman_photo"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith { false };
if (_filePath isEqualTo "") exitWith { false };
if ((toLower _filePath) find "comspec_sse_face" >= 0) exitWith { true };
if ((toLower _fileName) find "comspec_sse_face" >= 0) exitWith { true };

private _keyEarly = toLower _filePath;
private _deadEarly = profileNamespace getVariable ["COMSPEC_Athena_PhotoDead", []];
if (!(_deadEarly isEqualType [])) then { _deadEarly = []; };
private _failedEarly = missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []];
if (!(_failedEarly isEqualType [])) then { _failedEarly = []; };
private _baseEarly = toLower _fileName;
if (_baseEarly isEqualTo "") then {
    private _segsE = _filePath splitString "\/";
    _baseEarly = toLower (_segsE select ((count _segsE) - 1));
};

if (_force) then {
    private _scrubbed = false;
    if (_keyEarly in _deadEarly || {_baseEarly isNotEqualTo "" && {_baseEarly in _deadEarly}}) then {
        _deadEarly = _deadEarly - [_keyEarly, _baseEarly];
        profileNamespace setVariable ["COMSPEC_Athena_PhotoDead", _deadEarly];
        saveProfileNamespace;
        _scrubbed = true;
    };
    if (_keyEarly in _failedEarly || {_baseEarly isNotEqualTo "" && {_baseEarly in _failedEarly}}) then {
        _failedEarly = _failedEarly - [_keyEarly, _baseEarly];
        missionNamespace setVariable ["COMSPEC_Athena_PhotoFailed", _failedEarly, false];
        _scrubbed = true;
    };
    // Anti double-clic 3 s : lever aussi pour un renvoi forcé après scrub.
    if (_scrubbed) then {
        missionNamespace setVariable ["COMSPEC_Athena_LastPhotoUpload", ["", 0], false];
    };
} else {
    if (_keyEarly in _failedEarly || {_keyEarly in _deadEarly} || {_baseEarly isNotEqualTo "" && {_baseEarly in _deadEarly}}) exitWith { false };
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto") then {
    [_filePath, _fileName] call comspec_overwatch_atak_athena_fnc_athena_rememberLocalPhoto;
};

if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith { false };

// Anti double-clic SQF (le dédup réel est côté DLL).
private _last = missionNamespace getVariable ["COMSPEC_Athena_LastPhotoUpload", ["", 0]];
if ((_last select 0) isEqualTo _filePath && { (diag_tickTime - (_last select 1)) < 3 }) exitWith { true };
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
};

private _overlayKind = "";
if (!isNil "comspec_overwatch_connect_fnc_getActiveCaptureCam") then {
    private _cap = [] call comspec_overwatch_connect_fnc_getActiveCaptureCam;
    if ((_cap isEqualType []) && {(count _cap) >= 4} && {!isNull (_cap select 0)}) then {
        _overlayKind = _cap select 3;
    };
};
if (_overlayKind in ["phone", "hcam", "hcam_pip", "tgp", "uav_pip"]) then {
    _device = "CTAB";
    _feedId = "";
    if (_overlayKind in ["hcam", "hcam_pip"]) then { _device = "HELMET"; };
    if (_overlayKind in ["uav_pip", "tgp"]) then { _device = "DRONE"; };
};

private _skipShot = false;
private _lowPath = toLower _filePath;
// Photo Library / BCE : le .jpg annoncé est souvent un chemin mort (srcdir_missing).
// On prend un PNG Arma hors de la frame du clic, au lieu de notifier le JPEG fantôme.
if (
    ((_lowPath find ".jpg") >= 0 || {(_lowPath find ".jpeg") >= 0})
    && {
        _overlayKind isEqualTo ""
        || {missionNamespace getVariable ["COMSPEC_OverlayCamPromoted", false]}
    }
) exitWith {
    [_filePath, _caption, _device, _feedId] spawn {
        params ["_path", "_caption", "_device", "_feedId"];
        uiSleep 0.4;
        [_path, _caption, _device, _feedId, false, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
    };
    [format ["Photo en file (%1)", _fileName]] call comspec_overwatch_connect_fnc_appendModuleLog;
    true
};
if (
    _overlayKind isNotEqualTo ""
    && {!(missionNamespace getVariable ["COMSPEC_OverlayCamPromoted", false])}
) then {
    _skipShot = false;
};

private _ok = [_filePath, _caption, _device, _feedId, _skipShot] call comspec_overwatch_connect_fnc_captureReconImage;
if (!(_ok isEqualType true)) then { _ok = false; };

if (!_ok) exitWith {
    if (!_silent) then {
        private _detail = missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""];
        private _msg = "L’envoi de la photo a échoué.";
        private _d = toLower (str _detail);
        if ((_d find "not_connected") >= 0) then {
            _msg = "Pas de liaison Athena active — reconnectez-vous, puis la photo partira automatiquement.";
        };
        if ((_d find "queue_full") >= 0) then {
            _msg = "File d’attente photo saturée — réessayez dans un instant.";
        };
        [_msg, "error", 8] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    };
    [format ["Échec photo (%1)", if (_fileName isEqualTo "") then { "sans nom" } else { _fileName }]] call comspec_overwatch_connect_fnc_appendModuleLog;
    false
};

[format ["Photo en file (%1)", _fileName]] call comspec_overwatch_connect_fnc_appendModuleLog;

private _key = toLower _filePath;
// Pas encore sur le web : seulement en file (ACK = callback PhotoUpload OK|uploaded).
private _pending = missionNamespace getVariable ["COMSPEC_Athena_PhotoPending", []];
if (!(_pending isEqualType [])) then { _pending = []; };
if !(_key in _pending) then {
    _pending pushBack _key;
    while { (count _pending) > 100 } do { _pending deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_PhotoPending", _pending, false];
};
private _failed = missionNamespace getVariable ["COMSPEC_Athena_PhotoFailed", []];
if (!(_failed isEqualType [])) then { _failed = []; };
if (_key in _failed) then {
    _failed = _failed - [_key];
    missionNamespace setVariable ["COMSPEC_Athena_PhotoFailed", _failed, false];
};
private _seen = missionNamespace getVariable ["COMSPEC_Athena_PhotoSeen", []];
if (!(_seen isEqualType [])) then { _seen = []; };
if !(_key in _seen) then {
    _seen pushBack _key;
    while { (count _seen) > 100 } do { _seen deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_PhotoSeen", _seen, false];
};

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
        "Photo en envoi",
        _summary,
        _grid,
        [daytime, "HH:MM"] call BIS_fnc_timeToString,
        _cs
    ];
    while { (count _inbox) > 40 } do { _inbox deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_Athena_AlertInbox", _inbox, false];
    ["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
};

if (!_silent) then {
    [
        "Photo mise en file vers Athena.",
        "ok",
        5
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
} else {
    private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
    if (!isNull _group && {ctrlShown _group}) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
};

true

