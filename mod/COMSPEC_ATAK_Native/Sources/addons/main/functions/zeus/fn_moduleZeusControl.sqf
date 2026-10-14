/*
    Module Zeus « ATAK · Contrôle Zeus » (sans ZEN). Posé sur un joueur ou au sol : formulaire (cible, rayon, action,
    intensité, durée, message), puis fn_zeusMenu "apply". Cibles : joueur sous le module, joueurs dans le rayon,
    tous les joueurs, ou tout un camp.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _pos = _ctx get "pos";
private _under = ["players", _ctx get "targets"] call comspec_atak_native_fnc_zeusMenu;
deleteVehicle _logic;
if !(_ctx get "zeus") exitWith { true };
private _cat = ["catalog"] call comspec_atak_native_fnc_zeusMenu;
private _actions = _cat apply { [format ["%1 · %2", (_x select 1) call comspec_atak_native_fnc_tr, (_x select 2) call comspec_atak_native_fnc_tr], _x select 0] };
private _who = [
    ["Joueurs dans le rayon", "radius"], ["Tous les joueurs", "all"],
    ["Camp BLUFOR", "WEST"], ["Camp OPFOR", "EAST"], ["Camp indépendant", "GUER"], ["Civils", "CIV"]
];
if (_under isNotEqualTo []) then { _who = [[format ["Joueur sous le module (%1)", name (_under select 0)], "under"]] + _who; };
["ATAK · Contrôle Zeus", [
    ["COMBO", "Cible", _who, (_who select 0) select 1],
    ["SLIDER", "Rayon (m)", [10, 2000, 150]],
    ["COMBO", "Action", _actions, "off_on"],
    ["SLIDER", "Intensité (%)", [1, 100, 50]],
    ["SLIDER", "Durée (min, 0 = jusqu'à annulation)", [0, 120, 0]],
    ["EDIT", "Message (verrou, avertissement, message)", ""]
], {
    params ["_vals", "_a"];
    _a params ["_pos", "_under"];
    _vals params ["_who", "_rad", "_id", "_int", "_min", "_msg"];
    private _all = allPlayers select { alive _x && {!(_x isKindOf "HeadlessClient_F")} };
    private _units = switch (_who) do {
        case "under": { _under };
        case "radius": { _all select { (_x distance2D _pos) <= _rad } };
        case "all": { _all };
        default { _all select { (str side group _x) isEqualTo _who } };
    };
    ["apply", [_id, _units, _int, _min * 60, _msg]] call comspec_atak_native_fnc_zeusMenu;
}, [_pos, _under], "Agit sur le téléphone ATAK des joueurs visés. Durée : effets durables (désactivation, verrou, haut-parleurs, brouillage, surchauffe, décharge) ; 0 = jusqu'à « Tout rétablir » ou l'action inverse. Intensité : brouillage, fêlures, décharge, niveau de batterie."] call comspec_atak_native_fnc_zeusForm;
true
