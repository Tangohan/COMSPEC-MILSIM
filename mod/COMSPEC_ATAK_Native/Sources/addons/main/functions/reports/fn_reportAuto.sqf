/*
    Propositions de l'app Comptes rendus : valeurs tirées du jeu pour préremplir une fiche.
    Params : [identifiants] ; renvoie une HashMap identifiant -> texte ("" : rien à proposer).
      dtg, here, contact, missionStart, route                         heure, ma grille, mon indicatif, mission
      enemyGrid, enemyDir, enemyDist, enemySize, enemySizeClass,       ennemi visé (réticule) sinon le plus proche connu
      enemyCount, enemyKind, enemyUnit, enemyEquip, enemySummary       de mon groupe, à sa position estimée (pas la vraie)
      fit, wia, kia, groupLoss, friendlySummary, activity, capability  effectifs de mon groupe
      ammo, fuel, medical, vehicles                                    niveaux de mon groupe (VERT / ORANGE / ROUGE / NOIR)
      targetGrid                                                       désignateur laser, sinon ce que je vise
      casualty, casualtySigns                                          blessé le plus proche (moi compris)
    Seul ce que le groupe connaît (knowsAbout) sert : la fiche ne révèle jamais un ennemi que personne n'a vu.
*/
params [["_ids", []]];
private _out = createHashMap;
private _need = { params ["_l"]; (_l findIf { _x in _ids }) >= 0 };
private _grid = { params ["_p"]; [_p, 8] call comspec_atak_native_fnc_gridRef };
private _cardinal = {
    params ["_from", "_to"];
    private _b = _from getDir _to;
    ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_b / 45)) mod 8)
};
private _level = { params ["_r"]; switch (true) do { case (_r > 0.75): { "VERT" }; case (_r > 0.5): { "ORANGE" }; case (_r > 0.25): { "ROUGE" }; default { "NOIR" } } };
private _wName = { params ["_c", ["_cfg", "CfgWeapons"]]; private _n = getText (configFile >> _cfg >> _c >> "displayName"); [_n, _c] select (_n isEqualTo "") };

if ("dtg" in _ids) then {
    date params ["_yy", "_mo", "_dd", "_hh", "_mi"];
    _out set ["dtg", format ["%1%2%3J %4 %5", [_dd, 2] call CBA_fnc_formatNumber, [_hh, 2] call CBA_fnc_formatNumber, [_mi, 2] call CBA_fnc_formatNumber,
        ["JAN", "FÉV", "MAR", "AVR", "MAI", "JUN", "JUL", "AOÛ", "SEP", "OCT", "NOV", "DÉC"] select ((_mo - 1) max 0 min 11), [_yy mod 100, 2] call CBA_fnc_formatNumber]];
};
if ("here" in _ids) then { _out set ["here", [getPosASL player] call _grid]; };
if ("contact" in _ids) then { _out set ["contact", format ["%1 · ATAK", [player, true] call comspec_atak_native_fnc_unitCallsign]]; };
if ("missionStart" in _ids) then {
    // Début de la partie : heure du jeu moins le temps écoulé (accéléré si la mission accélère le temps).
    private _h = (dayTime - (time * timeMultiplier) / 3600) mod 24;
    if (_h < 0) then { _h = _h + 24; };
    _out set ["missionStart", [_h, "HH:MM"] call BIS_fnc_timeToString];
};
if ("route" in _ids) then {
    private _wps = (waypoints group player) select { ((waypointPosition _x) select 0) != 0 };
    _wps = _wps select [0, 5];
    _out set ["route", (_wps apply { private _d = waypointDescription _x; [[waypointPosition _x] call _grid, _d] select (_d isNotEqualTo "") }) joinString " › "];
};

