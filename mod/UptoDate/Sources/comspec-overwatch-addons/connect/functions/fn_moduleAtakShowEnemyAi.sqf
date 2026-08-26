/*
    Module Eden : afficher les IA ennemies sur le poste ATAK.

    En éditeur : le module pose le réglage de mission.
    En mission : applique « Afficher dès le début » si choisi.
*/
private _logic = objNull;
private _activated = true;

if (_this isEqualType objNull) then {
    _logic = _this;
} else {
    if (!(_this isEqualType [])) exitWith { false };
    private _a0 = _this param [0, objNull];
    if (_a0 isEqualType objNull) then {
        _logic = _a0;
        private _a1 = _this param [1, true];
        private _a2 = _this param [2, true];
        if (_a2 isEqualType true) then { _activated = _a2; };
        if (_a1 isEqualType true && {!(_a2 isEqualType true)}) then { _activated = _a1; };
    } else {
        if (_a0 isEqualType "" && {(_this param [1, objNull]) isEqualType objNull}) then {
            _logic = _this param [1, objNull];
            _activated = _this param [3, true];
        };
    };
};

if (isNull _logic) exitWith { false };
if (!(_activated isEqualType true)) then { _activated = true; };
if (!_activated) exitWith { false };

if (is3DEN) exitWith { true };

private _mode = _logic getVariable ["ShowAtStart", "hidden"];
private _show = false;
if (_mode isEqualType true) then { _show = _mode; };
if (_mode isEqualType 0) then { _show = _mode > 0; };
if (_mode isEqualType "") then {
    _show = (toLower (trim _mode)) in ["show", "start", "1", "true", "oui"];
};

deleteVehicle _logic;

if (_show) then {
    [{
        [true] call comspec_overwatch_connect_fnc_setAtakShowEnemyAi;
    }, [], 2] call CBA_fnc_waitAndExecute;
};

true
