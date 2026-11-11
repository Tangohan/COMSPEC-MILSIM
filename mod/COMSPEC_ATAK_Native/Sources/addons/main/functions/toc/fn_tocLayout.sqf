/*
    Dispositions des écrans TOC à plusieurs dalles (demande de TTRD, 11/11 : « créer d'autres vues pour l'incurvé, pour le
    multitasking ») : murs d'images, écrans triples, postes PC à 2 ou 3 moniteurs, PC incurvé (deux zones 16:9), écrans
    incurvés (zone centrale 16:9 et deux colonnes 8:9). Une disposition = la liste ordonnée du contenu des dalles, écrite
    comme le réglage des dalles du module (fn_tocScreen « panelSpec » : « SPLIT, SAT1, MOS2, CLOCK, SQ:Alpha 1-1, LOG,
    TXT:… »), dalle 1 (l'écran lui-même) puis les suivantes. Elle est ramenée à l'écran visé :
      moins de dalles que de contenus : la fin est ignorée ; plus de dalles : le motif recommence ;
      contenu que le format de la dalle ne permet pas (fn_tocScreen « format ») : son jumeau dans ce format (table
      _twins : sur une colonne 8:9, CLOCK -> COLA, FEED / NET / SPLIT -> COLB, MOSAIC / CAM -> COLC, LOG -> COLD,
      EXCHECK -> COLE, REQ / KILLS -> COLF), sinon l'affichage par défaut de la dalle. Jamais d'image 16:9 étirée.
    Seule source des dispositions (table _catalog) et des jumeaux (table _twins, lue aussi par fn_tocScreen « _fitView »).
    Tout passe par ["view", …] de fn_tocScreen : même chemin avec un serveur à jour ou en repli sur le poste du joueur.
    Aucun rendu ici : les dalles gardent le budget de fn_tocScreen « tick » (huit images par tour, écrans lointains figés).
    Modes :
      ["catalog"]                    [[clé, libellé, aide d'une ligne avec un exemple, contenu des dalles]...]
      ["list", écran]                [[libellé, clé]...] proposées pour cet écran ([] : une seule dalle), puis les
                                     dispositions gardées par le joueur (clé « P|nom »)
      ["apply", [écran, clé]]        pose la disposition (clé du catalogue ou « P|nom ») sur les dalles de l'écran
      ["current", écran]             clé de la disposition du catalogue que montrent les dalles, "" sinon
      ["save", [écran, nom]]         garde le contenu actuel des dalles sous ce nom : profileNamespace
                                     COMSPEC_ATAK_TocLayouts [[nom, [contenus...]]...] (20 au plus, nom de 24 caractères) ;
                                     rend le nom gardé ("" : refusé)
      ["presets"]                    dispositions gardées [[nom, [contenus...]]...]
      ["applyPreset", [écran, nom]]  pose une disposition gardée (même nombre de dalles ou non)
      ["delPreset", nom]             oublie une disposition gardée
      ["kit", [écran, clé]]          une disposition sur tout un poste : l'écran et les écrans de la même salle à 6 m au
                                     plus, leurs dalles mises bout à bout de gauche à droite (vu par l'opérateur)
      ["panes", écran]               dalles désignées de l'écran, dans l'ordre des index
      ["twin", [affichage, format]]  jumeau de l'affichage dans ce format ("" : aucun)
    Exemple : ["apply", [cursorObject, "MAPCAM"]] call comspec_atak_native_fnc_tocLayout;
*/
params [["_mode", "catalog"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_tocLayout;
private _ts = comspec_atak_native_fnc_tocScreen;
// ---- TABLE _catalog (JSON) : [clé, libellé, aide (une ligne, avec un exemple), contenu des dalles].
private _catalog = [
["SITNET", "Situation + réseau", "Carte et flux, réseau par groupe, horloge. Exemple : veille courante du poste", "SPLIT, NET, CLOCK"],
["WIDE", "Carte large", "Carte seule en grand, flux et horloge à côté. Exemple : suivre un déplacement", "MAP, FEED, CLOCK"],
["MAP2", "2 cartes", "Carte de situation et vue satellite SAT 1 côte à côte. Exemple : préparer un assaut", "MAP, SAT1, NET"],
["MAPCAM", "Carte + caméras", "Carte et caméras des éléments. Exemple : suivre une entrée en bâtiment", "SPLIT, MOS2, MOS2"],
["MAPLOG", "Carte + main courante + messages", "Carte, main courante et messages reçus. Exemple : tenir le journal pendant l'action", "MAP, LOG, FEED"],
["QUAD", "Quad", "Carte, satellite, caméras et réseau. Exemple : mur 2x2 ou poste à trois moniteurs", "MAP, SAT1, MOS2, NET"],
["WATCH", "Surveillance", "Mosaïques de caméras sur toutes les dalles. Exemple : garde d'une base", "MOS2, MOS3"],
["ORDERS", "Ordres et transmissions", "Flux des comptes rendus, demandes des éléments et carte. Exemple : poste des transmissions", "FEED, REQ, MAP"],
["EXCHECK", "EXCHECK / chronologie", "Liste d'exécution, horloge et chronos, carte. Exemple : heure H d'une opération", "EXCHECK, CLOCK, MAP"],
["HEALTH", "Santé / pertes", "Pertes et résultats, demandes des éléments (MEDEVAC) et carte. Exemple : suivi des blessés", "KILLS, REQ, MAP"],
["RELAY", "Réseau / relais", "État du réseau par groupe, carte et flux. Exemple : chercher un relais perdu", "NET, MAP, FEED"],
["CLOCKBC", "Horloges + BROADCAST", "Horloge et chronos, puis diffusion (mire et source recopiée). Exemple : avant un briefing", "CLOCK, BROADCAST"]
];
// ---- FIN
// ---- TABLE _twins (JSON) : [format de la dalle, [[affichage demandé, jumeau permis dans ce format]...]].
private _twins = [
["COL", [["CLOCK", "COLA"], ["STRIP", "COLA"], ["SPLIT", "COLB"], ["FEED", "COLB"], ["NET", "COLB"], ["MOSAIC", "COLC"], ["CAM", "COLC"], ["LOG", "COLD"], ["EXCHECK", "COLE"], ["REQ", "COLF"], ["KILLS", "COLF"]]],
["STD", [["COLA", "CLOCK"], ["STRIP", "CLOCK"], ["PLATE", "CLOCK"], ["COLB", "SPLIT"], ["COLC", "MOSAIC"], ["COLD", "LOG"], ["COLE", "EXCHECK"], ["COLF", "REQ"]]],
["STRIP", [["CLOCK", "STRIP"], ["COLA", "STRIP"], ["SQUAD", "PLATE"]]]
];
// ---- FIN
private _twinOf = {
    params [["_v", ""], ["_kind", "STD"]];
    private _row = _twins select { (_x select 0) isEqualTo _kind };
    private _pair = ((_row param [0, ["", []]]) select 1) select { (_x select 0) isEqualTo _v };
    (_pair param [0, ["", ""]]) select 1
};
// Mode « twin » d'abord : appelé par fn_tocScreen à chaque désignation, rien d'autre n'est préparé.
if (_mode isEqualTo "twin") exitWith { if (_a isEqualType []) then { _a call _twinOf } else { "" } };

private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
private _isScr = { params ["_o"]; !isNull _o && {(count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} };
// Dalles désignées d'un écran, dans l'ordre des index (dalle 1 : l'écran lui-même).
private _panesOf = {
    params ["_h"];
    if (isNull _h) exitWith { [] };
    private _out = [];
    for "_k" from 0 to (((getNumber (configOf _h >> "comspec_screenPanels")) max 1) - 1) do {
        private _po = ["panelOf", [_h, _k]] call _ts;
        if ([_po] call _isScr) then { _out pushBack _po; };
    };
    _out
};
// Les mêmes de gauche à droite. Écran incurvé : zone centrale (dalle 1) entre la colonne gauche (dalle 2) et la droite (dalle 3).
private _rowOf = {
    params ["_h"];
    private _p = [_h] call _panesOf;
    private _pr = getArray (configOf _h >> "comspec_screenPanelRatio");
    if ((count _p) isEqualTo 3 && {(count _pr) >= 3} && {(_pr select 0) > 1.3} && {(_pr select 1) < 1.3} && {(_pr select 2) < 1.3}) exitWith { [_p select 1, _p select 0, _p select 2] };
    _p
};
// Contenus d'une disposition : texte « A, B, C » ou liste. BROADCAST inconnu de ce mod : horloge à la place.
private _tokensOf = {
    params ["_spec"];
    private _l = [];
    if (_spec isEqualType "") then { _l = _spec splitString ","; };
    if (_spec isEqualType []) then { _l = _spec select { _x isEqualType "" }; };
    _l = (_l apply { trim _x }) select { _x isNotEqualTo "" };
    private _known = ["views"] call _ts;
    _l apply { [_x, "CLOCK"] select ((toUpper _x) isEqualTo "BROADCAST" && {!("BROADCAST" in _known)}) }
};
// Contenu -> réglage d'une dalle [affichage, satellite, escouade, caméra, mosaïque, éteinte, texte] (fn_tocScreen « panelSpec »).
private _entryOf = { params ["_tk"]; (["panelSpec", _tk] call _ts) param [0, ["SPLIT", 0, ""]] };
// Dispositions gardées par le joueur (profil), relues à chaque appel.
private _presets = {
    private _p = profileNamespace getVariable ["COMSPEC_ATAK_TocLayouts", []];
    if !(_p isEqualType []) then { _p = []; };
    (_p select { _x isEqualType [] && {(count _x) >= 2} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType []} && {(count (_x select 1)) > 0} }) select [0, 20]
};
// Clé (catalogue ou « P|nom ») -> contenus.
private _specOf = {
    params ["_key"];
    if !(_key isEqualType "") exitWith { [] };
    if ((_key select [0, 2]) isEqualTo "P|") exitWith {
        private _nm = toLower (_key select [2]);
        private _p = (call _presets) select { (toLower (_x select 0)) isEqualTo _nm };
        [(_p param [0, ["", []]]) select 1] call _tokensOf
    };
    private _i = _catalog findIf { (_x select 0) isEqualTo (toUpper _key) };
    if (_i < 0) exitWith { [] };
    [(_catalog select _i) select 3] call _tokensOf
};
// Dalles + contenus -> [[dalle, affichage permis par son format, réglage, demandé ou jumeau (pas le défaut)]...].
private _planOf = {
    params ["_panes", "_tokens"];
    if ((count _tokens) isEqualTo 0) exitWith { [] };
    private _out = [];
    {
        private _e = [_tokens select (_forEachIndex mod (count _tokens))] call _entryOf;
        private _v = _e select 0;
        (["format", _x] call _ts) params ["", "", "", "_kind", "_ok", "_def"];
        private _tw = [_v, _kind] call _twinOf;
        private _kept = _v in _ok || {_tw in _ok};
        _out pushBack [_x, [[_def, _tw] select (_tw in _ok), _v] select (_v in _ok), _e, _kept];
    } forEach _panes;
    _out
};
// Pose : seulement ce qui change, par le chemin habituel (serveur, ou poste du joueur en repli).
private _put = {
    params ["_panes", "_tokens"];
    private _plan = [_panes, _tokens] call _planOf;
    {
        _x params ["_po", "_v", "_e"];
        _e params ["", ["_sl", 0], ["_sq", ""], "", ["_mos", []], "", ["_txt", []]];
        private _c = _po getVariable ["COMSPEC_ATAK_TocScr", []];
        // Diapositives en cours sur la dalle : elle revient au TOC.
        if ((count (_po getVariable ["COMSPEC_ATAK_ScreenCast", []])) >= 5) then { ["stop", _po] call comspec_atak_native_fnc_screenCast; };
        if (_v isEqualTo "SAT" && {(_c param [8, 0]) isNotEqualTo _sl}) then { ["view", [_po, "sat", _sl]] call _ts; };
        if (_v in ["SQUAD", "PLATE"] && {_sq isNotEqualTo ""} && {(_c param [9, ""]) isNotEqualTo _sq}) then { ["view", [_po, "squad", _sq]] call _ts; };
        if (_v in ["MOSAIC", "COLC"] && {_mos isEqualType []} && {(count _mos) >= 5} && {(_po getVariable ["COMSPEC_ATAK_TocMosaic", []]) isNotEqualTo _mos}) then { ["view", [_po, "mosaic", _mos]] call _ts; };
        if (_v isEqualTo "TEXT" && {_txt isEqualType []} && {(count _txt) >= 5}) then { ["view", [_po, "text", _txt]] call _ts; };
        if ((_c param [3, ""]) isNotEqualTo _v) then { ["view", [_po, "mode", _v]] call _ts; };
        // Première image tout de suite sur ce poste.
        _po setVariable ["COMSPEC_ATAK_TocAt", -10];
    } forEach _plan;
    count _plan
};
// Contenu actuel d'une dalle, écrit comme dans une disposition.
private _tokenOf = {
    params ["_po"];
    private _c = _po getVariable ["COMSPEC_ATAK_TocScr", []];
    private _v = _c param [3, "SPLIT"];
    switch (_v) do {
        case "SAT": { ["SAT1", "SAT2"] select ((_c param [8, 0]) isEqualTo 1) };
        case "SQUAD": { private _q = trim ([_c param [9, ""], ",", " "] call CBA_fnc_replace); ["SQ:" + _q, "SQUAD"] select (_q isEqualTo "") };
        case "MOSAIC": { ["MOS2", "MOS3"] select (((["cfg", _po] call comspec_atak_native_fnc_tocMosaic) select 0) isEqualTo 3) };
        default { _v };
    }
};

switch (_mode) do {
    case "catalog": { +_catalog };
    case "panes": { if (_a isEqualType objNull) then { [[_a] call _hostOf] call _panesOf } else { [] } };
    case "presets": { +(call _presets) };
    case "list": {
        if !(_a isEqualType objNull) exitWith { [] };
        private _panes = [[_a] call _hostOf] call _panesOf;
        if ((count _panes) < 2) exitWith { [] };
        // Proposée si une dalle au moins montre ce que la disposition demande, tel quel ou par son jumeau.
        private _out = (_catalog select {
            (([_panes, [_x select 3] call _tokensOf] call _planOf) findIf { _x select 3 }) >= 0
        }) apply { [_x select 1, _x select 0] };
        { _out pushBack [_x select 0, "P|" + (_x select 0)]; } forEach (call _presets);
        _out
    };
    case "current": {
        if !(_a isEqualType objNull) exitWith { "" };
        private _panes = [[_a] call _hostOf] call _panesOf;
        if ((count _panes) < 2) exitWith { "" };
        private _found = "";
        {
            private _plan = [_panes, [_x select 3] call _tokensOf] call _planOf;
            private _diff = _plan findIf {
                _x params ["_po", "_v", "_e"];
                private _c = _po getVariable ["COMSPEC_ATAK_TocScr", []];
                (_c param [3, ""]) isNotEqualTo _v || {_v isEqualTo "SAT" && {(_c param [8, 0]) isNotEqualTo (_e param [1, 0])}}
            };
            if (_plan isNotEqualTo [] && {_diff < 0}) exitWith { _found = _x select 0; };
        } forEach _catalog;
        _found
    };
    case "apply": {
        _a params [["_o", objNull], ["_key", ""]];
        private _panes = [[_o] call _hostOf] call _panesOf;
        private _tokens = [_key] call _specOf;
        if ((count _panes) isEqualTo 0 || {(count _tokens) isEqualTo 0}) exitWith { false };
        [_panes, _tokens] call _put;
        true
    };
    case "applyPreset": {
        _a params [["_o", objNull], ["_name", ""]];
        if !(_name isEqualType "") exitWith { false };
        ["apply", [_o, "P|" + _name]] call _fn
    };
    case "save": {
        _a params [["_o", objNull], ["_name", ""]];
        if !(_name isEqualType "") exitWith { "" };
        // Nom nettoyé : ni balise, ni séparateur de clé ou de liste, 24 caractères au plus.
        private _nm = _name;
        { _nm = [_nm, _x, " "] call CBA_fnc_replace; } forEach ["<", ">", "&", """", "|", ",", toString [10], toString [13]];
        _nm = trim _nm;
        forceUnicode 0;
        _nm = trim (_nm select [0, 24]);
        private _panes = [[_o] call _hostOf] call _panesOf;
        if (_nm isEqualTo "" || {(count _panes) isEqualTo 0}) exitWith { "" };
        private _all = (call _presets) select { (toLower (_x select 0)) isNotEqualTo (toLower _nm) };
        _all pushBack [_nm, _panes apply { [_x] call _tokenOf }];
        if ((count _all) > 20) then { _all = _all select [(count _all) - 20]; };
        profileNamespace setVariable ["COMSPEC_ATAK_TocLayouts", _all];
        saveProfileNamespace;
        _nm
    };
    case "delPreset": {
        if !(_a isEqualType "") exitWith { false };
        private _all = call _presets;
        private _rest = _all select { (toLower (_x select 0)) isNotEqualTo (toLower _a) };
        if ((count _rest) isEqualTo (count _all)) exitWith { false };
        profileNamespace setVariable ["COMSPEC_ATAK_TocLayouts", _rest];
        saveProfileNamespace;
        true
    };
    case "kit": {
        _a params [["_o", objNull], ["_key", ""]];
        private _h = [_o] call _hostOf;
        private _tokens = [_key] call _specOf;
        if (isNull _h || {(count _tokens) isEqualTo 0}) exitWith { false };
        // Écrans du même poste : même salle (ou aucune), même camp, à 6 m au plus de l'écran visé.
        private _hall = ["of", _h] call comspec_atak_native_fnc_tocHall;
        private _side = (_h getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""];
        private _hosts = [_h] + ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select {
            !isNull _x && {_x isNotEqualTo _h} && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} && {[_x] call _isScr} && {(_x distance _h) <= 6}
                && {(["of", _x] call comspec_atak_native_fnc_tocHall) isEqualTo _hall} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) isEqualTo _side}
        });
        // De gauche à droite : relèvement de chaque écran vu par l'opérateur (le joueur qui pose la disposition) ; sans
        // joueur (serveur) : vu depuis 3 m devant l'écran visé.
        private _eye = if (hasInterface && {!isNull player} && {(player distance _h) < 30}) then { getPosASL player } else { _h modelToWorldWorld [0, 3, 0] };
        private _ref = _eye getDir _h;
        private _order = [];
        { _order pushBack [(((_eye getDir _x) - _ref) + 540) mod 360 - 180, _forEachIndex]; } forEach _hosts;
        _order sort true;
        private _row = [];
        { _row append ([_hosts select (_x select 1)] call _rowOf); } forEach _order;
        if ((count _row) isEqualTo 0) exitWith { false };
        [_row, _tokens] call _put;
        true
    };
    default { false };
};
