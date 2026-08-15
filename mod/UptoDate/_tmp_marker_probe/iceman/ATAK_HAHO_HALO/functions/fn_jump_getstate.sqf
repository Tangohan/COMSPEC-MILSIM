#include "..\script_component.hpp"

if (isNil "Iceman_ATAK_Jump_state") then {
    Iceman_ATAK_Jump_state = createHashMap;
};

{
    _x params ["_key", "_default"];
    if (isNil {Iceman_ATAK_Jump_state get _key}) then {
        Iceman_ATAK_Jump_state set [_key, _default];
    };
} forEach [
    ["jumpPoint", []],
    ["dropZone", []],
    ["waypoints", []],
    ["path", []],
    ["segments", []],
    ["ticks", []],
    ["distance", 0],
    ["canopyTime", 0],
    ["mode", "HAHO"],
    ["selectMode", ""],
    ["tab", "plan"],
    ["planned", false],
    ["requiredExitAGL", 0],
    ["requiredPullAGL", 0],
    ["avgGroundSpeedKph", 30],
    ["warnings", []]
];

Iceman_ATAK_Jump_state
