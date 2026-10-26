/*
    Module « ATAK · Écran COMSPEC » (Eden et Zeus) : les objets synchronisés (Eden) ou l'objet sous le module (Zeus)
    deviennent des écrans où l'app Briefing diffuse ses diapositives (fn_screenCast).
    Attributs : Nom, Portée (m) à laquelle le téléphone détecte l'écran, Camp qui peut le piloter, Texture (index de la
    sélection qui reçoit l'image, 0 sur la plupart des TV, écrans et projecteurs), contrôle par ACE ou la molette.
    Zeus : lâché sur un écran déjà désigné, le formulaire propose aussi de le retirer.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = _ctx get "targets";
if ((count _targets) isEqualTo 0 && {!isNull (_ctx getOrDefault ["near", objNull])}) then { _targets = [_ctx get "near"]; };
private _vals = [
    _logic getVariable ["Name", ""], _logic getVariable ["Range", 25], _logic getVariable ["Side", ""],
    _logic getVariable ["Index", 0], _logic getVariable ["Ace", true]
];
if !(_ctx get "zeus") exitWith {
    if ((count _targets) isEqualTo 0) then {
        ["WARNING", "ATAK", "Module Écran COMSPEC sans objet synchronisé : synchronisez-le avec une TV, un écran ou un projecteur."] call comspec_atak_native_fnc_log;
    };
    {
        _vals params ["_name", "_range", "_side", "_sel", "_ace"];
        ["add", [_x, [_name, format ["%1 %2", _name, _forEachIndex + 1]] select (_name isNotEqualTo "" && {(count _targets) > 1}), _range, _side, _sel, _ace]] call comspec_atak_native_fnc_screenCast;
    } forEach _targets;
    true
};
deleteVehicle _logic;
private _obj = _targets param [0, objNull];
if (isNull _obj) exitWith { ["feedback", ["ATAK : lâchez le module sur une TV, un écran ou un projecteur"]] call comspec_atak_native_fnc_zeusMenu; true };
private _cur = _obj getVariable ["COMSPEC_ATAK_Screen", []];
if ((count _cur) >= 5) then { _vals = +_cur; };
["ATAK · Écran COMSPEC", [
    ["COMBO", "Action", [["Désigner comme écran", "add"], ["Retirer l'écran", "remove"]], "add"],
    ["EDIT", "Nom", _vals select 0],
    ["SLIDER", "Portée de détection (m)", [3, 200, _vals select 1]],
    ["COMBO", "Camp qui le pilote", [["Tous", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]], _vals select 2],
    ["SLIDER", "Texture (index de sélection)", [0, 5, _vals select 3]],
    ["CHECK", "Contrôle par ACE / molette", _vals select 4]
], {
    params ["_v", "_obj"];
    _v params ["_act", "_name", "_range", "_side", "_sel", "_ace"];
    if (_act isEqualTo "remove") exitWith {
        ["remove", _obj] call comspec_atak_native_fnc_screenCast;
        ["feedback", ["ATAK : écran retiré"]] call comspec_atak_native_fnc_zeusMenu;
    };
    ["add", [_obj, _name, round _range, _side, round _sel, _ace]] call comspec_atak_native_fnc_screenCast;
    ["feedback", [format ["ATAK : écran « %1 » prêt (%2 m)", [_name, "Écran"] select (_name isEqualTo ""), round _range]]] call comspec_atak_native_fnc_zeusMenu;
}, _obj, "Les diapositives de l'app Briefing pourront être diffusées sur cet objet depuis un téléphone à portée, puis pilotées par le téléphone, ACE ou la molette. Chaque joueur voit l'image s'il a COMSPEC Link."] call comspec_atak_native_fnc_zeusForm;
true
