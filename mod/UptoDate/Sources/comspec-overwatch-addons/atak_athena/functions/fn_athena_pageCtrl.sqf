/*
    Contrôle Athena par IDC : chrome (groupe page) ou enfant d’un écran 9770–9773.
*/
params ["_group", "_idc"];
if (isNull _group) exitWith { controlNull };
private _c = _group controlsGroupCtrl _idc;
if (!isNull _c) exitWith { _c };
private _found = controlNull;
{
    private _pg = _group controlsGroupCtrl _x;
    if (isNull _pg) then { continue };
    private _inner = _pg controlsGroupCtrl _idc;
    if (!isNull _inner) exitWith { _found = _inner };
} forEach [9770, 9771, 9772, 9773];
_found
