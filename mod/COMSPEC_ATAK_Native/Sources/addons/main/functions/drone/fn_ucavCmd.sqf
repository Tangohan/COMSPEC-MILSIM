/*
    UCAV : exécution là où l'UCAV est local (événement comspec_atak_native_ucavExec, envoyé par le serveur une fois les
    droits vérifiés, fn_ucav "srv"). Params : [ucav, ordre, arguments, donneur]
      "take"    []                              prise en main : équipage IA prêt (sans souci, tir retenu, plus autonome :
                                                l'IA ne choisit plus ses cibles), orbite sur place si aucune n'est posée ;
      "release" []                              main rendue : posture d'origine, file et détections vidées, orbite gardée ;
      "orbit"   [centre ASL, rayon, altitude]   orbite : point de passage LOITER du jeu (cercle à gauche), hauteur tenue ;
      "hunt"    [centre ASL, rayon, altitude, zone inArea, règle]   Rechercher et tuer : orbite sur la zone et relevé
                                                des capteurs toutes les 3 s (scan) ;
      "roe"     ["PROPOSE" | "FREE"]            règle d'engagement ;
      "strike"  [cible, point ASL, arme, libellé, donneur]   frappe mise en file (arme "" : choisie par l'UCAV) ;
      "cancel"  [id | "ALL"]                    retire de la file, sauf la frappe dont le tir est parti ;
      "ignore"  [clé]                           détection écartée ; "look" [cible, point ASL] : caméra (servant) dessus ;
      "resume"  []                              mission réappliquée (équipage, orbite, file) ;
      "manual"  []                              terminal UAV connecté par le contrôleur (fn_droneCmd) : nos boucles s'effacent ;
      internes : "scan" (relevé périodique, unscheduled), "run" (file de frappes, script), "job" (une frappe, script).
    Frappe ("job") : arme choisie (missile pour un véhicule, bombe pour l'infanterie, une position ou une pièce
    fixe), approche pilotée par doMove jusqu'à l'enveloppe de tir (missile : entre la portée min et max de la munition,
    cible à moins de 40° du nez et vue ; bombe : point de largage calculé sur la hauteur et la vitesse, nez à moins de
    20° ; trop près et mal aligné : dégagement à 3 km puis nouvelle passe), puis tir sans joueur au poste du servant :
      1. cible révélée au servant IA, annoncée à la liaison de données du camp (reportRemoteTarget, confirmSensorTarget),
         doWatch + doTarget, arme choisie (selectWeaponTurret) ; point sans objet : cible laser posée sur le point ;
      2. fireAtTarget (tir forcé à distance : le missile part guidé vers la cible) ; rien en 2 s : BIS_fnc_fire sur la
         tourelle de l'arme ; rien en 2 s : forceWeaponFire du servant ;
      3. la munition partie (gestionnaire Fired) est accrochée à la cible : setMissileTarget (objet suivi) ou
         setMissileTargetPos (point), qu'elle ait verrouillé ou non : elle ne dépend plus du laser ni du verrou.
    Puis suivi de la munition jusqu'à l'impact, BDA proposé (à confirmer) et retour à l'orbite ou à la zone.
    Variables publiques de l'UCAV : COMSPEC_UcavCtl ([uid, indicatif, camp] du contrôleur), COMSPEC_UcavMode
    ("ORBIT" | "HUNT" | "MANUAL"), COMSPEC_UcavOrbit ([centre ASL, rayon, altitude]), COMSPEC_UcavZone (zone inArea),
    COMSPEC_UcavRoe, COMSPEC_UcavSt ([état, texte, point ASL, id, serverTime] : TRANSIT, ORBIT, PATROL, ENROUTE, INPOS,
    LOCK, FIRE, IMPACT, BDA, FAIL, CANCEL, MANUAL), COMSPEC_UcavQ ([[id, cible, point ASL, arme, libellé, donneur,
    automatique]...]), COMSPEC_UcavDet ([[clé, objet, libellé, point ASL, identifié, genre INF|VEH|ARM|STATIC, état
    NEW|UNID|PROPOSED|AUTO|QUEUED|DONE|IGNORED|DOWN, allié le plus proche m (-1), vu à]...]).
*/
params [["_u", objNull], ["_cmd", ""], ["_args", []], ["_by", objNull]];
if (isNull _u || {!alive _u} || {!local _u}) exitWith {};
private _fn = comspec_atak_native_fnc_ucav;
private _nm = _u getVariable ["COMSPEC_DroneName", ""];
if (_nm isEqualTo "") then { _nm = getText (configOf _u >> "displayName"); };
private _ctlUnit = {
    private _id = (_u getVariable ["COMSPEC_UcavCtl", []]) param [0, ""];
    private _i = allPlayers findIf { (getPlayerUID _x) isEqualTo _id };
    if (_i < 0) then { objNull } else { allPlayers select _i }
};
// Retour au contrôleur (notification, journal, page) : fn_ucav "evt".
private _evt = {
    params ["_kind", ["_txt", ""], ["_pos", []], ["_ex", []]];
    private _to = call _ctlUnit;
    if (!isNull _to) then { ["comspec_atak_native_ucavEvt", [_u, _kind, _txt, _pos, _ex], _to] call CBA_fnc_targetEvent; };
};
private _st = { params ["_k", ["_t", ""], ["_p", []], ["_id", ""]]; _u setVariable ["COMSPEC_UcavSt", [_k, _t, _p, _id, serverTime], true]; };
private _gunner = { private _g = gunner _u; if (isNull _g || {!alive _g}) then { _g = driver _u; }; _g };
private _running = { private _h = _u getVariable ["COMSPEC_UcavRunH", scriptNull]; !isNull _h && {!scriptDone _h} };
// Équipage IA prêt à obéir : sans souci (pas d'esquive), tir retenu, plus autonome (l'IA ne choisit plus ses cibles).
private _prep = {
    if (isNull driver _u || {!alive driver _u}) then {
        { if (!alive _x) then { _u deleteVehicleCrew _x; }; } forEach (crew _u);
        private _cg = createVehicleCrew _u;
        if (!isNull _cg) then { _cg deleteGroupWhenEmpty true; };
    };
    private _g = group driver _u;
    if (isNull _g) exitWith {};
    if (isNil { _u getVariable "COMSPEC_UcavPrev" }) then { _u setVariable ["COMSPEC_UcavPrev", [behaviour driver _u, combatMode _g, isAutonomous _u]]; };
    _g setBehaviour "CARELESS";
    _g setCombatMode "BLUE";
    _u setAutonomous false;
    { _x disableAI "AUTOTARGET"; _x enableAI "MOVE"; _x enableAI "PATH"; } forEach (crew _u);
    _u engineOn true;
};
private _loiter = {
    params ["_c", "_r", "_alt"];
    private _g = group driver _u;
    if (isNull _g) exitWith {};
    for "_i" from ((count waypoints _g) - 1) to 0 step -1 do { deleteWaypoint [_g, _i]; };
    private _wp = _g addWaypoint [ASLToAGL _c, -1];
    _wp setWaypointType "LOITER";
    _wp setWaypointLoiterType "CIRCLE_L";
    _wp setWaypointLoiterRadius _r;
    _wp setWaypointBehaviour "CARELESS";
    _wp setWaypointCombatMode "BLUE";
    _g setCurrentWaypoint _wp;
    _u flyInHeight [_alt, true];
    // Après les doMove d'une frappe, l'équipage reprend le point de passage.
    { _x doFollow (leader group _x); } forEach (crew _u);
    (call _gunner) doWatch (ASLToAGL _c);
};
private _resume = {
    private _o = _u getVariable ["COMSPEC_UcavOrbit", []];
    if ((count _o) < 3) then { _o = [getPosASL _u, 1500, 1000]; _u setVariable ["COMSPEC_UcavOrbit", _o, true]; };
    _o call _loiter;
};
// Boucle de relevé (états d'attente, Rechercher et tuer) : une par ordre, la précédente s'arrête au tour suivant.
private _watch = {
    private _tok = (_u getVariable ["COMSPEC_UcavLoop", 0]) + 1;
    _u setVariable ["COMSPEC_UcavLoop", _tok];
    [_u, _tok] spawn {
        params ["_u", "_tok"];
        while { alive _u && {local _u} && {(_u getVariable ["COMSPEC_UcavLoop", 0]) isEqualTo _tok} } do {
            [_u, "scan", []] call comspec_atak_native_fnc_ucavCmd;
            sleep 3;
        };
    };
};
private _startRun = { if !(call _running) then { _u setVariable ["COMSPEC_UcavRunH", [_u, "run", []] spawn comspec_atak_native_fnc_ucavCmd]; }; };
private _qDel = {
    params ["_id"];
    private _q = +(_u getVariable ["COMSPEC_UcavQ", []]);
    private _i = _q findIf { (_x select 0) isEqualTo _id };
    if (_i >= 0) then { _q deleteAt _i; _u setVariable ["COMSPEC_UcavQ", _q, true]; };
    _i >= 0
};
private _detSet = {
    params ["_obj", "_state", ["_key", ""]];
    private _d = +(_u getVariable ["COMSPEC_UcavDet", []]);
    private _ch = false;
    { if ((!isNull _obj && {(_x select 1) isEqualTo _obj}) || {_key isNotEqualTo "" && {(_x select 0) isEqualTo _key}}) then { _x set [6, _state]; _ch = true; }; } forEach _d;
    if (_ch) then { _u setVariable ["COMSPEC_UcavDet", _d, true]; };
};
private _enqueue = {
    params ["_t", "_aim", ["_w", ""], ["_lbl", "cible"], ["_byN", ""], ["_auto", false]];
    private _n = (_u getVariable ["COMSPEC_UcavN", 0]) + 1;
    _u setVariable ["COMSPEC_UcavN", _n];
    private _id = format ["F%1", _n];
    private _q = +(_u getVariable ["COMSPEC_UcavQ", []]);
    _q pushBack [_id, _t, _aim, _w, _lbl, _byN, _auto];
    _u setVariable ["COMSPEC_UcavQ", _q, true];
    [_t, ["QUEUED", "AUTO"] select _auto] call _detSet;
    call _startRun;
    [_id, count _q]
};
// Terminal UAV laissé par le contrôleur, puis ordre du téléphone : la mission reprend.
if (_cmd in ["take", "orbit", "hunt", "strike", "resume"] && {(_u getVariable ["COMSPEC_UcavMode", ""]) in ["", "MANUAL"]}) then {
    _u setVariable ["COMSPEC_UcavMode", ["ORBIT", "HUNT"] select ((count (_u getVariable ["COMSPEC_UcavZone", []])) >= 5), true];
};
switch (_cmd) do {
    case "take";
    case "resume": {
        call _prep;
        if (isNil { _u getVariable "COMSPEC_UcavRoe" }) then { _u setVariable ["COMSPEC_UcavRoe", "PROPOSE", true]; };
        if !(call _running) then { call _resume; };
        call _watch;
        if ((count (_u getVariable ["COMSPEC_UcavQ", []])) > 0) then { call _startRun; };
        (_u getVariable ["COMSPEC_UcavOrbit", []]) params [["_c", getPosASL _u], ["_r", 1500], ["_alt", 1000]];
        if (_cmd isEqualTo "take") then {
            ["TAKEN", format ["UCAV %1 : vous avez la main. Orbite en %2, rayon %3 m, %4 m", _nm, [_c, 8] call comspec_atak_native_fnc_gridRef, round _r, round _alt], _c] call _evt;
        };
    };
    case "release": {
        _u setVariable ["COMSPEC_UcavLoop", (_u getVariable ["COMSPEC_UcavLoop", 0]) + 1];
        { _u setVariable [_x, nil, true]; } forEach ["COMSPEC_UcavMode", "COMSPEC_UcavSt", "COMSPEC_UcavZone", "COMSPEC_UcavRoe"];
        _u setVariable ["COMSPEC_UcavQ", [], true];
        _u setVariable ["COMSPEC_UcavDet", [], true];
        (_u getVariable ["COMSPEC_UcavPrev", []]) params [["_b", "AWARE"], ["_cm", "YELLOW"], ["_au", true]];
        _u setVariable ["COMSPEC_UcavPrev", nil];
        private _g = group driver _u;
        if (!isNull _g) then { _g setBehaviour _b; _g setCombatMode _cm; };
        _u setAutonomous _au;
        { _x enableAI "AUTOTARGET"; } forEach (crew _u);
    };
    case "manual": {
        _u setVariable ["COMSPEC_UcavLoop", (_u getVariable ["COMSPEC_UcavLoop", 0]) + 1];
        _u setVariable ["COMSPEC_UcavMode", "MANUAL", true];
        ["MANUAL", "terminal UAV"] call _st;
        ["STATE", "Terminal UAV connecté : pilotage manuel. Un ordre du téléphone reprend la main"] call _evt;
    };
    case "orbit": {
        _args params [["_c", []], ["_r", 1500], ["_alt", 1000]];
        if !(_c isEqualTypeArray [0, 0, 0]) exitWith {};
        _r = (_r max 500) min 6000;
        _alt = (_alt max 150) min 6000;
        _u setVariable ["COMSPEC_UcavOrbit", [_c, _r, _alt], true];
        _u setVariable ["COMSPEC_UcavZone", [], true];
        _u setVariable ["COMSPEC_UcavMode", "ORBIT", true];
        call _prep;
        if !(call _running) then { [_c, _r, _alt] call _loiter; ["TRANSIT", "en route vers l'orbite", [], ""] call _st; };
        call _watch;
        ["STATE", format ["Orbite en %1 : rayon %2 m, %3 m%4", [_c, 8] call comspec_atak_native_fnc_gridRef, round _r, round _alt, ["", ", après les frappes en file"] select (call _running)], _c] call _evt;
    };
    case "hunt": {
        _args params [["_c", []], ["_r", 1500], ["_alt", 1000], ["_area", []], ["_roe", ""]];
        if !(_c isEqualTypeArray [0, 0, 0]) exitWith {};
        if ((count _area) < 5) then { _area = [_c, _r, _r, 0, false]; };
        _r = (_r max 800) min 6000;
        _alt = (_alt max 150) min 6000;
        _u setVariable ["COMSPEC_UcavOrbit", [_c, _r, _alt], true];
        _u setVariable ["COMSPEC_UcavZone", _area, true];
        _u setVariable ["COMSPEC_UcavMode", "HUNT", true];
        if (_roe in ["PROPOSE", "FREE"]) then { _u setVariable ["COMSPEC_UcavRoe", _roe, true]; };
        _u setVariable ["COMSPEC_UcavDet", [], true];
        call _prep;
        if !(call _running) then { [_c, _r, _alt] call _loiter; ["TRANSIT", "en route vers la zone", [], ""] call _st; };
        call _watch;
        ["STATE", format ["Rechercher et tuer en %1 (%2) : l'UCAV patrouille la zone", [_c, 8] call comspec_atak_native_fnc_gridRef,
            ["chaque cible vous sera proposée", "engagement libre des ennemis identifiés"] select ((_u getVariable ["COMSPEC_UcavRoe", "PROPOSE"]) isEqualTo "FREE")], _c] call _evt;
    };
    case "roe": {
        private _r = _args param [0, "PROPOSE"];
        if !(_r in ["PROPOSE", "FREE"]) exitWith {};
        _u setVariable ["COMSPEC_UcavRoe", _r, true];
        ["STATE", ["Règle d'engagement : chaque cible vous sera proposée", "Règle d'engagement : engagement libre des ennemis identifiés, jamais près d'un allié"] select (_r isEqualTo "FREE")] call _evt;
    };
    case "strike": {
        _args params [["_t", objNull], ["_aim", []], ["_w", ""], ["_lbl", "cible"], ["_byN", ""]];
        if !(_aim isEqualTypeArray [0, 0, 0]) exitWith {};
        call _prep;
        ([_t, _aim, _w, _lbl, _byN, false] call _enqueue) params ["_id", "_n"];
        ["QUEUED", format ["Frappe %1 en file (%2 en attente) : %3", _id, _n, _lbl], _aim] call _evt;
    };
    case "cancel": {
        private _id = _args param [0, ""];
        private _firing = _u getVariable ["COMSPEC_UcavFiring", ""];
        if (_id isEqualTo "ALL") exitWith {
            private _q = (_u getVariable ["COMSPEC_UcavQ", []]) select { (_x select 0) isEqualTo _firing };
            private _n = (count (_u getVariable ["COMSPEC_UcavQ", []])) - (count _q);
            _u setVariable ["COMSPEC_UcavQ", _q, true];
            ["CANCEL", format ["%1 frappe(s) annulée(s)%2", _n, ["", " ; celle dont le tir est parti continue"] select ((count _q) > 0)]] call _evt;
        };
        if (_id isEqualTo _firing) exitWith { ["FAIL", format ["Frappe %1 : tir déjà parti, impossible d'annuler", _id]] call _evt; };
        if ([_id] call _qDel) then { ["CANCEL", format ["Frappe %1 annulée", _id]] call _evt; };
    };
    case "ignore": { [objNull, "IGNORED", _args param [0, ""]] call _detSet; };
    case "look": {
        _args params [["_t", objNull], ["_p", []]];
        private _g = call _gunner;
        if (_t isEqualType objNull && {!isNull _t} && {alive _t}) then { _g doWatch _t; } else { if (_p isEqualTypeArray [0, 0, 0]) then { _g doWatch (ASLToAGL _p); }; };
    };
    case "scan": {
        private _mode = _u getVariable ["COMSPEC_UcavMode", ""];
        if (_mode in ["", "MANUAL"]) exitWith {};
        // Attente (pas de frappe) : en route ou en orbite ; un état de frappe récent reste affiché 25 s.
        if !(call _running) then {
            (_u getVariable ["COMSPEC_UcavOrbit", []]) params [["_c", getPosASL _u], ["_r", 1500]];
            (_u getVariable ["COMSPEC_UcavSt", []]) params [["_k", ""], ["_tx", ""], "", "", ["_at", -1e9]];
            private _in = (_u distance2D _c) < (_r + 600);
            private _want = [["TRANSIT", "en route vers l'orbite"], ["ORBIT", "en orbite"]] select _in;
            if (_mode isEqualTo "HUNT") then { _want = [["TRANSIT", "en route vers la zone"], ["PATROL", "patrouille la zone"]] select _in; };
            if (!(_k in ["IMPACT", "BDA", "FAIL", "CANCEL"]) || {(serverTime - _at) > 25}) then {
                if ([_k, _tx] isNotEqualTo _want) then { [_want select 0, _want select 1] call _st; };
            };
        };
        if (_mode isNotEqualTo "HUNT") exitWith {};
        // Rechercher et tuer : ce que l'équipage et les capteurs de l'UCAV connaissent dans la zone.
        private _zone = _u getVariable ["COMSPEC_UcavZone", []];
        if ((count _zone) < 5) exitWith {};
        private _side = ["sideOf", _u] call _fn;
        private _eye = call _gunner;
        if (isNull _eye) exitWith {};
        private _cands = [];
        { _x params ["", "", "_sd", "", "_o"]; if (!isNull _o) then { _cands pushBack [_o, _sd]; }; } forEach (_eye nearTargets 6000);
        {
            _x params ["_o", "", ["_rel", ""]];
            if (!isNull _o && {(_cands findIf { (_x select 0) isEqualTo _o }) < 0}) then { _cands pushBack [_o, [sideUnknown, side group _o] select (_rel isEqualTo "enemy")]; };
        } forEach (getSensorTargets _u);
        private _dcR = ["dc"] call _fn;
        private _det = +(_u getVariable ["COMSPEC_UcavDet", []]);
        private _old = str (_det apply { [_x select 0, _x select 4, _x select 6, round (((_x select 3) select 0) / 25), round (((_x select 3) select 1) / 25), _x select 7] });
        private _now = time;
        {
            _x params ["_o", "_ps"];
            if (!alive _o || {_o isKindOf "Air"} || {!(_o isKindOf "CAManBase") && {!(_o isKindOf "LandVehicle")} && {!(_o isKindOf "Ship")}}) then { continue };
            private _crewOk = if (_o isKindOf "CAManBase") then { !captive _o } else { ({alive _x} count (crew _o)) > 0 };
            if (!_crewOk) then { continue };
            private _pos = getPosASL _o;
            if !(_pos inArea _zone) then { continue };
            // Identifié : camp perçu ennemi. Non identifié : listé seulement s'il est vraiment ennemi (jamais d'allié
            // ni de civil proposé à la frappe), et jamais engagé seul.
            private _ident = _ps isNotEqualTo sideUnknown && {[_side, _ps] call BIS_fnc_sideIsEnemy};
            if (!_ident && {!([_side, side group _o] call BIS_fnc_sideIsEnemy)}) then { continue };
            private _k = _o getVariable ["COMSPEC_UcavKey", ""];
            if (_k isEqualTo "") then {
                private _n = (_u getVariable ["COMSPEC_UcavKN", 0]) + 1;
                _u setVariable ["COMSPEC_UcavKN", _n];
                _k = format ["T%1", _n];
                _o setVariable ["COMSPEC_UcavKey", _k];
            };
            private _fr = ["friend", [_side, _pos, _dcR, true]] call _fn;
            private _i = _det findIf { (_x select 0) isEqualTo _k };
            if (_i < 0) then {
                private _kind = switch (true) do {
                    case (_o isKindOf "CAManBase"): { "INF" };
                    case (_o isKindOf "StaticWeapon"): { "STATIC" };
                    case (_o isKindOf "Tank" || {_o isKindOf "Wheeled_APC_F"}): { "ARM" };
                    default { "VEH" };
                };
                _det pushBack [_k, _o, getText (configOf _o >> "displayName"), _pos, _ident, _kind, "NEW", _fr, _now];
            } else {
                private _e = _det select _i;
                _e set [3, _pos];
                _e set [4, _ident || {_e select 4}];
                _e set [7, _fr];
                _e set [8, _now];
            };
        } forEach _cands;
        // Règle d'engagement. Engager librement : ennemi identifié, aucun allié ni civil dans la distance de sécurité
        // (danger close de l'appui aérien), deux frappes automatiques en file au plus ; sinon la cible est proposée.
        private _free = (_u getVariable ["COMSPEC_UcavRoe", "PROPOSE"]) isEqualTo "FREE" && {missionNamespace getVariable ["comspec_atak_native_ucav_free", true]};
        private _autoN = { _x param [6, false] } count (_u getVariable ["COMSPEC_UcavQ", []]);
        private _toQ = [];
        {
            _x params ["_k", "_o", "_lbl", "_pos", "_ident", "_kind", "_state", "_fr"];
            if (!alive _o) then {
                if (_state isNotEqualTo "DOWN") then { _x set [6, "DOWN"]; _x set [8, _now]; };
                continue
            };
            if (_state in ["NEW", "UNID"] && {_ident}) then {
                private _dist = ((_u distance _o) / 1000) toFixed 1;
                if (_free && {_fr < 0} && {_autoN < 2}) then {
                    _x set [6, "AUTO"];
                    _autoN = _autoN + 1;
                    _toQ pushBack [_o, _pos, _lbl, _k];
                    ["ENGAGE", format ["Engagement libre : %1 à %2 km, frappe lancée", _lbl, _dist], _pos] call _evt;
                } else {
                    _x set [6, "PROPOSED"];
                    ["PROPOSE", format ["Cible proposée : %1 à %2 km%3. À valider dans l'app Drone", _lbl, _dist, ["", format [" (allié à %1 m)", _fr]] select (_fr >= 0)], _pos] call _evt;
                };
            };
            if (_state isEqualTo "NEW" && {!_ident}) then { _x set [6, "UNID"]; };
        } forEach _det;
        _det = _det select { ((_x select 6) in ["QUEUED", "AUTO"] && {(_now - (_x select 8)) < 600}) || {(_now - (_x select 8)) < 180} };
        while { (count _det) > 16 } do {
            private _j = _det findIf { (_x select 6) in ["UNID", "IGNORED", "DOWN", "DONE"] };
            _det deleteAt (_j max 0);
        };
        private _new = str (_det apply { [_x select 0, _x select 4, _x select 6, round (((_x select 3) select 0) / 25), round (((_x select 3) select 1) / 25), _x select 7] });
        if (_new isNotEqualTo _old) then { _u setVariable ["COMSPEC_UcavDet", _det, true]; };
        { _x params ["_o", "_pos", "_lbl", "_k"]; [_o, _pos, "", format ["%1 (détection %2)", _lbl, _k], "Rechercher et tuer", true] call _enqueue; } forEach _toQ;
    };
    // File de frappes, une après l'autre (script lancé par _startRun), puis retour à l'orbite ou à la zone.
    case "run": {
        while { alive _u && {local _u} } do {
            private _q = _u getVariable ["COMSPEC_UcavQ", []];
            if ((count _q) isEqualTo 0) exitWith {};
            if ((_u getVariable ["COMSPEC_UcavMode", ""]) in ["", "MANUAL"]) exitWith {};
            private _job = +(_q select 0);
            [_u, "job", _job] call comspec_atak_native_fnc_ucavCmd;
            [_job select 0] call _qDel;
            sleep 1;
        };
        _u setVariable ["COMSPEC_UcavFiring", ""];
        if (alive _u && {local _u} && {!((_u getVariable ["COMSPEC_UcavMode", ""]) in ["", "MANUAL"])}) then { call _resume; };
    };
    case "job": {
        _args params ["_id", ["_obj", objNull], ["_aim", []], ["_wpn", ""], ["_lbl", "cible"], ["_byN", ""], ["_auto", false]];
        if !(_obj isEqualType objNull) then { _obj = objNull; };
        private _isObj = !isNull _obj;
        private _inQ = { ((_u getVariable ["COMSPEC_UcavQ", []]) findIf { (_x select 0) isEqualTo _id }) >= 0 };
        private _tpos = { if (_isObj && {alive _obj}) then { getPosASL _obj } else { _aim } };
        private _fail = {
            params ["_t"];
            ["FAIL", _t, _aim, _id] call _st;
            ["FAIL", _t, _aim] call _evt;
            [_obj, "PROPOSED"] call _detSet;
        };
        private _ws = (["weapons", _u] call _fn) select { (_x select 2) > 0 };
        private _wi = _ws findIf { (_x select 0) == _wpn };
        if (_wi < 0) then {
            private _pref = ["BOMB", "MISSILE"] select (_isObj && {!(_obj isKindOf "CAManBase")} && {!(_obj isKindOf "StaticWeapon")});
            _wi = _ws findIf { (_x select 4) isEqualTo _pref };
            if (_wi < 0 && {(count _ws) > 0}) then { _wi = 0; };
        };
        if (_wi < 0) exitWith { [format ["Frappe %1 impossible : plus de munition guidée", _id]] call _fail; };
        (_ws select _wi) params ["_w", "_tur", "", "_wl", "_kind", "", "_minR", "_maxR"];
        private _g = group driver _u;
        if (!isNull _g) then { for "_i" from ((count waypoints _g) - 1) to 0 step -1 do { deleteWaypoint [_g, _i]; }; };
        private _gn = _u turretUnit _tur;
        if (isNull _gn || {!alive _gn}) then { _gn = call _gunner; };
        // Point sans objet (grille, laser, marqueur) : cible laser posée sur le point, retirée après la frappe.
        private _side = ["sideOf", _u] call _fn;
        private _helper = objNull;
        if (!_isObj || {!alive _obj}) then {
            private _cls = ["LaserTargetW", "LaserTargetE"] select (_side isEqualTo east);
            if (isClass (configFile >> "CfgVehicles" >> _cls)) then {
                _helper = createVehicle [_cls, ASLToAGL _aim, [], 0, "CAN_COLLIDE"];
                _helper setPosASL _aim;
            };
        };
        private _t0 = time;
        private _res = "";
        private _sentP = call _tpos;
        private _runOut = [];
        private _inPos = false;
        ["ENROUTE", format ["en route vers %1", _lbl], _sentP, _id] call _st;
        ["STATE", format ["Frappe %1 : en route vers %2 (%3)", _id, _lbl, _wl], _sentP] call _evt;
        while { true } do {
            if (!alive _u || {!local _u} || {(_u getVariable ["COMSPEC_UcavMode", ""]) in ["", "MANUAL"]}) exitWith { _res = "ABORT"; };
            if !(call _inQ) exitWith { _res = "CANCEL"; };
            if (_isObj && {!alive _obj}) exitWith { _res = "DEAD"; };
            if ((time - _t0) > 360) exitWith { _res = "TIMEOUT"; };
            private _p = call _tpos;
            // Point visé publié s'il bouge de plus de 25 m (carte des alliés, page du contrôleur).
            if ((_sentP distance _p) > 25) then {
                _sentP = _p;
                (_u getVariable ["COMSPEC_UcavSt", []]) params [["_k", "ENROUTE"], ["_tx", ""]];
                [_k, _tx, _p, _id] call _st;
            };
            private _me = getPosASL _u;
            private _d2 = _me distance2D _p;
            private _d3 = _me distance _p;
            private _rel = abs ((((_u getRelDir (ASLToAGL _p)) + 180) mod 360) - 180);
            private _h = (_me select 2) - (_p select 2);
            private _vh = vectorMagnitude [(velocity _u) select 0, (velocity _u) select 1, 0];
            private _throw = ((_vh max 30) * sqrt (2 * (_h max 50) / 9.81)) * 0.85;
            private _ready = if (_kind isEqualTo "BOMB") then {
                _rel < 20 && {_d2 < ((_throw * 1.4) max 500)} && {_d2 > (_throw * 0.4)}
            } else {
                _rel < 40 && {_d3 < _maxR} && {_d3 > _minR} && {!(terrainIntersectASL [_me vectorAdd [0, 0, -4], _p vectorAdd [0, 0, 2]])}
            };
            if (_ready) exitWith { _res = "FIRE"; };
            if (!_inPos && {[_d3 < (_maxR * 1.3), _d2 < (_throw * 3 + 1500)] select (_kind isEqualTo "BOMB")}) then {
                _inPos = true;
                ["INPOS", format ["en position : %1", _lbl], _p, _id] call _st;
                ["STATE", format ["Frappe %1 : en position, approche finale", _id], _p] call _evt;
            };
            // Pilotage : droit sur la cible ; trop près et mal aligné (ou sous la portée min d'un missile) : dégagement à
            // 3 km puis nouvelle passe.
            if (_runOut isNotEqualTo [] && {(_u distance2D _runOut) < 700}) then { _runOut = []; };
            if (_runOut isEqualTo [] && {(_kind isEqualTo "BOMB" && {_d2 < 2500} && {_rel > 60}) || {_kind isEqualTo "MISSILE" && {_d3 < _minR}}}) then {
                _runOut = (ASLToAGL _p) getPos [3000, _p getDir _me];
            };
            _u doMove ([ASLToAGL _p, _runOut] select (_runOut isNotEqualTo []));
            _gn doWatch (ASLToAGL _p);
            sleep 1;
        };
        if (_res isNotEqualTo "FIRE") exitWith {
            if (!isNull _helper) then { deleteVehicle _helper; };
            switch (_res) do {
                case "CANCEL": { ["CANCEL", format ["frappe %1 annulée", _id], _aim, _id] call _st; [_obj, "PROPOSED"] call _detSet; };
                case "DEAD": { ["BDA", format ["%1 déjà détruit", _lbl], _aim, _id] call _st; ["STATE", format ["Frappe %1 abandonnée : %2 déjà détruit", _id, _lbl]] call _evt; [_obj, "DOWN"] call _detSet; };
                case "TIMEOUT": { [format ["Frappe %1 abandonnée : enveloppe de tir non atteinte en 6 min", _id]] call _fail; };
                default {};
            };
        };
        // Tir : cible révélée et annoncée à la liaison de données, servant pointé, arme choisie.
        private _tgt = [_helper, _obj] select (_isObj && {alive _obj});
        private _p = call _tpos;
        ["LOCK", format ["verrouillé sur %1", _lbl], _p, _id] call _st;
        ["LOCK", format ["Frappe %1 : verrouillé sur %2 (%3)", _id, _lbl, _wl], _p] call _evt;
        if (!isNull _tgt) then {
            _gn reveal [_tgt, 4];
            (group _gn) reveal [_tgt, 4];
            _side reportRemoteTarget [_tgt, 120];
            _tgt confirmSensorTarget [_side, true];
            _gn doWatch _tgt;
            _gn doTarget _tgt;
        } else { _gn doWatch (ASLToAGL _p); };
        _u selectWeaponTurret [_w, _tur];
        sleep 1.5;
        // Dernière chance d'annuler : après, le tir part.
        if (!(call _inQ) || {!alive _u}) exitWith {
            if (!isNull _helper) then { deleteVehicle _helper; };
            ["CANCEL", format ["frappe %1 annulée", _id], _aim, _id] call _st;
            [_obj, "PROPOSED"] call _detSet;
        };
        _u setVariable ["COMSPEC_UcavFiring", _id];
        if (isNil { _u getVariable "COMSPEC_UcavFiredEh" }) then {
            _u setVariable ["COMSPEC_UcavFiredEh", _u addEventHandler ["Fired", {
                params ["_v", "_wf", "", "", "", "", "_proj"];
                private _want = _v getVariable ["COMSPEC_UcavWantW", ""];
                if (_want isNotEqualTo "" && {_wf == _want}) then { _v setVariable ["COMSPEC_UcavShot", _proj]; _v setVariable ["COMSPEC_UcavWantW", ""]; };
            }]];
        };
        _u setVariable ["COMSPEC_UcavShot", objNull];
        _u setVariable ["COMSPEC_UcavWantW", _w];
        private _wait = {
            params ["_sec"];
            private _end = time + _sec;
            waitUntil { sleep 0.1; !isNull (_u getVariable ["COMSPEC_UcavShot", objNull]) || {time > _end} };
            !isNull (_u getVariable ["COMSPEC_UcavShot", objNull])
        };
        private _how = "";
        if (!isNull _tgt) then { _u fireAtTarget [_tgt, _w]; };
        if ([2] call _wait) then { _how = "fireAtTarget"; } else {
            (group _gn) setCombatMode "RED";
            if (!isNull _tgt) then { _gn doTarget _tgt; };
            [_u, _w, _tur] call BIS_fnc_fire;
            if ([2] call _wait) then { _how = "BIS_fnc_fire"; } else {
                private _md = (getArray (configFile >> "CfgWeapons" >> _w >> "modes")) param [0, "this"];
                if (_md isEqualTo "this") then { _md = _w; };
                _gn forceWeaponFire [_w, _md];
                if ([2] call _wait) then { _how = "forceWeaponFire"; };
            };
            (group _gn) setCombatMode "BLUE";
        };
        _u setVariable ["COMSPEC_UcavWantW", ""];
        private _proj = _u getVariable ["COMSPEC_UcavShot", objNull];
        if (isNull _proj) exitWith {
            _u setVariable ["COMSPEC_UcavFiring", ""];
            if (!isNull _helper) then { deleteVehicle _helper; };
            ["WARNING", "UCAV", format ["%1 : %2 n'a pas tiré (frappe %3)", _nm, _w, _id]] call comspec_atak_native_fnc_log;
            [format ["Frappe %1 : %2 n'a pas tiré (cible hors du champ de l'arme), redonnez l'ordre", _id, _wl]] call _fail;
        };
        // Munition accrochée à la cible, verrou ou non : objet suivi, sinon le point.
        if (_isObj && {alive _obj}) then { _proj setMissileTarget _obj; } else { _proj setMissileTargetPos (ASLToATL _aim); };
        ["INFO", "UCAV", format ["%1 : tir %2 (%3) par %4", _nm, _id, _w, _how]] call comspec_atak_native_fnc_log;
        ["FIRE", format ["tir : %1 vers %2", _wl, _lbl], call _tpos, _id] call _st;
        ["FIRE", format ["Frappe %1 : tir, %2 vers %3", _id, _wl, _lbl], call _tpos] call _evt;
        private _last = getPosASL _proj;
        private _tE = time + 90;
        while { !isNull _proj && {alive _proj} && {time < _tE} } do {
            _last = getPosASL _proj;
            sleep 0.05;
        };
        if (!isNull _helper) then { deleteVehicle _helper; };
        _u setVariable ["COMSPEC_UcavFiring", ""];
        private _miss = round (_last distance2D (call _tpos));
        ["IMPACT", format ["impact à %1 m du point visé", _miss], _last, _id] call _st;
        ["IMPACT", format ["Frappe %1 : impact à %2 m du point visé", _id, _miss], _last] call _evt;
        (call _gunner) doWatch (ASLToAGL _last);
        sleep 5;
        // BDA proposé par le capteur de l'UCAV, à confirmer à vue (compte rendu pré-rempli au téléphone).
        private _type = "POSITION";
        private _res2 = "SANS EFFET";
        private _ekia = { (_x distance2D _last) < 20 } count allDeadMen;
        if (_isObj) then {
            _type = ["VÉHICULE", "PERSONNEL"] select (_obj isKindOf "CAManBase");
            _res2 = switch (true) do {
                case (!alive _obj): { "DÉTRUIT" };
                case ((damage _obj) > 0.3): { "ENDOMMAGÉ" };
                case (!(_obj isKindOf "CAManBase") && {!canMove _obj || {!canFire _obj}}): { "ENDOMMAGÉ" };
                default { "SANS EFFET" };
            };
        } else {
            if (_ekia > 0) then { _res2 = "NEUTRALISÉ"; };
        };
        private _rl = (createHashMapFromArray [["DÉTRUIT", "détruit"], ["ENDOMMAGÉ", "endommagé"], ["NEUTRALISÉ", "neutralisé"], ["SANS EFFET", "sans effet"]]) getOrDefault [_res2, ""];
        ["BDA", format ["BDA à confirmer : %1 %2", _lbl, _rl], _last, _id] call _st;
        ["BDA", format ["Frappe %1, BDA à confirmer : %2 %3. Compte rendu BDA prêt (app Drone, onglet JOURNAL)", _id, _lbl, _rl], _last,
            [_type, _res2, _wl, _lbl, _last, _miss, _nm, [str _ekia, ""] select (_ekia isEqualTo 0)]] call _evt;
        [_obj, ["DONE", "DOWN"] select (_isObj && {!alive _obj})] call _detSet;
    };
};
true
