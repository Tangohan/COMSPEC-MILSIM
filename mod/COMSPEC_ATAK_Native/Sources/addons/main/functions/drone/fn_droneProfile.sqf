/*
    Profils de décollage des drones, par famille, reconnus par la config (la table de classes n'est qu'un raccourci).
    Un décollage « tout droit vers le haut à 3 m/s puis doMove à 40 km/h » ne convient qu'à un quadricoptère : un avion
    sans vitesse décroche, et sous 40 km/h il décroche même en l'air (sa vitesse de décrochage est bien plus haute).
      ["table"]                  table des profils (QUAD, HELI, PLANE, LAUNCH, UGV) ; réglable par
                                 missionNamespace COMSPEC_ATAK_DroneProfiles (famille → champs à remplacer) ;
      ["kind", drone | classe]   famille : "QUAD" quadricoptère (Darter AR-2, Mavic...), "HELI" drone à rotor plus lourd
                                 (Falcon, Pelican...), "PLANE" voilure fixe sur piste (Greyhawk, Ababil, Sentinel...),
                                 "LAUNCH" voilure fixe légère lancée à la main (RQ-11 Raven), "UGV" drone terrestre
                                 (pas de décollage), "NONE" inconnu. Ordre : surcharge missionNamespace
                                 COMSPEC_ATAK_DroneClassKinds (classe → famille), attribut de config comspec_droneProfile,
                                 table des classes, puis simulation de la config (Plane, Helicopter, LandVehicle) ;
      ["get", drone | classe]    profil complet de la famille, avec vitesse minimale (minKmh, 1,3 fois le décrochage de
                                 la config), vitesse de lancer (launchMs) et clé "kind" ;
      ["check", drone, ignorerLiaison]  contrôles avant décollage → [code, profil] ; code "OK" (profil avec "method" :
                                 "CLIMB" montée verticale, "ROLL" roulage sur piste, "ASSIST" lancement) ou raison de
                                 l'échec : "DEAD", "UGV", "UNKNOWN", "FLYING" (déjà en vol : rien à faire), "FUEL",
                                 "CONNECTED" (terminal d'un autre joueur), "OVERHEAD" (obstacle au-dessus), "NOROOM"
                                 (pas d'espace dégagé devant pour rouler ou lancer).
    Aucun retour à l'écran d'Arma : le téléphone affiche le code sur la ligne d'état de l'app Drone (fn_droneAction).
*/
params [["_op", "table"], ["_a", objNull], ["_skipLink", false]];

private _mk = { createHashMapFromArray (_this select 0) };
private _table = [[
    ["QUAD", [[["name", "quadricoptère"], ["safeAgl", 6], ["climb", 2.5], ["timeout", 25], ["minKmh", 0], ["minAlt", 0], ["launchMs", 0], ["pitch", 0], ["run", 0], ["corridor", 0]]] call _mk],
    ["HELI", [[["name", "drone à rotor"], ["safeAgl", 12], ["climb", 3], ["timeout", 40], ["minKmh", 0], ["minAlt", 0], ["launchMs", 0], ["pitch", 0], ["run", 0], ["corridor", 0]]] call _mk],
    ["PLANE", [[["name", "voilure fixe sur piste"], ["safeAgl", 40], ["climb", 0], ["timeout", 70], ["minKmh", 120], ["minAlt", 80], ["launchMs", 45], ["pitch", 14], ["run", 700], ["corridor", 300]]] call _mk],
    ["LAUNCH", [[["name", "lancement à la main"], ["safeAgl", 15], ["climb", 0], ["timeout", 25], ["minKmh", 55], ["minAlt", 60], ["launchMs", 18], ["pitch", 12], ["run", 0], ["corridor", 120]]] call _mk],
    ["UGV", [[["name", "drone terrestre"], ["safeAgl", 0], ["climb", 0], ["timeout", 0], ["minKmh", 0], ["minAlt", 0], ["launchMs", 0], ["pitch", 0], ["run", 0], ["corridor", 0]]] call _mk]
]] call _mk;
{
    private _over = (missionNamespace getVariable ["COMSPEC_ATAK_DroneProfiles", createHashMap]);
    if (_over isEqualType createHashMap && {_x in _over} && {(_over get _x) isEqualType createHashMap}) then { (_table get _x) merge [_over get _x, true]; };
} forEach (keys _table);

