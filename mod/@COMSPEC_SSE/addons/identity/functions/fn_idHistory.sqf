/*
    Contrôles faits par le joueur (local, jamais diffusé, 50 au plus) : alimente l'app « Contrôle ID ».
    ["start", [document imprimé, sujet, clé, résultat N0]]  nouveau contrôle (ou reprise du même document < 10 min)
    ["level", [docId, niveau, résultat]]   résultat d'un niveau ; verdict recalculé ; alerte discrète si signalé
    ["current"] -> fiche en cours (HashMap vide sinon)        ["list"] -> fiches, la plus récente en dernier
    ["select", index]   rouvre une fiche de l'historique       ["verdict", fiche] -> verdict global
    ["fail", [docId, niveau, code, texte]]   niveau refusé (gone : document plus présenté, far, short, gear, off) : avis
        gardé sur la fiche (clé « notice »), même texte à l'écran du contrôle et dans la notification du téléphone
    ["busy", [docId, niveau | -1]]   analyse en cours (animation de l'app, clé « busy ») ; -1 : terminée
    ["clearAlerts"]    bandeaux d'alerte du contrôle encore en file dans le téléphone retirés (nouveau contrôle, avis levé)
    Fiche : docId, doc, subj, subjName, subjKey, levels (niveau -> résultat), verdict, clock, grid, case, sent, ref, idem,
      notice [code, texte, heure] (dernier refus, effacé par le résultat suivant ou un nouveau contrôle), busy (niveau).
*/
params [["_op", ""], ["_a", []]];
private _hist = missionNamespace getVariable ["comspec_sse_idHist", []];
missionNamespace setVariable ["comspec_sse_idHist", _hist];
private _cur = missionNamespace getVariable ["comspec_sse_idCur", createHashMap];
// Gravité croissante : le verdict global est le pire des niveaux.
private _rank = ["ras", "doute", "expire", "signale", "inconnu", "faux", "revoque", "vole", "recherche", "hvt"];
private _verdict = {
    params ["_rec"];
    private _best = "ras";
    { if ((_rank find (_y getOrDefault ["verdict", "ras"])) > (_rank find _best)) then { _best = _y getOrDefault ["verdict", "ras"]; }; } forEach (_rec getOrDefault ["levels", createHashMap]);
    _best
};
private _render = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCHECK") then { ["IDCHECK"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};

// Fiche d'un document : celle en cours, sinon la plus récente de l'historique.
private _find = {
    params ["_id"];
    if ((_cur getOrDefault ["docId", ""]) isEqualTo _id) exitWith { _cur };
    private _i = _hist findIf { (_x getOrDefault ["docId", ""]) isEqualTo _id };
    _hist param [_i, createHashMap]
};
private _clearAlerts = {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _q = _s getOrDefault ["notifications", []];
    private _keep = _q select { !((_x getOrDefault ["app", ""]) isEqualTo "IDCHECK" && {(_x getOrDefault ["type", ""]) in ["WARNING", "ERROR"]}) };
    if ((count _keep) isNotEqualTo (count _q)) then {
        _s set ["notifications", _keep];
        if (!isNil "comspec_atak_native_fnc_notificationsRender") then { [] call comspec_atak_native_fnc_notificationsRender; };
    };
};

