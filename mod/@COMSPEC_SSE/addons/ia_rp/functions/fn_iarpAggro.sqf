/*
    Agressivité des IA du SSE envers les joueurs : colère qui monte, insultes, bousculade, coups (IA contre IA : hors périmètre).
    Coupée par défaut (réglage CBA « Agressivité des IA » : jamais / verbale / physique), par IA ou par zone (Eden, Zeus).
    Tables : data\iarp_aggro.sqf (traits, déclencheurs, paliers, plafonds) ; répliques : data\iarp_hostile.sqf.

    PUR (toute machine, sans unité : fn_iarpSelfTest, tests\Unit\SseIaAgressiviteAssetTest.php)
      ["cfg"] -> HashMap des tables            ["lines"] -> HashMap des répliques
      ["gain", [déclencheur, trait]] -> colère ajoutée          ["decay", [colère, secondes, trait]] -> colère retombée
      ["stage", [colère, niveau, trait]] -> calm | irritated | hostile | shove | punch
            niveau off : toujours calm ; verbal, ou trait qui bluffe : jamais au-delà de hostile
      ["rankOf", palier] -> 0-4                 ["level", palier] -> palier de réplique ("" : calme)
      ["damage", [geste, intensité 0-100, mortel, dégâts déjà reçus en 60 s, dégâts du joueur]] -> dégâts du coup (0-1)
            plafond par minute ; sans « mortel », jamais au-delà de nonLethalCeil
      ["backend", [réglage auto | tsp | sse, TSP Melee chargé]] -> tsp | sse
      ["persona", trait] -> caractère des répliques        ["traitAuto", profil] -> trait tiré du profil de l'atelier IA
      ["register", [profil, tirage 0-1]] -> street | rural | educated | foreign
      ["pick", [palier, caractère, déclencheur, registre, pays, déjà dites]] -> [fr, en] ([] : rien)

    SERVEUR
      ["conf", ia] -> HashMap mode, trait, intensity, lethal, ko, weapon (réglage de l'IA, sinon de la mission ; « calme »
            du Zeus, zone sans agressivité, colère décochée dans ses émotions : mode off)
      ["trigger", [ia, joueur, déclencheur]] -> [palier, palier de réplique, monté] ; lance la bagarre au palier voulu
      ["say", [ia, joueur, intentions, commande RP, texte normalisé, profil, pays]] -> [] | [fr, en, palier, refuse les ordres]
            appelé par fn_iarpServer « say » : la réplique hostile remplace la réponse ordinaire
      ["speak", [ia, palier de réplique, déclencheur]] : réplique hostile en bulle, sans message du joueur
      ["rank", ia] -> 0-4 (en-tête de « Parler à » : « menaçant »)     ["reset", [ia]] : retour au calme
      ["set", [ias, niveau, trait, intensité, mortel, rayon]] : réglage par IA, ou toutes les IA à ce rayon de la 1re
      ["zeus", [ias, ordre, rayon]] : attack (l'IA passe au niveau physique et s'en prend au joueur à pied le plus proche),
            calm (ces IA se calment), calmOn / calmOff (« calme général » : plus aucune agressivité sur toute la carte,
            variable diffusée comspec_sse_aggroCalm), safe (zone sans agressivité autour de la 1re IA, au rayon donné,
            50 m par défaut : liste diffusée comspec_sse_aggroSafe [[position, rayon]…], qu'une mission peut aussi poser
            elle-même), safeClear (plus aucune zone)
              ["hit", …] · ["done", …] : comptes rendus de fn_iarpMelee
    Garde-fous : jamais contre une IA ; une bagarre à la fois par IA, deux IA au plus sur le même joueur ; 6 coups au
    plus par bagarre puis une minute sans bagarre ; dégâts plafonnés par minute ; jamais mortel sauf réglage ; rien pour
    une IA menottée, rendue ou inconsciente (elle insulte seulement) ; aucune fenêtre d'alerte du jeu.
*/
params [["_op", "", [""]], ["_a", []]];
private _read = {
    params ["_what"];
    private _key = "comspec_sse_iarpAggroData_" + _what;
    private _c = missionNamespace getVariable [_key, 0];
    if (_c isEqualType createHashMap) exitWith { _c };
    private _raw = call compile preprocessFileLineNumbers format ["\z\comspec_sse\addons\ia_rp\data\iarp_%1.sqf", _what];
    _c = createHashMapFromArray ([[], _raw] select (!isNil "_raw" && {_raw isEqualType []}));
    // Répliques ajoutées par la mission (même format, ajouts seulement).
    if (_what isEqualTo "hostile") then {
        private _extra = missionNamespace getVariable ["comspec_sse_iarpExtra_hostile", []];
        if (_extra isEqualType []) then {
            { _x params [["_k", "", [""]], ["_v", [], [[]]]]; if (_k in ["lines", "triggers", "registers", "countries"]) then { (_c getOrDefault [_k, []]) append _v; }; } forEach _extra;
        };
    };
    missionNamespace setVariable [_key, _c];
    _c
};
private _rowOf = {
    params ["_table", "_id"];
    private _rows = (["aggro"] call _read) getOrDefault [_table, []];
    private _i = _rows findIf { (_x select 0) isEqualTo _id };
    if (_i < 0) then { [] } else { _rows select _i }
};
private _val = {
    params ["_table", "_id", "_def"];
    private _r = [_table, _id] call _rowOf;
    if ((count _r) > 1) then { _r select 1 } else { _def }
};
private _traitRow = {
    params ["_trait"];
    private _r = ["traits", _trait] call _rowOf;
    if (_r isEqualTo []) then { ["none", 1, 20, false, 0.3, 0] } else { _r }
};
private _stageIds = ((["aggro"] call _read) getOrDefault ["stages", []]) apply { _x select 0 };
private _restrained = { params ["_u"]; (_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ace_captives_isSurrendering", false]} };
private _uncon = { params ["_u"]; (_u getVariable ["ACE_isUnconscious", false]) || {(lifeState _u) isEqualTo "INCAPACITATED"} };

// ------------------------------------------------------------------------------------------------ pur
if (_op isEqualTo "cfg") exitWith { ["aggro"] call _read };
if (_op isEqualTo "lines") exitWith { ["hostile"] call _read };
if (_op isEqualTo "gain") exitWith {
    _a params [["_trig", "", [""]], ["_trait", "none", [""]]];
    (["triggers", _trig, 0] call _val) * (([_trait] call _traitRow) select 1)
};
if (_op isEqualTo "decay") exitWith {
    _a params [["_anger", 0, [0]], ["_sec", 0, [0]], ["_trait", "none", [""]]];
    (_anger - ((_sec max 0) / 60) * (([_trait] call _traitRow) select 2)) max 0
};
if (_op isEqualTo "stage") exitWith {
    _a params [["_anger", 0, [0]], ["_mode", "off", [""]], ["_trait", "none", [""]]];
    if !(_mode in ["verbal", "physical"]) exitWith { "calm" };
    private _phys = _mode isEqualTo "physical" && {!(([_trait] call _traitRow) select 3)};
    private _out = "calm";
    {
        _x params ["_id", "_min", "_needPhys"];
        if (_anger >= _min && {!_needPhys || {_phys}}) then { _out = _id; };
    } forEach ((["aggro"] call _read) getOrDefault ["stages", []]);
    _out
};
if (_op isEqualTo "rankOf") exitWith { (_stageIds find _a) max 0 };
if (_op isEqualTo "level") exitWith {
    (createHashMapFromArray [["irritated", "irritated"], ["hostile", "hostile"], ["shove", "brink"], ["punch", "fight"]]) getOrDefault [_a, ""]
};
if (_op isEqualTo "damage") exitWith {
    _a params [["_kind", "punch", [""]], ["_int", 50, [0]], ["_lethal", false, [false]], ["_taken", 0, [0]], ["_vd", 0, [0]]];
    private _k = ((_int max 0) min 100) / 50;
    private _d = (["damage", _kind, 0] call _val) * _k;
    _d = _d min (((["damage", "perMinute", 0.3] call _val) * _k - _taken) max 0);
    if (!_lethal) then { _d = _d min (((["damage", "nonLethalCeil", 0.6] call _val) - _vd) max 0); };
    _d max 0
};
if (_op isEqualTo "backend") exitWith {
    _a params [["_set", "auto", [""]], ["_tsp", false, [false]]];
    ["sse", "tsp"] select (_tsp && {_set in ["auto", "tsp"]})
};
if (_op isEqualTo "persona") exitWith { ["*", _a] select (_a in ["irritable", "drunk", "fanatic", "resentful", "proud", "bluff"]) };
if (_op isEqualTo "traitAuto") exitWith {
    private _p = _a;
    if !(_p isEqualType createHashMap) exitWith { "none" };
    if ((_p getOrDefault ["drunk", 0]) >= 60) exitWith { "drunk" };
    if ((_p getOrDefault ["role", "auto"]) in ["insurge", "hvt"]) exitWith { "hostile" };
    if ((_p getOrDefault ["aggro", 30]) >= 70) exitWith { "irritable" };
    if ((_p getOrDefault ["fear", 50]) >= 75 && {(_p getOrDefault ["aggro", 30]) >= 45}) exitWith { "bluff" };
    "none"
};
if (_op isEqualTo "register") exitWith {
    _a params [["_p", createHashMap, [createHashMap]], ["_roll", 0, [0]]];
    if ((_p getOrDefault ["lang", "common"]) isEqualTo "local") exitWith { "foreign" };
    if ((_p getOrDefault ["honesty", 60]) >= 70 && {(_p getOrDefault ["talk", 50]) >= 55}) exitWith { "educated" };
    ["street", "rural"] select (_roll >= 0.5)
};
if (_op isEqualTo "pick") exitWith {
    _a params [["_level", "", [""]], ["_persona", "*", [""]], ["_trig", "", [""]], ["_reg", "", [""]], ["_country", "", [""]], ["_recent", [], [[]]]];
    private _d = ["hostile"] call _read;
    private _pool = [];
    private _add = { params ["_list", "_w"]; { for "_i" from 1 to _w do { _pool pushBack _x; }; } forEach _list; };
    {
        _x params ["_l", "_p", "_lines"];
        if (_l isEqualTo _level) then {
            if (_p isEqualTo _persona) then { [_lines, 3] call _add; };
            if (_p isEqualTo "*" && {_persona isNotEqualTo "*"}) then { [_lines, 1] call _add; };
        };
    } forEach (_d getOrDefault ["lines", []]);
    // Déclencheur, registre et pays : tant qu'on se parle encore (pas pendant les coups, ni après).
    if (_level in ["irritated", "hostile", "brink"]) then {
        { if ((_x select 0) isEqualTo _trig) then { [_x select 1, 2] call _add; }; } forEach (_d getOrDefault ["triggers", []]);
        { if ((_x select 0) isEqualTo _reg) then { [_x select 1, 1] call _add; }; } forEach (_d getOrDefault ["registers", []]);
        { if ((_x select 0) isEqualTo _country) then { [_x select 1, 1] call _add; }; } forEach (_d getOrDefault ["countries", []]);
    };
    if (_pool isEqualTo []) exitWith { [] };
    private _fresh = _pool select { !((_x select 0) in _recent) };
    +(selectRandom ([_pool, _fresh] select (_fresh isNotEqualTo [])))
};

// ------------------------------------------------------------------------------------------------ serveur
if (!isServer) exitWith { [] };
private _melee = createHashMapFromArray ((["aggro"] call _read) getOrDefault ["melee", []]);
private _stateOf = {
    params ["_u"];
    private _s = _u getVariable ["comspec_sse_aggroSt", 0];
    if !(_s isEqualType createHashMap) then {
        _s = createHashMapFromArray [["anger", 0], ["at", time], ["target", objNull], ["stage", "calm"], ["recent", []], ["rest", -1], ["busy", false],
            ["sideDone", false], ["register", ""], ["watch", -1], ["weaponAt", -100], ["nearAt", -1], ["trig", ""]];
        _u setVariable ["comspec_sse_aggroSt", _s];
    };
    _s
};
private _conf = {
    params ["_u"];
    private _p = ["get", _u] call comspec_sse_fnc_iarpProfile;
    private _mode = _u getVariable ["comspec_sse_aggroMode", "auto"];
    if !(_mode in ["off", "verbal", "physical"]) then { _mode = missionNamespace getVariable ["comspec_sse_iarp_aggro", "off"]; };
    if !(_mode in ["off", "verbal", "physical"]) then { _mode = "off"; };
    // Coupé : IA RP éteinte, « calme » du Zeus, zone sans agressivité, colère décochée dans ses émotions.
    if !(missionNamespace getVariable ["comspec_sse_iarp_enabled", true]) then { _mode = "off"; };
    if (missionNamespace getVariable ["comspec_sse_aggroCalm", false]) then { _mode = "off"; };
    if (((missionNamespace getVariable ["comspec_sse_aggroSafe", []]) findIf { (_u distance2D (_x select 0)) <= (_x select 1) }) >= 0) then { _mode = "off"; };
    if !("colere" in (["emoActive", _u] call comspec_sse_fnc_iarpProfile)) then { _mode = "off"; };
    private _trait = _u getVariable ["comspec_sse_aggroTrait", "auto"];
    if ((["traits", _trait] call _rowOf) isEqualTo []) then { _trait = ["traitAuto", _p] call comspec_sse_fnc_iarpAggro; };
    private _int = _u getVariable ["comspec_sse_aggroDmg", -1];
    if (!(_int isEqualType 0) || {_int < 0}) then { _int = missionNamespace getVariable ["comspec_sse_iarp_aggroDamage", 50]; };
    private _lethal = _u getVariable ["comspec_sse_aggroLethal", -1];
    _lethal = if (_lethal isEqualType 0 && {_lethal >= 0}) then { _lethal > 0 } else { missionNamespace getVariable ["comspec_sse_iarp_aggroLethal", false] };
    createHashMapFromArray [["mode", _mode], ["trait", _trait], ["intensity", (_int max 0) min 100], ["lethal", _lethal isEqualTo true],
        ["ko", (missionNamespace getVariable ["comspec_sse_iarp_aggroKo", false]) isEqualTo true],
        ["weapon", (missionNamespace getVariable ["comspec_sse_iarp_aggroWeapon", false]) isEqualTo true], ["profile", _p]]
};
// Colère retombée depuis le dernier passage.
private _cool = {
    params ["_s", "_trait"];
    _s set ["anger", ["decay", [_s get "anger", time - (_s get "at"), _trait]] call comspec_sse_fnc_iarpAggro];
    _s set ["at", time];
};
// Réplique hostile dite à voix haute : bulle aux joueurs à portée (comme fn_iarpReact), jamais une fenêtre d'alerte.
private _speak = {
    params ["_u", "_level", ["_trig", ""], ["_tell", true]];
    private _s = [_u] call _stateOf;
    private _c = [_u] call _conf;
    if ((_s get "register") isEqualTo "") then { _s set ["register", ["register", [_c get "profile", random 1]] call comspec_sse_fnc_iarpAggro]; };
    private _country = "";
    if (!isNil "comspec_sse_fnc_idLegend") then {
        private _lg = ["get", [_u]] call comspec_sse_fnc_idLegend;
        if (!isNil "_lg" && {_lg isEqualType createHashMap}) then { _country = _lg getOrDefault ["country", ""]; };
    };
    if !(_country isEqualType "") then { _country = ""; };
    private _line = ["pick", [_level, ["persona", _c get "trait"] call comspec_sse_fnc_iarpAggro, _trig, _s get "register", _country, _s get "recent"]] call comspec_sse_fnc_iarpAggro;
    if (_line isEqualTo []) exitWith { [] };
    private _rec = _s get "recent";
    _rec pushBack (_line select 0);
    if ((count _rec) > 8) then { _rec deleteAt 0; };
    if (_tell) then {
        _u setVariable ["comspec_sse_iarpSaidAt", time];
        private _range = missionNamespace getVariable ["comspec_sse_iarp_range", 20];
        private _pay = [_line select 0, _line select 1, createHashMap, "", ""];
        private _pl = allPlayers select { !(_x isKindOf "HeadlessClient_F") };
        if (hasInterface && {!isNull player}) then { _pl pushBackUnique player; };
        {
            if (alive _x && {(_x distance _u) <= _range}) then {
                private _args = [_u, "ai", ["for", [_u, _x]] call comspec_sse_fnc_iarpName, _pay, true];
                if (local _x && {hasInterface}) then { ["bubble", _args] call comspec_sse_fnc_iarpClient; } else { ["bubble", _args] remoteExecCall ["comspec_sse_fnc_iarpClient", owner _x]; };
            };
        } forEach _pl;
        // Geste de défi une fois sur trois (TSP Animate s'il est chargé, sinon geste du jeu), là où l'IA est locale.
        if (_level in ["irritated", "hostile"] && {(random 1) < 0.34} && {!([_u] call _restrained)}) then {
            ["comspec_sse_iarpMelee", ["taunt", [_u, missionNamespace getVariable ["comspec_sse_iarp_aggroTspAnim", true]]], _u] call CBA_fnc_targetEvent;
        };
    };
    _line
};
// Surveillance d'une IA en colère (2 s, tant qu'elle l'est) : la colère retombe, une arme braquée ou un joueur qui
// reste collé à elle la fait remonter ; « calme » du Zeus ou zone sans agressivité : tout s'arrête.
private _watch = {
    params ["_u"];
    private _s = [_u] call _stateOf;
    if ((_s get "watch") >= 0) exitWith {};
    _s set ["watch", [{
        params ["_u", "_h"];
        ["tick", [_u, _h]] call comspec_sse_fnc_iarpAggro;
    }, 2, _u] call CBA_fnc_addPerFrameHandler];
};

switch (_op) do {
    case "conf": { [_a] call _conf };

    case "speak": { _a call _speak };

    case "rank": {
        if !(_a isEqualType objNull) exitWith { 0 };
        private _s = _a getVariable ["comspec_sse_aggroSt", 0];
        if (_s isEqualType createHashMap) then { ["rankOf", _s get "stage"] call comspec_sse_fnc_iarpAggro } else { 0 }
    };

    case "reset": {
        _a params [["_u", objNull, [objNull]]];
        if (isNull _u) exitWith {};
        private _s = [_u] call _stateOf;
        _s set ["anger", 0]; _s set ["at", time]; _s set ["stage", "calm"]; _s set ["rest", -1];
        if ((_s get "busy") isEqualTo true) then { ["comspec_sse_iarpMelee", ["stop", [_u, "calm"]], _u] call CBA_fnc_targetEvent; };
    };

    case "tick": {
        _a params [["_u", objNull, [objNull]], ["_h", -1, [0]]];
        private _end = { [_h] call CBA_fnc_removePerFrameHandler; if (!isNull _u) then { ([_u] call _stateOf) set ["watch", -1]; }; };
        if (isNull _u || {!alive _u}) exitWith { call _end; };
        private _s = [_u] call _stateOf;
        private _c = [_u] call _conf;
        if ((_c get "mode") isEqualTo "off") exitWith { ["reset", [_u]] call comspec_sse_fnc_iarpAggro; call _end; };
        [_s, _c get "trait"] call _cool;
        private _t = _s get "target";
        if ((_s get "anger") < (["stages", "irritated", 20] call _val) && {!(_s get "busy")}) exitWith { _s set ["stage", "calm"]; call _end; };
        if (isNull _t || {!alive _t} || {(_t distance _u) > 60}) exitWith {};
        // Arme braquée sur elle : une IA qui en a peur se calme (intimidée), les autres s'énervent ; 10 s entre deux.
        if ((time - (_s get "weaponAt")) > 10 && {((["aimedAt", [_u]] call comspec_sse_fnc_iarpReact) param [0, 0]) > 0}) then {
            _s set ["weaponAt", time];
            private _emo = ["emoActive", _u] call comspec_sse_fnc_iarpProfile;
            if ("armes" in _emo && {"peur" in _emo} && {((_c get "profile") getOrDefault ["fear", 50]) >= 60}) then {
                _s set ["anger", ((_s get "anger") - 15) max 0];
            } else {
                ["trigger", [_u, _t, "weapon"]] call comspec_sse_fnc_iarpAggro;
            };
        };
        // Provoquée dans la durée : le joueur reste à moins de 3 m d'une IA déjà hostile, par tranche de 10 s.
        if ((_t distance _u) <= 3 && {(["rankOf", _s get "stage"] call comspec_sse_fnc_iarpAggro) >= 2}) then {
            if ((_s get "nearAt") < 0) then { _s set ["nearAt", time]; };
            if ((time - (_s get "nearAt")) >= 10) then { _s set ["nearAt", time]; ["trigger", [_u, _t, "provoked"]] call comspec_sse_fnc_iarpAggro; };
        } else {
            _s set ["nearAt", -1];
        };
    };

    case "trigger": {
        _a params [["_u", objNull, [objNull]], ["_by", objNull, [objNull]], ["_trig", "", [""]]];
        private _none = ["calm", "", false];
        // Jamais contre une IA (hors périmètre), jamais par une IA morte, inconsciente ou un joueur.
        if (isNull _u || {!alive _u} || {isPlayer _u} || {!(_u isKindOf "CAManBase")} || {isNull _by} || {!alive _by} || {!isPlayer _by} || {[_u] call _uncon}) exitWith { _none };
        private _c = [_u] call _conf;
        if ((_c get "mode") isEqualTo "off") exitWith { _none };
        private _s = [_u] call _stateOf;
        private _trait = _c get "trait";
        [_s, _trait] call _cool;
        private _gain = ["gain", [_trig, _trait]] call comspec_sse_fnc_iarpAggro;
        // Camp du joueur ennemi du sien : une fois, au premier accroc.
        if (!(_s get "sideDone") && {_gain > 0} && {[side group _u, side group _by] call BIS_fnc_sideIsEnemy}) then {
            _s set ["sideDone", true];
            _gain = _gain + (["gain", ["side", _trait]] call comspec_sse_fnc_iarpAggro);
        };
        _s set ["anger", ((_s get "anger") + _gain) min 100];
        _s set ["target", _by];
        _s set ["trig", _trig];
        private _old = _s get "stage";
        private _stage = ["stage", [_s get "anger", _c get "mode", _trait]] call comspec_sse_fnc_iarpAggro;
        // Menottée ou mains en l'air : elle insulte, elle ne frappe pas.
        if ([_u] call _restrained) then { _stage = ["stage", [_s get "anger", "verbal", _trait]] call comspec_sse_fnc_iarpAggro; };
        _s set ["stage", _stage];
        private _up = (_stageIds find _stage) > (_stageIds find _old);
        if (_stage isNotEqualTo "calm") then { [_u] call _watch; };
        if (_stage in ["shove", "punch"]) then { ["fight", [_u, _by, _stage, _c]] call comspec_sse_fnc_iarpAggro; };
        [_stage, ["level", _stage] call comspec_sse_fnc_iarpAggro, _up]
    };

    // Bagarre : une à la fois par IA, pas pendant la minute de repos, deux IA au plus sur le même joueur, à pied.
    case "fight": {
        _a params [["_u", objNull, [objNull]], ["_by", objNull, [objNull]], ["_stage", "shove", [""]], ["_c", createHashMap]];
        private _s = [_u] call _stateOf;
        if ((_s get "busy") || {time < (_s get "rest")} || {!isNull objectParent _u} || {!isNull objectParent _by} || {[_by] call _uncon}) exitWith { false };
        private _on = (_by getVariable ["comspec_sse_aggroOn", []]) select { !isNull _x && {alive _x} && {_x isNotEqualTo _u} };
        if ((count _on) >= (_melee getOrDefault ["perVictim", 2])) exitWith { false };
        // IA armée : arme sortie seulement si le réglage l'autorise ET que « mortel » est coché (automate des IA
        // réalistes, état « agressif ») ; sinon elle se bat à mains nues comme les autres.
        private _armed = (primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""};
        if (_armed && {_c get "weapon"} && {_c get "lethal"} && {_stage isEqualTo "punch"} && {!isNil "comspec_sse_fnc_idAiFsm"}) exitWith {
            _s set ["rest", time + (_melee getOrDefault ["rest", 60])];
            ["force", [_u, "agressif"]] call comspec_sse_fnc_idAiFsm;
            true
        };
        _s set ["busy", true];
        _on pushBack _u;
        _by setVariable ["comspec_sse_aggroOn", _on];
        private _tsp = isClass (configFile >> "CfgPatches" >> "tsp_melee");
        private _opts = createHashMapFromArray [
            ["kind", _stage], ["intensity", _c get "intensity"], ["lethal", _c get "lethal"], ["ko", _c get "ko"],
            ["backend", ["backend", [missionNamespace getVariable ["comspec_sse_iarp_aggroMelee", "auto"], _tsp]] call comspec_sse_fnc_iarpAggro],
            ["tspAnim", (missionNamespace getVariable ["comspec_sse_iarp_aggroTspAnim", true]) isEqualTo true],
            ["hitBonus", ([_c get "trait"] call _traitRow) select 5],
            ["maxSwings", [1, _melee getOrDefault ["maxSwings", 6]] select (_stage isEqualTo "punch")]
        ];
        [_u, ["level", _stage] call comspec_sse_fnc_iarpAggro, _s get "trig"] call _speak;
        ["comspec_sse_iarpMelee", ["start", [_u, _by, toArray _opts]], _u] call CBA_fnc_targetEvent;
        true
    };

    // Compte rendu d'un coup porté (fn_iarpMelee) : une réplique de temps en temps pendant les coups.
    case "hit": {
        _a params [["_u", objNull, [objNull]], ["_by", objNull, [objNull]], ["_kind", "", [""]]];
        if (isNull _u || {!alive _u}) exitWith {};
        if (_kind isEqualTo "punch" && {(random 1) < 0.4}) then { [_u, "fight"] call _speak; };
    };

    // Fin d'une bagarre : repos, colère ramenée au palier « hostile », réplique d'après, puis fuite selon le trait.
    case "done": {
        _a params [["_u", objNull, [objNull]], ["_by", objNull, [objNull]], ["_why", "", [""]], ["_swings", 0, [0]], ["_landed", 0, [0]]];
        if (isNull _u) exitWith {};
        private _s = [_u] call _stateOf;
        _s set ["busy", false];
        _s set ["rest", time + (_melee getOrDefault ["rest", 60])];
        _s set ["anger", (_s get "anger") min ((["stages", "hostile", 40] call _val) + 10)];
        _s set ["at", time];
        _s set ["stage", "hostile"];
        if (!isNull _by) then { _by setVariable ["comspec_sse_aggroOn", (_by getVariable ["comspec_sse_aggroOn", []]) - [_u]]; };
        if (!alive _u || {_why in ["calm", "dead"]}) exitWith {};
        private _c = [_u] call _conf;
        private _flee = (random 1) < (([_c get "trait"] call _traitRow) select 4);
        if (_landed > 0 && {!_flee}) then { [_u, "after"] call _speak; };
        if (_flee && {!([_u] call _restrained)}) then {
            [_u, "flee"] call _speak;
            private _from = [_by, _u] select (isNull _by);
            ["comspec_sse_iarpAiDo", [_u, "flee", [(getPosATL _u) getPos [120 + random 60, (_from getDir _u) + (random 60) - 30]]], _u] call CBA_fnc_targetEvent;
            _s set ["anger", 0];
            _s set ["stage", "calm"];
        };
    };

    case "say": {
        _a params [["_u", objNull, [objNull]], ["_by", objNull, [objNull]], ["_list", [], [[]]], ["_cmd", "", [""]], ["_norm", "", [""]]];
        if (isNull _u || {isNull _by}) exitWith { [] };
        private _c = [_u] call _conf;
        if ((_c get "mode") isEqualTo "off") exitWith { [] };
        private _s = [_u] call _stateOf;
        private _orders = ["handsup", "halt", "kneel", "follow", "dismount", "trunk", "dropWeapon", "stepBack", "comeHere", "turnAround", "sit", "quiet", "hurry", "bag"];
        private _push = _cmd in ["me", "do"] && {([" pousse", " bouscule", " frappe", " gifle", " tape", " shove", " push", " hit", " slap", " punch"] findIf { (_norm find _x) >= 0 }) >= 0};
        private _trigs = [];
        {
            private _i = _x;
            private _t = call {
                if (_i isEqualTo "insult") exitWith { "insulted" };
                if (_i isEqualTo "threat") exitWith { ["threatened", "pushed"] select _push };
                if (_i in ["search", "searchWait"]) exitWith { "searched" };
                if (_i in ["papers", "papersWait", "papersCloser"]) exitWith { "papers" };
                if (_i in _orders) exitWith { "ordered" };
                ""
            };
            if (_t isNotEqualTo "") then { _trigs pushBackUnique _t; };
        } forEach _list;
        // Excuses, apaisement, « vous pouvez partir » : la colère retombe d'un coup.
        if ((_list findIf { _x in ["sorry", "calm", "thanks", "leave"] }) >= 0 && {_trigs isEqualTo []}) then {
            [_s, _c get "trait"] call _cool;
            _s set ["anger", ((_s get "anger") - 20) max 0];
            _s set ["stage", ["stage", [_s get "anger", _c get "mode", _c get "trait"]] call comspec_sse_fnc_iarpAggro];
        };
        private _stage = _s get "stage";
        { _stage = (["trigger", [_u, _by, _x]] call comspec_sse_fnc_iarpAggro) select 0; } forEach _trigs;
        if (_trigs isEqualTo []) then {
            [_s, _c get "trait"] call _cool;
            _stage = ["stage", [_s get "anger", _c get "mode", _c get "trait"]] call comspec_sse_fnc_iarpAggro;
            if ([_u] call _restrained) then { _stage = ["stage", [_s get "anger", "verbal", _c get "trait"]] call comspec_sse_fnc_iarpAggro; };
            _s set ["stage", _stage];
        };
        private _rank = _stageIds find _stage;
        private _level = ["level", _stage] call comspec_sse_fnc_iarpAggro;
        // Agacée : la réplique hostile seulement si ce message l'a piquée ; hostile et au-delà : toujours.
        if (_level isEqualTo "" || {_rank < 2 && {_trigs isEqualTo []}}) exitWith { [] };
        // La bagarre vient de partir : sa réplique est déjà dans la bulle (« fight »), le terminal reçoit la même veine.
        private _line = [_u, _level, _trigs param [0, ""], false] call _speak;
        if (_line isEqualTo []) exitWith { [] };
        [_line select 0, _line select 1, _level, _rank >= 2]
    };

    case "set": {
        _a params [["_units", [], [[]]], ["_mode", "auto", [""]], ["_trait", "auto", [""]], ["_dmg", -1, [0]], ["_lethal", -1, [0]], ["_radius", 0, [0]]];
        private _all = _units select { _x isEqualType objNull && {!isNull _x} && {!isPlayer _x} && {_x isKindOf "CAManBase"} };
        // Par zone : toutes les IA à ce rayon de la première IA visée.
        if (_radius > 0 && {_all isNotEqualTo []}) then {
            { if (alive _x && {!isPlayer _x}) then { _all pushBackUnique _x; }; } forEach ((_all select 0) nearEntities ["CAManBase", _radius min 500]);
        };
        {
            _x setVariable ["comspec_sse_aggroMode", _mode, true];
            _x setVariable ["comspec_sse_aggroTrait", _trait, true];
            _x setVariable ["comspec_sse_aggroDmg", _dmg, true];
            _x setVariable ["comspec_sse_aggroLethal", _lethal, true];
            if (_mode isEqualTo "off") then { ["reset", [_x]] call comspec_sse_fnc_iarpAggro; };
        } forEach _all;
        count _all
    };

    case "zeus": {
        _a params [["_units", [], [[]]], ["_act", "", [""]], ["_radius", 0, [0]]];
        private _n = 0;
        // Calme général et zones sans agressivité : diffusés (la bagarre en cours les lit là où l'IA est locale).
        if (_act in ["calmOn", "calmOff"]) exitWith {
            missionNamespace setVariable ["comspec_sse_aggroCalm", _act isEqualTo "calmOn", true];
            1
        };
        if (_act isEqualTo "safeClear") exitWith { missionNamespace setVariable ["comspec_sse_aggroSafe", [], true]; 1 };
        if (_act isEqualTo "safe") exitWith {
            private _u0 = _units param [0, objNull];
            if (!(_u0 isEqualType objNull) || {isNull _u0}) exitWith { 0 };
            private _zones = +(missionNamespace getVariable ["comspec_sse_aggroSafe", []]);
            _zones pushBack [getPosATL _u0, [_radius, 50] select (_radius <= 0)];
            missionNamespace setVariable ["comspec_sse_aggroSafe", _zones, true];
            1
        };
        {
            private _u = _x;
            if (_u isEqualType objNull && {!isNull _u} && {alive _u} && {!isPlayer _u}) then {
                if (_act isEqualTo "calm") then { ["reset", [_u]] call comspec_sse_fnc_iarpAggro; _n = _n + 1; };
                if (_act isEqualTo "attack") then {
                    // Le joueur à pied le plus proche, à 30 m au plus.
                    private _pl = (allPlayers select { alive _x && {isNull objectParent _x} && {(_x distance _u) <= 30} && {!(_x isKindOf "HeadlessClient_F")} }) apply { [_x distance _u, _x] };
                    _pl sort true;
                    if (_pl isNotEqualTo []) then {
                        // Ordre du Zeus : cette IA passe au niveau « physique », quel que soit le réglage de la mission
                        // (le « calme » général et les zones sans agressivité l'emportent toujours).
                        _u setVariable ["comspec_sse_aggroMode", "physical", true];
                        ([_u] call _stateOf) set ["rest", -1];
                        ["trigger", [_u, (_pl select 0) select 1, "zeus"]] call comspec_sse_fnc_iarpAggro;
                        _n = _n + 1;
                    };
                };
            };
        } forEach _units;
        _n
    };

    default { [] };
};