// Raccourci par classe (vanilla Arma 3 et mods courants) : famille → classes ou classes de base.
private _classes = [
    ["QUAD", ["UAV_01_base_F", "B_UAV_01_F", "O_UAV_01_F", "I_UAV_01_F", "Mavic_drone_base_F"]],
    ["HELI", ["UAV_03_base_F", "B_T_UAV_03_F", "UAV_06_base_F", "B_UAV_06_F", "B_UAV_06_medical_F"]],
    ["PLANE", ["UAV_02_base_F", "B_UAV_02_F", "B_UAV_02_CAS_F", "O_UAV_02_F", "O_UAV_02_CAS_F", "I_UAV_02_F", "I_UAV_02_CAS_F", "UAV_04_base_F", "O_T_UAV_04_CAS_F", "UAV_05_base_F", "B_UAV_05_F"]],
    ["UGV", ["UGV_01_base_F", "B_UGV_01_F", "B_UGV_01_rcws_F", "O_UGV_01_F", "O_UGV_01_rcws_F", "I_UGV_01_F", "I_UGV_01_rcws_F", "UGV_02_Base_F", "B_T_UGV_01_F", "O_T_UGV_01_F"]]
];
// Lancé à la main : RQ-11 Raven et variantes (GX Drones « RQ-11B », RHS, CUP, USP, ACE...), reconnus par le nom de classe ou le nom affiché.
private _launchRx = ".*(raven|rq[ _-]?11).*";

private _kindOfClass = {
    params ["_cls"];
    private _cfg = configFile >> "CfgVehicles" >> _cls;
    if (!isClass _cfg) exitWith { "NONE" };
    private _plane = _cls isKindOf "Plane";
    private _heli = _cls isKindOf "Helicopter";
    private _ground = _cls isKindOf "LandVehicle" || {_cls isKindOf "Ship"};
    private _cl = toLower _cls;
    private _ov = missionNamespace getVariable ["COMSPEC_ATAK_DroneClassKinds", createHashMap];
    private _forced = if (_ov isEqualType createHashMap) then { _ov getOrDefault [_cls, ""] } else { "" };
    if (_forced isEqualTo "") then { _forced = getText (_cfg >> "comspec_droneProfile"); };
    if (_forced in ["QUAD", "HELI", "PLANE", "LAUNCH", "UGV"]) exitWith { _forced };
    if (_ground) exitWith { "UGV" };
    if (_plane && {_cl regexMatch _launchRx || {(toLower getText (_cfg >> "displayName")) regexMatch _launchRx}}) exitWith { "LAUNCH" };
    // La table ne vaut que si elle est d'accord avec la simulation de la config (la config gagne en cas de conflit).
    private _hit = "";
    { _x params ["_k", "_list"]; if (_hit isEqualTo "" && {(_list findIf { _cls isKindOf _x }) >= 0}) then { _hit = _k; }; } forEach _classes;
    if (_hit in ["PLANE", "LAUNCH"] && {_plane}) exitWith { _hit };
    if (_hit in ["QUAD", "HELI"] && {_heli}) exitWith { _hit };
    if (_hit isEqualTo "UGV") exitWith { "UGV" };
    if (_plane) exitWith {
        // Vtol de la config : décollage vertical ; petite voilure lente : lancement à la main ; sinon piste.
        if ((getNumber (_cfg >> "vtol")) > 0) exitWith { "HELI" };
        if ((getNumber (_cfg >> "maxSpeed")) > 0 && {(getNumber (_cfg >> "maxSpeed")) <= 140}) exitWith { "LAUNCH" };
        "PLANE"
    };
    if (_heli) exitWith {
        if ((getNumber (_cfg >> "transportSoldier")) > 0 || {(getNumber (_cfg >> "maximumLoad")) >= 500}) exitWith { "HELI" };
        "QUAD"
    };
    // Autre engin aérien non reconnu : montée verticale prudente, jamais d'ordre de vol à plat au sol.
    if (_cls isKindOf "Air") exitWith { "QUAD" };
    "NONE"
};
private _clsOf = { params ["_o"]; [_o, typeOf _o] select (_o isEqualType objNull) };

