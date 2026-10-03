/* Envoi vers le canal TOC (Athena). Le message s'affiche tout de suite, puis est remplacé par l'écho Athena. */
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit", controlNull];
if (isNull _edit) exitWith { false };
private _message = trim ctrlText _edit;
if (_message isEqualTo "") exitWith { false };
private _author = [] call comspec_atak_native_fnc_unitCallsign;
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
