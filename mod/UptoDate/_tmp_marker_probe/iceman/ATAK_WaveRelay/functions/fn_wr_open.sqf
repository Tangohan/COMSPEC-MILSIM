if !([player] call Iceman_fnc_wr_hasRadio) exitWith {
    ["WAVE RELAY", "MPU-5 radio not detected.", 3] call cTab_fnc_addNotification;
    false
};

[nil, "Iceman_ATAK_WaveRelay", -1] call BCE_fnc_ATAK_ChangeTool;
true