// ── Ennemi : celui que je vise s'il est connu, sinon le plus proche connu ──────────────────────────────────────
if ([["enemyGrid", "enemyDir", "enemyDist", "enemySize", "enemySizeClass", "enemyCount", "enemyKind", "enemyUnit", "enemyEquip", "enemySummary", "targetGrid", "activity"]] call _need) then {
    private _side = side group player;
    private _known = { params ["_u"]; ((group player) knowsAbout _u) > 0.2 || {(player knowsAbout _u) > 0.2} };
    private _all = (player nearEntities [["CAManBase", "LandVehicle", "Air", "Ship", "StaticWeapon"], 1500]) select {
        alive _x && {[_side, side group _x] call BIS_fnc_sideIsEnemy} && {[_x] call _known}
    };
    private _seen = { params ["_u"]; private _p = (player targetKnowledge _u) param [6, []]; if ((count _p) < 2 || {(_p select 0) isEqualTo 0}) then { getPosASL _u } else { _p } };
    private _aim = cursorTarget;
    if (!isNull _aim && {!(_aim in _all)}) then { _aim = objNull; };
    private _first = _aim;
    if (isNull _first && {(count _all) > 0}) then {
        // Tri sur [distance, rang] : jamais d'objet comparé par sort.
        private _s = _all apply { [player distance2D _x, _forEachIndex] };
        _s sort true;
        _first = _all select ((_s select 0) select 1);
    };
    _out set ["enemyFound", ["", "1"] select !isNull _first];
    if (!isNull _first) then {
        private _pos = [_first] call _seen;
        private _near = _all select { (([_x] call _seen) distance2D _pos) < 150 };
        private _men = [];
        private _veh = [];
        { if (_x isKindOf "CAManBase") then { _men pushBackUnique _x; } else { _veh pushBackUnique _x; { _men pushBackUnique _x; } forEach (crew _x); }; } forEach _near;
        private _dismount = _men select { (vehicle _x) isEqualTo _x };
        private _d = player distance2D _pos;
        _out set ["enemyGrid", [_pos] call _grid];
        _out set ["enemyDir", [player, _pos] call _cardinal];
        _out set ["enemyDist", switch (true) do { case (_d < 100): { "< 100 M" }; case (_d < 300): { "100-300 M" }; case (_d < 600): { "300-600 M" }; case (_d < 1000): { "600 M-1 KM" }; default { "> 1 KM" } }];
        private _bits = [];
        if ((count _dismount) > 0) then { _bits pushBack format ["%1 homme%2", count _dismount, ["", "s"] select ((count _dismount) > 1)]; };
        { _bits pushBack ([typeOf _x, "CfgVehicles"] call _wName); } forEach (_veh select [0, 3]);
        _out set ["enemySize", _bits joinString ", "];
        _out set ["enemyCount", str ((count _dismount) + (count _veh))];
        private _n = count _dismount;
        _out set ["enemySizeClass", switch (true) do {
            case (_n isEqualTo 0 && {(count _veh) > 0}): { "Véhicule(s) seul(s)" };
            case (_n <= 2): { "1 à 2 personnes" };
            case (_n <= 10): { "Groupe (3 à 10)" };
            case (_n <= 30): { "Section (10 à 30)" };
            default { "Compagnie ou plus" };
        }];
        _out set ["enemyKind", switch (true) do {
            case ((_veh findIf { unitIsUAV _x }) >= 0): { "Drone" };
            case ((_veh findIf { _x isKindOf "Air" }) >= 0): { "Aéronef" };
            case ((_veh findIf { _x isKindOf "Tank" || {_x isKindOf "Wheeled_APC_F"} }) >= 0): { "Véhicule blindé" };
            case ((count _veh) > 0): { "Véhicule léger" };
            default { "Personnel" };
        }];
        private _uni = "";
        { if (uniform _x isNotEqualTo "") exitWith { _uni = [uniform _x] call _wName; }; } forEach _dismount;
        _out set ["enemyUnit", _uni];
        private _eq = [];
        { if (primaryWeapon _x isNotEqualTo "") then { _eq pushBackUnique ([primaryWeapon _x] call _wName); }; if (secondaryWeapon _x isNotEqualTo "") then { _eq pushBackUnique ([secondaryWeapon _x] call _wName); }; } forEach _dismount;
        { _eq pushBackUnique ([typeOf _x, "CfgVehicles"] call _wName); } forEach _veh;
        _out set ["enemyEquip", (_eq select [0, 4]) joinString ", "];
        _out set ["enemySummary", format ["%1 ennemi(s) connu(s) à moins de 1,5 km. Le plus proche : %2 à %3 %4 m (grille %5).",
            count _all, [_out get "enemySize", "un ennemi"] select ((_out get "enemySize") isEqualTo ""), _out get "enemyDir", (round (_d / 10)) * 10, _out get "enemyGrid"]];
    } else {
        _out set ["enemySummary", "Aucun ennemi connu à moins de 1,5 km."];
    };
    // Cible du BDA : le désignateur laser, sinon ce que je vise (3 km), sinon l'ennemi trouvé ci-dessus.
    private _lt = laserTarget player;
    if (isNull _lt) then { _lt = laserTarget (vehicle player); };
    private _tp = [];
    if (!isNull _lt) then { _tp = getPosASL _lt; } else {
        private _co = cursorObject;
        if (!isNull _co && {(player distance2D _co) < 3000}) then { _tp = getPosASL _co; };
    };
    _out set ["targetGrid", [[_tp] call _grid, _out getOrDefault ["enemyGrid", ""]] select ((count _tp) < 2)];
    private _close = (_all findIf { (player distance2D _x) < 400 }) >= 0;
    _out set ["activity", switch (true) do {
        case (_close && {(behaviour player) isEqualTo "COMBAT"}): { "AU CONTACT" };
        case ((speed (vehicle player)) > 4): { "EN MOUVEMENT" };
        default { "EN POSITION" };
    }];
};

