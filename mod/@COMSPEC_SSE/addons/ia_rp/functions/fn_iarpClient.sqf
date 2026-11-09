/*
    Texte libre avec les IA : côté poste du joueur (réponses du serveur fn_iarpServer, envois).
      send     [op, args]                        vers le serveur (appel direct sur l'hôte ou en solo)
      bubble   [unité, genre, nom, contenu]      bulle 3D (fn_iarpBubble) ; genre say | me | do | todo | tryOk | tryKo | ai
               contenu : texte du joueur, ou réplique d'IA [modèle fr, modèle en, variables, langue locale, pays]
      typing   [unité, vrai | faux]              « … » au-dessus de la tête de quelqu'un qui écrit
      reply    [ia, contenu, intention]          réplique de l'IA à qui je parle : historique du terminal
      ack      [ia, intention, issue]            mon message est traité ; papiers / fouille : action ACE habituelle
      notice   [clé de stringtable, valeurs…]    message court (toast)
      log      [lignes] · zeus [lignes]          journal (onglet JOURNAL ; Zeus : lignes en direct)
      profile  [ia, profil texte]                profil lu pour l'atelier IA du Zeus
    Langue : celle du jeu (français, sinon anglais) ; faits de la couverture traduits par l'ATAK s'il est chargé.
*/
params [["_op", "", [""]], ["_a", []]];
if (_op isEqualTo "send") exitWith {
    _a params [["_sop", ""], ["_sa", []]];
    if (isServer) then { [_sop, _sa] call comspec_sse_fnc_iarpServer; } else { [_sop, _sa] remoteExecCall ["comspec_sse_fnc_iarpServer", 2]; };
    true
};
if (!hasInterface) exitWith {};
private _lang = ["en", "fr"] select (language isEqualTo "French");
private _hist = uiNamespace getVariable ["comspec_sse_iarpHist", createHashMap];
uiNamespace setVariable ["comspec_sse_iarpHist", _hist];
private _push = {
    params ["_net", "_kind", "_who", "_text"];
    private _l = _hist getOrDefault [_net, []];
    _l pushBack [_kind, _who, _text, [daytime, "HH:MM"] call BIS_fnc_timeToString];
    if ((count _l) > 80) then { _l deleteAt 0; };
    _hist set [_net, _l];
    if ((uiNamespace getVariable ["comspec_sse_iarpTarget", ""]) isEqualTo _net) then { ["refresh"] call comspec_sse_fnc_iarpUi; };
};
// Réplique d'IA dans ma langue ; langue du pays entre crochets si je ne la parle pas.
private _aiText = {
    params ["_pay"];
    _pay params [["_fr", ""], ["_en", ""], ["_vars", createHashMap], ["_local", ""], ["_country", ""]];
    private _v = _vars;
    if (_lang isEqualTo "en" && {!isNil "comspec_sse_fnc_tr"} && {!isNil "comspec_atak_native_fnc_tr"}) then {
        _v = +_vars;
        { if (_y isEqualType "") then { _v set [_x, _y call comspec_sse_fnc_tr]; }; } forEach _v;
    };
    private _s = [[_fr, _en] select (_lang isEqualTo "en"), _v, _lang] call comspec_sse_fnc_iarpFill;
    if (_local isNotEqualTo "" && {!(_country in (player getVariable ["comspec_sse_iarpSpeaks", []]))}) then {
        _s = format ["%1 [%2]", _local, _s];
    };
    _s
};
private _anon = { params ["_n"]; [_n, localize "STR_COMSPEC_IARP_Anonymous"] select (_n isEqualTo "") };

