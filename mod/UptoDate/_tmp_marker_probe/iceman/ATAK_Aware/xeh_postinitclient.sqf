if (!hasInterface) exitWith {};

[] spawn {
    waitUntil {
        sleep 0.25;
        !(isNil "Iceman_fnc_aware_install")
        && {!(isNil "Iceman_fnc_aware_drawBftMarkers")}
        && {!(isNil "Iceman_fnc_aware_followMiniMap")}
        && {!(isNil "Iceman_fnc_aware_drawHook")}
        && {!(isNil "Iceman_fnc_aware_installDrawHooks")}
        && {!(isNil "CBA_fnc_addPerFrameHandler")}
        && {!(isNil "cTabOnDrawbftAndroidDsp")}
    };

    call Iceman_fnc_aware_install;
};
