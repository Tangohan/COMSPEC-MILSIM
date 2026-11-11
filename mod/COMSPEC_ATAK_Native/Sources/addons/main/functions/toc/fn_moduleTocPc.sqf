/*
    Module « Poste opérateur » (Eden et Zeus) : pose d'un coup un poste de travail prêt à servir (fn_tocPc « station ») :
    console et fauteuil d'opérateur du mod, combiné radio et casque, et dessus un poste PC fixe (un, deux ou trois écrans,
    ou écran incurvé ; console en arc pour ces deux derniers). Le poste est un poste TOC (action « PC du poste TOC ») et ses moniteurs sont des écrans TOC.
    Orientation : l'opérateur REGARDE dans la direction du module (la flèche du module dans Eden pointe vers ce qu'il
    regarde, par exemple le mur d'images) ; les moniteurs et le côté genoux de la console sont donc tournés vers lui, à
    l'opposé de la flèche (fn_tocHallBuild « facing »). Au Zeus, il tourne le dos à la caméra.
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
    ["COMBO", "Action", [["Poser un nouveau poste ici", "place"], ["Ouvrir le tableau des écrans (tous les écrans de la mission)", "manager"]], "place"],
    ["COMBO", "Poste", _types, (_types select 0) select 1],
    ["EDIT", "Nom", ""],
    ["COMBO", "Camp réservé", [["Tous les camps", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]], ""]
], {
    params ["_v", "_pos"];
    _v params ["_act", "_cls", "_name", "_side"];
    if (_act isEqualTo "manager") exitWith { [{ ["open"] call comspec_atak_native_fnc_tocManager; }, [], 0.2] call comspec_atak_native_fnc_waitAndExec; };
    if (_name isNotEqualTo "") then { _name = ["uniqueName", _name] call comspec_atak_native_fnc_tocManager; };
    // L'opérateur tourne le dos à la caméra du Zeus : les écrans se voient depuis elle.
    ["station", [_pos, (getPosATL curatorCamera) getDir _pos, _cls, _side, _name]] call comspec_atak_native_fnc_tocPc;
    ["feedback", ["ATAK : poste opérateur posé"]] call comspec_atak_native_fnc_zeusMenu;
}, _pos, "Chaque usage pose un nouveau poste. Pose un poste de travail complet : console, fauteuil, combiné radio, casque et poste PC fixe. Le poste ouvre le PC du poste TOC et ses moniteurs sont des écrans TOC (PIP du poste sur le moniteur principal)."] call comspec_atak_native_fnc_zeusForm;
true
