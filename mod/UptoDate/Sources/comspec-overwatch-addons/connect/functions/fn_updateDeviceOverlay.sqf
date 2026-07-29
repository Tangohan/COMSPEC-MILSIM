/*
    Overlay terminal (écran cassé, éteint, gel, brouillage) sur cTab / Hub Overwatch.
    Indépendant du roleplay admin — inclut les effets Zeus.
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

private _needOverlay = _crashed || {!_connectionOk} || {!_canDisplay} || _isDisconnected || _isCompromised;

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
};

private _overlay = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];

if (isNull _display) exitWith {
    if (!isNull _overlay) then { _overlay ctrlShow false; };
};

if (!_needOverlay) exitWith {
    if (!isNull _overlay) then { _overlay ctrlShow false; };
};

if (isNull _overlay || {ctrlParent _overlay != _display}) then {
    _overlay = _display ctrlCreate ["RscStructuredText", 99887701];
    uiNamespace setVariable ["COMSPEC_DeviceOverlay_Ctrl", _overlay];
    _overlay ctrlSetPosition [0.04, 0.18, 0.92, 0.58];
    _overlay ctrlSetBackgroundColor [0.02, 0.05, 0.08, 0.94];
    _overlay ctrlCommit 0;
};

private _title = "TERMINAL INDISPONIBLE";
private _detail = "";

if (_crashed) then {
    _title = "TERMINAL BLOQUÉ";
    _detail = "Redémarrage automatique en cours…";
} else {
    if (!_connectionOk) then {
        _title = "APPAREIL HORS SERVICE";
        _detail = "Liaison Athena coupée — réparation requise.";
    } else {
        if (_isCompromised) then {
            _title = if (_compromise isEqualTo "compromised") then {
                "APPAREIL COMPROMIS"
            } else {
                "APPAREIL CAPTURÉ"
            };
            _detail = "Données illisibles — clé ou contrôle incorrect.";
        } else {
            if (_isDisconnected) then {
                _title = "LIAISON ATAK PERDUE";
                _detail = if (_remaining > 0) then {
                    format ["Reconnexion estimée dans %1 s", _remaining]
                } else {
                    "Aucune donnée transmise"
                };
            } else {
                if (!_powered) then {
                    _title = "ATAK ÉTEINT";
                    _detail = "Rallumez l’appareil (ACE · interaction personnelle).";
                } else {
                    if (!_screenOk) then {
                        _title = "ÉCRAN ENDOMMAGÉ";
                        _detail = "Position seule possible — toolkit ACE pour réparer.";
                    };
                };
            };
        };
    };
};

private _body = format [
    "<t align='center' size='1.35' color='#ff8a7a'>%1</t><br/><t align='center' size='0.95' color='#c8d4dc'>%2</t>",
    _title,
    _detail
];

_overlay ctrlSetStructuredText parseText _body;
_overlay ctrlShow true;
_overlay ctrlEnable false;
