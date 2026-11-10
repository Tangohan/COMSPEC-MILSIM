/*
    Côté joueur des fichiers (remoteExec du serveur, allowedTargets = 1) et API locale.
      ["init"]                     écoute le verdict du SEEK II (évènement comspec_sse_seekVerdict) pour le niveau N4
      ["send", [op, args]]         demande au serveur (fn_fileServer ; appel direct en solo / hôte)
      ["seekStore", [fiche]]       fiche SEEK II enregistrée au fichier de la mission (réponse « seekStored »)
      ["record", [relevé]]         relevé d'un document noté au fichier (fn_controlRecord)
    Réponses du serveur :
      result [étiquette, résultat]   consultation (fn_fileServer « query ») ; liaison Athena présente et licence acceptée :
          complétée par la requête Athena du poste (COMSPEC Link QueryIdDocument, déjà dans la DLL), sinon notée en
          file hors ligne (genre file_query) pour le journal du passage aux fichiers. Puis évènement CBA local
          comspec_sse_fileResult [étiquette, résultat], historique de l'app Fichiers, page redessinée si elle est ouverte.
      seekStored [REC, heure] -> évènement comspec_sse_fileSeekStored     recorded [clé, docId] -> comspec_sse_fileRecorded
*/
params [["_op", "", [""]], ["_a", []]];
if (_op isEqualTo "send") exitWith {
    _a params [["_sop", ""], ["_sargs", []]];
    if (isServer) then { [_sop, _sargs] call comspec_sse_fnc_fileServer; } else { [_sop, _sargs] remoteExecCall ["comspec_sse_fnc_fileServer", 2]; };
    true
};
if (!hasInterface) exitWith {};
private _app = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
uiNamespace setVariable ["comspec_sse_fileApp", _app];
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FICHIERS") then { ["FICHIERS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
switch (_op) do {
    case "init": {
        if (missionNamespace getVariable ["comspec_sse_fileInit", false]) exitWith {};
        missionNamespace setVariable ["comspec_sse_fileInit", true];
        // Verdict du SEEK II sur la personne du contrôle en cours : niveau N4 de la fiche (le téléphone ne compare rien).
        ["comspec_sse_seekVerdict", {
            params [["_u", objNull], ["_key", ""], ["_verdict", ""], ["_rec", ""], ["_lines", []]];
            private _cur = ["current"] call comspec_sse_fnc_idHistory;
            if ((count _cur) isEqualTo 0) exitWith {};
            private _subj = _cur getOrDefault ["subj", objNull];
            if !((_subj isEqualType objNull && {!isNull _subj} && {_subj isEqualTo _u}) || {_key isNotEqualTo "" && {(_cur getOrDefault ["subjKey", ""]) isEqualTo _key}}) exitWith {};
            private _map = createHashMapFromArray [["concordant", ["ok", "ras"]], ["discordant", ["fail", "faux"]], ["inconnu", ["doubt", "doute"]], ["signale", ["alert", "signale"]]];
            (_map getOrDefault [_verdict, ["doubt", "doute"]]) params ["_state", "_v"];
            private _l = [format ["SEEK II : %1%2.", toUpper _verdict, ["", format [" (%1)", _rec]] select (_rec isNotEqualTo "")]];
            _l append _lines;
            _cur deleteAt "seekWait";
            ["level", [_cur get "docId", 4, createHashMapFromArray [["level", 4], ["state", _state], ["verdict", _v], ["lines", _l], ["codes", ["seek_" + _verdict]], ["ref", _rec]]]] call comspec_sse_fnc_idHistory;
        }] call CBA_fnc_addEventHandler;
    };
    case "seekStore": {
        _a params [["_rec", createHashMap, [createHashMap]]];
        if ((_rec getOrDefault ["by", ""]) isEqualTo "") then { _rec set ["by", name player]; _rec set ["byUid", getPlayerUID player]; };
        if ((_rec getOrDefault ["clock", ""]) isEqualTo "") then { _rec set ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString]; };
        if ((_rec getOrDefault ["grid", ""]) isEqualTo "") then { _rec set ["grid", mapGridPosition player]; };
        ["send", ["seekStore", [player, _rec]]] call comspec_sse_fnc_fileClient;
    };
    case "record": {
        _a params [["_r", createHashMap, [createHashMap]]];
        ["send", ["record", [player, _r]]] call comspec_sse_fnc_fileClient;
    };
    case "seekStored": { _a params [["_rn", ""], ["_clock", ""]]; ["comspec_sse_fileSeekStored", [_rn, _clock]] call CBA_fnc_localEvent; };
    case "recorded": { ["comspec_sse_fileRecorded", _a] call CBA_fnc_localEvent; };
    case "result": {
        _a params [["_tag", ""], ["_res", createHashMap]];
        [_tag, _res, _render] spawn {
            params ["_tag", "_res", "_render"];
            private _lic = isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]};
            private _online = _lic && {["has"] call comspec_sse_fnc_idAthenaPack};
            private _q = _res getOrDefault ["query", createHashMap];
            private _type = _res get "type";
            private _ok = false;
            if (_online) then {
                private _args = switch (_type) do {
                    case "QW": { ["", "QW", "", _q getOrDefault ["last", ""], _q getOrDefault ["first", ""], _q getOrDefault ["dob", ""]] };
                    case "QB": { ["", "QB", [_q getOrDefault ["bioKey", ""], _q getOrDefault ["rec", ""]] select ((_q getOrDefault ["rec", ""]) isNotEqualTo ""), "", "", ""] };
                    case "QV": { ["", "QV", _q getOrDefault ["plate", ""], "", "", ""] };
                    default { [_q getOrDefault ["country", ""], _q getOrDefault ["docType", ""], _q getOrDefault ["number", ""], "", "", ""] };
                };
                private _raw = ["QueryIdDocument", _args] call comspec_sse_fnc_extensionCall;
                if !(_raw isEqualType "") then { _raw = ""; };
                if ((_raw select [0, 3]) isEqualTo "OK|") then {
                    private _arr = parseSimpleArray (_raw select [3]);
                    if (_arr isEqualType [] && {(count _arr) >= 5}) then {
                        _ok = true;
                        [_res, _arr] call comspec_sse_fnc_fileMerge;
                    };
                } else {
                    diag_log text format ["[COMSPEC SSE] Fichiers : requête Athena %1 refusée (%2), réponse locale seule.", _res get "qrn", _raw];
                };
            };
            _res set ["online", _ok];
            // Sans réponse d'Athena : la consultation part au journal du passage aux fichiers au retour de la liaison.
            if (!_ok) then {
                private _key = format ["FQ-%1-%2-%3", getPlayerUID player, _res get "qrn", round (serverTime max time)];
                private _payload = createHashMapFromArray [
                    ["kind", "file_query"], ["idempotency_key", _key], ["qrn", _res get "qrn"], ["type", _type],
                    ["query", createHashMapFromArray [["last", _q getOrDefault ["last", ""]], ["first", _q getOrDefault ["first", ""]], ["dob", _q getOrDefault ["dob", ""]],
                        ["number", _q getOrDefault ["number", ""]], ["country", _q getOrDefault ["country", ""]], ["doc_type", _q getOrDefault ["docType", ""]],
                        ["bio_key", _q getOrDefault ["bioKey", _q getOrDefault ["rec", ""]]], ["plate", _q getOrDefault ["plate", ""]]]],
                    ["hit", _res get "hit"], ["codes", _res get "codes"], ["source", _res getOrDefault ["source", "phone"]],
                    ["game_time", format ["%1-%2-%3 %4", date select 0, date select 1, date select 2, _res get "at"]], ["grid", mapGridPosition player],
                    ["controller", createHashMapFromArray [["callsign", name player], ["steam_id", getPlayerUID player]]],
                    ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])], ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]]
                ];
                if (_lic) then {
                    [createHashMapFromArray [["kind", "FILEQUERY"], ["command", "SubmitIdCheck"], ["payload", _payload], ["idempotency_key", _key], ["record_id", _key], ["txStatus", "PENDING"]]] call comspec_sse_fnc_queueOffline;
                };
            };
            private _app = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
            uiNamespace setVariable ["comspec_sse_fileApp", _app];
            private _h = _app getOrDefault ["history", []];
            _h pushBack _res;
            if ((count _h) > 30) then { _h deleteAt 0; };
            _app set ["history", _h];
            // Réponse de l'app : montrée à la fin de l'animation du terminal (fn_fileAction « run »), tout de suite si elle est finie.
            if ((_app getOrDefault ["pending", ""]) isEqualTo _tag) then {
                _app set ["ready", (count _h) - 1];
                if !(["busy", ["files"]] call comspec_sse_fnc_scanAnim) then { _app set ["pending", ""]; _app set ["view", (count _h) - 1]; };
            };
            ["comspec_sse_fileResult", [_tag, _res]] call CBA_fnc_localEvent;
            call _render;
        };
    };
};
true
