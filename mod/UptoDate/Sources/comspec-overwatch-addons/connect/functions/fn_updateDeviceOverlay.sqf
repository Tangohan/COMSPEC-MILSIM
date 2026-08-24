/*
    Overlay terminal (écran cassé, éteint, gel, brouillage, liaison perdue)
    UNIQUEMENT sur l’écran du téléphone / tablette — jamais le viewport 3D.

    Les PNG 1536×1024 ne s’affichent pas en RscPicture (pas puissance de 2).
    Textures : .paa packés (img/overlays + img/atak-fx).
*/
if (!hasInterface) exitWith {};

private _fncHide = {
    private _fx = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Fx", controlNull];
    private _overlay = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];
    private _caption = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Caption", controlNull];
    if (!isNull _fx) then { _fx ctrlShow false; };
    if (!isNull _overlay) then { _overlay ctrlShow false; };
    if (!isNull _caption) then { _caption ctrlShow false; };
};

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

private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
private _zoneOut = false;
private _zoneName = "";
private _zoneType = "";
if (!isNil "_zoneFx" && {_zoneFx isEqualType createHashMap}) then {
    _zoneType = toLower (_zoneFx getOrDefault ["type", ""]);
    _zoneName = _zoneFx getOrDefault ["name", ""];
    _zoneOut = _zoneFx getOrDefault ["force_disconnect", false]
        || {_zoneType isEqualTo "no_coverage"};
};

private _needOverlay = _crashed || {!_connectionOk} || {!_canDisplay} || {!_screenOk} || _isDisconnected || _isCompromised || _zoneOut;

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_WebBrowser_Display", displayNull];
};
if (isNull _display) then {
    _display = findDisplay 9974;
};
if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
};

private _fx = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Fx", controlNull];
private _overlay = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];

if (isNull _display) exitWith { call _fncHide; };

if (!_needOverlay) exitWith { call _fncHide; };

private _fncResolveTex = {
    params ["_paa", ["_png", ""]];
    if (_paa isNotEqualTo "" && {fileExists _paa}) exitWith { _paa };
    if (_png isNotEqualTo "" && {fileExists _png}) exitWith { _png };
    _paa
};

private _fncIsPhoneRect = {
    params ["_p"];
    if (!(_p isEqualType []) || {(count _p) < 4}) exitWith { false };
    _p params ["", "", "_w", "_h"];
    if (_w < (0.12 * safezoneW) || {_h < (0.12 * safezoneH)}) exitWith { false };
    // Un rectangle quasi plein écran n’est PAS l’écran du téléphone.
    if (_w > (0.78 * safezoneW) && {_h > (0.82 * safezoneH)}) exitWith { false };
    true
};

private _fncScreenPos = {
    params ["_disp"];
    private _pos = [];

    private _web = _disp displayCtrl 9401;
    if (!isNull _web && {ctrlShown _web}) then {
        private _p = ctrlPosition _web;
        if ([_p] call _fncIsPhoneRect) then { _pos = _p; };
    };
    if (_pos isEqualTo []) then {
        private _mapNative = _disp displayCtrl 9410;
        if (!isNull _mapNative && {ctrlShown _mapNative}) then {
            private _p = ctrlPosition _mapNative;
            if ([_p] call _fncIsPhoneRect) then { _pos = _p; };
        };
    };

    private _mapCtrl = controlNull;
    if (_pos isEqualTo [] && {!isNil "cTab_fnc_getSettings"} && {!isNil "cTab_fnc_getFromPairs"}) then {
        private _mapName = ["cTab_Android_dlg", "mapType"] call cTab_fnc_getSettings;
        private _mapTypes = ["cTab_Android_dlg", "mapTypes"] call cTab_fnc_getSettings;
        private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
        if (_mapIdc isEqualType 0) then { _mapCtrl = _disp displayCtrl _mapIdc; };
    };
    if (isNull _mapCtrl) then {
        {
            private _c = _disp displayCtrl _x;
            if (isNull _c) then { continue };
            if ([ctrlPosition _c] call _fncIsPhoneRect) exitWith { _mapCtrl = _c; };
        } forEach [1201, 1200, 1202, 16];
    };

    private _menu = _disp displayCtrl 4660;
    private _boxes = [];
    { if (!isNull _x) then { _boxes pushBack (ctrlPosition _x); }; } forEach [_mapCtrl, _menu];
    if (_pos isEqualTo [] && {_boxes isNotEqualTo []}) then {
        private _x0 = 99; private _y0 = 99; private _x1 = -99; private _y1 = -99;
        {
            _x params ["_cx", "_cy", "_cw", "_ch"];
            _x0 = _x0 min _cx;
            _y0 = _y0 min _cy;
            _x1 = _x1 max (_cx + _cw);
            _y1 = _y1 max (_cy + _ch);
        } forEach _boxes;
        private _union = [_x0, _y0, (_x1 - _x0) max 0.2, (_y1 - _y0) max 0.2];
        if ([_union] call _fncIsPhoneRect) then { _pos = _union; };
    };

    if (_pos isEqualTo []) then {
        private _hubDisc = _disp displayCtrl 9200;
        if (!isNull _hubDisc) then {
            private _p = ctrlPosition _hubDisc;
            // Le panneau hub 9200 est déjà dans le cadre tablette.
            if ((_p select 2) > 0.08 && {(_p select 3) > 0.04}) then { _pos = _p; };
        };
    };

    _pos
};

