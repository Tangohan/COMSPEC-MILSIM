private _state = call Iceman_fnc_drone_getState;
_state set ["selectMode", "target"];
["DRONE", "Tap the ATAK map to set the drone point.", 4] call cTab_fnc_addNotification;
call Iceman_fnc_drone_updatePanel;
