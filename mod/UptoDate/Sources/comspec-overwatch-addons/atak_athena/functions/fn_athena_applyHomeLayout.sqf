/*
    Place chrome + un seul écran Athena : Journal / Alerter / Rapporter / Poste.
    Les quatre RscControlsGroup (9770–9773) ne se superposent plus : un seul est visible.
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group || {!ctrlShown _group}) exitWith {};
private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_page isNotEqualTo "" && {_page isNotEqualTo "athena"}) exitWith {};

private _home = missionNamespace getVariable ["COMSPEC_Athena_HomeSection", "fil"];
if !(_home in ["fil", "alerter", "rapporter", "poste"]) then { _home = "fil"; };

(ctrlPosition _group) params ["", "", "_w", "_h"];
if (_w < 0.04 || {_h < 0.08}) exitWith {};

private _pad = ((_w * 0.025) max 0.003);
private _gap = ((_h * 0.012) max 0.0025);
private _titleH = ((_h * 0.062) max 0.016);
private _accentH = ((_h * 0.008) max 0.002);
private _statusH = ((_h * 0.086) max 0.024);
private _tabH = ((_h * 0.058) max 0.016);
private _fullW = (_w - (2 * _pad)) max 0.04;
private _tabGap = _pad * 0.45;
private _tabW = ((_fullW - (3 * _tabGap)) / 4) max 0.02;

private _fncPos = {
    params ["_grp", "_idc", "_rect", "_show"];
    private _c = [_grp, _idc] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (isNull _c) exitWith { controlNull };
    _c ctrlSetPosition _rect;
    _c ctrlShow _show;
    _c ctrlCommit 0;
    _c
};

[_group, 9700, [0, 0, _w, _titleH], true] call _fncPos;
private _y = _titleH;
[_group, 9701, [_pad, _y + _accentH, _fullW, _statusH], true] call _fncPos;
_y = _y + _accentH + _statusH + _gap;

private _tabX = _pad;
{
    [_group, _x, [_tabX, _y, _tabW, _tabH], true] call _fncPos;
    _tabX = _tabX + _tabW + _tabGap;
} forEach [9761, 9762, 9763, 9764];
_y = _y + _tabH + _gap;

private _pageH = (_h - _y) max 0.08;
private _pageIdc = switch (_home) do {
    case "alerter": { 9771 };
    case "rapporter": { 9772 };
    case "poste": { 9773 };
    default { 9770 };
};
{
    private _show = (_x isEqualTo _pageIdc);
    [_group, _x, [0, _y, _w, _pageH], _show] call _fncPos;
} forEach [9770, 9771, 9772, 9773];

private _idle = [0.145, 0.145, 0.145, 1];
private _active = [0.06, 0.22, 0.12, 1];
{
    _x params ["_id", "_idc"];
    private _c = [_group, _idc] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (!isNull _c) then {
        _c ctrlSetBackgroundColor (if (_home isEqualTo _id) then { _active } else { _idle });
    };
} forEach [["fil", 9761], ["alerter", 9762], ["rapporter", 9763], ["poste", 9764]];

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _linkBtn = [_group, 9734] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _linkBtn) then {
    if (_linked) then {
        private _cs = "";
        if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
            _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
        };
        if (_cs isEqualTo "") then { _cs = "compte"; };
        _linkBtn ctrlSetText format ["Lié — %1", _cs];
    } else {
        _linkBtn ctrlSetText "Connexion Athena";
    };
};
