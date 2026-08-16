/*
    Overlay terminal (écran cassé, éteint, gel, brouillage) sur cTab / Hub Overwatch.

    Texture fissurée : img/atak-fx/broken-screen.png (cracks transparents — laisse la carte visible).
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

private _pos = [0.04, 0.18, 0.92, 0.58];

if (isNull _fx || {ctrlParent _fx != _display}) then {
    _fx = _display ctrlCreate ["RscPicture", 99887700];
    uiNamespace setVariable ["COMSPEC_DeviceOverlay_Fx", _fx];
    _fx ctrlSetPosition _pos;
    _fx ctrlCommit 0;
};

if (isNull _overlay || {ctrlParent _overlay != _display}) then {
    _overlay = _display ctrlCreate ["RscStructuredText", 99887701];
    uiNamespace setVariable ["COMSPEC_DeviceOverlay_Ctrl", _overlay];
    _overlay ctrlSetPosition _pos;
    _overlay ctrlCommit 0;
};

private _title = "TERMINAL INDISPONIBLE";
private _detail = "";
private _tex = "";
private _bgAlpha = 0.72;
private _brokenTex = "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.png";

if (_crashed) then {
    _title = "TERMINAL BLOQUÉ";
    _detail = "Redémarrage automatique en cours…";
    _tex = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.png";
    _bgAlpha = 0.55;
} else {
    // Écran cassé en priorité visuelle (position seule) — avant hors-service / brouillage.
    if (!_screenOk && {_powered}) then {
        _title = "ÉCRAN ENDOMMAGÉ";
        _detail = "Position seule possible — toolkit ACE pour réparer.";
        _tex = _brokenTex;
        _bgAlpha = 0.18;
    } else {
        if (!_connectionOk) then {
            _title = "APPAREIL HORS SERVICE";
            _detail = "Liaison Athena coupée — réparation requise.";
            _tex = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png";
            _bgAlpha = 0.5;
        } else {
            if (_isCompromised) then {
                _title = if (_compromise isEqualTo "compromised") then {
                    "APPAREIL COMPROMIS"
                } else {
                    "APPAREIL CAPTURÉ"
                };
                _detail = "Données illisibles — clé ou contrôle incorrect.";
                _tex = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_static_noise_ca.png";
                _bgAlpha = 0.6;
            } else {
                if (_isDisconnected) then {
                    _title = "LIAISON ATAK PERDUE";
                    _detail = if (_remaining > 0) then {
                        format ["Reconnexion estimée dans %1 s", _remaining]
                    } else {
                        "Aucune donnée transmise"
                    };
                    _tex = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png";
                    _bgAlpha = 0.45;
                } else {
                    if (!_powered) then {
                        _title = "ATAK ÉTEINT";
                        _detail = "Rallumez l’appareil (ACE · interaction personnelle).";
                        _tex = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_screen_off_ca.png";
                        _bgAlpha = 0.75;
                    };
                };
            };
        };
    };
};

if (_tex isNotEqualTo "") then {
    _fx ctrlSetText _tex;
    _fx ctrlSetPosition _pos;
    _fx ctrlSetFade 0;
    _fx ctrlShow true;
    _fx ctrlEnable false;
    _fx ctrlCommit 0;
} else {
    _fx ctrlShow false;
};

private _body = format [
    "<br/><br/><t align='center' size='1.35' color='#ff8a7a'>%1</t><br/><t align='center' size='0.95' color='#c8d4dc'>%2</t>",
    _title,
    _detail
];

_overlay ctrlSetPosition _pos;
_overlay ctrlSetBackgroundColor [0.02, 0.05, 0.08, _bgAlpha];
_overlay ctrlSetStructuredText parseText _body;
_overlay ctrlShow true;
_overlay ctrlEnable false;
_overlay ctrlCommit 0;
