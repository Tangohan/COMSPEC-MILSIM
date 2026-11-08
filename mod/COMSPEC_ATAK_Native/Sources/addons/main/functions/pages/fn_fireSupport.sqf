/*
    Appui feu typé : complète l'app CAS (onglet APPEL DE FEU), la carte du téléphone, l'app Tâches, le PC du poste TOC
    et l'app JTAC ; aucune app nouvelle. Réglage CBA « Appui feu typé » (ATAK · Appui feu) :
      - décoché : appel de feu générique, comme avant (fn_firesAction) ; rien n'est chargé, scanné ni suivi ;
      - coché : l'appel de feu choisit un MOYEN (mortier léger, moyen, lourd, obusier, lance-roquettes, naval, aérien)
        avec ses portées, délais, munitions, dispersion, nombre de coups, angle de chute et distance de sécurité
        (danger close). Pièces de la mission reconnues par leur config (calculateur d'artillerie, support « Artillery »,
        mortiers statiques, calibre des munitions ; la mission peut forcer : _v setVariable ["COMSPEC_ATAK_FsType", "MORTAR_H", true]).
    Parcours d'un tir IA ou simulé : client "request" → serveur "srv" (droits, réglages, portée, munition) → machine du
    servant IA ("exec", événement comspec_atak_native_fsExec) ou serveur ("sim", batterie simulée) → état publié au camp
    (comspec_atak_native_fsState par comspec_atak_native_sideEvent), gardé pour les arrivants (COMSPEC_ATAK_SrvFs).
    Servant joueur : la mission lui arrive par le même état (Tâches, notification, solution de tir calculée chez lui).
    Appareil IA (CAS) : ordre ATTAQUER la zone par le chemin des ordres aux IA (fn_aiOrder : droits, refus, retours).
    Rien par image hors du dessin de la carte ; pièces relues au démarrage puis aux événements EntityCreated / EntityKilled.
      "on"                          -> système activé (réglage serveur)
      "types"                       -> fiches (HashMap) des moyens autorisés          "type" clé -> fiche ({} si inconnue)
      "classify" véhicule           -> clé du moyen ("" : pas une pièce)               "scan" / "add" véhicule (client)
      "guns" clé                    -> pièces vivantes de ce moyen, vides ou servies par mon camp (artillerie si "")
      "mag" [pièce, HE|SMOKE|ILLUM] -> chargeur ("" si absent)                         "charges" [pièce, chargeur]
      "range" [pièce, chargeur, clé]-> [portée min, portée max] (≈ balistique de la config, table du moyen à défaut)
      "inRange" [pièce, chargeur, position, clé] -> booléen
      "dc" [position, clé]          -> [distance de sécurité m, ami le plus proche m (-1 : aucun)]
      "solve" [pièce, position, munition] -> solution (HashMap : mils, dist, elev, charge, eta, inRange, mag)
      "can" unité                   -> peut demander un tir à une pièce IA
      "request" HashMap             -> (client) envoie la mission ; renvoie la façon (« tir IA »...) ou "!motif"
      "srv" [joueur, paires] (serveur)   "exec" paires (machine du servant)   "sim" paires (serveur)
      "state" [id, paires, camp]    -> publie un état au camp (toute machine)          "pub" [id, paires] (client)
      "keep" / "recv" [id, paires, camp]   mémoire du serveur / réception du client
      "crew" [id, état]             -> réponse du servant joueur (REÇU, COUPS PARTIS, IMPOSSIBLE)
      "eom" id | "open" id          -> fin de mission | mission rouverte dans l'app CAS
      "rows" / "tocText"            -> lignes de l'app Tâches / texte de l'onglet ORDRES du poste TOC
      "draw" [carte, taille du texte, police]   cercles de portée, zone dangereuse et missions en cours
    États : NEW (transmise), ACK (reçue par le servant), EXEC (en préparation), SHOT (coups partis), SPLASH (impact
    dans 5 s), DONE (tir fini : réglage, EFFICACE ou FIN DE MISSION), FAIL (impossible), EOM (fin de mission).
*/
params [["_act", ""], ["_arg", []]];
private _fn = comspec_atak_native_fnc_fireSupport;
private _on = missionNamespace getVariable ["comspec_atak_native_fs_enabled", true];
// Carte (à chaque image) : traité avant de construire les tables ci-dessous.
if (_act isEqualTo "draw") exitWith {
    if (!_on) exitWith {};
    _arg params ["_map", ["_ts", 0.03], ["_font", "RobotoCondensedBold"]];
    if (missionNamespace getVariable ["comspec_atak_native_fs_rings", true]) then {
        private _f = uiNamespace getVariable ["COMSPEC_ATAK_Fires", createHashMap];
        (_f getOrDefault ["fsRing", []]) params [["_c", []], ["_rMin", 0], ["_rMax", 0], ["_lbl", ""]];
        if ((count _c) >= 2 && {_rMax > 0}) then {
            _map drawEllipse [_c, _rMax, _rMax, 0, [0.30, 0.70, 1, 0.85], ""];
            if (_rMin > 0) then { _map drawEllipse [_c, _rMin, _rMin, 0, [0.95, 0.60, 0.20, 0.85], ""]; };
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [0.30, 0.70, 1, 1], _c getPos [_rMax, 0], 1, 1, 0, _lbl, 1, _ts, _font, "center"];
        };
        (_f getOrDefault ["fsDcRing", []]) params [["_p", []], ["_r", 0], ["_dl", ""]];
        if ((count _p) >= 2 && {_r > 0}) then {
            _map drawEllipse [_p, _r, _r, 0, [0.95, 0.30, 0.25, 0.9], ""];
            _map drawEllipse [_p, _r, _r, 0, [0.95, 0.30, 0.25, 0.07], "#(rgb,8,8,3)color(1,1,1,1)"];
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [0.95, 0.40, 0.35, 1], _p getPos [_r, 180], 1, 1, 0, _dl, 1, _ts, _font, "center"];
        };
    };
    private _all = missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap];
    {
        private _st = _y getOrDefault ["state", ""];
        private _t = _y getOrDefault ["tgt", []];
        if ((!(_st in ["EOM", "FAIL"]) || {(serverTime - (_y getOrDefault ["end", -1e9])) < 60}) && {(count _t) >= 2}) then {
            private _c = switch (_st) do {
                case "SHOT";
                case "SPLASH": { [1, 0.35, 0.25, 1] };
                case "EXEC";
                case "ACK": { [1, 0.80, 0.25, 1] };
                case "DONE": { [0.36, 0.85, 0.42, 0.9] };
                case "FAIL";
                case "EOM": { [0.6, 0.62, 0.6, 0.7] };
                default { [0.5, 0.75, 1, 1] };
            };
            private _p3 = [_t select 0, _t select 1, 0];
            private _gp = _y getOrDefault ["gunPos", []];
            if ((count _gp) >= 2) then { _map drawLine [[_gp select 0, _gp select 1, 0], _p3, [_c select 0, _c select 1, _c select 2, 0.55]]; };
            _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", _c, _p3, 22, 22, 0, _y getOrDefault ["lbl", ""], 1, _ts, _font, "right"];
        };
    } forEach _all;
};
// Moyens : clé, libellé, court, portée min, portée max (m), réaction (s), vol simulé (s), munitions, dispersion (m),
// coups max, angle de chute, danger close (m, distances de sécurité usuelles), munitions de la batterie simulée [HE, FUM, ÉCL].
private _table = [
    ["MORTAR_L", "Mortier léger 60 mm", "MORTIER 60", 70, 3500, 30, 20, ["HE", "SMOKE", "ILLUM"], 25, 12, "tir courbe (45-85°)", 600, ["Sh_82mm_AMOS", "Smoke_82mm_AMOS_White", "Flare_82mm_AMOS_White"]],
    ["MORTAR_M", "Mortier moyen 81/82 mm", "MORTIER 81", 80, 5600, 45, 25, ["HE", "SMOKE", "ILLUM"], 35, 12, "tir courbe (45-85°)", 600, ["Sh_82mm_AMOS", "Smoke_82mm_AMOS_White", "Flare_82mm_AMOS_White"]],
    ["MORTAR_H", "Mortier lourd 120 mm", "MORTIER 120", 200, 7200, 60, 30, ["HE", "SMOKE", "ILLUM"], 50, 10, "tir courbe (45-85°)", 600, ["Sh_155mm_AMOS", "Smoke_120mm_AMOS_White", "Flare_82mm_AMOS_White"]],
    ["HOWITZER", "Obusier 105/155 mm", "OBUSIER", 1500, 24000, 90, 45, ["HE", "SMOKE", "ILLUM"], 60, 8, "tir tendu ou courbe", 600, ["Sh_155mm_AMOS", "Smoke_120mm_AMOS_White", "Flare_82mm_AMOS_White"]],
    ["MLRS", "Lance-roquettes multiples", "LRM", 4000, 40000, 120, 60, ["HE"], 150, 12, "tir tendu, salve", 1000, ["Sh_155mm_AMOS", "", ""]],
    ["NAVAL", "Appui naval", "NAVAL", 2000, 24000, 60, 40, ["HE", "ILLUM"], 60, 10, "tir tendu", 750, ["Sh_155mm_AMOS", "", "Flare_82mm_AMOS_White"]],
    ["CAS", "Appui aérien (CAS)", "CAS", 0, 0, 180, 5, ["HE"], 30, 2, "piqué / bombe", 1000, ["Bo_GBU12_LGB", "", ""]]
];
private _mkT = {
    params ["_r"];
    createHashMapFromArray [["key", _r select 0], ["label", _r select 1], ["short", _r select 2], ["min", _r select 3], ["max", _r select 4], ["react", _r select 5],
        ["tof", _r select 6], ["ammo", _r select 7], ["disp", _r select 8], ["rounds", _r select 9], ["angle", _r select 10], ["dc", _r select 11], ["sim", _r select 12]]
};
private _stLbl = createHashMapFromArray [["NEW", "transmise"], ["ACK", "reçue"], ["EXEC", "en préparation"], ["SHOT", "coups partis"], ["SPLASH", "splash"], ["DONE", "tir fini"], ["FAIL", "impossible"], ["EOM", "fin de mission"]];
private _ammoLbl = createHashMapFromArray [["HE", "HE"], ["SMOKE", "FUM"], ["ILLUM", "ÉCL"]];
// Délais : réalistes (1), réduits (0,35) ou aucun (0), réglage serveur.
private _delay = missionNamespace getVariable ["comspec_atak_native_fs_delay", 1];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// Réseau : HashMap mise à plat en paires [[clé, valeur]...] (jamais de HashMap sur le réseau).
private _pairsOf = { (keys _this) apply { [_x, _this get _x] } };
private _crewOf = { params ["_v", "_k"]; if (_k isEqualTo "CAS" || {_v isKindOf "Air"}) then { effectiveCommander _v } else { gunner _v } };
switch (_act) do {
    case "on": { _on };
    case "type": {
        private _i = _table findIf { (_x select 0) isEqualTo _arg };
        if (_i < 0) then { createHashMap } else { [_table select _i] call _mkT }
    };
    case "types": {
        (_table select { missionNamespace getVariable [format ["comspec_atak_native_fs_t_%1", toLower (_x select 0)], true] }) apply { [_x] call _mkT }
    };
    case "classify": {
        private _v = _arg;
        if (isNull _v || {_v isKindOf "CAManBase"}) exitWith { "" };
        private _forced = _v getVariable ["COMSPEC_ATAK_FsType", ""];
        if (_forced isNotEqualTo "") exitWith { _forced };
        private _cls = typeOf _v;
        private _cache = missionNamespace getVariable ["COMSPEC_ATAK_FsCls", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_FsCls", _cache];
        if (_cls in _cache) exitWith { _cache get _cls };
        private _ws = [];
        { _ws append (_v weaponsTurret _x); } forEach ([[-1]] + (allTurrets [_v, false]));
        private _k = "";
        if (_v isKindOf "Air") then {
            // Appareil armé (hors leurres, phares, désignateur) : appui aérien ; les drones se commandent depuis l'app Drone.
            private _armed = (_ws findIf { private _w = toLower _x; (["flare", "chaff", "horn", "laserdesignator", "searchlight", "smokelauncher", "cmflare"] findIf { (_w find _x) >= 0 }) < 0 }) >= 0;
            if (_armed && {!unitIsUAV _v}) then { _k = "CAS"; };
        } else {
            private _cfg = configOf _v;
            private _art = (getNumber (_cfg >> "artilleryScanner")) isEqualTo 1 || {_v isKindOf "StaticMortar"}
                || {((getArray (_cfg >> "availableForSupportTypes")) findIf { _x == "Artillery" }) >= 0};
            if (_art) then {
                private _mags = (magazinesAllTurrets _v) apply { _x select 0 };
                private _s = toLower (([_cls] + _ws + _mags) joinString " ");
                private _has = { params ["_l"]; (_l findIf { (_s find _x) >= 0 }) >= 0 };
                private _cal = 0;
                private _m = _s regexFind ["(\d{2,3}) ?mm", 0];
                if (_m isNotEqualTo []) then { _cal = parseNumber (((_m select 0) select 1) select 0); };
                if ([["m224", "60mm"]] call _has) then { _cal = 60; };
                if ([["m120", "2b11", "2s12", "sani"]] call _has && {_cal < 100}) then { _cal = 120; };
                private _sim = "";
                if (_mags isNotEqualTo []) then { _sim = toLower getText (configFile >> "CfgAmmo" >> getText (configFile >> "CfgMagazines" >> (_mags select 0) >> "ammo") >> "simulation"); };
                private _mortar = _v isKindOf "StaticMortar" || {[["mortar", "_mo_", "mo_shells", "podnos", "2b14", "m252"]] call _has};
                _k = switch (true) do {
                    case (_v isKindOf "Ship" || {[["ship", "naval"]] call _has}): { "NAVAL" };
                    case (_sim in ["shotrocket", "shotmissile"] || {[["rocket", "mlrs", "mrls", "grad", "bm21", "bm-21", "rm70", "m270", "m142", "himars", "230mm", "227mm", "katyusha"]] call _has}): { "MLRS" };
                    case (_cal > 125): { "HOWITZER" };
                    case (_mortar): { if (_cal > 0 && {_cal <= 70}) then { "MORTAR_L" } else { ["MORTAR_M", "MORTAR_H"] select (_cal > 100) } };
                    default { "HOWITZER" };
                };
            };
        };
        _cache set [_cls, _k];
        _k
    };
    case "scan": {
        if (!hasInterface) exitWith {};
        private _list = [];
        { private _k = ["classify", _x] call _fn; if (_k isNotEqualTo "") then { _list pushBack [_x, _k]; }; } forEach vehicles;
        missionNamespace setVariable ["COMSPEC_ATAK_FsGuns", _list];
        missionNamespace setVariable ["COMSPEC_ATAK_FsScanAt", diag_tickTime];
        if (isNil "COMSPEC_ATAK_FsEh") then {
            COMSPEC_ATAK_FsEh = [
                addMissionEventHandler ["EntityCreated", {
                    params ["_e"];
                    if (_e isKindOf "AllVehicles" && {!(_e isKindOf "CAManBase")} && {missionNamespace getVariable ["comspec_atak_native_fs_enabled", true]}) then {
                        // Image suivante : tourelles et chargeurs posés.
                        [{ ["add", _this] call comspec_atak_native_fnc_fireSupport; }, _e] call comspec_atak_native_fnc_nextFrame;
                    };
                }],
                addMissionEventHandler ["EntityKilled", {
                    params ["_e"];
                    private _l = missionNamespace getVariable ["COMSPEC_ATAK_FsGuns", []];
                    private _i = _l findIf { (_x select 0) isEqualTo _e };
                    if (_i >= 0) then { _l deleteAt _i; };
                }]
            ];
        };
    };
    case "add": {
        private _v = _arg;
        if (isNull _v || {!alive _v}) exitWith {};
        private _k = ["classify", _v] call _fn;
        private _l = missionNamespace getVariable ["COMSPEC_ATAK_FsGuns", []];
        if (_k isNotEqualTo "" && {(_l findIf { (_x select 0) isEqualTo _v }) < 0}) then { _l pushBack [_v, _k]; missionNamespace setVariable ["COMSPEC_ATAK_FsGuns", _l]; };
    };
    case "guns": {
        private _k = [_arg, ""] select !(_arg isEqualType "");
        // Liste relue si trop ancienne (véhicule créé ailleurs sans événement ici) : à la demande, jamais par image.
        if (isNil "COMSPEC_ATAK_FsGuns" || {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_FsScanAt", -1e9]) > 30}) then { ["scan"] call _fn; };
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_FsGuns", []];
        _list = _list select { !isNull (_x select 0) };
        missionNamespace setVariable ["COMSPEC_ATAK_FsGuns", _list];
        private _sd = side group player;
        private _out = [];
        {
            _x params ["_v", "_t"];
            if (alive _v && {[_t isNotEqualTo "CAS", _t isEqualTo _k] select (_k isNotEqualTo "")}) then {
                private _c = [_v, _t] call _crewOf;
                if (((isNull _c || {!alive _c}) && {_t isNotEqualTo "CAS"}) || {alive _c && {(side group _c) isEqualTo _sd}}) then { _out pushBack _v; };
            };
        } forEach _list;
        [_out, [], { _x distance2D player }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "mag": {
        _arg params [["_gun", objNull], ["_ammo", "HE"]];
        if (isNull _gun) exitWith { "" };
        private _mags = getArtilleryAmmo [_gun];
        if (_mags isEqualTo []) then { { _mags pushBackUnique (_x select 0); } forEach (magazinesAllTurrets _gun); };
        private _not = ["smoke", "flare", "illum", "_wp", "mine", "cluster", "guided", "laser", "_lg", "lg_", "practice", "cmflare", "chaff"];
        private _i = switch (_ammo) do {
            case "ILLUM": { _mags findIf { private _m = toLower _x; (_m find "flare") >= 0 || {(_m find "illum") >= 0} } };
            case "SMOKE": { _mags findIf { private _m = toLower _x; (_m find "smoke") >= 0 || {(_m find "_wp") >= 0} } };
            default { _mags findIf { private _m = toLower _x; (_not findIf { (_m find _x) >= 0 }) < 0 } };
        };
        if (_i < 0) then { "" } else { _mags select _i }
    };
    case "charges": {
        _arg params ["_gun", "_mag"];
        private _ws = [];
        { _ws append (_gun weaponsTurret _x); } forEach (allTurrets [_gun, false]);
        if (_ws isEqualTo []) then { _ws = weapons _gun; };
        private _wpn = _ws param [(_ws findIf { private _w = _x; ((getArray (configFile >> "CfgWeapons" >> _w >> "magazines")) findIf { _x == _mag }) >= 0 }) max 0, ""];
        private _cs = [];
        { private _c = getNumber (configFile >> "CfgWeapons" >> _wpn >> _x >> "artilleryCharge"); if (_c > 0) then { _cs pushBackUnique _c; }; } forEach (getArray (configFile >> "CfgWeapons" >> _wpn >> "modes"));
        _cs sort true;
        [_cs, [1]] select (_cs isEqualTo [])
    };
    case "range": {
        _arg params [["_gun", objNull], ["_mag", ""], ["_k", ""]];
        private _t = ["type", _k] call _fn;
        private _tab = [_t getOrDefault ["min", 0], _t getOrDefault ["max", 0]];
        if (isNull _gun || {_mag isEqualTo ""}) exitWith { _tab };
        private _key = (typeOf _gun) + "|" + _mag;
        private _c = missionNamespace getVariable ["COMSPEC_ATAK_FsRange", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_FsRange", _c];
        if (_key in _c) exitWith { _c get _key };
        // Sans frottement : portée max à 45° avec la plus forte charge, portée min en tir courbe (85°) avec la plus faible.
        private _v0 = getNumber (configFile >> "CfgMagazines" >> _mag >> "initSpeed");
        private _cs = ["charges", [_gun, _mag]] call _fn;
        private _r = if (_v0 <= 0) then { _tab } else {
            private _vMax = _v0 * (_cs select ((count _cs) - 1));
            private _vMin = _v0 * (_cs select 0);
            [round (((_vMin ^ 2) * (sin 170) / 9.81) / 10) * 10, round (((_vMax ^ 2) / 9.81) / 10) * 10]
        };
        _c set [_key, _r];
        _r
    };
    case "inRange": {
        _arg params [["_gun", objNull], ["_mag", ""], ["_p", []], ["_k", ""]];
        if (isNull _gun || {_mag isEqualTo ""} || {(count _p) < 2}) exitWith { false };
        private _p3 = [_p select 0, _p select 1, 0];
        // Calculateur d'Arma quand la pièce en a un (mortier ACE sans calculateur : portée balistique).
        if ((getNumber (configOf _gun >> "artilleryScanner")) isEqualTo 1) exitWith { _p3 inRangeOfArtillery [[_gun], _mag] };
        (["range", [_gun, _mag, _k]] call _fn) params ["_rMin", "_rMax"];
        private _d = _gun distance2D _p3;
        _d >= _rMin && {_d <= _rMax}
    };
    case "dc": {
        _arg params [["_p", []], ["_k", ""]];
        private _r = (["type", _k] call _fn) getOrDefault ["dc", 600];
        if ((count _p) < 2) exitWith { [_r, -1] };
        private _sd = side group player;
        private _n = -1;
        {
            if (alive _x && {(side group _x) isEqualTo _sd || {!(_x isKindOf "CAManBase") && {(side _x) isEqualTo _sd}}}) then {
                private _d = _x distance2D _p;
                if (_n < 0 || {_d < _n}) then { _n = _d; };
            };
        } forEach ([_p select 0, _p select 1, 0] nearEntities [["CAManBase", "LandVehicle", "Ship"], _r * 1.5]);
        [_r, round _n]
    };
    case "solve": {
        _arg params [["_gun", objNull], ["_tgt", []], ["_ammo", "HE"]];
        private _out = createHashMap;
        if (isNull _gun || {(count _tgt) < 2}) exitWith { _out };
        private _t = [_tgt select 0, _tgt select 1, 0];
        private _mag = ["mag", [_gun, _ammo]] call _fn;
        private _d = _gun distance2D _t;
        { _out set _x; } forEach [["dist", round _d], ["mils", (round ((_gun getDir _t) * 6400 / 360)) mod 6400], ["mag", _mag], ["eta", -1], ["elev", -1], ["charge", -1], ["inRange", false]];
        if (_mag isEqualTo "") exitWith { _out };
        _out set ["inRange", ["inRange", [_gun, _mag, _t, ["classify", _gun] call _fn]] call _fn];
        _out set ["eta", round (_gun getArtilleryETA [_t, _mag])];
        // Élévation en tir courbe : plus petite charge qui atteint la cible (balistique sans frottement).
        private _v0 = getNumber (configFile >> "CfgMagazines" >> _mag >> "initSpeed");
        private _vi = (getTerrainHeightASL _t) - ((getPosASL _gun) select 2);
        {
            private _v = _v0 * _x;
            private _disc = _v ^ 4 - 9.81 * (9.81 * _d ^ 2 + 2 * _vi * _v ^ 2);
            if (_v > 0 && {_disc >= 0} && {_d > 1}) exitWith {
                private _th = atan ((_v ^ 2 + sqrt _disc) / (9.81 * _d));
                _out set ["charge", _forEachIndex + 1];
                _out set ["elev", round (_th * 6400 / 360)];
                if ((_out get "eta") <= 0) then { _out set ["eta", round (_d / (_v * cos _th))]; };
            };
        } forEach (["charges", [_gun, _mag]] call _fn);
        _out
    };
    case "can": {
        private _u = [_arg, player] select !(_arg isEqualType objNull);
        if (isNull _u || {!alive _u}) exitWith { false };
        if (_u getVariable ["COMSPEC_ATAK_FsCan", false]) exitWith { true };
        if (["can", _u] call comspec_atak_native_fnc_aiOrder) exitWith { true };
        (toLower (roleDescription _u)) regexMatch ".*(jtac|observ|fac|fist|artil|mortar|mortier|appui|fires|feux|forward|fso|fo ).*"
    };
    case "state": {
        _arg params ["_id", "_pairs", "_sd"];
        ["comspec_atak_native_sideEvent", ["comspec_atak_native_fsState", [_id, _pairs, _sd], _sd]] call CBA_fnc_serverEvent;
    };
    case "pub": {
        // Client : état gardé tout de suite chez moi, puis envoyé au camp (soumis au réseau du téléphone).
        _arg params ["_id", "_pairs"];
        private _sd = str side group player;
        ["recv", [_id, _pairs, _sd]] call _fn;
        [{ ["comspec_atak_native_sideEvent", ["comspec_atak_native_fsState", _this, _this select 2]] call CBA_fnc_serverEvent; }, [_id, _pairs, _sd], "Appel de feu", 1] call comspec_atak_native_fnc_netSend;
    };
    case "request": {
        private _m = _arg;
        private _id = _m getOrDefault ["id", ""];
        if (_id isEqualTo "") then { _id = format ["FS-%1-%2", getPlayerUID player, floor (serverTime * 10)]; _m set ["id", _id]; };
        if (((_m getOrDefault ["run", ""]) select [0, 1]) in ["", "#"]) then { _m set ["run", _id + (_m getOrDefault ["run", ""])]; };
        private _sd = str side group player;
        private _k = _m getOrDefault ["type", ""];
        private _gid = _m getOrDefault ["gun", "MAN"];
        private _gun = if (_gid in ["MAN", "SIM", ""]) then { objNull } else { objectFromNetId _gid };
        private _c = if (isNull _gun) then { objNull } else { [_gun, _k] call _crewOf };
        private _crew = switch (true) do {
            case (_gid isEqualTo "SIM"): { "SIM" };
            case (isNull _c || {!alive _c}): { "NONE" };
            case (isPlayer _c): { "PLAYER" };
            default { "AI" };
        };
        if (_crew isEqualTo "PLAYER") then { _m set ["gunnerUid", getPlayerUID _c]; };
        // Tir par l'IA coupé sur ce serveur : la demande reste écrite (camp, poste TOC), comme sans servant.
        private _noAi = _crew isEqualTo "AI" && {!(missionNamespace getVariable ["comspec_atak_native_fs_ai", true])};
        if (_noAi) then { _crew = "NONE"; };
        { _m set _x; } forEach [["crew", _crew], ["uid", getPlayerUID player], ["at", serverTime], ["state", "NEW"], ["side", _sd],
            ["by", ([[player, true] call comspec_atak_native_fnc_unitCallsign, name player] select { _x isNotEqualTo "" }) param [0, name player]]];
        private _how = "ordre";
        if (_crew in ["AI", "SIM"]) then {
            if (!isServer && {(missionNamespace getVariable ["COMSPEC_ATAK_SrvFsV", 0]) < 1}) exitWith { _how = "!Le serveur n'a pas cette version du mod ATAK : tir par l'IA ou simulé impossible"; };
            if (_crew isEqualTo "AI" && {_k isEqualTo "CAS" || {_gun isKindOf "Air"}}) exitWith {
                // Appareil IA : ordre ATTAQUER la zone, par le chemin des ordres aux IA (droits, refus et retours de fn_aiOrder).
                if !(["send", [group _c, "ATTACK", _m getOrDefault ["tgt", []]]] call comspec_atak_native_fnc_aiOrder) exitWith { _how = "!"; };
                _m set ["text", "appareil IA engagé (ordre ATTAQUER la zone)"];
                ["pub", [_id, (_m call _pairsOf)]] call _fn;
                _how = "appareil IA";
            };
            _m set ["text", "envoyée au serveur"];
            ["recv", [_id, (_m call _pairsOf), _sd]] call _fn;
            [{ ["comspec_atak_native_fsSrv", _this] call CBA_fnc_serverEvent; }, [player, (_m call _pairsOf)], "Appel de feu", 1] call comspec_atak_native_fnc_netSend;
            // Sans réponse du serveur au bout de 30 s (envoi perdu, serveur muet) : le demandeur le sait.
            [{
                params ["_id"];
                private _e = (missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap]) getOrDefault [_id, createHashMap];
                if ((_e getOrDefault ["text", ""]) isEqualTo "envoyée au serveur" && {((missionNamespace getVariable ["COMSPEC_ATAK_NetQueue", []]) findIf { ((((_x param [1, []]) param [1, []]) apply { _x param [1, ""] }) find _id) >= 0 }) < 0}) then {
                    ["WARNING", "Appel de feu : pas de réponse du serveur. Réessayez ; si ça recommence, le serveur n'a sans doute pas la même version du mod ATAK", 6, 40, "FIRES"] call comspec_atak_native_fnc_notify;
                };
            }, [_id], 30] call comspec_atak_native_fnc_waitAndExec;
            _how = ["tir IA", "batterie simulée"] select (_crew isEqualTo "SIM");
        } else {
            _m set ["text", switch (true) do { case (_crew isEqualTo "PLAYER"): { "envoyée au servant" }; case (_noAi): { "tir par l'IA désactivé : demande transmise au camp" }; default { "demande transmise au camp (pas de servant)" }; }];
            ["pub", [_id, (_m call _pairsOf)]] call _fn;
            _how = ["ordre", "téléphone du servant"] select (_crew isEqualTo "PLAYER");
        };
        _how
    };
    case "srv": {
        if (!isServer) exitWith {};
        _arg params [["_u", objNull], ["_pairs", []]];
        if (!(_u isEqualType objNull) || {isNull _u} || {!alive _u} || {!isPlayer _u} || {!(_pairs isEqualType [])}) exitWith {};
        private _m = createHashMapFromArray _pairs;
        private _id = _m getOrDefault ["id", ""];
        private _sd = str side group _u;
        _m set ["side", _sd];
        private _no = { params ["_t"]; ["comspec_atak_native_fsState", [_id, [["state", "FAIL"], ["text", _t]], _sd], _u] call CBA_fnc_targetEvent; };
        if (!_on) exitWith { ["L'appui feu typé est désactivé sur ce serveur"] call _no; };
        private _k = _m getOrDefault ["type", ""];
        if (((["types"] call _fn) findIf { (_x get "key") isEqualTo _k }) < 0) exitWith { ["Ce moyen d'appui n'est pas autorisé sur ce serveur"] call _no; };
        if !(["can", _u] call _fn) exitWith { ["Seuls un observateur, un JTAC, un chef de groupe, un commandant, le poste TOC ou Zeus peuvent demander un tir à une pièce IA"] call _no; };
        private _tgt = _m getOrDefault ["tgt", []];
        if !(_tgt isEqualTypeArray [0, 0]) exitWith { ["Cible invalide"] call _no; };
        if ((_m getOrDefault ["crew", ""]) isEqualTo "SIM") exitWith {
            if !(missionNamespace getVariable ["comspec_atak_native_fs_sim", false]) exitWith { ["Les batteries simulées ne sont pas autorisées sur ce serveur"] call _no; };
            _m set ["text", "transmise à la batterie simulée"];
            private _p = (_m call _pairsOf);
            ["state", [_id, _p, _sd]] call _fn;
            ["sim", _p] call _fn;
        };
        private _gun = objectFromNetId (_m getOrDefault ["gun", ""]);
        private _c = if (isNull _gun) then { objNull } else { gunner _gun };
        if (isNull _gun || {!alive _gun}) exitWith { ["La pièce n'existe plus"] call _no; };
        if (isNull _c || {!alive _c}) exitWith { ["La pièce n'a pas de servant"] call _no; };
        if ((str side group _c) isNotEqualTo _sd) exitWith { ["Cette pièce n'est pas de votre camp"] call _no; };
        if (({isPlayer _x} count (units group _c)) > 0) exitWith { ["La pièce est servie par un joueur : la mission lui est envoyée sur son téléphone"] call _no; };
        if ((group _c) getVariable ["COMSPEC_ATAK_NoOrders", false] || {_gun getVariable ["COMSPEC_ATAK_NoOrders", false]}) exitWith { ["Cette pièce est protégée par la mission : elle ne prend pas de mission de tir"] call _no; };
        if !(missionNamespace getVariable ["comspec_atak_native_fs_ai", true]) exitWith { ["Le tir par l'IA est désactivé sur ce serveur"] call _no; };
        private _mag = ["mag", [_gun, _m getOrDefault ["ammo", "HE"]]] call _fn;
        if (_mag isEqualTo "") exitWith { ["Munition absente sur la pièce"] call _no; };
        if !(["inRange", [_gun, _mag, _tgt, _k]] call _fn) exitWith { ["Cible hors de portée de la pièce"] call _no; };
        _m set ["mag", _mag];
        _m set ["text", "transmise à la pièce"];
        private _p = (_m call _pairsOf);
        ["state", [_id, _p, _sd]] call _fn;
        ["comspec_atak_native_fsExec", _p, _c] call CBA_fnc_targetEvent;
    };
    case "exec": {
        private _m = createHashMapFromArray _arg;
        private _id = _m getOrDefault ["id", ""];
        private _sd = _m getOrDefault ["side", ""];
        private _run = _m getOrDefault ["run", _id];
        private _gun = objectFromNetId (_m getOrDefault ["gun", ""]);
        private _rep = { params ["_st", "_txt", ["_more", []]]; ["state", [_id, [["state", _st], ["text", _txt]] + _more, _sd]] call comspec_atak_native_fnc_fireSupport; };
        if (isNull _gun || {!alive _gun} || {!local (gunner _gun)}) exitWith { ["FAIL", "la pièce a changé de machine ou n'existe plus : renvoyez la demande"] call _rep; };
        // Une nouvelle demande (réglage, EFFICACE) remplace la précédente ; FIN DE MISSION (serveur) l'efface.
        _gun setVariable ["COMSPEC_ATAK_FsId", _run];
        [_m, _gun, _rep, _run, _delay] spawn {
            params ["_m", "_gun", "_rep", "_run", "_delay"];
            private _fn = comspec_atak_native_fnc_fireSupport;
            // Variables lues par _rep (le script lancé ne voit pas celles de l'appelant).
            private _id = _m getOrDefault ["id", ""];
            private _sd = _m getOrDefault ["side", ""];
            private _t = ["type", _m getOrDefault ["type", ""]] call _fn;
            private _cur = { (_gun getVariable ["COMSPEC_ATAK_FsId", ""]) isEqualTo _run };
            // Réaction : préparation complète au premier tir, plus courte pour un réglage ou EFFICACE (pièce déjà pointée).
            private _react = (_t getOrDefault ["react", 45]) * _delay * ([1, 0.4] select ((_m getOrDefault ["phase", "FIRST"]) isNotEqualTo "FIRST"));
            ["EXEC", format ["en préparation, coups dans %1 s", round _react]] call _rep;
            sleep (_react max 0.5);
            if !(call _cur) exitWith {};
            if (!alive _gun || {!alive gunner _gun}) exitWith { ["FAIL", "pièce ou servant perdu"] call _rep; };
            private _mag = ["mag", [_gun, _m getOrDefault ["ammo", "HE"]]] call _fn;
            if (_mag isEqualTo "") exitWith { ["FAIL", "plus de munitions de ce type"] call _rep; };
            private _tgt = _m getOrDefault ["tgt", [0, 0]];
            _tgt = [_tgt select 0, _tgt select 1, 0];
            private _n = (_m getOrDefault ["rounds", 1]) max 1;
            private _eta = _gun getArtilleryETA [_tgt, _mag];
            if (_eta <= 0) then { _eta = ((["solve", [_gun, _tgt, _m getOrDefault ["ammo", "HE"]]] call _fn) getOrDefault ["eta", -1]) max 15; };
            private _gap = [6, 4] select ((_m getOrDefault ["type", ""]) in ["MORTAR_L", "MORTAR_M", "MORTAR_H"]);
            if ((_m getOrDefault ["sheaf", false]) && {_n > 1} && {(_m getOrDefault ["type", ""]) isNotEqualTo "MLRS"}) then {
                // Gerbe ouverte : un point par coup dans la dispersion du moyen.
                private _disp = (_t getOrDefault ["disp", 40]) * 1.5;
                for "_i" from 1 to _n do {
                    if !(call _cur) exitWith {};
                    _gun doArtilleryFire [_tgt getPos [_disp * sqrt (random 1), random 360], _mag, 1];
                    if (_i isEqualTo 1) then { ["SHOT", format ["coups partis, impact dans %1 s", round _eta], [["eta", round _eta]]] call _rep; };
                    sleep _gap;
                };
            } else {
                _gun doArtilleryFire [_tgt, _mag, _n];
                ["SHOT", format ["coups partis, impact dans %1 s", round _eta], [["eta", round _eta]]] call _rep;
            };
            sleep ((_eta - 5) max 1);
            if !(call _cur) exitWith {};
            ["SPLASH", "splash : impact dans 5 s"] call _rep;
            sleep (5 + ((_gap * (_n - 1)) min 60));
            if !(call _cur) exitWith {};
            ["DONE", "tir fini : réglez, EFFICACE ou FIN DE MISSION"] call _rep;
        };
    };
    case "sim": {
        if (!isServer) exitWith {};
        private _m = createHashMapFromArray _arg;
        private _id = _m getOrDefault ["id", ""];
        private _run = _m getOrDefault ["run", _id];
        COMSPEC_ATAK_SrvFsRun set [_id, _run];
        [_m, _id, _run, _delay] spawn {
            params ["_m", "_id", "_run", "_delay"];
            private _fn = comspec_atak_native_fnc_fireSupport;
            private _sd = _m getOrDefault ["side", ""];
            private _rep = { params ["_st", "_txt", ["_more", []]]; ["state", [_id, [["state", _st], ["text", _txt]] + _more, _sd]] call _fn; };
            private _cur = { (COMSPEC_ATAK_SrvFsRun getOrDefault [_id, ""]) isEqualTo _run };
            private _t = ["type", _m getOrDefault ["type", ""]] call _fn;
            private _react = (_t getOrDefault ["react", 45]) * _delay * ([1, 0.4] select ((_m getOrDefault ["phase", "FIRST"]) isNotEqualTo "FIRST"));
            private _tof = ((_t getOrDefault ["tof", 30]) * (_delay max 0.35)) max 3;
            ["EXEC", format ["en préparation, coups dans %1 s", round _react]] call _rep;
            sleep (_react max 0.5);
            if !(call _cur) exitWith {};
            ["SHOT", format ["coups partis, impact dans %1 s", round _tof], [["eta", round _tof]]] call _rep;
            sleep ((_tof - 5) max 1);
            if !(call _cur) exitWith {};
            ["SPLASH", "splash : impact dans 5 s"] call _rep;
            sleep ((_tof min 5) max 1);
            private _sims = _t getOrDefault ["sim", ["Sh_82mm_AMOS", "", ""]];
            private _cls = _sims param [(["HE", "SMOKE", "ILLUM"] find (_m getOrDefault ["ammo", "HE"])) max 0, ""];
            if (_cls isEqualTo "") then { _cls = _sims select 0; };
            private _illum = (_m getOrDefault ["ammo", "HE"]) isEqualTo "ILLUM";
            private _tgt = _m getOrDefault ["tgt", [0, 0]];
            private _disp = (_t getOrDefault ["disp", 40]) * ([1, 1.5] select (_m getOrDefault ["sheaf", false]));
            private _gap = switch (_m getOrDefault ["type", ""]) do { case "MLRS": { 0.4 }; case "CAS": { 0.8 }; default { 2.5 }; };
            for "_i" from 1 to ((_m getOrDefault ["rounds", 1]) max 1) do {
                if !(call _cur) exitWith {};
                private _p = [_tgt select 0, _tgt select 1, 0] getPos [_disp * sqrt (random 1), random 360];
                private _s = createVehicle [_cls, [_p select 0, _p select 1, 0], [], 0, "CAN_COLLIDE"];
                _s setPosASL [_p select 0, _p select 1, (getTerrainHeightASL _p) + ([300, 220] select _illum)];
                _s setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
                _s setVelocity [0, 0, [-200, -1] select _illum];
                sleep _gap;
            };
            if !(call _cur) exitWith {};
            ["DONE", "tir fini : réglez, EFFICACE ou FIN DE MISSION"] call _rep;
        };
    };
    case "keep": {
        if (!isServer) exitWith {};
        _arg params [["_id", ""], ["_pairs", []], ["_sd", ""]];
        if (_id isEqualTo "" || {!(_pairs isEqualType [])}) exitWith {};
        private _all = COMSPEC_ATAK_SrvFs getOrDefault [_sd, createHashMap];
        private _e = _all getOrDefault [_id, createHashMap];
        { if (_x isEqualType [] && {(count _x) isEqualTo 2}) then { _e set [_x select 0, _x select 1]; }; } forEach _pairs;
        _all set [_id, _e];
        // Fin de mission : tir en attente arrêté (pièce IA sur sa machine, batterie simulée ici).
        if ((_e getOrDefault ["state", ""]) isEqualTo "EOM") then {
            private _g = objectFromNetId (_e getOrDefault ["gun", ""]);
            if (!isNull _g) then { _g setVariable ["COMSPEC_ATAK_FsId", "", true]; };
            COMSPEC_ATAK_SrvFsRun set [_id, ""];
        };
        // 15 dernières missions par camp, rejouées aux arrivants.
        if ((count _all) > 15) then {
            private _old = (keys _all) apply { [(_all get _x) getOrDefault ["at", 0], _x] };
            _old sort true;
            { _all deleteAt (_x select 1); COMSPEC_ATAK_SrvFsRun deleteAt (_x select 1); } forEach (_old select [0, (count _all) - 15]);
        };
        COMSPEC_ATAK_SrvFs set [_sd, _all];
    };
    case "recv": {
        _arg params [["_id", ""], ["_pairs", []], ["_sd", ""]];
        if (!hasInterface || {isNull player} || {_id isEqualTo ""} || {_sd isNotEqualTo str side group player}) exitWith {};
        private _replay = missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false];
        private _all = missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_FsMsns", _all];
        private _e = _all getOrDefault [_id, createHashMap];
        private _was = _e getOrDefault ["state", ""];
        { if (_x isEqualType [] && {(count _x) isEqualTo 2}) then { _e set [_x select 0, _x select 1]; }; } forEach _pairs;
        _e set ["id", _id];
        private _st = _e getOrDefault ["state", "NEW"];
        if (_st in ["DONE", "FAIL", "EOM"]) then { if (_was isNotEqualTo _st) then { _e set ["end", serverTime]; }; } else { _e deleteAt "end"; };
        // Libellé de la carte préparé ici (traduit une fois, pas à chaque image).
        private _t = ["type", _e getOrDefault ["type", ""]] call _fn;
        _e set ["lbl", format ["%1 · %2", (_t getOrDefault ["short", "APPUI"]) call comspec_atak_native_fnc_tr, (_stLbl getOrDefault [_st, _st]) call comspec_atak_native_fnc_tr]];
        _all set [_id, _e];
        if ((count _all) > 20) then {
            private _old = ((keys _all) select { _x isNotEqualTo _id }) apply { [(_all get _x) getOrDefault ["at", 0], _x] };
            _old sort true;
            { _all deleteAt (_x select 1); } forEach (_old select [0, (count _all) - 20]);
        };
        private _me = getPlayerUID player;
        if (!_replay && {_st isNotEqualTo _was}) then {
            // Demandeur : chaque étape de sa mission.
            if ((_e getOrDefault ["uid", ""]) isEqualTo _me && {_st isNotEqualTo "NEW"}) then {
                private _nt = switch (_st) do { case "DONE": { "SUCCESS" }; case "SPLASH"; case "FAIL": { "WARNING" }; default { "INFO" }; };
                [_nt, format ["%1 : %2", (_e getOrDefault ["gunName", "Appui"]) call comspec_atak_native_fnc_tr, (_e getOrDefault ["text", ""]) call comspec_atak_native_fnc_tr], 6, 60, "FIRES"] call comspec_atak_native_fnc_notify;
                if (_st in ["SPLASH", "FAIL"]) then { [] call comspec_atak_native_fnc_vibrate; };
            };
            // Servant joueur : la mission arrive sur son téléphone avec sa solution de tir ; l'app CAS est préparée.
            if ((_e getOrDefault ["gunnerUid", ""]) isEqualTo _me && {_st isEqualTo "NEW"} && {(_e getOrDefault ["uid", ""]) isNotEqualTo _me}) then {
                private _g = objectFromNetId (_e getOrDefault ["gun", ""]);
                private _tg = _e getOrDefault ["tgt", []];
                private _sol = ["solve", [_g, _tg, _e getOrDefault ["ammo", "HE"]]] call _fn;
                _e set ["sol", _sol];
                private _f = [] call comspec_atak_native_fnc_firesState;
                if ((count _tg) >= 2) then {
                    private _p3 = [_tg select 0, _tg select 1, 0];
                    { _f set _x; } forEach [["target", _p3], ["tgtGrid", [_p3, 10] call comspec_atak_native_fnc_gridRef], ["mode", "GRID"], ["gun", _e getOrDefault ["gun", "MAN"]],
                        ["ammo", _e getOrDefault ["ammo", "HE"]], ["rounds", str (_e getOrDefault ["rounds", 1])], ["fsType", _e getOrDefault ["type", ""]], ["fsRecv", _id], ["clearing", true]];
                    _f deleteAt "solution";
                };
                private _sTxt = format [("AZ %1 MIL · %2 M · ÉLÉV %3 · CHARGE %4") call comspec_atak_native_fnc_tr, [_sol getOrDefault ["mils", 0], 4] call CBA_fnc_formatNumber, _sol getOrDefault ["dist", 0],
                    [str (_sol getOrDefault ["elev", -1]), "—"] select ((_sol getOrDefault ["elev", -1]) < 0), [str (_sol getOrDefault ["charge", -1]), "—"] select ((_sol getOrDefault ["charge", -1]) < 0)];
                private _log = _f getOrDefault ["log", []];
                _log pushBack format ["<t color='#f2ab33'>%1</t> REÇU de %2 · %3 · %4", [dayTime, "HH:MM"] call BIS_fnc_timeToString, _e getOrDefault ["by", ""], _e getOrDefault ["grid", ""], _sTxt];
                _f set ["log", _log select [((count _log) - 50) max 0]];
                ["WARNING", format [("MISSION DE TIR de %1 : %2 x%3 sur %4 · %5") call comspec_atak_native_fnc_tr, _e getOrDefault ["by", ""], (_ammoLbl getOrDefault [_e getOrDefault ["ammo", "HE"], "HE"]) call comspec_atak_native_fnc_tr,
                    _e getOrDefault ["rounds", 1], _e getOrDefault ["grid", ""], _sTxt], 10, 80, "TASK"] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
            };
        };
        [{
            private _pg = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""];
            if (_pg in ["TASK", "FIRES"]) then { [_pg] call comspec_atak_native_fnc_pageRender; };
            // Saisie de l'app CAS reprise des champs affichés seulement après ce redessin (fn_firesSave).
            (call comspec_atak_native_fnc_firesState) set ["clearing", false];
        }] call comspec_atak_native_fnc_nextFrame;
    };
    case "crew": {
        _arg params [["_id", ""], ["_st", "ACK"]];
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap]) getOrDefault [_id, createHashMap];
        if ((count _e) isEqualTo 0) exitWith {};
        private _eta = (_e getOrDefault ["sol", createHashMap]) getOrDefault ["eta", -1];
        private _txt = switch (_st) do {
            case "ACK": { "mission reçue par le servant" };
            case "SHOT": { if (_eta > 0) then { format ["coups partis, impact dans %1 s", _eta] } else { "coups partis" } };
            default { "impossible pour le servant" };
        };
        ["pub", [_id, [["state", _st], ["text", _txt]] + ([[], [["eta", _eta]]] select (_st isEqualTo "SHOT" && {_eta > 0}))]] call _fn;
    };
    case "eom": {
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap]) getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0 || {(_e getOrDefault ["state", ""]) isEqualTo "EOM"}) exitWith {};
        ["pub", [_arg, [["state", "EOM"], ["text", "fin de mission"]]]] call _fn;
        private _f = [] call comspec_atak_native_fnc_firesState;
        if ((_f getOrDefault ["fsId", ""]) isEqualTo _arg) then { _f set ["active", false]; _f deleteAt "activeGrid"; _f deleteAt "fsId"; };
    };
    case "open": {
        // Mission rouverte dans l'app CAS (onglet APPEL DE FEU) : réglage, EFFICACE, FIN DE MISSION.
        private _e = (missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap]) getOrDefault [_arg, createHashMap];
        private _tg = _e getOrDefault ["tgt", []];
        if ((count _tg) < 2) exitWith {};
        private _p3 = [_tg select 0, _tg select 1, 0];
        private _f = [] call comspec_atak_native_fnc_firesState;
        { _f set _x; } forEach [["tab", "cff"], ["target", _p3], ["tgtGrid", [_p3, 10] call comspec_atak_native_fnc_gridRef], ["mode", "GRID"], ["gun", _e getOrDefault ["gun", "MAN"]],
            ["fsType", _e getOrDefault ["type", ""]], ["ammo", _e getOrDefault ["ammo", "HE"]], ["rounds", str (_e getOrDefault ["rounds", 1])], ["fsId", _arg],
            ["active", !((_e getOrDefault ["state", ""]) in ["EOM", "FAIL"])], ["activeGrid", _e getOrDefault ["grid", ""]], ["clearing", true]];
        _f deleteAt "solution";
        ["FIRES"] call comspec_atak_native_fnc_navigate;
        [{ (call comspec_atak_native_fnc_firesState) set ["clearing", false]; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "rows": {
        // App Tâches : missions de tir du camp (les 6 dernières), réponses du servant ou suivi du demandeur.
        private _all = missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap];
        if (!_on || {(count _all) isEqualTo 0}) exitWith { [] };
        private _list = (values _all) apply { [_x getOrDefault ["at", 0], _x] };
        _list sort false;
        private _me = getPlayerUID player;
        private _open = { !((_x select 1) getOrDefault ["state", ""] in ["EOM", "FAIL"]) } count _list;
        private _rows = [["section", "Missions de tir", format ["%1 mission(s) du camp, %2 en cours", count _list, _open]]];
        {
            private _e = _x select 1;
            private _id = _e getOrDefault ["id", ""];
            private _st = _e getOrDefault ["state", "NEW"];
            (switch (true) do {
                case (_st in ["SHOT", "SPLASH"]): { [[0.88, 0.25, 0.22, 1], "#e04038"] };
                case (_st in ["ACK", "EXEC"]): { [[0.95, 0.67, 0.20, 1], "#f2ab33"] };
                case (_st isEqualTo "DONE"): { [[0.36, 0.78, 0.42, 1], "#5cc76b"] };
                case (_st in ["FAIL", "EOM"]): { [[0.58, 0.64, 0.60, 1], "#94a399"] };
                default { [[0.50, 0.75, 1, 1], "#80bfff"] };
            }) params ["_rgb", "_hex"];
            private _sol = _e getOrDefault ["sol", createHashMap];
            private _solTxt = if ((count _sol) > 0) then {
                format ["<br/><t size='0.85' color='#d8f29e'>%1</t>", format [("AZ %1 MIL · %2 M · ÉLÉV %3 · CHARGE %4") call comspec_atak_native_fnc_tr, [_sol getOrDefault ["mils", 0], 4] call CBA_fnc_formatNumber, _sol getOrDefault ["dist", 0],
                    [str (_sol getOrDefault ["elev", -1]), "—"] select ((_sol getOrDefault ["elev", -1]) < 0), [str (_sol getOrDefault ["charge", -1]), "—"] select ((_sol getOrDefault ["charge", -1]) < 0)]]
            } else { "" };
            private _t = ["type", _e getOrDefault ["type", ""]] call _fn;
            private _txt = format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='%2' size='0.85'>%3</t>%4<br/><t size='0.8' color='#8a9a93'>%5 · de %6 · cible %7 · %8 x%9</t><br/><t size='0.85'>%10</t>%11",
                [(_e getOrDefault ["gunName", "Appui"]) call comspec_atak_native_fnc_tr] call _esc, _hex, toUpper ((_stLbl getOrDefault [_st, _st]) call comspec_atak_native_fnc_tr),
                ["", "  <t color='#e04038' size='0.85'>DANGER CLOSE</t>"] select (_e getOrDefault ["dcClose", false]),
                (_t getOrDefault ["label", "Appui générique"]) call comspec_atak_native_fnc_tr, [_e getOrDefault ["by", ""]] call _esc, _e getOrDefault ["grid", ""],
                (_ammoLbl getOrDefault [_e getOrDefault ["ammo", "HE"], "HE"]) call comspec_atak_native_fnc_tr, _e getOrDefault ["rounds", 1], [(_e getOrDefault ["text", ""]) call comspec_atak_native_fnc_tr] call _esc, _solTxt];
            private _live = !(_st in ["EOM", "FAIL"]);
            private _btns = switch (true) do {
                case ((_e getOrDefault ["gunnerUid", ""]) isEqualTo _me): {
                    [["REÇU", compile format ["['crew', [%1, 'ACK']] call comspec_atak_native_fnc_fireSupport;", str _id], _st isEqualTo "ACK", _live],
                     ["COUPS PARTIS", compile format ["['crew', [%1, 'SHOT']] call comspec_atak_native_fnc_fireSupport;", str _id], _st isEqualTo "SHOT", _live],
                     ["IMPOSSIBLE", compile format ["['crew', [%1, 'FAIL']] call comspec_atak_native_fnc_fireSupport;", str _id], false, _live]]
                };
                case ((_e getOrDefault ["uid", ""]) isEqualTo _me): {
                    [["RÉGLER", compile format ["['open', %1] call comspec_atak_native_fnc_fireSupport;", str _id], false, _live, "Ouvre la mission dans l'app CAS : + / – / G / D, EFFICACE"],
                     ["FIN DE MISSION", compile format ["['eom', %1] call comspec_atak_native_fnc_fireSupport;", str _id], false, _live]]
                };
                default {
                    [["CARTE", compile format ["private _p = ((missionNamespace getVariable ['COMSPEC_ATAK_FsMsns', createHashMap]) getOrDefault [%1, createHashMap]) getOrDefault ['tgt', []]; if ((count _p) >= 2) then { ['MAP'] call comspec_atak_native_fnc_navigate; [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, _p] call comspec_atak_native_fnc_nextFrame; };", str _id], false, true]]
                };
            };
            _rows pushBack ["person", "\A3\ui_f\data\map\markers\military\destroy_CA.paa", _txt, _btns, _rgb];
        } forEach (_list select [0, 6]);
        _rows
    };
    case "tocText": {
        // Poste TOC, onglet ORDRES : missions de tir du camp (les 5 dernières).
        private _all = missionNamespace getVariable ["COMSPEC_ATAK_FsMsns", createHashMap];
        if (!_on || {(count _all) isEqualTo 0}) exitWith { "" };
        private _list = (values _all) apply { [_x getOrDefault ["at", 0], _x] };
        _list sort false;
        private _s = format ["<br/><t size='1.2' font='COMSPEC_BarlowBold'>%2</t> <t size='1' color='#8a9a93'>%1</t><br/>", count _list, ("MISSIONS DE TIR") call comspec_atak_native_fnc_tr];
        private _from = ("de") call comspec_atak_native_fnc_tr;
        {
            private _e = _x select 1;
            private _st = _e getOrDefault ["state", "NEW"];
            _s = _s + format ["<t size='1'><t color='%1'>%2</t> %3 → %4 · %5 x%6 · %10 %7%8<br/><t color='#8a9a93'>   %9</t></t><br/>",
                switch (true) do { case (_st in ["SHOT", "SPLASH"]): { "#e05a4a" }; case (_st in ["ACK", "EXEC"]): { "#e0b040" }; case (_st in ["EOM", "FAIL"]): { "#8a9a93" }; default { "#7fd0b0" }; },
                toUpper ((_stLbl getOrDefault [_st, _st]) call comspec_atak_native_fnc_tr), [(_e getOrDefault ["gunName", ""]) call comspec_atak_native_fnc_tr] call _esc, _e getOrDefault ["grid", ""],
                (_ammoLbl getOrDefault [_e getOrDefault ["ammo", "HE"], "HE"]) call comspec_atak_native_fnc_tr, _e getOrDefault ["rounds", 1], [_e getOrDefault ["by", ""]] call _esc,
                ["", " · <t color='#e05a4a'>DANGER CLOSE</t>"] select (_e getOrDefault ["dcClose", false]), [(_e getOrDefault ["text", ""]) call comspec_atak_native_fnc_tr] call _esc, _from];
        } forEach (_list select [0, 5]);
        _s
    };
    default { false };
}
