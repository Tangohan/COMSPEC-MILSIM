/*
    Capture recon : signal « nouvelle photo » vers la DLL (queue async).
    Params: [path, caption, deviceType, feedId, skipArmaShot, alignDevicePov, jpegRetryOnce]
    jpegRetryOnce : un seul report JPEG différé si le fichier n’est pas encore sur disque.
    Retour: true si l’extension a accepté (OK|queued), false sinon
    (OK|duplicate n’est plus traité comme un succès d’envoi).
*/
params [
    ["_path", ""],
    ["_caption", ""],
    ["_deviceType", "CTAB"],
    ["_feedId", ""],
    ["_skipArmaShot", false],
    ["_alignDevicePov", true],
    ["_jpegRetryOnce", true]
];
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!hasInterface) exitWith { false };

// Sans session Athena, NotifyNewPhoto échoue (not_connected) puis un retry
// reclichait un PNG toutes les ~2 s — spam journal + disque.
if (
    !(missionNamespace getVariable ["COMSPEC_AthenaReady", false])
    || {
        !isNil "comspec_overwatch_connect_fnc_isReady"
        && {!([] call comspec_overwatch_connect_fnc_isReady)}
    }
) exitWith {
    missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
    missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|not_connected", false];
    private _hintAt = missionNamespace getVariable ["COMSPEC_ReconNotReadyHintAt", 0];
    if (!(_hintAt isEqualType 0)) then { _hintAt = 0; };
    if (diag_tickTime > _hintAt) then {
        missionNamespace setVariable ["COMSPEC_ReconNotReadyHintAt", diag_tickTime + 30, false];
        ["COMSPEC_Warning", ["Session Athena pas encore prête — réessayez après la connexion"]] call comspec_overwatch_connect_fnc_showNotification;
    };
    false
};

private _unit = player;
private _pos = getPosASL _unit;
private _dir = getDir _unit;
private _grid = mapGridPosition _unit;
private _overlayCam = objNull;
private _overlayHost = objNull;
private _overlayRtt = "";
private _overlayKind = "";
if (!isNil "comspec_overwatch_connect_fnc_getActiveCaptureCam") then {
    private _cap = [] call comspec_overwatch_connect_fnc_getActiveCaptureCam;
    if ((_cap isEqualType []) && {(count _cap) >= 4}) then {
        _overlayCam = _cap select 0;
        _overlayHost = _cap select 1;
        _overlayRtt = _cap select 2;
        _overlayKind = _cap select 3;
    };
};
if (!isNull _overlayCam) then {
    _pos = getPosASL _overlayCam;
    _dir = getDir _overlayCam;
    _grid = mapGridPosition _overlayCam;
};
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
if (!isNull _overlayCam && {_feedId isEqualTo ""}) then {
    if (_overlayKind in ["hcam", "hcam_pip"]) then {
        _device = "HELMET";
        private _uid = "";
        if (!isNull _overlayHost) then { _uid = getPlayerUID _overlayHost; };
        if (_uid isEqualTo "") then { _uid = netId _overlayHost; };
        if (_uid isEqualTo "") then { _uid = str _overlayHost; };
        _feedId = format ["helmet:%1", _uid];
    };
    if (_overlayKind in ["uav_pip", "tgp"]) then {
        _device = "DRONE";
        private _netId = if (!isNull _overlayHost) then { netId _overlayHost } else { "" };
        if (_netId isEqualTo "") then { _netId = str _overlayHost; };
        _feedId = format ["drone:%1", _netId];
    };
};
private _capturedAt = str (floor time);
private _unitName = if (_feedId isEqualTo "") then { name _unit } else { _feedId };
if (!isNull _overlayHost) then {
    private _hostName = name _overlayHost;
    if (_hostName isNotEqualTo "") then { _unitName = _hostName; };
};

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
if ((toLower _path) find "comspec_sse_face" >= 0) exitWith { true };

if (_path isEqualTo "") then {
    _path = missionNamespace getVariable ["COMSPEC_LastScreenshotPath", ""];
    _path = [_path] call _fnc_cleanPath;
};

private _isQueuedOk = {
    params ["_text"];
    if (!(_text isEqualType "")) then { _text = str _text; };
    _text = trim _text;
    // callExtension (Arma 2.18+) peut renvoyer un tableau puis un texte avec guillemets.
    private _n = count _text;
    if (_n >= 2) then {
        private _a = _text select [0, 1];
        private _b = _text select [_n - 1, 1];
        private _dq = toString [34];
        if ((_a isEqualTo _dq && {_b isEqualTo _dq}) || {_a isEqualTo "'" && {_b isEqualTo "'"}}) then {
            _text = trim (_text select [1, _n - 2]);
        };
    };
    if (_text isEqualTo "") exitWith { false };
    private _u = toUpper _text;
    // Mise en file, ou ignore volontaire (visage SEEK ≠ reconnaissance).
    ((_u find "OK|QUEUED") == 0) || {(_u find "OK|IGNORED") == 0} || {_u isEqualTo "OK"}
};

