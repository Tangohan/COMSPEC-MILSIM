/*
    Bagarre d'une IA contre un joueur (fn_iarpAggro « fight »), là où l'IA est locale : évènement comspec_sse_iarpMelee.
      ["start", [ia, joueur, options]]   approche puis coups ; options (paires) : kind shove | punch, intensity 0-100, lethal,
                                         ko, backend tsp | sse, tspAnim, hitBonus, maxSwings
      ["tick", [ia, poignée]]            boucle (0,25 s)          ["stop", [ia, raison]]   fin, compte rendu au serveur
      ["taunt", [ia, gestes TSP]]        geste de défi d'une IA qui insulte
      ["hit", [joueur, ia, geste, dégâts, recul, assommé, mortel, sonné]]   chez le joueur touché (comspec_sse_iarpMeleeHit)
      ["gesture", [liste d'actions]] -> première action qui existe dans la configuration du jeu ("" : aucune)   PUR
    Deux moteurs :
      tsp   TSP Melee chargé (CfgPatches « tsp_melee »), activé chez lui (tsp_cba_melee), IA à mains nues, joueur visé
            le plus proche de l'IA : le coup est donné par [ia, mode, vrai] spawn tsp_fnc_melee_action (modes « push »
            pour une bousculade, « main » / « alt » pour un coup de poing). Animations, sons, parade, dégâts, sonné et
            assommé sont ceux de TSP Melee et de ses réglages : le SSE n'ajoute AUCUN dégât. Les plafonds du SSE jouent
            en arrêtant la bagarre (dégâts estimés d'après CfgMelee, comptés sur l'évènement tsp_melee_damageMan).
      sse   intégré : court élan (setVelocityModelSpace), geste (TSP Melee s'il est chargé : tsp_melee_fistPunchRight,
            tsp_melee_fistPunchLeft, tsp_melee_fistPush ; sinon signes de la main du jeu, faute d'animation de coup de
            poing dans Arma 3), tirage de la touche, puis chez le joueur : recul, dégâts (ace_medical_fnc_addDamageToUnit
            avec ACE, sinon setDamage), secousse de caméra, son de coup du jeu. Dégâts calculés par fn_iarpAggro « damage ».
    Fin : coups épuisés, joueur à terre, parti ou monté en véhicule, trop loin, pas rejoint à temps, plafond de dégâts
    atteint, « calme » du Zeus, IA menottée, rendue, inconsciente ou morte, autre ordre. Aucune fenêtre d'alerte du jeu.
*/
params [["_op", "", [""]], ["_a", []]];
private _restrained = { params ["_u"]; (_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]} };
private _uncon = { params ["_u"]; (_u getVariable ["ACE_isUnconscious", false]) || {(lifeState _u) isEqualTo "INCAPACITATED"} };
// Première action de geste qui existe vraiment (addon absent ou classe renommée : la suivante).
private _gesture = {
    params ["_list"];
    private _i = _list findIf { isClass (configFile >> "CfgGesturesMale" >> "States" >> _x) };
    if (_i < 0) then { "" } else { _list select _i }
};
// Dégâts reçus par ce joueur dans la dernière minute (liste diffusée sur lui : plusieurs IA, plusieurs machines).
private _taken = {
    params ["_v"];
    private _l = (_v getVariable ["comspec_sse_aggroTaken", []]) select { (time - (_x select 0)) < 60 };
    private _sum = 0;
    { _sum = _sum + (_x select 1); } forEach _l;
    [_sum, _l]
};
private _addTaken = {
    params ["_v", "_d"];
    if (_d <= 0) exitWith {};
    private _l = ([_v] call _taken) select 1;
    _l pushBack [time, _d];
    _v setVariable ["comspec_sse_aggroTaken", _l, true];
};

