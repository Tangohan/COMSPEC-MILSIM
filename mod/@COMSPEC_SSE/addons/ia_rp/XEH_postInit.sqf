/*
    IA RP : ordres suivis par une IA à qui l'on parle (fn_iarpServer), exécutés là où elle est locale (serveur, hôte, HC).
      surrender  reddition (ACE, sinon geste du jeu)       stop  s'arrête et attend       free  repart (circulez)
      pos [UP | MIDDLE | DOWN]  à genoux, couché           follow [joueur]  suit le joueur jusqu'à ce qu'on la relâche (fn_iarpFollow)
      out  descend du véhicule                             unlock  (véhicule) déverrouillé pour la fouille
      flee [position]  s'enfuit vers ce point (confrontation, tirs, témoin)
      unhalt  fin d'un stop (temporisation, joueur parti : fn_iarpReact « halt ») : elle reprend sa route
      prone  se jette au sol (tirs proches), se relève 25 s après     look [cible]  regarde la scène 8 s (témoin)
      back [position] · come [position]  recule, s'approche (« Reculez », « Approchez »)     turn [direction]  se retourne
      drop  pose son arme au sol (« Lâchez votre arme »), dans un conteneur au sol que tout le monde peut ramasser
    Tirs proches (fn_iarpReact « watch ») : IA locale ailleurs que sur le serveur (client sans tête, hôte) : FiredNear posé
    là où elle est locale, qui prévient le serveur (comspec_sse_iarpFired).
    Suivi en cours (fn_iarpFollow) : seul un ordre qui la déplace ou l'immobilise y met fin ; un regard (look), un demi-tour
    (turn) ou une arme posée (drop) ne l'annulent plus. « Reculez » et « Approchez » ne laissent plus l'IA bridée au pas :
    free, unhalt et follow lui rendent son allure (forceSpeed -1).
*/
["comspec_sse_iarpAiDo", {
    params [["_u", objNull], ["_cmd", ""], ["_a", []]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    if (_cmd isEqualTo "unlock") exitWith { _u lock 0; };
    if !(_cmd in ["follow", "look", "turn", "drop"]) then { ["stop", [_u, _cmd]] call comspec_sse_fnc_iarpFollow; };
    // Bagarre en cours (fn_iarpMelee) : un ordre qui l'immobilise, la rend ou la fait partir y met fin.
    if (_cmd in ["stop", "surrender", "free", "flee", "pos", "out", "follow"]) then { ["stop", [_u, "order"]] call comspec_sse_fnc_iarpMelee; };
    switch (_cmd) do {
        case "surrender": {
            if (isClass (configFile >> "CfgPatches" >> "ace_captives")) then { ["ace_captives_setSurrendered", [_u, true], _u] call CBA_fnc_targetEvent; } else { _u action ["Surrender", _u]; };
        };
        case "stop": { doStop _u; _u disableAI "PATH"; };
        case "free": { _u enableAI "PATH"; _u forceSpeed -1; _u setUnitPos "AUTO"; _u doFollow (leader group _u); };
        // S'enfuit vers un point (confrontation, tirs proches, témoin effrayé : fn_iarpServer, fn_iarpReact).
        case "flee": {
            if ((_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]}) exitWith {};
            _u enableAI "PATH"; _u doWatch objNull; _u setUnitPos "UP"; _u allowFleeing 1; _u forceSpeed -1;
            _u doMove (_a param [0, getPosATL _u]);
        };
        case "unhalt": {
            // Menotté ou rendu (ACE) : il reste où il est.
            if ((_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]}) exitWith {};
            _u enableAI "PATH"; _u forceSpeed -1; _u doWatch objNull; _u setUnitPos "AUTO"; _u doFollow (leader group _u);
        };
        case "pos": { _u setUnitPos (_a param [0, "MIDDLE"]); };
        case "prone": {
            _u setUnitPos "DOWN";
            [{ params ["_u"]; if (alive _u && {(unitPos _u) isEqualTo "Down"}) then { _u setUnitPos "AUTO"; }; }, [_u], 25] call CBA_fnc_waitAndExecute;
        };
        case "look": {
            private _t = _a param [0, objNull];
            if (isNull _t) exitWith {};
            _u doWatch _t;
            [{ params ["_u"]; if (alive _u) then { _u doWatch objNull; }; }, [_u], 8] call CBA_fnc_waitAndExecute;
        };
        case "out": { [_u] allowGetIn false; unassignVehicle _u; moveOut _u; };
        case "back";
        case "come": {
            if (_u getVariable ["ace_captives_isHandcuffed", false]) exitWith {};
            _u enableAI "PATH"; _u forceSpeed 1.5;
            _u doMove (_a param [0, getPosATL _u]);
        };
        case "turn": { _u doWatch objNull; _u setFormDir (_a param [0, getDir _u]); _u setDir (_a param [0, getDir _u]); };
        case "drop": {
            private _w = primaryWeapon _u;
            if (_w isEqualTo "") then { _w = handgunWeapon _u; };
            if (_w isEqualTo "") exitWith {};
            private _h = createVehicle ["GroundWeaponHolder", getPosATL _u, [], 0.5, "CAN_COLLIDE"];
            _u action ["DropWeapon", _h, _w];
        };
        case "follow": { ["start", [_u, _a param [0, objNull]]] call comspec_sse_fnc_iarpFollow; };
    };
}] call CBA_fnc_addEventHandler;

