/*
    Capture recon : signal « nouvelle photo » vers la DLL (queue async).
    Params: [path, caption, deviceType, feedId]
    Retour: true si l’extension a accepté (OK|queued), false sinon
    (OK|duplicate n’est plus traité comme un succès d’envoi).
*/
params [
    ["_path", ""],
    ["_caption", ""],
    ["_deviceType", "CTAB"],
    ["_feedId", ""]
];
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

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

private _fxProfile = "";
private _fxIntensity = 0;
private _zoneType = "";
if (missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false]) then {
    private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
    private _disc = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
    private _zone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
    if (!isNil "_zone" && {_zone isEqualType createHashMap}) then {
        _zoneType = toLower (_zone getOrDefault ["type", ""]);
    };
    private _atak = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
    if (_disc getOrDefault ["is_disconnected", false]) then {
        _fxProfile = "signal_lost";
        _fxIntensity = 1;
    } else {
        if !(_atak getOrDefault ["powered_on", true]) then {
            _fxProfile = "screen_off";
            _fxIntensity = 1;
        } else {
            if !(_atak getOrDefault ["can_display", true]) then {
                _fxProfile = "screen_broken";
                _fxIntensity = 1;
            } else {
                if (_loss >= 35) then {
                    _fxProfile = if (_zoneType in ["jammer", "interference"]) then { "jammed_heavy" } else { "glitch_heavy" };
                    _fxIntensity = 1;
                } else {
                    if (_loss >= 18) then {
                        _fxProfile = if (_zoneType in ["jammer", "interference"]) then { "jammed_medium" } else { "glitch_medium" };
                        _fxIntensity = 0.7;
                    } else {
                        if (_loss >= 8) then {
                            _fxProfile = "glitch_light";
                            _fxIntensity = 0.4;
                        };
                    };
                };
            };
        };
    };
};

private _fnc_cleanPath = {
    params ["_p"];
    if (!(_p isEqualType "") || {_p isEqualTo ""}) exitWith { "" };
    _p = trim _p;
    private _n = count _p;
    if (_n >= 2) then {
        private _a = _p select [0, 1];
        private _b = _p select [_n - 1, 1];
        private _dq = toString [34];
        if ((_a isEqualTo _dq && {_b isEqualTo _dq}) || {_a isEqualTo "'" && {_b isEqualTo "'"}}) then {
            _p = _p select [1, _n - 2];
            _p = trim _p;
        };
    };
    (_p splitString "/") joinString "\\"
};

private _fnc_basename = {
    params ["_p"];
    if (!(_p isEqualType "") || {_p isEqualTo ""}) exitWith { "" };
    private _parts = _p splitString "\/";
    if ((count _parts) < 1) exitWith { _p };
    _parts select ((count _parts) - 1)
};

_path = [_path] call _fnc_cleanPath;

if (_path isEqualTo "") then {
    _path = missionNamespace getVariable ["COMSPEC_LastScreenshotPath", ""];
    _path = [_path] call _fnc_cleanPath;
};

private _isQueuedOk = {
    params ["_text"];
    if (!(_text isEqualType "")) then { _text = str _text; };
    _text = trim _text;
    if (_text isEqualTo "") exitWith { false };
    private _u = toUpper _text;
    // Uniquement une vraie mise en file — pas OK|duplicate.
    ((_u find "OK|QUEUED") == 0) || {_u isEqualTo "OK"}
};

