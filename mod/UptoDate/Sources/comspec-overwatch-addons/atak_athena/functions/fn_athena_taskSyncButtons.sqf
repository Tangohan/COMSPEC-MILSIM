/*
    Deux boutons d’action TASK, jamais superposés.
    À traiter : Accepter + Refuser
    Accepté  : En cours + Interrompre
    En cours : Terminer + Interrompre
    Terminé / refusé / annulé : Supprimer (retire de la liste locale)
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
if (isNull _group) exitWith {};

private _btnLeft = _group controlsGroupCtrl 9904;
private _btnRight = _group controlsGroupCtrl 9906;
private _btnLegacyExec = _group controlsGroupCtrl 9905;
private _btnLegacyAbort = _group controlsGroupCtrl 9908;

{
    if (!isNull _x) then {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach [_btnLegacyExec, _btnLegacyAbort];

private _fnc_paint = {
    params ["_btn", "_show", "_text", "_action", "_bg", "_fg"];
    if (isNull _btn) exitWith {};
    _btn ctrlShow _show;
    _btn ctrlEnable _show;
    _btn ctrlSetFade ([1, 0] select _show);
    if (_show) then {
        _btn ctrlSetText _text;
        _btn ctrlSetTooltip _text;
        _btn setVariable ["COMSPEC_TaskAction", _action];
        _btn ctrlSetBackgroundColor _bg;
        if (!isNil "_fg") then {
            _btn ctrlSetTextColor _fg;
        };
    } else {
        _btn ctrlSetText "";
        _btn ctrlSetTooltip "";
        _btn setVariable ["COMSPEC_TaskAction", ""];
    };
    _btn ctrlCommit 0;
};

private _ok = [0.05, 0.16, 0.09, 0.98];
private _exec = [0.145, 0.145, 0.145, 0.98];
private _warn = [0.18, 0.05, 0.05, 0.98];
private _mute = [0.12, 0.14, 0.16, 0.98];
private _fgOk = [0.49, 1, 0.60, 1];
private _fgWarn = [1, 0.54, 0.48, 1];
private _fgMute = [0.85, 0.88, 0.90, 1];
private _fgExec = [0.94, 0.95, 0.96, 1];

private _leftShow = false;
private _leftTxt = "";
private _leftAct = "";
private _leftBg = _ok;
private _leftFg = _fgOk;
private _rightShow = false;
private _rightTxt = "";
private _rightAct = "";
private _rightBg = _warn;
private _rightFg = _fgWarn;

private _id = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
if (!(_id isEqualType "")) then { _id = str _id; };
_id = trim _id;

// Repli : lire la sélection liste si l’id mémoire est vide.
if (_id isEqualTo "") then {
    private _list = _group controlsGroupCtrl 9902;
    if (!isNull _list) then {
        private _sel = lbCurSel _list;
        if (_sel >= 0) then {
            _id = trim (str (_list lbData _sel));
            uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", _id];
        };
    };
};

if (_id isNotEqualTo "") then {
    private _status = "";
    {
        if (!(_x isEqualType createHashMap)) then { continue };
        if ((str (_x getOrDefault ["id", ""])) isEqualTo _id) exitWith {
            _status = toUpper (trim (_x getOrDefault ["status", "PENDING"]));
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
    if (_status isEqualTo "" || {_status isEqualTo "-"}) then { _status = "PENDING"; };

    switch (_status) do {
        case "ACK": {
            _leftShow = true;
            _leftTxt = "En cours";
            _leftAct = "EXEC";
            _leftBg = _exec;
            _leftFg = _fgExec;
            _rightShow = true;
            _rightTxt = "Interrompre";
            _rightAct = "ABORT";
            _rightBg = _warn;
            _rightFg = _fgWarn;
        };
        case "EXEC": {
            _leftShow = true;
            _leftTxt = "Terminer";
            _leftAct = "DONE";
            _leftBg = _ok;
            _leftFg = _fgOk;
            _rightShow = true;
            _rightTxt = "Interrompre";
            _rightAct = "ABORT";
            _rightBg = _warn;
            _rightFg = _fgWarn;
        };
        case "FAILED";
        case "CANCELLED";
        case "DONE";
        case "CLOSED": {
            _leftShow = true;
            _leftTxt = "Supprimer";
            _leftAct = "DISMISS";
            _leftBg = _mute;
            _leftFg = _fgMute;
            _rightShow = false;
        };
        case "PENDING";
        case "DELIVERED";
        default {
            _leftShow = true;
            _leftTxt = "Accepter";
            _leftAct = "ACCEPT";
            _leftBg = _ok;
            _leftFg = _fgOk;
            _rightShow = true;
            _rightTxt = "Refuser";
            _rightAct = "REFUSE";
            _rightBg = _warn;
            _rightFg = _fgWarn;
        };
    };
};

[_btnLeft, _leftShow, _leftTxt, _leftAct, _leftBg, _leftFg] call _fnc_paint;
[_btnRight, _rightShow, _rightTxt, _rightAct, _rightBg, _rightFg] call _fnc_paint;
