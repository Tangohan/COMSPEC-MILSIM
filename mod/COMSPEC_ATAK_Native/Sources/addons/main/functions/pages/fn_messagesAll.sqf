/*
    Fil TOC unifié. Avec COMSPEC Link : son fil par canal (general, commandement, groupe),
    ou ses alertes pour le canal "alertes". Sans : GetChatMessages, la boîte d'alertes et les envois locaux.
    Params : [canal ("" = tous)]
    Renvoie des HashMaps normalisées : id, time, author, body, tags, kind, mine, status, stamp (date et heure d'origine).
    Résultat gardé par canal tant que les sources n'ont pas changé (au plus 5 s) : la messagerie, ses pastilles et la
    barre d'état le demandent plusieurs fois par seconde, chaque canal à part ; tout relire à chaque fois la faisait
    ramer avec beaucoup de canaux. Le tableau rendu est partagé : ne pas le modifier.
*/
params [["_channel", ""]];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _lastOf = { params ["_a"]; if (_a isEqualType [] && {_a isNotEqualTo []}) then { _a select -1 } else { 0 } };
// Hors connexion (fn_offline) : le fil Athena reste celui de la perte du signal ; mes messages en file s'y affichent.
private _comms = ["var", ["COMSPEC_Comms_Messages", []]] call comspec_atak_native_fnc_offline;
if !(_comms isEqualType []) then { _comms = []; };
private _srcs = if (_bridge) then { [_comms, _data getOrDefault ["inbox", []], _data getOrDefault ["pendingOut", []]] } else {
    [_data getOrDefault ["messages", []], _data getOrDefault ["inbox", []], _data getOrDefault ["outbox", []]]
};
private _sig = [_bridge, name player] + (_srcs apply { [count _x, [_x] call _lastOf] });
private _memo = uiNamespace getVariable ["COMSPEC_ATAK_MsgMemo", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_MsgMemo", _memo];
private _hit = _memo getOrDefault [_channel, []];
if ((count _hit) isEqualTo 3 && {(_hit select 0) isEqualTo _sig} && {diag_tickTime - (_hit select 1) < 5}) exitWith { _hit select 2 };
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
        ["tags", _tags], ["kind", _kind], ["mine", (_m getOrDefault ["mine", false]) || {(toLower _author) in _names}],
        ["status", _m getOrDefault ["status", "RECEIVED"]], ["grid", _m getOrDefault ["grid", ""]], ["stamp", _m getOrDefault ["stamp", ""]]
    ];
};
if ([] call comspec_atak_native_fnc_bridge) then {
    // Fil COMSPEC Link : [id, auteur, texte, heure, canal, moi]
    if (_channel isEqualTo "alertes") then {
        { [_x, _x getOrDefault ["kind", "NOTIFY"]] call _add; } forEach (_data getOrDefault ["inbox", []]);
    } else {
        {
            _x params [["_id", ""], ["_author", ""], ["_text", ""], ["_time", ""], ["_ch", "general"], ["_mine", false]];
            if (_channel isEqualTo "" || {_ch isEqualTo _channel}) then {
                [createHashMapFromArray [["id", _id], ["author", [_author, "Moi"] select (_mine && {_author isEqualTo ""})], ["body", _text], ["time", (_time splitString " ") param [1, _time]], ["stamp", _time], ["mine", _mine]], "CHAT"] call _add;
            };
        } forEach _comms;
        // Envoyés sans signal (fn_chatSend) : « En attente de réseau » jusqu'au départ de la file.
        {
            if (_channel isEqualTo "" || {(_x getOrDefault ["channel", "general"]) isEqualTo _channel}) then { [_x, "CHAT"] call _add; };
        } forEach (_data getOrDefault ["pendingOut", []]);
    };
} else {
    { [_x, "CHAT"] call _add; } forEach (_data getOrDefault ["messages", []]);
    { [_x, _x getOrDefault ["kind", "NOTIFY"]] call _add; } forEach (_data getOrDefault ["inbox", []]);
    // Envois locaux : affichés tant qu'Athena ne les a pas renvoyés (dédoublonnés plus haut).
    { [_x, "CHAT"] call _add; } forEach (_data getOrDefault ["outbox", []]);
};
private _keyed = [];
{ _keyed pushBack [_x get "time", _forEachIndex, _x]; } forEach _out;
_keyed sort true;
private _res = _keyed apply { _x select 2 };
_memo set [_channel, [_sig, diag_tickTime, _res]];
_res
