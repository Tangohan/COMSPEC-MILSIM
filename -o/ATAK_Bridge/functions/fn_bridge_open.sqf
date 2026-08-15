if !([player] call Iceman_fnc_bridge_hasradio) exitWith {
    ["BRIDGE", "MPU-5 radio not detected.", 3] call Iceman_fnc_bridge_notify;
    false
};

[nil, "Iceman_ATAK_Bridge", -1] call BCE_fnc_ATAK_ChangeTool;
true
