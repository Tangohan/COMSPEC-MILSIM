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
if (isNull _badge) exitWith {};

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
