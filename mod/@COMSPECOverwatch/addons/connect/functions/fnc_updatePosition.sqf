#include "..\script_component.hpp"

if (!hasInterface) exitWith {};
if (diag_tickTime < GVAR(nextUpdate)) exitWith {};

GVAR(nextUpdate) = diag_tickTime + 0.5;

private _pos = getPos player;
private _dir = direction player;
private _callSign = name player;
if (!isNil "callsign" && { typeName callsign == "STRING" }) then {
    _callSign = callsign;
};

private _data = [round (_pos select 0), round (_pos select 1), _dir, _callSign];
"COMSPECExtension" callExtension ["UpdatePosition", _data];