switch (_op) do {
    case "gesture": { [_a] call _gesture };

    case "taunt": {
        _a params [["_u", objNull, [objNull]], ["_tspAnim", true, [true]]];
        if (isNull _u || {!local _u} || {!alive _u} || {[_u] call _restrained}) exitWith {};
        // TSP Animate (CfgPatches « tsp_animate ») : doigt d'honneur, pouce en bas, « perdant » ; sinon « non » de la tête.
        private _g = "";
        if (_tspAnim && {isClass (configFile >> "CfgPatches" >> "tsp_animate")}) then {
            private _have = ["tsp_animate_bird_loop", "tsp_animate_thumbsdown_loop", "tsp_animate_loser_loop"] select { isClass (configFile >> "CfgGesturesMale" >> "States" >> _x) };
            if (_have isNotEqualTo []) then { _g = selectRandom _have; };
        };
        if (_g isEqualTo "") exitWith { _u playActionNow "gestureNo"; };
        _u playActionNow _g;
        // Geste en boucle chez TSP : rendu au bout de 2,5 s (action d'arrêt de TSP Core, sinon geste neutre du jeu).
        [{
            params ["_u", "_g"];
            if (!alive _u || {(gestureState _u) isNotEqualTo (toLower _g)}) exitWith {};
            _u playActionNow (["gestureNod", "tsp_common_stop"] select (isClass (configFile >> "CfgGesturesMale" >> "States" >> "tsp_common_stop")));
        }, [_u, _g], 2.5] call CBA_fnc_waitAndExecute;
    };

    case "start": {
        _a params [["_u", objNull, [objNull]], ["_v", objNull, [objNull]], ["_pairs", [], [[]]]];
        if (isNull _u || {!local _u} || {!alive _u} || {isNull _v}) exitWith { false };
        if ((_u getVariable ["comspec_sse_meleeSt", []]) isNotEqualTo []) exitWith { false };
        private _o = createHashMapFromArray _pairs;
        ["stop", [_u, "fight"]] call comspec_sse_fnc_iarpFollow;
        private _g = group _u;
        private _solo = (count (units _g)) isEqualTo 1;
        private _sm = speedMode _g;
        _u enableAI "PATH";
        _u enableAI "MOVE";
        _u setUnitPos "UP";
        _u forceWalk false;
        _u forceSpeed -1;
        if (_solo) then { _g setSpeedMode "FULL"; };
        // Coups de TSP Melee comptés sur son évènement (posé une fois par machine) : touche et dégâts estimés.
        if ((_o getOrDefault ["backend", "sse"]) isEqualTo "tsp" && {isNil "comspec_sse_meleeTspEh"}) then {
            comspec_sse_meleeTspEh = ["tsp_melee_damageMan", {
                params [["_att", objNull], ["_vic", objNull]];
                private _s = _att getVariable ["comspec_sse_meleeSt", []];
                if (_s isEqualTo [] || {(_s select 0) isNotEqualTo _vic}) exitWith {};
                ["tspHit", [_att, _vic]] call comspec_sse_fnc_iarpMelee;
            }] call CBA_fnc_addEventHandler;
        };
        private _h = [{ params ["_u", "_h"]; ["tick", [_u, _h]] call comspec_sse_fnc_iarpMelee; }, 0.25, _u] call CBA_fnc_addPerFrameHandler;
        // [joueur, options, début, dernier coup, coups, touches, dernier ordre de déplacement, allure d'avant, seule, poignée, occupée jusqu'à]
        _u setVariable ["comspec_sse_meleeSt", [_v, _o, time, -100, 0, 0, -100, _sm, _solo, _h, 0]];
        _u setVariable ["comspec_sse_aggroBusy", true, true];
        true
    };

    case "stop": {
        _a params [["_u", objNull, [objNull]], ["_why", "", [""]]];
        if (isNull _u) exitWith { false };
        private _s = _u getVariable ["comspec_sse_meleeSt", []];
        if (_s isEqualTo []) exitWith { false };
        _s params ["_v", "_o", "", "", "_swings", "_landed", "", "_sm", "_solo", "_h"];
        [_h] call CBA_fnc_removePerFrameHandler;
        _u setVariable ["comspec_sse_meleeSt", []];
        _u setVariable ["comspec_sse_aggroBusy", false, true];
        if (local _u && {alive _u}) then {
            _u doWatch objNull;
            if (_solo) then { (group _u) setSpeedMode _sm; };
            doStop _u;
            // Garde de TSP Melee rendue (poings levés en boucle).
            if ((_o getOrDefault ["backend", "sse"]) isEqualTo "tsp" && {!isNil "tsp_fnc_gesture_stop"}) then { [_u] call tsp_fnc_gesture_stop; };
            if (isClass (configFile >> "CfgGesturesMale" >> "States" >> "tsp_common_stop") && {"tsp_melee" in (gestureState _u)}) then { _u playActionNow "tsp_common_stop"; };
        };
        ["comspec_sse_iarpAggroEv", ["done", [_u, _v, _why, _swings, _landed]]] call CBA_fnc_serverEvent;
        true
    };

    // Coup de TSP Melee arrivé sur le joueur (évènement tsp_melee_damageMan) : compté, dégâts estimés d'après CfgMelee.
    case "tspHit": {
        _a params [["_u", objNull, [objNull]], ["_v", objNull, [objNull]]];
        private _s = _u getVariable ["comspec_sse_meleeSt", []];
        if (_s isEqualTo []) exitWith {};
        _s set [5, (_s select 5) + 1];
        private _max = 0;
        {
            { _max = _max max (getNumber (_x >> "manDamage")); } forEach (configProperties [configFile >> "CfgMelee" >> "fist" >> _x, "isClass _x", true]);
        } forEach ["main", "alt"];
        [_v, _max * (missionNamespace getVariable ["tsp_cba_melee_damage", 1])] call _addTaken;
        ["comspec_sse_iarpAggroEv", ["hit", [_u, _v, "punch"]]] call CBA_fnc_serverEvent;
    };

    case "tick": {
        _a params [["_u", objNull, [objNull]], ["_h", -1, [0]]];
        if (isNull _u) exitWith { [_h] call CBA_fnc_removePerFrameHandler; };
        private _s = _u getVariable ["comspec_sse_meleeSt", []];
        if (_s isEqualTo [] || {(_s select 9) isNotEqualTo _h}) exitWith { [_h] call CBA_fnc_removePerFrameHandler; };
        _s params ["_v", "_o", "_t0", "_lastSwing", "_swings", "_landed", "_lastMove", "", "", "", "_busyUntil"];
        private _stop = { params ["_why"]; ["stop", [_u, _why]] call comspec_sse_fnc_iarpMelee; };
        if (!alive _u) exitWith { ["dead"] call _stop; };
        if (!local _u) exitWith { ["moved"] call _stop; };
        if (isNull _v || {!alive _v} || {!isNull objectParent _v}) exitWith { ["gone"] call _stop; };
        if ((missionNamespace getVariable ["comspec_sse_aggroCalm", false]) || {!isDamageAllowed _v && {(_o getOrDefault ["kind", "punch"]) isEqualTo "punch"}}) exitWith { ["calm"] call _stop; };
        if (([_u] call _restrained) || {[_u] call _uncon}) exitWith { ["stopped"] call _stop; };
        if ([_v] call _uncon) exitWith { ["down"] call _stop; };
        if (time < _busyUntil) exitWith {};
        private _m = createHashMapFromArray ((["cfg"] call comspec_sse_fnc_iarpAggro) getOrDefault ["melee", []]);
        private _dm = createHashMapFromArray ((["cfg"] call comspec_sse_fnc_iarpAggro) getOrDefault ["damage", []]);
        if (_swings >= (_o getOrDefault ["maxSwings", 1])) exitWith { ["done"] call _stop; };
        private _d = _u distance _v;
        if (_d > (_m getOrDefault ["chase", 25]) || {_swings isEqualTo 0 && {(time - _t0) > (_m getOrDefault ["giveUp", 20])}}) exitWith { ["lost"] call _stop; };
        // Plafonds avant chaque coup : dégâts reçus en une minute, et, sans « mortel », joueur déjà trop amoché.
        private _int = _o getOrDefault ["intensity", 50];
        private _lethal = (_o getOrDefault ["lethal", false]) isEqualTo true;
        private _kind = _o getOrDefault ["kind", "punch"];
        private _got = ([_v] call _taken) select 0;
        if (_kind isEqualTo "punch" && {_got >= ((_dm getOrDefault ["perMinute", 0.3]) * (_int / 50)) || {!_lethal && {(damage _v) >= (_dm getOrDefault ["nonLethalCeil", 0.6])}}}) exitWith { ["cap"] call _stop; };
        private _reach = _m getOrDefault ["reach", 1.6];
        if (_d > _reach) exitWith {
            // Approche : la cible bouge, un ordre par seconde au plus.
            if ((time - _lastMove) >= 1) then {
                _s set [6, time];
                _u doWatch _v;
                _u doMove ((getPosATL _v) vectorAdd ((vectorNormalized ((getPosATL _u) vectorDiff (getPosATL _v))) vectorMultiply ((_m getOrDefault ["approach", 1.3]) min _d)));
            };
        };
        if ((time - _lastSwing) < (_m getOrDefault ["cooldown", 2.5])) exitWith {};

        // ------------------------------------------------------------------ le coup
        _u doWatch _v;
        _u setDir (_u getDir _v);
        private _swing = if (_kind isEqualTo "punch") then { "punch" } else { selectRandom ["shove", "shove", "slap"] };
        if (_int <= 0) then { _swing = "shove"; };
        _s set [3, time];
        _s set [4, _swings + 1];
        // TSP Melee : seulement s'il peut jouer (activé, IA à mains nues et debout) et si le joueur visé est bien l'homme
        // le plus proche de l'IA (TSP Melee choisit lui-même sa victime : la plus proche).
        private _useTsp = (_o getOrDefault ["backend", "sse"]) isEqualTo "tsp" && {!isNil "tsp_fnc_melee_action"} && {!isNil "tsp_fnc_melee_can"}
            && {!isNil "tsp_fnc_melee_weapon"} && {(missionNamespace getVariable ["tsp_cba_melee", false]) isEqualTo true};
        if (_useTsp) then {
            private _near = (_u nearEntities ["CAManBase", 10]) select { _x isNotEqualTo _u && {alive _x} };
            _near = _near apply { [_x distance _u, _x] };
            _near sort true;
            _useTsp = ((_near param [0, [0, objNull]]) select 1) isEqualTo _v && {_d <= 2.5} && {_u call tsp_fnc_melee_can} && {([_u] call tsp_fnc_melee_weapon) isEqualTo "fist"};
        };
        if (_useTsp) exitWith {
            _s set [10, time + 1.4];
            [_u, ["push", selectRandom ["main", "main", "alt"]] select (_swing isEqualTo "punch"), true] spawn tsp_fnc_melee_action;
        };

        // Moteur intégré : geste, court élan, puis la touche 0,3 s plus tard.
        private _tspG = (_o getOrDefault ["tspAnim", true]) isEqualTo true;
        private _tspList = [[], [["tsp_melee_fistPush"], selectRandom [["tsp_melee_fistPunchRight", "tsp_melee_fistPunchLeft"], ["tsp_melee_fistPunchLeft", "tsp_melee_fistPunchRight"]]] select (_swing isEqualTo "punch")] select _tspG;
        private _g = [_tspList + ([["GestureGo", "GestureFreeze", "GestureNo"], ["GestureAttack", "GestureAdvance", "GestureGo"]] select (_swing isEqualTo "punch"))] call _gesture;
        if (_g isNotEqualTo "") then { _u playActionNow _g; };
        _u setVelocityModelSpace [0, [1.6, 2.4] select (_swing isEqualTo "punch"), 0.15];
        _s set [10, time + 0.5];
        [{
            params ["_u", "_v", "_swing", "_o", "_reach"];
            private _s = _u getVariable ["comspec_sse_meleeSt", []];
            if (_s isEqualTo [] || {!alive _u} || {!alive _v} || {!isNull objectParent _v} || {(_u distance _v) > (_reach + 0.6)}) exitWith {};
            private _cfg = ["cfg"] call comspec_sse_fnc_iarpAggro;
            private _m = createHashMapFromArray (_cfg getOrDefault ["melee", []]);
            private _dm = createHashMapFromArray (_cfg getOrDefault ["damage", []]);
            // Une bousculade touche toujours ; un coup de poing peut manquer (trait : ivre moins, hostile plus).
            if (_swing isEqualTo "punch" && {(random 1) > ((_m getOrDefault ["hitChance", 0.7]) + (_o getOrDefault ["hitBonus", 0]))}) exitWith {};
            private _int = _o getOrDefault ["intensity", 50];
            private _lethal = (_o getOrDefault ["lethal", false]) isEqualTo true;
            private _sum = 0;
            { if ((time - (_x select 0)) < 60) then { _sum = _sum + (_x select 1); }; } forEach (_v getVariable ["comspec_sse_aggroTaken", []]);
            private _dmg = ["damage", [_swing, _int, _lethal, _sum, damage _v]] call comspec_sse_fnc_iarpAggro;
            if (_dmg > 0) then {
                private _l = (_v getVariable ["comspec_sse_aggroTaken", []]) select { (time - (_x select 0)) < 60 };
                _l pushBack [time, _dmg];
                _v setVariable ["comspec_sse_aggroTaken", _l, true];
            };
            private _ko = _swing isEqualTo "punch" && {(_o getOrDefault ["ko", false]) isEqualTo true} && {_int >= 50} && {(random 1) < (_dm getOrDefault ["koChance", 0.15])};
            private _knock = (_dm getOrDefault [["knockShove", "knockPunch"] select (_swing isEqualTo "punch"), 2]) * ([1, 0.4] select (_swing isEqualTo "slap"));
            private _stun = _swing isEqualTo "punch" && {(_o getOrDefault ["tspAnim", true]) isEqualTo true} && {(random 1) < 0.3};
            ["comspec_sse_iarpMeleeHit", [_v, _u, _swing, _dmg, _knock, _ko, _lethal, _stun], _v] call CBA_fnc_targetEvent;
            // Son de coup du jeu, entendu de tous (fichiers d'Arma 3, sounds_f).
            private _snd = if (_swing isEqualTo "shove") then {
                format ["a3\sounds_f\characters\movements\bodyfalls\bodyfall_concrete_%1.wss", 1 + floor (random 3)]
            } else {
                format ["a3\sounds_f\characters\human-sfx\P01\Low_Hit_0%1.wss", 1 + floor (random 6)]
            };
            playSound3D [_snd, _v, false, getPosASL _v, 2.5, 1, 30];
            _s set [5, (_s select 5) + 1];
            ["comspec_sse_iarpAggroEv", ["hit", [_u, _v, _swing]]] call CBA_fnc_serverEvent;
        }, [_u, _v, _swing, _o, _reach], 0.3] call CBA_fnc_waitAndExecute;
    };

    // Chez le joueur touché (moteur intégré seulement) : recul, dégâts plafonnés, secousse ; jamais de fenêtre d'alerte.
    case "hit": {
        _a params [["_v", objNull, [objNull]], ["_att", objNull, [objNull]], ["_swing", "shove", [""]], ["_dmg", 0, [0]], ["_knock", 0, [0]], ["_ko", false, [false]],
            ["_lethal", false, [false]], ["_stun", false, [false]]];
        if (isNull _v || {!local _v} || {!alive _v} || {!isNull objectParent _v}) exitWith {};
        if (_knock > 0 && {!isNull _att}) then {
            private _dir = _att getDir _v;
            _v setVelocity ((velocity _v) vectorAdd [(sin _dir) * _knock, (cos _dir) * _knock, [0.3, 0.8] select (_swing isEqualTo "shove")]);
        };
        if (hasInterface && {_v isEqualTo player}) then { addCamShake [[3, 7] select (_swing isEqualTo "punch"), 0.7, 14]; };
        if (!isDamageAllowed _v) exitWith {};
        if (_dmg > 0) then {
            if (!isNil "ace_medical_fnc_addDamageToUnit") then {
                // ACE médical : contusion (type « punch »), sans auteur (ni score, ni représailles d'autres IA).
                [_v, _dmg, selectRandom ([["body", "body", "head", "leftarm", "rightarm"], ["body"]] select (_swing isEqualTo "shove")), "punch", objNull] call ace_medical_fnc_addDamageToUnit;
            } else {
                private _ceil = (createHashMapFromArray ((["cfg"] call comspec_sse_fnc_iarpAggro) getOrDefault ["damage", []])) getOrDefault ["nonLethalCeil", 0.6];
                private _new = (damage _v) + _dmg;
                if (!_lethal) then { _new = _new min (_ceil max (damage _v)); };
                _v setDamage (_new min 1);
            };
        };
        // Sonné (animation de TSP Melee, s'il est chargé) : jamais sur un joueur déjà à terre.
        if (_stun && {!isNil "tsp_fnc_melee_stun"} && {(stance _v) isEqualTo "STAND"} && {isClass (configFile >> "CfgMovesMaleSdr" >> "States" >> "tsp_melee_fistStun")}) then {
            [_v, 0] spawn tsp_fnc_melee_stun;
        };
        if (_ko && {alive _v}) then {
            if (!isNil "ace_medical_fnc_setUnconscious") then {
                [_v, true, 10, true] call ace_medical_fnc_setUnconscious;
            } else {
                _v setUnconscious true;
                [{ params ["_v"]; if (alive _v) then { _v setUnconscious false; }; }, [_v], 8] call CBA_fnc_waitAndExecute;
            };
        };
    };
};
