/*
    Une IA suit un joueur (« Suivez-moi », fn_iarpServer) jusqu'à ce qu'on la relâche. Exécuté là où l'IA est locale
    (serveur, hôte, client sans tête : évènement comspec_sse_iarpAiDo « follow ») ; l'état est gardé sur l'unité.
      ["cfg"] -> HashMap de data\iarp_follow.sqf (lue une fois)
      ["step", [distance, vitesse du joueur km/h, en mouvement, cfg]] -> [avancer, allure]      PUR (table, sans unité)
      ["block", unité] -> "" | "dead" | "cuffed" | "uncon"     ce qui l'empêche de marcher seule (variables ACE diffusées)
      ["start", [ia, joueur]] · ["stop", [ia, raison]] · ["tick", [ia, poignée]]               là où l'IA est locale
      ["leader", ia] -> joueur suivi (objNull sinon) : variable diffusée comspec_sse_iarpFollowing (état « Suit le joueur »)
    Boucle (1 s) : reste à 2-4 m, marche, trottine ou court selon la distance et l'allure du joueur, s'arrête quand il
    s'arrête, ne redonne un ordre de déplacement que si le point visé a bougé (un doMove répété à chaque passage
    relance le calcul du chemin et fige l'IA), vise le joueur lui-même si elle reste bloquée. Elle rétablit le calcul
    du chemin si un autre système l'a coupé (« stop » de l'automate des IA réalistes : doStop et disableAI "PATH").
    Fin : « Reste ici », « Circulez », tout autre ordre de déplacement, mort de l'un des deux, joueur parti du
    serveur, joueur perdu de vue (150 m pendant 45 s), fuite ou hostilité décidée par l'automate. En pause, sans
    abandonner : menottée (escorte ACE), mains en l'air, inconsciente, dans un véhicule.
    Groupe d'une seule IA : son allure de groupe est réglée (rendue à la fin) ; IA d'un groupe : marche forcée seulement.
*/
params [["_op", "", [""]], ["_a", []]];
private _cfg = {
    private _c = missionNamespace getVariable ["comspec_sse_iarpFollowCfg", 0];
    if (_c isEqualType createHashMap) exitWith { _c };
    private _raw = call compile preprocessFileLineNumbers "\z\comspec_sse\addons\ia_rp\data\iarp_follow.sqf";
    _c = createHashMapFromArray ([[], _raw] select (!isNil "_raw" && {_raw isEqualType []}));
    missionNamespace setVariable ["comspec_sse_iarpFollowCfg", _c];
    _c
};
private _restrained = { params ["_u"]; (_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]} };
private _uncon = { params ["_u"]; (_u getVariable ["ACE_isUnconscious", false]) || {(lifeState _u) isEqualTo "INCAPACITATED"} };

