/*
    Bandeau haut : filtre ALL / GROUPE / ALLIÉS / INTEL / AIR / JTAC + recherche.
    IDC 88550–88559. Hors boussole (haut gauche) et Map Tools.
*/
params ["_disp", "_mapCtrl", "_vis"];
_vis params ["_vx", "_vy", "_vw", "_vh"];
private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};
private _barH = (_vh * 0.055) max 0.022;
private _barW = (_vw * 0.62) min 0.42;
private _barX = _vx + (_vw * 0.22);
private _barY = _vy + (_vh * 0.012);
private _bg = [_disp, 88550, "RscText"] call _fncEnsure;
_bg ctrlSetPosition [_barX, _barY, _barW, _barH];
_bg ctrlSetBackgroundColor [0.07, 0.07, 0.07, 0.88];
_bg ctrlEnable false;
_bg ctrlShow true;
_bg ctrlCommit 0;

private _filters = [
    ["ALL", "Tout", 88551],
    ["MY GROUP", "Groupe", 88552],
    ["FRIENDLY", "Alliés", 88553],
    ["INTEL", "Intel", 88554],
    ["AIR", "Air", 88555],
    ["JTAC", "JTAC", 88556]
];
private _fw = _barW / 8.6;
private _cur = missionNamespace getVariable ["COMSPEC_MapFilter", "ALL"];
private _i = 0;
{
    _x params ["_id", "_lab", "_idc"];
    private _b = [_disp, _idc, "RscButton"] call _fncEnsure;
    _b ctrlSetPosition [_barX + (_i * _fw), _barY, _fw - 0.002, _barH];
    _b ctrlSetText _lab;
    _b ctrlSetFont "RobotoCondensed";
    _b ctrlSetFontHeight (_barH * 0.55);
    private _on = (_cur isEqualTo _id);
    _b ctrlSetBackgroundColor (if (_on) then { [0.06, 0.22, 0.12, 1] } else { [0.14, 0.14, 0.14, 1] });
    _b ctrlSetTextColor [0.94, 0.95, 0.96, 1];
    _b ctrlShow true;
    _b ctrlEnable true;
    _b ctrlCommit 0;
    if (isNil {_b getVariable "COMSPEC_FilterWired"}) then {
        _b setVariable ["COMSPEC_FilterWired", true];
        _b setVariable ["COMSPEC_FilterId", _id];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            missionNamespace setVariable ["COMSPEC_MapFilter", _ctrl getVariable ["COMSPEC_FilterId", "ALL"], false];
        }];
    };
    _i = _i + 1;
} forEach _filters;

private _ed = [_disp, 88557, "RscEdit"] call _fncEnsure;
_ed ctrlSetPosition [_barX + (_i * _fw), _barY, _fw * 1.2, _barH];
_ed ctrlSetFont "RobotoCondensed";
_ed ctrlSetFontHeight (_barH * 0.5);
_ed ctrlSetBackgroundColor [0.09, 0.09, 0.09, 1];
_ed ctrlSetTextColor [0.9, 0.93, 0.95, 1];
if ((ctrlText _ed) isEqualTo "") then { _ed ctrlSetText "Rechercher…"; };
_ed ctrlShow true;
_ed ctrlEnable true;
_ed ctrlCommit 0;
if (isNil {_ed getVariable "COMSPEC_SearchWired"}) then {
    _ed setVariable ["COMSPEC_SearchWired", true];
    _ed ctrlAddEventHandler ["KeyDown", {
        params ["_ctrl", "_key"];
        if (_key isEqualTo 28 || {_key isEqualTo 156}) then {
            [ctrlText _ctrl] call comspec_overwatch_atak_athena_fnc_mapSearch;
            true
        } else {
            false
        }
    }];
};

private _wsBtn = [_disp, 88558, "RscButton"] call _fncEnsure;
private _ws = missionNamespace getVariable ["COMSPEC_MapWorkspace", "MISSION"];
private _wsLab = switch (_ws) do {
    case "SERVER": { "Serveur" };
    case "THEATER": { "Théâtre" };
    default { "Mission" };
};
_wsBtn ctrlSetPosition [_barX + ((_i + 1.22) * _fw), _barY, _fw * 1.15, _barH];
_wsBtn ctrlSetText _wsLab;
_wsBtn ctrlSetFont "RobotoCondensed";
_wsBtn ctrlSetFontHeight (_barH * 0.48);
_wsBtn ctrlSetBackgroundColor [0.16, 0.18, 0.22, 1];
_wsBtn ctrlSetTextColor [0.94, 0.95, 0.96, 1];
_wsBtn ctrlShow true;
_wsBtn ctrlEnable true;
_wsBtn ctrlCommit 0;
if (isNil {_wsBtn getVariable "COMSPEC_WsWired"}) then {
    _wsBtn setVariable ["COMSPEC_WsWired", true];
    _wsBtn ctrlAddEventHandler ["ButtonClick", {
        private _cur = missionNamespace getVariable ["COMSPEC_MapWorkspace", "MISSION"];
        private _next = switch (_cur) do {
            case "MISSION": { "SERVER" };
            case "SERVER": { "THEATER" };
            default { "MISSION" };
        };
        missionNamespace setVariable ["COMSPEC_MapWorkspace", _next, false];
    }];
};

