if (!hasInterface) exitWith {};

[] spawn {
    waitUntil {!isNil "Iceman_PiP_ViewDistance_distance"};
    setPiPViewDistance Iceman_PiP_ViewDistance_distance;
};
