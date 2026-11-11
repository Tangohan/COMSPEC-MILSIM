/*
    Saisies du poste TOC (4.9.0, « le TOC sans téléphone »). Avant, le flux, la main courante, les demandes et les
    pertes n'étaient alimentés que par les téléphones du camp. Ici l'opérateur transcrit au poste ce qu'il entend à la
    radio, au nom d'un élément : MEDEVAC 9 lignes, demande de tir, ravitaillement, comptes rendus (contact, SITREP,
    SALUTE), pertes, ordre (FRAGO…), message au camp, accusé d'un ordre au nom d'un élément.
    Le serveur retrouve lui-même l'opérateur (fn_tocRoom « req » : camp, nom et identifiant ne viennent jamais du
    client), revalide la saisie puis écrit dans la salle (fn_tocRoom) et le flux (fn_tocFeed) : tout est sauvegardé
    avec la salle (fn_tocPersist) et paraît sur tous les écrans.
    Doublon avec un téléphone : une demande du même genre, du même élément, encore ouverte et vieille de 3 minutes au
    plus n'est pas créée une seconde fois ; la saisie du poste complète la demande existante (détail des lignes).

    Modes :
      ["forms"]                       -> [[genre, titre, champs [[clé, libellé, type, obligatoire, maximum, choix]...]]...]
                                         types : "text" | "grid" (4, 6, 8 ou 10 chiffres) | "int" (0 à maximum) | "list"
      ["validate", [genre, [[clé, valeur]...]]]   pure -> [valide, erreurs [[clé, texte]...], saisie nettoyée [[clé, valeur]...]]
      ["dup", [demandes de la salle, genre, élément, heure ZULU "HH:MM", fenêtre en minutes]]   pure -> rang du doublon ou -1
      ["summary", [genre, saisie nettoyée]]       pure -> texte court (200 caractères au plus) de la demande ou du compte rendu
      ["submit", [genre, [[clé, valeur]...], [x, y] ou []]]   client -> [envoyée, erreurs]
      ["apply", [camp, opérateur, UID, [genre, saisie, [x, y]]]]   serveur (fn_tocRoom « req », émetteur vérifié)
      ["selfTest"]                    -> [réussis, noms des cas en échec]
    Exemple (console du poste) :
      ["submit", ["MEDEVAC", [["el", "ALPHA 1"], ["grid", "043127"], ["a", 1], ["sec", "P"]], []]] call comspec_atak_native_fnc_tocEntry;
*/
params [["_mode", "forms"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocEntry;
// ---- FORMULAIRES (JSON) : [genre, titre, [[clé, libellé, type, obligatoire, maximum, choix]...]].
private _forms = [
["MEDEVAC", "MEDEVAC 9 lignes", [
    ["el", "Élément demandeur", "text", true, 32, []],
    ["grid", "1 · Grille de la zone de poser", "grid", true, 10, []],
    ["freq", "2 · Fréquence et indicatif sur zone", "text", false, 24, []],
    ["a", "3 · Blessés urgents", "int", false, 99, []],
    ["b", "3 · Blessés prioritaires", "int", false, 99, []],
    ["c", "3 · Blessés routine", "int", false, 99, []],
    ["equip", "4 · Équipement spécial", "list", false, 0, ["AUCUN", "TREUIL", "EXTRACTION", "VENTILATEUR"]],
    ["litter", "5 · Blessés couchés", "int", false, 99, []],
    ["amb", "5 · Blessés assis", "int", false, 99, []],
    ["sec", "6 · Sécurité de la zone", "list", false, 0, ["N", "P", "E", "X"]],
    ["mark", "7 · Balisage", "list", false, 0, ["AUCUN", "PANNEAUX", "PYROTECHNIE", "FUMIGENE", "AUTRE"]],
    ["nat", "8 · Statut des blessés", "list", false, 0, ["MILITAIRE AMI", "CIVIL", "PRISONNIER", "AUTRE"]],
    ["terr", "9 · Terrain et obstacles", "text", false, 60, []],
    ["rem", "Remarques", "text", false, 80, []]
]],
["FIRE", "Demande de tir", [
    ["el", "Élément observateur", "text", true, 32, []],
    ["grid", "Grille de la cible", "grid", true, 10, []],
    ["desc", "Description de la cible", "text", true, 80, []],
    ["ammo", "Effet demandé", "list", false, 0, ["HE", "FUMIGENE", "ECLAIRANT", "GUIDE", "APPUI AERIEN"]],
    ["rounds", "Nombre de coups", "int", false, 99, []],
    ["close", "Amis à proximité (danger close)", "list", false, 0, ["NON", "OUI"]],
    ["rem", "Remarques", "text", false, 80, []]
]],
["RESUPPLY", "Demande de ravitaillement", [
    ["el", "Élément demandeur", "text", true, 32, []],
    ["grid", "Grille de livraison", "grid", false, 10, []],
    ["cls", "Nature", "list", false, 0, ["MUNITIONS", "EAU ET VIVRES", "CARBURANT", "MEDICAL", "AUTRE"]],
    ["qty", "Quantité", "int", false, 99, []],
    ["rem", "Détail", "text", false, 80, []]
]],
["CONTACT", "Compte rendu de contact", [
    ["el", "Élément", "text", true, 32, []],
    ["grid", "Grille de l'élément", "grid", false, 10, []],
    ["dir", "Direction de l'ennemi", "text", false, 16, []],
    ["dist", "Distance en mètres", "int", false, 9999, []],
    ["enemy", "Ennemi vu", "text", true, 60, []],
    ["action", "Action en cours", "text", false, 60, []]
]],
["SITREP", "Point de situation", [
    ["el", "Élément", "text", true, 32, []],
    ["grid", "Grille de l'élément", "grid", false, 10, []],
    ["text", "Situation", "text", true, 180, []]
]],
["SALUTE", "Compte rendu SALUTE", [
    ["el", "Élément", "text", true, 32, []],
    ["size", "S · Effectif", "text", true, 40, []],
    ["act", "A · Activité", "text", false, 60, []],
    ["grid", "L · Grille", "grid", false, 10, []],
    ["unit", "U · Unité ou tenue", "text", false, 40, []],
    ["time", "T · Heure d'observation", "text", false, 16, []],
    ["equip", "E · Équipement", "text", false, 60, []]
]],
["CASUALTY", "Pertes et résultats", [
    ["el", "Élément", "text", true, 32, []],
    ["wia", "Blessés amis", "int", false, 99, []],
    ["kia", "Tués amis", "int", false, 99, []],
    ["ekia", "Ennemis neutralisés", "int", false, 99, []],
    ["ewia", "Ennemis blessés ou capturés", "int", false, 99, []]
]],
["ORDER", "Ordre", [
    ["target", "Destinataire (vide : tout le camp)", "text", false, 32, []],
    ["type", "Type d'ordre", "list", false, 0, ["FRAGO", "MOVE", "HOLD", "RECON", "CAS", "QRF", "DEMSSE"]],
    ["text", "Consigne", "text", true, 160, []],
    ["prio", "Priorité", "list", false, 0, ["ROUTINE", "URGENT", "FLASH"]],
    ["grid", "Grille de l'objectif", "grid", false, 10, []]
]],
["MESSAGE", "Message au camp", [
    ["text", "Message", "text", true, 400, []],
    ["prio", "Priorité", "list", false, 0, ["ROUTINE", "URGENT", "FLASH"]]
]],
["ACK", "Accusé d'un ordre au nom d'un élément", [
    ["order", "Ordre", "text", true, 48, []],
    ["el", "Élément qui accuse (compte rendu radio)", "text", true, 32, []],
    ["status", "Réponse", "list", false, 0, ["ACK", "EXEC", "DONE", "FAILED"]]
]]
];
// ---- FIN FORMULAIRES
private _get = { params ["_pairs", "_k", ["_def", ""]]; private _i = _pairs findIf { (_x select 0) isEqualTo _k }; if (_i < 0) then { _def } else { (_pairs select _i) select 1 } };
switch (_mode) do {
    case "forms": { _forms };
    case "validate": {
        _a params [["_kind", ""], ["_pairs", []]];
        private _i = _forms findIf { (_x select 0) isEqualTo _kind };
        if (_i < 0 || {!(_pairs isEqualType [])}) exitWith { [false, [["", "Saisie inconnue"]], []] };
        private _in = createHashMapFromArray (_pairs select { _x isEqualType [] && {(count _x) >= 2} && {(_x select 0) isEqualType ""} });
        private _errs = [];
        private _clean = [];
        private _blank = toString [9, 10, 13];
        {
            _x params ["_key", "_label", "_type", "_req", "_max", "_opts"];
            private _v = _in getOrDefault [_key, ""];
            switch (_type) do {
                case "int": {
                    if (_v isEqualType "") then { _v = parseNumber _v; };
                    if !(_v isEqualType 0) then { _v = 0; };
                    _v = round _v;
                    if (_v < 0 || {_v > _max}) then {
                        _errs pushBack [_key, format ["%1 : entre 0 et %2", _label, _max]];
                        _v = (_v max 0) min _max;
                    };
                };
                case "list": {
                    if !(_v isEqualType "") then { _v = str _v; };
                    _v = toUpper (trim _v);
                    if (_v isEqualTo "") then { _v = _opts select 0; };
                    if !(_v in _opts) then {
                        _errs pushBack [_key, format ["%1 : choix inconnu", _label]];
                        _v = _opts select 0;
                    };
                };
                case "grid": {
                    if !(_v isEqualType "") then { _v = str _v; };
                    _v = (_v splitString " -") joinString "";
                    if (_v isEqualTo "") then {
                        if (_req) then { _errs pushBack [_key, format ["%1 : à renseigner", _label]]; };
                    } else {
                        private _bad = ((toArray _v) findIf { _x < 48 || {_x > 57} }) >= 0;
                        if (_bad || {!((count _v) in [4, 6, 8, 10])}) then {
                            _errs pushBack [_key, format ["%1 : 4, 6, 8 ou 10 chiffres", _label]];
                            _v = "";
                        };
                    };
                };
                default {
                    if !(_v isEqualType "") then { _v = str _v; };
                    _v = trim ((_v splitString _blank) joinString " ");
                    if ((count _v) > _max) then { _v = _v select [0, _max]; };
                    if (_req && {_v isEqualTo ""}) then { _errs pushBack [_key, format ["%1 : à renseigner", _label]]; };
                };
            };
            _clean pushBack [_key, _v];
        } forEach ((_forms select _i) select 2);
        if (_kind isEqualTo "MEDEVAC" && {(([_clean, "a", 0] call _get) + ([_clean, "b", 0] call _get) + ([_clean, "c", 0] call _get)) <= 0}) then {
            _errs pushBack ["a", "MEDEVAC : au moins un blessé à la ligne 3"];
        };
        if (_kind isEqualTo "CASUALTY" && {(([_clean, "wia", 0] call _get) + ([_clean, "kia", 0] call _get) + ([_clean, "ekia", 0] call _get) + ([_clean, "ewia", 0] call _get)) <= 0}) then {
            _errs pushBack ["wia", "Pertes : au moins un nombre"];
        };
        [(count _errs) isEqualTo 0, _errs, _clean]
    };
    case "dup": {
        _a params [["_rows", []], ["_kind", ""], ["_el", ""], ["_hhmm", ""], ["_win", 3]];
        private _min = {
            private _p = _this splitString ":";
            if ((count _p) < 2) exitWith { -1 };
            (parseNumber (_p select 0)) * 60 + (parseNumber (_p select 1))
        };
        private _now = _hhmm call _min;
        if (_now < 0 || {_el isEqualTo ""}) exitWith { -1 };
        _rows findIf {
            private _t = (_x param [1, ""]) call _min;
            private _d = abs (_now - _t);
            if (_d > 720) then { _d = 1440 - _d; };
            _t >= 0 && {(_x param [2, ""]) isEqualTo _kind} && {(toLower (_x param [3, ""])) isEqualTo (toLower _el)} && {(_x param [7, ""]) in ["RECUE", "PRISE"]} && {_d <= _win}
        }
    };
    case "summary": {
        _a params [["_kind", ""], ["_c", []]];
        private _s = { [_c, _this, ""] call _get };
        private _n = { [_c, _this, 0] call _get };
        private _opt = { params ["_lbl", "_v"]; if (_v isEqualType 0) then { _v = [str _v, ""] select (_v <= 0); }; ["", format [" · %1 %2", _lbl, _v]] select (_v isNotEqualTo "" && {!(_v in ["AUCUN", "NON"])}) };
        private _t = switch (_kind) do {
            case "MEDEVAC": {
                format ["Blessés : urgents %1, prioritaires %2, différés %3", "a" call _n, "b" call _n, "c" call _n]
                    + (["couchés", "litter" call _n] call _opt) + (["assis", "amb" call _n] call _opt) + (["équipement", "equip" call _s] call _opt)
                    + (["sécurité", "sec" call _s] call _opt) + (["balisage", "mark" call _s] call _opt) + (["fréq.", "freq" call _s] call _opt) + (["", "rem" call _s] call _opt)
            };
            case "FIRE": {
                format ["Mission de tir : %1 x%2 · %3", "ammo" call _s, ("rounds" call _n) max 1, "desc" call _s]
                    + (["danger close", "close" call _s] call _opt) + (["", "rem" call _s] call _opt)
            };
            case "RESUPPLY": { format ["%1 x%2", "cls" call _s, ("qty" call _n) max 1] + (["", "rem" call _s] call _opt) };
            case "CONTACT": {
                format ["Ennemi : %1", "enemy" call _s] + (["direction", "dir" call _s] call _opt) + (["distance en m", "dist" call _n] call _opt) + (["action", "action" call _s] call _opt)
            };
            case "SITREP": { "text" call _s };
            case "SALUTE": {
                format ["S %1", "size" call _s] + (["A", "act" call _s] call _opt) + (["U", "unit" call _s] call _opt) + (["T", "time" call _s] call _opt) + (["E", "equip" call _s] call _opt)
            };
            case "CASUALTY": {
                format ["Blessés %1, tués %2, ennemis neutralisés %3, ennemis blessés ou capturés %4", "wia" call _n, "kia" call _n, "ekia" call _n, "ewia" call _n]
            };
            default { "text" call _s };
        };
        _t select [0, 200]
    };
    case "submit": {
        _a params [["_kind", ""], ["_pairs", []], ["_pos", []]];
        if (!hasInterface || {isNull player}) exitWith { [false, [["", "Pas de joueur"]]] };
        (["validate", [_kind, _pairs]] call _fn) params ["_ok", "_errs", "_clean"];
        if (!_ok) exitWith {
            ["feedback", ["WARNING", (_errs select 0) select 1]] call comspec_atak_native_fnc_tocInbox;
            [false, _errs]
        };
        if !(_pos isEqualType [] && {(count _pos) >= 2}) then { _pos = []; };
        private _title = ((_forms select (_forms findIf { (_x select 0) isEqualTo _kind })) select 1);
        switch (_kind) do {
            case "ORDER": {
                // Même chemin que le PC du poste : ordre du camp, émetteur « TOC · indicatif », réseau filaire du poste.
                private _tgt = [_clean, "target"] call _get;
                private _type = [_clean, "type"] call _get;
                private _lbl = (createHashMapFromArray [["MOVE", "DÉPLACER"], ["HOLD", "TENIR"], ["RECON", "RECO"], ["CAS", "APPUI AÉRIEN"], ["QRF", "RENFORT"], ["FRAGO", "FRAGO"], ["DEMSSE", "DEM-SSE"]]) getOrDefault [_type, _type];
                ["send", [_type, _lbl, ["group", "all"] select (_tgt isEqualTo ""), _tgt, _tgt, [_clean, "text"] call _get, [_clean, "prio"] call _get, true, _pos]] call comspec_atak_native_fnc_orderLocal;
            };
            case "MESSAGE": {
                private _side = str side group player;
                private _ev = [["callsign"] call comspec_atak_native_fnc_tocPost, [_clean, "text"] call _get, [dayTime, "HH:MM"] call BIS_fnc_timeToString, [_clean, "prio"] call _get, _side,
                    format ["T-%1-%2", getPlayerUID player, floor (serverTime * 10)], getPlayerUID player];
                ["comspec_atak_native_sideEvent", ["comspec_atak_native_tocMsg", _ev, _side]] call CBA_fnc_serverEvent;
            };
            default { ["send", ["entry", [_kind, _clean, _pos]]] call comspec_atak_native_fnc_tocRoom; };
        };
        ["feedback", ["INFO", format ["%1 : envoyé", _title]]] call comspec_atak_native_fnc_tocInbox;
        [true, []]
    };
    case "apply": {
        if (!isServer) exitWith { false };
        // Appel direct d'un joueur par remoteExec : refusé (seul fn_tocRoom « req », qui vérifie l'émetteur, mène ici).
        if (isRemoteExecuted && {!(remoteExecutedOwner in [0, 2])} && {isNil "_tocTrusted"}) exitWith { false };
        _a params [["_side", ""], ["_who", ""], ["_uid", ""], ["_args", []]];
        if !(_args isEqualType []) exitWith { false };
        _args params [["_kind", ""], ["_pairs", []], ["_pos", []]];
        if (!(_kind isEqualType "") || {_kind in ["ORDER", "MESSAGE"]}) exitWith { false };
        (["validate", [_kind, _pairs]] call _fn) params ["_ok", "", "_clean"];
        if (!_ok) exitWith { false };
        private _room = comspec_atak_native_fnc_tocRoom;
        if (_kind isEqualTo "ACK") exitWith {
            ["ackFor", [_side, _who, _uid, [[_clean, "order"] call _get, [_clean, "el"] call _get, [_clean, "status"] call _get]]] call comspec_atak_native_fnc_tocInbox
        };
        private _el = [_clean, "el"] call _get;
        private _grid = [_clean, "grid"] call _get;
        // Position : celle cliquée sur la carte du poste, sinon le centre de la case de la grille tapée.
        if !(_pos isEqualType [] && {(count _pos) >= 2} && {(_pos select 0) isEqualType 0} && {(_pos select 1) isEqualType 0}) then { _pos = []; };
        if (_pos isEqualTo [] && {_grid isNotEqualTo ""} && {!isNil "BIS_fnc_gridToPos"}) then {
            private _g = [_grid] call BIS_fnc_gridToPos;
            if (_g isEqualType [] && {(count _g) >= 2}) then {
                _g params ["_o", "_sz"];
                _pos = [(_o select 0) + (_sz select 0) / 2, (_o select 1) + (_sz select 1) / 2];
            };
        };
        if (_pos isNotEqualTo []) then { _pos = [round (_pos select 0), round (_pos select 1)]; };
        private _text = ["summary", [_kind, _clean]] call _fn;
        private _time = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        if (_kind isEqualTo "CASUALTY") exitWith {
            { private _n = [_clean, _x, 0] call _get; if (_n > 0) then { ["do", [_side, "kill", [_el, _x, _n], _who, _uid]] call _room; }; } forEach ["wia", "kia", "ekia", "ewia"];
            ["do", [_side, "log", ["PERTES", format ["%1 · %2", _el, _text]], _who, _uid]] call _room;
            true
        };
        if (_kind in ["CONTACT", "SITREP", "SALUTE"]) exitWith {
            private _lbl = _kind;
            ["push", [_side, [_time, _lbl, ["", "URGENT"] select (_kind isEqualTo "CONTACT"), _el, _grid, _text, _pos]]] call comspec_atak_native_fnc_tocFeed;
            ["do", [_side, "log", ["CR", format ["%1 de %2%3 (saisi au poste)", _lbl, _el, ["", " · grille " + _grid] select (_grid isNotEqualTo "")]], _who, _uid]] call _room;
            true
        };
        // Demandes : MEDEVAC, tir, ravitaillement. Détail des lignes gardé avec la demande.
        private _rk = (createHashMapFromArray [["MEDEVAC", "MEDEVAC"], ["FIRE", "APPUI FEU"], ["RESUPPLY", "RAVITAILLEMENT"]]) getOrDefault [_kind, ""];
        if (_rk isEqualTo "") exitWith { false };
        private _labels = createHashMapFromArray ((((_forms select (_forms findIf { (_x select 0) isEqualTo _kind })) select 2)) apply { [_x select 0, _x select 1] });
        private _extra = (_clean select { private _v = _x select 1; !(_v isEqualTo "" || {_v isEqualTo 0}) }) apply { [_labels getOrDefault [_x select 0, _x select 0], [str (_x select 1), _x select 1] select ((_x select 1) isEqualType "")] };
        private _rows = (["state", _side] call _room) select 5;
        private _dup = ["dup", [_rows, _rk, _el, (["zulu"] call _room) select [0, 5], 3]] call _fn;
        if (_dup >= 0) exitWith {
            // Déjà reçue par le réseau ATAK : la saisie du poste la complète, sans seconde demande.
            ["do", [_side, "reqExtra", [(_rows select _dup) select 0, _extra], _who, _uid]] call _room;
            true
        };
        // Demandeur : le chef joueur de l'élément, s'il existe, lira la réponse dans sa fenêtre « Messages et ordres du TOC ».
        private _byUid = "";
        {
            if ((str side _x) isEqualTo _side && {(toLower (groupId _x)) isEqualTo (toLower _el)} && {isPlayer (leader _x)}) exitWith { _byUid = getPlayerUID (leader _x); };
        } forEach allGroups;
        ["do", [_side, "reqAdd", [_rk, _el, _byUid, _grid, _text, _pos, _extra], _who, _uid]] call _room;
        ["push", [_side, [_time, _rk, "URGENT", _el, _grid, _text, _pos]]] call comspec_atak_native_fnc_tocFeed;
        true
    };
    case "selfTest": {
        private _ok = 0;
        private _bad = [];
        // ---- CAS VALIDATION (JSON) : [nom, genre, saisie, valide attendu, clés en erreur attendues].
        private _casesValid = [
            ["MEDEVAC complet", "MEDEVAC", [["el", "ALPHA 1"], ["grid", "043 127"], ["a", 1], ["sec", "p"]], true, []],
            ["MEDEVAC sans grille", "MEDEVAC", [["el", "ALPHA 1"], ["a", 1]], false, ["grid"]],
            ["MEDEVAC sans blessé", "MEDEVAC", [["el", "ALPHA 1"], ["grid", "043127"]], false, ["a"]],
            ["grille de 5 chiffres", "FIRE", [["el", "BRAVO"], ["grid", "04312"], ["desc", "BMP"]], false, ["grid"]],
            ["grille avec lettres", "FIRE", [["el", "BRAVO"], ["grid", "04A127"], ["desc", "BMP"]], false, ["grid"]],
            ["choix inconnu", "FIRE", [["el", "BRAVO"], ["grid", "043127"], ["desc", "BMP"], ["ammo", "NUCLEAIRE"]], false, ["ammo"]],
            ["nombre hors bornes", "RESUPPLY", [["el", "CHARLIE"], ["qty", 500]], false, ["qty"]],
            ["nombre tapé en texte", "RESUPPLY", [["el", "CHARLIE"], ["qty", "12"]], true, []],
            ["élément manquant", "SITREP", [["text", "RAS"]], false, ["el"]],
            ["pertes sans nombre", "CASUALTY", [["el", "ALPHA 1"]], false, ["wia"]],
            ["pertes", "CASUALTY", [["el", "ALPHA 1"], ["kia", 2]], true, []],
            ["genre inconnu", "PIZZA", [["el", "ALPHA 1"]], false, [""]],
            ["accusé au nom d'un élément", "ACK", [["order", "L-1-2"], ["el", "ALPHA 1"], ["status", "ack"]], true, []],
            ["ordre sans consigne", "ORDER", [["target", "ALPHA 1"]], false, ["text"]]
        ];
        // ---- FIN CAS VALIDATION
        {
            _x params ["_name", "_kind", "_pairs", "_want", "_keys"];
            (["validate", [_kind, _pairs]] call _fn) params ["_valid", "_errs"];
            if (_valid isEqualTo _want && {(_errs apply { _x select 0 }) isEqualTo _keys}) then { _ok = _ok + 1; } else { _bad pushBack _name; };
        } forEach _casesValid;
        // ---- CAS DOUBLON (JSON) : [nom, demandes [[id, heure, genre, demandeur, uid, grille, texte, statut]...], genre, élément, heure, rang attendu].
        private _casesDup = [
            ["même élément, deux minutes après", [["R1", "10:00", "MEDEVAC", "Alpha 1", "", "", "", "RECUE"]], "MEDEVAC", "ALPHA 1", "10:02", 0],
            ["même élément, dix minutes après", [["R1", "10:00", "MEDEVAC", "ALPHA 1", "", "", "", "RECUE"]], "MEDEVAC", "ALPHA 1", "10:10", -1],
            ["autre genre", [["R1", "10:00", "APPUI FEU", "ALPHA 1", "", "", "", "RECUE"]], "MEDEVAC", "ALPHA 1", "10:01", -1],
            ["autre élément", [["R1", "10:00", "MEDEVAC", "BRAVO", "", "", "", "RECUE"]], "MEDEVAC", "ALPHA 1", "10:01", -1],
            ["demande déjà terminée", [["R1", "10:00", "MEDEVAC", "ALPHA 1", "", "", "", "TERMINEE"]], "MEDEVAC", "ALPHA 1", "10:01", -1],
            ["passage de minuit", [["R1", "23:59", "MEDEVAC", "ALPHA 1", "", "", "", "PRISE"]], "MEDEVAC", "ALPHA 1", "00:01", 0],
            ["la seconde des deux", [["R1", "09:00", "MEDEVAC", "ALPHA 1", "", "", "", "RECUE"], ["R2", "10:00", "MEDEVAC", "ALPHA 1", "", "", "", "RECUE"]], "MEDEVAC", "ALPHA 1", "10:01", 1]
        ];
        // ---- FIN CAS DOUBLON
        {
            _x params ["_name", "_rows", "_kind", "_el", "_hhmm", "_want"];
            if ((["dup", [_rows, _kind, _el, _hhmm, 3]] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _name; };
        } forEach _casesDup;
        [_ok, _bad]
    };
    default { false };
};
