/*
    UCAV (drone armé : MQ-9 / Greyhawk, Fenghuang, K40 Ababil, Sentinel, drones armés des mods) commandé depuis le
    téléphone, sans appairage à ses pieds, sans prendre la tourelle du servant ni tirer à la main : on le met en orbite,
    on marque des cibles, il les frappe avec ses propres armes guidées ; mode « Rechercher et tuer » sur une zone.
    Exécution là où l'UCAV est local : fn_ucavCmd. Page : fn_pageUcav (vue UCAV de l'app Drone).
    Parcours d'un ordre : téléphone ("send") → serveur ("srv" : réglages, droits, camp, contrôleur, cible amie) →
    machine de l'UCAV (événement comspec_atak_native_ucavExec) → états publiés sur l'UCAV (variables publiques, lues par
    la carte des alliés et le poste TOC) et retours au contrôleur (événement comspec_atak_native_ucavEvt, "evt").
    Toute machine :
      ["is", véhicule]          -> UCAV reconnu : drone (unitIsUAV) avion ou hélicoptère, au moins une munition guidée
                                   air-sol (laserLock, irLock, manualControl, autoSeekTarget, capteurs ; jamais un
                                   missile air-air seul) ; la mission peut forcer : _v setVariable ["COMSPEC_ATAK_Ucav", true|false, true]
      ["weapons", véhicule]     -> [[arme, tourelle, coups, libellé, "MISSILE"|"BOMB", guidage, portée min, portée max,
                                   munition, chargeur, rayon de l'explosion]...]
      ["sideOf", véhicule]      -> camp de l'UCAV (équipage, sinon config)
      ["can", unité]            -> "" si l'unité peut commander un UCAV (réglages serveur), sinon le motif
      ["friend", [camp, position ASL, rayon, civils comptés]] -> distance de l'allié le plus proche (-1 : aucun)
      ["dc"]                    -> distance de sécurité (danger close) de l'appui aérien de fn_fireSupport
    Client :
      ["list"]                  -> UCAV de mon camp, du plus proche au plus loin
      ["link", ucav]            -> [en liaison, barres 0-4, distance, motif]
      ["view", netId | ""]      ouvre la vue UCAV de l'app Drone ; ["back"] revient aux petits drones
      ["take", netId]           prendre la main ; ["release"] la rendre (deux appuis)
      ["alt", m] / ["rad", m] / ["wpn", arme] / ["tab", onglet]   réglages de la page
      ["orbit"] / ["hunt"]      orbite / Rechercher et tuer sur la grille saisie (vide : sur moi)
      ["roe", "PROPOSE" | "FREE"]   règle d'engagement du mode Rechercher et tuer
      ["pickMap", "ORBIT" | "HUNT"] / ["picked", position]   point choisi sur la carte (même outil que l'app Drone)
      ["mapZone", [genre, point A, point B]]   zone tracée avec l'outil DRONE de la carte (UCAV_ORBIT, UCAV_HUNT)
      ["strike", source]        frappe, deux appuis : "PEND" (cible venue de la carte), "DET:clé" (détection),
                                "LOCK:clé" (cible marquée), "LASER" (laser ami), "GRID" (grille saisie)
      ["cancel", id | "ALL"] / ["ignore", clé] / ["look", source] / ["resume"] / ["pendClear"]
      ["bda"]                   ouvre le compte rendu BDA pré-rempli de la dernière frappe (app Comptes rendus)
      ["rows", [menu, carte]] / ["ctx", [clé, position, cible]]   lignes et actions du clic droit de la carte (fn_mapCtxMenu)
      ["draw", carte]           UCAV commandés de mon camp sur la carte (fn_droneDraw, PC du poste TOC)
      ["evt", [ucav, genre, texte, position, extra]]   retour reçu ; ["tick"] surveillance chaque seconde
    Serveur : ["srv", [joueur, ucav, ordre, arguments]].
    État client : missionNamespace COMSPEC_ATAK_Ucav (UCAV commandé), COMSPEC_ATAK_UcavLog, COMSPEC_ATAK_UcavBda ;
    page : clés droneUcav, ucavTab, ucavAlt, ucavRad, ucavWpn, ucavCf, ucavPend, ucavGridVal, ucavSGridVal de l'état.
*/
params [["_act", ""], ["_arg", []]];
private _fn = comspec_atak_native_fnc_ucav;
// Carte, à chaque image : liste des UCAV gardée 2 s.
if (_act isEqualTo "draw") exitWith {
    private _map = _arg;
    if (isNull _map || {!hasInterface}) exitWith {};
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_UcavDrawC", [-1, []]];
    if (diag_tickTime > (_c select 0)) then {
        private _sd = str side group player;
        _c = [diag_tickTime + 2, allUnitsUAV select { alive _x && {((_x getVariable ["COMSPEC_UcavCtl", []]) param [2, ""]) isEqualTo _sd} }];
        uiNamespace setVariable ["COMSPEC_ATAK_UcavDrawC", _c];
    };
    private _font = (missionNamespace getVariable ["COMSPEC_ATAK_FontSet", ["RobotoCondensed", "RobotoCondensedBold"]]) select 1;
    {
        private _u = _x;
        if (!alive _u) then { continue };
        private _mode = _u getVariable ["COMSPEC_UcavMode", ""];
        (_u getVariable ["COMSPEC_UcavSt", []]) params [["_sk", ""], ["_stx", ""], ["_aim", []]];
        private _col = [[0.30, 0.85, 0.95, 1], [1, 0.62, 0.2, 1]] select (_mode isEqualTo "HUNT");
        (_u getVariable ["COMSPEC_UcavOrbit", []]) params [["_oc", []], ["_or", 0]];
        if ((count _oc) >= 2 && {_or > 0} && {_mode isEqualTo "ORBIT"}) then { _map drawEllipse [_oc, _or, _or, 0, _col, ""]; };
        private _z = _u getVariable ["COMSPEC_UcavZone", []];
        if ((count _z) >= 5 && {_mode isEqualTo "HUNT"}) then {
            if (_z select 4) then { _map drawRectangle [_z select 0, _z select 1, _z select 2, _z select 3, _col, ""]; } else { _map drawEllipse [_z select 0, _z select 1, _z select 2, _z select 3, _col, ""]; };
        };
        private _p = getPosASLVisual _u;
        // Frappe en cours : trait vers le point visé ; cibles en file : croix.
        if ((count _aim) >= 2 && {_sk in ["ENROUTE", "INPOS", "LOCK", "FIRE", "IMPACT"]}) then {
            _map drawLine [_p, _aim, [0.95, 0.3, 0.25, 1]];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\destroy_CA.paa", [0.95, 0.3, 0.25, 1], _aim, 22, 22, 0, "", 0];
        };
        {
            private _qa = _x param [2, []];
            if ((count _qa) >= 2) then { _map drawIcon ["\A3\ui_f\data\map\markers\military\objective_CA.paa", [0.95, 0.45, 0.3, 0.85], _qa, 16, 16, 0, format [" %1", _forEachIndex + 1], 1, 0.026, _font, "right"]; };
        } forEach (_u getVariable ["COMSPEC_UcavQ", []]);
        private _nm = _u getVariable ["COMSPEC_DroneName", ""];
        if (_nm isEqualTo "") then { _nm = getText (configOf _u >> "displayName"); };
        _map drawIcon ["\A3\ui_f\data\map\vehicleicons\iconPlane_ca.paa", _col, _p, 24, 24, getDir _u,
            format [" UCAV %1 · %2%3", _nm, ((createHashMapFromArray [["ORBIT", "orbite"], ["HUNT", "rechercher et tuer"], ["MANUAL", "terminal UAV"]]) getOrDefault [_mode, ""]) call comspec_atak_native_fnc_tr,
                ["", " · " + (_stx call comspec_atak_native_fnc_tr)] select (_stx isNotEqualTo "")], 1, 0.028, _font, "right"];
    } forEach (_c select 1);
};
// Munition guidée air-sol : "" (non), "MISSILE" ou "BOMB" ; gardé par munition.
private _guided = {
    params ["_ammo"];
    private _cache = missionNamespace getVariable ["COMSPEC_ATAK_UcavAmmoK", 0];
    if !(_cache isEqualType createHashMap) then { _cache = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_UcavAmmoK", _cache]; };
    private _k = _cache getOrDefault [toLower _ammo, "?"];
    if (_k isNotEqualTo "?") exitWith { _k };
    private _c = configFile >> "CfgAmmo" >> _ammo;
    _k = call {
        if (!isClass _c) exitWith { "" };
        private _sim = toLower getText (_c >> "simulation");
        if !(_sim in ["shotmissile", "shotrocket", "shotsubmunitions"]) exitWith { "" };
        private _laser = (getNumber (_c >> "laserLock")) > 0;
        private _ir = (getNumber (_c >> "irLock")) > 0;
        private _man = (getNumber (_c >> "manualControl")) > 0;
        private _seek = (getNumber (_c >> "autoSeekTarget")) > 0;
        private _sens = isClass (_c >> "Components" >> "SensorsManagerComponent");
        private _air = getNumber (_c >> "airLock");
        // Missile air-air (airLock 2, ou airLock sans autre guidage) : inutile contre une cible au sol.
        if (_air >= 2 || {_air > 0 && {!_laser} && {!_ir} && {!_man}}) exitWith { "" };
        if !(_laser || {_ir} || {_man} || {_seek} || {_sens}) exitWith { "" };
        private _lo = toLower _ammo;
        private _bomb = ((getNumber (_c >> "thrust")) <= 0 && {(getNumber (_c >> "thrustTime")) <= 0})
            || {(_lo find "bomb") >= 0} || {(_lo find "bo_") isEqualTo 0} || {"BombCore" in ([_c, true] call BIS_fnc_returnParents)};
        ["MISSILE", "BOMB"] select _bomb
    };
    _cache set [toLower _ammo, _k];
    _k
};
private _sideOf = {
    params ["_v"];
    private _dr = driver _v;
    if (!isNull _dr && {alive _dr}) exitWith { side group _dr };
    [east, west, independent, civilian] select (((getNumber (configOf _v >> "side")) max 0) min 3)
};
private _nameOf = {
    params ["_v"];
    private _n = _v getVariable ["COMSPEC_DroneName", ""];
    if (_n isEqualTo "") then { _n = getText (configOf _v >> "displayName"); };
    _n
};
// Toute machine et serveur : la valeur du switch est rendue telle quelle (exitWith).
if (_act in ["is", "sideOf", "weapons", "can", "dc", "friend", "list", "link", "srv"]) exitWith { switch (_act) do {
    case "is": {
        private _v = _arg;
        if (isNull _v || {!alive _v} || {!unitIsUAV _v}) exitWith { false };
        if !(isNil { _v getVariable "COMSPEC_ATAK_Ucav" }) exitWith { (_v getVariable "COMSPEC_ATAK_Ucav") isEqualTo true };
        if (!(_v isKindOf "Plane") && {!(_v isKindOf "Helicopter")}) exitWith { false };
        if (_v isKindOf "Mavic_drone_base_F") exitWith { false };
        // Reconnu une fois pour toutes (pylônes vides compris : l'UCAV à court de munitions reste listé).
        private _known = _v getVariable ["COMSPEC_UcavIs", -1];
        if (_known isEqualType true) exitWith { _known };
        private _r = ((magazinesAllTurrets _v) findIf { ([getText (configFile >> "CfgMagazines" >> (_x select 0) >> "ammo")] call _guided) isNotEqualTo "" }) >= 0;
        _v setVariable ["COMSPEC_UcavIs", _r];
        _r
    };
    case "sideOf": { [_arg] call _sideOf };
    case "weapons": {
        private _v = _arg;
        if (isNull _v) exitWith { [] };
        private _byW = createHashMap;
        {
            _x params ["_mag", "_tur", ["_cnt", 0]];
            private _ammo = getText (configFile >> "CfgMagazines" >> _mag >> "ammo");
            private _k = [_ammo] call _guided;
            if (_k isEqualTo "") then { continue };
            private _tw = _v weaponsTurret _tur;
            private _w = getText (configFile >> "CfgMagazines" >> _mag >> "pylonWeapon");
            if (_w isEqualTo "" || {(_tw findIf { _x == _w }) < 0}) then {
                private _ml = toLower _mag;
                private _wi = _tw findIf { (((compatibleMagazines _x) apply { toLower _x }) find _ml) >= 0 };
                _w = if (_wi < 0) then { "" } else { _tw select _wi };
            };
            if (_w isEqualTo "") then { continue };
            private _key = toLower (_w + str _tur);
            private _e = _byW getOrDefault [_key, []];
            if (_e isEqualTo []) then {
                private _cw = configFile >> "CfgWeapons" >> _w;
                private _ca = configFile >> "CfgAmmo" >> _ammo;
                private _lbl = getText (_cw >> "displayName");
                if (_lbl isEqualTo "") then { _lbl = getText (configFile >> "CfgMagazines" >> _mag >> "displayNameShort"); };
                if (_lbl isEqualTo "") then { _lbl = getText (configFile >> "CfgMagazines" >> _mag >> "displayName"); };
                if (_lbl isEqualTo "") then { _lbl = _w; };
                private _gl = [];
                if ((getNumber (_ca >> "laserLock")) > 0) then { _gl pushBack "laser"; };
                if ((getNumber (_ca >> "irLock")) > 0) then { _gl pushBack "IR"; };
                if ((getNumber (_ca >> "manualControl")) > 0) then { _gl pushBack "guidé"; };
                if ((count _gl) isEqualTo 0) then { _gl pushBack "capteurs"; };
                private _maxR = getNumber (_ca >> "missileLockMaxDistance");
                if (_maxR <= 0) then { _maxR = 5000; };
                _maxR = [(_maxR max 2500) min 8000, 0] select (_k isEqualTo "BOMB");
                private _minR = (getNumber (_ca >> "missileLockMinDistance")) max 300;
                _e = [_w, _tur, 0, _lbl, _k, _gl joinString "/", _minR, _maxR, _ammo, _mag, getNumber (_ca >> "indirectHitRange")];
                _byW set [_key, _e];
            };
            _e set [2, (_e select 2) + _cnt];
        } forEach (magazinesAllTurrets _v);
        // Missiles d'abord, puis bombes ; par libellé.
        private _vals = values _byW;
        private _ord = [];
        { _ord pushBack [_x select 4, _x select 3, _forEachIndex]; } forEach _vals;
        _ord sort false;
        _ord apply { _vals select (_x select 2) }
    };
    case "can": {
        private _u = _arg;
        if !(missionNamespace getVariable ["comspec_atak_native_ucav", true]) exitWith { "Les frappes d'UCAV sont désactivées sur ce serveur" };
        if (!(_u isEqualType objNull) || {isNull _u} || {!alive _u}) exitWith { "Aucun joueur" };
        if ((_u getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "" || {!isNull (getAssignedCuratorLogic _u)}) exitWith { "" };
        private _who = missionNamespace getVariable ["comspec_atak_native_ucav_who", 0];
        if (_who isEqualTo 2) exitWith { "Seuls le poste TOC et Zeus commandent un UCAV sur ce serveur" };
        private _r = toLower (roleDescription _u);
        private _role = _who isEqualTo 1 || {(leader group _u) isEqualTo _u} || {_u getUnitTrait "UAVHacker"}
            || {(["chef", "command", "leader", "jtac", "fac", "tacp", "uav", "drone", "opérateur", "operator"] findIf { (_r find _x) >= 0 }) >= 0};
        if (!_role) exitWith { "Seuls un chef de groupe, un JTAC, un opérateur de drone, le poste TOC ou Zeus commandent un UCAV" };
        if ((missionNamespace getVariable ["comspec_atak_native_ucav_terminal", false]) && {(((assignedItems _u) + (items _u)) findIf { _x isKindOf ["UavTerminal_base", configFile >> "CfgWeapons"] }) < 0}) exitWith {
            "Il vous faut un terminal UAV dans l'inventaire pour commander un UCAV"
        };
        ""
    };
    case "dc": { ((["type", "CAS"] call comspec_atak_native_fnc_fireSupport) getOrDefault ["dc", 1000]) max 100 };
    case "friend": {
        _arg params ["_side", "_p", "_r", ["_civ", false]];
        private _n = -1;
        if ((count _p) < 2) exitWith { -1 };
        {
            private _cr = if (_x isKindOf "CAManBase") then { [_x] } else { (crew _x) select { alive _x } };
            if (alive _x && {(count _cr) > 0}) then {
                private _sd = side group (_cr select 0);
                private _f = if (_civ) then { !([_side, _sd] call BIS_fnc_sideIsEnemy) } else { _sd isNotEqualTo civilian && {[_side, _sd] call BIS_fnc_sideIsFriendly} };
                if (_f) then {
                    private _d = _x distance2D _p;
                    if (_n < 0 || {_d < _n}) then { _n = _d; };
                };
            };
        } forEach ((ASLToAGL [_p select 0, _p select 1, (_p param [2, 0])]) nearEntities [["CAManBase", "LandVehicle", "Ship"], _r]);
        round _n
    };
    case "list": {
        private _sd = str side group player;
        private _l = allUnitsUAV select { alive _x && {["is", _x] call _fn} && {(str ([_x] call _sideOf)) isEqualTo _sd} };
        [_l, [], { player distance _x }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "link": {
        private _v = _arg;
        if (isNull _v || {!alive _v}) exitWith { [false, 0, 0, "UCAV détruit"] };
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { [false, 0, 0, "pas de téléphone sur vous"] };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0) exitWith { [false, 0, 0, "batterie du téléphone vide"] };
        private _dist = round (player distance _v);
        // Poste TOC (réseau filaire) et Zeus : toujours en liaison.
        if ((player getVariable ["COMSPEC_ATAK_TocOn", ""]) isNotEqualTo "" || {!isNull (getAssignedCuratorLogic player)}) exitWith { [true, 4, _dist, ""] };
        private _rg = missionNamespace getVariable ["comspec_atak_native_ucav_range", 15000];
        if !(_rg isEqualType 0) then { _rg = 15000; };
        if (terrainIntersectASL [eyePos player, getPosASL _v]) then { _rg = _rg * 0.5; };
        private _q = [0, ((1 - (_dist / (_rg max 1)) ^ 2) max 0) min 1] select (_dist < _rg);
        // Relais de la mission (fn_videoLink) : le meilleur chemin l'emporte, comme pour les petits drones.
        private _vl = ["path", ["UCAV" + netId _v, getPosASL _v, _q, 20]] call comspec_atak_native_fnc_videoLink;
        if (_vl getOrDefault ["active", false]) then { _q = [0, _vl getOrDefault ["q", 0]] select (_vl getOrDefault ["ok", false]); };
        if (_q <= 0) exitWith { [false, 0, _dist, format ["UCAV à %1 km, hors de portée de la liaison (%2 km)", (_dist / 1000) toFixed 1, (_rg / 1000) toFixed 1]] };
        [true, ((ceil (4 * _q)) max 1) min 4, _dist, ""]
    };
    case "srv": {
        if (!isServer) exitWith {};
        _arg params [["_p", objNull], ["_u", objNull], ["_cmd", ""], ["_args", []]];
        if (!(_p isEqualType objNull) || {isNull _p} || {!alive _p} || {!isPlayer _p}) exitWith {};
        private _no = {
            params ["_t"];
            ["comspec_atak_native_ucavEvt", [_u, "REFUSED", _t, [], [_cmd]], _p] call CBA_fnc_targetEvent;
        };
        if !(_cmd in ["take", "release", "orbit", "hunt", "roe", "strike", "cancel", "ignore", "look", "resume"]) exitWith { ["Ordre inconnu du serveur : versions du mod différentes"] call _no; };
        if !(missionNamespace getVariable ["comspec_atak_native_ucav", true]) exitWith { ["Les frappes d'UCAV sont désactivées sur ce serveur"] call _no; };
        if (!(_u isEqualType objNull) || {isNull _u} || {!alive _u}) exitWith { ["Cet UCAV n'existe plus"] call _no; };
        if !(["is", _u] call _fn) exitWith { ["Ce drone n'est pas un UCAV armé"] call _no; };
        private _zeus = !isNull (getAssignedCuratorLogic _p);
        private _side = [_u] call _sideOf;
        if (!_zeus && {(str _side) isNotEqualTo (str side group _p)}) exitWith { ["Cet UCAV n'est pas de votre camp"] call _no; };
        private _why = ["can", _p] call _fn;
        if (_why isNotEqualTo "") exitWith { [_why] call _no; };
        private _ctl = _u getVariable ["COMSPEC_UcavCtl", []];
        private _cuid = _ctl param [0, ""];
        private _uid = getPlayerUID _p;
        private _mine = _cuid isEqualTo _uid;
        private _online = _cuid isNotEqualTo "" && {(allPlayers findIf { (getPlayerUID _x) isEqualTo _cuid && {alive _x} }) >= 0};
        if (_cmd isEqualTo "take") exitWith {
            if (_online && {!_mine} && {!_zeus}) exitWith { [format ["%1 commande déjà cet UCAV", _ctl param [1, "?"]]] call _no; };
            private _op = (UAVControl _u) param [0, objNull];
            if (_op isEqualType objNull && {!isNull _op} && {alive _op} && {_op isNotEqualTo _p}) exitWith { ["Un opérateur pilote cet UCAV au terminal UAV : demandez-lui de le lâcher"] call _no; };
            if (_online && {!_mine}) then {
                private _old = allPlayers select { (getPlayerUID _x) isEqualTo _cuid };
                if ((count _old) > 0) then { ["comspec_atak_native_ucavEvt", [_u, "LOST", format ["%1 a pris la main sur l'UCAV", name _p], [], []], _old select 0] call CBA_fnc_targetEvent; };
            };
            private _cs = [[_p, true] call comspec_atak_native_fnc_unitCallsign, name _p] select { _x isNotEqualTo "" };
            _u setVariable ["COMSPEC_UcavCtl", [_uid, (_cs param [0, name _p]) regexReplace ["[<>&]", ""], str side group _p], true];
            // Verrou du terminal UAV (XEH_postInitClient) et hôte du flux vidéo (fn_feedLink) : le téléphone du contrôleur.
            _u setVariable ["COMSPEC_DroneOwner", _uid, true];
            _u setVariable ["COMSPEC_DroneLock", "", true];
            ["comspec_atak_native_ucavExec", [_u, "take", _args, _p], _u] call CBA_fnc_targetEvent;
        };
        if (!_mine && {!_zeus}) exitWith { ["Vous ne commandez pas cet UCAV : prenez-en la main d'abord"] call _no; };
        // Un ordre du téléphone reprend la main sur le terminal UAV (même règle que les petits drones).
        if (_cmd isNotEqualTo "release" && {(_u getVariable ["COMSPEC_DroneLock", ""]) isNotEqualTo ""}) then { _u setVariable ["COMSPEC_DroneLock", "", true]; };
        if (_cmd isEqualTo "roe" && {(_args param [0, ""]) isEqualTo "FREE"} && {!(missionNamespace getVariable ["comspec_atak_native_ucav_free", true])}) exitWith {
            ["L'engagement libre est interdit sur ce serveur : les cibles restent proposées"] call _no;
        };
        if (_cmd isEqualTo "hunt" && {(_args param [4, ""]) isEqualTo "FREE"} && {!(missionNamespace getVariable ["comspec_atak_native_ucav_free", true])}) then { _args set [4, "PROPOSE"]; };
        if (_cmd isEqualTo "strike") exitWith {
            _args params [["_t", objNull], ["_aim", []]];
            if !(_t isEqualType objNull) then { _t = objNull; };
            if (!isNull _t) then { _aim = getPosASL _t; };
            if !(_aim isEqualTypeArray [0, 0, 0]) exitWith { ["Point visé illisible"] call _no; };
            // Pas de tir sur un allié : cible amie (équipage vivant de mon camp ou d'un camp ami), ou allié tout près du point.
            if (!isNull _t && {alive _t}) then {
                private _cr = if (_t isKindOf "CAManBase") then { [_t] } else { (crew _t) select { alive _x } };
                if ((count _cr) > 0 && {[_side, side group (_cr select 0)] call BIS_fnc_sideIsFriendly}) then { _t = objNull; _aim = []; };
            };
            if (_aim isEqualTo []) exitWith { ["Cible amie ou civile : frappe refusée"] call _no; };
            private _fr = ["friend", [_side, _aim, 40, false]] call _fn;
            if (_fr >= 0) exitWith { [format ["Allié à %1 m du point visé : frappe refusée", _fr]] call _no; };
            _args set [0, _t];
            _args set [1, _aim];
            ["comspec_atak_native_ucavExec", [_u, "strike", _args, _p], _u] call CBA_fnc_targetEvent;
        };
        if (_cmd isEqualTo "release") then {
            _u setVariable ["COMSPEC_UcavCtl", nil, true];
            _u setVariable ["COMSPEC_DroneOwner", "", true];
            _u setVariable ["COMSPEC_DroneLock", nil, true];
        };
        ["comspec_atak_native_ucavExec", [_u, _cmd, _args, _p], _u] call CBA_fnc_targetEvent;
    };
} };
if (!hasInterface) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _u = missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull];
private _say = { params ["_lvl", "_txt", ["_dur", 4]]; [_lvl, _txt, _dur, 40, "DRONE"] call comspec_atak_native_fnc_notify; };
private _log = {
    params ["_t", ["_c", ""]];
    private _lg = missionNamespace getVariable ["COMSPEC_ATAK_UcavLog", []];
    _lg pushBack [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _t, _c];
    while { (count _lg) > 80 } do { _lg deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_UcavLog", _lg];
};
// Champs de saisie gardés avant chaque nouveau rendu.
private _saveFields = {
    { _x params ["_k", "_sk"]; private _v = [_k, "§"] call comspec_atak_native_fnc_formValue; if (_v isNotEqualTo "§") then { _s set [_sk, _v]; }; } forEach [["ucavGrid", "ucavGridVal"], ["ucavSGrid", "ucavSGridVal"]];
};
private _render = {
    call _saveFields;
    [{ private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]; if ((_st getOrDefault ["activePage", ""]) isEqualTo "DRONE" && {_st getOrDefault ["droneUcav", false]}) then { ["DRONE"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
};
// Grille saisie (6, 8 ou 10 chiffres) → centre de la case, position ASL ; [] si invalide.
private _gridPos = {
    params ["_txt"];
    private _g = _txt regexReplace ["[^0-9]", ""];
    if ((count _g) < 6 || {((count _g) mod 2) isEqualTo 1}) exitWith { [] };
    ([_g] call BIS_fnc_gridToPos) params [["_p", []], ["_sz", [0, 0]]];
    if ((count _p) < 2) exitWith { [] };
    private _c = [(_p select 0) + (_sz select 0) / 2, (_p select 1) + (_sz select 1) / 2];
    [_c select 0, _c select 1, getTerrainHeightASL _c]
};
// Envoi au serveur, après la liaison ; renvoie true si l'ordre part.
private _send = {
    params ["_cmd", ["_args", []], ["_v", _u]];
    if (isNull _v || {!alive _v}) exitWith { ["WARNING", "Aucun UCAV commandé"] call _say; false };
    if (!isServer && {(missionNamespace getVariable ["COMSPEC_ATAK_SrvUcavV", 0]) < 1}) exitWith { ["WARNING", "Le serveur n'a pas cette version du mod ATAK : UCAV impossible à commander"] call _say; false };
    private _lk = ["link", _v] call _fn;
    if !(_lk select 0) exitWith { ["WARNING", format ["UCAV hors liaison : %1", _lk select 3], 5] call _say; false };
    ["comspec_atak_native_ucavSrv", [player, _v, _cmd, _args]] call CBA_fnc_serverEvent;
    true
};
// Deux appuis dans les 5 s : true au second.
private _confirm = {
    params ["_k", ["_msg", "Appuyez encore pour confirmer"]];
    private _c = _s getOrDefault ["ucavCf", ["", 0]];
    if ((_c select 0) isNotEqualTo _k || {diag_tickTime - (_c select 1) > 5}) exitWith {
        _s set ["ucavCf", [_k, diag_tickTime]];
        ["WARNING", _msg] call _say;
        call _render;
        false
    };
    _s set ["ucavCf", ["", 0]];
    true
};
private _stopPfh = {
    private _h = missionNamespace getVariable ["COMSPEC_ATAK_UcavPfh", -1];
    if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
    missionNamespace setVariable ["COMSPEC_ATAK_UcavPfh", -1];
};
private _drop = {
    params ["_why"];
    call _stopPfh;
    missionNamespace setVariable ["COMSPEC_ATAK_Ucav", objNull];
    if (_why isNotEqualTo "") then { ["WARNING", _why, 6] call _say; [_why, "#e5483a"] call _log; };
    call _render;
};
// Cible d'une source de frappe : [objet, position ASL, libellé] ou [] si introuvable.
private _srcTarget = {
    params ["_src"];
    switch (true) do {
        case (_src isEqualTo "PEND"): { +(_s getOrDefault ["ucavPend", []]) };
        case ((_src find "DET:") isEqualTo 0): {
            private _k = _src select [4];
            private _e = (_u getVariable ["COMSPEC_UcavDet", []]) select { (_x select 0) isEqualTo _k };
            if ((count _e) isEqualTo 0) then { [] } else { (_e select 0) params ["", "_o", "_lbl", "_pos"]; [_o, _pos, _lbl] }
        };
        case ((_src find "LOCK:") isEqualTo 0): {
            private _e = (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]) getOrDefault [_src select [5], createHashMap];
            if ((count _e) isEqualTo 0) then { [] } else {
                private _o = _e getOrDefault ["obj", objNull];
                [[objNull, _o] select (alive _o && {(_e getOrDefault ["state", ""]) isEqualTo "TRACK"}), _e getOrDefault ["pos", []], format ["cible marquée %1", _e getOrDefault ["name", ""]]]
            };
        };
        case (_src isEqualTo "LASER"): {
            private _l = (((units group player) + [player]) apply { laserTarget _x }) select { !isNull _x };
            if ((count _l) isEqualTo 0) then { [] } else { [objNull, getPosASL (_l select 0), "point laser ami"] }
        };
        default {
            private _p = [_s getOrDefault ["ucavSGridVal", ""]] call _gridPos;
            if ((count _p) isEqualTo 0) then { [] } else { [objNull, _p, format ["grille %1", [_p, 8] call comspec_atak_native_fnc_gridRef]] }
        };
    };
};
switch (_act) do {
    case "view": {
        _s set ["droneUcav", true];
        if (_arg isEqualType "" && {_arg isNotEqualTo ""}) then { _s set ["ucavSel", _arg]; };
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "DRONE") then { call _render; } else { [{ ["DRONE"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame; };
    };
    case "back": { _s set ["droneUcav", false]; [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
    case "tab": { call _saveFields; _s set ["ucavTab", _arg]; call _render; };
    case "alt": { _s set ["ucavAlt", _arg]; call _render; };
    case "rad": { _s set ["ucavRad", _arg]; call _render; };
    case "wpn": { _s set ["ucavWpn", _arg]; call _render; };
    case "pendClear": { _s set ["ucavPend", []]; call _render; };
    case "take": {
        private _v = objectFromNetId _arg;
        if (isNull _v || {!alive _v}) exitWith { ["WARNING", "Cet UCAV n'existe plus"] call _say; };
        private _why = ["can", player] call _fn;
        if (_why isNotEqualTo "") exitWith { ["WARNING", _why, 6] call _say; };
        if (["take", [], _v] call _send) then {
            ["INFO", "Demande de prise en main envoyée"] call _say;
            [format ["Prise en main demandée : %1", [_v] call _nameOf]] call _log;
        };
    };
    case "release": {
        if (isNull _u) exitWith {};
        if !(["release", "Appuyez encore pour rendre l'UCAV : il garde son orbite"] call _confirm) exitWith {};
        ["release"] call _send;
        ["UCAV rendu : il garde son orbite"] call _drop;
    };
    case "orbit";
    case "hunt": {
        call _saveFields;
        private _g = _s getOrDefault ["ucavGridVal", ""];
        private _p = if ((_g regexReplace ["[^0-9]", ""]) isEqualTo "") then { getPosASL player } else { [_g] call _gridPos };
        if (_arg isEqualType [] && {(count _arg) >= 2}) then { _p = [_arg select 0, _arg select 1, getTerrainHeightASL [_arg select 0, _arg select 1]]; };
        if ((count _p) < 3) exitWith { ["WARNING", "Grille invalide (6, 8 ou 10 chiffres)"] call _say; };
        private _r = _s getOrDefault ["ucavRad", 1200];
        private _alt = _s getOrDefault ["ucavAlt", 1000];
        private _ok = if (_act isEqualTo "orbit") then { ["orbit", [_p, _r, _alt]] call _send } else {
            ["hunt", [_p, _r, _alt, [_p, _r, _r, 0, false], _s getOrDefault ["ucavRoe", "PROPOSE"]]] call _send
        };
        if (_ok) then {
            private _gr = [_p, 8] call comspec_atak_native_fnc_gridRef;
            [format [["Ordre : orbite en %1, rayon %2 m, %3 m", "Ordre : rechercher et tuer en %1, rayon %2 m, %3 m"] select (_act isEqualTo "hunt"), _gr, _r, _alt]] call _log;
            ["INFO", format [["UCAV : orbite en %1", "UCAV : rechercher et tuer en %1"] select (_act isEqualTo "hunt"), _gr]] call _say;
        };
        call _render;
    };
    case "mapZone": {
        _arg params ["_k", "_a", "_b"];
        if (isNull _u) exitWith { ["WARNING", "Aucun UCAV commandé"] call _say; };
        private _alt = _s getOrDefault ["ucavAlt", 1000];
        if (_k isEqualTo "UCAV_HUNT") then {
            private _c = [((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2];
            _c pushBack (getTerrainHeightASL _c);
            private _hx = (abs ((_b select 0) - (_a select 0)) / 2) max 100;
            private _hy = (abs ((_b select 1) - (_a select 1)) / 2) max 100;
            if (["hunt", [_c, ((_hx max _hy) max 800) min 4000, _alt, [_c, _hx, _hy, 0, true], _s getOrDefault ["ucavRoe", "PROPOSE"]]] call _send) then {
                [format ["Ordre : rechercher et tuer sur zone %1 × %2 m en %3 (carte)", round (_hx * 2), round (_hy * 2), [_c, 8] call comspec_atak_native_fnc_gridRef]] call _log;
            };
        } else {
            private _r = (((_a distance2D _b) max 600) min 5000);
            if (["orbit", [[_a select 0, _a select 1, getTerrainHeightASL _a], round _r, _alt]] call _send) then {
                [format ["Ordre : orbite en %1, rayon %2 m (carte)", [_a, 8] call comspec_atak_native_fnc_gridRef, round _r]] call _log;
            };
        };
    };
    case "roe": {
        if !(_arg in ["PROPOSE", "FREE"]) exitWith {};
        if (_arg isEqualTo "FREE" && {!(missionNamespace getVariable ["comspec_atak_native_ucav_free", true])}) exitWith { ["WARNING", "L'engagement libre est interdit sur ce serveur : les cibles restent proposées"] call _say; };
        _s set ["ucavRoe", _arg];
        if (!isNull _u) then {
            if (["roe", [_arg]] call _send) then { [format ["Règle d'engagement : %1", ["proposer chaque cible", "engager librement les ennemis identifiés"] select (_arg isEqualTo "FREE")], "#f2ab33"] call _log; };
        };
        call _render;
    };
    case "pickMap": {
        call _saveFields;
        _s set ["ucavPick", _arg];
        _s set ["dronePick", "1"];
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
        ["INFO", ["Touchez le centre de l'orbite sur la carte", "Touchez le centre de la zone de recherche sur la carte"] select (_arg isEqualTo "HUNT")] call _say;
    };
    case "picked": {
        private _k = _s getOrDefault ["ucavPick", ""];
        _s set ["ucavPick", ""];
        _s set ["ucavGridVal", [_arg, 10] call comspec_atak_native_fnc_gridRef];
        _s set ["droneUcav", true];
        [{ ["DRONE"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
        if (_k in ["ORBIT", "HUNT"]) then { [toLower _k, _arg] call _fn; };
    };
    case "strike": {
        call _saveFields;
        if (isNull _u) exitWith { ["WARNING", "Aucun UCAV commandé"] call _say; };
        private _t = [_arg] call _srcTarget;
        if ((count _t) < 2 || {(count (_t select 1)) < 2}) exitWith {
            ["WARNING", switch (true) do { case (_arg isEqualTo "LASER"): { "Aucun laser allumé dans votre groupe" }; case (_arg isEqualTo "GRID"): { "Grille invalide (6, 8 ou 10 chiffres)" }; default { "Cible introuvable" }; }] call _say;
        };
        _t params ["_o", "_p", ["_lbl", "cible"]];
        if (_o isEqualType objNull && {!isNull _o}) then { _p = getPosASL _o; } else { _o = objNull; };
        if ((count _p) < 3) then { _p = [_p select 0, _p select 1, getTerrainHeightASL _p]; };
        private _w = _s getOrDefault ["ucavWpn", ""];
        private _ws = (["weapons", _u] call _fn) select { (_x select 2) > 0 };
        if ((count _ws) isEqualTo 0) exitWith { ["WARNING", "L'UCAV n'a plus de munition guidée"] call _say; };
        if (_w isNotEqualTo "" && {(_ws findIf { (_x select 0) == _w }) < 0}) then { _w = ""; };
        // Danger close (distance de sécurité de l'appui aérien) : dit au second appui demandé.
        private _fr = ["friend", [side group player, _p, ["dc"] call _fn, false]] call _fn;
        private _msg = if (_fr >= 0) then { format ["Appuyez encore pour confirmer la frappe (DANGER CLOSE : allié à %1 m)", _fr] } else { "Appuyez encore pour confirmer la frappe" };
        if !(["S:" + _arg, _msg] call _confirm) exitWith {};
        private _wl = if (_w isEqualTo "") then { "arme choisie par l'UCAV" } else { ((_ws select { (_x select 0) == _w }) select 0) select 3 };
        if (["strike", [_o, _p, _w, _lbl, [player, true] call comspec_atak_native_fnc_unitCallsign]] call _send) then {
            if (_arg isEqualTo "PEND") then { _s set ["ucavPend", []]; };
            [format ["Frappe demandée sur %1 en %2 (%3)", _lbl, [_p, 8] call comspec_atak_native_fnc_gridRef, _wl], "#e5483a"] call _log;
        };
        call _render;
    };
    case "cancel": {
        if (isNull _u) exitWith {};
        if !(["C:" + _arg, ["Appuyez encore pour annuler cette frappe", "Appuyez encore pour annuler toutes les frappes en attente"] select (_arg isEqualTo "ALL")] call _confirm) exitWith {};
        if (["cancel", [_arg]] call _send) then { [format ["Annulation demandée : %1", [_arg, "toutes les frappes"] select (_arg isEqualTo "ALL")]] call _log; };
        call _render;
    };
    case "ignore": { if (!isNull _u) then { ["ignore", [_arg]] call _send; call _render; }; };
    case "look": {
        if (isNull _u) exitWith {};
        private _t = [_arg] call _srcTarget;
        if ((count _t) < 2) exitWith { ["WARNING", "Cible introuvable"] call _say; };
        if (["look", [_t select 0, _t select 1]] call _send) then { ["INFO", "Caméra de l'UCAV pointée sur la cible"] call _say; };
    };
    case "resume": {
        if (isNull _u) exitWith {};
        if (["resume"] call _send) then { ["Reprise demandée : l'UCAV réapplique sa mission"] call _log; ["INFO", "UCAV : mission réappliquée"] call _say; };
    };
    case "bda": {
        private _b = missionNamespace getVariable ["COMSPEC_ATAK_UcavBda", []];
        if ((count _b) < 7) exitWith { ["WARNING", "Aucune frappe à rapporter"] call _say; };
        _b params ["_type", "_res", "_wl", "_tl", "_imp", "_miss", "_nm", ["_ekia", ""]];
        ["prefill", "BDA", createHashMapFromArray [
            ["type", _type], ["grid", [_imp, 8] call comspec_atak_native_fnc_gridRef], ["result", _res], ["ekia", _ekia], ["equip", ["", _tl] select (_type isEqualTo "VÉHICULE" && {_res isEqualTo "DÉTRUIT"})],
            ["ordnance", format ["1 x %1, UCAV %2", _wl, _nm]], ["collateral", "INCONNU"], ["reattack", ["REPRISE REQUISE", "PAS DE REPRISE"] select (_res isEqualTo "DÉTRUIT")],
            ["remarks", format ["Frappe UCAV sur %1, impact à %2 m du point visé. Résultat proposé par le capteur de l'UCAV, à confirmer à vue.", _tl, _miss]]
        ]] call comspec_atak_native_fnc_reportAction;
        [{ ["REPORTS"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "rows": {
        _arg params [["_c", createHashMap], ["_map", controlNull]];
        if ((_c getOrDefault ["sub", ""]) isNotEqualTo "" || {_c getOrDefault ["card", false]}) exitWith { [] };
        private _dir = "\z\comspec_atak_native\addons\main\data\";
        private _col = [0.30, 0.85, 0.95, 1];
        if (isNull _u || {!alive _u}) exitWith {
            if (!(missionNamespace getVariable ["comspec_atak_native_ucav", true]) || {(["can", player] call _fn) isNotEqualTo ""} || {(count (["list"] call _fn)) isEqualTo 0}) exitWith { [] };
            [["UCAV_OPEN", "UCAV armés", "prendre la main", _dir + "app_drone.paa", _col]]
        };
        private _pos = _c getOrDefault ["pos", [0, 0, 0]];
        private _nm = [_u] call _nameOf;
        // Cible sous le clic : cible marquée (marquage suivi), contact de la carte, sinon marqueur pointé.
        private _t = [];
        if (!isNull _map) then {
            private _sp0 = _map ctrlMapWorldToScreen _pos;
            private _best = 0.03;
            {
                _x params ["_k", "_e"];
                private _ep = _e getOrDefault ["pos", []];
                if ((count _ep) >= 2) then {
                    private _dd = (_map ctrlMapWorldToScreen _ep) distance2D _sp0;
                    if (_dd < _best) then { _best = _dd; _t = ["LOCK:" + _k, format ["cible marquée %1", _e getOrDefault ["name", ""]]]; };
                };
            } forEach (["list"] call comspec_atak_native_fnc_lockAction);
            if ((count _t) isEqualTo 0) then {
                {
                    if ((_y getOrDefault ["affiliation", "friend"]) isNotEqualTo "friend") then {
                        private _ep = _y getOrDefault ["position", []];
                        if ((count _ep) >= 2) then {
                            private _dd = (_map ctrlMapWorldToScreen _ep) distance2D _sp0;
                            if (_dd < _best) then {
                                _best = _dd;
                                private _o = _y getOrDefault ["object", objNull];
                                _t = ["CONTACT", "contact de la carte", _o, [_ep select 0, _ep select 1, getTerrainHeightASL _ep]];
                            };
                        };
                    };
                } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]);
            };
        };
        private _m = _c getOrDefault ["marker", ""];
        if ((count _t) isEqualTo 0 && {_m isNotEqualTo ""}) then {
            private _mp = getMarkerPos _m;
            _t = ["MARKER", format ["marqueur %1", [markerText _m, "sans nom"] select ((markerText _m) isEqualTo "")], objNull, [_mp select 0, _mp select 1, getTerrainHeightASL _mp]];
        };
        _c set ["ucavT", _t];
        private _rows = [];
        if ((count _t) > 0) then { _rows pushBack ["UCAV_STRIKE", format ["Frapper avec %1", _nm], "arme, 2 appuis", "\A3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa", [0.95, 0.35, 0.3, 1]]; };
        _rows pushBack ["UCAV_ORBIT", "UCAV : orbite ici", format ["rayon %1 m", _s getOrDefault ["ucavRad", 1200]], _dir + "app_drone.paa", _col];
        _rows pushBack ["UCAV_HUNT", "UCAV : rechercher et tuer", ["proposer les cibles", "engager librement"] select ((_s getOrDefault ["ucavRoe", "PROPOSE"]) isEqualTo "FREE"), "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa", [1, 0.62, 0.2, 1]];
        _rows
    };
    case "ctx": {
        _arg params [["_k", ""], ["_pos", []], ["_t", []]];
        switch (_k) do {
            case "OPEN": { ["view", ""] call _fn; };
            case "ORBIT": { ["orbit", _pos] call _fn; };
            case "HUNT": { ["hunt", _pos] call _fn; };
            case "STRIKE": {
                if ((count _t) isEqualTo 0) exitWith {};
                if (((_t select 0) find "LOCK:") isEqualTo 0) then {
                    private _e = (missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap]) getOrDefault [(_t select 0) select [5], createHashMap];
                    private _o = _e getOrDefault ["obj", objNull];
                    _s set ["ucavPend", [[objNull, _o] select (alive _o && {(_e getOrDefault ["state", ""]) isEqualTo "TRACK"}), _e getOrDefault ["pos", []], _t select 1]];
                } else {
                    _t params ["", "_lbl", ["_o", objNull], ["_p", []]];
                    if !(_o isEqualType objNull) then { _o = objNull; };
                    _s set ["ucavPend", [_o, _p, _lbl]];
                };
                _s set ["ucavTab", "TARGETS"];
                _s set ["ucavCf", ["", 0]];
                ["INFO", "Cible prête : choisissez l'arme, puis FRAPPER deux fois"] call _say;
                ["view", ""] call _fn;
            };
        };
    };
    case "evt": {
        _arg params [["_v", objNull], ["_kind", ""], ["_txt", ""], ["_pos", []], ["_ex", []]];
        private _lvl = switch (_kind) do {
            case "REFUSED"; case "FAIL"; case "LOST": { "WARNING" };
            case "PROPOSE"; case "ENGAGE": { "TACTICAL" };
            case "IMPACT"; case "BDA"; case "TAKEN": { "SUCCESS" };
            default { "INFO" };
        };
        if (_kind isEqualTo "TAKEN" && {!isNull _v}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_Ucav", _v];
            _s set ["droneUcav", true];
            call _stopPfh;
            missionNamespace setVariable ["COMSPEC_ATAK_UcavLinkOk", true];
            missionNamespace setVariable ["COMSPEC_ATAK_UcavPfh", [{ ["tick"] call comspec_atak_native_fnc_ucav; }, 1] call CBA_fnc_addPerFrameHandler];
        };
        if (_kind isEqualTo "LOST" && {_v isEqualTo _u}) exitWith { [_txt] call _drop; };
        if (_kind isEqualTo "BDA") then { missionNamespace setVariable ["COMSPEC_ATAK_UcavBda", +_ex]; };
        if (_txt isNotEqualTo "") then {
            [_lvl, _txt, [5, 8] select (_kind in ["PROPOSE", "BDA", "REFUSED"])] call _say;
            [_txt, (createHashMapFromArray [["WARNING", "#f2ab33"], ["TACTICAL", "#e5483a"], ["SUCCESS", "#5cc76b"]]) getOrDefault [_lvl, ""]] call _log;
        };
        if (_kind in ["PROPOSE", "ENGAGE", "IMPACT"]) then { [] call comspec_atak_native_fnc_vibrate; };
        call _render;
    };
    case "tick": {
        if (isNull _u) exitWith { call _stopPfh; };
        if (!alive _u) exitWith { ["UCAV perdu : détruit"] call _drop; };
        if (((_u getVariable ["COMSPEC_UcavCtl", []]) param [0, ""]) isNotEqualTo (getPlayerUID player)) exitWith { ["Vous n'avez plus la main sur l'UCAV"] call _drop; };
        private _lk = ["link", _u] call _fn;
        missionNamespace setVariable ["COMSPEC_ATAK_UcavLinkC", _lk];
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_UcavLinkOk", true];
        if ((_lk select 0) isNotEqualTo _was) then {
            missionNamespace setVariable ["COMSPEC_ATAK_UcavLinkOk", _lk select 0];
            if (_lk select 0) then { ["SUCCESS", "Liaison UCAV rétablie"] call _say; ["Liaison rétablie", "#5cc76b"] call _log; } else {
                private _t = format ["Liaison UCAV perdue (%1) : il poursuit sa mission seul", _lk select 3];
                ["WARNING", _t, 6] call _say; [_t, "#e5483a"] call _log;
            };
        };
        if !((_s getOrDefault ["activePage", ""]) isEqualTo "DRONE" && {_s getOrDefault ["droneUcav", false]}) exitWith {};
        // Page ouverte : redessinée quand l'état, la file, les détections ou les munitions changent ; sinon incrustation seule.
        private _sig = str [_u getVariable ["COMSPEC_UcavMode", ""], (_u getVariable ["COMSPEC_UcavSt", []]) select [0, 2], (_u getVariable ["COMSPEC_UcavQ", []]) apply { _x select 0 },
            (_u getVariable ["COMSPEC_UcavDet", []]) apply { [_x select 0, _x select 4, _x select 6] }, _u getVariable ["COMSPEC_UcavRoe", ""], (["weapons", _u] call _fn) apply { _x select 2 }, _lk select 0];
        if (_sig isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_UcavSig", ""])) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_UcavSig", _sig];
            call _render;
        };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_UcavOsd", controlNull];
        if (!isNull _osd) then { _osd ctrlSetStructuredText parseText (([_u, _lk] call comspec_atak_native_fnc_pageUcav) call comspec_atak_native_fnc_tr); };
    };
};
