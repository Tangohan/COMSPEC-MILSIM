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

Iceman_ATAK_DroneOps_state