private _fnc_stripExtQuotes = {
    params ["_text"];
    if (!(_text isEqualType "")) then { _text = format ["%1", _text]; };
    _text = trim _text;
    private _n = count _text;
    if (_n >= 2) then {
        private _a = _text select [0, 1];
        private _b = _text select [_n - 1, 1];
        private _dq = toString [34];
        if ((_a isEqualTo _dq && {_b isEqualTo _dq}) || {_a isEqualTo "'" && {_b isEqualTo "'"}}) then {
            _text = trim (_text select [1, _n - 2]);
        };
    };
    _text
};

private _fnc_isConnErr = {
    private _d = toLower ([missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]] call _fnc_stripExtQuotes);
    (_d find "not_connected") >= 0
    || {(_d find "unauthorized") >= 0}
    || {(_d find "queue_full") >= 0}
};

// format %1 sur un grand nombre → "1.1141e+06" (le PNG disque n’a jamais ce nom).
private _fnc_shotStem = {
    private _a = (floor diag_tickTime) toFixed 0;
    private _b = (floor random 99999) toFixed 0;
    format ["COMSPEC_%1_%2", _a, _b]
};

private _fnc_armaPngCapture = {
    // screenshot Arma EXIGE .png — sinon échec silencieux (wiki BI).
    // Retour booléen false = HDR trop bas ou dossier Screenshots saturé (250 Mo).
    private _png = ([] call _fnc_shotStem) + ".png";
    private _ret = screenshot _png;
    missionNamespace setVariable ["COMSPEC_LastScreenshotPath", _png, false];
    if (_ret isEqualType true && {!_ret}) exitWith { "" };
    _png
};

private _fnc_isJpegPath = {
    params ["_p"];
    if (!(_p isEqualType "") || {_p isEqualTo ""}) exitWith { false };
    private _l = toLower _p;
    (_l find ".jpg") >= 0 || {(_l find ".jpeg") >= 0}
};

private _fnc_stagePath = {
    params ["_hint"];
    if (!(_hint isEqualType "") || {_hint isEqualTo ""}) exitWith { "" };
    if (isNil "comspec_overwatch_connect_fnc_extResult") exitWith { "" };
    private _raw = ["COMSPECExtension" callExtension ["StageCapture", [_hint]]] call comspec_overwatch_connect_fnc_extResult;
    if (!(_raw isEqualType "")) exitWith { "" };
    _raw = [_raw] call _fnc_stripExtQuotes;
    if ((count _raw) < 4) exitWith { "" };
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { "" };
    private _body = _raw select [3, (count _raw) - 3];
    _body = trim _body;
    if ([_body] call _fnc_isJpegPath) exitWith { "" };
    _body
};

