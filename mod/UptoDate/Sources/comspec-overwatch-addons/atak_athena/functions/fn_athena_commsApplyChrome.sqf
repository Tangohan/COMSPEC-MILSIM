/*
    Nettoie l’écran Messagerie : page IceMan Groups masquée, bandeau bas
    sans Live Feed, une seule vue à la fois (liste OU fil).
*/
if (!hasInterface) exitWith {};

["comms"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (isNull _group) then {
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) then {
        _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
    };
    if (!isNull _display) then {
        private _apps = _display displayCtrl (17000 + 4650);
        if (!isNull _apps) then {
            {
                if ((toLower (ctrlClassName _x)) find "comspec_atak_comms" >= 0) exitWith {
                    _group = _x;
                    uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", _x];
                };
            } forEach (allControls _apps);
        };
    };
};

if (!isNull _group) then {
    private _view = missionNamespace getVariable ["COMSPEC_Comms_View", "list"];
    if !(_view in ["list", "thread"]) then { _view = "list"; };
    private _isList = _view isEqualTo "list";

    private _showCtrl = {
        params ["_c", "_show"];
        if (isNull _c) exitWith {};
        _c ctrlEnable _show;
        _c ctrlShow _show;
        _c ctrlSetFade ([1, 0] select _show);
        _c ctrlCommit 0;
    };

    [_group controlsGroupCtrl 9921, _isList] call _showCtrl;
    [_group controlsGroupCtrl 9922, _isList] call _showCtrl;
    [_group controlsGroupCtrl 9931, _isList] call _showCtrl;
    [_group controlsGroupCtrl 9927, _isList] call _showCtrl;
    [_group controlsGroupCtrl 9928, _isList] call _showCtrl;
    [_group controlsGroupCtrl 9930, !_isList] call _showCtrl;
    [_group controlsGroupCtrl 9934, !_isList] call _showCtrl;
    [_group controlsGroupCtrl 9923, !_isList] call _showCtrl;
    [_group controlsGroupCtrl 9924, !_isList] call _showCtrl;
    [_group controlsGroupCtrl 9925, !_isList] call _showCtrl;
    [_group controlsGroupCtrl 9926, !_isList] call _showCtrl;

    private _active = toLower (trim (missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"]));
    private _isCustom = !(_active in ["groupe", "commandement", "general", "jtac", "air", "squad", "global", "hq", "c2", "command", "group", ""]);
    [_group controlsGroupCtrl 9929, (!_isList) && {_isCustom}] call _showCtrl;

    {
        private _hideIce = false;
        if (_x getVariable ["IcemanGroupCtrl", false]) then {
            _hideIce = true;
        } else {
            private _idc = ctrlIDC _x;
            // 5/6/10/11 = ATAK_Message IceMan (titre, contacts, boîte, saisie noire).
            if (_idc in [5, 6, 10, 11, 9900, 9902, 9904, 9905]) then { _hideIce = true; };
        };
        if (_hideIce) then {
            _x ctrlEnable false;
            _x ctrlShow false;
            _x ctrlSetFade 1;
            _x ctrlCommit 0;
        };
    } forEach (allControls _group);
};

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _display) exitWith {};

private _buttonGrp = _display displayCtrl 46600;
if (isNull _buttonGrp) exitWith {};

private _bntBack = _buttonGrp controlsGroupCtrl 10;
private _bntEnt = _buttonGrp controlsGroupCtrl 11;
private _bntThird = _buttonGrp controlsGroupCtrl 12;
private _bntResult = _buttonGrp controlsGroupCtrl 13;
private _pos = ctrlPosition _buttonGrp;
private _slot = 0.04;
if ((count _pos) > 2 && {(_pos select 2) > 0}) then {
    _slot = (_pos select 2) / 4;
};

[
    [_bntBack, _bntEnt, _bntThird, _bntResult],
    [0, 0, _slot],
    false,
    true
] call comspec_overwatch_atak_athena_fnc_athena_commsFooter;
