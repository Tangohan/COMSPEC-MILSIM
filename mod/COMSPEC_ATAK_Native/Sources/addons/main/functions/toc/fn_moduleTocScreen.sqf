/*
    Module « Poser un écran TOC » (Eden et Zeus) : un grand écran qui affiche le réseau ATAK d'un camp (fn_tocScreen).
    Eden : les objets synchronisés (TV, écran sur trépied, écran de projection, poste informatique) deviennent des écrans
    TOC ; sans objet synchronisé, le module pose lui-même l'écran du jeu choisi (attribut Écran) à sa place, tourné
    comme lui.
    Zeus : lâché sur le sol, il pose un NOUVEL écran tourné vers la caméra, à chaque usage (nom rendu unique : « TOC »,
    « TOC 2 »…) ; lâché précisément sur un objet, il ouvre le réglage de cet objet (désigner, tester, poser un nouvel
    écran à côté, et « Retirer », qui n'est jamais le choix proposé d'office). Retour de TTRD (4.7.1) : « si on réutilise
    le module on peut juste retirer, et pas en remettre » : l'objet le plus proche à moins de 3 m (fn_moduleContext
    « near ») était pris pour l'objet visé, donc l'écran posé au premier usage, et la fenêtre s'ouvrait sur « Retirer ».
    Les écrans posés au Zeus sont gardés pour la partie suivante (réglage « Garder les écrans TOC »).
    Attributs : Nom, Camp affiché (vide : celui de chaque joueur), Affichage (dont « PIP du poste » : l'écran recopie
    ce que l'opérateur a sous les yeux sur le PC du poste ; salle TOC : vue satellite, horloge et chronos, écran
    d'escouade, main courante, liste d'exécution, pertes et résultats, demandes), Satellite montré (SAT 1 / SAT 2),
    Escouade montrée (nom du groupe), Texte (affichage « Texte à LED » : messages, couleur, vitesse, défilement,
    fn_tocText), Liste d'exécution préparée (étapes séparées par |, champs par ;), Dalles du mur
    (contenu des dalles 2 et suivantes d'un mur multi-écrans : « SAT1, CLOCK, SQ:Alpha 1-1, LOG »), Poste TOC,
    Écran posé, Texture (index).
    Eden : PC de TOC synchronisés avec le module (en plus des écrans) : une salle TOC au nom du module est créée, ces PC
    en sont les postes maîtres et les écrans synchronisés y sont liés (fn_tocHall).
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
// Affichage TEXTE (fn_tocText) : texte, couleur, vitesse, défilement du module -> réglage ([] : pas de texte écrit).
private _txtCfg = ["fromFields", [_logic getVariable ["Text", ""], _logic getVariable ["TextColor", "G"], _logic getVariable ["TextSpeed", 1], _logic getVariable ["TextScroll", 0]]] call comspec_atak_native_fnc_tocText;
private _extra = [[], [["text", _txtCfg]]] select (_txtCfg isNotEqualTo []);
// Liste d'exécution préparée : « événement ; mot de code ; élément ; heure | … » pour le camp de l'écran (tous si vide).
private _exApply = {
    params ["_txt", "_s"];
    if !(_txt isEqualType "" && {(trim _txt) isNotEqualTo ""}) exitWith {};
    private _lines = ((_txt splitString "|") apply { ((_x splitString ";") apply { trim _x }) }) select { (count _x) >= 1 && {(_x select 0) isNotEqualTo ""} };
    { ["do", [_x, "exSet", _lines, "Mission", ""]] call comspec_atak_native_fnc_tocRoom; } forEach ([[_s], ["WEST", "EAST", "GUER", "CIV"]] select (_s isEqualTo ""));
};
// Attributs Eden jamais posés (le module vient d'un Zeus ou d'un script, pas d'Eden) : jamais les valeurs par défaut,
// la fenêtre de choix s'ouvre. Avant, un module Zeus pris pour un module Eden posait toujours le grand écran sur
// trépied, en carte et flux, nommé « TOC » (les défauts ci-dessus), quel que soit le choix (journal de TTRD, 11/11).
// Module de la mission (Eden) : exécuté au démarrage (time 0), jamais pris pour un module Zeus.
private _zeus = (_ctx get "zeus") || {hasInterface && {time > 1} && {isNil { _logic getVariable "Class" }} && {!isNull (getAssignedCuratorLogic player)}};
if (!_zeus) exitWith {
    // PC de TOC synchronisés avec le module : une salle TOC à son nom, dont ces PC sont les postes maîtres et ces
    // écrans les écrans liés (fn_tocHall).
    private _pcs = _targets select { _x isKindOf "COMSPEC_ATAK_TocLaptop" || {(getNumber (configOf _x >> "comspec_tocPost")) > 0} || {_x getVariable ["COMSPEC_ATAK_Toc", false]} || {((toLower (typeOf _x)) find "laptop") >= 0} || {((toLower (typeOf _x)) find "computer") >= 0} };
    _targets = _targets - _pcs;
    if ((count _pcs) > 0 && {(count _targets) isEqualTo 0}) then { _targets = []; };
    if ((count _targets) isEqualTo 0 && {(count _pcs) isEqualTo 0}) then {
        ["spawn", [getPosATL _logic, getDir _logic, _cls, _name, _side, _view, _toc, _slot, _squad, _pans, _extra]] call comspec_atak_native_fnc_tocScreen;
    } else {
        {
            if (_pans isNotEqualTo "") then { _x setVariable ["COMSPEC_ATAK_TocPanelCfg", ["panelSpec", _pans] call comspec_atak_native_fnc_tocScreen]; };
            ["add", [_x, [_name, format ["%1 %2", _name, _forEachIndex + 1]] select (_name isNotEqualTo "" && {(count _targets) > 1}), _side, _sel, _view, _toc, false, _slot, _squad]] call comspec_atak_native_fnc_tocScreen;
            if (_txtCfg isNotEqualTo []) then { ["view", [_x, "text", _txtCfg]] call comspec_atak_native_fnc_tocScreen; };
        } forEach _targets;
    };
    if ((count _pcs) > 0 && {isServer}) then {
        private _hid = format ["E-%1", toUpper ([_name, "TOC"] select (_name isEqualTo ""))];
        ["do", ["create", [[_name, "TOC"] select (_name isEqualTo ""), _side, _pcs select 0, _hid]]] call comspec_atak_native_fnc_tocHall;
        { _x setVariable ["COMSPEC_ATAK_TocHall", _hid, true]; } forEach _pcs;
        { ["do", ["link", [_hid, _x]]] call comspec_atak_native_fnc_tocHall; } forEach _targets;
    };
    // Après la reprise de la partie précédente (3 s) : la liste préparée par la mission passe devant.
    if (isServer) then { [_exApply, [_ex, toUpper _side], 4] call comspec_atak_native_fnc_waitAndExec; };
    true
};
private _pos = getPosATL _logic;
deleteVehicle _logic;
// Objet visé : seulement celui sur lequel le module est lâché (accroche du Zeus, objet sous la souris). Jamais
// « l'objet le plus proche » : un écran déjà posé à côté n'empêche plus d'en poser un autre.
private _obj = _targets param [0, objNull];
private _sides = [["Celui de chaque joueur", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]];
private _views = [["Carte et flux", "SPLIT"], ["Carte seule (carte en grand)", "MAP"], ["Flux seul", "FEED"], ["PIP du poste (ce que voit l'opérateur)", "TOC"],
    ["Vue satellite (suivi des alliés)", "SAT"], ["Horloge ZULU et chronos", "CLOCK"], ["Écran d'escouade (un groupe)", "SQUAD"], ["Main courante du poste", "LOG"],
    ["Liste d'exécution (EXCHECK)", "EXCHECK"], ["Pertes et résultats", "KILLS"], ["Demandes des éléments", "REQ"], ["Texte à LED (message libre, défilant)", "TEXT"], ["Diffusion et mire (BROADCAST)", "BROADCAST"], ["Activité radio (qui parle, sur quelle fréquence)", "RADIO"]];
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
// Pose d'un nouvel écran à un endroit : fenêtre appelée à chaque usage du module (sol), et depuis le réglage d'un objet.
missionNamespace setVariable ["COMSPEC_ATAK_TocNewForm", {
    params ["_pos"];
    (missionNamespace getVariable ["COMSPEC_ATAK_TocUseLists", []]) params ["_sides", "_views", "_sats", "_grps", "_exHelp", "_panHelp"];
    // Tous les écrans posables (COMSPEC, jeu, autres mods), avec leur aperçu Eden : la classe choisie part telle quelle.
    private _classes = ["classes"] call comspec_atak_native_fnc_tocScreen;
    if ((count _classes) isEqualTo 0) exitWith { ["feedback", ["ATAK : aucun écran du jeu disponible"]] call comspec_atak_native_fnc_zeusMenu; true };
    ["Poser un écran TOC", [
        ["COMBO", "Action", [["Poser un nouvel écran ici", "place"], ["Ouvrir le tableau des écrans (tous les écrans de la mission)", "manager"]], "place"],
        ["COMBO", "Écran posé", _classes, (_classes select 0) select 1],
        ["EDIT", "Nom", "TOC"],
        ["COMBO", "Camp affiché", _sides, ""],
        ["COMBO", "Affichage", _views, "SPLIT"],
        ["CHECK", "Sert aussi de poste TOC (PC du poste, carte en grand)", true],
        ["COMBO", "Satellite montré (vue satellite)", _sats, 0],
        ["COMBO", "Escouade montrée (écran d'escouade)", _grps, ""],
        ["EDIT", _exHelp, ""],
        ["EDIT", _panHelp, ""]
    ] + (["zeusFields"] call comspec_atak_native_fnc_tocText), {
        params ["_v", "_pos"];
        _v params ["_act", "_cls", "_name", "_side", "_view", "_toc", "_slot", "_squad", "_ex", "_pans"];
        if (_act isEqualTo "manager") exitWith { [{ ["open"] call comspec_atak_native_fnc_tocManager; }, [], 0.2] call comspec_atak_native_fnc_waitAndExec; };
        private _cam = getPosATL curatorCamera;
        // Chaque usage pose un écran de plus : nom rendu unique dans la mission (« TOC », « TOC 2 »…).
        _name = ["uniqueName", _name] call comspec_atak_native_fnc_tocManager;
        // Champs de l'affichage TEXTE (à la suite des dix premiers) : réglage posé avec l'écran.
        private _txtCfg = ["fromFields", _v select [10]] call comspec_atak_native_fnc_tocText;
        ["spawn", [_pos, _pos getDir _cam, _cls, _name, _side, _view, _toc, _slot, _squad, _pans, [[], [["text", _txtCfg]]] select (_txtCfg isNotEqualTo [])]] call comspec_atak_native_fnc_tocScreen;
        [_ex, _side] call COMSPEC_ATAK_TocExZ;
        ["feedback", [format ["ATAK : écran TOC « %1 » posé", [_name, "TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
    }, _pos, "Chaque usage pose un nouvel écran ici, tourné vers vous. Il affiche le réseau ATAK du camp, ou un affichage de la salle TOC (satellite, horloge, escouade, registres du poste, mire). Liste d'exécution : Franchissement LD ; ROMEO ; ALPHA ; 22:30 | Objectif pris ; TANGO ; BRAVO ; 23:10. Gardé pour la partie suivante."] call comspec_atak_native_fnc_zeusForm;
    true
}];
missionNamespace setVariable ["COMSPEC_ATAK_TocUseLists", [_sides, _views, _sats, _grps, _exHelp, _panHelp]];
if (isNull _obj) exitWith { [_pos] call COMSPEC_ATAK_TocNewForm; true };
// « Écran TOC : utiliser cet objet » (lâché sur n'importe quel objet, écran d'un autre mod compris) : surface de l'image
// choisie dans la liste des sélections de l'objet (proposée : surface connue de CfgComspecTocScreens, sinon nom
// d'écran), format, matière d'écran, et « Tester l'image » (8 s, puis la fenêtre revient avec les mêmes choix).
private _cur = _obj getVariable ["COMSPEC_ATAK_TocScr", []];
if ((count _cur) >= 8) then { _name = _cur select 0; _side = _cur select 1; _sel = _cur select 2; _view = _cur select 3; _toc = _cur select 6; _slot = _cur param [8, 0]; _squad = _cur param [9, ""]; } else { _sel = -1; };
private _forced = _obj getVariable ["COMSPEC_ATAK_TocSurf", []];
missionNamespace setVariable ["COMSPEC_ATAK_TocUseForm", {
    params ["_obj", "_st"];
    _st params ["_act", "_name", "_side", "_view", "_toc", "_sel", "_ratio", "_mat", "_slot", "_squad", "_ex"];
    (missionNamespace getVariable ["COMSPEC_ATAK_TocUseLists", []]) params ["_sides", "_views", "_sats", "_grps", "_exHelp"];
    (["surfaces", _obj] call comspec_atak_native_fnc_tocScreen) params ["_surfs", "_auto"];
    // Aucune surface d'image : la fenêtre le dit, rien à régler (pas de message par-dessus le jeu).
    if (_surfs isEqualTo []) exitWith {
        ["Écran TOC : utiliser cet objet", [], {}, _obj, "Cet objet n'a aucune surface d'image (aucune sélection cachée dans sa config) : il ne peut pas devenir un écran TOC. Choisissez un autre écran, ou lâchez le module sur le sol pour poser un écran COMSPEC."] call comspec_atak_native_fnc_zeusForm;
    };
    if (_sel < 0) then { _sel = _auto; };
    private _surfOpts = _surfs apply { [format ["%1 · %2%3", _x select 1, _x select 0, ["", "  ★"] select ((_x select 1) isEqualTo _auto)], _x select 1] };
    ["Écran TOC : utiliser cet objet", [
        ["COMBO", "Action", [["Désigner ou régler cet objet comme écran TOC", "add"], ["Tester l'image (8 s), puis revenir ici", "test"], ["Poser un nouvel écran à côté de cet objet", "new"],
            ["Ouvrir le tableau des écrans (tous les écrans de la mission)", "manager"], ["Retirer l'écran TOC de cet objet", "remove"]], _act],
        ["COMBO", "Surface de l'image", _surfOpts, _sel],
        ["COMBO", "Format de l'image", [["16:9 (écran, TV, mur d'images)", 0], ["Bandeau 4:1 (horloge, plaque de nom)", 4]], _ratio],
        ["CHECK", "Matière d'écran COMSPEC, lumineuse jour et nuit", _mat],
        ["EDIT", "Nom", [_name, "TOC"] select (_name isEqualTo "")],
        ["COMBO", "Camp affiché", _sides, _side],
        ["COMBO", "Affichage", _views, _view],
        ["CHECK", "Sert aussi de poste TOC (PC du poste, carte en grand)", _toc],
        ["COMBO", "Satellite montré (vue satellite)", _sats, _slot],
        ["COMBO", "Escouade montrée (écran d'escouade)", _grps, _squad],
        ["EDIT", _exHelp, _ex]
    ], {
        params ["_v", "_obj"];
        _v params ["_act", "_sel", "_ratio", "_mat", "_name", "_side", "_view", "_toc", "_slot", "_squad", "_ex"];
        if (_act isEqualTo "manager") exitWith { [{ ["open", [_this]] call comspec_atak_native_fnc_tocManager; }, _obj, 0.2] call comspec_atak_native_fnc_waitAndExec; };
        // Nouvel écran à côté : 1,5 m sur la droite de l'objet, jamais à sa place.
        if (_act isEqualTo "new") exitWith { [{ [_this] call COMSPEC_ATAK_TocNewForm; }, _obj modelToWorld [1.5, 0, 0], 0.2] call comspec_atak_native_fnc_waitAndExec; };
        if (_act isEqualTo "remove") exitWith {
            ["remove", _obj] call comspec_atak_native_fnc_tocScreen;
            ["feedback", ["ATAK : écran TOC retiré"]] call comspec_atak_native_fnc_zeusMenu;
        };
        if (_act isEqualTo "test") exitWith {
            ["test", [_obj, _sel]] call comspec_atak_native_fnc_tocScreen;
            [{ _this call COMSPEC_ATAK_TocUseForm; }, [_obj, ["add", _name, _side, _view, _toc, _sel, _ratio, _mat, _slot, _squad, _ex]], 0.2] call comspec_atak_native_fnc_waitAndExec;
        };
        // Surface, format et matière choisis : posés avant la désignation, gardés avec l'écran (fn_tocPersist).
        _obj setVariable ["COMSPEC_ATAK_TocSurf", [_sel, _ratio, _mat], true];
        ["add", [_obj, _name, _side, _sel, _view, _toc, true, _slot, _squad]] call comspec_atak_native_fnc_tocScreen;
        [_ex, _side] call COMSPEC_ATAK_TocExZ;
        ["feedback", [format ["ATAK : écran TOC « %1 » prêt", [_name, "TOC"] select (_name isEqualTo "")]]] call comspec_atak_native_fnc_zeusMenu;
    }, _obj, "L'objet affiche le réseau ATAK du camp ou un affichage de la salle TOC pour chaque joueur à moins de 150 m. ★ : surface proposée. Image absente ou mal placée : choisissez une autre surface et testez l'image."] call comspec_atak_native_fnc_zeusForm;
}];
// Action proposée d'office : toujours « désigner ou régler ». « Retirer » se choisit, il n'est jamais présélectionné.
[_obj, ["add", _name, _side, _view, _toc, _forced param [0, _sel], _forced param [1, 0], _forced param [2, true], _slot, _squad, ""]] call COMSPEC_ATAK_TocUseForm;
true
