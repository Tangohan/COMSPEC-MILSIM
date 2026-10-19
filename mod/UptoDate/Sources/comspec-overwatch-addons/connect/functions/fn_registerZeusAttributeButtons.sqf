/*
    Injection des boutons SSE / ATAK / OVERWATCH : fiches d’édition
    personne / véhicule / groupe seulement. Pas de balayage des autres
    fenêtres Zeus (objets éditables, modules, filtres).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZeusAttrButtonsRegistered", false]) exitWith {};

// Branchement par CfgEventHandlers.hpp (Extended_DisplayLoad_EventHandlers : RscDisplayAttributesMan, Vehicle,
// VehicleEmpty, Group -> fn_zeusAttributesInject, qui appelle aussi fn_fillZeusGroupId).
// Plus de CBA_fnc_addDisplayHandler ici : il pose un gestionnaire sur l'écran de mission ([type, code]), pas sur une
// classe de fenêtre ; appelé avec un nom de fenêtre, il levait « Unknown enum value: rscdisplayattributes… » à
// chaque lancement de mission (fnc_addDisplayHandler.sqf ligne 36).

missionNamespace setVariable ["COMSPEC_ZeusAttrButtonsRegistered", true];
["INFO", "Zeus", "Boutons SSE / ATAK / OVERWATCH du panneau Éditer enregistrés"] call comspec_overwatch_connect_fnc_log;