private _pos = [_display] call _fncScreenPos;
if (!([_pos] call _fncIsPhoneRect)) then {
    private _hubDisc = _display displayCtrl 9200;
    if (!isNull _hubDisc) then { _pos = ctrlPosition _hubDisc; };
};

if (!(_pos isEqualType []) || {(count _pos) < 4} || {(_pos select 2) < 0.08} || {(_pos select 3) < 0.04}) exitWith {
    call _fncHide;
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
private _caption = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Caption", controlNull];
if (isNull _caption || {ctrlParent _caption isNotEqualTo _display}) then {
    ctrlDelete _caption;
    _caption = _display ctrlCreate ["RscStructuredText", 99887702];
    uiNamespace setVariable ["COMSPEC_DeviceOverlay_Caption", _caption];
};

private _title = "";
private _detail = "";
private _tex = "";
private _captionPlace = "none"; // none | top | bottom

if (_crashed) then {
    _title = "TERMINAL BLOQUÉ";
    _detail = "Redémarrage automatique en cours…";
    _tex = [
        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.paa",
        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.png"
    ] call _fncResolveTex;
    _captionPlace = "bottom";
} else {
    if (!_screenOk && {_powered}) then {
        _title = "ÉCRAN ENDOMMAGÉ";
        _detail = "Position seule — réparez avec un toolkit.";
        _tex = [
            "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.paa",
            "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.png"
        ] call _fncResolveTex;
        _captionPlace = "bottom";
    } else {
        if (!_connectionOk) then {
            _title = "APPAREIL HORS SERVICE";
            _detail = "Liaison coupée — réparation requise.";
            _tex = [
                "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.paa",
                "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.png"
            ] call _fncResolveTex;
            _captionPlace = "bottom";
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
                _captionPlace = "bottom";
            } else {
                if (_zoneOut) then {
                    _title = "AUCUNE COUVERTURE";
                    _detail = if (_zoneName isNotEqualTo "") then {
                        format ["Pas de liaison dans cette zone (%1).", _zoneName]
                    } else {
                        "Le terminal n’a plus de liaison ici."
                    };
                    _tex = [
                        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.paa",
                        "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.png"
                    ] call _fncResolveTex;
                    _captionPlace = "bottom";
                } else {
                    if (_isDisconnected) then {
                        _title = "";
                        _detail = if (_remaining > 0) then {
                            format ["Reconnexion estimée dans %1 s", _remaining]
                        } else {
                            "Aucune donnée transmise"
                        };
                        _tex = [
                            "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.paa",
                            "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png"
                        ] call _fncResolveTex;
                        _captionPlace = "top";
                    } else {
                        if (!_powered) then {
                            _title = "ATAK ÉTEINT";
                            _detail = "Rallumez l’appareil (interaction personnelle).";
                            _tex = [
                                "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.paa",
                                "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.png"
                            ] call _fncResolveTex;
                            _captionPlace = "bottom";
                        };
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
    _overlay ctrlShow false;
};

if (!isNull _caption) then {
    if (_captionPlace isEqualTo "none" || {_detail isEqualTo "" && {_title isEqualTo ""}}) then {
        _caption ctrlShow false;
    } else {
        _pos params ["_px", "_py", "_pw", "_ph"];
        private _ch = (_ph * 0.16) max 0.035;
        private _cy = if (_captionPlace isEqualTo "bottom") then { _py + _ph - _ch } else { _py };
        private _line = if (_title isEqualTo "") then {
            format ["<t align='center' size='0.92' color='#F4F7FA'>%1</t>", _detail]
        } else {
            format [
                "<t align='center' size='0.88' color='#F4F7FA'>%1</t><br/><t align='center' size='0.72' color='#C9D4DC'>%2</t>",
                _title,
                _detail
            ]
        };
        _caption ctrlSetPosition [_px, _cy, _pw, _ch];
        _caption ctrlSetBackgroundColor [0.02, 0.04, 0.08, 0.55];
        _caption ctrlSetStructuredText parseText _line;
        _caption ctrlEnable false;
        _caption ctrlShow true;
        _caption ctrlCommit 0;
    };
};
