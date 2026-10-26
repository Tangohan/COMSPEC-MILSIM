/*
    Ordres sans Athena (ATAK local, mode full local, poste TOC) : l'ordre part au camp par le serveur
    (comspec_atak_native_sideEvent, rejoué aux arrivants), arrive dans l'app Tâches des destinataires
    (COMSPEC_ATAK_Data "localTasks", lu par fn_tasksAll) ; les réponses REÇU / EN COURS / TERMINÉ passent par fn_orderAck.
      "local"  -> true quand les ordres passent en local (pas de COMSPEC Link, ATAK local, ou poste TOC sans Athena)
      "can"    -> true si je peux émettre : opérateur d'un PC de TOC, chef de groupe, joueur seul, rôle chef / commandant
      "send" [type, libellé du type, genre de cible ("all" | "group"), clé du groupe, nom de la cible, consigne, priorité,
              PC du poste (fn_tocLaptop : émetteur « TOC · indicatif », envoi direct par le réseau filaire du poste),
              position de l'objectif [x, y] ou []] -> id
      "recv" [ordre en paires [[clé, valeur]...], camp]   (événement comspec_atak_native_localOrder)
    Tout passe en tableaux simples (jamais de HashMap sur le réseau).
*/
params [["_act", ""], ["_arg", []]];
switch (_act) do {
    case "local": {
        !([] call comspec_atak_native_fnc_bridge) || {([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on"} || {isNil "comspec_overwatch_connect_fnc_issueOrder"}
    };
    case "can": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { false };
        if (([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo "toc") exitWith { true };
        if (!isNil "comspec_overwatch_connect_fnc_canIssueOrder") exitWith { [] call comspec_overwatch_connect_fnc_canIssueOrder };
        private _g = group player;
        (leader _g) isEqualTo player || {({isPlayer _x} count (units _g)) <= 1}
            || {private _r = toLower (roleDescription player); (_r find "chef") >= 0 || {(_r find "command") >= 0} || {(_r find "leader") >= 0}}
    };
    case "send": {
        _arg params [["_kind", "MOVE"], ["_typeLabel", ""], ["_tType", "all"], ["_tRef", ""], ["_tLabel", ""], ["_payload", ""], ["_prio", "ROUTINE"], ["_direct", false], ["_pos", []]];
        private _toc = _direct || {([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo "toc"};
        private _issuer = if (_toc) then {
            format ["TOC · %1", ((["get"] call comspec_atak_native_fnc_localCfg) get "toc") get "callsign"]
        } else {
            ([[player, true] call comspec_atak_native_fnc_unitCallsign, name player] select { _x isNotEqualTo "" }) param [0, name player]
        };
        private _id = format ["L-%1-%2", getPlayerUID player, floor (serverTime * 10)];
        private _o = [
            ["id", _id], ["type", _kind], ["typeLabel", _typeLabel], ["target", ["", _tLabel] select (_tType isNotEqualTo "all")],
            ["priority", _prio], ["issuer", _issuer], ["issuerUid", getPlayerUID player], ["status", "NEW"], ["payload", _payload],
            ["targetType", _tType], ["targetRef", _tRef], ["source", "local"], ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        ];
        if ((count _pos) >= 2) then { _o pushBack ["pos", [_pos select 0, _pos select 1]]; };
        private _side = str side group player;
        ["recv", [_o, _side]] call comspec_atak_native_fnc_orderLocal;
        if (_direct) exitWith { ["comspec_atak_native_sideEvent", ["comspec_atak_native_localOrder", [_o, _side], _side]] call CBA_fnc_serverEvent; _id };
        [{ ["comspec_atak_native_sideEvent", ["comspec_atak_native_localOrder", _this, _this select 1]] call CBA_fnc_serverEvent; }, [_o, _side], "Ordre", 2] call comspec_atak_native_fnc_netSend;
        _id
    };
    case "recv": {
        _arg params [["_pairs", []], ["_side", ""]];
        if (!hasInterface || {isNull player} || {_side isNotEqualTo str side group player}) exitWith { false };
        private _o = createHashMapFromArray _pairs;
        private _id = _o getOrDefault ["id", ""];
        if (_id isEqualTo "") exitWith { false };
        private _mine = (_o getOrDefault ["issuerUid", ""]) isEqualTo getPlayerUID player;
        private _ref = _o getOrDefault ["targetRef", ""];
        private _tg = toLower (_o getOrDefault ["target", ""]);
        private _forMe = (_o getOrDefault ["targetType", "all"]) isEqualTo "all" || {_tg isEqualTo ""}
            || {_ref isEqualTo ([player] call comspec_atak_native_fnc_unitGroup)} || {_tg isEqualTo toLower groupId group player}
            || {_tg isEqualTo toLower name player};
        // L'émetteur (poste TOC, chef) garde ses ordres pour suivre les réponses.
        if (!_forMe && {!_mine}) exitWith { false };
        private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
        private _tasks = _data getOrDefault ["localTasks", createHashMap];
        if (_id in _tasks) exitWith { false };
        _tasks set [_id, _o];
        // Les plus anciens partent au-delà de 40 ordres.
        if ((count _tasks) > 40) then {
            private _old = (keys _tasks) apply { [(_tasks get _x) getOrDefault ["time", ""], _x] };
            _old sort true;
            { _tasks deleteAt (_x select 1); } forEach (_old select [0, (count _tasks) - 40]);
        };
        _data set ["localTasks", _tasks];
        if (_forMe && {!_mine} && {!(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false])} && {[player] call comspec_atak_native_fnc_hasDevice}) then {
            private _lbl = _o getOrDefault ["typeLabel", ""];
            if (_lbl isEqualTo "") then { _lbl = _o getOrDefault ["type", "ORDRE"]; };
            ["TACTICAL", format ["Nouvel ordre · %1 · de %2", _lbl, _o getOrDefault ["issuer", "TOC"]], 8, 60, "TASK"] call comspec_atak_native_fnc_notify;
            [] call comspec_atak_native_fnc_vibrate;
        };
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) in ["TASK", "TOC"]) then { [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) get "activePage"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        true
    };
    default { false };
}
