/*
    Texte libre avec les IA : côté poste du joueur (réponses du serveur fn_iarpServer, envois).
      send     [op, args]                        vers le serveur (appel direct sur l'hôte ou en solo)
      name     [unité] -> nom affiché            le même partout : fn_iarpName « shown » (papiers, légende, « Anonyme »)
      bubble   [unité, genre, nom, contenu, fil] bulle 3D (fn_iarpBubble) ; genre say | me | do | todo | tryOk | tryKo | ai
               contenu : texte du joueur, ou réplique d'IA [modèle fr, modèle en, variables, langue locale, pays]
               nom d'une IA : nom affiché, ou [nom, vrai nom, nature] pour le Zeus (fn_iarpName « label »)
               fil vrai : la réplique d'IA va aussi dans mon fil si je parle à cette IA (réponse faite à un collègue,
               réplique du Zeus, cri spontané)
      aiLine   [unité, texte, type] -> vrai si la bulle est vue    phrase d'une IA venue d'un autre système (identité SSE :
               réponse au contrôle de papiers…, fn_toast) : la même bulle, le même nom, et le fil si je lui parle
      typing   [unité, vrai | faux]              « … » au-dessus de la tête de quelqu'un qui écrit (et dans mon fil)
      reply    [ia, contenu, intention, nom]     réplique de l'IA à qui je parle : bulle et fil, une seule fois ; nom :
                                                 comme pour une bulle (fn_iarpName « label »)
      ack      [ia, intention, issue]            mon message est traité ; issue « ace » : action ACE habituelle (papiers,
                                                 fouille), après la réponse de l'IA ; incompris : rappel du GUIDE (une fois)
      dropped  [ia]                              message refusé par l'anti-flood du serveur : dit dans le fil
      who      [ia, nom, humeur] · mood [ia, humeur]   en-tête du terminal : nom, état (calme, nerveux, Stop…), peur
      notice   [clé de stringtable, valeurs…]    message court (toast)
      log      [lignes] · zeus [lignes]          journal (onglet JOURNAL ; Zeus : lignes en direct, sans doublon)
      profile  [ia, profil texte, nom]           profil lu pour l'atelier IA du Zeus, ou pour une fenêtre du menu ZEN en
                                                 attente (uiNamespace comspec_sse_iarpZenPending, fn_iarpZen)
      mem      [ia, [avoué, affirmé, joueurs], nom]   mémoire de l'IA pour le Zeus (fenêtre ZEN, fn_iarpServer memGet)
      mark     [position, lieu, nom de l'IA]     renseignement précis avec un lieu : marqueur sur MA carte seulement
    Fils, brouillons et journal vivent dans missionNamespace : une autre mission (mêmes netId) repart à zéro.
    Langue : celle du jeu (français, sinon anglais) ; faits de la couverture traduits par l'ATAK s'il est chargé.
*/
params [["_op", "", [""]], ["_a", []]];
if (_op isEqualTo "send") exitWith {
    _a params [["_sop", ""], ["_sa", []]];
    if (isServer) then { [_sop, _sa] call comspec_sse_fnc_iarpServer; } else { [_sop, _sa] remoteExecCall ["comspec_sse_fnc_iarpServer", 2]; };
    true
};
// Nom affiché : une seule règle, fn_iarpName « shown » (papiers SSE, sinon légende SSE, sinon « Anonyme »).
if (_op isEqualTo "name") exitWith {
    _a params [["_u", objNull]];
    ["shown", _u] call comspec_sse_fnc_iarpName
};
if (!hasInterface) exitWith { false };
private _lang = ["en", "fr"] select (language isEqualTo "French");
private _hist = missionNamespace getVariable ["comspec_sse_iarpHist", createHashMap];
missionNamespace setVariable ["comspec_sse_iarpHist", _hist];
private _target = uiNamespace getVariable ["comspec_sse_iarpTarget", ""];
private _push = {
    params ["_net", "_kind", "_who", "_text"];
    if (_net isEqualTo "") exitWith {};
    private _l = _hist getOrDefault [_net, []];
    _l pushBack [_kind, _who, _text, [daytime, "HH:MM"] call BIS_fnc_timeToString];
    if ((count _l) > 80) then { _l deleteAt 0; };
    _hist set [_net, _l];
    if (_target isEqualTo _net) then { ["refresh"] call comspec_sse_fnc_iarpUi; };
};
// Je parle à cette IA (fil ouvert ou déjà commencé) ?
private _talking = { params ["_u"]; !isNull _u && {(netId _u) in _hist || {_target isEqualTo (netId _u)}} };
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
private _setTyping = {
    params ["_u", "_on"];
    if (isNull _u || {(netId _u) isNotEqualTo _target}) exitWith {};
    uiNamespace setVariable ["comspec_sse_iarpAiTyping", [_on, time]];
    ["refresh"] call comspec_sse_fnc_iarpUi;
};

