/*
    Carte opérateur : le cartouche 99887812 reste la source visible.
    Ici : roster de groupe compact (IDC 88650) sous le cartouche identité.
*/
params ["_disp", "_mapCtrl", "_vis"];
_vis params ["_vx", "_vy", "_vw", "_vh"];
private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};
private _state = missionNamespace getVariable ["COMSPEC_MapState", createHashMap];
private _units = _state getOrDefault ["units", []];
private _grp = group player;
private _alive = { alive _x } count (units _grp);
private _tot = count (units _grp);
private _gid = groupId _grp;
private _lines = [format ["<t font='RobotoCondensedBold' size='0.62' color='#5EC7F2'>%1  %2/%3</t>", _gid, _alive, _tot]];
{
    private _u = _x getOrDefault ["unit", objNull];
    if (isNull _u || {group _u isNotEqualTo _grp}) then { continue };
    private _dot = switch (_x getOrDefault ["medical", "NOMINAL"]) do {
        case "UNCONSCIOUS";
        case "CRITICAL": { "#FF8A7A" };
        case "WOUNDED": { "#FFD080" };
        default { "#7CFF9A" };
    };
    if (_x getOrDefault ["stale", false]) then { _dot = "#8aa0b4"; };
    _lines pushBack format [
        "<t size='0.55' color='%1'>●</t> <t size='0.55' color='#E8F0F4'>%2</t>",
        _dot,
        _x getOrDefault ["callsign", "—"]
    ];
} forEach _units;
private _html = _lines joinString "<br/>";
private _box = [_disp, 88650, "RscStructuredText"] call _fncEnsure;
private _w = (_vw * 0.28) min 0.18;
private _h = (_vh * 0.18) min 0.12;
private _x0 = _vx + (_vw * 0.012);
private _y0 = _vy + (_vh * 0.36);
_box ctrlSetPosition [_x0, _y0, _w, _h];
_box ctrlSetBackgroundColor [0.07, 0.07, 0.07, 0.86];
_box ctrlSetStructuredText parseText _html;
_box ctrlEnable false;
_box ctrlShow true;
_box ctrlCommit 0;
