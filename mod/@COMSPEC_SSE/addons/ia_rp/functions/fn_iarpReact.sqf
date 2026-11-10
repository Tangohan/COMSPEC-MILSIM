/*
    Réactions des IA à ce qui se passe autour d'elles (SERVEUR), sur évènement seulement : aucune boucle sur toutes
    les IA. Seules les IA à qui l'on parle (fn_iarpServer) ou engagées par l'automate des IA réalistes (fn_idAiFsm)
    sont suivies.
      ["halt", [ia, joueur]]   « Stop » obéi : l'IA est relâchée seule (réglage « Durée d'un stop », joueur parti à plus
                               de « Distance de relâche », automate qui la reprend : fuite, hostilité, retour au calme).
                               Un autre ordre (suivez-moi, circulez, mains en l'air, descendez) annule le suivi.
      ["watch", [ia]]          suit les tirs proches de cette IA : gestionnaire FiredNear posé une fois (serveur, et là où
                               elle est locale si c'est ailleurs : client sans tête, hôte), retiré après 5 min sans échange
      ["fired", [ia, tireur, distance]]   tir proche (réglages « Réaction aux tirs », « Portée des tirs ») : l'IA se jette au
                               sol (« Ne tirez pas ! »), se rend, fuit, ou se bat (insurgé armé et agressif) selon son profil ;
                               8 s au moins entre deux réactions ; seulement en conversation (3 min) ou engagée par l'automate
      ["witness", [victime, joueur]]      un civil menacé ou braqué : jusqu'à 5 IA civiles proches (réglage « Témoins »,
                               rayon réglable) qui voient la scène sont choquées (tension de l'automate en hausse, qui retombe
                               de 30 par minute) ; hors automate, elles regardent la scène ou s'enfuient (peureuses)
      ["shock", ia] -> choc (tirs, scène vue, 0-80) plus pression de la conversation (-30 à 60), lu par fn_idAiBrain ;
                               les deux retombent de 30 par minute
      ["press", [ia, écart, raison, joueur]]   pression de la conversation (menace, pot-de-vin refusé, apaisement…),
                               notée au journal de la peur
      ["aimedAt", [ia]] -> [armes braquées sur elle, distance la plus courte]   arme levée (pas baissée), canon à 8° de
                               son buste, à 25 m au plus : porter son arme ou la tenir baissée ne compte pas
      ["level", ia] -> peur 0-100 d'une IA hors automate (pression, choc, arme braquée), sans valeur de départ cachée
    Tirs : comptés seulement à 15 m du tireur, ou plus loin si le tir part vers elle (25° au plus) ; un tir de son
    groupe, ou d'un Zeus à distance, ne compte pas.
    Par IA (menu ZEN « Réactions… », fn_iarpServer reactSet) : variable d'unité comspec_sse_iarpReactOff, liste parmi
    "shots" (ne réagit plus aux tirs proches) et "witness" (n'est plus témoin), diffusée pour la fenêtre du Zeus.
    Coût : suivi d'un stop, une fonction CBA toutes les 2 s par IA arrêtée ; tirs, un gestionnaire par IA suivie,
    réaction bornée par le délai de 8 s ; témoins, une recherche de 8 IA au plus par menace (20 s par victime).
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith {};
// État de l'automate des IA réalistes ("" : hors automate, ou identité SSE absente).
private _fsmState = {
    params ["_u"];
    if (isNil "comspec_sse_fnc_idAiFsm") exitWith { "" };
    private _s = ["state", [_u]] call comspec_sse_fnc_idAiFsm;
    if (isNil "_s" || {!(_s isEqualType "")}) then { "" } else { _s }
};
// Choc (tirs, scène vue) : [valeur, heure], -30 par minute.
private _shockOf = {
    params ["_u"];
    (_u getVariable ["comspec_sse_idShock", [0, 0]]) params [["_v", 0], ["_at", 0]];
    (_v - (time - _at) * 0.5) max 0
};
private _addShock = { params ["_u", "_d"]; _u setVariable ["comspec_sse_idShock", [(([_u] call _shockOf) + _d) min 80, time]]; };
// Réaction coupée pour cette IA par le Zeus ("shots" | "witness").
private _off = { params ["_u", "_what"]; private _l = _u getVariable ["comspec_sse_iarpReactOff", []]; _l isEqualType [] && {_what in _l} };
private _pressOf = {
    params ["_u"];
    (_u getVariable ["comspec_sse_iarpPress", [0, 0]]) params [["_v", 0], ["_at", 0]];
    private _k = (time - _at) * 0.5;
    if (_v > 0) then { (_v - _k) max 0 } else { (_v + _k) min 0 }
};
// Journal de la peur (onglet JOURNAL) : chaque variation et sa raison.
private _fearLog = {
    params ["_u", "_d", "_reason", ["_by", objNull]];
    if (isNil "comspec_sse_fnc_iarpServer") exitWith {};
    ["logFear", [_by, _u, _d, _reason]] call comspec_sse_fnc_iarpServer;
};
// Phrase spontanée (sans question du joueur) : bulle aux joueurs à portée, rien dans les terminaux.
private _speak = {
    params ["_u", "_gi"];
    private _p = ["get", _u] call comspec_sse_fnc_iarpProfile;
    private _g = [_gi, _p, createHashMapFromArray [["state", [_u] call _fsmState], ["tension", _u getVariable ["comspec_sse_idTension", 0]]], []] call comspec_sse_fnc_iarpGen;
    private _pay = [_g select 0, _g select 1, createHashMapFromArray [["sir", ["monsieur", "sir"]]], _g select 2, ""];
    private _range = missionNamespace getVariable ["comspec_sse_iarp_range", 20];
    private _pl = allPlayers select { !(_x isKindOf "HeadlessClient_F") };
    if (hasInterface && {!isNull player}) then { _pl pushBackUnique player; };
    _u setVariable ["comspec_sse_iarpSaidAt", time];
    {
        if (alive _x && {(_x distance _u) <= _range}) then {
            // Nom de l'IA : le Zeus voit son vrai nom et la nature de son identité (fn_iarpName).
            private _args = [_u, "ai", ["for", [_u, _x]] call comspec_sse_fnc_iarpName, _pay, true];
            if (local _x && {hasInterface}) then { ["bubble", _args] call comspec_sse_fnc_iarpClient; } else { ["bubble", _args] remoteExecCall ["comspec_sse_fnc_iarpClient", owner _x]; };
        };
    } forEach _pl;
};
private _do = { params ["_u", "_cmd", ["_args", []]]; ["comspec_sse_iarpAiDo", [_u, _cmd, _args], _u] call CBA_fnc_targetEvent; };

switch (_op) do {
    case "halt": {
        _a params [["_t", objNull, [objNull]], ["_by", objNull, [objNull]]];
        if (isNull _t) exitWith {};
        private _id = (_t getVariable ["comspec_sse_iarpHaltId", 0]) + 1;
        _t setVariable ["comspec_sse_iarpHaltId", _id];
        private _end = time + (missionNamespace getVariable ["comspec_sse_iarp_haltTime", 120]);
        [{
            params ["_args", "_h"];
            _args params ["_t", "_by", "_id", "_end", "_fsmState"];
            // "" : on attend ; "drop" : suivi fini sans rien faire ; "free" : l'IA repart.
            private _why = call {
                if (isNull _t || {!alive _t}) exitWith { "drop" };
                if ((_t getVariable ["comspec_sse_iarpHaltId", 0]) isNotEqualTo _id) exitWith { "drop" };
                private _st = [_t] call _fsmState;
                // L'automate la reprend : il donne lui-même ses ordres (fuite, hostilité, reddition) ; retour au calme : elle repart.
                if (_st in ["fuit", "agressif", "renforts", "rendu"]) exitWith { "drop" };
                if (_st isEqualTo "calme") exitWith { "free" };
                if (time > _end) exitWith { "free" };
                if (isNull _by || {!alive _by} || {(_by distance _t) > (missionNamespace getVariable ["comspec_sse_iarp_haltDist", 25])}) exitWith { "free" };
                ""
            };
            if (_why isEqualTo "") exitWith {};
            [_h] call CBA_fnc_removePerFrameHandler;
            if (_why isEqualTo "free") then {
                _t setVariable ["comspec_sse_iarpHaltId", _id + 1];
                ["comspec_sse_iarpAiDo", [_t, "unhalt", []], _t] call CBA_fnc_targetEvent;
            };
        }, 2, [_t, _by, _id, _end, _fsmState]] call CBA_fnc_addPerFrameHandler;
    };

    case "watch": {
        _a params [["_u", objNull, [objNull]]];
        if (isNull _u || {!alive _u} || {isPlayer _u}) exitWith {};
        _u setVariable ["comspec_sse_iarpWatchAt", time];
        if !(missionNamespace getVariable ["comspec_sse_iarp_shots", true]) exitWith {};
        if ((_u getVariable ["comspec_sse_iarpFnEh", -1]) >= 0) exitWith {};
        private _eh = _u addEventHandler ["FiredNear", {
            params ["_u", "_firer", ["_d", 0]];
            ["fired", [_u, _firer, _d]] call comspec_sse_fnc_iarpReact;
        }];
        _u setVariable ["comspec_sse_iarpFnEh", _eh];
        // IA locale ailleurs (client sans tête, hôte) : le même gestionnaire là-bas, qui prévient le serveur.
        if (!local _u) then { ["comspec_sse_iarpWatchLocal", [_u], _u] call CBA_fnc_targetEvent; };
    };

    case "fired": {
        _a params [["_u", objNull], ["_firer", objNull], ["_d", 0]];
        if (isNull _u || {!alive _u} || {isPlayer _u} || {isNull _firer}) exitWith {};
        private _fsm = [_u] call _fsmState;
        // Plus d'échange depuis 5 min et hors automate : le gestionnaire est retiré (il sera reposé au prochain échange).
        if (_fsm in ["", "calme"] && {(time - (_u getVariable ["comspec_sse_iarpWatchAt", -1000])) > 300}) exitWith {
            private _eh = _u getVariable ["comspec_sse_iarpFnEh", -1];
            if (_eh >= 0) then { _u removeEventHandler ["FiredNear", _eh]; _u setVariable ["comspec_sse_iarpFnEh", -1]; };
        };
        if !(missionNamespace getVariable ["comspec_sse_iarp_shots", true]) exitWith {};
        if ([_u, "shots"] call _off) exitWith {};
        if (_firer isEqualTo _u || {(group _firer) isEqualTo (group _u)} || {_d > (missionNamespace getVariable ["comspec_sse_iarp_shotRange", 40])}) exitWith {};
        // Tir lointain qui ne part pas vers elle (entraînement, combat ailleurs), Zeus à distance : rien.
        private _fw = currentWeapon _firer;
        private _toward = _fw isNotEqualTo "" && {((_firer weaponDirection _fw) vectorDotProduct (vectorNormalized ((aimPos _u) vectorDiff (eyePos _firer)))) > 0.9};
        if (_d > 15 && {!_toward}) exitWith {};
        if (!isNull (getAssignedCuratorLogic _firer) && {(_firer distance _u) > 15}) exitWith {};
        if (time < ((_u getVariable ["comspec_sse_iarpShotAt", -100]) + 8)) exitWith {};
        // Concernée : en conversation (3 min) ou engagée par l'automate ; déjà au combat, menottée, inconsciente : rien.
        if (_fsm in ["", "calme"] && {(time - (_u getVariable ["comspec_sse_iarpTalkAt", -1000])) > 180}) exitWith {};
        if (_fsm in ["agressif", "renforts"]) exitWith {};
        if ((_u getVariable ["ace_captives_isHandcuffed", false]) || {_u getVariable ["ACE_isUnconscious", false]} || {(lifeState _u) isEqualTo "INCAPACITATED"}) exitWith {};
        _u setVariable ["comspec_sse_iarpShotAt", time];
        [_u, 35] call _addShock;
        [_u, 35, format ["tirs proches (%1 m)", round _d], _firer] call _fearLog;
        private _p = ["get", _u] call comspec_sse_fnc_iarpProfile;
        private _inFsm = !(_fsm in ["", "calme"]);
        private _armed = (primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""};
        private _choice = call {
            if (_inFsm && {_armed} && {(_p get "role") in ["insurge", "hvt"]} && {(_p get "aggro") >= 60} && {(random 1) < 0.5}) exitWith { "fight" };
            if (_u getVariable ["ace_captives_isSurrendering", false]) exitWith { "down" };
            if ((_p get "fear") >= 70 || {_d < 12 && {(random 1) < 0.5}}) exitWith { "surrender" };
            if ((_p get "nerve") >= 60 && {(_p get "aggro") < 60} && {isNull objectParent _u} && {(random 1) < 0.6}) exitWith { "flee" };
            "down"
        };
        switch (_choice) do {
            case "fight": { _u setVariable ["comspec_sse_idWant", "agressif"]; };
            case "surrender": {
                if (_inFsm) then { _u setVariable ["comspec_sse_idWant", "rendu"]; } else { [_u, "surrender"] call _do; };
                [_u, "shotsSurrender"] call _speak;
            };
            case "flee": {
                if (_inFsm) then { _u setVariable ["comspec_sse_idWant", "fuit"]; } else {
                    _u setVariable ["comspec_sse_iarpHaltId", (_u getVariable ["comspec_sse_iarpHaltId", 0]) + 1];
                    [_u, "flee", [(getPosATL _u) getPos [150 + random 100, (_firer getDir _u) + (random 40) - 20]]] call _do;
                };
                [_u, "shotsFlee"] call _speak;
            };
            default { [_u, "prone"] call _do; [_u, "shotsDown"] call _speak; };
        };
    };

    case "witness": {
        _a params [["_v", objNull, [objNull]], ["_by", objNull, [objNull]]];
        if (isNull _v || {!(missionNamespace getVariable ["comspec_sse_iarp_witness", true])}) exitWith {};
        if (time < ((_v getVariable ["comspec_sse_iarpWitAt", -100]) + 20)) exitWith {};
        _v setVariable ["comspec_sse_iarpWitAt", time];
        private _range = missionNamespace getVariable ["comspec_sse_iarp_witnessRange", 30];
        // Les 8 civils les plus proches au plus, puis ceux qui voient la scène (5 au plus).
        private _c = ((_v nearEntities ["CAManBase", _range]) select {
            alive _x && {!isPlayer _x} && {_x isNotEqualTo _v} && {_x isNotEqualTo _by} && {(side group _x) isEqualTo civilian}
                && {!(_x getVariable ["ACE_isUnconscious", false])} && {!(_x getVariable ["ace_captives_isHandcuffed", false])}
                && {!([_x, "witness"] call _off)}
        }) apply { [_x distance _v, _x] };
        _c sort true;
        private _n = 0;
        {
            if (_n >= 5) exitWith {};
            private _w = _x select 1;
            if (([_w, "VIEW", _v] checkVisibility [eyePos _w, aimPos _v]) > 0.3) then {
                _n = _n + 1;
                [_w, 25] call _addShock;
                [_w, 25, "violence vue (civil menacé ou braqué)", _by] call _fearLog;
                if (([_w] call _fsmState) in ["", "calme"]) then {
                    // Hors automate : elle regarde la scène, ou s'enfuit si elle est peureuse et déjà secouée.
                    private _fear = (["get", _w] call comspec_sse_fnc_iarpProfile) get "fear";
                    if (([_w] call _shockOf) >= 40 && {_fear >= 60} && {isNull objectParent _w} && {(random 1) < 0.5}) then {
                        [_w, "flee", [(getPosATL _w) getPos [120 + random 80, (_v getDir _w) + (random 60) - 30]]] call _do;
                    } else {
                        [_w, "look", [_v]] call _do;
                    };
                };
            };
        } forEach (_c select [0, 8]);
        _n
    };

    case "shock": { ([_a] call _shockOf) + ([_a] call _pressOf) };
    case "press": {
        _a params [["_u", objNull, [objNull]], ["_d", 0, [0]], ["_reason", "", [""]], ["_by", objNull, [objNull]]];
        if (isNull _u || {_d isEqualTo 0}) exitWith {};
        _u setVariable ["comspec_sse_iarpPress", [((([_u] call _pressOf) + _d) max -30) min 60, time]];
        [_u, _d, _reason, _by] call _fearLog;
    };
    case "aimedAt": {
        _a params [["_u", objNull, [objNull]]];
        if (isNull _u) exitWith { [0, 1e6] };
        private _n = 0;
        private _near = 1e6;
        {
            private _w = currentWeapon _x;
            if (alive _x && {_x isNotEqualTo _u} && {_w isNotEqualTo ""} && {_w isNotEqualTo (binocular _x)} && {!(weaponLowered _x)}) then {
                private _d = _x distance _u;
                // Canon à 8° de son buste ou, de près, à moins de 0,6 m de lui : à 3 m, viser la tête sort du cône de 8°.
                private _wd = _x weaponDirection _w;
                private _to = (aimPos _u) vectorDiff (eyePos _x);
                private _al = _to vectorDotProduct _wd;
                if (_d <= 25 && {_al > 0} && {(_wd vectorDotProduct (vectorNormalized _to)) > 0.99 || {(vectorMagnitude (_to vectorDiff (_wd vectorMultiply _al))) < 0.6}}) then { _n = _n + 1; _near = _near min _d; };
            };
        } forEach (_u nearEntities ["CAManBase", 25]);
        [_n, _near]
    };
    case "level": {
        private _u = _a;
        if !(_u isEqualType objNull) exitWith { 0 };
        (["aimedAt", [_u]] call comspec_sse_fnc_iarpReact) params ["_n", "_near"];
        private _aim = if (_n > 0) then { ([10, 18, 26] select ((parseNumber (_near <= 12)) + (parseNumber (_near <= 5)))) + 8 * ((_n - 1) min 2) } else { 0 };
        (((([_u] call _shockOf) + ([_u] call _pressOf) + _aim) max 0) min 100)
    };
};
