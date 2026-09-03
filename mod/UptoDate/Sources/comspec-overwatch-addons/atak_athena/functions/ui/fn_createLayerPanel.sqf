/*
    Panneau des couches (masqué tant que l’outil « Couc. » n’est pas actif).
    IDC 88620–88639.
*/
params ["_disp", "_mapCtrl", "_vis"];
_vis params ["_vx", "_vy", "_vw", "_vh"];
private _show = (missionNamespace getVariable ["COMSPEC_MapActiveTool", ""]) isEqualTo "layers";
private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};
private _labels = [
    ["units", "Unités"], ["vehicles", "Véhicules"], ["objectives", "Objectifs"],
    ["player_markers", "Marqueurs"], ["athena", "Athena"], ["intel", "Intel"],
    ["photos", "Photos"], ["jtac", "JTAC"], ["cas", "CAS"], ["sigint", "SIGINT"],
    ["logistics", "Logistique"]
];
private _layers = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
private _panel = [_disp, 88620, "RscStructuredText"] call _fncEnsure;
private _pw = (_vw * 0.28) min 0.20;
private _ph = (_vh * 0.42) min 0.28;
private _px = _vx + _vw - _pw - (_vw * 0.08);
private _py = _vy + (_vh * 0.14);
_panel ctrlSetPosition [_px, _py, _pw, _ph];
_panel ctrlSetBackgroundColor [0.06, 0.06, 0.06, 0.92];
private _html = "<t font='RobotoCondensedBold' size='0.58' color='#5EC7F2'>Couches</t><br/>";
{
    _x params ["_key", "_lab"];
    private _on = _layers getOrDefault [_key, true];
    _html = _html + format [
        "<t size='0.52' color='%1'>[%2]</t> <t size='0.52' color='#E8F0F4'>%3</t><br/>",
        ["#8aa0b4", "#7CFF9A"] select _on,
        [" ", "x"] select _on,
        _lab
    ];
} forEach _labels;
_panel ctrlSetStructuredText parseText _html;
_panel ctrlEnable false;
_panel ctrlShow _show;
_panel ctrlCommit 0;

private _n = 0;
{
    _x params ["_key", "_lab"];
    private _idc = 88621 + _n;
    private _b = [_disp, _idc, "RscButton"] call _fncEnsure;
    private _rowH = _ph / 12;
    _b ctrlSetPosition [_px + 0.004, _py + (_rowH * (_n + 1.1)), _pw - 0.008, _rowH * 0.85];
    _b ctrlSetText "";
    _b ctrlSetBackgroundColor [0, 0, 0, 0.05];
    _b ctrlShow _show;
    _b ctrlEnable _show;
    _b ctrlCommit 0;
    if (isNil {_b getVariable "COMSPEC_LayerWired"}) then {
        _b setVariable ["COMSPEC_LayerWired", true];
        _b setVariable ["COMSPEC_LayerKey", _key];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            private _k = _ctrl getVariable ["COMSPEC_LayerKey", ""];
            private _ly = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
            _ly set [_k, !(_ly getOrDefault [_k, true])];
            missionNamespace setVariable ["COMSPEC_MapLayers", _ly, false];
        }];
    };
    _n = _n + 1;
} forEach _labels;
