if (!hasInterface) exitWith {};

if (isNil "Iceman_ATAK_DroneOps_state") then {
    Iceman_ATAK_DroneOps_state = createHashMapFromArray [
        ["drone", objNull],
        ["target", []],
        ["altitude", 60],
        ["radius", 150],
        ["function", "move"],
        ["selectMode", ""],
        ["lastProtectTask", 0],
        ["lastProtectPos", []],
        ["lastScan", 0],
        ["lastContacts", createHashMap],
        ["contacts", []],
        ["markerCounter", 0]
    ];
};

{
    if (isNil {uiNamespace getVariable _x}) then {
        uiNamespace setVariable [_x, missionNamespace getVariable [_x, {}]];
    };
} forEach [
    "Iceman_fnc_drone_connect",
    "Iceman_fnc_drone_onOpened",
    "Iceman_fnc_drone_selectTarget",
    "Iceman_fnc_drone_sendTask",
    "Iceman_fnc_drone_updatePanel"
];

[
    "Iceman ATAK",
    "droneOpsOpen",
    ["Drone Ops: Open App", "Open Drone Ops when ATAK is already displayed."],
    {[nil, "Iceman_ATAK_DroneOps", -1] call BCE_fnc_ATAK_ChangeTool},
    "",
    [],
    false
] call cba_fnc_addKeybind;

call Iceman_fnc_drone_installActions;

[{
    call Iceman_fnc_drone_installOpenMapHandlers;
    call Iceman_fnc_drone_tick;
}, 2] call CBA_fnc_addPerFrameHandler;

[{
    if !(isNil "BCE_fnc_ATAK_getAPPs") then {
        [true, true] call BCE_fnc_ATAK_getAPPs;
    };
}, 1] call CBA_fnc_waitAndExecute;
