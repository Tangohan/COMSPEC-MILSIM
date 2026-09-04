/*
    Menu contextuel désactivé : le clic droit reste aux outils carte IceMan.
*/
params [["_mapCtrl", controlNull], ["_world", []]];
if (isNull _mapCtrl) exitWith {};
private _disp = ctrlParent _mapCtrl;
if (isNull _disp) exitWith {};
private _n = 0;
while { _n < 16 } do {
    private _c = _disp displayCtrl (88900 + _n);
    if (!isNull _c) then {
        _c ctrlShow false;
        _c ctrlEnable false;
    };
    _n = _n + 1;
};
