["Iceman_PiP_ViewDistance_distance", "SLIDER", ["PiP View Distance", "Local PiP render distance in meters. This is independent from the game's normal view distance options."], ["Iceman ATAK", "View Distance"], [100, 12000, 3000, 0], 0, {
    params ["_value"];
    if (hasInterface) then {
        setPiPViewDistance _value;
    };
}, false] call CBA_fnc_addSetting;
