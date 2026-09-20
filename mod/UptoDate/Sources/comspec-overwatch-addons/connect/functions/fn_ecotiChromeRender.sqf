/*
    Met à jour boussole, grille, distance regardée, heure et niveau d’éblouissement.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_EcotiChromeDisp", displayNull];
if (isNull _disp) exitWith {};

private _theme = [] call comspec_overwatch_connect_fnc_ecotiThemeColors;
private _badge = _theme getOrDefault ["badge", [1, 1, 1, 1]];
private _fnc_hex2 = {
    params ["_n"];
    private _v = round (((_n max 0) min 1) * 255);
    private _h = "0123456789ABCDEF";
    format ["%1%2", _h select [floor (_v / 16), 1], _h select [(_v % 16), 1]]
};
private _hex = format [
    "#%1%2%3",
    [_badge select 0] call _fnc_hex2,
    [_badge select 1] call _fnc_hex2,
    [_badge select 2] call _fnc_hex2
];

private _h = (positionCameraToWorld [0, 0, 0]) getDir (positionCameraToWorld [0, 0, 1]);
if (_h < 0) then { _h = _h + 360; };
private _names = ["N", "NE", "E", "SE", "S", "SW", "W", "NW"];
private _idx = (round (_h / 45)) mod 8;
if (_idx < 0) then { _idx = _idx + 8; };
private _left = _names select ((_idx + 7) mod 8);
private _mid = _names select _idx;
private _right = _names select ((_idx + 1) mod 8);
private _lDeg = ((round (_idx * 45)) + 360 - 45) mod 360;
private _rDeg = ((round (_idx * 45)) + 45) mod 360;
private _hRnd = round _h;

private _comp = format [
    "<t align='center' font='PuristaMedium' size='0.82' color='#A8C4B8' shadow='1'>%1  %2     <t color='%3' size='1.05'>%4  %5</t>     %6  %7</t>",
    _left, _lDeg, _hex, _mid, _hRnd, _right, _rDeg
];

private _gridRaw = mapGridPosition player;
private _grid = _gridRaw;
if ((count _gridRaw) >= 8) then {
    _grid = format ["%1-%2", _gridRaw select [0, 4], _gridRaw select [4, 4]];
};
private _gridTxt = format [
    "<t align='center' font='PuristaMedium' size='0.72' color='#C5D8CC' shadow='1'>%1</t>",
    _grid
];

private _cam = positionCameraToWorld [0, 0, 0];
private _look = screenToWorld [0.5, 0.5];
private _lookDist = 0;
if (_look isEqualType [] && {(count _look) >= 3}) then {
    _lookDist = _cam distance _look;
};
private _lookTxt = if (_lookDist > 8 && {_lookDist < 12000}) then {
    [_lookDist] call comspec_overwatch_connect_fnc_ecotiFormatDistance
} else {
    "—"
};

private _clock = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _flare = missionNamespace getVariable ["COMSPEC_EcotiGateLevel", 0];
if (!(_flare isEqualType 0)) then { _flare = 0; };
private _br = "A";
if (_flare > 0.62) then { _br = "C"; } else {
    if (_flare > 0.28) then { _br = "B"; };
};

private _fnc_plain = {
    params ["_idc", "_align", "_text"];
    private _c = _disp displayCtrl _idc;
    if (isNull _c) exitWith {};
    _c ctrlSetStructuredText parseText format [
        "<t align='%1' font='PuristaMedium' size='0.72' color='#C5D8CC' shadow='1'>%2</t>",
        _align, _text
    ];
};

private _compassOnly = missionNamespace getVariable ["comspec_overwatch_ecoti_compass_only", false];
if (!(_compassOnly isEqualType true)) then { _compassOnly = false; };

private _cComp = _disp displayCtrl 77401;
if (!isNull _cComp) then {
    _cComp ctrlShow true;
    _cComp ctrlSetStructuredText parseText _comp;
};

private _fnc_extra = {
    params ["_idc", "_show", "_text"];
    private _c = _disp displayCtrl _idc;
    if (isNull _c) exitWith {};
    _c ctrlShow _show;
    if (_show) then {
        _c ctrlSetStructuredText parseText _text;
    } else {
        _c ctrlSetStructuredText parseText "";
    };
};

if (_compassOnly) then {
    [77402, false, ""] call _fnc_extra;
    [77403, false, ""] call _fnc_extra;
    [77404, false, ""] call _fnc_extra;
    [77405, false, ""] call _fnc_extra;
    [77406, false, ""] call _fnc_extra;
} else {
    [77402, true, _gridTxt] call _fnc_extra;
    [77403, "left", _lookTxt] call _fnc_plain;
    [77404, "center", "COMSPEC ECOTI"] call _fnc_plain;
    [77405, "right", _clock] call _fnc_plain;
    [77406, "right", format ["Brightness %1", _br]] call _fnc_plain;
    {
        private _c = _disp displayCtrl _x;
        if (!isNull _c) then { _c ctrlShow true; };
    } forEach [77403, 77404, 77405, 77406];
};
