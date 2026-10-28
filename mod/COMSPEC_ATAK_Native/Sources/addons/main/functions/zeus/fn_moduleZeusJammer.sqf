/*
    Module Zeus « ATAK · Brouilleur de zone » : brouilleur invisible à l'emplacement du module (rayon, force, durée,
    camp épargné), ou retrait des brouilleurs Zeus posés (dans le rayon ou tous). fn_zeusServer "jamArea" / "jamClear".
    Le brouilleur agit comme ceux de l'app Guerre électronique : débit, GPS, BFT, drones et relais.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _pos = _ctx get "pos";
deleteVehicle _logic;
if !(_ctx get "zeus") exitWith { true };
["ATAK · Brouilleur de zone", [
    ["COMBO", "Action", [["Poser un brouilleur ici", "add"], ["Retirer les brouilleurs Zeus dans le rayon", "clearNear"], ["Retirer tous les brouilleurs Zeus", "clearAll"]], "add"],
    ["SLIDER", "Rayon (m)", [50, 5000, 600]],
    ["SLIDER", "Force (%)", [1, 100, 80]],
    ["SLIDER", "Durée (min, 0 = sans fin)", [0, 180, 0]],
    ["COMBO", "Camp épargné", [["Aucun", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"]], ""]
], {
    params ["_vals", "_pos"];
    _vals params ["_act", "_rad", "_str", "_min", "_spare"];
    switch (_act) do {
        case "add": {
            ["comspec_atak_native_zeus", [player, "jamArea", [_pos, _rad, _str, _min * 60, _spare]]] call CBA_fnc_serverEvent;
            ["feedback", [format ["ATAK : brouilleur posé (%1 m, %2 %%)", _rad, _str]]] call comspec_atak_native_fnc_zeusMenu;
        };
        case "clearNear": {
            ["comspec_atak_native_zeus", [player, "jamClear", [_pos, _rad]]] call CBA_fnc_serverEvent;
            ["feedback", ["ATAK : brouilleurs Zeus du rayon retirés"]] call comspec_atak_native_fnc_zeusMenu;
        };
        default {
            ["comspec_atak_native_zeus", [player, "jamClear", [_pos, 0]]] call CBA_fnc_serverEvent;
            ["feedback", ["ATAK : tous les brouilleurs Zeus retirés"]] call comspec_atak_native_fnc_zeusMenu;
        };
    };
}, _pos, "Brouille les téléphones ATAK autour du module : plein effet au centre, plus faible vers le bord. Les brouilleurs du mod Crows Electronic Warfare sont aussi pris en compte par l'ATAK."] call comspec_atak_native_fnc_zeusForm;
true
