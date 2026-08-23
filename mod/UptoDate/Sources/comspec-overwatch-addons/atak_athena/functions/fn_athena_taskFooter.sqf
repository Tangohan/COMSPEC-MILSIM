/*
    Bandeau bas ATAK pour TASK : un seul bouton Retour, sans Enter / Live Feed
    superposés (héritage du menu Messages).
*/
params ["_ctrlBnts", "_ctrlPOS", "_subMenu", "_interfaceInit"];
_ctrlBnts params [["_bntBack", controlNull], ["_bntEnt", controlNull], ["_bntThird", controlNull], ["_bntResult", controlNull]];

private _slot = 0;
if ((_ctrlPOS isEqualType []) && {(count _ctrlPOS) > 2}) then {
    _slot = _ctrlPOS select 2;
};
if (!(_slot isEqualType 0) || {_slot <= 0}) then { _slot = 0.04; };
private _full = 4 * _slot;

{
    if (!isNull _x) then {
        _x ctrlShow false;
        _x ctrlEnable false;
        _x ctrlSetFade 1;
        _x ctrlCommit 0;
    };
} forEach [_bntEnt, _bntThird, _bntResult];

if (!isNull _bntBack) then {
    _bntBack ctrlShow true;
    _bntBack ctrlEnable true;
    _bntBack ctrlSetFade 0;
    _bntBack ctrlSetText "Retour";
    _bntBack ctrlSetTooltip "Revenir au tiroir des applications.";
    _bntBack ctrlSetPositionX 0;
    _bntBack ctrlSetPositionW _full;
    _bntBack ctrlCommit 0;
};

private _grp = if (!isNull _bntBack) then { ctrlParentControlsGroup _bntBack } else { controlNull };
if (!isNull _grp) then {
    _grp ctrlEnable true;
    _grp ctrlShow true;
    _grp ctrlSetFade 0;
    _grp ctrlCommit 0;
};
