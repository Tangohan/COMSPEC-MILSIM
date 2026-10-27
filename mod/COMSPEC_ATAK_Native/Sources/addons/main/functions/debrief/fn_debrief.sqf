/*
    App CR fin d'op : compte rendu de fin d'opération imposé dans le téléphone, à remplir ou simplement à lire.
    Demandé par le site (back-office « CR des joueurs (fin d'op) » : avis Athena de genre « debrief », fn_adminNotices) ou
    en jeu par un Zeus, l'hôte, un admin, un UID autorisé (réglage comspec_atak_native_admin_uids) ou un gestionnaire
    de la communauté sur Athena (fn_debriefServer). La fiche s'ouvre d'elle-même dans le téléphone et revient toutes les
    deux minutes tant qu'elle n'est pas remise ; jamais rien sur l'écran de jeu.
    Params : [action, argument, valeur]
      "site" ligne d'avis      demande du site : fiche ["DBF1", mode, titre, intro, texte à lire, questions] lue par
                               parseSimpleArray (aucun code exécuté), méta « debrief=<id>;mode=… »
      "cfg" ligne d'avis       réglages du site (questionnaire par défaut, droit d'imposer, Zeus autorisé)
      "recv" demande           demande faite en jeu (événement comspec_atak_native_debriefRecv)
      "lift" référence         demande levée par le commandement          "force" id : ouvre la fiche dans le téléphone
      "tick"                   toutes les 30 s : la fiche non remise revient toutes les 2 min
      "open" id / "tab" onglet / "set" clé valeur / "send" / "read" / "web" id (renvoi à Athena)
      "may"                    ce joueur peut-il imposer le CR ?          "questions" : questionnaire par défaut
      "sMode" / "sTarget" / "sForce" / "sLift" réf / "sRemind" réf / "sPull"   panneau du commandement
      "track" suivi            suivi des demandes faites en jeu, renvoyé par le serveur
      "forced" demande         ma demande est partie : prévient Athena (joueurs visés)
    Questions : [id, type (text, textarea, select, checkbox), libellé, aide, requis 0/1, [options]]
    État : missionNamespace COMSPEC_ATAK_Dbf (id → fiche), COMSPEC_ATAK_DbfCfg, COMSPEC_ATAK_DbfTrack ;
           uiNamespace COMSPEC_ATAK_DbfUi (onglet, fiche ouverte, réglages du panneau, lignes en défaut).
    Retours au site : POST /api/atak/reports type DEBRIEF (événements opened, read, submitted ; forced pour le commandement).
*/
params [["_act", ""], ["_arg", ""], ["_val", ""]];
private _all = missionNamespace getVariable ["COMSPEC_ATAK_Dbf", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_Dbf", _all];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_DbfUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_DbfUi", _ui];
private _cfg = missionNamespace getVariable ["COMSPEC_ATAK_DbfCfg", createHashMap];
private _now = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DEBRIEF") then { ["DEBRIEF"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _server = { params ["_a", ["_x", []]]; ["comspec_atak_native_debrief", [player, _a, _x]] call CBA_fnc_serverEvent; };
// Questionnaire intégré (même liste que AtakDebriefSpec::BUILTIN sur le site) : traduit à l'affichage.
private _builtin = [
    ["summary", "textarea", "Déroulé de votre mission", "", 1, []],
    ["went_well", "textarea", "Ce qui a bien marché", "", 0, []],
    ["improve", "textarea", "Ce qui doit progresser", "", 0, []],
    ["incidents", "text", "Pertes, blessés, matériel perdu", "", 0, []],
    ["comms", "select", "Liaisons radio et ATAK", "", 0, ["Bonnes", "Correctes", "Difficiles", "Coupées"]],
    ["rating", "select", "Note de la mission", "", 0, ["1", "2", "3", "4", "5"]]
];
// Fiche du site : tableau simple, jamais du code. Renvoie [mode, titre, intro, texte, questions] ou [].
private _parse = {
    params ["_body"];
    if !(_body isEqualType "") exitWith { [] };
    if ((_body select [0, 8]) isNotEqualTo "[""DBF1""") exitWith { [] };
    private _a = parseSimpleArray _body;
    if !(_a isEqualType []) exitWith { [] };
    _a params ["", ["_mode", "fill"], ["_title", ""], ["_intro", ""], ["_read", ""], ["_qs", []]];
    if !(_qs isEqualType []) then { _qs = []; };
    _qs = _qs select { _x isEqualType [] && {(count _x) >= 3} } apply {
        _x params [["_id", ""], ["_t", "text"], ["_l", ""], ["_h", ""], ["_r", 0], ["_o", []]];
        if !(_id isEqualType "") then { _id = str _id; };
        [_id select [0, 40], _t, _l, _h, [0, 1] select (_r isEqualTo 1 || {_r isEqualTo true}), [[], _o] select (_o isEqualType [])]
    };
    [["fill", "read"] select (_mode isEqualTo "read"), _title, _intro, [_read, "¶", "<br/><br/>"] call CBA_fnc_replace, _qs]
};
// Envoi d'un événement à Athena (seulement si le téléphone y est relié). _then : appelé avec [OK ?] sur le jeu.
private _web = {
    params ["_e", "_event", ["_extra", createHashMap], ["_then", {}]];
    if !([] call comspec_atak_native_fnc_bridge) exitWith { [false] call _then; false };
    if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { [false] call _then; false };
    private _sd = createHashMapFromArray [["debrief_event", _event], ["mode", _e getOrDefault ["mode", "fill"]], ["label", _e getOrDefault ["title", ""]],
        ["by", _e getOrDefault ["by", ""]], ["source", "COMSPEC ATAK · CR fin d'op"]];
    if ((_e getOrDefault ["sid", 0]) > 0) then { _sd set ["debrief_id", _e get "sid"]; } else { _sd set ["debrief_ref", _e getOrDefault ["ref", ""]]; };
    { _sd set [_x, _y]; } forEach _extra;
    private _cs = [player, true] call comspec_atak_native_fnc_unitCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    private _p = getPosWorld player;
    private _label = _e getOrDefault ["title", ""];
    private _body = createHashMapFromArray [
        ["report_type", "DEBRIEF"], ["priority", "ROUTINE"], ["submitter_callsign", _cs], ["submitter_unit", groupId (group player)],
        ["submitter_steam_id", getPlayerUID player], ["pos_x", _p select 0], ["pos_y", _p select 1], ["grid_reference", mapGridPosition player],
        ["summary", format ["CR de fin d'opération · %1", [_label, "Fin d'opération"] select (_label isEqualTo "")]], ["details", _sd getOrDefault ["a_summary", ""]],
        ["structured_data", _sd]
    ];
    private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", ""];
    if (_mk isEqualType "" && {_mk isNotEqualTo ""}) then { _body set ["mission_key", _mk]; };
    [[_body] call comspec_atak_native_fnc_json, _then] spawn {
        params ["_json", "_then"];
        private _r = ["COMSPECExtension", "SubmitTacticalReport", [_json]] call comspec_atak_native_fnc_extAwait;
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if !(_r isEqualType "") then { _r = str _r; };
        private _ok = ((toUpper _r) find "OK") >= 0 && {((toUpper _r) find "ERR") < 0};
        [{ params ["_then", "_ok"]; [_ok] call _then; }, [_then, _ok]] call CBA_fnc_execNextFrame;
    };
    true
};
// Fin de la fiche : l'avis du site est validé (lu / remis sur le back-office), le serveur suit les demandes du jeu.
private _close = {
    params ["_e", "_state"];
    _e set ["state", "done"];
    _e set ["doneAt", call _now];
    _e set ["doneKind", _state];
    if ((_e getOrDefault ["msg", ""]) isNotEqualTo "") then {
        [_e get "msg"] spawn { params ["_id"]; ["COMSPECExtension", "AdminControl.Ack", [_id, "acked", getPlayerUID player]] call comspec_atak_native_fnc_extAwait; };
    };
    if ((_e getOrDefault ["ref", ""]) isNotEqualTo "") then { ["state", [_e get "ref", _state]] call _server; };
};
private _add = {
    params ["_e"];
    private _id = _e get "id";
    private _old = _all getOrDefault [_id, createHashMap];
    // Déjà remise ou déjà là : on ne la réimpose pas (une demande relancée remet seulement l'alerte).
    if ((_old getOrDefault ["state", ""]) isEqualTo "done") exitWith { false };
    if ((count _old) > 0) exitWith { _old set ["forcedAt", -1e9]; ["force", _id] call comspec_atak_native_fnc_debrief; false };
    _e set ["state", "new"];
    _e set ["at", call _now];
    _e set ["vals", createHashMap];
    _e set ["forcedAt", -1e9];
    _all set [_id, _e];
    if ((_e getOrDefault ["ref", ""]) isNotEqualTo "") then { ["state", [_e get "ref", "received"]] call _server; };
    ["force", _id] call comspec_atak_native_fnc_debrief;
    true
};
private _cur = {
    private _id = _ui getOrDefault ["open", ""];
    private _e = _all getOrDefault [_id, createHashMap];
    if ((count _e) isEqualTo 0) then {
        private _pend = (values _all) select { (_x get "state") isNotEqualTo "done" };
        if ((count _pend) > 0) then { _e = _pend select ((count _pend) - 1); };
    };
    _e
};
// Garde la saisie avant de redessiner : les boutons sont déjà dans les valeurs.
private _save = {
    params ["_e"];
    if ((count _e) isEqualTo 0) exitWith {};
    private _vals = _e get "vals";
    {
        _x params ["_k", "_t"];
        if (_t in ["text", "textarea", "select"]) then {
            private _v = ["dbf_" + _k, "§"] call comspec_atak_native_fnc_formValue;
            if (_v isNotEqualTo "§") then { _vals set [_k, _v]; _ui set ["errors", (_ui getOrDefault ["errors", []]) - [_k]]; };
        };
    } forEach (_e get "qs");
};

