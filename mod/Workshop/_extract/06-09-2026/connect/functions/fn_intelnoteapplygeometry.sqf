/*
    Applique la taille et le rendu de la fiche FRS / FRM.

    Sur le téléphone ATAK, le cadre épouse le panneau d’application (idc 4660) :
    la fiche tient dans l’écran, pas en overlay plein jeu. Hors téléphone, une
    fenêtre proportionnelle reste centrée.
*/
if (!hasInterface) exitWith { false };

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith { false };

private _size = missionNamespace getVariable ["comspec_overwatch_frs_size", 1];
if (!(_size isEqualType 0)) then { _size = 1; };
_size = (_size max 0.55) min 1;

private _ui = missionNamespace getVariable ["comspec_overwatch_frs_ui", 1.2];
if (!(_ui isEqualType 0)) then { _ui = 1.2; };
_ui = (_ui max 0.75) min 1.45;

private _frameX = safezoneX;
private _frameY = safezoneY;
private _frameW = safezoneW;
private _frameH = safezoneH;
private _onPhone = false;

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (!isNull _parent) then {
    private _panel = _parent displayCtrl 4660;
    if (isNull _panel) then { _panel = _parent displayCtrl (17000 + 4660); };
    if (!isNull _panel) then {
        (ctrlPosition _panel) params ["_px", "_py", "_pw", "_ph"];
        if (_pw > 0.06 && {_ph > 0.08}) then {
            _frameX = _px;
            _frameY = _py;
            _frameW = _pw;
            _frameH = _ph;
            _onPhone = true;
        };
    };
};

if (!_onPhone) then {
    // Hors ATAK : fenêtre type téléphone, centrée (pas tout l’écran).
    _frameW = (safezoneW * 0.38) * _size;
    _frameH = (_frameW * 4 / 3) min (safezoneH * 0.92);
    _frameX = safezoneX + (safezoneW - _frameW) / 2;
    _frameY = safezoneY + (safezoneH - _frameH) / 2;
} else {
    if (_size < 0.995) then {
        private _nw = _frameW * _size;
        private _nh = _frameH * _size;
        _frameX = _frameX + (_frameW - _nw) / 2;
        _frameY = _frameY + (_frameH - _nh) / 2;
        _frameW = _nw;
        _frameH = _nh;
    };
};

private _fontMul = ([1.12, 1] select _onPhone) * _ui;

uiNamespace setVariable ["COMSPEC_IntelNote_Frame", [_frameX, _frameY, _frameW, _frameH, _size, _ui, _onPhone]];

private _dimmer = _disp displayCtrl 9600;
if (!isNull _dimmer) then {
    _dimmer ctrlSetPosition [safezoneX, safezoneY, safezoneW, safezoneH];
    // Transparent sur le téléphone : le chrome ATAK reste visible autour.
    _dimmer ctrlSetBackgroundColor [0, 0, 0, [0, 0.35] select (!_onPhone && {_size < 0.995})];
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
