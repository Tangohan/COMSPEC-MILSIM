/*
    Badge « X messages en attente de synchro » sur le téléphone.
    Visible dès qu’une transmission est tamponnée (hors couverture ou file de liaison).
*/
if (!hasInterface) exitWith { false };

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (isNull _disp) exitWith { false };

private _n = 0;
if (!isNil "comspec_overwatch_connect_fnc_pendingSyncCount") then {
    _n = [] call comspec_overwatch_connect_fnc_pendingSyncCount;
};
if (!(_n isEqualType 0)) then { _n = 0; };
_n = round _n;

private _IDC = 99872;
private _ctrl = uiNamespace getVariable ["COMSPEC_AtakQueueBadge", controlNull];
if (isNull _ctrl || {ctrlParent _ctrl isNotEqualTo _disp}) then {
    if (!isNull _ctrl) then { ctrlDelete _ctrl; };
    _ctrl = _disp ctrlCreate ["RscStructuredText", _IDC];
    uiNamespace setVariable ["COMSPEC_AtakQueueBadge", _ctrl];
};
if (isNull _ctrl) exitWith { false };

if (_n < 1) exitWith {
    _ctrl ctrlShow false;
    _ctrl ctrlCommit 0;
    false
};

private _strip = _disp displayCtrl 99871;
private _sx = 0;
private _sy = 0;
private _sw = 0.22;
private _sh = 0.028;
if (!isNull _strip && {ctrlShown _strip}) then {
    (ctrlPosition _strip) params ["_x0", "_y0", "_w0", "_h0"];
    _sx = _x0;
    _sw = _w0;
    _sh = (_h0 * 1.15) max 0.018;
    _sy = _y0 - _sh - 0.002;
} else {
    private _hdr = _disp displayCtrl 1;
    if (!isNull _hdr) then {
        (ctrlPosition _hdr) params ["_x0", "_y0", "_w0", "_h0"];
        _sx = _x0;
        _sw = _w0;
        _sy = _y0 + (_h0 * 1.15);
        _sh = (_h0 * 0.7) max 0.018;
    };
};

private _offline = false;
private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
if (_state in ["offline", "disabled"]) then { _offline = true; };
private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!isNil "_zoneFx" && {_zoneFx isEqualType createHashMap}) then {
    if (_zoneFx getOrDefault ["force_disconnect", false]) then { _offline = true; };
};
if (missionNamespace getVariable ["COMSPEC_LinkViaRelays", false]) then {
    if (!isNil "comspec_overwatch_connect_fnc_isNearLiveRelay") then {
        if !([] call comspec_overwatch_connect_fnc_isNearLiveRelay) then { _offline = true; };
    };
};

private _word = if (_n > 1) then { "messages" } else { "message" };
private _label = if (_offline) then {
    format ["%1 %2 en attente de synchro", _n, _word]
} else {
    format ["%1 %2 en cours de synchro", _n, _word]
};
private _col = if (_offline) then { "#FFE08A" } else { "#7CFF9A" };
private _bg = if (_offline) then { [0.18, 0.10, 0.02, 0.82] } else { [0.02, 0.10, 0.06, 0.72] };

_ctrl ctrlSetPosition [_sx, _sy, _sw, _sh];
_ctrl ctrlSetBackgroundColor _bg;
_ctrl ctrlSetStructuredText parseText format [
    "<t align='center' valign='middle' size='0.52' shadow='0' color='%1'>%2</t>",
    _col,
    _label
];
_ctrl ctrlSetTooltip _label;
_ctrl ctrlEnable false;
_ctrl ctrlShow true;
_ctrl ctrlCommit 0;
true