switch (_act) do {
    case "site": {
        _arg params [["_nid", ""], "", "", ["_title", ""], ["_body", ""], "", "", ["_meta", ""]];
        private _m = ["meta", _meta] call comspec_atak_native_fnc_compromiseView;
        private _spec = [_body] call _parse;
        private _sid = parseNumber (_m getOrDefault ["debrief", "0"]);
        if (_sid <= 0) exitWith {};
        if ((count _spec) isEqualTo 0) then { _spec = [_m getOrDefault ["mode", "fill"], _title, "", "", []]; };
        _spec params ["_mode", "_t", "_intro", "_read", "_qs"];
        if (_mode isEqualTo "fill" && {(count _qs) isEqualTo 0}) then { _qs = +_builtin; };
        [createHashMapFromArray [["id", format ["S%1", _sid]], ["src", "site"], ["sid", _sid], ["ref", ""], ["msg", _nid], ["mode", _mode],
            ["title", [_t, "Fin d'opération"] select (_t isEqualTo "")], ["intro", _intro], ["read", _read], ["qs", _qs], ["by", "Athena"]]] call _add;
    };
    case "cfg": {
        _arg params ["", "", "", "", ["_body", ""], "", "", ["_meta", ""]];
        private _m = ["meta", _meta] call comspec_atak_native_fnc_compromiseView;
        private _spec = [_body] call _parse;
        private _c = createHashMapFromArray [["rights", (_m getOrDefault ["rights", "0"]) isEqualTo "1"], ["zeus", (_m getOrDefault ["zeus", "1"]) isNotEqualTo "0"],
            ["mode", _m getOrDefault ["mode", "fill"]], ["intro", _spec param [2, ""]], ["qs", _spec param [4, []]]];
        missionNamespace setVariable ["COMSPEC_ATAK_DbfCfg", _c];
        // Droit donné par Athena (gestionnaire de la communauté, UID autorisé sur le site) : déclaré au serveur.
        if ((player getVariable ["COMSPEC_ATAK_DebriefRight", false]) isNotEqualTo (_c get "rights")) then { player setVariable ["COMSPEC_ATAK_DebriefRight", _c get "rights", true]; };
    };
    case "recv": {
        _arg params [["_ref", ""], ["_mode", "fill"], ["_title", ""], ["_intro", ""], ["_read", ""], ["_qs", []], ["_by", ""]];
        if (_ref isEqualTo "" || {!hasInterface}) exitWith {};
        if (_mode isEqualTo "fill" && {(count _qs) isEqualTo 0}) then { _qs = +_builtin; };
        [createHashMapFromArray [["id", _ref], ["src", "game"], ["sid", 0], ["ref", _ref], ["msg", ""], ["mode", _mode],
            ["title", [_title, "Fin d'opération"] select (_title isEqualTo "")], ["intro", _intro], ["read", _read], ["qs", _qs], ["by", _by]]] call _add;
    };
    case "lift": {
        private _e = _all getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0 || {(_e get "state") isEqualTo "done"}) exitWith {};
        _all deleteAt _arg;
        ["INFO", format ["CR « %1 » levé par le commandement", _e get "title"], 6, 40, "DEBRIEF"] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "force": {
        private _e = _all getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0 || {(_e get "state") isEqualTo "done"}) exitWith {};
        _e set ["forcedAt", diag_tickTime];
        _ui set ["open", _arg];
        _ui set ["tab", "CR"];
        private _read = (_e get "mode") isEqualTo "read";
        private _what = ["Compte rendu de fin d'opération à remplir", "Compte rendu de fin d'opération à lire"] select _read;
        // Alerte dans l'écran du téléphone (attend son ouverture s'il est rangé), notification et vibration.
        ["CR FIN D'OPÉRATION", format ["%1 · %2", _what, _e get "title"], _e getOrDefault ["by", ""], [0.70, 0.40, 0.04, 0.94], 10] call comspec_atak_native_fnc_fullAlert;
        ["WARNING", format ["%1 : %2", _what, _e get "title"], 10, 90, "DEBRIEF"] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        if (isNull player || {!alive player} || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {};
        // Le téléphone s'ouvre sur la fiche ; jamais pendant la conduite, le pilotage, l'inconscience ou un autre menu.
        private _busy = (player getVariable ["ACE_isUnconscious", false]) || {dialog} || {(vehicle player) isNotEqualTo player && {(driver (vehicle player)) isEqualTo player}};
        if (isNull ([] call comspec_atak_native_fnc_display)) then {
            if (!_busy) then { [true] call comspec_atak_native_fnc_open; };
        };
        [{ if !(isNull ([] call comspec_atak_native_fnc_display)) then { ["DEBRIEF"] call comspec_atak_native_fnc_navigate; }; }, [], 0.4] call CBA_fnc_waitAndExecute;
    };
    case "tick": {
        if (!hasInterface) exitWith {};
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        {
            if ((_y get "state") isNotEqualTo "done" && {(diag_tickTime - (_y getOrDefault ["forcedAt", -1e9])) > 120}) then {
                // Déjà ouverte sous les yeux du joueur : on attend.
                if ((_st getOrDefault ["activePage", ""]) isEqualTo "DEBRIEF" && {!isNull ([] call comspec_atak_native_fnc_display)}) then { _y set ["forcedAt", diag_tickTime]; } else { ["force", _x] call comspec_atak_native_fnc_debrief; };
                break;
            };
        } forEach _all;
    };
    case "opened": {
        // Première ouverture de la fiche : « affiché » sur le back-office et dans le suivi du commandement.
        private _e = _all getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0 || {_e getOrDefault ["seen", false]}) exitWith {};
        _e set ["seen", true];
        if ((_e get "state") isEqualTo "new") then { _e set ["state", "open"]; };
        [_e, "opened"] call _web;
        if ((_e getOrDefault ["ref", ""]) isNotEqualTo "") then { ["state", [_e get "ref", "opened"]] call _server; };
    };
    case "open": { [call _cur] call _save; _ui set ["open", _arg]; _ui set ["errors", []]; call _rerender; };
    case "tab": { [call _cur] call _save; _ui set ["tab", _arg]; if (_arg isEqualTo "STAFF") then { ["sPull"] call comspec_atak_native_fnc_debrief; }; call _rerender; };
    case "set": {
        private _e = call _cur;
        if ((count _e) isEqualTo 0) exitWith {};
        [_e] call _save;
        (_e get "vals") set [_arg, _val];
        _ui set ["errors", (_ui getOrDefault ["errors", []]) - [_arg]];
        call _rerender;
    };
    case "send": {
        private _e = call _cur;
        if ((count _e) isEqualTo 0 || {(_e get "state") isEqualTo "done"}) exitWith {};
        [_e] call _save;
        private _vals = _e get "vals";
        private _missing = ((_e get "qs") select { ((_x select 4) isEqualTo 1) && {(trim (_vals getOrDefault [_x select 0, ""])) isEqualTo ""} }) apply { _x select 0 };
        if ((count _missing) > 0) exitWith {
            _ui set ["errors", _missing];
            ["Répondez aux questions marquées d'une étoile.", 3] call comspec_atak_native_fnc_toast;
            call _rerender;
        };
        private _extra = createHashMap;
        { _extra set ["a_" + (_x select 0), _vals getOrDefault [_x select 0, ["", "Non"] select ((_x select 1) isEqualTo "checkbox")]]; } forEach (_e get "qs");
        _e set ["web", "EN COURS"];
        [_e, "submitted"] call _close;
        private _id = _e get "id";
        [_e, "submitted", _extra, compile format ["params ['_ok']; ['web', %1, ['ÉCHEC', 'OK'] select _ok] call comspec_atak_native_fnc_debrief;", str _id]] call _web;
        if (!([] call comspec_atak_native_fnc_bridge) || {!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])}) then { _e set ["web", ["", "HORS LIGNE"] select ((_e get "src") isEqualTo "site")]; };
        _e set ["answers", _extra];
        ["SUCCESS", "Compte rendu de fin d'opération remis", 4, 50, "DEBRIEF"] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "read": {
        private _e = call _cur;
        if ((count _e) isEqualTo 0 || {(_e get "state") isEqualTo "done"}) exitWith {};
        [_e, "read"] call _close;
        [_e, "read"] call _web;
        ["SUCCESS", "Compte rendu de fin d'opération lu", 4, 50, "DEBRIEF"] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "web": {
        // Résultat d'un envoi (valeur OK / ÉCHEC) ou renvoi demandé par le joueur (sans valeur).
        private _e = _all getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0) exitWith {};
        if (_val isNotEqualTo "") exitWith { _e set ["web", _val]; call _rerender; };
        _e set ["web", "EN COURS"];
        [_e, "submitted", _e getOrDefault ["answers", createHashMap], compile format ["params ['_ok']; ['web', %1, ['ÉCHEC', 'OK'] select _ok] call comspec_atak_native_fnc_debrief;", str _arg]] call _web;
        call _rerender;
    };
    case "may": {
        if (isNull player) exitWith { false };
        if (_cfg getOrDefault ["rights", false]) exitWith { true };
        if !([player] call comspec_atak_native_fnc_adminAllowed) exitWith { false };
        // Zeus seul (ni admin, ni hôte, ni UID autorisé) : selon le réglage du site et celui du serveur.
        private _zeusOnly = !isNull (getAssignedCuratorLogic player) && {!(isServer && {hasInterface})} && {!serverCommandAvailable "#kick"}
            && {!((getPlayerUID player) in (((missionNamespace getVariable ["comspec_atak_native_admin_uids", ""]) splitString ", ;") apply { trim _x }))};
        !_zeusOnly || {(_cfg getOrDefault ["zeus", true]) && {missionNamespace getVariable ["comspec_atak_native_debrief_zeus", true]}}
    };
    case "questions": {
        private _q = _cfg getOrDefault ["qs", []];
        [_q, +_builtin] select ((count _q) isEqualTo 0)
    };
    case "sMode": { _ui set ["sMode", _arg]; call _rerender; };
    case "sTarget": { _ui set ["sTarget", _arg]; call _rerender; };
    case "sForce": {
        if !(["may"] call comspec_atak_native_fnc_debrief) exitWith { ["Vous ne pouvez pas imposer le CR.", 3] call comspec_atak_native_fnc_toast; };
        private _mode = _ui getOrDefault ["sMode", _cfg getOrDefault ["mode", "fill"]];
        private _title = trim (["dbf_s_title", ""] call comspec_atak_native_fnc_formValue);
        private _text = trim (["dbf_s_text", ""] call comspec_atak_native_fnc_formValue);
        _ui set ["sTitle", _title];
        _ui set ["sText", _text];
        if (_mode isEqualTo "read" && {_text isEqualTo ""}) exitWith { ["Écrivez le texte à lire.", 3] call comspec_atak_native_fnc_toast; };
        private _target = _ui getOrDefault ["sTarget", "all"];
        private _intro = [_cfg getOrDefault ["intro", ""], _text] select (_mode isEqualTo "fill" && {_text isNotEqualTo ""});
        ["force", [_mode, _target, [_title, "Fin d'opération"] select (_title isEqualTo ""), _intro, [_text, ""] select (_mode isEqualTo "fill"),
            [["questions"] call comspec_atak_native_fnc_debrief, []] select (_mode isEqualTo "read"), []]] call _server;
        ["CR imposé : il s'ouvre dans les téléphones visés.", 3] call comspec_atak_native_fnc_toast;
    };
    case "zeus": {
        // Menu Zeus (fn_zeusMenu) : [mode, joueurs visés, texte à lire].
        _arg params [["_mode", "fill"], ["_units", []], ["_text", ""]];
        if !(["may"] call comspec_atak_native_fnc_debrief) exitWith { ["feedback", ["ATAK : vous ne pouvez pas imposer le CR."]] call comspec_atak_native_fnc_zeusMenu; };
        ["force", [_mode, "units", "Fin d'opération", [_cfg getOrDefault ["intro", ""], ""] select (_mode isEqualTo "read"), [_text, ""] select (_mode isEqualTo "fill"),
            [["questions"] call comspec_atak_native_fnc_debrief, []] select (_mode isEqualTo "read"), _units]] call _server;
    };
    case "sLift": { ["lift", [_arg, _val]] call _server; };
    case "sRemind": { ["remind", [_arg]] call _server; ["Relance envoyée aux joueurs qui n'ont pas remis le CR.", 3] call comspec_atak_native_fnc_toast; };
    case "sPull": { ["pull"] call _server; };
    case "track": {
        missionNamespace setVariable ["COMSPEC_ATAK_DbfTrack", _arg];
        call _rerender;
    };
    case "forced": {
        // Ma demande vient de partir : Athena la crée sur le back-office avec la liste des joueurs visés.
        _arg params [["_ref", ""], ["_mode", "fill"], ["_title", ""], ["_read", ""], ["_players", []]];
        [createHashMapFromArray [["ref", _ref], ["mode", _mode], ["title", _title], ["by", [player, true] call comspec_atak_native_fnc_unitCallsign]], "forced",
            createHashMapFromArray [["read_text", _read], ["players", _players apply { createHashMapFromArray [["uid", _x select 0], ["name", _x select 1]] }]]] call _web;
    };
};
