/*
    Contrôle et présentation des papiers entre joueurs, côté joueur. Fenêtre : fn_idP2pUi ; serveur : fn_idP2pServer.
    [_op, _args] call comspec_sse_fnc_idP2pClient
    Gestes (menu ACE, raccourcis clavier, fenêtres) :
      on                -> Boolean : contrôles entre joueurs autorisés (réglage, choix du Zeus)
      target            -> personne visée, vivante, à portée (objNull sinon)
      near              -> personnes à portée, à pied et en vue, la personne visée d'abord
      ask [personne]    « Papiers, s'il vous plaît » (2 s, comme le menu ACE) ; sans personne : celle que je vise
      present [classe]  ouvre « Présenter mes papiers » (classe cochée d'avance, facultative)
      fromViewer        bouton PRÉSENTER de la visionneuse, sur un de mes papiers
      answerOpen        rouvre la demande en attente (menu ACE personnel › Mes papiers › Répondre à la demande)
      pending           -> demandes reçues encore valables [[contrôleur, fin, refus permis, numéro]…]
      answer [contrôleur, accept | refuse, classes, show | give, motif]     view [docId]  ouvre la visionneuse
      popWait [données, texte, type]   fenêtre du contrôleur dès que l'écran est libre (1 s), sinon notification du téléphone
    Messages du serveur (fn_idClient « p2p ») :
      request [contrôleur, délai, refus permis, numéro] · expired [contrôleur, numéro] · asked [contrôlé, délai, numéro]
      result [contrôlé, accepted | refused | timeout, motif, papiers, show | give, numéro] · shown [porteur, papiers, show | give]
      sent [destinataire, nombre, show | give | refused] · denied [personne, code] · zeus [0 | 1 | 2]
    Rien ne s'affiche par-dessus le jeu : fenêtre du mod si aucune autre n'est ouverte, sinon notification du téléphone
    (centre de notifications de l'ATAK, app Contrôle ID) et menu ACE personnel. Jamais d'accord automatique.
    Journal : chaque contrôle (accepté, refusé, sans réponse) et chaque présentation part à Athena dans la forme d'un
    contrôle d'identité (fn_fileSubmit : licence acceptée seulement, file hors ligne sans liaison), si la transmission
    automatique est active. Niveau N0 « non fait », constats p2p, p2p_accepted | p2p_refused | p2p_no_answer | p2p_presented.
*/
params [["_op", "", [""]], ["_a", []]];
if (!hasInterface) exitWith { false };
private _L = { localize ("STR_COMSPEC_SSE_P2P_" + _this) };
private _rules = { _this call comspec_sse_fnc_idP2pRules };
private _max = ["distance", missionNamespace getVariable ["comspec_sse_id_p2pDistance", 3]] call _rules;
private _uiOpen = { !isNull (uiNamespace getVariable ["comspec_sse_idP2pDisplay", displayNull]) };
private _uiMode = { (uiNamespace getVariable ["comspec_sse_idP2p", createHashMap]) getOrDefault ["mode", ""] };
// Une fenêtre peut-elle s'ouvrir maintenant ? Jamais par-dessus une autre fenêtre ni le téléphone.
private _canPop = { !dialog && {!(call _uiOpen)} && {isNil "comspec_atak_native_fnc_display" || {isNull ([] call comspec_atak_native_fnc_display)}} };
private _phone = { params ["_t", ["_type", "INFO"]]; [_t, _type, "", "IDCHECK"] call comspec_sse_fnc_notify; };
private _buzz = { if (!isNil "comspec_atak_native_fnc_vibrate") then { [] call comspec_atak_native_fnc_vibrate; }; };
private _pend = (missionNamespace getVariable ["comspec_sse_idP2pPending", []]) select { !isNull (_x select 0) && {(_x select 1) > time} };
missionNamespace setVariable ["comspec_sse_idP2pPending", _pend];
// Papiers reçus : jetons locaux (menu ACE › Contrôle d'identité › Contrôler l'identité), puis visionneuse ou liste.
private _receive = {
    params ["_holder", "_docs", "_how"];
    private _tokens = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
    missionNamespace setVariable ["comspec_sse_idTokens", _tokens];
    private _life = (missionNamespace getVariable ["comspec_sse_id_tokenTime", 120]) - 1;
    { _tokens set [_x select 0, [_holder, _x select 1, time + _life]]; } forEach _docs;
    private _auto = (missionNamespace getVariable ["comspec_sse_id_autoView", true]) isEqualTo true;
    private _mine = (call _uiOpen) && {(call _uiMode) isEqualTo "wait"};
    if (!_mine && {!(call _canPop)}) exitWith {
        [format [((["N_Received", "N_Given"] select (_how isEqualTo "give")) call _L), name _holder, count _docs], "INFO"] call _phone;
        call _buzz;
    };
    // Un seul papier : il s'ouvre tout de suite en grand (réglage « Ouvrir les papiers tendus à l'écran »).
    if (_auto && {(count _docs) isEqualTo 1}) exitWith {
        if (_mine) then { ["status", [("S_Opening" call _L), "muted"]] call comspec_sse_fnc_idP2pUi; };
        ["view", [(_docs select 0) select 0]] call comspec_sse_fnc_idP2pClient;
    };
    ["open", ["received", createHashMapFromArray [["peer", _holder], ["docs", _docs], ["how", _how], ["end", time + _life]]]] call comspec_sse_fnc_idP2pUi;
};

