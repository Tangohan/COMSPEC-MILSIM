/*
    Arguments d'un module, quelle que soit la forme d'appel de BI :
      [logic, units, activated]                    module classique (is3DEN = 0)
      ["init", [logic, activated, curatorPlaced]]  module Eden (is3DEN = 1) : unités = objets synchronisés
      ["attributesChanged3DEN" …, [logic]]         édition dans Eden : rien à faire
      logic                                        appel direct
    _this call comspec_sse_fnc_moduleArgs -> [logic, units, activated], ou [] s'il n'y a rien à faire.
    Avant : les modules is3DEN = 1 recevaient ["init", [logic…]] et attendaient un objet en position 1, d'où
    une logique nulle et un module qui ne faisait jamais rien.
*/
if (_this isEqualType objNull) exitWith { [[_this, [], true], []] select (isNull _this) };
if !(_this isEqualType []) exitWith { [] };
private _a0 = _this param [0, objNull];
private _logic = objNull;
private _units = [];
private _activated = true;
switch (true) do {
    case (_a0 isEqualType objNull): {
        _logic = _a0;
        _units = _this param [1, []];
        _activated = _this param [2, true];
    };
    case (_a0 isEqualType "" && {(_this param [1, objNull]) isEqualType []}): {
        // Seul « init » applique le module ; les autres modes viennent de l'éditeur.
        if ((toLower _a0) isNotEqualTo "init") exitWith {};
        private _input = _this select 1;
        _logic = _input param [0, objNull];
        _activated = _input param [1, true];
        if (!isNull _logic) then { _units = synchronizedObjects _logic; };
    };
    case (_a0 isEqualType "" && {(_this param [1, objNull]) isEqualType objNull}): {
        _logic = _this param [1, objNull];
        _units = _this param [2, []];
        _activated = _this param [3, true];
    };
};
if (isNull _logic) exitWith { [] };
if !(_units isEqualType []) then { _units = []; };
if !(_activated isEqualType true) then { _activated = true; };
[_logic, _units select { _x isEqualType objNull && {!isNull _x} }, _activated]
