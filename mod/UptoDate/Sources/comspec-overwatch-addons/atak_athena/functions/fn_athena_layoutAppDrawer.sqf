/*
    Menu d’applications : fond charbon, libellés cyan.
    Ne pas redimensionner les tuiles — IceMan calcule icône et texte sur la taille d’origine.
    On recale seulement X/Y en 3 colonnes, comme BCE_fnc_ATAK_openMenu, avec la largeur réelle.
*/
if (!hasInterface) exitWith {};

params [["_open", true]];

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (isNull _disp) then { _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull]; };
if (isNull _disp) exitWith {};

private _grp = _disp displayCtrl (17000 + 4660);
if (isNull _grp) then { _grp = _disp displayCtrl 4660; };
if (isNull _grp) exitWith {};

private _charcoal = [0.071, 0.071, 0.071, 0.98];
private _cyan = [0.37, 0.78, 0.95, 1];

private _bg = _grp controlsGroupCtrl 9;
if (!isNull _bg) then {
    _bg ctrlSetBackgroundColor _charcoal;
};
_grp ctrlSetBackgroundColor _charcoal;

if (!_open) exitWith {};

private _drawerW = uiNamespace getVariable ["COMSPEC_ATAK_DrawerW", 0];
if (!(_drawerW isEqualType 0) || {_drawerW != _drawerW} || {_drawerW < 0.06}) then {
    if (!isNull _bg) then { _drawerW = (ctrlPosition _bg) param [2, 0]; };
};
if (!(_drawerW isEqualType 0) || {_drawerW != _drawerW} || {_drawerW < 0.06}) then {
    _drawerW = (ctrlPosition _grp) param [2, 0];
};
if (!(_drawerW isEqualType 0) || {_drawerW != _drawerW} || {_drawerW < 0.06}) exitWith {};

private _order = [];
if (!isNil "BCE_fnc_ATAK_getAPPs") then {
    _order = [] call BCE_fnc_ATAK_getAPPs;
};
if (!(_order isEqualType []) || {(count _order) < 1}) exitWith {};

private _cols = 3;
private _appW = _drawerW / _cols;
private _y = 0;
{
    private _ctrl = _grp controlsGroupCtrl (100 + _forEachIndex);
    if (isNull _ctrl) then { continue };

    private _col = _forEachIndex mod _cols;
    if (_col == 0 && {_forEachIndex >= _cols}) then {
        _y = _y + ((ctrlPosition _ctrl) param [3, 0]);
    };

    _ctrl ctrlSetPositionX (_appW * _col);
    _ctrl ctrlSetPositionY _y;
    _ctrl ctrlSetTextColor _cyan;
    _ctrl ctrlCommit 0;
} forEach _order;
