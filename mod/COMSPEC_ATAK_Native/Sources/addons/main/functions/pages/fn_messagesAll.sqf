/*
    Fil TOC unifié : messages Athena (GetChatMessages), alertes de la boîte Overwatch
    et messages envoyés depuis ce terminal pas encore revenus d'Athena.
    Renvoie des HashMaps normalisées : id, time, author, body, tags, kind, mine, status.
*/
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _me = [] call comspec_atak_native_fnc_unitCallsign;
private _names = [toLower _me, toLower name player];
private _out = [];
private _seen = createHashMap;
private _add = {
    params ["_m", "_kind"];
    ([_m getOrDefault ["body", ""], _m getOrDefault ["time", "--:--"]] call comspec_atak_native_fnc_chatParse) params ["_time", "_tags", "_text", "_system"];
    if (_system || {_text isEqualTo ""}) exitWith {};
    private _author = _m getOrDefault ["author", "TOC"];
    if !(_author isEqualType "") then { _author = str _author; };
    private _key = toLower (_author + "|" + _text);
    if (_seen getOrDefault [_key, false]) exitWith {};
    _seen set [_key, true];
    private _title = _m getOrDefault ["title", ""];
    if (_kind isNotEqualTo "CHAT" && {_title isNotEqualTo ""} && {!((toUpper _title) in _tags)}) then { _tags pushBack (toUpper _title); };
    _out pushBack createHashMapFromArray [
        ["id", _m getOrDefault ["id", ""]], ["time", _time], ["author", _author], ["body", _text],
        ["tags", _tags], ["kind", _kind], ["mine", (toLower _author) in _names],
        ["status", _m getOrDefault ["status", "RECEIVED"]], ["grid", _m getOrDefault ["grid", ""]]
    ];
};
{ [_x, "CHAT"] call _add; } forEach (_data getOrDefault ["messages", []]);
{ [_x, _x getOrDefault ["kind", "NOTIFY"]] call _add; } forEach (_data getOrDefault ["inbox", []]);
// Envois locaux : affichés tant qu'Athena ne les a pas renvoyés (dédoublonnés plus haut).
{ [_x, "CHAT"] call _add; } forEach (_data getOrDefault ["outbox", []]);
private _keyed = [];
{ _keyed pushBack [_x get "time", _forEachIndex, _x]; } forEach _out;
_keyed sort true;
_keyed apply { _x select 2 }