// Tirs proches d'une IA suivie (fn_iarpReact) : gestionnaire posé là où elle est locale quand ce n'est pas le serveur.
["comspec_sse_iarpWatchLocal", {
    params [["_u", objNull]];
    if (isNull _u || {!local _u} || {isServer} || {(_u getVariable ["comspec_sse_iarpFnEhLocal", -1]) >= 0}) exitWith {};
    _u setVariable ["comspec_sse_iarpFnEhLocal", _u addEventHandler ["FiredNear", {
        params ["_u", "_firer", ["_d", 0]];
        if (_d <= 69 && {time > ((_u getVariable ["comspec_sse_iarpFnSent", -100]) + 4)}) then {
            _u setVariable ["comspec_sse_iarpFnSent", time];
            ["comspec_sse_iarpFired", [_u, _firer, _d]] call CBA_fnc_serverEvent;
        };
    }]];
}] call CBA_fnc_addEventHandler;
if (isServer) then {
    ["comspec_sse_iarpFired", { ["fired", _this] call comspec_sse_fnc_iarpReact; }] call CBA_fnc_addEventHandler;
};

// Agressivité des IA (fn_iarpAggro, serveur) : bagarre et gestes là où l'IA est locale, coup reçu chez le joueur touché,
// comptes rendus au serveur ; un contrôle de papiers par l'action du menu (identity : comspec_sse_idAsked) compte
// comme le même contrôle demandé en texte.
["comspec_sse_iarpMelee", { _this call comspec_sse_fnc_iarpMelee; }] call CBA_fnc_addEventHandler;
["comspec_sse_iarpMeleeHit", { ["hit", _this] call comspec_sse_fnc_iarpMelee; }] call CBA_fnc_addEventHandler;
if (isServer) then {
    ["comspec_sse_iarpAggroEv", { _this call comspec_sse_fnc_iarpAggro; }] call CBA_fnc_addEventHandler;
    ["comspec_sse_idAsked", {
        params [["_t", objNull], ["_me", objNull]];
        if (!isNull _t && {!isPlayer _t} && {!isNull _me}) then {
            private _r = ["trigger", [_t, _me, "papers"]] call comspec_sse_fnc_iarpAggro;
            if ((_r param [2, false]) && {(_r param [1, ""]) in ["irritated", "hostile"]}) then { ["speak", [_t, _r select 1, "papers"]] call comspec_sse_fnc_iarpAggro; };
        };
    }] call CBA_fnc_addEventHandler;
};
