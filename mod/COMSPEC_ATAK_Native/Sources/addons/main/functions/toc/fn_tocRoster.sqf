/*
    Tableau des effectifs du TOC (demande de TTRD : « il faut aussi un tableau récap des joueurs pour dire INDICATIF,
    RÔLE, NOM et PRÉNOM »). Fournisseur de données : une ligne par joueur du camp (IA en option), lue dans le jeu sur
    le poste de qui regarde, sans téléphone et sans requête. Panneaux « ROSTER » (console) et « ROSTERWALL » (écrans)
    du registre (fn_tocPanels).
    NOM et PRÉNOM sont ceux du PERSONNAGE : l'identité SSE active du joueur (COMSPEC_SSE_LastName, COMSPEC_SSE_FirstName,
    publiées sur l'unité par le mod SSE). Jamais une donnée de la vraie vie, jamais le pseudo Steam ; aucune identité : « — ».
    Réglage du serveur comspec_atak_native_toc_roster_names : "console" (défaut : nom et prénom au PC du poste seulement,
    les écrans du mur montrent INDICATIF et RÔLE), "off" (nulle part), "all" (partout).

    Ligne : [0 clé (netId), 1 indicatif (fn_tocDisplayName), 2 rôle, 3 nom, 4 prénom, 5 unité (groupe), 6 état,
             7 grille, 8 mise à jour (serverTime), 9 IA, 10 compte relié, 11 a un ATAK, 12 [x, y] ou []]
    État : "MORT" | "BLESSÉ" | "EN VÉHICULE" | "SANS TÉLÉPHONE" | "EN LIGNE"

    Modes :
      ["rows", [camp, avec les IA]]            lignes brutes (gardées 3 s), nom et prénom compris
      ["console", [camp, recherche, tri, décroissant, avec les IA]]   lignes pour le PC du poste, réglage appliqué
                                               tri : "cs" | "role" | "last" | "first" | "unit" | "state"
      ["wall", [camp, page, lignes par page]]  [colonnes, lignes, page, pages, total] pour un écran : groupé par unité,
                                               une ligne de titre par unité ["#", unité, effectif, …], réglage appliqué
      ["columns", "console" | "wall"]          [[clé, en-tête, part de la largeur]...] selon le réglage
      ["export", camp]                         texte du tableau (une ligne par joueur, tabulations), réglage du PC
      ["action", [clé, "follow" | "message" | "order"]]   suivre au satellite 1, ou [genre, saisie] à ouvrir dans fn_tocEntry
      ["role", [fonction ORBAT ou rôle du slot, ACE médecin 0-2, ACE sapeur 0-2, ACE EOD, poste à bord, chef de groupe, rôle ATAK]]   pure
      ["state", [vivant, santé 0-3, en véhicule, a un ATAK]]                  pure
      ["identity", [nom, prénom, réglage, "console" | "wall"]]                pure -> [nom, prénom, montrés]
      ["selfTest"]                             [réussis, noms des cas en échec]
    Exemple : (["wall", ["WEST", 0, 12]] call comspec_atak_native_fnc_tocRoster) params ["_cols", "_lignes", "_page", "_pages"];
*/
params [["_mode", "rows"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocRoster;
private _setting = {
    private _v = missionNamespace getVariable ["comspec_atak_native_toc_roster_names", "console"];
    if !(_v isEqualType "" && {_v in ["console", "off", "all"]}) then { _v = "console"; };
    _v
};
switch (_mode) do {
    case "role": {
        _a params [["_fn0", ""], ["_medic", 0], ["_eng", 0], ["_eod", false], ["_crew", ""], ["_lead", false], ["_atak", ""]];
        // Fonction ORBAT ou rôle du slot d'abord ; puis ACE, poste à bord, rôle ATAK ; un chef sans rien d'autre : « Chef de groupe ».
        private _ace = switch (true) do {
            case (_medic >= 2): { "Médecin" };
            case (_medic >= 1): { "Infirmier" };
            case (_eod): { "EOD" };
            case (_eng >= 2): { "Sapeur confirmé" };
            case (_eng >= 1): { "Sapeur" };
            default { "" };
        };
        private _crewL = (createHashMapFromArray [["driver", "Pilote ou conducteur"], ["commander", "Chef de bord"], ["gunner", "Tireur"], ["turret", "Tireur"], ["cargo", ""]]) getOrDefault [toLower _crew, ""];
        private _l = [_fn0, _ace, _crewL, _atak, ["", "Chef de groupe"] select _lead] select { _x isEqualType "" && {_x isNotEqualTo ""} };
        if (_l isEqualTo []) exitWith { "—" };
        // Rôle principal, complété par le poste à bord quand il dit autre chose.
        private _out = _l select 0;
        if (_crewL isNotEqualTo "" && {_out isNotEqualTo _crewL}) then { _out = format ["%1 · %2", _out, _crewL]; };
        _out
    };
    case "state": {
        _a params [["_alive", true], ["_life", 0], ["_inVeh", false], ["_phone", true]];
        switch (true) do {
            case (!_alive || {_life >= 3}): { "MORT" };
            case (_life >= 1): { "BLESSÉ" };
            case (_inVeh): { "EN VÉHICULE" };
            case (!_phone): { "SANS TÉLÉPHONE" };
            default { "EN LIGNE" };
        }
    };
    case "identity": {
        _a params [["_last", ""], ["_first", ""], ["_set", "console"], ["_ctx", "console"]];
        private _show = _set isEqualTo "all" || {_set isEqualTo "console" && {_ctx isEqualTo "console"}};
        if (!_show) exitWith { ["", "", false] };
        if !(_last isEqualType "") then { _last = ""; };
        if !(_first isEqualType "") then { _first = ""; };
        _last = trim _last; _first = trim _first;
        [["—", _last] select (_last isNotEqualTo ""), ["—", _first] select (_first isNotEqualTo ""), true]
    };
    case "columns": {
        private _names = (["identity", ["x", "x", call _setting, _a]] call _fn) select 2;
        if (_names) exitWith { [["cs", "INDICATIF", 0.2], ["role", "RÔLE", 0.22], ["last", "NOM", 0.15], ["first", "PRÉNOM", 0.13], ["unit", "UNITÉ", 0.16], ["state", "ÉTAT", 0.14]] };
        [["cs", "INDICATIF", 0.28], ["role", "RÔLE", 0.32], ["unit", "UNITÉ", 0.22], ["state", "ÉTAT", 0.18]]
    };
    case "rows": {
        _a params [["_side", ""], ["_withAi", false]];
        if (!(_side isEqualType "") || {_side isEqualTo ""}) exitWith { [] };
        private _key = [_side, _withAi, floor (diag_tickTime / 3)];
        private _c = missionNamespace getVariable ["COMSPEC_ATAK_TocRosterCache", [[], []]];
        if ((_c select 0) isEqualTo _key) exitWith { _c select 1 };
        // Grille : celle du flux du serveur (respecte « Source des positions du TOC »), jamais une lecture à part.
        private _nodes = ((["feed", _side] call comspec_atak_native_fnc_tocPanels) param [1, []]);
        private _pos = createHashMap;
        { _pos set [_x param [0, ""], _x param [2, []]]; } forEach _nodes;
        private _byGroup = createHashMap;
        { if !((_x param [7, ""]) in _byGroup) then { _byGroup set [_x param [7, ""], _x param [2, []]]; }; } forEach _nodes;
        private _rows = [];
        private _atakOnly = ["only"] call comspec_atak_native_fnc_tocHasAtak;
        {
            private _g = _x;
            if ((str side _g) isNotEqualTo _side) then { continue };
            {
                private _u = _x;
                private _isAi = !isPlayer _u;
                if (_isAi && {!_withAi}) then { continue };
                if (unitIsUAV (vehicle _u)) then { continue };
                // Filtre « seulement les entités équipées d'un ATAK » (le joueur lui-même et un opérateur au poste restent).
                if (_atakOnly && {!(["keep", _u] call comspec_atak_native_fnc_tocHasAtak)}) then { continue };
                private _v = vehicle _u;
                private _inVeh = _v isNotEqualTo _u;
                private _crew = "";
                if (_inVeh) then { _crew = ((fullCrew _v) select { (_x select 0) isEqualTo _u }) param [0, [objNull, ""]] select 1; };
                private _life = switch (true) do {
                    case (!alive _u): { 3 };
                    case ((lifeState _u) isEqualTo "INCAPACITATED" || {(_u getVariable ["ACE_isUnconscious", false]) isEqualTo true}): { 2 };
                    case ((damage _u) >= 0.25): { 1 };
                    default { 0 };
                };
                private _num = { private _n = _u getVariable [_this, 0]; if (_n isEqualType true) then { parseNumber _n } else { [0, _n] select (_n isEqualType 0) } };
                private _slotRole = ["", [_u, "role"] call comspec_atak_native_fnc_displayRole] select (!_isAi);
                if !(_slotRole isEqualType "") then { _slotRole = ""; };
                private _phone = [_u] call comspec_atak_native_fnc_hasDevice;
                private _p = _pos getOrDefault [netId _u, _byGroup getOrDefault [groupId _g, []]];
                _rows pushBack [netId _u, [_u] call comspec_atak_native_fnc_tocDisplayName,
                    ["role", [_slotRole, "ace_medical_medicClass" call _num, "ACE_isEngineer" call _num, (_u getVariable ["ACE_isEOD", false]) isEqualTo true, _crew, _u isEqualTo (leader _g), ""]] call _fn,
                    _u getVariable ["COMSPEC_SSE_LastName", ""], _u getVariable ["COMSPEC_SSE_FirstName", ""], groupId _g,
                    ["state", [alive _u, _life, _inVeh, _phone]] call _fn,
                    ["", [[_p param [0, 0], _p param [1, 0], 0], 6] call comspec_atak_native_fnc_gridRef] select ((count _p) >= 2),
                    serverTime, _isAi, ["linked", _u] call comspec_atak_native_fnc_tocDisplayName, _phone, _p];
            } forEach (units _g);
        } forEach allGroups;
        missionNamespace setVariable ["COMSPEC_ATAK_TocRosterCache", [_key, _rows]];
        _rows
    };
    case "console": {
        _a params [["_side", ""], ["_q", ""], ["_sort", "unit"], ["_desc", false], ["_withAi", false]];
        private _set = call _setting;
        private _rows = (["rows", [_side, _withAi]] call _fn) apply {
            private _r = +_x;
            (["identity", [_r select 3, _r select 4, _set, "console"]] call _fn) params ["_l", "_f"];
            _r set [3, _l]; _r set [4, _f];
            _r
        };
        if (_q isEqualType "" && {(trim _q) isNotEqualTo ""}) then {
            private _needle = toLower (trim _q);
            _rows = _rows select { ((toLower ([_x select 1, _x select 2, _x select 3, _x select 4, _x select 5] joinString " ")) find _needle) >= 0 };
        };
        private _i = (createHashMapFromArray [["cs", 1], ["role", 2], ["last", 3], ["first", 4], ["unit", 5], ["state", 6]]) getOrDefault [_sort, 5];
        private _keyed = _rows apply { [toLower (_x select _i), toLower (_x select 1), _x] };
        _keyed sort !_desc;
        _keyed apply { _x select 2 }
    };
    case "wall": {
        _a params [["_side", ""], ["_page", 0], ["_per", 12]];
        private _set = call _setting;
        private _rows = ["rows", [_side, false]] call _fn;
        // Groupé par unité, titre « unité · effectif ».
        private _units = [];
        { _units pushBackUnique (_x select 5); } forEach _rows;
        _units sort true;
        private _lines = [];
        {
            private _un = _x;
            private _mine = _rows select { (_x select 5) isEqualTo _un };
            _lines pushBack ["#", _un, count _mine, { (_x select 6) in ["MORT", "BLESSÉ"] } count _mine];
            {
                (["identity", [_x select 3, _x select 4, _set, "wall"]] call _fn) params ["_l", "_f"];
                _lines pushBack ["", _x select 1, _x select 2, _l, _f, _x select 5, _x select 6];
            } forEach _mine;
        } forEach _units;
        _per = (round _per) max 4;
        private _pages = (ceil ((count _lines) / _per)) max 1;
        if !(_page isEqualType 0) then { _page = 0; };
        _page = (round _page) mod _pages;
        if (_page < 0) then { _page = 0; };
        [["columns", "wall"] call _fn, _lines select [_page * _per, _per], _page, _pages, count _rows]
    };
    case "export": {
        private _side = _a;
        private _cols = ["columns", "console"] call _fn;
        private _idx = createHashMapFromArray [["cs", 1], ["role", 2], ["last", 3], ["first", 4], ["unit", 5], ["state", 6]];
        private _tab = toString [9];
        private _out = [(_cols apply { _x select 1 }) joinString _tab];
        {
            private _r = _x;
            _out pushBack ((_cols apply { _r select (_idx get (_x select 0)) }) joinString _tab);
        } forEach (["console", [_side, "", "unit", false, false]] call _fn);
        _out joinString (toString [10])
    };
    case "action": {
        _a params [["_key", ""], ["_verb", ""]];
        private _u = objectFromNetId _key;
        if (isNull _u) exitWith { [] };
        switch (_verb) do {
            case "follow": { ["send", ["sat", [0, "follow", ["U", _key]]]] call comspec_atak_native_fnc_tocRoom; ["follow", _key] };
            case "message": { ["MESSAGE", [["text", format ["%1 : ", [_u] call comspec_atak_native_fnc_tocDisplayName]]]] };
            case "order": { ["ORDER", [["target", groupId group _u]]] };
            default { [] };
        }
    };
    case "selfTest": {
        private _ok = 0;
        private _bad = [];
        // ---- CAS ROLE (JSON) : [nom, [fonction ou slot, médecin, sapeur, EOD, poste à bord, chef, rôle ATAK], attendu].
        private _casesRole = [
            ["fonction de l'ORBAT d'abord", ["Chef d'équipe", 2, 0, false, "", true, "JTAC"], "Chef d'équipe"],
            ["sans fonction : médecin ACE", ["", 2, 0, false, "", false, ""], "Médecin"],
            ["infirmier ACE", ["", 1, 1, false, "", false, ""], "Infirmier"],
            ["EOD avant sapeur", ["", 0, 1, true, "", false, ""], "EOD"],
            ["sapeur ACE", ["", 0, 1, false, "", false, ""], "Sapeur"],
            ["poste à bord seul", ["", 0, 0, false, "gunner", false, ""], "Tireur"],
            ["fonction et poste à bord", ["Chef de char", 0, 0, false, "commander", true, ""], "Chef de char · Chef de bord"],
            ["passager : pas de poste", ["Fusilier", 0, 0, false, "cargo", false, ""], "Fusilier"],
            ["rôle ATAK en dernier", ["", 0, 0, false, "", false, "JTAC"], "JTAC"],
            ["chef sans rien d'autre", ["", 0, 0, false, "", true, ""], "Chef de groupe"],
            ["rien", ["", 0, 0, false, "", false, ""], "—"]
        ];
        // ---- FIN CAS ROLE
        { _x params ["_n", "_args", "_want"]; if ((["role", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _n; }; } forEach _casesRole;
        // ---- CAS IDENTITE (JSON) : [nom, [nom, prénom, réglage, contexte], [nom, prénom, montrés]].
        private _casesIdentity = [
            ["console, réglage par défaut", ["DURAND", "Paul", "console", "console"], ["DURAND", "Paul", true]],
            ["mur, réglage par défaut : caché", ["DURAND", "Paul", "console", "wall"], ["", "", false]],
            ["partout", ["DURAND", "Paul", "all", "wall"], ["DURAND", "Paul", true]],
            ["nulle part, même au PC", ["DURAND", "Paul", "off", "console"], ["", "", false]],
            ["aucune identité SSE", ["", "", "console", "console"], ["—", "—", true]],
            ["prénom seul", [" ", "Paul", "all", "console"], ["—", "Paul", true]]
        ];
        // ---- FIN CAS IDENTITE
        { _x params ["_n", "_args", "_want"]; if ((["identity", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _n; }; } forEach _casesIdentity;
        // ---- CAS ETAT (JSON) : [nom, [vivant, santé, en véhicule, a un ATAK], attendu].
        private _casesState = [
            ["mort d'abord", [false, 3, true, true], "MORT"],
            ["inconscient compte comme blessé", [true, 2, true, true], "BLESSÉ"],
            ["blessé à pied sans téléphone", [true, 1, false, false], "BLESSÉ"],
            ["en véhicule", [true, 0, true, false], "EN VÉHICULE"],
            ["sans téléphone", [true, 0, false, false], "SANS TÉLÉPHONE"],
            ["en ligne", [true, 0, false, true], "EN LIGNE"]
        ];
        // ---- FIN CAS ETAT
        { _x params ["_n", "_args", "_want"]; if ((["state", _args] call _fn) isEqualTo _want) then { _ok = _ok + 1; } else { _bad pushBack _n; }; } forEach _casesState;
        [_ok, _bad]
    };
    default { [] };
};
