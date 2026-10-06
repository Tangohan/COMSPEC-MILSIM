/*
    Module « Connu des joueurs / vérité » : compare ce que les joueurs ont
    découvert sur l'entité à la vérité complète.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if (!hasInterface) exitWith { deleteVehicle _logic; true };

private _target = _ctx get "target";
if (isNull _target) exitWith {
    ["Connu / vérité : posez le module sur une entité SSE.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _view = [_target] call comspec_sse_fnc_getPlayerKnownView;
private _fog = _view getOrDefault ["fog", createHashMap];
if !(_fog isEqualType createHashMap) then { _fog = createHashMap; };
[format [
    "Connu des joueurs : %1 élément(s)\nVérité complète : %2 élément(s)\nNiveau d'exploitation : %3\nBrouillard — connu %4 · évalué %5 · confirmé %6 · infirmé %7",
    _view getOrDefault ["knownCount", 0],
    _view getOrDefault ["truthCount", 0],
    _view getOrDefault ["level", "?"],
    _fog getOrDefault ["KNOWN", 0],
    _fog getOrDefault ["ASSESSED", 0],
    _fog getOrDefault ["CONFIRMED", 0],
    _fog getOrDefault ["DISPROVEN", 0]
]] call comspec_sse_fnc_zeusNotify;

deleteVehicle _logic;
true
