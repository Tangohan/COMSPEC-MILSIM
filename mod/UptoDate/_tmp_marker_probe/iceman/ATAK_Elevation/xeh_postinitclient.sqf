#include "script_component.hpp"

if (!hasInterface) exitWith {};

[
    "Iceman ATAK",
    "elevationOpen",
    ["Elevation: Open App", "Open the Elevation app when ATAK is already displayed."],
    {[nil, "Iceman_ATAK_Elevation", -1] call BCE_fnc_ATAK_ChangeTool},
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "elevationToggleMode",
    ["Elevation: Toggle Mode", "Toggle between View Shed and Heatmap."],
    {
        private _state = call Iceman_fnc_elev_getState;
        private _mode = _state getOrDefault ["mode", "viewshed"];
        [["heatmap", "viewshed"] select (_mode == "heatmap")] call Iceman_fnc_elev_setMode;
    },
    "",
    [],
    false
] call cba_fnc_addKeybind;

[
    "Iceman ATAK",
    "elevationClear",
    ["Elevation: Clear Overlay", "Clear the current elevation overlay."],
    {call Iceman_fnc_elev_clear},
    "",
    [],
    false
] call cba_fnc_addKeybind;

Iceman_ATAK_Elevation_state = createHashMapFromArray [
    ["mode", "viewshed"],
    ["selectMode", ""],
    ["viewshedPoint", []],
    ["heatmapCenter", []],
    ["heightFt", missionNamespace getVariable ["Iceman_ATAK_Elevation_defaultHeightFt", 6]],
    ["radiusM", missionNamespace getVariable ["Iceman_ATAK_Elevation_defaultRadiusM", 500]],
    ["heatmapSizeM", missionNamespace getVariable ["Iceman_ATAK_Elevation_defaultHeatmapSizeM", 1000]],
    ["sampleM", missionNamespace getVariable ["Iceman_ATAK_Elevation_defaultSampleM", 80]],
    ["overlayType", ""],
    ["overlay", []],
    ["active", false],
    ["planning", false],
    ["planningId", -1],
    ["status", "Ready"]
];

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_elev_apply",
    "Iceman_fnc_elev_clear",
    "Iceman_fnc_elev_draw",
    "Iceman_fnc_elev_installDesktopShortcut",
    "Iceman_fnc_elev_installOpenMapHandlers",
    "Iceman_fnc_elev_onOpened",
    "Iceman_fnc_elev_setMode",
    "Iceman_fnc_elev_selectPoint",
    "Iceman_fnc_elev_updatePanel"
];

[{
    !(isNil "cTabOnDrawbftAndroidDsp")
}, {
    call Iceman_fnc_elev_installDrawHooks;
}] call CBA_fnc_waitUntilAndExecute;

[{
    !(isNil "BCE_fnc_ATAK_getAPPs")
}, {
    [true, true] call BCE_fnc_ATAK_getAPPs;
}] call CBA_fnc_waitUntilAndExecute;

call Iceman_fnc_elev_installDesktopShortcut;

[{
    call Iceman_fnc_elev_installOpenMapHandlers;
}, 1] call CBA_fnc_addPerFrameHandler;
