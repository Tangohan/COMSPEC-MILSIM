/*
    IA RP : ordres suivis par une IA à qui l'on parle (fn_iarpServer), exécutés là où elle est locale (serveur, hôte, HC).
      surrender  reddition (ACE, sinon geste du jeu)       stop  s'arrête et attend       free  repart (circulez)
      pos [UP | MIDDLE | DOWN]  à genoux, couché           follow [joueur]  suit le joueur (4 min, 80 m au plus)
      out  descend du véhicule                             unlock  (véhicule) déverrouillé pour la fouille
      flee [position]  s'enfuit vers ce point (confrontation, tirs, témoin)
      unhalt  fin d'un stop (temporisation, joueur parti : fn_iarpReact « halt ») : elle reprend sa route
      prone  se jette au sol (tirs proches), se relève 25 s après     look [cible]  regarde la scène 8 s (témoin)
    Tirs proches (fn_iarpReact « watch ») : IA locale ailleurs que sur le serveur (client sans tête, hôte) : FiredNear posé
    là où elle est locale, qui prévient le serveur (comspec_sse_iarpFired).
*/
["comspec_sse_iarpAiDo", {
    params [["_u", objNull], ["_cmd", ""], ["_a", []]];
    if (isNull _u || {!local _u} || {!alive _u}) exitWith {};
    if (_cmd isEqualTo "unlock") exitWith { _u lock 0; };
    _u setVariable ["comspec_sse_iarpFollow", objNull];
    switch (_cmd) do {
        case "surrender": {
            if (isClass (configFile >> "CfgPatches" >> "ace_captives")) then { ["ace_captives_setSurrendered", [_u, true], _u] call CBA_fnc_targetEvent; } else { _u action ["Surrender", _u]; };
        };
        case "stop": { doStop _u; _u disableAI "PATH"; };
        case "free": { _u enableAI "PATH"; _u setUnitPos "AUTO"; _u doFollow (leader group _u); };
        // S'enfuit vers un point (confrontation, tirs proches, témoin effrayé : fn_iarpServer, fn_iarpReact).
        case "flee": {
            if ((_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]}) exitWith {};
            _u enableAI "PATH"; _u doWatch objNull; _u setUnitPos "UP"; _u allowFleeing 1; _u forceSpeed -1;
            _u doMove (_a param [0, getPosATL _u]);
        };
        case "unhalt": {
            // Menotté ou rendu (ACE) : il reste où il est.
            if ((_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]}) exitWith {};
            _u enableAI "PATH"; _u doWatch objNull; _u setUnitPos "AUTO"; _u doFollow (leader group _u);
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
        case "follow": {
            private _l = _a param [0, objNull];
            if (isNull _l) exitWith {};
            _u enableAI "PATH";
            _u setUnitPos "UP";
            _u setVariable ["comspec_sse_iarpFollow", _l];
            [{
                params ["_args", "_h"];
                _args params ["_u", "_l", "_end"];
                if (!alive _u || {isNull _l} || {time > _end} || {(_u getVariable ["comspec_sse_iarpFollow", objNull]) isNotEqualTo _l} || {(_u distance _l) > 80}) exitWith {
                    [_h] call CBA_fnc_removePerFrameHandler;
                    if ((_u getVariable ["comspec_sse_iarpFollow", objNull]) isEqualTo _l) then { _u setVariable ["comspec_sse_iarpFollow", objNull]; doStop _u; };
                };
                if ((_u distance _l) > 3) then { _u doMove ((getPosATL _l) getPos [2, _l getDir _u]); };
            }, 2, [_u, _l, time + 240]] call CBA_fnc_addPerFrameHandler;
        };
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
