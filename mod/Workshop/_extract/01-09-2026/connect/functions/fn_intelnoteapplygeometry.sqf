/*
    Applique la taille et le rendu de la fiche FRS / FRM (réglages addon).
    Le dialog est défini plein écran : on recadre ensuite tous les contrôles
    dans un rectangle centré, et on agrandit ou réduit le texte.
*/
if (!hasInterface) exitWith { false };

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith { false };

private _size = missionNamespace getVariable ["comspec_overwatch_frs_size", 1];
if (!(_size isEqualType 0)) then { _size = 1; };
_size = (_size max 0.55) min 1;

private _ui = missionNamespace getVariable ["comspec_overwatch_frs_ui", 1];
if (!(_ui isEqualType 0)) then { _ui = 1; };
_ui = (_ui max 0.75) min 1.45;

private _frameW = safezoneW * _size;
private _frameH = safezoneH * _size;
private _frameX = safezoneX + (safezoneW - _frameW) / 2;
private _frameY = safezoneY + (safezoneH - _frameH) / 2;
private _fontMul = _size * _ui;

uiNamespace setVariable ["COMSPEC_IntelNote_Frame", [_frameX, _frameY, _frameW, _frameH, _size, _ui]];

private _dimmer = _disp displayCtrl 9600;
if (!isNull _dimmer) then {
    _dimmer ctrlSetPosition [safezoneX, safezoneY, safezoneW, safezoneH];
    _dimmer ctrlSetBackgroundColor [0, 0, 0, [0, 0.42] select (_size < 0.995)];
    _dimmer ctrlCommit 0;
};

private _orig = _disp getVariable ["COMSPEC_IntelNote_OrigGeom", []];
if (!(_orig isEqualType []) || {_orig isEqualTo []}) then {
    _orig = [];
    {
        private _idc = ctrlIDC _x;
        if (_idc isEqualTo 9600) then { continue };
        private _cfg = ctrlConfig _x;
        private _sizeEx = 0;
        if (isNumber (_cfg >> "sizeEx")) then { _sizeEx = getNumber (_cfg >> "sizeEx"); };
        _orig pushBack [_x, ctrlPosition _x, _sizeEx];
    } forEach (allControls _disp);
    _disp setVariable ["COMSPEC_IntelNote_OrigGeom", _orig];
};

{
    _x params ["_ctrl", "_pos", "_sizeEx"];
    if (isNull _ctrl) then { continue };
    _pos params ["_px", "_py", "_pw", "_ph"];
    private _nx = _frameX + ((_px - safezoneX) / safezoneW) * _frameW;
    private _ny = _frameY + ((_py - safezoneY) / safezoneH) * _frameH;
    private _nw = (_pw / safezoneW) * _frameW;
    private _nh = (_ph / safezoneH) * _frameH;
    _ctrl ctrlSetPosition [_nx, _ny, _nw, _nh];
    if (_sizeEx > 0) then {
        _ctrl ctrlSetFontHeight (_sizeEx * _fontMul);
    };
    _ctrl ctrlCommit 0;
} forEach _orig;

{
    if (!isNull _x) then { ctrlDelete _x; };
} forEach (_disp getVariable ["COMSPEC_IntelNote_Chips", []]);
_disp setVariable ["COMSPEC_IntelNote_Chips", []];
_disp setVariable ["COMSPEC_IntelNote_ChipsKey", ""];

true
