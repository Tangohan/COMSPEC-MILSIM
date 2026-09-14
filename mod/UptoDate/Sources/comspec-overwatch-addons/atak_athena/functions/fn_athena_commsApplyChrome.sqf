/*
    Nettoie l’écran Messagerie : page IceMan Groups masquée, bandeau bas
    sans Live Feed, bouton Retour utilisable.
*/
if (!hasInterface) exitWith {};

["comms"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (!isNull _group) then {
    {
        if (_x getVariable ["IcemanGroupCtrl", false]) then {
            _x ctrlShow false;
            _x ctrlEnable false;
        } else {
            private _idc = ctrlIDC _x;
            if (_idc in [5, 6, 10, 11, 9900, 9902, 9904, 9905]) then {
                _x ctrlShow false;
                _x ctrlEnable false;
            };
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