switch (_op) do {
    case "cfg": { call _cfg };

    case "step": {
        _a params [["_d", 0, [0]], ["_ls", 0, [0]], ["_moving", false, [false]], ["_c", 0]];
        if !(_c isEqualType createHashMap) then { _c = call _cfg; };
        private _mv = _d > ([_c getOrDefault ["startAt", 4], _c getOrDefault ["stopAt", 2.5]] select _moving);
        private _pace = "walk";
        { _x params ["_dm", "_sm", "_p"]; if (_d >= _dm || {_ls >= _sm}) exitWith { _pace = _p; }; } forEach (_c getOrDefault ["pace", []]);
        [_mv, _pace]
    };

    case "block": {
        private _u = _a;
        if !(_u isEqualType objNull) exitWith { "dead" };
        if (isNull _u || {!alive _u}) exitWith { "dead" };
        if (_u getVariable ["ace_captives_isHandcuffed", false]) exitWith { "cuffed" };
        if ([_u] call _uncon) exitWith { "uncon" };
        ""
    };

    case "leader": {
        if (_a isEqualType objNull) then { _a getVariable ["comspec_sse_iarpFollowing", objNull] } else { objNull }
    };

    case "start": {
        _a params [["_u", objNull, [objNull]], ["_l", objNull, [objNull]]];
        if (isNull _u || {!local _u} || {!alive _u} || {isNull _l} || {_u isEqualTo _l}) exitWith { false };
        if ((_u getVariable ["comspec_sse_iarpFollowSt", []]) isNotEqualTo []) then { ["stop", [_u, "restart"]] call comspec_sse_fnc_iarpFollow; };
        private _g = group _u;
        private _solo = (count (units _g)) isEqualTo 1;
        _u enableAI "PATH";
        _u enableAI "MOVE";
        _u forceSpeed -1;
        _u setUnitPos "UP";
        _u doWatch objNull;
        private _h = [{ params ["_u", "_h"]; ["tick", [_u, _h]] call comspec_sse_fnc_iarpFollow; }, (call _cfg) getOrDefault ["tick", 1], _u] call CBA_fnc_addPerFrameHandler;
        // [joueur, en mouvement, allure, dernier point visé, heure du dernier ordre, bloquée depuis, perdu depuis,
        //  allure du groupe avant, groupe d'une seule IA, poignée]
        _u setVariable ["comspec_sse_iarpFollowSt", [_l, false, "", [0, 0, 0], -100, -1, -1, speedMode _g, _solo, _h]];
        _u setVariable ["comspec_sse_iarpFollow", _l];
        _u setVariable ["comspec_sse_iarpFollowing", _l, true];
        true
    };

    case "stop": {
        _a params [["_u", objNull, [objNull]], ["_why", "", [""]]];
        if (isNull _u) exitWith { false };
        private _s = _u getVariable ["comspec_sse_iarpFollowSt", []];
        if (_s isEqualTo []) exitWith { false };
        [_s select 9] call CBA_fnc_removePerFrameHandler;
        _u setVariable ["comspec_sse_iarpFollowSt", []];
        _u setVariable ["comspec_sse_iarpFollow", objNull];
        _u setVariable ["comspec_sse_iarpFollowing", objNull, true];
        if (local _u && {alive _u}) then {
            _u forceWalk false;
            _u forceSpeed -1;
            _u doWatch objNull;
            if (_s select 8) then { (group _u) setSpeedMode (_s select 7); };
            // Joueur parti ou perdu de vue : elle s'arrête là. Autre ordre (stop, circulez, fuite…) : c'est lui qui décide.
            if (_why in ["gone", "lost"]) then { doStop _u; _u setUnitPos "AUTO"; };
        };
        true
    };

    case "tick": {
        _a params [["_u", objNull, [objNull]], ["_h", -1, [0]]];
        if (isNull _u) exitWith { [_h] call CBA_fnc_removePerFrameHandler; };
        private _s = _u getVariable ["comspec_sse_iarpFollowSt", []];
        if (_s isEqualTo [] || {(_s select 9) isNotEqualTo _h}) exitWith { [_h] call CBA_fnc_removePerFrameHandler; };
        _s params ["_l", "_moving", "_pace", "_last", "_at", "_stall", "_lost", "", "_solo"];
        if (!alive _u || {isNull _l} || {!alive _l}) exitWith { ["stop", [_u, "gone"]] call comspec_sse_fnc_iarpFollow; };
        // L'IA a changé de machine (client sans tête, Zeus) : la boucle reprend là où elle est locale.
        if (!local _u) exitWith {
            [_h] call CBA_fnc_removePerFrameHandler;
            _u setVariable ["comspec_sse_iarpFollowSt", []];
            ["comspec_sse_iarpAiDo", [_u, "follow", [_l]], _u] call CBA_fnc_targetEvent;
        };
        // L'automate des IA réalistes l'a reprise (fuite, hostilité, renforts), ou elle se bat : le suivi s'arrête.
        private _fsm = "";
        if (isServer && {!isNil "comspec_sse_fnc_idAiFsm"}) then {
            _fsm = ["state", [_u]] call comspec_sse_fnc_idAiFsm;
            if (isNil "_fsm" || {!(_fsm isEqualType "")}) then { _fsm = ""; };
        };
        if (_fsm in ["fuit", "agressif", "renforts"] || {(behaviour _u) isEqualTo "COMBAT"} || {fleeing _u}) exitWith {
            ["stop", [_u, "taken"]] call comspec_sse_fnc_iarpFollow;
        };
        // En pause : menottée (escorte ACE), mains en l'air, inconsciente, dans un véhicule.
        if (([_u] call _restrained) || {[_u] call _uncon} || {!isNull objectParent _u}) exitWith { _s set [1, false]; _s set [5, -1]; };
        private _c = call _cfg;
        private _lv = vehicle _l;
        private _d = _u distance _lv;
        if (_d > (_c getOrDefault ["lostAt", 150])) then {
            if (_lost < 0) then { _lost = time; _s set [6, _lost]; };
        } else {
            _lost = -1;
            _s set [6, -1];
        };
        if (_lost >= 0 && {(time - _lost) > (_c getOrDefault ["lostAfter", 45])}) exitWith { ["stop", [_u, "lost"]] call comspec_sse_fnc_iarpFollow; };
        // Chemin coupé par un autre système (« stop » d'une interpellation) : rétabli, sinon aucun ordre ne la fait avancer.
        if !(_u checkAIFeature "PATH") then { _u enableAI "PATH"; };
        if !(_u checkAIFeature "MOVE") then { _u enableAI "MOVE"; };
        (["step", [_d, abs (speed _lv), _moving, _c]] call comspec_sse_fnc_iarpFollow) params ["_mv", "_p"];
        if (_p isNotEqualTo _pace) then {
            _s set [2, _p];
            _u forceSpeed -1;
            _u forceWalk (_p isEqualTo "walk");
            if (_solo) then { (group _u) setSpeedMode (["NORMAL", "FULL"] select (_p isEqualTo "run")); };
        };
        if (!_mv) exitWith {
            if (_moving) then { _s set [1, false]; _s set [5, -1]; _u doWatch _l; };
        };
        if (!_moving) then { _u doWatch objNull; _s set [1, true]; _at = -100; };
        private _lp = getPosATL _lv;
        private _dir = (getPosATL _u) vectorDiff _lp;
        _dir set [2, 0];
        private _tp = if ((vectorMagnitude _dir) < 0.1) then { _lp } else { _lp vectorAdd ((vectorNormalized _dir) vectorMultiply (_c getOrDefault ["keep", 2])) };
        // Bloquée (porte, obstacle, chemin perdu) : immobile alors qu'elle devrait avancer ; elle vise le joueur lui-même.
        if ((abs (speed _u)) < 1) then {
            if (_stall < 0) then { _stall = time; _s set [5, _stall]; };
        } else {
            _stall = -1;
            _s set [5, -1];
        };
        private _stuck = _stall >= 0 && {(time - _stall) > (_c getOrDefault ["stallAfter", 6])};
        if (_stuck) then { _tp = _lp; _s set [5, -1]; };
        private _due = (time - _at) >= (_c getOrDefault ["minOrder", 1.5]);
        if (_stuck || {_due && {(_tp distance _last) > (_c getOrDefault ["replan", 1.5]) || {unitReady _u}}}) then {
            _u doMove _tp;
            _s set [3, _tp];
            _s set [4, time];
        };
    };
};
