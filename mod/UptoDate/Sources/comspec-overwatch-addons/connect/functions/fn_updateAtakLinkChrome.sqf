/*
    Icône de liaison dans la barre d’état ATAK (heure / batterie / signal).
    Pas de panneau sur la carte : les barres passent au rouge si la liaison est perdue.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _disp) exitWith {};

private _fncCtrl = {
    params ["_idc"];
    private _c = _disp displayCtrl _idc;
    if (isNull _c) then { _c = _disp displayCtrl (17000 + _idc); };
    _c
};

private _lost = false;
private _remaining = 0;
private _disconnectInfo = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
if (_disconnectInfo isEqualType createHashMap) then {
    _lost = _disconnectInfo getOrDefault ["is_disconnected", false];
    _remaining = _disconnectInfo getOrDefault ["remaining_seconds", 0];
};

private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
if (!(_state isEqualType "")) then { _state = "offline"; };

private _sigInfo = createHashMap;
if (!isNil "comspec_overwatch_connect_fnc_atakSignalState") then {
    _sigInfo = [] call comspec_overwatch_connect_fnc_atakSignalState;
};
if (!(_sigInfo isEqualType createHashMap)) then { _sigInfo = createHashMap; };
private _bars = _sigInfo getOrDefault ["bars", -1];
private _sigColor = _sigInfo getOrDefault ["color", []];
private _sigTip = _sigInfo getOrDefault ["tip", ""];

private _color = [0.55, 0.95, 0.72, 1];
private _tip = "Liaison au poste";
if (_lost) then {
    _color = [1, 0.32, 0.26, 1];
    _tip = if (_remaining > 0) then {
        format ["Liaison perdue — reconnexion dans %1 s", _remaining]
    } else {
        "Liaison perdue"
    };
} else {
    if ((_sigInfo getOrDefault ["jammed", false]) || {_sigInfo getOrDefault ["destroyed", false]}) then {
        _color = [1, 0.32, 0.26, 1];
        if (_sigTip isNotEqualTo "") then { _tip = _sigTip; };
    } else {
        if (_bars isEqualType 0 && {_bars >= 0}) then {
            if (_sigColor isEqualType [] && {(count _sigColor) >= 3}) then { _color = _sigColor; };
            if (_sigTip isNotEqualTo "") then { _tip = _sigTip; };
        } else {
            if (_state in ["offline", "disabled"]) then {
                _color = [1, 0.32, 0.26, 1];
                _tip = "Hors liaison";
            } else {
                if (_state in ["degraded", "connecting"]) then {
                    _color = [1, 0.82, 0.48, 1];
                    _tip = if (_state isEqualTo "connecting") then { "Connexion…" } else { "Liaison dégradée" };
                };
            };
        };
    };
};

private _sig = [3] call _fncCtrl;
private _sat = [4] call _fncCtrl;
if (!isNull _sig) then {
    _sig ctrlSetTextColor _color;
    _sig ctrlSetTooltip _tip;
};
if (!isNull _sat) then {
    _sat ctrlSetTextColor _color;
    _sat ctrlSetTooltip _tip;
};

private _anchor = _sig;
if (isNull _anchor) then { _anchor = _sat; };
if (isNull _anchor) then { _anchor = [2] call _fncCtrl; };

private _badge = uiNamespace getVariable ["COMSPEC_AtakLinkChrome_Badge", controlNull];
if (isNull _badge || {ctrlParent _badge isNotEqualTo _disp}) then {
    _badge = _disp ctrlCreate ["RscPicture", 99887710];
    uiNamespace setVariable ["COMSPEC_AtakLinkChrome_Badge", _badge];
};
if (!isNull _badge) then {
    if (_lost && {!isNull _anchor}) then {
        (ctrlPosition _anchor) params ["_ax", "_ay", "_aw", "_ah"];
        private _bw = (_aw * 0.92) max 0.008;
        private _bh = (_ah * 0.92) max 0.008;
        _badge ctrlSetPosition [_ax + ((_aw - _bw) / 2), _ay + ((_ah - _bh) / 2), _bw, _bh];
        private _tex = "\a3\ui_f\data\igui\cfg\actions\ico_off_ca.paa";
        if (!(fileExists _tex)) then {
            _tex = "\a3\ui_f\data\map\diary\signal_ca.paa";
        };
        _badge ctrlSetText _tex;
        _badge ctrlSetTextColor [1, 0.28, 0.22, 1];
        _badge ctrlSetTooltip _tip;
        _badge ctrlEnable false;
        _badge ctrlShow true;
        _badge ctrlCommit 0;
    } else {
        _badge ctrlShow false;
        _badge ctrlCommit 0;
    };
};

// Barres de signal (façon réseau mobile) à gauche de l’icône native.
private _barN = if (_bars isEqualType 0 && {_bars >= 0}) then { _bars } else { -1 };
private _showBars = (!_lost) && {_barN >= 0} && {!isNull _sig};
private _barCol = if (_sigColor isEqualType [] && {(count _sigColor) >= 3}) then { _sigColor } else { _color };
private _emptyCol = [0.22, 0.28, 0.30, 0.55];
if (!isNull _sig && {_showBars}) then {
    (ctrlPosition _sig) params ["_sx", "_sy", "_sw", "_sh"];
    private _gap = (_sw * 0.12) max 0.0012;
    private _bw = (_sw * 0.18) max 0.0022;
    private _baseX = _sx - ((_bw + _gap) * 4) - (_sw * 0.08);
    for "_i" from 0 to 3 do {
        private _key = format ["COMSPEC_AtakSigBar_%1", _i];
        private _bar = uiNamespace getVariable [_key, controlNull];
        if (isNull _bar || {ctrlParent _bar isNotEqualTo _disp}) then {
            if (!isNull _bar) then { ctrlDelete _bar; };
            _bar = _disp ctrlCreate ["RscText", 99887720 + _i];
            uiNamespace setVariable [_key, _bar];
        };
        if (isNull _bar) then { continue };
        private _frac = (0.38 + (_i * 0.20)) min 1;
        private _bh = (_sh * _frac) max 0.004;
        private _by = _sy + (_sh - _bh);
        _bar ctrlSetPosition [_baseX + (_i * (_bw + _gap)), _by, _bw, _bh];
        _bar ctrlSetBackgroundColor (if (_i < _barN) then { _barCol } else { _emptyCol });
        _bar ctrlSetTooltip _tip;
        _bar ctrlEnable false;
        _bar ctrlShow true;
        _bar ctrlCommit 0;
    };
} else {
    for "_i" from 0 to 3 do {
        private _bar = uiNamespace getVariable [format ["COMSPEC_AtakSigBar_%1", _i], controlNull];
        if (!isNull _bar) then {
            _bar ctrlShow false;
            _bar ctrlCommit 0;
        };
    };
};
