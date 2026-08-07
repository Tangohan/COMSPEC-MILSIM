params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };
private _target = objNull;
{ if (!isNull _x) exitWith { _target = _x; }; } forEach _units;
if (isNull _target) then {
    private _attached = attachedTo _logic;
    if (!isNull _attached) then { _target = _attached; };
};
if (isNull _target) exitWith {
    hint "Attacher le module à une entité SSE.";
    if (!isNull _logic) then { deleteVehicle _logic; };
    true
};
private _view = [_target] call comspec_sse_fnc_getPlayerKnownView;
hint format [
    "SPOIL CONTROL\nConnu joueurs : %1\nVérité : %2\nNiveau : %3\nFog K/A/C/D : %4/%5/%6/%7",
    _view getOrDefault ["knownCount", 0],
    _view getOrDefault ["truthCount", 0],
    _view getOrDefault ["level", "?"],
    (_view get "fog") getOrDefault ["KNOWN", 0],
    (_view get "fog") getOrDefault ["ASSESSED", 0],
    (_view get "fog") getOrDefault ["CONFIRMED", 0],
    (_view get "fog") getOrDefault ["DISPROVEN", 0]
];
if (!isNull _logic) then { deleteVehicle _logic; };
true
