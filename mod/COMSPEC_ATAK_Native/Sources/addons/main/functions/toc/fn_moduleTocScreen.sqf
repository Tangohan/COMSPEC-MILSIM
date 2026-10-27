/*
    Module « ATAK · Écran TOC » (Eden et Zeus) : un grand écran qui affiche le réseau ATAK d'un camp (fn_tocScreen).
    Eden : les objets synchronisés (TV, écran sur trépied, écran de projection, poste informatique) deviennent des écrans
    TOC ; sans objet synchronisé, le module pose lui-même l'écran du jeu choisi (attribut Écran) à sa place, tourné
    comme lui.
    Zeus : lâché sur un objet, il le désigne (ou le retire s'il l'est déjà) ; lâché sur le sol, il pose un écran du jeu
    tourné vers la caméra. Les écrans posés au Zeus sont gardés pour la partie suivante (réglage « Garder les écrans TOC »).
    Attributs : Nom, Camp affiché (vide : celui de chaque joueur), Affichage (dont « PIP du poste » : l'écran recopie
    ce que l'opérateur a sous les yeux sur le PC du poste), Poste TOC, Écran posé, Texture (index).
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = _ctx get "targets";
private _vals = [
    _logic getVariable ["Name", ""], _logic getVariable ["Side", ""], _logic getVariable ["View", "SPLIT"],
    _logic getVariable ["Toc", true], _logic getVariable ["Class", "Land_TripodScreen_01_large_F"], _logic getVariable ["Index", 0]
];
_vals params ["_name", "_side", "_view", "_toc", "_cls", "_sel"];
if !(_ctx get "zeus") exitWith {
    if ((count _targets) isEqualTo 0) then {
        ["spawn", [getPosATL _logic, getDir _logic, _cls, _name, _side, _view, _toc]] call comspec_atak_native_fnc_tocScreen;
    } else {
        {
            ["add", [_x, [_name, format ["%1 %2", _name, _forEachIndex + 1]] select (_name isNotEqualTo "" && {(count _targets) > 1}), _side, _sel, _view, _toc, false]] call comspec_atak_native_fnc_tocScreen;
        } forEach _targets;
    };
    true
};
private _pos = getPosATL _logic;
deleteVehicle _logic;
private _obj = _targets param [0, _ctx getOrDefault ["near", objNull]];
private _sides = [["Celui de chaque joueur", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]];
private _views = [["Carte et flux", "SPLIT"], ["Carte seule (carte en grand)", "MAP"], ["Flux seul", "FEED"], ["PIP du poste (ce que voit l'opérateur)", "TOC"]];
if (isNull _obj) exitWith {
    private _classes = (["classes"] call comspec_atak_native_fnc_tocScreen) apply { [_x select 0, _x select 1] };
    if ((count _classes) isEqualTo 0) exitWith { ["feedback", ["ATAK : aucun écran du jeu disponible"]] call comspec_atak_native_fnc_zeusMenu; true };
    ["ATAK · Écran TOC", [
        ["COMBO", "Écran posé", _classes, (_classes select 0) select 1],
        ["EDIT", "Nom", "TOC"],
        ["COMBO", "Camp affiché", _sides, ""],
        ["COMBO", "Affichage", _views, "SPLIT"],
        ["CHECK", "Sert aussi de poste TOC (PC du poste, carte en grand)", true]
    ], {
        params ["_v", "_pos"];
        _v params ["_cls", "_name", "_side", "_view", "_toc"];
        private _cam = getPosATL curatorCamera;
        ["spawn", [_pos, _pos getDir _cam, _cls, _name, _side, _view, _toc]] call comspec_atak_native_fnc_tocScreen;
        ["feedback", [format ["ATAK : écran TOC « %1 » posé", [_name, "TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
    }, _pos, "Un grand écran du jeu qui affiche le réseau ATAK du camp tel que le serveur le voit : carte avec les nœuds (joueurs et IA équipées), marqueurs, derniers comptes rendus et messages. Gardé pour la partie suivante."] call comspec_atak_native_fnc_zeusForm;
    true
};
private _cur = _obj getVariable ["COMSPEC_ATAK_TocScr", []];
if ((count _cur) >= 8) then { _name = _cur select 0; _side = _cur select 1; _sel = _cur select 2; _view = _cur select 3; _toc = _cur select 6; };
["ATAK · Écran TOC", [
    ["COMBO", "Action", [["Désigner comme écran TOC", "add"], ["Retirer l'écran TOC", "remove"]], ["add", "remove"] select ((count _cur) >= 8)],
    ["EDIT", "Nom", [_name, "TOC"] select (_name isEqualTo "")],
    ["COMBO", "Camp affiché", _sides, _side],
    ["COMBO", "Affichage", _views, _view],
    ["CHECK", "Sert aussi de poste TOC (PC du poste, carte en grand)", _toc],
    ["SLIDER", "Texture (index de sélection)", [0, 5, _sel]]
], {
    params ["_v", "_obj"];
    _v params ["_act", "_name", "_side", "_view", "_toc", "_sel"];
    if (_act isEqualTo "remove") exitWith {
        ["remove", _obj] call comspec_atak_native_fnc_tocScreen;
        ["feedback", ["ATAK : écran TOC retiré"]] call comspec_atak_native_fnc_zeusMenu;
    };
    ["add", [_obj, _name, _side, round _sel, _view, _toc, true]] call comspec_atak_native_fnc_tocScreen;
    ["feedback", [format ["ATAK : écran TOC « %1 » prêt", [_name, "TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
}, _obj, "L'objet affiche le réseau ATAK du camp (carte, nœuds, comptes rendus, messages) pour chaque joueur à moins de 150 m. Image noire ou mal placée : essayez un autre index de texture."] call comspec_atak_native_fnc_zeusForm;
true
