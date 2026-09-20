/*
    Controle du formulaire d'appui : page ATAK d'abord, sinon dialogue overlay.
*/
params ["_idc"];
if (!(_idc isEqualType 0)) exitWith { controlNull };

private _c = controlNull;
private _g = uiNamespace getVariable ["COMSPEC_ATAK_Cas_group", controlNull];
if (!isNull _g) then {
    _c = _g controlsGroupCtrl _idc;
};
if (isNull _c) then {
    private _d = uiNamespace getVariable ["COMSPEC_CasRequest_Display", displayNull];
    if (isNull _d) then { _d = findDisplay 9988; };
    if (!isNull _d) then { _c = _d displayCtrl _idc; };
};
_c
