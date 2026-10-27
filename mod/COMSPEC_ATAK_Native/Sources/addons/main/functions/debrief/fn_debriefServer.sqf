/*
    App CR fin d'op, côté serveur (seul juge des droits). Événement CBA serveur comspec_atak_native_debrief :
      [demandeur, action, arguments]. Imposer, relancer, lever et suivre : Zeus (si le réglage comspec_atak_native_debrief_zeus
      le permet), hôte, admin connecté, UID autorisé (comspec_atak_native_admin_uids, fn_adminAllowed) ou gestionnaire
      désigné par Athena (variable publique COMSPEC_ATAK_DebriefRight du joueur, donnée par le site).
    Actions :
      "force"  [mode, cible (all | side | group | units), titre, intro, texte à lire, questions, joueurs]   nouvelle demande :
               chaque joueur visé la reçoit (comspec_atak_native_debriefRecv), le demandeur reçoit « forced » puis le suivi
      "remind" [réf]              renvoie la demande aux joueurs qui ne l'ont pas remise
      "lift"   [réf, joueurs]     lève la demande (réf vide : toutes les demandes en cours de ces joueurs)
      "pull"                      suivi de toutes les demandes de la partie, au demandeur
      "state"  [réf, état]        un joueur a reçu, ouvert, lu ou remis sa fiche (tout joueur)
      "hello"                     joueur arrivé en cours de partie : reçoit les demandes en cours qui le visent (tout joueur)
    Interne : "init" (postInit serveur).
    Suivi publié : [[réf, mode, titre, auteur, heure, levée, [[uid, nom, état, heure]…]]…] (comspec_atak_native_debriefData).
*/
if (!isServer) exitWith {};
params [["_act", ""], ["_args", []], ["_caller", objNull]];
private _store = missionNamespace getVariable ["COMSPEC_ATAK_DbfSrv", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_DbfSrv", _store];
private _now = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _players = { (allPlayers - (entities "HeadlessClient_F")) select { alive _x } };
private _pack = { params ["_d"]; [_d get "ref", _d get "mode", _d get "title", _d get "by", _d get "at", _d get "lifted", (_d get "rows") apply { [_x] + _y }] };
private _payload = { params ["_d"]; [_d get "ref", _d get "mode", _d get "title", _d get "intro", _d get "read", _d get "qs", _d get "by"] };
// Suivi renvoyé au demandeur et à ceux qui l'ont consulté.
private _publish = {
    private _data = (values _store) apply { [_x] call _pack };
    private _w = missionNamespace getVariable ["COMSPEC_ATAK_DbfWatch", []];
    { private _uid = _x; private _u = (call _players) select { (getPlayerUID _x) isEqualTo _uid }; if ((count _u) > 0) then { ["comspec_atak_native_debriefData", ["track", _data], _u select 0] call CBA_fnc_targetEvent; }; } forEach _w;
};
private _watch = { params ["_u"]; private _w = missionNamespace getVariable ["COMSPEC_ATAK_DbfWatch", []]; _w pushBackUnique (getPlayerUID _u); missionNamespace setVariable ["COMSPEC_ATAK_DbfWatch", _w]; };
switch (_act) do {
    case "init": {
        ["comspec_atak_native_debrief", {
            params [["_caller", objNull], ["_a", ""], ["_xa", []]];
            if (isNull _caller) exitWith {};
            if (_a in ["state", "hello"]) exitWith { [_a, _xa, _caller] call comspec_atak_native_fnc_debriefServer; };
            private _zeusOnly = !isNull (getAssignedCuratorLogic _caller) && {!(local _caller)} && {(admin (owner _caller)) isEqualTo 0}
                && {!((getPlayerUID _caller) in (((missionNamespace getVariable ["comspec_atak_native_admin_uids", ""]) splitString ", ;") apply { trim _x }))};
            private _ok = (_caller getVariable ["COMSPEC_ATAK_DebriefRight", false]) isEqualTo true
                || {[_caller] call comspec_atak_native_fnc_adminAllowed && {!_zeusOnly || {missionNamespace getVariable ["comspec_atak_native_debrief_zeus", true]}}};
            if (!_ok) exitWith {
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][DEBRIEF] Demande refusée (ni Zeus, ni admin, ni UID autorisé) : %1 %2", name _caller, _a];
                ["comspec_atak_native_debriefData", ["denied", []], _caller] call CBA_fnc_targetEvent;
            };
            [_a, _xa, _caller] call comspec_atak_native_fnc_debriefServer;
        }] call CBA_fnc_addEventHandler;
    };
    case "force": {
        _args params [["_mode", "fill"], ["_target", "all"], ["_title", ""], ["_intro", ""], ["_read", ""], ["_qs", []], ["_units", []]];
        _mode = ["fill", "read"] select (_mode isEqualTo "read");
        private _to = switch (_target) do {
            case "side": { (call _players) select { (side group _x) isEqualTo (side group _caller) } };
            case "group": { (units group _caller) select { isPlayer _x } };
            case "units": { (call _players) select { _x in _units } };
            default { call _players };
        };
        // Le demandeur ne se l'impose pas (sauf s'il l'a visé lui-même depuis Zeus).
        if (_target isNotEqualTo "units") then { _to = _to - [_caller]; };
        if ((count _to) isEqualTo 0) exitWith { ["comspec_atak_native_debriefData", ["none", []], _caller] call CBA_fnc_targetEvent; };
        systemTimeUTC params ["_y", "_mo", "_dd", "_h", "_mi", "_s"];
        private _ref = format ["G%1%2%3%4%5%6-%7", _y, [_mo, 2] call CBA_fnc_formatNumber, [_dd, 2] call CBA_fnc_formatNumber, [_h, 2] call CBA_fnc_formatNumber,
            [_mi, 2] call CBA_fnc_formatNumber, [_s, 2] call CBA_fnc_formatNumber, floor random 10000];
        private _by = name _caller;
        private _rows = createHashMap;
        { _rows set [getPlayerUID _x, [name _x, "sent", call _now]]; } forEach _to;
        private _d = createHashMapFromArray [["ref", _ref], ["mode", _mode], ["title", _title select [0, 120]], ["intro", _intro select [0, 400]], ["read", _read select [0, 2000]],
            ["qs", _qs select [0, 20]], ["by", _by], ["at", call _now], ["target", _target], ["side", side group _caller], ["lifted", false], ["rows", _rows]];
        _store set [_ref, _d];
        { ["comspec_atak_native_debriefRecv", [[_d] call _payload], _x] call CBA_fnc_targetEvent; } forEach _to;
        [_caller] call _watch;
        ["comspec_atak_native_debriefData", ["forced", [_ref, _mode, _d get "title", _d get "read", _to apply { [getPlayerUID _x, name _x] }]], _caller] call CBA_fnc_targetEvent;
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][DEBRIEF] %1 impose le CR %2 (%3, %4) à %5 joueur(s)", _by, _ref, _mode, _target, count _to];
        call _publish;
    };
    case "remind": {
        private _d = _store getOrDefault [_args param [0, ""], createHashMap];
        if ((count _d) isEqualTo 0 || {_d get "lifted"}) exitWith {};
        private _rows = _d get "rows";
        { if (((_rows getOrDefault [getPlayerUID _x, ["", ""]]) select 1) in ["sent", "received", "opened"]) then { ["comspec_atak_native_debriefRecv", [[_d] call _payload], _x] call CBA_fnc_targetEvent; }; } forEach (call _players);
    };
    case "lift": {
        _args params [["_ref", ""], ["_units", []]];
        private _uids = (_units select { _x isEqualType objNull }) apply { getPlayerUID _x };
        {
            private _d = _y;
            if (!(_d get "lifted") && {_x isEqualTo _ref || {_ref isEqualTo "" && {((keys (_d get "rows")) arrayIntersect _uids) isNotEqualTo []}}}) then {
                _d set ["lifted", true];
                private _r = _d get "ref";
                { if ((getPlayerUID _x) in (keys (_d get "rows"))) then { ["comspec_atak_native_debriefData", ["lift", _r], _x] call CBA_fnc_targetEvent; }; } forEach (call _players);
                diag_log format ["[COMSPEC ATAK NATIVE][INFO][DEBRIEF] %1 lève le CR %2", name _caller, _r];
            };
        } forEach _store;
        call _publish;
    };
    case "pull": {
        [_caller] call _watch;
        ["comspec_atak_native_debriefData", ["track", (values _store) apply { [_x] call _pack }], _caller] call CBA_fnc_targetEvent;
    };
    case "state": {
        _args params [["_ref", ""], ["_state", ""]];
        private _d = _store getOrDefault [_ref, createHashMap];
        if ((count _d) isEqualTo 0 || {!(_state in ["received", "opened", "read", "submitted"])}) exitWith {};
        private _rows = _d get "rows";
        private _old = (_rows getOrDefault [getPlayerUID _caller, ["", "sent"]]) select 1;
        // Un état ne recule pas (une fiche remise ne redevient pas « ouverte »).
        private _order = ["sent", "received", "opened", "read", "submitted"];
        if ((_order find _state) <= (_order find _old)) exitWith {};
        _rows set [getPlayerUID _caller, [name _caller, _state, call _now]];
        call _publish;
    };
    case "hello": {
        private _uid = getPlayerUID _caller;
        {
            private _d = _y;
            private _mine = _uid in (keys (_d get "rows"));
            private _match = _mine || {(_d get "target") isEqualTo "all"} || {(_d get "target") isEqualTo "side" && {(side group _caller) isEqualTo (_d get "side")}};
            if (!(_d get "lifted") && {_match}) then {
                private _st = ((_d get "rows") getOrDefault [_uid, ["", "sent"]]) select 1;
                if !(_st in ["read", "submitted"]) then {
                    if (!_mine) then { (_d get "rows") set [_uid, [name _caller, "sent", call _now]]; };
                    ["comspec_atak_native_debriefRecv", [[_d] call _payload], _caller] call CBA_fnc_targetEvent;
                };
            };
        } forEach _store;
    };
};
