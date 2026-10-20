#include "script_component.hpp"
/*
    Interaction, toutes machines : traces de tir pour le test GSR, pose des cônes (serveur).
*/

// Dernier tir de chaque unité (test de résidus) : horodatage serveur, diffusé au plus toutes les 30 s.
["CAManBase", "Fired", {
    params ["_unit"];
    if (!local _unit) exitWith {};
    if ((serverTime - (_unit getVariable ["comspec_sse_lastFired", -1e9])) < 30) exitWith {};
    _unit setVariable ["comspec_sse_lastFired", serverTime, true];
}] call CBA_fnc_addClassEventHandler;

if (isServer) then {
    ["comspec_sse_placeMarker", { _this call comspec_sse_fnc_placeMarkerServer; }] call CBA_fnc_addEventHandler;
};
