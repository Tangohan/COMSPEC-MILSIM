/*
    Côté joueur des fichiers (remoteExec du serveur, allowedTargets = 1) et API locale.
      ["init"]                     écoute le verdict du SEEK II (évènement comspec_sse_seekVerdict) pour le niveau N4
      ["send", [op, args]]         demande au serveur (fn_fileServer ; appel direct en solo / hôte)
      ["seekStore", [fiche]]       fiche SEEK II enregistrée au fichier de la mission (réponse « seekStored »)
      ["record", [relevé]]         relevé d'un document noté au fichier (fn_controlRecord)
      ["cancel", [étiquette]]      consultation abandonnée par le joueur (bouton « Annuler ») : sa réponse est ignorée
    Réponses du serveur :
      result [étiquette, résultat]   consultation (fn_fileServer « query ») : posée TOUT DE SUITE dans l'app (réponse
          locale), puis, licence acceptée et liaison présente, complétée par la requête Athena du poste (COMSPEC Link
          QueryIdDocument, déjà dans la DLL) en tâche de fond, 15 s au plus ; sans Athena, notée en file hors ligne
          (genre file_query). État « athenaState » du résultat : pending | ok | none | offline | unlinked | timeout.
          Puis évènement CBA local comspec_sse_fileResult [étiquette, résultat] (après l'étape Athena, comme avant).
      fail [étiquette, motif]        consultation refusée par le serveur : résultat d'erreur (motif lisible).
      seekStored [REC, heure] -> évènement comspec_sse_fileSeekStored     recorded [clé, docId] -> comspec_sse_fileRecorded
    ["timeout", [étiquette]] (fn_fileQuery, 8 s) : pas de réponse du serveur, résultat d'erreur ; Athena seule si possible.
    Toute consultation finit donc dans l'historique (journal de l'app), trouvée, introuvable ou en erreur ; une réponse
    tardive du serveur remplace l'erreur de même étiquette. Erreur : évènements comspec_sse_fileError [étiquette, motif]
    et comspec_sse_fileResult [étiquette, HashMap vide].
*/
params [["_op", "", [""]], ["_a", []]];
if (_op isEqualTo "send") exitWith {
    _a params [["_sop", ""], ["_sargs", []]];
    if (isServer) then { [_sop, _sargs] call comspec_sse_fnc_fileServer; } else { [_sop, _sargs] remoteExecCall ["comspec_sse_fnc_fileServer", 2]; };
    true
};
if (!hasInterface) exitWith {};
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FICHIERS") then { ["FICHIERS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
private _pend = missionNamespace getVariable ["comspec_sse_filePending", createHashMap];
missionNamespace setVariable ["comspec_sse_filePending", _pend];
private _cancelled = missionNamespace getVariable ["comspec_sse_fileCancelled", createHashMap];
missionNamespace setVariable ["comspec_sse_fileCancelled", _cancelled];
// Réponse posée dans l'historique (journal de l'app) : remplace l'entrée de même étiquette (réponse tardive après une
// erreur), montrée tout de suite si l'app l'attend (à la fin de l'animation du terminal si elle tourne encore).
private _land = {
    params ["_tag", "_res"];
    private _app = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
    uiNamespace setVariable ["comspec_sse_fileApp", _app];
    private _h = _app getOrDefault ["history", []];
    private _i = _h findIf { (_x getOrDefault ["tag", ""]) isEqualTo _tag };
    if (_i >= 0) then { _h set [_i, _res]; } else {
        _h pushBack _res;
        if ((count _h) > 30) then {
            _h deleteAt 0;
            { _app set [_x, ((_app getOrDefault [_x, -1]) - 1) max -1]; } forEach ["view", "ready"];
        };
        _i = (count _h) - 1;
    };
    _app set ["history", _h];
    if ((_app getOrDefault ["pending", ""]) isEqualTo _tag) then {
        _app set ["ready", _i];
        if !(["busy", ["files"]] call comspec_sse_fnc_scanAnim) then { _app set ["pending", ""]; _app set ["view", _i]; };
    };
    call _render;
};
// Étape Athena, toujours APRÈS la réponse locale et sans la retenir. Licence non acceptée : aucune requête ne part.
// _rescue : le serveur n'a pas répondu, Athena est la seule source (sa réponse remplace alors l'erreur).
private _athena = {
    params ["_tag", "_res", ["_rescue", false]];
    private _lic = isNil "comspec_overwatch_connect_fnc_licenceStatus" || {([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]};
    if (!_lic) exitWith {
        _res set ["athenaState", "unlinked"];
        call _render;
        ["comspec_sse_fileResult", [_tag, [_res, createHashMap] select ((_res getOrDefault ["error", ""]) isNotEqualTo "")]] call CBA_fnc_localEvent;
    };
    _res set ["athenaState", "pending"];
    [{
        params ["_res", "_render"];
        if ((_res getOrDefault ["athenaState", ""]) isEqualTo "pending") then { _res set ["athenaState", "timeout"]; call _render; };
    }, [_res, _render], 15] call CBA_fnc_waitAndExecute;
    [_tag, _res, _render, _rescue] spawn {
        params ["_tag", "_res", "_render", "_rescue"];
        private _q = _res getOrDefault ["query", createHashMap];
        private _type = _res getOrDefault ["type", "QA"];
        private _state = "offline";
        if (["has"] call comspec_sse_fnc_idAthenaPack) then {
            private _args = switch (_type) do {
                case "QW": { ["", "QW", "", _q getOrDefault ["last", ""], _q getOrDefault ["first", ""], _q getOrDefault ["dob", ""]] };
                case "QB": { ["", "QB", [_q getOrDefault ["bioKey", ""], _q getOrDefault ["rec", ""]] select ((_q getOrDefault ["rec", ""]) isNotEqualTo ""), "", "", ""] };
                case "QV": { ["", "QV", _q getOrDefault ["plate", ""], "", "", ""] };
                default { [_q getOrDefault ["country", ""], _q getOrDefault ["docType", ""], _q getOrDefault ["number", ""], "", "", ""] };
            };
            private _raw = ["QueryIdDocument", _args] call comspec_sse_fnc_extensionCall;
            if !(_raw isEqualType "") then { _raw = ""; };
            private _arr = if ((_raw select [0, 3]) isEqualTo "OK|") then { parseSimpleArray (_raw select [3]) } else { [] };
            if (_arr isEqualType [] && {(count _arr) >= 5}) then {
                _state = ["none", "ok"] select ((_arr select 0) isEqualTo true);
                // Arrivée après les 15 s : l'app a déjà dit « sans réponse », rien n'est ajouté.
                if ((_res getOrDefault ["athenaState", ""]) isEqualTo "pending") then { [_res, _arr] call comspec_sse_fnc_fileMerge; };
            } else {
                if (_raw isEqualTo "ERR|timeout") then { _state = "timeout"; };
                diag_log text format ["[COMSPEC SSE] Fichiers : requête Athena %1 sans réponse exploitable (%2), réponse locale seule.", _res getOrDefault ["qrn", ""], _raw];
            };
        };
        private _ok = (_res getOrDefault ["athenaState", ""]) isEqualTo "pending" && {_state in ["ok", "none"]};
        if ((_res getOrDefault ["athenaState", ""]) isEqualTo "pending") then { _res set ["athenaState", _state]; };
        _res set ["online", _ok];
        // Serveur muet mais Athena a répondu : c'est la réponse des fichiers (source Athena seule).
        if (_ok && {_rescue}) then { _res set ["error", ""]; _res set ["localDown", true]; };
        // Sans réponse d'Athena : la consultation part au journal du passage aux fichiers au retour de la liaison.
        if (!_ok) then {
            private _key = format ["FQ-%1-%2-%3", getPlayerUID player, _res getOrDefault ["qrn", _tag], round (serverTime max time)];
            private _payload = createHashMapFromArray [
                ["kind", "file_query"], ["idempotency_key", _key], ["qrn", _res getOrDefault ["qrn", ""]], ["type", _type],
                ["query", createHashMapFromArray [["last", _q getOrDefault ["last", ""]], ["first", _q getOrDefault ["first", ""]], ["dob", _q getOrDefault ["dob", ""]],
                    ["number", _q getOrDefault ["number", ""]], ["country", _q getOrDefault ["country", ""]], ["doc_type", _q getOrDefault ["docType", ""]],
                    ["bio_key", _q getOrDefault ["bioKey", _q getOrDefault ["rec", ""]]], ["plate", _q getOrDefault ["plate", ""]]]],
                ["hit", _res getOrDefault ["hit", false]], ["codes", _res getOrDefault ["codes", []]], ["source", _res getOrDefault ["source", "phone"]],
                ["game_time", format ["%1-%2-%3 %4", date select 0, date select 1, date select 2, _res getOrDefault ["at", ""]]], ["grid", mapGridPosition player],
                ["controller", createHashMapFromArray [["callsign", name player], ["steam_id", getPlayerUID player]]],
                ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])], ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]]
            ];
            [createHashMapFromArray [["kind", "FILEQUERY"], ["command", "SubmitIdCheck"], ["payload", _payload], ["idempotency_key", _key], ["record_id", _key], ["txStatus", "PENDING"]]] call comspec_sse_fnc_queueOffline;
        };
        ["comspec_sse_fileResult", [_tag, [_res, createHashMap] select ((_res getOrDefault ["error", ""]) isNotEqualTo "")]] call CBA_fnc_localEvent;
        call _render;
    };
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
    case "cancel": {
        _a params [["_tag", ""]];
        if !(_tag in _pend) exitWith { false };
        _pend deleteAt _tag;
        _cancelled set [_tag, true];
        true
    };
    case "result": {
        _a params [["_tag", ""], ["_res", createHashMap]];
        if !(_res isEqualType createHashMap) exitWith {};
        if (_tag in _cancelled) exitWith { _cancelled deleteAt _tag; };
        if !(_tag in _pend) then { diag_log text format ["[COMSPEC SSE] Fichiers : réponse tardive du serveur (%1), erreur remplacée.", _tag]; };
        _pend deleteAt _tag;
        _res set ["tag", _tag];
        // Serveur d'avant (sans « match ») : déduit de « hit ».
        if !("match" in _res) then { _res set ["match", ["none", "hit"] select (_res getOrDefault ["hit", false])]; };
        [_tag, _res] call _land;
        [_tag, _res] call _athena;
    };
    case "fail";
    case "timeout": {
        _a params [["_tag", ""], ["_why", ""]];
        private _p = _pend getOrDefault [_tag, []];
        if (_p isEqualTo []) exitWith {};
        _pend deleteAt _tag;
        if (_op isEqualTo "timeout") then {
            _why = "Le serveur de la mission ne répond pas : Fichiers absent ou plus ancien sur le serveur.";
            diag_log text format ["[COMSPEC SSE] Fichiers : aucune réponse du serveur en 8 s (%1), consultation terminée en erreur.", _tag];
        };
        if !(_why isEqualType "") then { _why = str _why; };
        if (_why isEqualTo "") then { _why = "Réponse du serveur illisible."; };
        _p params [["_type", "QA"], ["_q", createHashMap], ["_src", "phone"]];
        private _res = createHashMapFromArray [
            ["qrn", format ["FQ-L-%1", (str (100000 + floor (diag_tickTime mod 99999))) select [1, 5]]], ["type", _type], ["query", _q], ["source", _src],
            ["tag", _tag], ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["date", date], ["hit", false], ["match", "none"],
            ["codes", []], ["records", []], ["numChecks", []], ["online", false], ["error", _why], ["by", name player]
        ];
        [_tag, _res] call _land;
        ["comspec_sse_fileError", [_tag, _why]] call CBA_fnc_localEvent;
        [_tag, _res, true] call _athena;
    };
};
true
