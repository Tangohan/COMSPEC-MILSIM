/*
    Envoi vers le canal TOC (Athena). Le message s'affiche tout de suite, puis est remplacé par l'écho Athena.
    Sans Athena (mode full local, service « Non ») : le message part aux téléphones du camp par le serveur
    (comspec_atak_native_chatLocal, rejoué aux arrivants), canal groupe limité au groupe, et s'affiche dans le flux du PC TOC.
      ["recv", [id, auteur, texte, "jj/mm HH:MM", canal, camp, UID, netId du groupe]]  message reçu
*/
if ((_this param [0, ""]) isEqualTo "recv") exitWith {
    (_this param [1, []]) params [["_id", ""], ["_author", ""], ["_body", ""], ["_time", ""], ["_ch", "general"], ["_side", ""], ["_uid", ""], ["_grp", ""]];
    if (!hasInterface || {isNull player} || {_side isNotEqualTo str side group player} || {_uid isEqualTo getPlayerUID player}) exitWith { false };
    if (_ch isEqualTo "groupe" && {_grp isNotEqualTo netId group player}) exitWith { false };
    private _dup = false;
    if ([] call comspec_atak_native_fnc_bridge) then {
        // Même fil que les messages d'Athena (fn_messagesAll) : [id, auteur, texte, heure, canal, moi].
        private _store = +(missionNamespace getVariable ["COMSPEC_Comms_Messages", []]);
        _dup = (_store findIf { (_x param [0, ""]) isEqualTo _id }) >= 0;
        if (!_dup) then {
            _store pushBack [_id, _author, _body, _time, _ch, false];
            while { (count _store) > 120 } do { _store deleteAt 0; };
            missionNamespace setVariable ["COMSPEC_Comms_Messages", _store];
        };
    } else {
        private _msgs = +((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["messages", []]);
        _dup = (_msgs findIf { (_x getOrDefault ["id", ""]) isEqualTo _id }) >= 0;
        if (!_dup) then {
            _msgs pushBack createHashMapFromArray [["id", _id], ["author", _author], ["body", _body], ["time", (_time splitString " ") param [1, _time]], ["status", "RECEIVED"]];
            while { (count _msgs) > 200 } do { _msgs deleteAt 0; };
            ["messages", _msgs] call comspec_atak_native_fnc_storeSet;
        };
    };
    if (_dup) exitWith { false };
    if ([player] call comspec_atak_native_fnc_hasDevice) then {
        ["MESSAGE", format ["%1 : %2", _author, _body select [0, 80]], 5, 40, "CHAT"] call comspec_atak_native_fnc_notify;
    };
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then { [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
    true
};
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (isNull _edit) exitWith { false };
private _message = trim ctrlText _edit;
if (_message isEqualTo "") exitWith { false };
// Commandes (/urgent, /contact, /aide…) : voir fn_chatCommand.
([_message] call comspec_atak_native_fnc_chatCommand) params ["_prio", "_kind", "_text", "_unknown", "_tags", "_help"];
if (_help) exitWith {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatWiki", true];
    _edit ctrlSetText (("") call comspec_atak_native_fnc_tr); uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
    [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    false
};
if ((count _unknown) > 0) exitWith { ["WARNING", format ["Commande inconnue : %1 (tapez /aide)", _unknown joinString " "], 4, 30] call comspec_atak_native_fnc_notify; false };
if (_text isEqualTo "") exitWith { ["WARNING", "Message vide après la commande", 3, 20] call comspec_atak_native_fnc_notify; false };
// Sans Athena : message relayé au camp par le serveur (voir « recv » plus haut), en plus de l'affichage local.
private _local = ([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on";
private _ev = [];
if (_local) then {
    private _ch = toLower trim ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["chatChannel", "general"]);
    if (_ch in ["", "squad", "global", "alertes"]) then { _ch = "general"; };
    if (_ch in ["hq", "c2", "command"]) then { _ch = "commandement"; };
    if (_ch isEqualTo "group") then { _ch = "groupe"; };
    private _st = systemTime;
    private _p2 = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
    _ev = [format ["C-%1-%2", getPlayerUID player, floor (serverTime * 10)], [] call comspec_atak_native_fnc_unitCallsign,
        ((_tags apply { format ["[%1]", _x] }) joinString "") + ([" ", ""] select ((count _tags) isEqualTo 0)) + _text,
        format ["%1/%2 %3:%4", [_st select 2] call _p2, [_st select 1] call _p2, [_st select 3] call _p2, [_st select 4] call _p2],
        _ch, str side group player, getPlayerUID player, netId group player];
};
if ([] call comspec_atak_native_fnc_bridge) exitWith {
    // Même chemin que la tablette Overwatch : canal choisi, préfixes, envoi Athena et écho local.
    missionNamespace setVariable ["COMSPEC_Comms_Channel", (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["chatChannel", "general"]];
    private _ok = [] call comspec_overwatch_connect_fnc_canStartSync;
    // Overwatch comprend #CONTACT / #SITREP / #URGENT et la priorité du canal ; les autres types passent en puce [TYPE].
    private _out = switch (_kind) do {
        case "CONTACT": { "#CONTACT " + _text };
        case "SITREP": { "#SITREP " + _text };
        case "": { _text };
        default { format ["[%1] %2", _kind, _text] };
    };
    // Débit simulé : le message part après la latence, ou attend le retour du réseau (affiché « En attente de réseau »
    // dans la conversation, fn_messagesAll « pendingOut », jusqu'au départ de la file).
    private _pid = format ["pend:%1:%2", diag_tickTime, floor random 1e5];
    private _chan = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["chatChannel", "general"];
    private _r = [{
        params ["_out", "_prio", "_pid", "_chan", "_ev"];
        private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
        _data set ["pendingOut", (_data getOrDefault ["pendingOut", []]) select { (_x getOrDefault ["id", ""]) isNotEqualTo _pid }];
        missionNamespace setVariable ["COMSPEC_Comms_Channel", _chan];
        private _prevPrio = missionNamespace getVariable ["COMSPEC_Comms_Priority", "ROUTINE"];
        if (_prio isNotEqualTo "") then { missionNamespace setVariable ["COMSPEC_Comms_Priority", _prio]; };
        [_out] call comspec_overwatch_connect_fnc_tabletChatSend;
        missionNamespace setVariable ["COMSPEC_Comms_Priority", _prevPrio];
        if (_ev isNotEqualTo []) then { ["comspec_atak_native_sideEvent", ["comspec_atak_native_chatLocal", _ev, _ev select 5]] call CBA_fnc_serverEvent; };
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then { [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
    }, [_out, _prio, _pid, _chan, _ev], "Message", 1] call comspec_atak_native_fnc_netSend;
    if (_r isEqualTo "QUEUED") then {
        private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
        private _pend = _data getOrDefault ["pendingOut", []];
        _pend pushBack createHashMapFromArray [["id", _pid], ["author", [] call comspec_atak_native_fnc_unitCallsign], ["body", _out], ["channel", [_chan, "general"] select (_chan isEqualTo "")],
            ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["status", "PENDING"], ["mine", true]];
        while { (count _pend) > 30 } do { _pend deleteAt 0; };
        _data set ["pendingOut", _pend];
    };
    _edit ctrlSetText (("") call comspec_atak_native_fnc_tr);
    uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
    if (!_ok && {!_local}) then { ["WARNING", "Athena non connecté : message affiché ici seulement", 4, 30] call comspec_atak_native_fnc_notify; };
    [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    _ok || _local
};
private _author = [] call comspec_atak_native_fnc_unitCallsign;
_message = ((_tags apply { format ["[%1]", _x] }) joinString "") + ([" ", ""] select ((count _tags) isEqualTo 0)) + _text;
private _id = format ["out:%1", diag_tickTime];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _outbox = +(_data getOrDefault ["outbox", []]);
_outbox pushBack createHashMapFromArray [
    ["id", _id], ["author", _author], ["body", _message],
    ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["status", "PENDING"]
];
while { (count _outbox) > 30 } do { _outbox deleteAt 0; };
["outbox", _outbox] call comspec_atak_native_fnc_storeSet;
// Débit simulé : l'envoi Athena part après la latence, ou attend le retour du réseau.
private _ok = true;
[{
    params ["_id", "_author", "_message", "_ev"];
    private _raw = if (_ev isNotEqualTo []) then {
        ["comspec_atak_native_sideEvent", ["comspec_atak_native_chatLocal", _ev, _ev select 5]] call CBA_fnc_serverEvent;
        "OK|local"
    } else { ["SendChat", [_author, _message]] call comspec_atak_native_fnc_extensionCall };
    // SendChat part en file dans la DLL et répond "" : seul un retour ERR est un échec.
    private _ok = (toUpper (_raw select [0, 3])) isNotEqualTo "ERR";
    private _outbox = +((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["outbox", []]);
    { if ((_x getOrDefault ["id", ""]) isEqualTo _id) then { _x set ["status", ["FAILED", "SENT"] select _ok]; }; } forEach _outbox;
    ["outbox", _outbox] call comspec_atak_native_fnc_storeSet;
    if !(_ok) then { ["WARNING", "Athena indisponible : message gardé sur le terminal", 4, 30] call comspec_atak_native_fnc_notify; };
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "CHAT") then { [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
}, [_id, _author, _message, _ev], "Message", 1] call comspec_atak_native_fnc_netSend;
_edit ctrlSetText (("") call comspec_atak_native_fnc_tr);
uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
[{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
_ok
