/*
    Tuile Athena : formulaire connexion natif (hors liaison) ou fiche + actions (liée).
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group || {!ctrlShown _group}) exitWith {};
private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
if (_page isNotEqualTo "" && {_page isNotEqualTo "athena"}) exitWith {};

(ctrlPosition _group) params ["", "", "_w", "_h"];
if (_w < 0.04 || {_h < 0.08}) exitWith {};

private _pad = ((_w * 0.05) max 0.004);
private _gap = ((_h * 0.012) max 0.002);
private _titleH = ((_h * 0.072) max 0.018);
private _accentH = ((_h * 0.008) max 0.002);
private _btnH = ((_h * 0.08) max 0.024);
private _fullW = (_w - (2 * _pad)) max 0.04;
private _halfW = ((_fullW - _gap) / 2) max 0.04;
private _actionH = ((_h * 0.075) max 0.022);

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
private _authState = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
private _ready = _authState isEqualTo "READY" || {_linked};

[_group, 9700, [0, 0, _w, _titleH], true] call _fncPos;
private _y = _titleH + _accentH + _gap;
[_group, 9734, [_pad, _y, _fullW, _btnH], true] call _fncPos;
_y = _y + _btnH + _gap;

private _bodyH = (_h - _y - _pad) max 0.08;

if (_allOk) then {
    private _statusH = (_bodyH - _actionH - _gap) max 0.08;
    [_group, 9701, [_pad, _y, _fullW, _statusH], true] call _fncPos;
    [_group, 9790, [0, _y, _w, _bodyH], false] call _fncPos;
    private _ay = _y + _statusH + _gap;
    [_group, 9805, [_pad, _ay, _halfW, _actionH], true] call _fncPos;
    [_group, 9806, [_pad + _halfW + _gap, _ay, _halfW, _actionH], true] call _fncPos;
} else {
    [_group, 9701, [0, 0, 0, 0], false] call _fncPos;
    [_group, 9790, [0, _y, _w, _bodyH], true] call _fncPos;
    [_group, 9805, [0, 0, 0, 0], false] call _fncPos;
    [_group, 9806, [0, 0, 0, 0], false] call _fncPos;
};

{
    [_group, _x, [0, 0, 0, 0], false] call _fncPos;
} forEach [9761, 9762, 9763, 9764, 9770, 9771, 9772, 9773, 9710, 9711, 9712, 9760, 9765, 9766, 9735];

private _linkBtn = [_group, 9734] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _linkBtn) then {
    if (_allOk) then {
        private _linkState = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
        if (_linkState isEqualTo "linked") then {
            _linkBtn ctrlSetText "Liaison OK — canal ouvert";
            _linkBtn ctrlSetBackgroundColor [0.06, 0.28, 0.14, 1];
        } else {
            _linkBtn ctrlSetText "Compte lié — canal interrompu";
            _linkBtn ctrlSetBackgroundColor [0.32, 0.18, 0.06, 1];
        };
    } else {
        if (_ready) then {
            _linkBtn ctrlSetText "Compte trouvé";
            _linkBtn ctrlSetBackgroundColor [0.08, 0.22, 0.18, 1];
        } else {
            _linkBtn ctrlSetText "Connexion Athena";
            _linkBtn ctrlSetBackgroundColor [0.16, 0.16, 0.16, 1];
        };
    };
};

// Entrer reste dans le formulaire (9790) — visible seulement hors liaison complète
private _enter = [_group, 9801] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
if (!isNull _enter) then {
    private _showEnter = !_allOk && {_ready || {_linked}};
    _enter ctrlShow _showEnter;
    _enter ctrlEnable _showEnter;
};
