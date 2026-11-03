/*
    Contrôle d'identité : gestes locaux (toutes machines) et registre (serveur).
    Rien par image chez les clients. Serveur : l'automate des IA réalistes traite une seule IA contrôlée par image (les
    autres ne coûtent rien) et les checkpoints IA tournent sur une boucle d'une seconde ; le reste part d'une action,
    d'un évènement ou d'une mort.
*/
// Ajout / retrait d'un exemplaire là où la personne est locale (addItem, removeItem : argument local).
["comspec_sse_idLocalAdd", {
    params [["_u", objNull], ["_cls", ""]];
    if (isNull _u || {!local _u} || {_cls isEqualTo ""}) exitWith {};
    if (alive _u && {_u canAdd _cls}) exitWith { _u addItem _cls; };
    // Corps : dans son uniforme ; plein, ou sans uniforme : au sol à ses pieds.
    if (!alive _u && {(uniform _u) isNotEqualTo ""}) then { _u addItemToUniform _cls; };
    if (_cls in (items _u)) exitWith {};
    private _h = createVehicle ["GroundWeaponHolder", getPosATL _u, [], 0.3, "CAN_COLLIDE"];
    _h addItemCargoGlobal [_cls, 1];
}] call CBA_fnc_addEventHandler;
["comspec_sse_idLocalRemove", {
    params [["_u", objNull], ["_cls", ""]];
    if (isNull _u || {!local _u}) exitWith {};
    _u removeItem _cls;
}] call CBA_fnc_addEventHandler;
// IA recherchée qui s'enfuit (fn_idAiAnswer) ; occupants qu'on fait descendre (fn_idServer « dismount »).
["comspec_sse_idAiFlee", {
    params [["_u", objNull], ["_to", []]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    _u allowFleeing 1;
    _u setUnitPos "UP";
    (group _u) setSpeedMode "FULL";
    _u doMove _to;
}] call CBA_fnc_addEventHandler;
["comspec_sse_idMoveOut", {
    params [["_u", objNull]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    [_u] allowGetIn false;
    unassignVehicle _u;
    moveOut _u;
}] call CBA_fnc_addEventHandler;

// IA réalistes (automate fn_idAiFsm, checkpoints IA) : ordres exécutés là où l'IA est locale (serveur, client hôte, HC).
["comspec_sse_idAiDo", {
    params [["_u", objNull], ["_cmd", ""], ["_a", []]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    switch (_cmd) do {
        case "stop": { doStop _u; _u disableAI "PATH"; };
        case "free": { _u enableAI "PATH"; _u doWatch objNull; _u setUnitPos "AUTO"; _u allowFleeing 0.5; _u doFollow (leader group _u); };
        case "watch": { _u doWatch (_a param [0, objNull]); };
        case "pos": { _u setUnitPos (_a param [0, "AUTO"]); };
        case "gesture": { _u playActionNow (_a param [0, ""]); };
        // Regard de côté (vers une issue), puis retour sur le contrôleur.
        case "glance": {
            private _t = _a param [0, objNull];
            _u doWatch ((getPosATL _u) getPos [25, (_u getDir _t) + 120 + random 120]);
            [{ params ["_u", "_t"]; if (alive _u && {!isNull _t}) then { _u doWatch _t; }; }, [_u, _t], 1.2 + random 1] call CBA_fnc_waitAndExecute;
        };
        case "flee": {
            _u enableAI "PATH"; _u doWatch objNull; _u allowFleeing 1; _u setUnitPos "UP";
            (group _u) setSpeedMode "FULL"; _u forceSpeed -1;
            _u doMove (_a param [0, getPosATL _u]);
        };
        // Insurgé caché parmi les civils ou camp ami : il passe dans un groupe du camp hostile pour pouvoir tirer.
        case "hostile": {
            _a params [["_side", east], ["_targets", []]];
            _u enableAI "PATH"; _u allowFleeing 0; _u setUnitPos "AUTO";
            private _t0 = _targets param [0, objNull];
            if (!isNull _t0 && {!([side group _u, side group _t0] call BIS_fnc_sideIsEnemy)}) then {
                private _g = createGroup [_side, true];
                if (!isNull _g) then { [_u] joinSilent _g; };
            };
            (group _u) setBehaviour "COMBAT"; (group _u) setCombatMode "RED";
            { _u reveal [_x, 4]; } forEach _targets;
            if (!isNull _t0) then { _u doTarget _t0; };
        };
        case "converge": {
            _a params [["_pos", getPosATL _u], ["_targets", []]];
            _u enableAI "PATH";
            if ((behaviour _u) in ["CARELESS", "SAFE"]) then { (group _u) setBehaviour "AWARE"; };
            { (group _u) reveal [_x, 2.5]; } forEach _targets;
            _u doMove _pos;
        };
        case "approach": {
            private _t = _a param [0, objNull];
            if (isNull _t) exitWith {};
            _u enableAI "PATH"; _u doWatch _t;
            _u doMove ((getPosATL _t) getPos [2, _t getDir _u]);
        };
        case "post": {
            private _p = _u getVariable ["comspec_sse_idPost", []];
            _u doWatch objNull; _u doTarget objNull;
            if (_p isNotEqualTo []) then { _u doMove _p; };
        };
        case "aim": { private _t = _a param [0, objNull]; _u doWatch _t; _u doTarget _t; };
        case "alert": { (group _u) setBehaviour "COMBAT"; { (group _u) reveal [_x, 4]; } forEach (_a param [0, []]); };
        case "fire": {
            private _t = _a param [0, objNull];
            (group _u) setBehaviour "COMBAT"; (group _u) setCombatMode "RED";
            _u reveal [_t, 4]; _u doTarget _t; _u doFire _t;
        };
        case "hold": { _u allowFleeing 0; doStop _u; };
    };
}] call CBA_fnc_addEventHandler;
// Checkpoint IA qui ouvre le feu sur un joueur qui n'est pas ennemi de son camp : il devient hostile (là où il est local).
["comspec_sse_idLocalRating", {
    params [["_u", objNull], ["_v", 0]];
    if (isNull _u || {!local _u}) exitWith {};
    _u addRating _v;
}] call CBA_fnc_addEventHandler;
// Infiltré (fn_idCover) : captif tant que sa couverture tient (setCaptive là où il est local).
["comspec_sse_idLocalCaptive", {
    params [["_u", objNull], ["_v", false]];
    if (isNull _u || {!local _u}) exitWith {};
    _u setCaptive _v;
}] call CBA_fnc_addEventHandler;

if (!isServer) exitWith {};
["init"] call comspec_sse_fnc_idRegistry;
// IA réalistes : automate CBA (une unité contrôlée par image) et checkpoints IA (une boucle d'une seconde).
["init"] call comspec_sse_fnc_idAiFsm;
["init"] call comspec_sse_fnc_idCheckpoint;
// Compte rendu AAR : une personne contrôlée puis menottée = interpellation.
["ace_captiveStatusChanged", {
    params [["_u", objNull], ["_state", false], ["_reason", ""], ["_by", objNull]];
    if (!_state || {_reason isNotEqualTo "SetHandcuffed"} || {isNull _u}) exitWith {};
    private _c = ((missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) getOrDefault ["controlled", createHashMap]) getOrDefault [netId _u, []];
    if (_c isEqualTo [] || {(time - (_c select 0)) > 1800}) exitWith {};
    _c params ["", ["_ctrlName", ""], ["_lbl", ""]];
    ["add", ["INTERPELLATION", format ["%1 menotté au %2%3 (contrôlé par %4)", [_lbl, "Personne contrôlée"] select (_lbl isEqualTo ""), mapGridPosition _u,
        ["", format [" par %1", name _by]] select (!isNull _by), _ctrlName], format ["cuff|%1", netId _u]]] call comspec_sse_fnc_idAar;
}] call CBA_fnc_addEventHandler;
// HVT (fn_idHvt) : capture (menottes ACE) = JACKPOT, exploitation SSE ouverte. Infiltré menotté : couverture brûlée.
["ace_captiveStatusChanged", {
    params [["_u", objNull], ["_state", false], ["_reason", ""], ["_by", objNull]];
    if (!_state || {_reason isNotEqualTo "SetHandcuffed"} || {isNull _u}) exitWith {};
    ["jackpot", [_u, "CAPTURÉ", _by]] call comspec_sse_fnc_idHvt;
    if (_u getVariable ["comspec_sse_idCoverOn", false]) then { ["burn", [_u, "menotté"]] call comspec_sse_fnc_idCover; };
}] call CBA_fnc_addEventHandler;
// Paquet Athena (documents délivrés, avis de recherche) : une fois la liaison établie.
[{ ["load"] call comspec_sse_fnc_idAthenaPack; }, [], 15] call CBA_fnc_waitAndExecute;
// Mort : les papiers (encore virtuels) deviennent des objets sur le corps, trouvés par la fouille ou l'inventaire.
addMissionEventHandler ["EntityKilled", {
    params ["_u"];
    if (!(_u isKindOf "CAManBase") || {!(missionNamespace getVariable ["comspec_sse_id_enabled", true])}) exitWith {};
    // HVT neutralisée : JACKPOT (chasse HVT de l'ATAK, compte rendu, Athena).
    ["jackpot", [_u, "EKIA", _this param [1, objNull]]] call comspec_sse_fnc_idHvt;
    // Joueur : ses papiers sont déjà des objets ; pas de papiers « d'IA » tirés sur son corps.
    if (isPlayer _u || {(getPlayerUID _u) isNotEqualTo ""}) exitWith {};
    [{
        params ["_u"];
        if (isNull _u) exitWith {};
        { [_x, _u] call comspec_sse_fnc_idMaterialize; } forEach (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry);
    }, [_u], 1] call CBA_fnc_waitAndExecute;
}];
