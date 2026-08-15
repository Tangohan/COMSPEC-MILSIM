#include "..\script_component.hpp"

if (isNil "Iceman_ATAK_Route_state") then {
    Iceman_ATAK_Route_state = createHashMap;
};

private _state = Iceman_ATAK_Route_state;
{
    _x params ["_key", "_value"];
    if (isNil {_state get _key}) then {
        _state set [_key, _value];
    };
} forEach [
    ["start", []],
    ["end", []],
    ["waypoints", []],
    ["route", []],
    ["turns", []],
    ["distance", 0],
    ["remaining", 0],
    ["mot", "foot"],
    ["selectMode", ""],
    ["tab", "route"],
    ["active", false],
    ["planning", false],
    ["planningId", -1],
    ["nextTurn", 0],
    ["lastPromptTurn", -1]
];

_state
