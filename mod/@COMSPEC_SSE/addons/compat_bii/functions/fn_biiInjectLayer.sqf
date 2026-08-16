/*
    Injecte une barre COMSPEC dans le dialogue BII-10 (sans override compileFinal).
    Boutons : Identify (scan), SSE, ouvrir fiche SEEK COMSPEC sur la cible BII.
*/
if (!hasInterface) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith { false };

private _disp = uiNamespace getVariable ["BII_Identifi_Dialog", displayNull];
if (isNull _disp) exitWith { false };
if (_disp getVariable ["comspec_sse_biiLayerReady", false]) exitWith { true };

private _screen = _disp displayCtrl 861001;
private _group = _disp displayCtrl 861011;
if (isNull _group) exitWith { false };

private _sx = (ctrlPosition _screen) # 0;
private _sy = (ctrlPosition _screen) # 1;
private _sw = (ctrlPosition _screen) # 2;
private _sh = (ctrlPosition _screen) # 3;

private _barH = _sh * 0.07;
private _y = _sy + _sh - _barH;
private _gap = _sw * 0.01;
private _bw = (_sw - (4 * _gap)) / 3;

private _mkBtn = {
    params ["_idc", "_x", "_text", "_code"];
    private _btn = _disp ctrlCreate ["RscButtonMenu", _idc];
    _btn ctrlSetPosition [_x, _y, _bw, _barH * 0.92];
    _btn ctrlSetText _text;
    _btn ctrlSetFontHeight (_barH * 0.38);
    _btn ctrlSetBackgroundColor [0.05, 0.22, 0.28, 0.92];
    _btn ctrlAddEventHandler ["ButtonClick", _code];
    _btn ctrlCommit 0;
    _btn
};

[861091, _sx + _gap, "ID / SEEK", {
    if (!isNil "BII_fnc_identifi_setTab") then { ["scan"] call BII_fnc_identifi_setTab; };
}] call _mkBtn;

[861092, _sx + _gap + _bw + _gap, "SSE", {
    if (!isNil "BII_fnc_identifi_setTab") then { ["sse"] call BII_fnc_identifi_setTab; };
}] call _mkBtn;

[861093, _sx + _gap + 2 * (_bw + _gap), "Fiche COMSPEC", {
    private _target = objNull;
    if (!isNil "BII_fnc_identifi_getState") then {
        private _state = call BII_fnc_identifi_getState;
        if (_state isEqualType createHashMap) then {
            _target = _state getOrDefault ["lastTarget", objNull];
        };
    };
    if (isNull _target) then { _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull]; };
    if (isNull _target) then { _target = cursorObject; };
    if (isNull _target) then { _target = player; };
    if (!isNil "comspec_sse_fnc_openSeek") then {
        [_target] call comspec_sse_fnc_openSeek;
    };
}] call _mkBtn;

_disp setVariable ["comspec_sse_biiLayerReady", true];
true
