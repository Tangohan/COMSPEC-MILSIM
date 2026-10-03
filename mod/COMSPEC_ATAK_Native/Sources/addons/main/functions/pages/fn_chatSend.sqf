/* Envoi vers le canal TOC (Athena). Le message s'affiche tout de suite, puis est remplacé par l'écho Athena. */
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (isNull _edit) exitWith { false };
private _message = trim ctrlText _edit;
if (_message isEqualTo "") exitWith { false };
// Commandes (/urgent, /contact, /aide…) : voir fn_chatCommand.
([_message] call comspec_atak_native_fnc_chatCommand) params ["_prio", "_kind", "_text", "_unknown", "_tags", "_help"];
if (_help) exitWith {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["chatWiki", true];
    _edit ctrlSetText ""; uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
    [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    false
};
if ((count _unknown) > 0) exitWith { ["WARNING", format ["Commande inconnue : %1 (tapez /aide)", _unknown joinString " "], 4, 30] call comspec_atak_native_fnc_notify; false };
if (_text isEqualTo "") exitWith { ["WARNING", "Message vide après la commande", 3, 20] call comspec_atak_native_fnc_notify; false };
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
    private _prevPrio = missionNamespace getVariable ["COMSPEC_Comms_Priority", "ROUTINE"];
    if (_prio isNotEqualTo "") then { missionNamespace setVariable ["COMSPEC_Comms_Priority", _prio]; };
    [_out] call comspec_overwatch_connect_fnc_tabletChatSend;
    missionNamespace setVariable ["COMSPEC_Comms_Priority", _prevPrio];
    _edit ctrlSetText "";
    uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
    if !(_ok) then { ["WARNING", "Athena non connecté : message affiché ici seulement", 4, 30] call comspec_atak_native_fnc_notify; };
    [{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    _ok
};
private _author = [] call comspec_atak_native_fnc_unitCallsign;
_message = ((_tags apply { format ["[%1]", _x] }) joinString "") + ([" ", ""] select ((count _tags) isEqualTo 0)) + _text;
private _raw = ["SendChat", [_author, _message]] call comspec_atak_native_fnc_extensionCall;
private _ok = (_raw find "OK|") isEqualTo 0;
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _outbox = +(_data getOrDefault ["outbox", []]);
_outbox pushBack createHashMapFromArray [
    ["id", format ["out:%1", diag_tickTime]], ["author", _author], ["body", _message],
    ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["status", ["FAILED", "SENT"] select _ok]
];
while { (count _outbox) > 30 } do { _outbox deleteAt 0; };
["outbox", _outbox] call comspec_atak_native_fnc_storeSet;
_edit ctrlSetText "";
uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ""];
if !(_ok) then { ["WARNING", "Athena indisponible : message gardé sur le terminal", 4, 30] call comspec_atak_native_fnc_notify; };
[{ ["CHAT"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
_ok
