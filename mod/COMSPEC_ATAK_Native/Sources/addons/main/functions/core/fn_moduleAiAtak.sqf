/*
    Module « ATAK · IA équipées » (Eden et Zeus, fn_aiAtak) : oblige des IA à porter un ATAK (ou le leur interdit).
    Eden : unités synchronisées et leurs groupes ; sans rien de synchronisé, toutes les IA du camp choisi.
    Zeus : lâché sur une unité, formulaire (son groupe, elle seule, ou retirer l'ATAK au groupe).
    Attributs : Qui (tout le groupe, chefs de groupe seulement, aucun = interdire), Camp (sans synchronisation).
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _men = [];
{
    if (_x isKindOf "CAManBase") then { { _men pushBackUnique _x; } forEach (units group _x); };
} forEach (_ctx get "targets");
private _who = _logic getVariable ["Who", "all"];
private _apply = {
    params ["_list", "_who"];
    private _on = _list select { _who isEqualTo "all" || {_who isEqualTo "leaders" && {_x isEqualTo leader group _x}} };
    if (_who isEqualTo "none") then { ["comspec_atak_native_aiAtak", [_list, false]] call CBA_fnc_serverEvent; } else {
        ["comspec_atak_native_aiAtak", [_on, true]] call CBA_fnc_serverEvent;
    };
    count ([_on, _list] select (_who isEqualTo "none"))
};
if !(_ctx get "zeus") exitWith {
    if ((count _men) isEqualTo 0) then {
        private _side = _logic getVariable ["Side", "WEST"];
        _men = allUnits select { !isPlayer _x && {(str side group _x) isEqualTo _side} };
    };
    [_men select { !isPlayer _x }, _who] call _apply;
    true
};
deleteVehicle _logic;
private _u = _ctx getOrDefault ["target", objNull];
if (isNull _u || {!(_u isKindOf "CAManBase")}) exitWith { ["feedback", ["ATAK : lâchez le module sur une unité IA"]] call comspec_atak_native_fnc_zeusMenu; true };
["ATAK · IA équipées", [
    ["COMBO", "Action", [["Donner un ATAK à tout son groupe", "group"], ["Donner un ATAK aux chefs de groupe", "leaders"], ["Donner un ATAK à cette unité seulement", "unit"], ["Retirer l'ATAK du groupe", "none"]], "group"]
], {
    params ["_v", "_u"];
    private _act = _v select 0;
    private _list = ((units group _u) select { !isPlayer _x });
    private _n = switch (_act) do {
        case "unit": { ["comspec_atak_native_aiAtak", [[_u], true]] call CBA_fnc_serverEvent; 1 };
        case "leaders": { private _l = _list select { _x isEqualTo leader group _x }; ["comspec_atak_native_aiAtak", [_l, true]] call CBA_fnc_serverEvent; count _l };
        case "none": { ["comspec_atak_native_aiAtak", [_list, false]] call CBA_fnc_serverEvent; count _list };
        default { ["comspec_atak_native_aiAtak", [_list, true]] call CBA_fnc_serverEvent; count _list };
    };
    ["feedback", [format [["ATAK : %1 IA équipée(s)", "ATAK : ATAK retiré à %1 IA"] select (_act isEqualTo "none"), _n]]] call comspec_atak_native_fnc_zeusMenu;
}, _u, "Les IA équipées apparaissent sur le BFT et les écrans TOC comme des nœuds ATAK : points PLI envoyés par le serveur au rythme du réseau, erreur GPS réaliste, perdus près d'un brouilleur."] call comspec_atak_native_fnc_zeusForm;
true
