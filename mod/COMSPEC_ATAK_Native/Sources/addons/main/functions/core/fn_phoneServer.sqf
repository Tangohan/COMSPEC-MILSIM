/*
    Téléphones personnels (serveur) : codes, essais et données. Rien de cela n'est diffusé aux clients.
    Événement CBA serveur comspec_atak_native_phoneSrv : [action, arguments] ; réponse comspec_atak_native_phoneCli.
    Les arguments sont contrôlés en type et en taille. Un client modifié peut toujours mentir : le code est une
    mécanique de jeu, pas une sécurité. La remise à zéro passe par fn_adminServer, seul juge des droits.
    Mission : sur une IA, COMSPEC_ATAK_PhonePin (4 à 8 chiffres), COMSPEC_ATAK_PhoneHint et COMSPEC_ATAK_PhoneData
    ([["msgs", [[heure, auteur, texte]...]], ["notes", [[titre, texte]...]], ["marks", [[grille, texte]...]]]).
    Téléphone d'IA lu : ses données portent aussi « ai_uid » (variable publique comspec_sse_aiUid, posée ici si absente).
    Params : [action, arguments]
      "init"                          "pin" [unité, empreinte, indice]        "store" [unité, ids, données]
      "adopt" [id, clé, unité d'origine]    "unlock" [unité, id, clé, empreinte]    "forget" [id]    "reset" [clé]
*/
if (!isServer) exitWith {};
params [["_act", ""], ["_xa", []]];
private _map = {
    params ["_name"];
    private _m = missionNamespace getVariable [_name, createHashMap];
    missionNamespace setVariable [_name, _m];
    _m
};
private _pins = ["COMSPEC_ATAK_PhonePins"] call _map;
private _tries = ["COMSPEC_ATAK_PhoneTries"] call _map;
private _store = ["COMSPEC_ATAK_PhoneStore"] call _map;
private _resets = ["COMSPEC_ATAK_PhoneResets"] call _map;
private _now = [time, serverTime] select isMultiplayer;
private _send = { params ["_kind", "_data", "_to"]; ["comspec_atak_native_phoneCli", [_kind, _data], _to] call CBA_fnc_targetEvent; };
private _playerOf = {
    params ["_key"];
    private _i = allPlayers findIf { (getPlayerUID _x) isEqualTo _key };
    if (_i >= 0) then { allPlayers select _i } else { objNull }
};
switch (_act) do {
    case "init": {
        ["comspec_atak_native_phoneSrv", {
            params [["_a", ""], ["_args", []]];
            if (_a isEqualType "" && {_a in ["pin", "store", "adopt", "unlock", "forget"]} && {_args isEqualType []}) then {
                [_a, _args] call comspec_atak_native_fnc_phoneServer;
            };
        }] call CBA_fnc_addEventHandler;
    };
    case "pin": {
        _xa params [["_unit", objNull, [objNull]], ["_hash", "", [""]], ["_hint", "", [""]]];
        if (isNull _unit || {(count _hash) > 32}) exitWith {};
        private _key = getPlayerUID _unit;
        if (_key isEqualTo "") exitWith {};
        // Remis à zéro pendant son absence : l'ancien code n'est pas repris, sa machine l'oublie aussi.
        if (_hash isNotEqualTo "" && {_key in _resets}) exitWith {
            _resets deleteAt _key;
            ["cleared", "admin", _unit] call _send;
        };
        if (_hash isEqualTo "") then { _pins deleteAt _key; } else { _pins set [_key, [_hash, _hint select [0, 40]]]; };
        _tries deleteAt _key;
    };
    case "store": {
        _xa params [["_unit", objNull, [objNull]], ["_ids", [], [[]]], ["_pairs", [], [[]]]];
        if (isNull _unit || {(count str _pairs) > 16000}) exitWith {};
        private _prefix = getPlayerUID _unit;
        {
            if (_x isEqualType "" && {(_x select [0, count _prefix]) isEqualTo _prefix}) then {
                _store set [_x, _pairs];
            };
        } forEach (_ids select [0, 12]);
        // Borne de mémoire pour une partie très longue.
        if ((count _store) > 200) then { _store deleteAt ((keys _store) select 0); };
    };
    case "adopt": {
        _xa params [["_id", "", [""]], ["_key", "", [""]], ["_from", objNull, [objNull]]];
        if (_id isEqualTo "" || {_id in _store}) exitWith {};
        private _live = [_key] call _playerOf;
        if (!isNull _live) exitWith { ["want", [_id], _live] call _send; };
        if (isNull _from) exitWith {};
        // IA : code et contenu donnés par la mission, identité calculée.
        private _pin = _from getVariable ["COMSPEC_ATAK_PhonePin", ""];
        if (_pin isEqualType 0) then { _pin = str _pin; };
        if (_pin isEqualType "" && {_pin regexMatch "^[0-9]{4,8}$"}) then {
            private _hint = _from getVariable ["COMSPEC_ATAK_PhoneHint", ""];
            if !(_hint isEqualType "") then { _hint = ""; };
            _pins set [_key, [["hash", [_key, _pin]] call comspec_atak_native_fnc_phoneOwn, _hint select [0, 40]]];
        };
        private _data = _from getVariable ["COMSPEC_ATAK_PhoneData", []];
        if !(_data isEqualType [] && {(count str _data) <= 16000}) then { _data = []; };
        ([_from] call comspec_atak_native_fnc_phoneIdent) params ["_num", "_imei"];
        // Identifiant stable de l'IA (owner.ai_uid de la synchro web, fn_phoneSync ; même variable que le SSE) : variable
        // publique comspec_sse_aiUid, posée ici si la mission ou le SSE ne l'a pas fait. Nom de variable Eden : même valeur
        // d'une session à l'autre ; sinon un identifiant de cette session.
        private _ai = [];
        if (!isPlayer _from && {(_key select [0, 3]) isEqualTo "AI_"}) then {
            private _aiUid = _from getVariable ["comspec_sse_aiUid", ""];
            if !(_aiUid isEqualType "" && {_aiUid isNotEqualTo ""}) then {
                private _vn = vehicleVarName _from;
                private _n = (missionNamespace getVariable ["COMSPEC_ATAK_AiUidSeq", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_ATAK_AiUidSeq", _n];
                _aiUid = if (_vn isNotEqualTo "") then { format ["%1.%2.%3", missionName, worldName, _vn] } else { format ["ai.%1.%2.%3", worldName, round serverTime, _n] };
                _aiUid = (_aiUid regexReplace ["[^A-Za-z0-9_.:-]", "_"]) select [0, 61];
                _from setVariable ["comspec_sse_aiUid", _aiUid, true];
            };
            _ai = [["ai_uid", _aiUid select [0, 61]]];
        };
        _store set [_id, [["wiped", false], ["number", _num], ["imei", _imei], ["callsign", ""], ["at", ""]] + _ai + (_data select { _x isEqualTypeArray ["", []] })];
    };
    case "unlock": {
        _xa params [["_unit", objNull, [objNull]], ["_id", "", [""]], ["_key", "", [""]], ["_hash", "", [""]]];
        if (isNull _unit || {_id isEqualTo ""}) exitWith {};
        private _pin = _pins getOrDefault [_key, []];
        private _hint = _pin param [1, ""];
        private _max = (missionNamespace getVariable ["comspec_atak_native_pin_attempts", 5]) max 1;
        private _block = (missionNamespace getVariable ["comspec_atak_native_pin_block", 120]) max 10;
        (_tries getOrDefault [_key, [0, -1]]) params ["_n", "_until"];
        private _reply = switch (true) do {
            case (_pin isEqualTo []): { "open" };
            case (_now < _until): { "blocked" };
            case (_hash isEqualTo ""): { "locked" };
            case (_hash isEqualTo (_pin select 0)): { "open" };
            default { "bad" };
        };
        switch (_reply) do {
            case "open": {
                _tries deleteAt _key;
                ["state", [_id, "open", _hint, 0, 0, _store getOrDefault [_id, []]], _unit] call _send;
            };
            case "blocked": { ["state", [_id, "blocked", _hint, 0, _until - _now], _unit] call _send; };
            case "locked": { ["state", [_id, "locked", _hint, _max - _n, 0], _unit] call _send; };
            default {
                _n = _n + 1;
                if (_n >= _max) then {
                    _tries set [_key, [0, _now + _block]];
                    diag_log format ["[COMSPEC ATAK NATIVE][INFO][PHONE] Téléphone %1 bloqué %2 s après %3 essais de %4", _id, _block, _max, name _unit];
                    ["state", [_id, "blocked", _hint, 0, _block], _unit] call _send;
                } else {
                    _tries set [_key, [_n, -1]];
                    ["state", [_id, "bad", _hint, _max - _n, 0], _unit] call _send;
                };
            };
        };
    };
    case "forget": {
        _xa params [["_id", "", [""]]];
        _store deleteAt _id;
    };
    case "reset": {
        _xa params [["_key", "", [""]]];
        if (_key isEqualTo "") exitWith { false };
        _pins deleteAt _key;
        _tries deleteAt _key;
        private _live = [_key] call _playerOf;
        if (isNull _live) then { _resets set [_key, true]; } else { ["cleared", "admin", _live] call _send; };
        true
    };
};