private _fnc_notifyPath = {
    params ["_uploadPath"];
    if (!(_uploadPath isEqualType "") || {_uploadPath isEqualTo ""}) exitWith { false };
    ["NotifyNewPhoto", "attempt", [_uploadPath] call _fnc_basename, nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    private _raw = ["COMSPECExtension" callExtension [
        "NotifyNewPhoto",
        [
            _uploadPath,
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
            _feedId,
            _fxProfile,
            str _fxIntensity
        ]
    ]] call comspec_overwatch_connect_fnc_extResult;
    private _ok = [_raw] call _isQueuedOk;
    missionNamespace setVariable ["COMSPEC_LastReconUploadOk", _ok, false];
    missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", _raw, false];
    if (_ok) then {
        ["NotifyNewPhoto", "ok", [_uploadPath] call _fnc_basename, nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    } else {
        private _u = toUpper (str _raw);
        if ((_u find "OK|DUPLICATE") == 0) then {
            ["NotifyNewPhoto", "ok", "duplicate (ignoré)", nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        } else {
            ["NotifyNewPhoto", "fail", str _raw, _raw, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        };
    };
    _ok
};

// Chemin fourni : un seul signal. Resolve / jpg↔png / watcher = DLL.
if (_path isNotEqualTo "") exitWith {
    private _ok = [_path] call _fnc_notifyPath;
    if (_ok) then {
        ["COMSPEC_Info", ["Image de recon mise en file"]] call comspec_overwatch_connect_fnc_showNotification;
    } else {
        private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
        private _msg = "Échec d’envoi de la photo vers Athena";
        if ((_detail find "not_connected") >= 0 || {(_detail find "unauthorized") >= 0}) then {
            _msg = "Liaison Athena dégradée — reconnectez-vous puis réessayez";
        };
        if ((_detail find "queue_full") >= 0) then {
            _msg = "File d’attente photo saturée — réessayez dans un instant";
        };
        if ((_detail find "file_not_found") >= 0) then {
            _msg = "Fichier photo introuvable sur le disque — capturez à nouveau (app Photos ATAK / BCE)";
            missionNamespace setVariable ["COMSPEC_FeedSnapFailUntil", diag_tickTime + 300, false];
        };
        if ((_detail find "ok|duplicate") < 0) then {
            ["COMSPEC_Error", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
        };
    };
    _ok
};

// Pas de fichier : capture dédiée (BCE d’abord, sinon screenshot Arma).
if (_path isEqualTo "") exitWith {
    private _failUntil = missionNamespace getVariable ["COMSPEC_FeedSnapFailUntil", 0];
    if (_failUntil isEqualType 0 && {diag_tickTime < _failUntil}) exitWith {
        missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
        missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|feed_backoff", false];
        false
    };

    private _resolved = "";
    private _stem = format ["COMSPEC_%1_%2", floor diag_tickTime, floor random 99999];

    if (!isNil "BCE_fnc_screenShot") then {
        private _shot = [_stem] call BCE_fnc_screenShot;
        if ((_shot isEqualType []) && {(count _shot) >= 1}) then {
            private _ret = _shot select 0;
            private _file = if ((count _shot) > 1) then { _shot select 1 } else { _stem + ".jpg" };
            if (_ret isEqualType "" && {_ret isNotEqualTo ""}) then {
                private _low = toLower _ret;
                if ((_low find ".jpg") >= 0 || {(_low find ".jpeg") >= 0} || {(_low find ".png") >= 0}) then {
                    _resolved = [_ret] call _fnc_cleanPath;
                } else {
                    private _base = [_ret] call _fnc_cleanPath;
                    while { (count _base) > 0 && {(_base select [(count _base) - 1, 1]) isEqualTo "\\"} } do {
                        _base = _base select [0, (count _base) - 1];
                    };
                    _resolved = format ["%1\\%2", _base, _file];
                };
            };
        };
    };

    if (_resolved isNotEqualTo "") exitWith {
        missionNamespace setVariable ["COMSPEC_LastScreenshotPath", _resolved, false];
        [_resolved] call _fnc_notifyPath
    };

    // Repli moteur Arma — stem unique (l’ancien COMSPEC_AthenaFeed fixe spammait le dédup).
    screenshot _stem;
    [_stem, _caption, _device, _feedId] spawn {
        params ["_stem", "_caption", "_device", "_feedId"];
        uiSleep 2.2;
        private _ok = [_stem, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
        if (!_ok) then {
            uiSleep 0.8;
            [_stem + ".png", _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
        };
    };
    true
};

false
