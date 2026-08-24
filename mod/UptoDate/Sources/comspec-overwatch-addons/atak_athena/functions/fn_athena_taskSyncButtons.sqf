/*
    Deux boutons d’action TASK, jamais superposés.
    À traiter : Accepter + Refuser
    Accepté  : En cours + Interrompre
    En cours : Interrompre
    Terminé / refusé / annulé : aucun
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
    params ["_btn", "_show", "_text", "_action", "_bg", "_bgFocus"];
    if (isNull _btn) exitWith {};
    _btn ctrlShow _show;
    _btn ctrlEnable _show;
    _btn ctrlSetFade ([1, 0] select _show);
    if (_show) then {
        _btn ctrlSetText _text;
        _btn setVariable ["COMSPEC_TaskAction", _action];
        _btn ctrlSetBackgroundColor _bg;
    } else {
        _btn ctrlSetText "";
        _btn setVariable ["COMSPEC_TaskAction", ""];
    };
    _btn ctrlCommit 0;
};

private _ok = [0.05, 0.16, 0.09, 0.98];
private _okF = [0.08, 0.26, 0.13, 1];
private _exec = [0.145, 0.145, 0.145, 0.98];
private _execF = [0.22, 0.22, 0.22, 1];
private _warn = [0.18, 0.05, 0.05, 0.98];
private _warnF = [0.28, 0.08, 0.08, 1];

private _leftShow = false;
private _leftTxt = "";
private _leftAct = "";
private _leftBg = _ok;
private _leftFg = _okF;
private _rightShow = false;
private _rightTxt = "";
private _rightAct = "";
private _rightBg = _warn;
private _rightFg = _warnF;

private _id = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
if (_id isNotEqualTo "") then {
    private _status = "";
    {
        if ((_x getOrDefault ["id", ""]) isEqualTo _id) exitWith {
            _status = toUpper (_x getOrDefault ["status", "PENDING"]);
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

    switch (_status) do {
        case "ACK": {
            _leftShow = true;
            _leftTxt = "En cours";
            _leftAct = "EXEC";
            _leftBg = _exec;
            _leftFg = _execF;
            _rightShow = true;
            _rightTxt = "Interrompre";
            _rightAct = "ABORT";
            _rightBg = _warn;
            _rightFg = _warnF;
        };
        case "EXEC": {
            _rightShow = true;
            _rightTxt = "Interrompre";
            _rightAct = "ABORT";
            _rightBg = _warn;
            _rightFg = _warnF;
        };
        case "FAILED";
        case "CANCELLED";
        case "DONE";
        case "CLOSED": {};
        default {
            _leftShow = true;
            _leftTxt = "Accepter";
            _leftAct = "ACCEPT";
            _leftBg = _ok;
            _leftFg = _okF;
            _rightShow = true;
            _rightTxt = "Refuser";
            _rightAct = "REFUSE";
            _rightBg = _warn;
            _rightFg = _warnF;
        };
    };
};

[_btnLeft, _leftShow, _leftTxt, _leftAct, _leftBg, _leftFg] call _fnc_paint;
[_btnRight, _rightShow, _rightTxt, _rightAct, _rightBg, _rightFg] call _fnc_paint;
