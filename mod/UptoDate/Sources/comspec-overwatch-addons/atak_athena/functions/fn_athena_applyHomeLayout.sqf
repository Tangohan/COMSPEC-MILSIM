/*
    Place listes et boutons selon l’écran Athena : Journal / Alerter / Rapporter / Poste.
    Coordonnées = fraction du groupe réel (après anim ATAK). sizeEx écran trop petit
    rendait le journal invisible (flèche du filtre seule, rectangles noirs).
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group) exitWith {};

private _home = missionNamespace getVariable ["COMSPEC_Athena_HomeSection", "fil"];
if !(_home in ["fil", "alerter", "rapporter", "poste"]) then { _home = "fil"; };

(ctrlPosition _group) params ["", "", "_w", "_h"];
if (_w < 0.04 || {_h < 0.08}) exitWith {};

private _pad = ((_w * 0.025) max 0.003);
private _gap = ((_h * 0.012) max 0.0025);
private _titleH = ((_h * 0.062) max 0.016);
private _accentH = ((_h * 0.008) max 0.002);
private _statusH = ((_h * 0.048) max 0.014);
private _tabH = ((_h * 0.058) max 0.016);
private _btnH = ((_h * 0.055) max 0.015);
private _comboH = ((_h * 0.048) max 0.014);
private _fullW = (_w - (2 * _pad)) max 0.04;
private _tabGap = _pad * 0.45;
private _tabW = ((_fullW - (3 * _tabGap)) / 4) max 0.02;
private _btnGap = _pad * 0.5;
private _btnW = ((_fullW - (2 * _btnGap)) / 3) max 0.02;
private _font = (((_h * 0.042) max 0.024) min 0.040);

private _fncPos = {
    params ["_grp", "_idc", "_rect", "_show"];
    private _c = _grp controlsGroupCtrl _idc;
    if (isNull _c) exitWith { controlNull };
    _c ctrlSetPosition _rect;
    _c ctrlShow _show;
    _c ctrlCommit 0;
    _c
};

private _alerter = [9720, 9724, 9723, 9736, 9737, 9738];
private _rapporter = [9721, 9725, 9722, 9732, 9739, 9753];
private _poste = [9750, 9751, 9752, 9734, 9735, 9731];

{ private _c = _group controlsGroupCtrl _x; if (!isNull _c) then { _c ctrlShow false; }; } forEach (_alerter + _rapporter + _poste + [9760, 9715]);

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

switch (_home) do {
    case "alerter": {
        [_group, 9720, [_pad, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9724, [_pad + _btnW + _btnGap, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9723, [_pad + 2 * (_btnW + _btnGap), _y, _btnW, _btnH], true] call _fncPos;
        _y = _y + _btnH + _gap;
        [_group, 9736, [_pad, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9737, [_pad + _btnW + _btnGap, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9738, [_pad + 2 * (_btnW + _btnGap), _y, _btnW, _btnH], true] call _fncPos;
        _y = _y + _btnH + _gap;
    };
    case "rapporter": {
        [_group, 9721, [_pad, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9725, [_pad + _btnW + _btnGap, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9722, [_pad + 2 * (_btnW + _btnGap), _y, _btnW, _btnH], true] call _fncPos;
        _y = _y + _btnH + _gap;
        [_group, 9732, [_pad, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9739, [_pad + _btnW + _btnGap, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9753, [_pad + 2 * (_btnW + _btnGap), _y, _btnW, _btnH], true] call _fncPos;
        _y = _y + _btnH + _gap;
    };
    case "poste": {
        [_group, 9750, [_pad, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9751, [_pad + _btnW + _btnGap, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9752, [_pad + 2 * (_btnW + _btnGap), _y, _btnW, _btnH], true] call _fncPos;
        _y = _y + _btnH + _gap;
        [_group, 9734, [_pad, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9735, [_pad + _btnW + _btnGap, _y, _btnW, _btnH], true] call _fncPos;
        [_group, 9731, [_pad + 2 * (_btnW + _btnGap), _y, _btnW, _btnH], true] call _fncPos;
        _y = _y + _btnH + _gap;
    };
    default {
        private _combo = [_group, 9760, [_pad, _y, _fullW, _comboH], true] call _fncPos;
        if (!isNull _combo) then {
            _combo ctrlSetFontHeight _font;
            _combo ctrlSetTextColor [0.94, 0.95, 0.96, 1];
        };
        _y = _y + _comboH + _gap;
    };
};

private _remain = (_h - _y - _pad) max 0.08;
private _listH = (_remain * 0.58) max 0.05;
private _detH = (_remain - _listH - _gap) max 0.04;

private _list = [_group, 9710, [_pad, _y, _fullW, _listH], true] call _fncPos;
if (!isNull _list) then {
    _list ctrlSetFontHeight _font;
    _list ctrlSetTextColor [0.94, 0.95, 0.96, 1];
};
_y = _y + _listH + _gap;
[_group, 9711, [_pad, _y, _fullW, _detH], true] call _fncPos;

private _idle = [0.145, 0.145, 0.145, 1];
private _active = [0.06, 0.22, 0.12, 1];
{
    _x params ["_id", "_idc"];
    private _c = _group controlsGroupCtrl _idc;
    if (!isNull _c) then {
        _c ctrlSetBackgroundColor (if (_home isEqualTo _id) then { _active } else { _idle });
    };
} forEach [["fil", 9761], ["alerter", 9762], ["rapporter", 9763], ["poste", 9764]];