switch (_op) do {
    case "bubble": {
        _a params [["_u", objNull], ["_kind", "say"], ["_tag", ""], ["_pay", ""]];
        if (isNull _u) exitWith {};
        private _text = if (_kind isEqualTo "ai") then { [_pay] call _aiText } else { _pay };
        private _show = switch (_kind) do {
            case "me": { format ["* %1 %2", _tag, _text] };
            case "do": { format ["(%1)", _text] };
            case "todo": { format ["%1 *", _text] };
            case "tryOk": { format ["%1 %2", localize "STR_COMSPEC_IARP_TryOk", _text] };
            case "tryKo": { format ["%1 %2", localize "STR_COMSPEC_IARP_TryKo", _text] };
            default { _text };
        };
        private _name = if (_kind isEqualTo "ai") then { [_tag] call _anon } else { _tag };
        ["add", [_u, _show, _name, _kind]] call comspec_sse_fnc_iarpBubble;
        // Mon /try : le résultat s'inscrit aussi dans le terminal.
        if (_u isEqualTo player && {_kind in ["tryOk", "tryKo"]}) then {
            [uiNamespace getVariable ["comspec_sse_iarpTarget", ""], _kind, localize "STR_COMSPEC_IARP_Me", _show] call _push;
        };
    };
    case "typing": {
        _a params [["_u", objNull], ["_on", false]];
        if (isNull _u) exitWith {};
        private _name = if (isPlayer _u) then { name _u } else { [_u getVariable ["comspec_sse_idName", ""]] call _anon };
        ["typing", [_u, _on, _name]] call comspec_sse_fnc_iarpBubble;
    };
    case "reply": {
        _a params [["_t", objNull], ["_pay", []], ["_intent", ""]];
        if (isNull _t) exitWith {};
        private _s = [_pay] call _aiText;
        missionNamespace setVariable ["comspec_sse_iarpLastReply", [_t, _s, _intent, time]];
        [netId _t, "ai", [_t getVariable ["comspec_sse_idName", ""]] call _anon, _s] call _push;
    };
    case "ack": {
        _a params [["_t", objNull], ["_intent", ""], ["_outcome", ""]];
        missionNamespace setVariable ["comspec_sse_iarpLastAck", [_t, _intent, _outcome, time]];
        if (_outcome isNotEqualTo "ace" || {isNull _t}) exitWith {};
        // Papiers et fouille : l'action ACE habituelle, ses durées et ses distances ; rien n'est contourné.
        closeDialog 0;
        if (_intent isEqualTo "papers") exitWith {
            if ((player distance _t) > 3) exitWith { ["notice", ["STR_COMSPEC_IARP_N_Closer"]] call comspec_sse_fnc_iarpClient; };
            ["send", ["begin", [player, "ask", _t]]] call comspec_sse_fnc_idClient;
            [2, "Papiers, s'il vous plaît…", { params ["_t"]; ["send", ["ask", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
        };
        if (_intent isEqualTo "search") then {
            private _alive = alive _t;
            ["send", ["begin", [player, ["docs", "docsAlive"] select _alive, _t]]] call comspec_sse_fnc_idClient;
            [[4, 6] select _alive, "Fouille des papiers…", { params ["_t"]; ["send", ["docs", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
        };
    };
    case "notice": {
        _a params [["_key", ""]];
        private _vals = _a select [1];
        private _txt = format ([localize _key] + _vals);
        if (!isNil "comspec_sse_fnc_toast") then { [_txt, "INFO"] call comspec_sse_fnc_toast; } else { hintSilent _txt; };
        missionNamespace setVariable ["comspec_sse_iarpLastNotice", _txt];
    };
    case "log": {
        _a params [["_rows", []]];
        uiNamespace setVariable ["comspec_sse_iarpLogRows", _rows];
        ["refreshLog"] call comspec_sse_fnc_iarpUi;
    };
    case "zeus": {
        _a params [["_row", []]];
        private _rows = uiNamespace getVariable ["comspec_sse_iarpLogRows", []];
        _rows pushBack _row;
        if ((count _rows) > 500) then { _rows deleteAt 0; };
        uiNamespace setVariable ["comspec_sse_iarpLogRows", _rows];
        ["refreshLog"] call comspec_sse_fnc_iarpUi;
    };
    case "profile": {
        _a params [["_t", objNull], ["_raw", ""]];
        if (!isNil "comspec_sse_fnc_iarpWorkshop") then { ["loaded", [_t, _raw]] call comspec_sse_fnc_iarpWorkshop; };
    };
};
