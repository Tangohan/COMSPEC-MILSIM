/*
    Traçage d'un numéro, côté serveur : seul le serveur lit les positions, l'état des téléphones et la couverture.
    Le téléphone demande « tracer ce numéro » (fn_traceAction, remoteExecCall) ; le serveur répond après le délai de la
    requête opérateur, au seul demandeur, avec une position approximative et un rayon. Rien n'est diffusé à tous :
    aucune position d'une unité non tracée ne quitte le serveur.
    Params : [action, arguments]
      "req" [demandeur, numéro saisi, "manual" | "track", jeton]
            Appelant vérifié (un client ne demande pas au nom d'un autre). Refus : DISABLED (traçage coupé), DENIED
            (droit : opérateur GE ou renseignement, mandat du Zeus), NOLINK (pas de liaison avec l'opérateur),
            WAIT (requête trop tôt, attente en secondes), CAP (trop de numéros suivis).
      "index" ["force"] -> HashMap numéro normalisé -> [objet, genre ("NUMÉRO" | "IMEI" | "MAC")], gardé 20 s :
            téléphones ATAK (joueurs, IA qui en ont un), numéros des profils SSE (identité, appareils, champs Eden),
            numéro Eden ou Zeus de l'ATAK (COMSPEC_ATAK_PhoneNumber), IA réglées « Téléphone » sans numéro :
            numéro calculé de façon déterministe (fn_phoneIdent).
      "find" [numéro normalisé] -> [objet, genre, numéro de l'index] ou []
      "fix" [objet, genre, numéro de l'index, demandeur] -> [code, position, rayon, instant]
      "coverage" [position ATL, camp] -> [antennes qui entendent le téléphone, distance de la plus proche]
            Relais ATAK en service du camp (ou sans camp) qui l'entendent (relief compris, fn_relayNet « link »), puis,
            si le réseau mobile civil est actif, antennes civiles des localités proches : ville 2, village 1, rien à
            plus de 6 km (campagne isolée : hors couverture).
    Réponse : événement CBA comspec_atak_native_traceRes chez le demandeur :
      [jeton, numéro saisi, numéro normalisé, code, position ([] ou [x, y, 0]), rayon, instant du relevé, genre, attente s]
      code OK | SOS (position) ; OFF | JAM | NOCOV (dernière position connue si le serveur en a une) ; UNKNOWN ; OWN
      (numéro de son propre camp, non traçable sur cette mission) ; refus ci-dessus. Instant : horloge de mission
      (time en solo, serverTime en multijoueur), comme le client.
    Dernière position connue : gardée sur le serveur par numéro (COMSPEC_ATAK_TraceLast, jamais publique).
*/
params [["_act", ""], ["_arg", []]];
if (!isServer) exitWith { [] };
private _now = [time, serverTime] select isMultiplayer;
private _calc = comspec_atak_native_fnc_traceCalc;
private _sideKey = { params ["_s"]; switch (_s) do { case west: { "west" }; case east: { "east" }; case resistance: { "guer" }; default { "civ" }; } };
private _jammedAt = {
    params ["_pos", "_side"];
    ((missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) findIf {
        _x params [["_jp", [0, 0, 0]], ["_jr", 500], "", ["_js", ""], ["_ju", 1e9], ["_jx", false]];
        if (_jp isEqualType objNull) then { _jp = if (isNull _jp) then { [] } else { getPosATL _jp }; };
        (count _jp) >= 2 && {_ju >= _now} && {!(_jx && {_js isEqualTo _side})} && {(_pos distance2D [_jp select 0, _jp select 1]) < _jr}
    }) >= 0
};

