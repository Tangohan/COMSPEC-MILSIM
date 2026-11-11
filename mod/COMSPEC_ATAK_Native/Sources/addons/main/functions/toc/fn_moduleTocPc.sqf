/*
    Module « Poste opérateur » (Eden et Zeus) : pose d'un coup un poste de travail prêt à servir (fn_tocPc « station ») :
    bureau, chaise et téléphone de table du jeu de base, et dessus un poste PC fixe (un, deux ou trois écrans, ou écran
    incurvé). Le poste est un poste TOC (action « PC du poste TOC ») et ses moniteurs sont des écrans TOC.
    L'opérateur regarde dans la direction du module (Eden) ; au Zeus, il tourne le dos à la caméra.
    Attributs : Poste (classe du poste PC), Camp réservé (vide : tous), Nom.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
if !(_ctx get "zeus") exitWith {
    if (isServer) then {
        ["station", [getPosATL _logic, getDir _logic, _logic getVariable ["Class", "COMSPEC_ATAK_TocPC_1"], _logic getVariable ["Side", ""], _logic getVariable ["Name", ""]]] call comspec_atak_native_fnc_tocPc;
    };
    true
};
private _pos = getPosATL _logic;
deleteVehicle _logic;
private _types = ["types"] call comspec_atak_native_fnc_tocPc;
if ((count _types) isEqualTo 0) exitWith { ["feedback", ["ATAK : aucun poste PC disponible"]] call comspec_atak_native_fnc_zeusMenu; true };
["Poste opérateur", [
    ["COMBO", "Poste", _types, (_types select 0) select 1],
    ["EDIT", "Nom", ""],
    ["COMBO", "Camp réservé", [["Tous les camps", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]], ""]
], {
    params ["_v", "_pos"];
    _v params ["_cls", "_name", "_side"];
    // L'opérateur tourne le dos à la caméra du Zeus : les écrans se voient depuis elle.
    ["station", [_pos, (getPosATL curatorCamera) getDir _pos, _cls, _side, _name]] call comspec_atak_native_fnc_tocPc;
    ["feedback", ["ATAK : poste opérateur posé"]] call comspec_atak_native_fnc_zeusMenu;
}, _pos, "Pose un poste de travail complet : bureau, chaise, téléphone de table et poste PC fixe. Le poste ouvre le PC du poste TOC et ses moniteurs sont des écrans TOC (PIP du poste sur le moniteur principal)."] call comspec_atak_native_fnc_zeusForm;
true
