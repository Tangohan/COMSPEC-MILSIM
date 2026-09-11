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
    params ["_btn", "_show", "_text", "_action", "_bg"];
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
private _exec = [0.145, 0.145, 0.145, 0.98];
private _warn = [0.18, 0.05, 0.05, 0.98];
private _mute = [0.12, 0.14, 0.16, 0.98];

private _leftShow = false;
private _leftTxt = "";
private _leftAct = "";
private _leftBg = _ok;
private _rightShow = false;
private _rightTxt = "";
private _rightAct = "";
private _rightBg = _warn;

private _id = uiNamespace getVariable ["COMSPEC_ATAK_Task_selectedId", ""];
if (_id isNotEqualTo "") then {
    private _status = "";
    {
        if ((_x getOrDefault ["id", ""]) isEqualTo _id) exitWith {
            _status = toUpper (trim (_x getOrDefault ["status", "PENDING"]));
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
    if (_status isEqualTo "") then { _status = "PENDING"; };

    switch (_status) do {
        case "ACK": {
            _leftShow = true;
            _leftTxt = "En cours";
            _leftAct = "EXEC";
            _leftBg = _exec;
            _rightShow = true;
            _rightTxt = "Interrompre";
            _rightAct = "ABORT";
            _rightBg = _warn;
        };
        case "EXEC": {
            _leftShow = true;
            _leftTxt = "Terminer";
            _leftAct = "DONE";
            _leftBg = _ok;
            _rightShow = true;
            _rightTxt = "Interrompre";
            _rightAct = "ABORT";
            _rightBg = _warn;
        };
        case "FAILED";
        case "CANCELLED";
        case "DONE";
        case "CLOSED": {
            _leftShow = true;
            _leftTxt = "Supprimer";
            _leftAct = "DISMISS";
            _leftBg = _mute;
            _rightShow = false;
        };
        case "PENDING";
        case "DELIVERED": {
            _leftShow = true;
            _leftTxt = "Accepter";
            _leftAct = "ACCEPT";
            _leftBg = _ok;
            _rightShow = true;
            _rightTxt = "Refuser";
            _rightAct = "REFUSE";
            _rightBg = _warn;
        };
        default {
            _leftShow = true;
            _leftTxt = "Accepter";
            _leftAct = "ACCEPT";
            _leftBg = _ok;
            _rightShow = true;
            _rightTxt = "Refuser";
            _rightAct = "REFUSE";
            _rightBg = _warn;
        };
    };
};

[_btnLeft, _leftShow, _leftTxt, _leftAct, _leftBg] call _fnc_paint;
[_btnRight, _rightShow, _rightTxt, _rightAct, _rightBg] call _fnc_paint;