switch (_op) do {
    case "current": { _cur };
    case "clearAlerts": { call _clearAlerts; true };
    case "busy": {
        _a params [["_id", ""], ["_lvl", -1]];
        private _rec = [_id] call _find;
        if ((count _rec) isEqualTo 0) exitWith { false };
        if (_lvl < 0) then { _rec deleteAt "busy"; } else { _rec set ["busy", _lvl]; _rec deleteAt "notice"; };
        call _render;
        true
    };
    case "fail": {
        _a params [["_id", ""], ["_lvl", 0], ["_code", ""], ["_text", ""]];
        private _rec = [_id] call _find;
        if ((count _rec) isEqualTo 0) exitWith { false };
        _rec deleteAt "busy";
        _rec set ["notice", [_code, _text, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _lvl]];
        call _clearAlerts;
        [_text, "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify;
        call _render;
        true
    };
    case "list": { _hist };
    case "verdict": { _a call _verdict };
    case "select": {
        private _r = _hist param [_a param [0, -1], createHashMap];
        if ((count _r) > 0) then { missionNamespace setVariable ["comspec_sse_idCur", _r]; };
        call _render;
        _r
    };
    case "start": {
        _a params [["_doc", createHashMap], ["_subj", objNull], ["_key", ""], ["_res", createHashMap]];
        private _id = _doc getOrDefault ["id", ""];
        private _rec = _cur;
        if ((_rec getOrDefault ["docId", ""]) isNotEqualTo _id || {(time - (_rec getOrDefault ["t", -1e6])) > 600}) then {
            private _case = if (isNil "comspec_sse_fnc_getCaseReference") then { "" } else { [] call comspec_sse_fnc_getCaseReference };
            _rec = createHashMapFromArray [
                ["docId", _id], ["doc", _doc], ["subj", _subj], ["subjName", [name _subj, ""] select (isNull _subj)],
                ["subjNetId", [netId _subj, ""] select (isNull _subj)], ["subjKey", _key],
                ["subjUid", [getPlayerUID _subj, ""] select (isNull _subj || {!isPlayer _subj})], ["levels", createHashMap],
                ["verdict", "ras"], ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["date", date], ["t", time],
                ["grid", mapGridPosition player], ["pos", getPosASL player], ["case", _case], ["sent", false], ["ref", ""],
                ["idem", format ["IDCHK-%1-%2-%3", getPlayerUID player, _id, round (serverTime max time)]], ["alerted", false]
            ];
            _hist pushBack _rec;
            if ((count _hist) > 50) then { _hist deleteRange [0, (count _hist) - 50]; };
        };
        if ((count _res) > 0) then { (_rec get "levels") set [0, _res]; };
        _rec set ["verdict", [_rec] call _verdict];
        // Document (re)présenté : l'avis d'un refus précédent et ses bandeaux ne valent plus.
        _rec deleteAt "notice";
        _rec deleteAt "busy";
        call _clearAlerts;
        missionNamespace setVariable ["comspec_sse_idCur", _rec];
        call _render;
        _rec
    };
    case "level": {
        _a params [["_id", ""], ["_lvl", 1], ["_res", createHashMap]];
        private _rec = [_id] call _find;
        if ((count _rec) isEqualTo 0) exitWith {};
        (_rec get "levels") set [_lvl, _res];
        _rec deleteAt "notice";
        _rec deleteAt "busy";
        call _clearAlerts;
        _rec set ["verdict", [_rec] call _verdict];
        _rec set ["sent", false];
        // Personne signalée, recherchée, HVT, document volé ou révoqué : le téléphone vibre (sans son en discrétion).
        if ((_rec get "verdict") in ["signale", "recherche", "hvt", "vole", "revoque"] && {!(_rec get "alerted")}) then {
            _rec set ["alerted", true];
            // ATAK 3.0.2 et plus : vibreur seul (aucun son, aucun bandeau ; rangée au centre de notifications).
            if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) exitWith {
                ["MESSAGE", format [("Contrôle ID : %1 — %2" call comspec_sse_fnc_tr), toUpper (_rec get "verdict"), _rec get "subjName"], 0, 10, "IDCHECK", "vibrate"] call comspec_atak_native_fnc_notify;
            };
            if (!isNil "comspec_atak_native_fnc_vibrate") then { [] call comspec_atak_native_fnc_vibrate; };
            [format ["Contrôle ID : %1 — %2", toUpper (_rec get "verdict"), _rec get "subjName"], "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify;
        };
        // Réglage de la communauté (paquet Athena), sinon réglage CBA de la mission.
        if (_lvl isEqualTo 3 && {missionNamespace getVariable ["comspec_sse_id_autoTransmitCommunity", missionNamespace getVariable ["comspec_sse_id_autoTransmit", true]]}) then { [_rec] call comspec_sse_fnc_idAthenaSubmit; };
        call _render;
    };
};