// ── Mon groupe : effectifs, niveaux ───────────────────────────────────────────────────────────────────────────
if ([["fit", "wia", "kia", "groupLoss", "friendlySummary", "capability", "ammo", "fuel", "medical", "vehicles"]] call _need) then {
    private _us = units group player;
    private _hurt = { params ["_u"]; (lifeState _u) isEqualTo "INCAPACITATED" || {(damage _u) > 0.25} || {_u getVariable ["ACE_isUnconscious", false]} };
    private _alive = _us select { alive _x };
    private _wia = _alive select { [_x] call _hurt };
    private _kia = (count _us) - (count _alive);
    private _fit = (count _alive) - (count _wia);
    _out set ["fit", str _fit];
    _out set ["wia", str (count _wia)];
    _out set ["kia", str _kia];
    _out set ["groupLoss", switch (true) do { case (_kia > 0): { "TUÉS" }; case ((count _wia) > 0): { "BLESSÉS" }; default { "AUCUNE" } }];
    private _r = _fit / ((count _us) max 1);
    _out set ["capability", switch (true) do { case (_r > 0.9): { "100 %" }; case (_r > 0.65): { "75 %" }; case (_r > 0.4): { "50 %" }; default { "25 % ET MOINS" } }];
    _out set ["friendlySummary", format ["%1 : %2 apte(s), %3 blessé(s), %4 tué(s). Position %5.", groupId group player, _fit, count _wia, _kia, [getPosASL player] call _grid]];
    // Munitions : chargeurs de l'arme principale (6 par homme = plein).
    private _mags = 0;
    private _armed = 0;
    {
        private _w = primaryWeapon _x;
        if (_w isNotEqualTo "") then {
            _armed = _armed + 1;
            private _ok = ([_w] call BIS_fnc_compatibleMagazines) apply { toLower _x };
            _mags = _mags + ((count ((magazines _x) select { (toLower _x) in _ok })) min 8);
            if ((primaryWeaponMagazine _x) isNotEqualTo []) then { _mags = _mags + 1; };
        };
    } forEach _alive;
    if (_armed > 0) then { _out set ["ammo", [_mags / (_armed * 6)] call _level]; };
    // Matériel médical : pansements, garrots, trousses (4 par homme = plein).
    private _medItems = ["firstaidkit", "medikit", "ace_fielddressing", "ace_elasticbandage", "ace_packingbandage", "ace_quikclot", "ace_tourniquet", "ace_morphine", "ace_epinephrine"];
    private _med = 0;
    { _med = _med + (count (((items _x) apply { toLower _x }) select { _x in _medItems })); } forEach _alive;
    if ((count _alive) > 0) then { _out set ["medical", [_med / ((count _alive) * 4)] call _level]; };
    // Véhicules du groupe (et le mien).
    private _vs = [];
    { if ((vehicle _x) isNotEqualTo _x) then { _vs pushBackUnique (vehicle _x); }; if (!isNull (assignedVehicle _x)) then { _vs pushBackUnique (assignedVehicle _x); }; } forEach _us;
    _vs = _vs select { !isNull _x && {!(_x isKindOf "StaticWeapon")} };
    if ((count _vs) > 0) then {
        private _fuel = 0;
        { _fuel = _fuel + fuel _x; } forEach _vs;
        _out set ["fuel", [_fuel / (count _vs)] call _level];
        _out set ["vehicles", switch (true) do {
            case ((_vs findIf { !alive _x || {!canMove _x} }) >= 0): { "IMMOBILISÉS" };
            case ((_vs findIf { (damage _x) > 0.3 }) >= 0): { "DÉGRADÉS" };
            default { "OPÉRATIONNELS" };
        }];
    } else {
        _out set ["vehicles", "AUCUN"];
    };
};

// ── Blessé le plus proche (moi compris) ─────────────────────────────────────────────────────────────────────
if ([["casualty", "casualtySigns"]] call _need) then {
    private _cands = ((player nearEntities ["CAManBase", 60]) + [player]) select {
        (side group _x) isEqualTo (side group player) && {(lifeState _x) isEqualTo "INCAPACITATED" || {(damage _x) > 0.1} || {!alive _x}}
    };
    private _s = _cands apply { [player distance _x, _forEachIndex] };
    _s sort true;
    if ((count _s) > 0) then {
        private _u = _cands select ((_s select 0) select 1);
        _out set ["casualty", [_u, true] call comspec_atak_native_fnc_unitCallsign];
        _out set ["casualtySigns", switch (true) do { case (!alive _u): { "ARRÊT" }; case ((lifeState _u) isEqualTo "INCAPACITATED" || {_u getVariable ["ACE_isUnconscious", false]}): { "INCONSCIENT" }; default { "CONSCIENT" } }];
    };
};
_out
