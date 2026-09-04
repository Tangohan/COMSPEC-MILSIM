/*
    Inspecteur d’entité sélectionnée (unité / marqueur). IDC 88700.
    Détail médical et radio uniquement ici.
*/
params ["_disp", "_mapCtrl", "_vis"];
_vis params ["_vx", "_vy", "_vw", "_vh"];
private _sel = missionNamespace getVariable ["COMSPEC_MapSelected", objNull];
private _mk = missionNamespace getVariable ["COMSPEC_MapSelectedMarker", ""];
private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};
private _box = [_disp, 88700, "RscStructuredText"] call _fncEnsure;
private _show = !isNull _sel || {_mk isNotEqualTo ""};
private _w = (_vw * 0.36) min 0.24;
private _h = (_vh * 0.28) min 0.18;
private _x0 = _vx + _vw - _w - (_vw * 0.012);
private _y0 = _vy + _vh - _h - (_vh * 0.14);
_box ctrlSetPosition [_x0, _y0, _w, _h];
_box ctrlSetBackgroundColor [0.06, 0.06, 0.06, 0.94];
if (_show) then {
    [_sel, _mk] call comspec_overwatch_atak_athena_fnc_setInspector;
    private _html = missionNamespace getVariable ["COMSPEC_MapInspectorHtml", ""];
    _box ctrlSetStructuredText parseText _html;
};
_box ctrlEnable false;
_box ctrlShow _show;
_box ctrlCommit 0;
