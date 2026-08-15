#include "script_component.hpp"

if (!hasInterface) exitWith {};

[
    "Iceman ATAK",
    "routeOpen",
    ["Route: Open App", "Open the Route app when ATAK is already displayed."],
    {[nil, "Iceman_ATAK_Route", -1] call BCE_fnc_ATAK_ChangeTool},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "routePlan",
    ["Route: Plan Current Route", "Plan the route using the current stored start/end points."],
    {call Iceman_fnc_route_startNavigation},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "routeClear",
    ["Route: Clear", "Clear the current ATAK route."],
    {call Iceman_fnc_route_clear},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "refreshApps",
    ["Refresh ATAK Apps", "Refresh custom ATAK app tiles and page text."],
    {if !(isNil "BCE_fnc_ATAK_getAPPs") then {[true, true] call BCE_fnc_ATAK_getAPPs}},
    "",
    [],
    false
] call cba_fnc_addKeybind;

Iceman_ATAK_Route_state = createHashMapFromArray [
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

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_route_addWaypoint",
    "Iceman_fnc_route_addWaypointFromInput",
    "Iceman_fnc_route_clear",
    "Iceman_fnc_route_clearWaypoints",
    "Iceman_fnc_route_draw",
    "Iceman_fnc_route_installDrawHooks",
    "Iceman_fnc_route_installOpenMapHandlers",
    "Iceman_fnc_route_onOpened",
    "Iceman_fnc_route_planFromInputs",
    "Iceman_fnc_route_removeWaypoint",
    "Iceman_fnc_route_selectMode",
    "Iceman_fnc_route_selectTab",
    "Iceman_fnc_route_setMot",
    "Iceman_fnc_route_startNavigation",
    "Iceman_fnc_route_updatePanel"
];

[{
    !(isNil "cTabOnDrawbftAndroidDsp")
}, {
    call Iceman_fnc_route_installDrawHooks;
    if !(isNil "BCE_fnc_ATAK_getAPPs") then {
        [true, true] call BCE_fnc_ATAK_getAPPs;
        [{[true, true] call BCE_fnc_ATAK_getAPPs}, 0.5] call CBA_fnc_waitAndExecute;
        [{[true, true] call BCE_fnc_ATAK_getAPPs}, 2] call CBA_fnc_waitAndExecute;
    };
}] call CBA_fnc_waitUntilAndExecute;

[{
    call Iceman_fnc_route_installOpenMapHandlers;
    call Iceman_fnc_route_updateMiniInfo;
    call Iceman_fnc_route_tickNavigation;
}, 1] call CBA_fnc_addPerFrameHandler;