switch (_op) do {
    case "bubble": {
        _a params [["_u", objNull], ["_kind", "say"], ["_tag", ""], ["_pay", ""], ["_toHist", false]];
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
        private _name = if (_kind isEqualTo "ai") then { ["label", [_tag, ["shown", _u] call comspec_sse_fnc_iarpName] select (_tag isEqualTo "")] call comspec_sse_fnc_iarpName } else { _tag };
        // Mon /try : le résultat s'inscrit dans le terminal, pas de bulle au-dessus de ma propre tête.
        if (_u isEqualTo player) exitWith {
            if (_kind in ["tryOk", "tryKo"]) then { [_target, _kind, localize "STR_COMSPEC_IARP_Me", _show] call _push; };
        };
        ["add", [_u, _show, _name, _kind]] call comspec_sse_fnc_iarpBubble;
        if (_kind isEqualTo "ai") then {
            [_u, false] call _setTyping;
            if (_toHist && {[_u] call _talking}) then { [netId _u, "ai", _name, _text] call _push; };
        };
    };
    case "aiLine": {
        _a params [["_u", objNull], ["_text", ""], ["_type", "INFO"]];
        if (isNull _u || {_text isEqualTo ""}) exitWith { false };
        private _name = ["shown", _u] call comspec_sse_fnc_iarpName;
        private _seen = ["add", [_u, _text, _name, "ai"]] call comspec_sse_fnc_iarpBubble;
        if (isNil "_seen" || {!(_seen isEqualType true)}) then { _seen = false; };
        if ([_u] call _talking) then { [netId _u, "ai", _name, _text] call _push; };
        _seen
    };
    case "typing": {
        _a params [["_u", objNull], ["_on", false]];
        if (isNull _u) exitWith {};
        ["typing", [_u, _on, ["shown", _u] call comspec_sse_fnc_iarpName]] call comspec_sse_fnc_iarpBubble;
        if (!isPlayer _u) then { [_u, _on] call _setTyping; };
    };
    case "reply": {
        _a params [["_t", objNull], ["_pay", []], ["_intent", ""], ["_who", ""]];
        if (isNull _t) exitWith {};
        private _s = [_pay] call _aiText;
        if (_who isEqualTo "") then { _who = ["shown", _t] call comspec_sse_fnc_iarpName; };
        private _name = ["label", _who] call comspec_sse_fnc_iarpName;
        missionNamespace setVariable ["comspec_sse_iarpLastReply", [_t, _s, _intent, time, _name]];
        // Une seule réponse : la bulle (qui remplace la précédente) et la même ligne dans le fil, au même nom.
        ["add", [_t, _s, _name, "ai"]] call comspec_sse_fnc_iarpBubble;
        uiNamespace setVariable ["comspec_sse_iarpAiTyping", [false, time]];
        [netId _t, "ai", _name, _s] call _push;
    };
    case "ack": {
        _a params [["_t", objNull], ["_intent", ""], ["_outcome", ""]];
        missionNamespace setVariable ["comspec_sse_iarpLastAck", [_t, _intent, _outcome, time]];
        if (isNull _t) exitWith {};
        // Rien de rapproché : une fois par IA, le rappel du GUIDE dans le fil.
        if (_intent isEqualTo "unknown") exitWith {
            private _seen = missionNamespace getVariable ["comspec_sse_iarpHinted", []];
            if !((netId _t) in _seen) then {
                _seen pushBack (netId _t);
                missionNamespace setVariable ["comspec_sse_iarpHinted", _seen];
                [netId _t, "sys", "", localize "STR_COMSPEC_IARP_HintGuide"] call _push;
            };
        };
        if (_outcome isNotEqualTo "ace") exitWith {};
        // Papiers et fouille : l'action ACE habituelle, ses durées et ses distances ; rien n'est contourné.
        if (!isNull (uiNamespace getVariable ["comspec_sse_iarpDisplay", displayNull])) then { closeDialog 0; };
        if (_intent isEqualTo "papers") exitWith {
            if ((player distance _t) > 3) exitWith { ["notice", ["STR_COMSPEC_IARP_N_Closer"]] call comspec_sse_fnc_iarpClient; };
            ["send", ["begin", [player, "ask", _t]]] call comspec_sse_fnc_idClient;
            [2, localize "STR_COMSPEC_IARP_PapersBar", { params ["_t"]; ["send", ["ask", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
        };
        if (_intent isEqualTo "search") then {
            private _alive = alive _t;
            ["send", ["begin", [player, ["docs", "docsAlive"] select _alive, _t]]] call comspec_sse_fnc_idClient;
            [[4, 6] select _alive, localize "STR_COMSPEC_IARP_SearchBar", { params ["_t"]; ["send", ["docs", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
        };
    };
    case "dropped": {
        _a params [["_t", objNull]];
        if (!isNull _t) then { [netId _t, "sys", "", localize "STR_COMSPEC_IARP_Dropped"] call _push; };
    };
    case "who": {
        _a params [["_t", objNull], ["_name", ""], ["_mood", []]];
        if (isNull _t) exitWith {};
        private _w = missionNamespace getVariable ["comspec_sse_iarpWho", createHashMap];
        _w set [netId _t, [_name, _mood, time]];
        missionNamespace setVariable ["comspec_sse_iarpWho", _w];
        if ((netId _t) isEqualTo _target) then { ["head"] call comspec_sse_fnc_iarpUi; };
    };
    case "mood": {
        _a params [["_t", objNull], ["_mood", []]];
        if (isNull _t) exitWith {};
        private _w = missionNamespace getVariable ["comspec_sse_iarpWho", createHashMap];
        private _e = _w getOrDefault [netId _t, ["", [], 0]];
        _w set [netId _t, [_e select 0, _mood, time]];
        missionNamespace setVariable ["comspec_sse_iarpWho", _w];
        if ((netId _t) isEqualTo _target) then { ["head"] call comspec_sse_fnc_iarpUi; };
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
        missionNamespace setVariable ["comspec_sse_iarpLogRows", +_rows];
        ["refreshLog"] call comspec_sse_fnc_iarpUi;
    };
    case "zeus": {
        _a params [["_row", []]];
        private _rows = missionNamespace getVariable ["comspec_sse_iarpLogRows", []];
        // Une ligne par numéro : un Zeus relié à deux modules de curateur la recevait deux fois.
        if ((_rows findIf { (_x param [0, -1]) isEqualTo (_row param [0, -2]) }) >= 0) exitWith {};
        _rows pushBack _row;
        if ((count _rows) > 500) then { _rows deleteAt 0; };
        missionNamespace setVariable ["comspec_sse_iarpLogRows", _rows];
        ["refreshLog"] call comspec_sse_fnc_iarpUi;
    };
    case "mark": {
        _a params [["_pos", []], ["_label", ""], ["_who", ""]];
        if !(_pos isEqualType [] && {(count _pos) >= 2}) exitWith {};
        // Marqueur local (createMarkerLocal) : personne d'autre ne le voit ; texte simple, jamais interprété.
        private _n = format ["comspec_sse_iarpIntel_%1_%2", round (diag_tickTime * 1000), floor random 1000];
        private _m = createMarkerLocal [_n, [_pos select 0, _pos select 1]];
        _m setMarkerTypeLocal "hd_unknown";
        _m setMarkerColorLocal "ColorOrange";
        private _what = [_label, localize "STR_COMSPEC_IARP_MarkPlace"] select (_label isEqualTo "");
        if (_who isNotEqualTo "") then { _what = format ["%1 (%2)", _what, _who]; };
        _m setMarkerTextLocal format [localize "STR_COMSPEC_IARP_MarkIntel", _what];
        ["notice", ["STR_COMSPEC_IARP_N_Marked", _what]] call comspec_sse_fnc_iarpClient;
    };
    case "profile": {
        _a params [["_t", objNull], ["_raw", ""], ["_who", ""]];
        if (!isNil "comspec_sse_fnc_iarpWorkshop") then { ["loaded", [_t, _raw, _who]] call comspec_sse_fnc_iarpWorkshop; };
        // Fenêtre du menu ZEN en attente de ce profil (fn_iarpZen, 15 s au plus) : ouverte une fois, pré-remplie.
        private _cb = uiNamespace getVariable ["comspec_sse_iarpZenPending", []];
        _cb params [["_cu", objNull], ["_code", {}], ["_units", []], ["_at", -100]];
        if (!isNull _t && {_cu isEqualTo _t} && {(diag_tickTime - _at) < 15}) then {
            uiNamespace setVariable ["comspec_sse_iarpZenPending", []];
            [_t, ["parse", _raw] call comspec_sse_fnc_iarpProfile, _who, _units] call _code;
        };
    };
    case "mem": {
        _a params [["_t", objNull], ["_m", []], ["_who", ""]];
        _m params [["_conf", false], ["_said", []], ["_pl", []]];
        private _yn = { localize (["STR_COMSPEC_IARP_Z_No", "STR_COMSPEC_IARP_Z_Yes"] select (_this isEqualTo true)) };
        if (_who isEqualTo "") then { _who = ["shown", _t] call comspec_sse_fnc_iarpName; };
        private _title = format [localize "STR_COMSPEC_IARP_Z_MemTitle", ["label", _who] call comspec_sse_fnc_iarpName];
        private _lines = [];
        if (_said isEqualTo [] && {_pl isEqualTo []}) then { _lines pushBack (localize "STR_COMSPEC_IARP_Z_MemEmpty"); } else {
            _lines pushBack format ["%1 : %2", localize "STR_COMSPEC_IARP_Z_MemConfessed", _conf call _yn];
            {
                _x params [["_k", ""], ["_v", ""], ["_ok", true]];
                _lines pushBack format ["%1 %2 : %3 (%4)", localize "STR_COMSPEC_IARP_Z_MemSaid", _k, _v, localize (["STR_COMSPEC_IARP_Z_MemFalse", "STR_COMSPEC_IARP_Z_MemTrue"] select _ok)];
            } forEach _said;
            {
                _x params [["_n", ""], ["_trust", 0], ["_bribes", 0], ["_intel", 0], ["_word", false], ["_turns", 0]];
                _lines pushBack format [localize "STR_COMSPEC_IARP_Z_MemRow", _n, _trust, _bribes, _intel, _word call _yn, _turns];
            } forEach _pl;
        };
        private _txt = _lines joinString (toString [10]);
        missionNamespace setVariable ["comspec_sse_iarpLastMem", [_t, _m, _txt]];
        // Fenêtre ZEN (texte copiable) ; sans ZEN, retour au chef de mission.
        if (!isNil "zen_dialog_fnc_create") exitWith {
            [_title, [["EDIT:MULTI", [localize "STR_COMSPEC_IARP_Z_MemView", localize "STR_COMSPEC_IARP_Z_MemView_D"], [_txt, { _this }, 12]]], {}, {}, []] call zen_dialog_fnc_create;
        };
        if (!isNil "comspec_sse_fnc_zeusNotify") then { [_title + (toString [10]) + _txt, "info"] call comspec_sse_fnc_zeusNotify; } else { hintSilent (_title + (toString [10]) + _txt); };
    };
};
