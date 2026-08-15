if (!hasInterface) exitWith {};

missionNamespace setVariable ["Iceman_ROIP_buildVersion", "2026.07.18-roip-v1", false];

call Iceman_fnc_roip_getState;

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_roip_connect",
    "Iceman_fnc_roip_disconnect",
    "Iceman_fnc_roip_onOpened",
    "Iceman_fnc_roip_onRadioSelect",
    "Iceman_fnc_roip_onTgSelect",
    "Iceman_fnc_roip_open",
    "Iceman_fnc_roip_refresh",
    "Iceman_fnc_roip_updatePanel"
];

[
    ["Iceman ATAK", "ROIP"],
    "roipOpen",
    ["ROIP: Open App", "Open the MPU-5 radio-over-IP controller when ATAK is displayed."],
    {call Iceman_fnc_roip_open},
    "",
    [],
    false
] call CBA_fnc_addKeybind;

[{
    call Iceman_fnc_roip_tick;
}, 1] call CBA_fnc_addPerFrameHandler;

[{
    if !(isNil "BCE_fnc_ATAK_getAPPs") then {
        [true, true] call BCE_fnc_ATAK_getAPPs;
    };
}, 1] call CBA_fnc_waitAndExecute;
