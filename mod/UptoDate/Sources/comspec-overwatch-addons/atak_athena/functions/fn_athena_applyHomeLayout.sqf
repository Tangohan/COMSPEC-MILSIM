/*
    Place listes et boutons selon l’écran Athena : Journal / Alerter / Rapporter / Poste.
*/
private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group) exitWith {};

private _home = missionNamespace getVariable ["COMSPEC_Athena_HomeSection", "fil"];
if !(_home in ["fil", "alerter", "rapporter", "poste"]) then { _home = "fil"; };

private _phoneW = safezoneW * 0.8;
private _phoneH = _phoneW * 4 / 3;
private _posH = (60 / 2048) * _phoneH;
private _sizeH = ((626 - 60) / 2048) * _phoneH;
private _posW = (_sizeH * 0.56) / 3;

private _fncPos = {
    params ["_idc", "_xU", "_yU", "_wU", "_hU", "_show", "_grp", "_pw", "_ph"];
    private _c = _grp controlsGroupCtrl _idc;
    if (isNull _c) exitWith {};
    _c ctrlSetPosition [_xU * _pw, _yU * _ph, _wU * _pw, _hU * _ph];
    _c ctrlShow _show;
    _c ctrlCommit 0;
};

private _alerter = [9720, 9724, 9723, 9736, 9737, 9738];
private _rapporter = [9721, 9725, 9722, 9732, 9739, 9753];
private _poste = [9750, 9751, 9752, 9734, 9735, 9731];

{ private _c = _group controlsGroupCtrl _x; if (!isNull _c) then { _c ctrlShow false; }; } forEach (_alerter + _rapporter + _poste + [9760, 9715]);

private _combo = _group controlsGroupCtrl 9760;
private _listY = 2.32;
private _listH = 3.55;
private _detY = 5.95;
private _detH = 2.35;

switch (_home) do {
    case "alerter": {
        [9720, 0.06, 1.82, 0.92, 0.58, true, _group, _posW, _posH] call _fncPos;
        [9724, 1.04, 1.82, 0.92, 0.58, true, _group, _posW, _posH] call _fncPos;
        [9723, 2.02, 1.82, 0.92, 0.58, true, _group, _posW, _posH] call _fncPos;
        [9736, 0.06, 2.48, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9737, 1.04, 2.48, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9738, 2.02, 2.48, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        _listY = 3.08;
        _listH = 2.85;
        _detY = 6.01;
        _detH = 2.25;
    };
    case "rapporter": {
        [9721, 0.06, 1.82, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9725, 1.04, 1.82, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9722, 2.02, 1.82, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9732, 0.06, 2.40, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9739, 1.04, 2.40, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9753, 2.02, 2.40, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        _listY = 3.00;
        _listH = 2.90;
        _detY = 5.98;
        _detH = 2.28;
    };
    case "poste": {
        [9750, 0.06, 1.82, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9751, 1.04, 1.82, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9752, 2.02, 1.82, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9734, 0.06, 2.40, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9735, 1.04, 2.40, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        [9731, 2.02, 2.40, 0.92, 0.50, true, _group, _posW, _posH] call _fncPos;
        _listY = 3.00;
        _listH = 2.90;
        _detY = 5.98;
        _detH = 2.28;
    };
    default {
        if (!isNull _combo) then {
            _combo ctrlSetPosition [0.06 * _posW, 1.82 * _posH, 2.88 * _posW, 0.42 * _posH];
            _combo ctrlShow true;
            _combo ctrlCommit 0;
        };
        _listY = 2.32;
        _listH = 3.55;
        _detY = 5.95;
        _detH = 2.35;
    };
};

[9710, 0.06, _listY, 2.88, _listH, true, _group, _posW, _posH] call _fncPos;
[9711, 0.06, _detY, 2.88, _detH, true, _group, _posW, _posH] call _fncPos;

private _idle = [0.145, 0.145, 0.145, 1];
private _active = [0.06, 0.22, 0.12, 1];
{
    _x params ["_id", "_idc"];
    private _c = _group controlsGroupCtrl _idc;
    if (!isNull _c) then {
        _c ctrlSetBackgroundColor (if (_home isEqualTo _id) then { _active } else { _idle });
    };
} forEach [["fil", 9761], ["alerter", 9762], ["rapporter", 9763], ["poste", 9764]];
