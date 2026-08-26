/*
    Module Eden : relevé complet du théâtre.

    En éditeur : ouvre la fenêtre si le module vient d’être posé (encore sélectionné).
    En mission : lance le relevé seulement si « Au début de la mission » est choisi,
    et uniquement pour le Zeus / le chef de mission.
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

if (is3DEN) then {
    if (!hasInterface) exitWith { true };
    private _sel = [];
    _sel = get3DENSelected "object";
    if (!(_sel isEqualType [])) then { _sel = []; };
    if (_logic in _sel) then {
        [false] call comspec_overwatch_connect_fnc_theaterSurveyShow;
    };
    true
} else {
    if (!hasInterface) exitWith {
        deleteVehicle _logic;
        false
    };

    private _mode = _logic getVariable ["RunAtStart", "manual"];
    private _runAtStart = false;
    if (_mode isEqualType true) then { _runAtStart = _mode; };
    if (_mode isEqualType 0) then { _runAtStart = _mode > 0; };
    if (_mode isEqualType "") then {
        _runAtStart = (toLower (trim _mode)) in ["start", "1", "true", "oui", "auto"];
    };

    private _isZeus = false;
    if (!isNull player) then {
        _isZeus = !isNull (getAssignedCuratorLogic player);
    };
    private _isAdmin = (!isMultiplayer) || {serverCommandAvailable "#kick"};

    deleteVehicle _logic;

    if (_runAtStart && {_isZeus || {_isAdmin}}) then {
        [{
            [true] call comspec_overwatch_connect_fnc_theaterSurveyShow;
        }, [], 3] call CBA_fnc_waitAndExecute;
    };
    true
};
