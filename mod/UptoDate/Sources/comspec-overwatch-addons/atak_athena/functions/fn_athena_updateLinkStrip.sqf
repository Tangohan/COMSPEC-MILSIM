/*
    Bandeau liaison sur le téléphone ATAK (sous la barre d’état cTab) :
    OK / NOK · débit estimé · taux d’erreur.
*/
if (!hasInterface) exitWith { false };

private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _disp) exitWith { false };

private _IDC = 99871;
private _ctrl = _disp displayCtrl _IDC;

// Ancre : batterie (2) ou en-tête (1)
private _bat = _disp displayCtrl 2;
if (isNull _bat) then { _bat = _disp displayCtrl 1; };
if (isNull _bat) exitWith { false };

(ctrlPosition _bat) params ["_bx", "_by", "_bw", "_bh"];
private _hdr = _disp displayCtrl 1;
private _hx = _bx;
private _hy = _by;
private _hw = _bw * 12;
private _hh = _bh;
if (!isNull _hdr) then {
    (ctrlPosition _hdr) params ["_x0", "_y0", "_w0", "_h0"];
    _hx = _x0;
    _hy = _y0;
    _hw = _w0;
    _hh = _h0;
};

// Ligne sous la barre noire native (ne masque pas horloge / signal)
private _sy = _hy + _hh;
private _sh = (_hh * 0.85) max 0.016;

if (isNull _ctrl) then {
    _ctrl = _disp ctrlCreate ["RscStructuredText", _IDC];
    if (isNull _ctrl) exitWith { false };
};

_ctrl ctrlSetPosition [_hx, _sy, _hw, _sh];
_ctrl ctrlSetBackgroundColor [0.02, 0.04, 0.05, 0.88];
_ctrl ctrlCommit 0;

// --- Métriques ---
[] call comspec_overwatch_connect_fnc_refreshLinkState;
private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
if (!(_state isEqualType "")) then { _state = "offline"; };
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
if (!(_ready isEqualType true)) then { _ready = false; };
private _lastHealth = missionNamespace getVariable ["COMSPEC_LastHealthOk", -1];
if (!(_lastHealth isEqualType 0)) then { _lastHealth = -1; };
private _healthFresh = (_lastHealth >= 0) && {(diag_tickTime - _lastHealth) < 90};

private _ok = (_state isEqualTo "linked") && {_ready || _healthFresh};
// Dégradé : encore « OK » mais on le signale via la couleur erreur
private _degraded = _state isEqualTo "degraded";

private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
if (!(_loss isEqualType 0)) then { _loss = 0; };
private _sentWin = _pkt getOrDefault ["packets_sent_window", 0];
private _measDur = _pkt getOrDefault ["measurement_duration", 0];
private _posInterval = missionNamespace getVariable ["comspec_overwatch_position_interval", 3];
if (!(_posInterval isEqualType 0)) then { _posInterval = 3; };
_posInterval = (_posInterval max 1) min 60;

private _bitrateKbps = 0;
if (_measDur > 0.5 && {_sentWin > 0}) then {
    _bitrateKbps = ((_sentWin * 0.45) / _measDur) * 8;
};
if (_bitrateKbps < 0.05 && {_ok || _degraded}) then {
    _bitrateKbps = (0.45 / _posInterval) * 8;
};

private _okTxt = if (_ok) then { "OK" } else { if (_degraded) then { "OK*" } else { "NOK" } };
private _okColor = if (_ok) then {
    if (_loss > 12) then { "#ffd27a" } else { "#7dffb0" }
} else {
    if (_degraded) then { "#ffd27a" } else { "#ff8a7a" }
};

private _rateTxt = if (!_ok && {!_degraded}) then {
    "—"
} else {
    if (_bitrateKbps < 0.1) then {
        "—"
    } else {
        if (_bitrateKbps < 10) then {
            format ["%1 kbit/s", (_bitrateKbps toFixed 1)]
        } else {
            format ["%1 kbit/s", round _bitrateKbps]
        }
    }
};

private _errTxt = if (!_ok && {!_degraded} && {_sentWin < 1}) then {
    "—"
} else {
    format ["perte %1%%", round _loss]
};
private _errColor = switch (true) do {
    case (_loss >= 25): { "#ff8a7a" };
    case (_loss >= 10): { "#ffd27a" };
    default { "#c8d8e8" };
};

private _html = format [
    "<t align='center' size='0.72' shadow='1'><t color='%1'>%2</t><t color='#6a7a88'> · </t><t color='#c8e8ff'>%3</t><t color='#6a7a88'> · </t><t color='%4'>%5</t></t>",
    _okColor,
    _okTxt,
    _rateTxt,
    _errColor,
    _errTxt
];
_ctrl ctrlSetStructuredText parseText _html;
_ctrl ctrlShow true;

// Teinte légère de l’icône signal (décorative cTab)
private _sig = _disp displayCtrl 3;
if (!isNull _sig) then {
    if (_ok) then {
        _sig ctrlSetTextColor [0.55, 0.95, 0.72, 1];
    } else {
        if (_degraded) then {
            _sig ctrlSetTextColor [1, 0.82, 0.48, 1];
        } else {
            _sig ctrlSetTextColor [1, 0.55, 0.48, 1];
        };
    };
};

true