switch (_op) do {
    case "table": { _table };
    case "kind": { [[_a] call _clsOf] call _kindOfClass };
    case "get": {
        private _cls = [_a] call _clsOf;
        private _k = [_cls] call _kindOfClass;
        private _p = +(_table getOrDefault [_k, _table get "QUAD"]);
        _p set ["kind", _k];
        private _cfg = configFile >> "CfgVehicles" >> _cls;
        private _stall = getNumber (_cfg >> "stallSpeed");
        if (_k in ["PLANE", "LAUNCH"]) then {
            // Au moins 1,3 fois le décrochage de la config (jamais sous la valeur sûre du profil).
            _p set ["minKmh", ceil ((_p get "minKmh") max (_stall * 1.3))];
            _p set ["launchMs", (_p get "launchMs") max (((_p get "minKmh") / 3.6) * 1.15)];
            private _mx = getNumber (_cfg >> "maxSpeed");
            if (_mx > 0) then { _p set ["minKmh", (_p get "minKmh") min (_mx * 0.8)]; };
        };
        _p
    };
    case "check": {
        private _d = _a;
        private _p = ["get", _d] call comspec_atak_native_fnc_droneProfile;
        private _k = _p get "kind";
        if (isNull _d || {!alive _d}) exitWith { ["DEAD", _p] };
        if (_k isEqualTo "UGV") exitWith { ["UGV", _p] };
        if (_k isEqualTo "NONE") exitWith { ["UNKNOWN", _p] };
        private _agl = (getPosATL _d) select 2;
        if ((_agl > 1.5 && {isEngineOn _d}) || {_agl > 3 && {!isTouchingGround _d}}) exitWith { ["FLYING", _p] };
        if ((fuel _d) < 0.05) exitWith { ["FUEL", _p] };
        if (!_skipLink && {isUAVConnected _d}) then {
            private _c = (UAVControl _d) param [0, objNull];
            if (!isNull _c && {isPlayer _c} && {_c isNotEqualTo player}) exitWith { _p set ["blocked", "CONNECTED"]; };
        };
        if ((_p getOrDefault ["blocked", ""]) isEqualTo "CONNECTED") exitWith { ["CONNECTED", _p] };
        private _pa = getPosASL _d;
        private _dir = getDir _d;
        private _fw = [sin _dir, cos _dir, 0];
        private _head = _pa vectorAdd [0, 0, 1.2];
        // Obstacle au-dessus (toit, arbre, câble) ou terrain : la montée verticale ne passerait pas.
        private _blocked = {
            params ["_from", "_to"];
            (terrainIntersectASL [_from, _to]) || {(count (lineIntersectsSurfaces [_from, _to, _d, objNull, true, 1])) > 0}
        };
        if (_k in ["QUAD", "HELI"]) exitWith {
            if ([_head, _pa vectorAdd [0, 0, (_p get "safeAgl") + 2]] call _blocked) exitWith { ["OVERHEAD", _p] };
            _p set ["method", "CLIMB"];
            ["OK", _p]
        };
        // Voilure fixe : couloir de montée dégagé devant (pente de 20 %), puis piste plane pour rouler, sinon lancement assisté.
        private _len = _p get "corridor";
        private _corr = _head vectorAdd (_fw vectorMultiply _len) vectorAdd [0, 0, (_len * 0.2) + 5];
        if ([_head, _corr] call _blocked) exitWith { ["NOROOM", _p] };
        private _method = "ASSIST";
        private _run = _p get "run";
        if (_k isEqualTo "PLANE" && {_run > 0}) then {
            private _h0 = getTerrainHeightASL _pa;
            private _flat = true;
            for "_i" from 50 to _run step 50 do {
                private _q = _pa vectorAdd (_fw vectorMultiply _i);
                if (surfaceIsWater _q || {abs ((getTerrainHeightASL _q) - _h0) > 4}) exitWith { _flat = false; };
            };
            if (_flat && {!([_pa vectorAdd [0, 0, 1.5], (_pa vectorAdd (_fw vectorMultiply _run)) vectorAdd [0, 0, 1.5]] call _blocked)}) then { _method = "ROLL"; };
        };
        _p set ["method", _method];
        ["OK", _p]
    };
    default { _table };
}