switch (_act) do {
    case "req": {
        _arg params [["_unit", objNull, [objNull]], ["_q", "", [""]], ["_mode", "manual", [""]], ["_token", 0, [0]]];
        if (isNull _unit) exitWith {};
        if (isRemoteExecuted && {!(remoteExecutedOwner in [0, 2, clientOwner])} && {(owner _unit) isNotEqualTo remoteExecutedOwner}) exitWith {
            ["WARNING", "TRACE", format ["Requête refusée : client %1 pour %2", remoteExecutedOwner, name _unit]] call comspec_atak_native_fnc_log;
        };
        _q = _q select [0, 40];
        private _qn = ["norm", [_q]] call _calc;
        private _reply = {
            params ["_code", ["_pos", []], ["_rad", 0], ["_at", -1], ["_kind", ""], ["_wait", 0]];
            ["comspec_atak_native_traceRes", [_token, _q, _qn, _code, _pos, _rad, _at, _kind, _wait], _unit] call CBA_fnc_targetEvent;
        };
        if !(missionNamespace getVariable ["comspec_atak_native_geoloc_enabled", true]) exitWith { ["DISABLED"] call _reply; };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_TraceOff", false]) isEqualTo true) exitWith { ["DISABLED"] call _reply; };
        if !(["may", _unit] call comspec_atak_native_fnc_traceAction) exitWith { ["DENIED"] call _reply; };
        if ((count _qn) < 8) exitWith { ["UNKNOWN"] call _reply; };
        // Liaison avec l'opérateur : téléphone du demandeur sur le réseau (barres publiées) et hors brouillage.
        if (["needLink"] call comspec_atak_native_fnc_traceAction) then {
            private _bars = (_unit getVariable ["COMSPEC_ATAK_Pub", []]) param [1, 4];
            if (!(_bars isEqualType 0) || {_bars <= 0} || {[getPosATL _unit, str side group _unit] call _jammedAt}) then { _qn = ""; };
        };
        if (_qn isEqualTo "") exitWith { ["NOLINK"] call _reply; };
        // Limites : une requête toutes les 2 s, attente réglée entre deux traçages, suivi à son rythme et plafonné.
        private _uid = getPlayerUID _unit;
        if (_uid isEqualTo "") then { _uid = str _unit; };
        private _rates = missionNamespace getVariable ["COMSPEC_ATAK_TraceRate", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TraceRate", _rates];
        private _r = _rates getOrDefault [_uid, createHashMapFromArray [["any", -1e9], ["manual", -1e9], ["tracks", createHashMap]]];
        _rates set [_uid, _r];
        private _wait = ["cooldown", [_r get "any", _now, 2]] call _calc;
        private _tracks = _r get "tracks";
        private _every = ((missionNamespace getVariable ["comspec_atak_native_geoloc_refresh", 30]) max 10);
        if (_mode isEqualTo "track") then {
            // Suivi : chaque numéro à son rythme (2 s de tolérance), au plus N numéros suivis dans la même fenêtre.
            _wait = _wait max (["cooldown", [_tracks getOrDefault [_qn, -1e9], _now, _every - 2]] call _calc);
            private _live = (keys _tracks) select { (_now - (_tracks get _x)) < (2.5 * _every) && {_x isNotEqualTo _qn} };
            if ((count _live) >= ((missionNamespace getVariable ["comspec_atak_native_trace_max", 3]) max 1)) then { _wait = -1; };
        } else {
            _wait = _wait max (["cooldown", [_r get "manual", _now, (missionNamespace getVariable ["comspec_atak_native_trace_cooldown", 20]) max 0]] call _calc);
        };
        if (_wait < 0) exitWith { ["CAP"] call _reply; };
        if (_wait > 0) exitWith { ["WAIT", [], 0, -1, "", ceil _wait] call _reply; };
        _r set ["any", _now];
        if (_mode isEqualTo "track") then { _tracks set [_qn, _now]; } else { _r set ["manual", _now]; };
        // Requête opérateur : jamais instantanée (délai réglé, ±20 %) ; un suivi déjà ouvert répond plus vite.
        private _delay = ((missionNamespace getVariable ["comspec_atak_native_trace_delay", 8]) max 1) * (0.8 + random 0.4);
        if (_mode isEqualTo "track") then { _delay = (_delay / 2) max 1; };
        [{
            params ["_unit", "_q", "_qn", "_token"];
            if (isNull _unit) exitWith {};
            private _now = [time, serverTime] select isMultiplayer;
            private _hit = ["find", [_qn]] call comspec_atak_native_fnc_traceServer;
            private _send = {
                params ["_code", ["_pos", []], ["_rad", 0], ["_at", -1], ["_kind", ""]];
                ["comspec_atak_native_traceRes", [_token, _q, _qn, _code, _pos, _rad, _at, _kind, 0], _unit] call CBA_fnc_targetEvent;
            };
            if (_hit isEqualTo []) exitWith { ["UNKNOWN"] call _send; };
            _hit params ["_o", "_kind", "_key"];
            (["fix", [_o, _kind, _key, _unit]] call comspec_atak_native_fnc_traceServer) params ["_code", "_pos", "_rad", ["_at", _now]];
            [_code, _pos, _rad, _at, _kind] call _send;
            ["INFO", "TRACE", format ["%1 trace %2 : %3", name _unit, _q, _code]] call comspec_atak_native_fnc_log;
        }, [_unit, _q, _qn, _token], _delay] call comspec_atak_native_fnc_waitAndExec;
    };
    case "index": {
        private _c = missionNamespace getVariable ["COMSPEC_ATAK_TraceIdx", []];
        if ((count _c) isEqualTo 2 && {(diag_tickTime - (_c select 0)) < 20} && {_arg isNotEqualTo "force"}) exitWith { _c select 1 };
        private _map = createHashMap;
        private _add = {
            params ["_num", "_o", "_kind"];
            if !(_num isEqualType "") exitWith {};
            private _k = ["norm", [_num]] call _calc;
            if ((count _k) >= 8 && {!(_k in _map)}) then { _map set [_k, [_o, _kind]]; };
        };
        private _ai = missionNamespace getVariable ["comspec_atak_native_geoloc_ai", true];
        private _sse = !isNil "comspec_sse_fnc_getSection";
        {
            private _u = _x;
            // Réglage « IA géolocalisables » décoché : aucune IA n'est traçable.
            if (!isPlayer _u && {!_ai}) then { continue; };
            private _set = _u getVariable ["COMSPEC_ATAK_PhoneTrace", ""];
            if !(_set isEqualType "") then { _set = ""; };
            // Téléphone ATAK : numéro, IMEI et adresse MAC (joueurs ; IA seulement si le réglage les rend traçables).
            if ([_u] call comspec_atak_native_fnc_hasDevice) then {
                private _ids = [_u] call comspec_atak_native_fnc_phoneIdent;
                { [_x, _u, ["NUMÉRO", "IMEI", "MAC"] select _forEachIndex] call _add; } forEach _ids;
            };
            if (!isPlayer _u) then {
                // Numéro posé dans Eden ou par le Zeus, puis profil SSE (champs Eden, identité générée, appareils).
                [_u getVariable ["COMSPEC_ATAK_PhoneNumber", ""], _u, "NUMÉRO"] call _add;
                [_u getVariable ["comspec_sse_personPhone", ""], _u, "NUMÉRO"] call _add;
                [_u getVariable ["comspec_sse_phoneNumber", ""], _u, "NUMÉRO"] call _add;
                if (_sse && {!isNil { _u getVariable "comspec_sse_data" }}) then {
                    private _id = [_u, "identity"] call comspec_sse_fnc_getSection;
                    if (!isNil "_id" && {_id isEqualType createHashMap}) then { [_id getOrDefault ["phone", ""], _u, "NUMÉRO"] call _add; };
                    private _dev = [_u, "digitalDevices"] call comspec_sse_fnc_getSection;
                    if (!isNil "_dev" && {_dev isEqualType []}) then {
                        { if (_x isEqualType createHashMap) then { [_x getOrDefault ["phoneNumber", ""], _u, "NUMÉRO"] call _add; }; } forEach _dev;
                    };
                };
                // IA réglée « Téléphone : allumé / éteint » sans numéro : numéro calculé, toujours le même pour cette unité.
                if (_set in ["on", "off"]) then { [([_u] call comspec_atak_native_fnc_phoneIdent) select 0, _u, "NUMÉRO"] call _add; };
            };
        } forEach (allUnits + allDeadMen);
        // Registre SSE des numéros (couches de renseignement) : téléphones posés, saisis ou d'une personne déjà générée.
        private _logical = missionNamespace getVariable ["comspec_sse_logicalEntities", createHashMap];
        if (_ai && {_logical isEqualType createHashMap}) then {
            {
                if (_y isEqualType createHashMap && {(_y getOrDefault ["kind", ""]) isEqualTo "PHONE_NUMBER"}) then {
                    private _o = objectFromNetId (_y getOrDefault ["linkedEntity", ""]);
                    if (!isNull _o) then { [_x, _o, "NUMÉRO"] call _add; };
                };
            } forEach _logical;
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TraceIdx", [diag_tickTime, _map]];
        _map
    };
    case "find": {
        _arg params [["_qn", ""]];
        private _map = ["index"] call comspec_atak_native_fnc_traceServer;
        private _key = _qn;
        private _hit = _map getOrDefault [_qn, []];
        if (_hit isEqualTo [] || {isNull (_hit select 0)}) then {
            _map = ["index", "force"] call comspec_atak_native_fnc_traceServer;
            _hit = _map getOrDefault [_qn, []];
        };
        // Numéro saisi sans indicatif (ou avec) : mêmes 9 derniers chiffres.
        if (_hit isEqualTo []) then {
            private _keys = keys _map;
            private _k = _keys findIf { ["same", [_x, _qn]] call _calc };
            if (_k >= 0) then { _key = _keys select _k; _hit = _map get _key; };
        };
        if (_hit isEqualTo [] || {isNull (_hit select 0)}) exitWith { [] };
        [_hit select 0, _hit select 1, _key]
    };
    case "coverage": {
        _arg params [["_pos", [0, 0, 0]], ["_side", "CIV"]];
        private _tp = (AGLToASL _pos) vectorAdd [0, 0, 1.2];
        private _heard = [];
        {
            if !(_x select 6) then { continue };
            if ((_x select 3) isNotEqualTo "relay") then { continue };
            private _l = ["link", [_x select 1, _x select 4, _x select 5, _tp]] call comspec_atak_native_fnc_relayNet;
            if ((_l select 0) > 0) then { _heard pushBack ((_x select 1) distance2D _tp); };
        } forEach (["list", [_side]] call comspec_atak_native_fnc_relayNet);
        private _civ = missionNamespace getVariable ["COMSPEC_ATAK_TraceCivNet", missionNamespace getVariable ["comspec_atak_native_trace_civnet", true]];
        if (_civ isEqualTo true) then {
            {
                private _d = _pos distance2D (locationPosition _x);
                // Antennes civiles : ville 2 (à moins de 3 km), village 1 (2,5 km), lieu-dit 1 (1,5 km).
                (switch (type _x) do { case "NameCityCapital"; case "NameCity": { [2, 3000] }; case "NameVillage": { [1, 2500] }; default { [1, 1500] }; }) params ["_n", "_reach"];
                private _w = _n * parseNumber (_d <= _reach);
                for "_i" from 1 to _w do { _heard pushBack _d; };
            } forEach (nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 6000]);
            // Campagne : une antenne relais de grande cellule tant qu'une localité est à moins de 6 km.
            if (_heard isEqualTo [] && {(nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 6000]) isNotEqualTo []}) then {
                _heard pushBack 4000;
            };
        };
        _heard sort true;
        [count _heard, _heard param [0, 0]]
    };
    case "fix": {
        _arg params [["_o", objNull], ["_kind", "NUMÉRO"], ["_qn", ""], ["_by", objNull]];
        private _lastAll = missionNamespace getVariable ["COMSPEC_ATAK_TraceLast", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_TraceLast", _lastAll];
        private _last = _lastAll getOrDefault [_qn, []];
        private _withLast = { params ["_code"]; if (_last isEqualTo []) exitWith { [_code, [], 0, -1] }; [_code, _last select 0, _last select 1, _last select 2] };
        if (isNull _o) exitWith { ["OFF"] call _withLast };
        // Camps traçables (réglages « Géolocalisables ») : un camp fermé répond comme un numéro inconnu.
        private _ts = side group _o;
        if (!isNull _by && {_ts isEqualTo (side group _by)} && {_o isNotEqualTo _by}
            && {!(missionNamespace getVariable ["comspec_atak_native_geoloc_own", false])} && {!(["ownOk", _o] call comspec_atak_native_fnc_simSos)}) exitWith { ["OWN", [], 0, -1] };
        if !(missionNamespace getVariable [format ["comspec_atak_native_geoloc_%1", [_ts] call _sideKey], true]) exitWith { ["UNKNOWN", [], 0, -1] };
        private _set = _o getVariable ["COMSPEC_ATAK_PhoneTrace", ""];
        if !(_set isEqualType "") then { _set = ""; };
        private _atak = _kind in ["IMEI", "MAC"] || {[_o] call comspec_atak_native_fnc_hasDevice && {["same", [["norm", [([_o] call comspec_atak_native_fnc_phoneIdent) select 0]] call _calc, _qn]] call _calc}};
        private _on = switch (true) do {
            case (_set isEqualTo "off"): { false };
            case (_atak): {
                [_o] call comspec_atak_native_fnc_hasDevice
                    && {!(isPlayer _o) || {!(((_o getVariable ["COMSPEC_ATAK_Pub", []]) param [2, ""]) in ["OFF", "BROKEN"]) && {_o getVariable ["COMSPEC_ATAK_Beacon", true]}}}
            };
            // Profil SSE sans téléphone (mode « Aucun ») : rien à capter.
            default { toUpper (_o getVariable ["comspec_sse_phoneMode", "AUTO"]) isNotEqualTo "NONE" };
        };
        private _pos = getPosATL _o;
        private _cov = ["coverage", [_pos, str _ts]] call comspec_atak_native_fnc_traceServer;
        private _flags = createHashMapFromArray [
            ["disabled", false], ["found", true],
            ["burner", _set isEqualTo "burner" || {!(_o getVariable ["COMSPEC_ATAK_Traceable", true])}],
            ["present", true], ["jammed", [_pos, str _ts] call _jammedAt],
            ["sos", _atak && {["active", _o] call comspec_atak_native_fnc_simSos}],
            ["on", _on], ["airplane", isPlayer _o && {_o getVariable ["COMSPEC_ATAK_Airplane", false]}],
            ["antennas", _cov select 0]
        ];
        private _code = ["status", [_flags]] call _calc;
        if (_code isEqualTo "SOS") exitWith {
            private _fx = ["fix", _o] call comspec_atak_native_fnc_simSos;
            if (_fx isEqualTo []) exitWith { ["NOCOV"] call _withLast };
            _lastAll set [_qn, [_fx select 0, _fx select 1, _now]];
            ["SOS", _fx select 0, _fx select 1, _now]
        };
        if (_code isNotEqualTo "OK") exitWith {
            if (_code isEqualTo "UNKNOWN") exitWith { ["UNKNOWN", [], 0, -1] };
            [_code] call _withLast
        };
        private _r = ["radius", [_cov select 0, _cov select 1, (missionNamespace getVariable ["comspec_atak_native_trace_rad_multi", 100]) max 10,
            (missionNamespace getVariable ["comspec_atak_native_trace_rad_single", 800]) max 50]] call _calc;
        _r = round (_r * (0.75 + random 0.5));
        private _p = _pos getPos [random (_r * 0.7), random 360];
        _p = [_p select 0, _p select 1, 0];
        _lastAll set [_qn, [_p, _r, _now]];
        if (isPlayer _o && {missionNamespace getVariable ["comspec_atak_native_geoloc_warn", false]}) then {
            ["comspec_atak_native_geoWarn", [], _o] call CBA_fnc_targetEvent;
        };
        ["OK", _p, _r, _now]
    };
    default { [] };
};