private _fnc_notifyPath = {
    params ["_uploadPath"];
    if (!(_uploadPath isEqualType "") || {_uploadPath isEqualTo ""}) exitWith { false };
    // JPEG IceMan / BCE : chemin fantôme (srcdir_missing). On n’envoie jamais ce nom.
    if ([_uploadPath] call _fnc_isJpegPath) then {
        private _png = [] call _fnc_armaPngCapture;
        if (_png isNotEqualTo "") then { _uploadPath = _png; };
    };
    if ([_uploadPath] call _fnc_isJpegPath) exitWith {
        missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
        missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|screenshot_rejected", false];
        false
    };
    private _staged = [_uploadPath] call _fnc_stagePath;
    if (_staged isNotEqualTo "") then { _uploadPath = _staged; };
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
        private _txt = [_raw] call _fnc_stripExtQuotes;
        private _u = toUpper _txt;
        if ((_u find "OK|DUPLICATE") == 0) then {
            ["NotifyNewPhoto", "ok", "duplicate (ignoré)", nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        } else {
            ["NotifyNewPhoto", "fail", _txt, _raw, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        };
    };
    _ok
};

// Overlay ATAK / casque / tourelle : le JPEG BCE peut être la vue soldat si la
// caméra regardée est en rendu vers texture. Forcer un cliché scène — sauf si
// le JPEG vient déjà du cliché overlay (TakePicture a promu avant SOAR).
if (
    !isNull _overlayCam
    && {_skipArmaShot}
    && {!(missionNamespace getVariable ["COMSPEC_OverlayCamPromoted", false])}
) then {
    private _lowGiven = toLower _path;
    if ((_lowGiven find ".jpg") < 0 && {(_lowGiven find ".jpeg") < 0}) then {
        _skipArmaShot = false;
    };
};

if (
    !_skipArmaShot
    && {_alignDevicePov}
    && {!isNull _overlayCam}
    && {isNull curatorCamera}
) exitWith {
    if (missionNamespace getVariable ["COMSPEC_ReconCaptureBusy", false]) exitWith { false };
    missionNamespace setVariable ["COMSPEC_ReconCaptureBusy", true, false];
    [_caption, _device, _feedId] spawn {
        params ["_caption", "_device", "_feedId"];

        if (!isNil "ace_interact_menu_fnc_hideMenu") then {
            [] call ace_interact_menu_fnc_hideMenu;
        };
        showHUD false;

        private _restore = [];
        if (!isNil "comspec_overwatch_connect_fnc_promoteCaptureCam") then {
            _restore = [true] call comspec_overwatch_connect_fnc_promoteCaptureCam;
            if (!(_restore isEqualType [])) then { _restore = []; };
        };

        uiSleep 0.16;

        private _png = format [
            "COMSPEC_%1_%2.png",
            (floor diag_tickTime) toFixed 0,
            (floor random 99999) toFixed 0
        ];
        private _shotOk = screenshot _png;
        missionNamespace setVariable ["COMSPEC_LastScreenshotPath", _png, false];

        showHUD true;
        if ((count _restore) >= 3 && {!isNil "comspec_overwatch_connect_fnc_restoreCaptureCam"}) then {
            _restore call comspec_overwatch_connect_fnc_restoreCaptureCam;
        };

        if (_shotOk isEqualType true && {!_shotOk}) then {
            missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
            missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|screenshot_rejected", false];
            ["COMSPEC_Error", ["Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen, puis reprenez la photo."]] call comspec_overwatch_connect_fnc_showNotification;
            missionNamespace setVariable ["COMSPEC_ReconCaptureBusy", false, false];
        } else {
            uiSleep 1.25;
            [_png, _caption, _device, _feedId, true, false] call comspec_overwatch_connect_fnc_captureReconImage;
            missionNamespace setVariable ["COMSPEC_ReconCaptureBusy", false, false];
        };
    };
    true
};

// Casque / drone : `screenshot` capture la vue courante (3e personne si le
// joueur y est). On bascule 2–3 frames en 1re personne / tourelle UAV,
// on cliche, on restaure. Pas pour les aperçus périodiques (alignDevicePov=false)
// ni Zeus (curatorCamera) — ça arracherait le joueur.
if (
    !_skipArmaShot
    && {_alignDevicePov}
    && {isNull _overlayCam}
    && {_device in ["HELMET", "DRONE"]}
    && {isNull curatorCamera}
) exitWith {
    if (missionNamespace getVariable ["COMSPEC_ReconCaptureBusy", false]) exitWith { false };
    missionNamespace setVariable ["COMSPEC_ReconCaptureBusy", true, false];
    [_caption, _device, _feedId] spawn {
        params ["_caption", "_device", "_feedId"];
        private _prevView = cameraView;

        if (!isNil "ace_interact_menu_fnc_hideMenu") then {
            [] call ace_interact_menu_fnc_hideMenu;
        };
        showHUD false;

        if (_device isEqualTo "DRONE") then {
            private _uav = objNull;
            private _st = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
            if (_st isEqualType createHashMap) then {
                _uav = _st getOrDefault ["drone", objNull];
            };
            if (isNull _uav) then { _uav = getConnectedUAV player; };
            if (!isNull _uav && {alive _uav}) then {
                _uav switchCamera "GUNNER";
            };
        } else {
            // 1re personne (yeux / casque). screenshot est synchrone : sans ce
            // délai d’1–2 frames, Arma cliche encore la 3e personne.
            if (!isNull player) then {
                player switchCamera "INTERNAL";
            };
        };

        uiSleep 0.16;

        private _png = format [
            "COMSPEC_%1_%2.png",
            (floor diag_tickTime) toFixed 0,
            (floor random 99999) toFixed 0
        ];
        private _shotOk = screenshot _png;
        missionNamespace setVariable ["COMSPEC_LastScreenshotPath", _png, false];

        showHUD true;
        if (_prevView isEqualType "" && {_prevView isNotEqualTo ""}) then {
            player switchCamera _prevView;
        };

        if (_shotOk isEqualType true && {!_shotOk}) then {
            missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
            missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|screenshot_rejected", false];
            ["COMSPEC_Error", ["Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen, puis reprenez la photo."]] call comspec_overwatch_connect_fnc_showNotification;
            missionNamespace setVariable ["COMSPEC_ReconCaptureBusy", false, false];
        } else {
            // Laisser le PNG se flusher (évite file_empty juste après le hitch).
            uiSleep 1.25;
            [_png, _caption, _device, _feedId, true, false] call comspec_overwatch_connect_fnc_captureReconImage;
            missionNamespace setVariable ["COMSPEC_ReconCaptureBusy", false, false];
        };
    };
    true
};

// Chemin fourni (IceMan / BCE / Photo Library). Un JPEG annoncé n’est pas un
// fichier : on recliche un PNG Arma (Screenshots / AppData) et on envoie ça.
if (_path isNotEqualTo "") exitWith {
    private _isJpeg = [_path] call _fnc_isJpegPath;
    private _png = _path;
    if (!_skipArmaShot || _isJpeg) then {
        _png = [] call _fnc_armaPngCapture;
    };
    if (!_skipArmaShot && {_png isEqualTo ""} && {!_isJpeg}) exitWith {
        missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
        missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|screenshot_rejected", false];
        ["COMSPEC_Error", ["Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen (options d’affichage), puis reprenez la photo."]] call comspec_overwatch_connect_fnc_showNotification;
        false
    };
    if (_isJpeg && {(_png isEqualTo "") || {[_png] call _fnc_isJpegPath}}) exitWith {
        missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
        missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|screenshot_rejected", false];
        ["COMSPEC_Error", ["Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen (options d’affichage), puis reprenez la photo."]] call comspec_overwatch_connect_fnc_showNotification;
        false
    };
    private _ok = [_png] call _fnc_notifyPath;
    // not_connected / unauthorized : ne pas reclicher ni relancer — ça inonde le journal.
    if (!_ok && {[] call _fnc_isConnErr}) exitWith { false };
    if (!_ok && {_skipArmaShot} && {_jpegRetryOnce} && {!_isJpeg}) then {
        [_path, _caption, _device, _feedId] spawn {
            params ["_path", "_caption", "_device", "_feedId"];
            uiSleep 0.45;
            private _retry = [_path, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
            if (!(_retry isEqualType true) || {!_retry}) then {
                private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
                if ((_detail find "not_connected") >= 0 || {(_detail find "unauthorized") >= 0}) exitWith {};
                uiSleep 0.2;
                [_path, _caption, _device, _feedId, false, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
            };
        };
        true
    } else {
        if (!_ok && {!_skipArmaShot}) then {
            [_png, _caption, _device, _feedId] spawn {
                params ["_png", "_caption", "_device", "_feedId"];
                uiSleep 2.5;
                [_png, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
            };
        };
        if (_ok) then {
            if (_jpegRetryOnce) then {
                ["COMSPEC_Info", ["Image de recon mise en file"]] call comspec_overwatch_connect_fnc_showNotification;
            };
        } else {
            private _detail = toLower ([missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]] call _fnc_stripExtQuotes);
            private _msg = "Échec d’envoi de la photo vers Athena";
            if ((_detail find "not_connected") >= 0 || {(_detail find "unauthorized") >= 0}) then {
                _msg = "Liaison Athena dégradée — reconnectez-vous puis réessayez";
            };
            if ((_detail find "queue_full") >= 0) then {
                _msg = "File d’attente photo saturée — réessayez dans un instant";
            };
            if ((_detail find "file_not_found") >= 0 || {(_detail find "screenshot_rejected") >= 0}) then {
                _msg = "Capture non écrite sur le disque — passez la qualité HDR au moins sur Moyen, puis reprenez une photo";
                missionNamespace setVariable ["COMSPEC_FeedSnapFailUntil", diag_tickTime + 300, false];
            };
            if ((_detail find "ok|duplicate") < 0) then {
                ["COMSPEC_Error", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
            };
        };
        _ok
    }
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
    private _stem = [] call _fnc_shotStem;

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

    // BCE ou pas : toujours un .png Arma (chemin BCE souvent mort / srcdir_missing).
    private _png = if (_skipArmaShot) then {
        private _last = missionNamespace getVariable ["COMSPEC_LastScreenshotPath", ""];
        if (_last isEqualType "" && {_last isNotEqualTo ""}) then { _last } else { _stem + ".png" }
    } else {
        [] call _fnc_armaPngCapture
    };
    if (_png isEqualTo "") exitWith {
        missionNamespace setVariable ["COMSPEC_LastReconUploadOk", false, false];
        missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", "ERR|screenshot_rejected", false];
        ["COMSPEC_Error", ["Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen, puis reprenez la photo."]] call comspec_overwatch_connect_fnc_showNotification;
        false
    };
    private _ok = [_png] call _fnc_notifyPath;
    if (!_ok && {!_skipArmaShot} && {!([ ] call _fnc_isConnErr)}) then {
        [_png, _caption, _device, _feedId] spawn {
            params ["_png", "_caption", "_device", "_feedId"];
            uiSleep 2.4;
            [_png, _caption, _device, _feedId, true, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
        };
    };
    _ok
};

false
