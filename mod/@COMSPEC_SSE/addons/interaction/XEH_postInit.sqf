#include "script_component.hpp"
/*
    Interaction, toutes machines : traces de tir pour le test GSR, pose des cônes (serveur).
*/

// Dernier tir de chaque unité (test de résidus) : horodatage serveur, diffusé au plus toutes les 5 min.
// Avec 30 s, chaque IA au contact diffusait sans cesse (≈ 5 diffusions/s pour 150 IA) une valeur lue par un test rare ;
// seuils du test à 20 et 45 min : 5 min d'imprécision au plus.
["CAManBase", "Fired", {
    params ["_unit"];
    if (!local _unit) exitWith {};
    if ((serverTime - (_unit getVariable ["comspec_sse_lastFired", -1e9])) < 300) exitWith {};
    _unit setVariable ["comspec_sse_lastFired", serverTime, true];
}] call CBA_fnc_addClassEventHandler;

if (isServer) then {
    ["comspec_sse_placeMarker", { _this call comspec_sse_fnc_placeMarkerServer; }] call CBA_fnc_addEventHandler;
};
