/*
    Controle du manifeste : page ATAK (scroll inclus) d'abord, sinon dialogue overlay.
*/
params ["_idc"];
if (!(_idc isEqualType 0)) exitWith { controlNull };

private _c = controlNull;
private _g = uiNamespace getVariable ["COMSPEC_ATAK_Manifest_group", controlNull];
if (!isNull _g) then {
    private _scroll = _g controlsGroupCtrl 9939;
    if (!isNull _scroll) then { _c = _scroll controlsGroupCtrl _idc; };
    if (isNull _c) then { _c = _g controlsGroupCtrl _idc; };
};
if (isNull _c) then {
    private _d = uiNamespace getVariable ["COMSPEC_FlightManifest_Display", displayNull];
    if (isNull _d) then { _d = findDisplay 9998; };
    if (!isNull _d) then {
        private _ovScroll = _d displayCtrl 1599;
        if (!isNull _ovScroll) then { _c = _ovScroll controlsGroupCtrl _idc; };
        if (isNull _c) then { _c = _d displayCtrl _idc; };
    };
};
_c
