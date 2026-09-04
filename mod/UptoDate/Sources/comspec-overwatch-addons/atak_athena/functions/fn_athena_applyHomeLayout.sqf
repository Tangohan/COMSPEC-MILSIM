/*
    Tuile Athena : bouton de liaison + journal / fiche.
    Les anciens écrans Journal / Alerter / Rapporter / Poste restent en IDC
    mais restent masqués.
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group || {!ctrlShown _group}) exitWith {};
private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_page isNotEqualTo "" && {_page isNotEqualTo "athena"}) exitWith {};

(ctrlPosition _group) params ["", "", "_w", "_h"];
if (_w < 0.04 || {_h < 0.08}) exitWith {};

private _pad = ((_w * 0.05) max 0.004);
private _gap = ((_h * 0.016) max 0.003);
private _titleH = ((_h * 0.072) max 0.018);
private _accentH = ((_h * 0.008) max 0.002);
private _btnH = ((_h * 0.10) max 0.028);
private _fullW = (_w - (2 * _pad)) max 0.04;

private _fncPos = {
    params ["_grp", "_idc", "_rect", "_show"];
    private _c = [_grp, _idc] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
    if (isNull _c) exitWith { controlNull };
    _c ctrlSetPosition _rect;
    _c ctrlShow _show;
    _c ctrlEnable _show;
    _c ctrlCommit 0;
    _c
};

private _linked = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
private _steamRaw = missionNamespace getVariable ["COMSPEC_SteamLinked", nil];
private _steamOk = if (isNil "_steamRaw") then { _linked } else { _steamRaw isEqualTo true };
private _allOk = _linked && {_steamOk};

[_group, 9700, [0, 0, _w, _titleH], true] call _fncPos;
private _y = _titleH + _accentH + _gap;
[_group, 9734, [_pad, _y, _fullW, _btnH], true] call _fncPos;
_y = _y + _btnH + _gap;
private _lineH = (_h * 0.052) max 0.015;
private _bodyH = if (_allOk) then {
    ((_lineH * 6.4) + _gap) min ((_h - _y - _pad) max 0.08)
} else {
    ((_lineH * 2.6) + _gap) min ((_h - _y - _pad) max 0.04)
};
[_group, 9701, [_pad, _y, _fullW, _bodyH], true] call _fncPos;

{
    [_group, _x, [0, 0, 0, 0], false] call _fncPos;
} forEach [9761, 9762, 9763, 9764, 9770, 9771, 9772, 9773, 9710, 9711, 9712, 9760, 9765, 9766, 9735];

private _linkBtn = [_group, 9734] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _linkBtn) then {
    if (_linked) then {
        _linkBtn ctrlSetText "Liaison OK";
        _linkBtn ctrlSetBackgroundColor [0.06, 0.28, 0.14, 1];
    } else {
        _linkBtn ctrlSetText "Connexion";
        _linkBtn ctrlSetBackgroundColor [0.16, 0.16, 0.16, 1];
    };
};
