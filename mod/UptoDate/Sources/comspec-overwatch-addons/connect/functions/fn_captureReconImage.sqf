/*
    Capture recon : chemin fourni (Photo Library / BCE) OU capture d’écran via l’extension.
    Params: [path, caption, deviceType, feedId]
    Retour: true si l’extension a accepté l’envoi (OK|…), false sinon.
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
private _zone = createHashMap;
if (missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false]) then {
    private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
    private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
    private _disc = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
    _zone = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
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
                private _zoneType = if (_zone isEqualType createHashMap) then { toLower (_zone getOrDefault ["type", ""]) } else { "" };
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

// Nettoie chemin Photo Library / BCE (guillemets, slashes).
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

private _isOk = {
    params ["_text"];
    if (!(_text isEqualType "")) then { _text = str _text; };
    _text = trim _text;
    if (_text isEqualTo "") exitWith { false };
    ((toUpper _text) find "OK") == 0
};

private _fnc_uploadPath = {
    params ["_uploadPath"];
    if (!(_uploadPath isEqualType "") || {_uploadPath isEqualTo ""}) exitWith { false };
    ["UploadReconImage", "attempt", [_uploadPath] call _fnc_basename, nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    private _raw = ["COMSPECExtension" callExtension [
        "UploadReconImage",
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
    private _ok = [_raw] call _isOk;
    missionNamespace setVariable ["COMSPEC_LastReconUploadOk", _ok, false];
    missionNamespace setVariable ["COMSPEC_LastReconUploadDetail", _raw, false];
    if (_ok) then {
        ["UploadReconImage", "ok", [_uploadPath] call _fnc_basename, nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    } else {
        ["UploadReconImage", "fail", str _raw, _raw, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
    };
    _ok
};

// Chemin fourni (souvent absolu Windows — ne pas tester avec fileExists Arma)
if (_path isNotEqualTo "") exitWith {
    private _ok = [_path] call _fnc_uploadPath;
    // Repli : nom de fichier seul (jpg↔png / mauvais profil résolus côté extension)
    if (!_ok) then {
        private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
        if ((_detail find "file_not_found") >= 0) then {
            private _bn = [_path] call _fnc_basename;
            if (_bn isNotEqualTo "" && {_bn isNotEqualTo _path}) then {
                _ok = [_bn] call _fnc_uploadPath;
            };
        };
    };
    if (_ok) then {
        ["COMSPEC_Info", ["Image de recon envoyée"]] call comspec_overwatch_connect_fnc_showNotification;
    } else {
        private _detail = toLower (str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]));
        private _msg = "Échec d’envoi de la photo vers Athena";
        if ((_detail find "file_not_found") >= 0) then {
            _msg = "Fichier photo introuvable — reprenez la capture";
        };
        if ((_detail find "file_too_large") >= 0 || {(_detail find "too_large") >= 0}) then {
            _msg = "Photo trop lourde — essayez une capture plus légère";
        };
        if ((_detail find "not_connected") >= 0 || {(_detail find "unauthorized") >= 0}) then {
            _msg = "Liaison Athena dégradée — reconnectez-vous puis réessayez";
        };
        if ((_detail find "file_empty") >= 0 || {(_detail find "read_failed") >= 0}) then {
            _msg = "Capture illisible — reprenez une nouvelle photo";
        };
        ["COMSPEC_Error", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
    };
    _ok
};

// Pas de fichier : capture dédiée puis upload de CE fichier (pas « la plus récente » du dossier Screenshots).
if (_path isEqualTo "") exitWith {
    private _snapStem = "COMSPEC_AthenaFeed";
    screenshot _snapStem;
    [_snapStem, _caption, _device, _feedId] spawn {
        params ["_stem", "_caption", "_device", "_feedId"];
        uiSleep 0.9;
        [_stem, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
    };
    true
};

false
