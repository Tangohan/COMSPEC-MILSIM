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
        if (_a0 isEqualType "") then {
            private _a1 = _this param [1, objNull];
            if (_a1 isEqualType objNull) then {
                _logic = _a1;
                _activated = _this param [3, true];
            } else {
                // is3DEN = 1 : BI appelle [mode, [logic, activated, curatorPlaced]], mission comprise.
                if (_a1 isEqualType [] && {(toLower _a0) in ["init"]}) then {
                    _logic = _a1 param [0, objNull, [objNull]];
                    _activated = _a1 param [1, true, [true]];
                };
            };
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

// isGlobal = 1 : chaque machine lit la logique ; seule la machine propriétaire la supprime (sinon un client
// l'effacerait avant la lecture du serveur).
if (local _logic) then { deleteVehicle _logic; };

// Serveur seul : la variable publique atteint les clients (sinon N diffusions et des clients
// lents qui lisent une logique déjà supprimée).
if (_show && {isServer}) then {
    [{
        [true] call comspec_overwatch_connect_fnc_setAtakShowEnemyAi;
    }, [], 2] call CBA_fnc_waitAndExecute;
};

true
