/*
    Overlay terminal (écran cassé, éteint, gel, brouillage, liaison perdue)
    sur cTab Android / Hub Overwatch.

    Les PNG 1536×1024 ne s’affichent pas en RscPicture (pas puissance de 2).
    Textures : .paa packés (img/overlays + img/atak-fx).
*/
if (!hasInterface) exitWith {};

private _atak = [] call comspec_overwatch_connect_fnc_isAtakFunctional;
private _canDisplay = _atak getOrDefault ["can_display", true];
private _crashed = _atak getOrDefault ["device_crashed", false];
private _powered = _atak getOrDefault ["powered_on", true];
private _screenOk = _atak getOrDefault ["screen_ok", true];
private _connectionOk = _atak getOrDefault ["connection_ok", true];

private _disconnectInfo = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
private _isDisconnected = _disconnectInfo getOrDefault ["is_disconnected", false];
private _remaining = _disconnectInfo getOrDefault ["remaining_seconds", 0];

private _compromise = toLower (missionNamespace getVariable ["COMSPEC_CompromiseState", "none"]);
private _isCompromised = _compromise in ["captured", "compromised"];

private _needOverlay = _crashed || {!_connectionOk} || {!_canDisplay} || {!_screenOk} || _isDisconnected || _isCompromised;

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
};

private _fx = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Fx", controlNull];
private _overlay = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];

if (isNull _display) exitWith {
    if (!isNull _fx) then { _fx ctrlShow false; };
    if (!isNull _overlay) then { _overlay ctrlShow false; };
};

if (!_needOverlay) exitWith {
    if (!isNull _fx) then { _fx ctrlShow false; };
    if (!isNull _overlay) then { _overlay ctrlShow false; };
};

private _fncResolveTex = {
    params ["_paa", ["_png", ""]];
    if (_paa isNotEqualTo "" && {fileExists _paa}) exitWith { _paa };
    if (_png isNotEqualTo "" && {fileExists _png}) exitWith { _png };
    _paa
};

private _fncScreenPos = {
    params ["_disp"];
    private _pos = [];
    private _mapCtrl = controlNull;
    if (!isNil "cTab_fnc_getSettings" && {!isNil "cTab_fnc_getFromPairs"}) then {
        private _mapName = ["cTab_Android_dlg", "mapType"] call cTab_fnc_getSettings;
        private _mapTypes = ["cTab_Android_dlg", "mapTypes"] call cTab_fnc_getSettings;
        private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
        if (_mapIdc isEqualType 0) then { _mapCtrl = _disp displayCtrl _mapIdc; };
    };
    if (isNull _mapCtrl) then { _mapCtrl = _disp displayCtrl 1201; };
    private _menu = _disp displayCtrl 4660;
    private _boxes = [];
    { if (!isNull _x) then { _boxes pushBack (ctrlPosition _x); }; } forEach [_mapCtrl, _menu];
    if (_boxes isEqualTo []) exitWith { [safeZoneX, safeZoneY, safeZoneW, safeZoneH] };
    private _x0 = 99; private _y0 = 99; private _x1 = -99; private _y1 = -99;
    {
        _x params ["_cx", "_cy", "_cw", "_ch"];
        _x0 = _x0 min _cx;
        _y0 = _y0 min _cy;
        _x1 = _x1 max (_cx + _cw);
        _y1 = _y1 max (_cy + _ch);
    } forEach _boxes;
    [_x0, _y0, (_x1 - _x0) max 0.2, (_y1 - _y0) max 0.2]
};

private _pos = [_display] call _fncScreenPos;
private _hubDisp = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
if (!isNull _hubDisp && {_display isEqualTo _hubDisp}) then {
    private _hubDisc = _display displayCtrl 9200;
    if (!isNull _hubDisc) then { _pos = ctrlPosition _hubDisc; };
};

if (isNull _fx || {ctrlParent _fx isNotEqualTo _display}) then {
    ctrlDelete _fx;
    _fx = _display ctrlCreate ["RscPicture", 99887700];
    uiNamespace setVariable ["COMSPEC_DeviceOverlay_Fx", _fx];
};
if (isNull _overlay || {ctrlParent _overlay isNotEqualTo _display}) then {
    ctrlDelete _overlay;
    _overlay = _display ctrlCreate ["RscStructuredText", 99887701];
    uiNamespace setVariable ["COMSPEC_DeviceOverlay_Ctrl", _overlay];
};

private _title = "TERMINAL INDISPONIBLE";
private _detail = "";
private _tex = "";
private _bgAlpha = 0.12;

if (_crashed) then {
    _title = "TERMINAL BLOQUÉ";
    _detail = "Redémarrage automatique en cours…";
    _tex = [
        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.paa",
        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.png"
    ] call _fncResolveTex;
    _bgAlpha = 0.2;
} else {
    if (!_screenOk && {_powered}) then {
        _title = "ÉCRAN ENDOMMAGÉ";
        _detail = "Position seule possible — toolkit ACE pour réparer.";
        _tex = [
            "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.paa",
            "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.png"
        ] call _fncResolveTex;
        _bgAlpha = 0.05;
    } else {
        if (!_connectionOk) then {
            _title = "APPAREIL HORS SERVICE";
            _detail = "Liaison Athena coupée — réparation requise.";
            _tex = [
                "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.paa",
                "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png"
            ] call _fncResolveTex;
            _bgAlpha = 0.08;
        } else {
            if (_isCompromised) then {
                _title = if (_compromise isEqualTo "compromised") then {
                    "APPAREIL COMPROMIS"
                } else {
                    "APPAREIL CAPTURÉ"
                };
                _detail = "Données illisibles — clé ou contrôle incorrect.";
                _tex = [
                    "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.paa",
                    "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.png"
                ] call _fncResolveTex;
                _bgAlpha = 0.12;
            } else {
                if (_isDisconnected) then {
                    _title = "LIAISON ATAK PERDUE";
                    _detail = if (_remaining > 0) then {
                        format ["Reconnexion estimée dans %1 s", _remaining]
                    } else {
                        "Aucune donnée transmise"
                    };
                    _tex = [
                        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.paa",
                        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png"
                    ] call _fncResolveTex;
                    _bgAlpha = 0.06;
                } else {
                    if (!_powered) then {
                        _title = "ATAK ÉTEINT";
                        _detail = "Rallumez l’appareil (ACE · interaction personnelle).";
                        _tex = [
                            "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.paa",
                            "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.png"
                        ] call _fncResolveTex;
                        _bgAlpha = 0.25;
                    };
                };
            };
        };
    };
};

if (!isNull _fx) then {
    if (_tex isNotEqualTo "") then {
        _fx ctrlSetText _tex;
        _fx ctrlSetPosition _pos;
        _fx ctrlSetFade 0;
        _fx ctrlEnable false;
        _fx ctrlShow true;
        _fx ctrlCommit 0;
    } else {
        _fx ctrlShow false;
    };
};

if (!isNull _overlay) then {
    private _body = format [
        "<br/><br/><t align='center' size='1.35' color='#ff8a7a'>%1</t><br/><t align='center' size='0.95' color='#c8d4dc'>%2</t>",
        _title,
        _detail
    ];
    _overlay ctrlSetPosition _pos;
    _overlay ctrlSetBackgroundColor [0.02, 0.05, 0.08, _bgAlpha];
    _overlay ctrlSetStructuredText parseText _body;
    _overlay ctrlEnable false;
    _overlay ctrlShow true;
    _overlay ctrlCommit 0;
};
