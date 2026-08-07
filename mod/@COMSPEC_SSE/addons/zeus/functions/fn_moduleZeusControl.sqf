params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };

private _target = objNull;
{ if (!isNull _x) exitWith { _target = _x; }; } forEach _units;
if (isNull _target) then {
    private _attached = attachedTo _logic;
    if (!isNull _attached) then { _target = _attached; };
};
if (isNull _target) then { _target = cursorObject; };

if (!isNull _target && {!isNil "comspec_sse_fnc_uiSetRecord"}) then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

if (!isNil "comspec_sse_fnc_uiOpenScreen") then {
    ["zeus"] call comspec_sse_fnc_uiOpenScreen;
} else {
    hint "UI Zeus SSE indisponible.";
};

if (!isNull _logic) then { deleteVehicle _logic; };
true
