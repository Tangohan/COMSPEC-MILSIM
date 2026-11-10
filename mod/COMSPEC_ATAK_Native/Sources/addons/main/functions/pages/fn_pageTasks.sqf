/*
    Tâches : ordres Athena en cartes (statut en couleur, boutons REÇU / EN COURS / TERMINÉ / IMPOSSIBLE,
    suivi de qui a vu, reçu, exécute avec photo ; le chef répond pour son groupe)
    et tâches de la mission (état, distance, carte, tâche suivie).
    4.2.1 : ordres triés (à traiter et prioritaires en tête, plus récents d'abord, terminés en bas), filtre À TRAITER /
    TERMINÉS / TOUS (état « taskFilter », ordres et tâches de mission), consigne longue dépliable au lieu d'être coupée
    sans le dire (état « taskTxtOpen » : clés dépliées), états vides qui disent quoi faire.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _icon = "\z\comspec_atak_native\addons\main\data\app_tasks.paa";
// DELIVERED = « vu » côté Athena (transmis au téléphone) ; TERMINÉ = DONE.
private _labels = createHashMapFromArray [["ACK", "REÇU"], ["EXEC", "EN COURS"], ["DONE", "TERMINÉ"], ["DELIVERED", "VU"], ["PENDING", "TRANSMIS"], ["CANCELLED", "ANNULÉ"], ["FAILED", "IMPOSSIBLE"], ["SUCCEEDED", "RÉUSSIE"], ["CANCELED", "ANNULÉE"], ["CREATED", "NOUVELLE"], ["ASSIGNED", "ASSIGNÉE"], ["NEW", "NOUVEAU"]];
private _colorOf = {
    params ["_st"];
    switch (true) do {
        case (_st in ["DONE", "SUCCEEDED"]): { [[0.36, 0.78, 0.42, 1], (COMSPEC_ATAK_Th get "hexOk")] };
        case (_st in ["FAILED", "CANCELED", "CANCELLED"]): { [[0.58, 0.64, 0.60, 1], "#94a399"] };
        case (_st in ["ACK", "EXEC", "ASSIGNED"]): { [[0.95, 0.67, 0.20, 1], (COMSPEC_ATAK_Th get "hexWarn")] };
        default { [[0.88, 0.25, 0.22, 1], "#e04038"] };
    }
};
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _s0 = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _isLead = (leader group player) isEqualTo player && {({isPlayer _x} count (units group player)) > 1};
// Destinataires attendus d'un ordre : groupe, joueur ou indicatif visé ; tout mon camp si l'ordre est pour tous.
private _expected = {
    params ["_o"];
    private _tg = _o getOrDefault ["target", ""];
    if !(_tg isEqualType "") then { _tg = str _tg; };
    _tg = toLower trim _tg;
    private _tt = toLower (_o getOrDefault ["targetType", "all"]);
    private _pl = allPlayers select { side group _x isEqualTo side group player && {alive _x} };
    if (_tg in ["", "-", "all", "tous"] || {_tt isEqualTo "all" && {_tg isEqualTo ""}}) exitWith { _pl };
    _pl select { (toLower groupId group _x) isEqualTo _tg || {(toLower name _x) isEqualTo _tg} || {(toLower ([_x, true] call comspec_atak_native_fnc_unitCallsign)) isEqualTo _tg} }
};
if (_s0 getOrDefault ["orderCompose", false]) exitWith { [] call comspec_atak_native_fnc_pageOrderCompose };
private _orders = [] call comspec_atak_native_fnc_tasksAll;
private _closedSt = ["DONE", "FAILED", "SUCCEEDED", "CANCELED", "CANCELLED"];
// Rang de priorité d'un ordre : FLASH, URGENT, prioritaire, puis le reste.
private _prioRank = {
    params ["_p"];
    switch (true) do {
        case (_p isEqualTo "FLASH"): { 0 };
        case (_p isEqualTo "URGENT"): { 1 };
        case (_p in ["HIGH", "PRIORITY", "IMPORTANT"]): { 2 };
        default { 3 };
    }
};
// Ordre d'affichage (avant : celui de la HashMap, au hasard) : à traiter d'abord, puis priorité, puis le plus récent.
// Clé texte en dernier pour départager deux ordres de même heure (une clé nombre et une clé texte ne se comparent pas).
private _open = 0;
private _sorted = [];
{
    private _closed = (toUpper (_y getOrDefault ["status", "NEW"])) in _closedSt;
    if (!_closed) then { _open = _open + 1; };
    private _stamp = _y getOrDefault ["stamp", 0];
    if !(_stamp isEqualType 0) then { _stamp = 0; };
    private _pr = _y getOrDefault ["priority", "NORMAL"];
    if !(_pr isEqualType "") then { _pr = str _pr; };
    _sorted pushBack [[0, 1] select _closed, [toUpper _pr] call _prioRank, -_stamp, str _x, _x];
} forEach _orders;
_sorted sort true;
// Filtre (ordres et tâches de mission) : À TRAITER (par défaut), TERMINÉS, TOUS.
private _filter = _s0 getOrDefault ["taskFilter", "OPEN"];
if !(_filter in ["OPEN", "DONE", "ALL"]) then { _filter = "OPEN"; };
private _shows = { params ["_closed"]; _filter isEqualTo "ALL" || {(_filter isEqualTo "DONE") isEqualTo _closed} };
private _setFilter = { params ["_f"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['taskFilter', '%1']; ['TASK'] call comspec_atak_native_fnc_pageRender;", _f] };
// Consigne longue : début montré, la suite par une section repliable (clé de l'ordre, ou « m » + rang de la tâche de mission).
private _txtOpen = _s0 getOrDefault ["taskTxtOpen", []];
private _foldCode = {
    params ["_k"];
    compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; private _o = +(_s getOrDefault ['taskTxtOpen', []]); if (%1 in _o) then { _o = _o - [%1]; } else { _o pushBack %1; }; _s set ['taskTxtOpen', _o]; ['TASK'] call comspec_atak_native_fnc_pageRender;", str _k]
};
// Texte échappé, retours à la ligne gardés ; coupé à la fin d'un mot quand il est replié.
private _bodyTxt = {
    params ["_t", "_max", "_full"];
    if (!_full && {(count _t) > _max}) then {
        _t = _t select [0, _max];
        private _sp = _t find " ";
        private _last = -1;
        while { _sp >= 0 } do { _last = _sp; _sp = _t find [" ", _sp + 1]; };
        if (_last > _max * 0.7) then { _t = _t select [0, _last]; };
        _t = _t + "…";
    };
    (([_t] call _esc) splitString (toString [13, 10])) joinString "<br/>"
};
private _foldRow = {
    params ["_k", "_len"];
    private _isOpen = _k in _txtOpen;
    ["fold", "Texte complet", [format ["%1 caractères · déplier", _len], "replier"] select _isOpen, _isOpen, [_k] call _foldCode]
};
// Avec Athena : chef d'unité (COMSPEC Link) ; sans Athena (ATAK local, poste TOC) : ordres au camp par fn_orderLocal.
private _canOrder = ["can"] call comspec_atak_native_fnc_orderLocal;
private _rows = [];
if (_canOrder) then { _rows pushBack ["buttons", [["NOUVEL ORDRE", { ['open'] call comspec_atak_native_fnc_orderAction; }, true]]]; };
// Onglets en « segment » : leurs libellés rétrécissent ou passent à la ligne s'ils ne tiennent pas (format mini).
_rows pushBack ["segment", "", [
    ["À TRAITER", ["OPEN"] call _setFilter, _filter isEqualTo "OPEN", "Ordres et tâches pas encore terminés"],
    ["TERMINÉS", ["DONE"] call _setFilter, _filter isEqualTo "DONE", "Terminés, impossibles ou annulés"],
    ["TOUS", ["ALL"] call _setFilter, _filter isEqualTo "ALL"]
]];
// Hors connexion (fn_offline) : ordres connus à la perte du signal, les nouveaux arrivent au retour du réseau.
(["state"] call comspec_atak_native_fnc_offline) params ["_offNow", "_offSync"];
_rows append [["section", "Ordres du TOC", ([format ["%1 ordre(s), %2 à traiter", count _orders, _open], ""] select ((count _orders) isEqualTo 0))
    + (["", format [" · hors connexion, ordres connus à %1", _offSync]] select _offNow)]];
private _shown = _sorted select { [(_x select 0) isEqualTo 1] call _shows };
if ((count _shown) isEqualTo 0) then {
    _rows pushBack (switch (true) do {
        case ((count _orders) isEqualTo 0): {
            ["empty", _icon, "Aucun ordre reçu", ["Le TOC en envoie depuis la carte web d'Athena : ils arrivent ici avec une alerte.",
                "Le TOC en envoie depuis la carte web d'Athena. Pour en émettre un vous-même : NOUVEL ORDRE, en haut."] select _canOrder]
        };
        case (_filter isEqualTo "DONE"): { ["empty", _icon, "Aucun ordre terminé", "Un ordre passe ici quand vous répondez TERMINÉ ou IMPOSSIBLE, ou quand le TOC l'annule."] };
        default { ["empty", "\z\comspec_atak_native\addons\main\data\ui_check.paa", "Rien à traiter", "Tous les ordres reçus sont terminés. Onglet TERMINÉS pour les revoir."] };
    });
};
{
    private _id = _x select 4;
    private _ord = _orders get _id;
    private _st = toUpper (_ord getOrDefault ["status", "NEW"]);
    ([_st] call _colorOf) params ["_rgb", "_hex"];
    private _label = _ord getOrDefault ["typeLabel", ""];
    if (_label isEqualTo "") then { _label = _ord getOrDefault ["type", "ORDRE"]; };
    private _prio = _ord getOrDefault ["priority", "NORMAL"];
    if !(_prio isEqualType "") then { _prio = str _prio; };
    _prio = toUpper _prio;
    private _payload = _ord getOrDefault ["payload", ""];
    if !(_payload isEqualType "") then { _payload = str _payload; };
    private _long = (count _payload) > 220;
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='%2' size='0.85'>%3</t>%4<br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>De %5 · cible %6</t>%7",
        [_label] call _esc, _hex, _labels getOrDefault [_st, _st], ["", "  <t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "' size='0.85'>PRIORITAIRE</t>"] select (_prio in ["HIGH", "URGENT", "FLASH", "PRIORITY"]),
        [_ord getOrDefault ["issuer", "TOC"]] call _esc, [_ord getOrDefault ["target", "-"]] call _esc,
        ["", format ["<br/><t size='0.85'>%1</t>", [_payload, 220, _id in _txtOpen] call _bodyTxt]] select (_payload isNotEqualTo "")], [], _rgb];
    if (_long) then { _rows pushBack ([_id, count _payload] call _foldRow); };
    private _btn = {
        params ["_k", "_t"];
        [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['selectedTask', %1]; ['%2'] call comspec_atak_native_fnc_taskAction;", str _id, _k], _st isEqualTo _k]
    };
    _rows pushBack ["segment", ["", format ["Chef : votre réponse vaut pour le groupe %1", [groupId group player] call _esc]] select _isLead, [["ACK", "REÇU"] call _btn, ["EXEC", "EN COURS"] call _btn, ["DONE", "TERMINÉ"] call _btn, ["FAILED", "IMPOSSIBLE"] call _btn]];
    // Suivi : qui a vu, reçu, exécute (réponses du camp, fn_orderAck) ; « VU » envoyé à l'affichage.
    ["seen", [_id]] call comspec_atak_native_fnc_orderAck;
    private _acks = ["list", [_id]] call comspec_atak_native_fnc_orderAck;
    private _who = [_ord] call _expected;
    private _uids = _who apply { getPlayerUID _x };
    private _extra = (keys _acks) select { !(_x in _uids) };
    private _cnt = createHashMap;
    { private _k = _y select 1; _cnt set [_k, (_cnt getOrDefault [_k, 0]) + 1]; } forEach _acks;
    private _total = (count _who) + (count _extra);
    private _sum = format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Vu %1/%5 · reçu %2 · en cours %3 · terminé %4</t>",
        count _acks, (_cnt getOrDefault ["ACK", 0]), (_cnt getOrDefault ["EXEC", 0]), (_cnt getOrDefault ["DONE", 0]) + (_cnt getOrDefault ["FAILED", 0]), _total max (count _acks)];
    private _whoOpen = (_s0 getOrDefault ["taskWho", ""]) isEqualTo _id;
    _rows pushBack ["person", "\a3\ui_f\data\igui\cfg\simpletasks\types\meet_ca.paa", _sum,
        [[["QUI ?", "MASQUER"] select _whoOpen, compile format ["private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['taskWho', [%1, ''] select ((_s getOrDefault ['taskWho', '']) isEqualTo %1)]; ['TASK'] call comspec_atak_native_fnc_pageRender;", str _id], _whoOpen]], [0.55, 0.62, 0.58, 1]];
    if (_whoOpen) then {
        private _list = (_who apply { [getPlayerUID _x, name _x, groupId group _x, _x] }) + (_extra apply { private _a = _acks get _x; [_x, _a select 0, _a select 4, objNull] });
        {
            _x params ["_uid", "_nm", "_gn", "_u"];
            if (_forEachIndex >= 16) exitWith { _rows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>… et %1 autre(s)</t>", (count _list) - 16]]; };
            private _a = _acks getOrDefault [_uid, []];
            private _st2 = _a param [1, ""];
            ([switch (_st2) do { case "": { "NEW" }; case "SEEN": { "CANCELED" }; default { _st2 }; }] call _colorOf) params ["_rgb2", "_hex2"];
            private _stTxt = switch (true) do {
                case (_st2 isEqualTo ""): { "pas encore vu" };
                case (_st2 isEqualTo "SEEN"): { "vu" };
                default { toLower (_labels getOrDefault [_st2, _st2]) };
            };
            private _pic = "";
            if (!isNull _u) then { _pic = [_u] call comspec_atak_native_fnc_avatarPath; if (_pic isEqualTo "") then { _pic = [_u, "texture"] call BIS_fnc_rankParams; }; };
            if (_pic isEqualTo "") then { _pic = "\a3\ui_f\data\map\vehicleicons\iconman_ca.paa"; };
            _rows pushBack ["person", _pic, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='%2' size='0.85'>%3</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%4%5%6</t>",
                [_nm] call _esc, _hex2, _stTxt, [_gn] call _esc, ["", format [" · %1", _a param [2, ""]]] select ((_a param [2, ""]) isNotEqualTo ""),
                ["", format [" · par le chef %1", [_a param [3, ""]] call _esc]] select ((_a param [3, ""]) isNotEqualTo "")], [], [1, 1, 1, 1]];
        } forEach _list;
    };
} forEach _shown;
// Liste « QUI ? » dépliée : la touche retour la replie au lieu de quitter l'app.
private _whoId = _s0 getOrDefault ["taskWho", ""];
if (_whoId isNotEqualTo "" && {(_shown findIf { (_x select 4) isEqualTo _whoId }) >= 0}) then {
    _s0 set ["backSub", ["TASK", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["taskWho", ""]; ["TASK"] call comspec_atak_native_fnc_pageRender; }]];
};

// Missions de tir du camp (appui feu typé, fn_fireSupport) : servant joueur (REÇU, COUPS PARTIS) ou demandeur (RÉGLER, FIN).
_rows append (["rows"] call comspec_atak_native_fnc_fireSupport);

// Tâches de la mission : la tâche suivie en tête, puis celles en cours, les terminées en bas ; même filtre que les ordres.
private _tasks = simpleTasks player;
private _curT = currentTask player;
private _tOrder = [];
{
    private _closed = (toUpper taskState _x) in ["SUCCEEDED", "FAILED", "CANCELED", "CANCELLED"];
    if ([_closed] call _shows) then { _tOrder pushBack [[[1, 2] select _closed, 0] select (_x isEqualTo _curT), _forEachIndex]; };
} forEach _tasks;
_tOrder sort true;
_rows pushBack ["section", "Tâches de la mission", [format ["%1 tâche(s)", count _tasks], ""] select ((count _tasks) isEqualTo 0)];
if ((count _tOrder) isEqualTo 0) then {
    _rows pushBack (if ((count _tasks) isEqualTo 0) then {
        ["empty", "\a3\ui_f\data\igui\cfg\simpletasks\types\default_ca.paa", "Aucune tâche de mission", "Rien à faire ici : les tâches posées par la mission ou par le Zeus s'ajoutent toutes seules, avec leur distance."]
    } else {
        ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", ["Aucune tâche terminée pour l'instant.", "Toutes les tâches de la mission sont terminées. Onglet TERMINÉS pour les revoir."] select (_filter isEqualTo "OPEN")]]
    });
};
{
    private _idx = _x select 1;
    private _t = _tasks select _idx;
    private _st = toUpper taskState _t;
    ([_st] call _colorOf) params ["_rgb", "_hex"];
    private _desc = taskDescription _t;
    private _dest = taskDestination _t;
    private _has = (_dest isEqualType [] && {(count _dest) >= 2} && {(_dest select 0) != 0 || {(_dest select 1) != 0}});
    private _dist = ["", format [" · %1 m", round (player distance2D _dest)]] select _has;
    private _body = (_desc select 0) regexReplace ["<[^>]*>", ""];
    private _isCur = _t isEqualTo _curT;
    private _tk = format ["m%1", _idx];
    private _btns = [];
    if (_has) then { _btns pushBack ["CARTE", compile format ["private _p = taskDestination ((simpleTasks player) select %1); ['MAP'] call comspec_atak_native_fnc_navigate; [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call comspec_atak_native_fnc_nextFrame;", _idx]]; };
    _btns pushBack [["SUIVRE", "SUIVIE"] select _isCur, compile format ["player setCurrentTask ((simpleTasks player) select %1); ['TASK'] call comspec_atak_native_fnc_pageRender;", _idx], !_isCur, !_isCur];
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold' %1>%2</t>  <t color='%3' size='0.85'>%4</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Tâche de mission%5</t>%6",
        ["", format ["color='%1'", _accHex]] select _isCur, [_desc select 1] call _esc, _hex, _labels getOrDefault [_st, _st], _dist,
        ["", format ["<br/><t size='0.8'>%1</t>", [_body, 200, _tk in _txtOpen] call _bodyTxt]] select (_body isNotEqualTo "")], _btns, _rgb];
    if ((count _body) > 200) then { _rows pushBack ([_tk, count _body] call _foldRow); };
} forEach _tOrder;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
