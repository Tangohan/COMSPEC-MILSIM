/*
    Bandeau bas : derniers événements. IDC 88800.
*/
params ["_disp", "_mapCtrl", "_vis"];
if (isNull _disp) exitWith {};
if (!(_vis isEqualType []) || {(count _vis) < 4}) exitWith {};
_vis params ["_vx", "_vy", "_vw", "_vh"];
if (!(_vw isEqualType 0) || {!(_vh isEqualType 0)}) exitWith {};
if (_vw <= 0 || {_vh <= 0}) exitWith {};

private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    if (isNull _d) exitWith { controlNull };
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};

private _rawEv = missionNamespace getVariable ["COMSPEC_MapTimeline", []];
if (!(_rawEv isEqualType [])) then { _rawEv = []; };
private _ev = +_rawEv;

private _show = (count _ev) > 0;
private _box = [_disp, 88800, "RscStructuredText"] call _fncEnsure;
if (isNull _box) exitWith {};

private _w = (_vw * 0.55) min 0.38;
private _h = (_vh * 0.07) max 0.028;
private _x0 = _vx + ((_vw - _w) / 2);
private _y0 = _vy + _vh - _h - (_vh * 0.02);
_box ctrlSetPosition [_x0, _y0, _w, _h];
_box ctrlSetBackgroundColor [0.05, 0.05, 0.05, 0.82];
private _bits = [];
private _cnt = count _ev;
private _startIdx = _cnt - 4;
if (_startIdx < 0) then { _startIdx = 0; };
private _slicedEv = _ev select [_startIdx, 4];
{
    _x params [["_prio", "DEFAULT"], ["_txt", ""]];
    private _col = switch (_prio) do {
        case "CRITICAL": { "#FF8A7A" };
        case "PRIORITY": { "#FFD080" };
        default { "#C8CDD2" };
    };
    _bits pushBack format ["<t size='0.52' color='%1'>%2</t>", _col, _txt];
} forEach _slicedEv;
_box ctrlSetStructuredText parseText (_bits joinString "   ·   ");
_box ctrlEnable false;
_box ctrlShow _show;
_box ctrlCommit 0;

private _oid = "";
private _rawOrders = missionNamespace getVariable ["COMSPEC_Orders", []];
if (!(_rawOrders isEqualType [])) then { _rawOrders = []; };
{
    private _st = toUpper (_x getOrDefault ["status", "PENDING"]);
    if (_st in ["PENDING", "DELIVERED", "ACK", "EXEC"]) exitWith {
        _oid = _x getOrDefault ["id", ""];
    };
} forEach (+_rawOrders);
missionNamespace setVariable ["COMSPEC_MapOrderId", _oid, false];
private _showAck = _oid isNotEqualTo "";
private _bw = 0.055;
private _bh = _h;
private _bx = _x0 + _w + 0.004;
{
    _x params ["_lab", "_act", "_off"];
    private _idc = 88810 + _off;
    private _b = [_disp, _idc, "RscButton"] call _fncEnsure;
    if (isNull _b) then { continue };
    _b ctrlSetPosition [_bx + (_off * (_bw + 0.003)), _y0, _bw, _bh];
    _b ctrlSetText _lab;
    _b ctrlSetFont "RobotoCondensedBold";
    _b ctrlSetFontHeight 0.014;
    _b ctrlSetBackgroundColor [0.12, 0.18, 0.14, 0.95];
    _b ctrlSetTextColor [0.94, 0.95, 0.96, 1];
    _b ctrlShow _showAck;
    _b ctrlEnable _showAck;
    _b ctrlCommit 0;
    if (isNil {_b getVariable "COMSPEC_AckWired"}) then {
        _b setVariable ["COMSPEC_AckWired", true];
        _b setVariable ["COMSPEC_AckAct", _act];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            [_ctrl getVariable ["COMSPEC_AckAct", "ACK"]] call comspec_overwatch_atak_athena_fnc_mapOrderAck;
        }];
    };
} forEach [["Accepté", "ACK", 0], ["Refus", "DECLINE", 1], ["Terminé", "COMPLETE", 2]];
