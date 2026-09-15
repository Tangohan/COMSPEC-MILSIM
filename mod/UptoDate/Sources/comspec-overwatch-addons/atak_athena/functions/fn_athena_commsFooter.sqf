/*
    Bandeau bas ATAK pour Messagerie : un seul bouton, sans Live Feed / Tourelle
    superposés. Dans un fil, ce bouton ramène à la liste des canaux.
*/
params ["_ctrlBnts", "_ctrlPOS", "_subMenu", "_interfaceInit"];
_ctrlBnts params [["_bntBack", controlNull], ["_bntEnt", controlNull], ["_bntThird", controlNull], ["_bntResult", controlNull]];

private _slot = 0;
if ((_ctrlPOS isEqualType []) && {(count _ctrlPOS) > 2}) then {
    _slot = _ctrlPOS select 2;
};
if (!(_slot isEqualType 0) || {_slot <= 0} || {_slot != _slot}) then { _slot = 0.04; };
private _full = (4 * _slot) max 0.04;
if (_full > 2) then { _full = 0.16; };

{
    if (!isNull _x) then {
        _x ctrlShow false;
        _x ctrlEnable false;
        _x ctrlSetFade 1;
        _x ctrlCommit 0;
    };
} forEach [_bntEnt, _bntThird, _bntResult];

private _inThread = (missionNamespace getVariable ["COMSPEC_Comms_View", "list"]) isEqualTo "thread";

if (!isNull _bntBack) then {
    _bntBack ctrlShow true;
    _bntBack ctrlEnable true;
    _bntBack ctrlSetFade 0;
    _bntBack ctrlSetPositionX 0;
    _bntBack ctrlSetPositionW _full;
    if (_inThread) then {
        _bntBack ctrlSetText "Canaux";
        _bntBack ctrlSetTooltip "Revenir à la liste des canaux.";
        _bntBack ctrlSetEventHandler ["ButtonClick", "[] call comspec_overwatch_atak_athena_fnc_athena_commsBack; true"];
    } else {
        _bntBack ctrlSetText "Retour";
        _bntBack ctrlSetTooltip "Revenir au tiroir des applications.";
        _bntBack ctrlSetEventHandler ["ButtonClick", "call BCE_fnc_ATAK_LastPage"];
    };
    _bntBack ctrlCommit 0;
};

private _grp = if (!isNull _bntBack) then { ctrlParentControlsGroup _bntBack } else { controlNull };
if (!isNull _grp) then {
    _grp ctrlEnable true;
    _grp ctrlShow true;
    _grp ctrlSetFade 0;
    _grp ctrlCommit 0;

    {
        private _extra = _grp getVariable [_x, controlNull];
        if (!isNull _extra) then {
            _extra ctrlShow false;
            _extra ctrlEnable false;
        };
    } forEach ["Iceman_ATAK_MapFeedButton", "Iceman_ATAK_FullFeedActionButton"];
};
