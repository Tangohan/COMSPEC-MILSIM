/*
    Reprend les données publiées par COMSPEC Link (ordres web, boîte d'alertes, intel).
    Elles vont dans des stores à part ("legacyTasks", "inbox") pour ne plus écraser
    ce que GetOrders / GetChatMessages ont rempli ; messagesAll et tasksAll fusionnent.
*/
private _tasks = createHashMap;
{
    if (_x isEqualType createHashMap) then {
        private _id = _x getOrDefault ["id", str _forEachIndex];
        if !(_id isEqualType "") then { _id = str _id; };
        _tasks set [_id, _x];
    };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
["legacyTasks", _tasks] call comspec_atak_native_fnc_storeSet;

// COMSPEC_Athena_AlertInbox : [type, titre, texte, grille, heure, émetteur, canal]
private _inbox = [];
{
    if (_x isEqualType createHashMap) then { _inbox pushBack _x; continue };
    if !(_x isEqualType [] && {(count _x) >= 3}) then { continue };
    _x params [["_kind", "NOTIFY"], ["_title", ""], ["_text", ""], ["_grid", ""], ["_time", "--:--"], ["_from", ""], ["_channel", ""]];
    if !(_text isEqualType "") then { _text = str _text; };
    if !(_title isEqualType "") then { _title = str _title; };
    if !(_from isEqualType "") then { _from = ""; };
    private _author = [_from, _title] select (_from isEqualTo "");
    if (_author isEqualTo "") then { _author = "TOC"; };
    _inbox pushBack createHashMapFromArray [
        ["id", format ["inbox:%1:%2:%3", _time, _author, count _text]],
        ["kind", toUpper ([str _kind, _kind] select (_kind isEqualType ""))],
        ["title", _title], ["body", _text], ["grid", _grid],
        ["time", [str _time, _time] select (_time isEqualType "")],
        ["author", _author], ["channel", _channel], ["source", "inbox"]
    ];
} forEach (missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []]);
["inbox", _inbox] call comspec_atak_native_fnc_storeSet;

// COMSPEC_IntelStore est un tableau de HashMaps (publicVariable d'Overwatch).
private _intel = missionNamespace getVariable ["COMSPEC_IntelStore", []];
if (_intel isEqualType [] || {_intel isEqualType createHashMap}) then { ["intel", _intel] call comspec_atak_native_fnc_storeSet; };
true
