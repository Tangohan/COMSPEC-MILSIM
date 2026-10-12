/*
    Suivi des ordres par joueur (app Tâches) : qui l'a vu, qui l'a reçu, qui l'exécute, qui a terminé.
      "send" [id, statut] : ma réponse, diffusée à mon camp et gardée par le serveur (arrivée en cours de partie).
                            Le chef d'un groupe de plusieurs joueurs répond pour tout son groupe (sauf « VU »).
      "recv" [id, statut, camp, uid, nom, membres [[uid, nom]...], pour le groupe, heure, groupe] :
                            réception (événement comspec_atak_native_orderAck).
      "seen" [id] : « VU » envoyé une seule fois, à l'affichage de l'ordre.
      "list" [id] : HashMap uid -> [nom, statut, heure, répondu par le chef (nom ou ""), groupe].
      "rank" [statut] : ordre des statuts (un « VU » n'écrase jamais une vraie réponse).
    Tout passe en tableaux simples (jamais de HashMap sur le réseau).
*/
params [["_action", ""], ["_args", []]];
private _store = missionNamespace getVariable ["COMSPEC_ATAK_OrderAcks", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_OrderAcks", _store];
private _rank = { params ["_st"]; (["SEEN", "ACK", "EXEC", "DONE", "FAILED"] find (toUpper _st)) max 0 };
switch (toLower _action) do {
    case "rank": { [_args param [0, ""]] call _rank };
    case "list": { _store getOrDefault [_args param [0, ""], createHashMap] };
    case "seen": {
        private _id = _args param [0, ""];
        if (_id isEqualTo "") exitWith { false };
        private _sent = missionNamespace getVariable ["COMSPEC_ATAK_OrderSeenSent", []];
        if (_id in _sent) exitWith { false };
        _sent pushBack _id;
        if ((count _sent) > 100) then { _sent deleteAt 0; };
        missionNamespace setVariable ["COMSPEC_ATAK_OrderSeenSent", _sent];
        ["send", [_id, "SEEN"]] call comspec_atak_native_fnc_orderAck;
        true
    };
    case "send": {
        _args params [["_id", ""], ["_status", "ACK"]];
        if (_id isEqualTo "") exitWith { false };
        _status = toUpper _status;
        private _grp = group player;
        private _lead = _status isNotEqualTo "SEEN" && {(leader _grp) isEqualTo player} && {({isPlayer _x} count (units _grp)) > 1};
        private _members = [[getPlayerUID player, name player]];
        if (_lead) then {
            _members = ((units _grp) select { isPlayer _x && {alive _x} }) apply { [getPlayerUID _x, name _x] };
        };
        private _ev = [_id, _status, str side _grp, getPlayerUID player, name player, _members, _lead, [dayTime, "HH:MM"] call BIS_fnc_timeToString, groupId _grp];
        ["recv", _ev] call comspec_atak_native_fnc_orderAck;
        ["comspec_atak_native_sideEvent", ["comspec_atak_native_orderAck", _ev, str side _grp]] call CBA_fnc_serverEvent;
        _lead
    };
    case "recv": {
        _args params [["_id", ""], ["_status", ""], ["_side", ""], ["_byUid", ""], ["_byName", ""], ["_members", []], ["_lead", false], ["_time", ""], ["_gname", ""]];
        if (_id isEqualTo "" || {_side isNotEqualTo str side group player}) exitWith {};
        private _m = _store getOrDefault [_id, createHashMap];
        private _newRank = [_status] call _rank;
        {
            _x params [["_uid", ""], ["_nm", ""]];
            if (_uid isEqualTo "") then { continue };
            private _old = _m getOrDefault [_uid, []];
            // « VU » ne remplace pas une réponse déjà donnée ; les autres statuts se succèdent librement.
            if (_status isEqualTo "SEEN" && {(count _old) > 0}) then { continue };
            _m set [_uid, [_nm, _status, _time, ["", _byName] select (_lead && {_uid isNotEqualTo _byUid}), _gname]];
        } forEach _members;
        _store set [_id, _m];
        // Le chef a répondu pour mon groupe : mon ordre prend le même statut.
        private _me = getPlayerUID player;
        if (_lead && {_byUid isNotEqualTo _me} && {(_members findIf { (_x select 0) isEqualTo _me }) >= 0}) then {
            private _task = ([] call comspec_atak_native_fnc_tasksAll) getOrDefault [_id, createHashMap];
            if ((count _task) > 0 && {_newRank >= ([_task getOrDefault ["status", ""]] call _rank)}) then { _task set ["status", _status]; };
            if !(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]) then {
                private _lbl = createHashMapFromArray [["ACK", "REÇU"], ["EXEC", "EN COURS"], ["DONE", "TERMINÉ"], ["FAILED", "IMPOSSIBLE"]];
                ["INFO", format ["%1 a répondu %2 pour le groupe", _byName, _lbl getOrDefault [_status, _status]], 3, 30] call comspec_atak_native_fnc_notify;
            };
        };
    };
};
