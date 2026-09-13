/*
    Injection des boutons SSE / ATAK / OVERWATCH : fiches d’édition
    personne / véhicule / groupe seulement. Pas de balayage des autres
    fenêtres Zeus (objets éditables, modules, filtres).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZeusAttrButtonsRegistered", false]) exitWith {};

private _hook = {
    params ["_display"];
    if (isNull _display) exitWith {};
    [{
        [_this] call comspec_overwatch_connect_fnc_zeusAttributesInject;
    }, _display, 0.12] call CBA_fnc_waitAndExecute;
};

{
    [_x, "onLoad", _hook] call CBA_fnc_addDisplayHandler;
} forEach [
    "RscDisplayAttributesMan",
    "RscDisplayAttributesVehicle",
    "RscDisplayAttributesVehicleEmpty",
    "RscDisplayAttributesGroup"
];

missionNamespace setVariable ["COMSPEC_ZeusAttrButtonsRegistered", true];
["INFO", "Zeus", "Boutons SSE / ATAK / OVERWATCH du panneau Éditer enregistrés"] call comspec_overwatch_connect_fnc_log;
