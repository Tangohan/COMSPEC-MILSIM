/*
    Module « Poser un écran TOC » (Eden et Zeus) : un grand écran qui affiche le réseau ATAK d'un camp (fn_tocScreen).
    Eden : les objets synchronisés (TV, écran sur trépied, écran de projection, poste informatique) deviennent des écrans
    TOC ; sans objet synchronisé, le module pose lui-même l'écran du jeu choisi (attribut Écran) à sa place, tourné
    comme lui.
    Zeus : lâché sur un objet, il le désigne (ou le retire s'il l'est déjà) ; lâché sur le sol, il pose un écran du jeu
    tourné vers la caméra. Les écrans posés au Zeus sont gardés pour la partie suivante (réglage « Garder les écrans TOC »).
    Attributs : Nom, Camp affiché (vide : celui de chaque joueur), Affichage (dont « PIP du poste » : l'écran recopie
    ce que l'opérateur a sous les yeux sur le PC du poste ; salle TOC : vue satellite, horloge et chronos, écran
    d'escouade, main courante, liste d'exécution, pertes et résultats, demandes), Satellite montré (SAT 1 / SAT 2),
    Escouade montrée (nom du groupe), Liste d'exécution préparée (étapes séparées par |, champs par ;), Dalles du mur
    (contenu des dalles 2 et suivantes d'un mur multi-écrans : « SAT1, CLOCK, SQ:Alpha 1-1, LOG »), Poste TOC,
    Écran posé, Texture (index).
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = _ctx get "targets";
private _vals = [
    _logic getVariable ["Name", ""], _logic getVariable ["Side", ""], _logic getVariable ["View", "SPLIT"],
    _logic getVariable ["Toc", true], _logic getVariable ["Class", "COMSPEC_ATAK_Screen_Tripod"], _logic getVariable ["Index", 0],
    _logic getVariable ["Sat", 0], _logic getVariable ["Squad", ""], _logic getVariable ["Excheck", ""], _logic getVariable ["Panels", ""]
];
_vals params ["_name", "_side", "_view", "_toc", "_cls", "_sel", "_slot", "_squad", "_ex", "_pans"];
// Liste d'exécution préparée : « événement ; mot de code ; élément ; heure | … » pour le camp de l'écran (tous si vide).
private _exApply = {
    params ["_txt", "_s"];
    if !(_txt isEqualType "" && {(trim _txt) isNotEqualTo ""}) exitWith {};
    private _lines = ((_txt splitString "|") apply { ((_x splitString ";") apply { trim _x }) }) select { (count _x) >= 1 && {(_x select 0) isNotEqualTo ""} };
    { ["do", [_x, "exSet", _lines, "Mission", ""]] call comspec_atak_native_fnc_tocRoom; } forEach ([[_s], ["WEST", "EAST", "GUER", "CIV"]] select (_s isEqualTo ""));
};
if !(_ctx get "zeus") exitWith {
    if ((count _targets) isEqualTo 0) then {
        ["spawn", [getPosATL _logic, getDir _logic, _cls, _name, _side, _view, _toc, _slot, _squad, _pans]] call comspec_atak_native_fnc_tocScreen;
    } else {
        {
            if (_pans isNotEqualTo "") then { _x setVariable ["COMSPEC_ATAK_TocPanelCfg", ["panelSpec", _pans] call comspec_atak_native_fnc_tocScreen]; };
            ["add", [_x, [_name, format ["%1 %2", _name, _forEachIndex + 1]] select (_name isNotEqualTo "" && {(count _targets) > 1}), _side, _sel, _view, _toc, false, _slot, _squad]] call comspec_atak_native_fnc_tocScreen;
        } forEach _targets;
    };
    // Après la reprise de la partie précédente (3 s) : la liste préparée par la mission passe devant.
    if (isServer) then { [_exApply, [_ex, toUpper _side], 4] call comspec_atak_native_fnc_waitAndExec; };
    true
};
private _pos = getPosATL _logic;
deleteVehicle _logic;
private _obj = _targets param [0, _ctx getOrDefault ["near", objNull]];
private _sides = [["Celui de chaque joueur", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]];
private _views = [["Carte et flux", "SPLIT"], ["Carte seule (carte en grand)", "MAP"], ["Flux seul", "FEED"], ["PIP du poste (ce que voit l'opérateur)", "TOC"],
    ["Vue satellite (suivi des alliés)", "SAT"], ["Horloge ZULU et chronos", "CLOCK"], ["Écran d'escouade (un groupe)", "SQUAD"], ["Main courante du poste", "LOG"],
    ["Liste d'exécution (EXCHECK)", "EXCHECK"], ["Pertes et résultats", "KILLS"], ["Demandes des éléments", "REQ"]];
private _sats = [["SAT 1", 0], ["SAT 2", 1]];
// Groupes avec un joueur ou un porteur d'ATAK, tous camps (le Zeus choisit) : « nom (camp) ».
private _grps = [["Aucune", ""]];
{
    if (({alive _x} count (units _x)) > 0 && {({isPlayer _x} count (units _x)) > 0 || {((units _x) findIf { [_x] call comspec_atak_native_fnc_hasDevice }) >= 0}}) then {
        _grps pushBackUnique [format ["%1 (%2)", groupId _x, str side _x], groupId _x];
    };
} forEach allGroups;
private _exHelp = "Liste d'exécution préparée (étapes séparées par |, champs par ;)";
private _panHelp = "Mur à plusieurs dalles : dalles 2 et suivantes (exemple : SAT1, CLOCK, SQ:Alpha 1-1, LOG)";
private _exApplyZ = {
    params ["_ex", "_side"];
    if (_ex isEqualTo "") exitWith {};
    private _lines = ((_ex splitString "|") apply { ((_x splitString ";") apply { trim _x }) }) select { (count _x) >= 1 && {(_x select 0) isNotEqualTo ""} };
    { ["do", [_x, "exSet", _lines, "Zeus", ""]] call comspec_atak_native_fnc_tocRoom; } forEach ([[toUpper _side], ["WEST", "EAST", "GUER", "CIV"]] select (_side isEqualTo ""));
};
missionNamespace setVariable ["COMSPEC_ATAK_TocExZ", _exApplyZ];
if (isNull _obj) exitWith {
    private _classes = (["classes"] call comspec_atak_native_fnc_tocScreen) apply { [_x select 0, _x select 1] };
    if ((count _classes) isEqualTo 0) exitWith { ["feedback", ["ATAK : aucun écran du jeu disponible"]] call comspec_atak_native_fnc_zeusMenu; true };
    ["Poser un écran TOC", [
        ["COMBO", "Écran posé", _classes, (_classes select 0) select 1],
        ["EDIT", "Nom", "TOC"],
        ["COMBO", "Camp affiché", _sides, ""],
        ["COMBO", "Affichage", _views, "SPLIT"],
        ["CHECK", "Sert aussi de poste TOC (PC du poste, carte en grand)", true],
        ["COMBO", "Satellite montré (vue satellite)", _sats, 0],
        ["COMBO", "Escouade montrée (écran d'escouade)", _grps, ""],
        ["EDIT", _exHelp, ""],
        ["EDIT", _panHelp, ""]
    ], {
        params ["_v", "_pos"];
        _v params ["_cls", "_name", "_side", "_view", "_toc", "_slot", "_squad", "_ex", "_pans"];
        private _cam = getPosATL curatorCamera;
        ["spawn", [_pos, _pos getDir _cam, _cls, _name, _side, _view, _toc, _slot, _squad, _pans]] call comspec_atak_native_fnc_tocScreen;
        [_ex, _side] call COMSPEC_ATAK_TocExZ;
        ["feedback", [format ["ATAK : écran TOC « %1 » posé", [_name, "TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
    }, _pos, "Un grand écran du jeu qui affiche le réseau ATAK du camp tel que le serveur le voit, ou un affichage de la salle TOC (satellite, horloge, escouade, registres du poste). Liste d'exécution : Franchissement LD ; ROMEO ; ALPHA ; 22:30 | Objectif pris ; TANGO ; BRAVO ; 23:10. Gardé pour la partie suivante."] call comspec_atak_native_fnc_zeusForm;
    true
};
private _cur = _obj getVariable ["COMSPEC_ATAK_TocScr", []];
if ((count _cur) >= 8) then { _name = _cur select 0; _side = _cur select 1; _sel = _cur select 2; _view = _cur select 3; _toc = _cur select 6; _slot = _cur param [8, 0]; _squad = _cur param [9, ""]; };
["Poser un écran TOC", [
    ["COMBO", "Action", [["Désigner comme écran TOC", "add"], ["Retirer l'écran TOC", "remove"]], ["add", "remove"] select ((count _cur) >= 8)],
    ["EDIT", "Nom", [_name, "TOC"] select (_name isEqualTo "")],
    ["COMBO", "Camp affiché", _sides, _side],
    ["COMBO", "Affichage", _views, _view],
    ["CHECK", "Sert aussi de poste TOC (PC du poste, carte en grand)", _toc],
    ["SLIDER", "Texture (index de sélection)", [0, 5, _sel]],
    ["COMBO", "Satellite montré (vue satellite)", _sats, _slot],
    ["COMBO", "Escouade montrée (écran d'escouade)", _grps, _squad],
    ["EDIT", _exHelp, ""]
], {
    params ["_v", "_obj"];
    _v params ["_act", "_name", "_side", "_view", "_toc", "_sel", "_slot", "_squad", "_ex"];
    if (_act isEqualTo "remove") exitWith {
        ["remove", _obj] call comspec_atak_native_fnc_tocScreen;
        ["feedback", ["ATAK : écran TOC retiré"]] call comspec_atak_native_fnc_zeusMenu;
    };
    ["add", [_obj, _name, _side, round _sel, _view, _toc, true, _slot, _squad]] call comspec_atak_native_fnc_tocScreen;
    [_ex, _side] call COMSPEC_ATAK_TocExZ;
    ["feedback", [format ["ATAK : écran TOC « %1 » prêt", [_name, "TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
}, _obj, "L'objet affiche le réseau ATAK du camp (carte, nœuds, comptes rendus, messages) ou un affichage de la salle TOC pour chaque joueur à moins de 150 m. Image noire ou mal placée : essayez un autre index de texture."] call comspec_atak_native_fnc_zeusForm;
true