switch (_op) do {
    case "on": { ["enabled", [missionNamespace getVariable ["comspec_sse_id_p2pEnabled", true], missionNamespace getVariable ["comspec_sse_idP2pZeus", 0]]] call _rules };
    case "pending": { _pend };
    case "target": {
        private _t = cursorObject;
        if (isNull _t || {!(_t isKindOf "CAManBase")}) then { _t = cursorTarget; };
        if (isNull _t || {!(_t isKindOf "CAManBase")} || {_t isEqualTo player} || {!alive _t} || {(player distance _t) > _max}) exitWith { objNull };
        _t
    };
    case "near": {
        private _aim = ["target"] call comspec_sse_fnc_idP2pClient;
        private _list = (player nearEntities ["CAManBase", _max]) select {
            _x isNotEqualTo player && {alive _x} && {isNull objectParent _x}
                && {(["range", [0, _max, [player, "VIEW", _x] checkVisibility [eyePos player, eyePos _x], true]] call _rules) isEqualTo ""}
        };
        // Celui qui m'a demandé mes papiers, puis la personne visée, puis les plus proches.
        private _askers = _pend apply { _x select 0 };
        [_list, [], { [[player distance _x, -1] select (_x isEqualTo _aim), -2] select (_x in _askers) }] call BIS_fnc_sortBy
    };

    case "ask": {
        _a params [["_t", objNull, [objNull]]];
        if (isNull _t) then { _t = ["target"] call comspec_sse_fnc_idP2pClient; };
        if (!(missionNamespace getVariable ["comspec_sse_id_enabled", true]) || {!alive player} || {!isNull objectParent player}) exitWith { false };
        if (isNull _t) exitWith { [format [("N_NoTarget" call _L), _max], "WARNING"] call _phone; false };
        if (isPlayer _t && {!(["on"] call comspec_sse_fnc_idP2pClient)}) exitWith { [("D_off" call _L), "WARNING"] call _phone; false };
        ["send", ["begin", [player, "ask", _t]]] call comspec_sse_fnc_idClient;
        [2, "Papiers, s'il vous plaît…", { params ["_t"]; ["send", ["ask", [player, _t]]] call comspec_sse_fnc_idClient; }, [_t], _t] call comspec_sse_fnc_progressAction;
        true
    };
    case "present": {
        _a params [["_cls", "", [""]]];
        if (!(missionNamespace getVariable ["comspec_sse_id_enabled", true]) || {!alive player}) exitWith { false };
        private _to = if (_pend isEqualTo []) then { objNull } else { (_pend select -1) select 0 };
        if (isNull _to) then { _to = ["target"] call comspec_sse_fnc_idP2pClient; };
        ["open", ["present", createHashMapFromArray [["peer", _to], ["pre", _cls]]]] call comspec_sse_fnc_idP2pUi
    };
    case "fromViewer": {
        if !((uiNamespace getVariable ["comspec_sse_idView", createHashMap]) getOrDefault ["own", false]) exitWith { false };
        ["present", [missionNamespace getVariable ["comspec_sse_idOwnViewCls", ""]]] call comspec_sse_fnc_idP2pClient
    };
    case "answerOpen": {
        if (_pend isEqualTo []) exitWith { false };
        (_pend select -1) params ["_c", "_end", "_canRefuse", "_serial"];
        ["open", ["request", createHashMapFromArray [["peer", _c], ["end", _end], ["canRefuse", _canRefuse], ["serial", _serial]]]] call comspec_sse_fnc_idP2pUi
    };
    case "answer": {
        _a params [["_c", objNull, [objNull]], ["_ans", "refuse", [""]], ["_classes", [], [[]]], ["_how", "show", [""]], ["_reason", "", [""]]];
        if (isNull _c) exitWith { false };
        ["send", ["p2pAnswer", [player, _c, _ans, _classes, _how, _reason]]] call comspec_sse_fnc_idClient;
        if (_ans isEqualTo "refuse") then {
            missionNamespace setVariable ["comspec_sse_idP2pPending", _pend select { (_x select 0) isNotEqualTo _c }];
            // Geste de refus, à pied seulement (rien d'imposé : un simple « non » de la tête et de la main).
            if (isNull objectParent player && {alive player}) then { player playActionNow "gestureNo"; };
        };
        true
    };
    case "view": {
        _a params [["_id", "", [""]]];
        if (_id isEqualTo "") exitWith { false };
        ["send", ["begin", [player, "view", _id]]] call comspec_sse_fnc_idClient;
        // 60 % de la durée d'examen (2 s) exigés par le serveur, plus la latence (comme un papier tendu par une IA).
        [{ ["send", ["view", [player, _this]]] call comspec_sse_fnc_idClient; }, _id, 1.5] call CBA_fnc_waitAndExecute;
        // Visionneuse pas venue (document repris entre-temps) : la liste redevient utilisable.
        [{ if (!isNull (uiNamespace getVariable ["comspec_sse_idP2pDisplay", displayNull])) then { ["status", ["", "muted"]] call comspec_sse_fnc_idP2pUi; }; }, [], 5] call CBA_fnc_waitAndExecute;
        true
    };

    // --- Messages du serveur ------------------------------------------------------------------------------------
    case "request": {
        _a params [["_c", objNull, [objNull]], ["_delay", 20, [0]], ["_canRefuse", true, [true]], ["_serial", 0, [0]]];
        if (isNull _c) exitWith { false };
        _pend = _pend select { (_x select 0) isNotEqualTo _c };
        _pend pushBack [_c, time + _delay, _canRefuse, _serial];
        missionNamespace setVariable ["comspec_sse_idP2pPending", _pend];
        call _buzz;
        if (call _canPop) exitWith { ["answerOpen"] call comspec_sse_fnc_idP2pClient };
        [format [("N_Request" call _L), name _c, round _delay], "WARNING"] call _phone;
        true
    };
    case "expired": {
        _a params [["_c", objNull, [objNull]], ["_serial", 0, [0]]];
        missionNamespace setVariable ["comspec_sse_idP2pPending", _pend select { (_x select 3) isNotEqualTo _serial }];
        private _st = uiNamespace getVariable ["comspec_sse_idP2p", createHashMap];
        if ((call _uiOpen) && {(call _uiMode) isEqualTo "request"} && {(_st getOrDefault ["serial", -1]) isEqualTo _serial}) then { ["done"] call comspec_sse_fnc_idP2pUi; };
        true
    };
    case "asked": {
        _a params [["_t", objNull, [objNull]], ["_delay", 20, [0]], ["_serial", 0, [0]]];
        ["popWait", [createHashMapFromArray [["peer", _t], ["end", time + _delay], ["serial", _serial]], format [("N_Asked" call _L), name _t, round _delay], "INFO"]] call comspec_sse_fnc_idP2pClient
    };
    // Fenêtre du contrôleur : la barre de progression de la demande vient de finir et se ferme (1 s d'attente au plus) ;
    // toujours une autre fenêtre ou le téléphone à l'écran : notification du téléphone à la place.
    case "popWait": {
        _a params [["_extra", createHashMap, [createHashMap]], ["_text", "", [""]], ["_type", "INFO", [""]]];
        [
            { !dialog && {isNull (uiNamespace getVariable ["comspec_sse_idP2pDisplay", displayNull])} && {isNil "comspec_atak_native_fnc_display" || {isNull ([] call comspec_atak_native_fnc_display)}} },
            {
                params ["_extra"];
                // Réponse déjà arrivée entre-temps : rien à attendre.
                if ((_extra getOrDefault ["serial", -1]) isEqualTo (missionNamespace getVariable ["comspec_sse_idP2pResolved", -2])) exitWith {};
                ["open", ["wait", _extra]] call comspec_sse_fnc_idP2pUi;
            },
            [_extra, _text, _type], 1,
            { params ["", "_text", "_type"]; [_text, _type, "", "IDCHECK"] call comspec_sse_fnc_notify; }
        ] call CBA_fnc_waitUntilAndExecute;
        true
    };
    case "result": {
        _a params [["_t", objNull, [objNull]], ["_state", "", [""]], ["_reason", "", [""]], ["_docs", [], [[]]], ["_how", "show", [""]], ["_serial", 0, [0]]];
        private _who = [name _t, ("Someone" call _L)] select (isNull _t);
        missionNamespace setVariable ["comspec_sse_idP2pResolved", _serial];
        ["log", [_t,["no_answer", _state] select (_state in ["accepted", "refused"]), _docs, _how, _reason, _serial]] call comspec_sse_fnc_idP2pClient;
        private _st = uiNamespace getVariable ["comspec_sse_idP2p", createHashMap];
        private _mine = (call _uiOpen) && {(call _uiMode) isEqualTo "wait"} && {(_st getOrDefault ["serial", -1]) isEqualTo _serial};
        if (_state isEqualTo "accepted") exitWith {
            missionNamespace setVariable ["comspec_sse_idLastAnswer", [_t, "present", format [("N_Accepted" call _L), _who], time]];
            // Document parti par le menu ACE (un seul, jeton classique) : la fenêtre d'attente n'a plus rien à dire.
            if (_docs isEqualTo []) exitWith { if (_mine) then { ["done"] call comspec_sse_fnc_idP2pUi; }; true };
            [_t, _docs, _how] call _receive;
            true
        };
        private _text = if (_state isEqualTo "refused") then {
            [format ["%1 (%2)", ("R_Refused" call _L), (("Reason_" + _reason) call _L)], ("R_Refused" call _L)] select (_reason isEqualTo "")
        } else { ("R_Timeout" call _L) };
        // Dernière réponse d'une personne contrôlée : reprise par les comptes rendus SALUTE / SPOTREP (fn_idReport).
        missionNamespace setVariable ["comspec_sse_idLastAnswer", [_t, ["none", "refuse"] select (_state isEqualTo "refused"), _text, time]];
        if (_mine) exitWith {
            _st set ["result", _state];
            _st set ["reason", _reason];
            ["render"] call comspec_sse_fnc_idP2pUi;
            true
        };
        [format ["%1 : %2", _who, _text], "WARNING"] call _phone;
        true
    };
    case "shown": {
        _a params [["_holder", objNull, [objNull]], ["_docs", [], [[]]], ["_how", "show", [""]]];
        if (isNull _holder || {_docs isEqualTo []}) exitWith { false };
        ["log", [_holder, "presented", _docs, _how, "", 0]] call comspec_sse_fnc_idP2pClient;
        [_holder, _docs, _how] call _receive;
        true
    };
    case "sent": {
        _a params [["_to", objNull, [objNull]], ["_n", 0, [0]], ["_how", "show", [""]]];
        missionNamespace setVariable ["comspec_sse_idP2pPending", _pend select { (_x select 0) isNotEqualTo _to }];
        if ((call _uiOpen) && {(call _uiMode) in ["request", "present"]}) then { ["done"] call comspec_sse_fnc_idP2pUi; };
        true
    };
    case "denied": {
        _a params [["_t", objNull, [objNull]], ["_code", "", [""]]];
        private _text = format [(("D_" + _code) call _L), [name _t, ("Someone" call _L)] select (isNull _t), _max];
        if (call _uiOpen) exitWith {
            ["status", [_text, "warn"]] call comspec_sse_fnc_idP2pUi;
            // Personne partie, papier posé entre-temps : la liste et les destinataires sont relus.
            if ((call _uiMode) in ["request", "present"]) then { ["render"] call comspec_sse_fnc_idP2pUi; };
            true
        };
        // Demande que le serveur n'a pas prise (trop loin, vue masquée, demande trop rapprochée…) : dite dans la fenêtre.
        ["popWait", [createHashMapFromArray [["peer", _t], ["result", "denied"], ["text", _text]], _text, "WARNING"]] call comspec_sse_fnc_idP2pClient
    };
    case "zeus": {
        _a params [["_v", 0, [0]]];
        [((["Zeus_Follow", "Zeus_On", "Zeus_Off"] select ((_v max 0) min 2)) call _L), "info"] call comspec_sse_fnc_zeusNotify;
        true
    };

    // --- Journal Athena ------------------------------------------------------------------------------------------
    case "log": {
        _a params [["_subj", objNull, [objNull]], ["_result", "", [""]], ["_docs", [], [[]]], ["_how", "show", [""]], ["_reason", "", [""]], ["_serial", 0, [0]]];
        if (isNull _subj || {_result isEqualTo ""}) exitWith { false };
        // Réglage de la communauté (paquet Athena), sinon réglage CBA de la mission ; la licence est vérifiée par fn_fileSubmit.
        if !(missionNamespace getVariable ["comspec_sse_id_autoTransmitCommunity", missionNamespace getVariable ["comspec_sse_id_autoTransmit", true]]) exitWith { false };
        private _first = _docs param [0, []];
        private _codes = ["p2p", "p2p_" + _result];
        if (_docs isNotEqualTo []) then { _codes pushBack ("p2p_" + _how); _codes pushBack format ["p2p_docs:%1", count _docs]; };
        if (_reason isNotEqualTo "") then { _codes pushBack ("p2p_reason_" + _reason); };
        private _case = if (isNil "comspec_sse_fnc_getCaseReference") then { "" } else { [] call comspec_sse_fnc_getCaseReference };
        [createHashMapFromArray [
            ["kind", "id_p2p"],
            ["idempotency_key", format ["P2P-%1-%2-%3", getPlayerUID player, round (serverTime max time), _serial]],
            ["document", createHashMapFromArray [["id", ""], ["local_id", _first param [0, ""]], ["type", _first param [2, ""]], ["country", _first param [3, ""]], ["number", _first param [4, ""]]]],
            ["presented_name", createHashMapFromArray [["last", _first param [5, ""]], ["first", _first param [6, ""]], ["dob", _first param [7, ""]]]],
            ["subject", createHashMapFromArray [["person_id", ""], ["netid", netId _subj], ["bio_key", [_subj] call comspec_sse_fnc_bioPersonKey], ["steam_id", [getPlayerUID _subj, ""] select !(isPlayer _subj)]]],
            ["controller", createHashMapFromArray [["callsign", name player], ["steam_id", getPlayerUID player]]],
            ["levels", ["N0"]],
            ["results", createHashMapFromArray [["N0", createHashMapFromArray [["state", "na"], ["verdict", ""], ["codes", _codes],
                ["p2p", createHashMapFromArray [["kind", "player"], ["result", _result], ["mode", _how], ["reason", _reason],
                    ["documents", _docs apply { createHashMapFromArray [["type", _x param [2, ""]], ["country", _x param [3, ""]], ["number", _x param [4, ""]]] }]]]]]]],
            ["verdict", ""],
            ["grid", mapGridPosition player],
            ["pos", (getPosASL player) apply { round _x }],
            ["game_time", format ["%1-%2-%3 %4", date select 0, date select 1, date select 2, [dayTime, "HH:MM"] call BIS_fnc_timeToString]],
            ["case", _case]
        ]] call comspec_sse_fnc_fileSubmit
    };
    default { false };
};
