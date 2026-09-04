/*
    Rail d’outils à droite de la carte visible (mesure, grille, route, zone, couches).
    IDC 88600–88610. Ne recouvre pas Map Tools (bas) ni le tiroir.
*/
params ["_disp", "_mapCtrl", "_vis"];
_vis params ["_vx", "_vy", "_vw", "_vh"];
private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};
private _btnW = (_vw * 0.055) max 0.028;
private _btnH = (_vh * 0.05) max 0.024;
private _x0 = _vx + _vw - _btnW - (_vw * 0.012);
private _y0 = _vy + (_vh * 0.14);
private _tools = [
    ["measure", "Mes.", 88600],
    ["coord", "Gril.", 88601],
    ["route", "Iti.", 88602],
    ["zone", "Zone", 88603],
    ["layers", "Couc.", 88604],
    ["bookmark", "Sign.", 88605]
];
private _active = missionNamespace getVariable ["COMSPEC_MapActiveTool", ""];
private _n = 0;
{
    _x params ["_id", "_lab", "_idc"];
    private _b = [_disp, _idc, "RscButton"] call _fncEnsure;
    _b ctrlSetPosition [_x0, _y0 + (_n * (_btnH + 0.004)), _btnW, _btnH];
    _b ctrlSetText _lab;
    _b ctrlSetFont "RobotoCondensedBold";
    _b ctrlSetFontHeight (_btnH * 0.42);
    private _on = (_active isEqualTo _id);
    _b ctrlSetBackgroundColor (if (_on) then { [0.18, 0.42, 0.52, 1] } else { [0.12, 0.12, 0.12, 0.94] });
    _b ctrlSetTextColor [0.95, 0.96, 0.97, 1];
    _b ctrlShow true;
    _b ctrlEnable true;
    _b ctrlCommit 0;
    if (isNil {_b getVariable "COMSPEC_ToolWired"}) then {
        _b setVariable ["COMSPEC_ToolWired", true];
        _b setVariable ["COMSPEC_ToolId", _id];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            private _id = _ctrl getVariable ["COMSPEC_ToolId", ""];
            if (_id isEqualTo "bookmark") then {
                [] call comspec_overwatch_atak_athena_fnc_mapBookmarks;
            } else {
                [_id] call comspec_overwatch_atak_athena_fnc_setActiveTool;
            };
        }];
    };
    _n = _n + 1;
} forEach _tools;
