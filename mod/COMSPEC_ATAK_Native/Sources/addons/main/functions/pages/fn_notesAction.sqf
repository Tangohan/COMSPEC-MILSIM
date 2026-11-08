/*
    Actions du bloc-notes (fn_pageNotes).
    Note : [id, titre, corps, date, reçue de (indicatif, "" : écrite ici), épinglée]. Date : minutes depuis 2020 selon
    l'horloge du PC (les notes d'avant la 3.1.0 gardent un nombre sans signification, affiché vide).
      "new" | "open" id | "back"        ouvre une note ; revenir à la liste enregistre
      "autosave"                        enregistre ce qui est saisi, sans redessiner (fn_pageRender avant d'effacer la page)
      "ins" grid | time                 ajoute ma grille ou l'heure à la fin du texte, sans redessiner
      "pin" | "delete" | "undo"         épingler ; supprimer ; rendre la note supprimée
      "team" | "bt"                     envoyer en message ; partager en Bluetooth
      "search"                          recherche de la liste (champ noteQ)
      "when" date -> texte              date à afficher
      "receive" [titre, texte, de]      note reçue en Bluetooth (fn_btItems) -> résumé pour la notification
*/
params [["_act", ""], ["_arg", ""]];
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_NoteEdit", ""];
private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
if !(_notes isEqualType []) then { _notes = []; };
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "NOTES") then { ["NOTES"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _stamp = {
    systemTime params ["_y", "_mo", "_d", "_h", "_mi"];
    (((_y - 2020) * 372 + (_mo - 1) * 31 + (_d - 1)) * 1440) + _h * 60 + _mi
};
private _store = {
    params ["_list"];
    profileNamespace setVariable ["COMSPEC_ATAK_Notes", _list];
    [] call comspec_atak_native_fnc_saveProfile;
};
private _newId = { format ["n%1%2", round (diag_tickTime * 100), floor random 9999] };
// Enregistre les champs affichés ; rien à faire s'ils sont fermés, vides sur une nouvelle note ou inchangés. Rend l'id.
private _saveForm = {
    params ["_id", "_list"];
    private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    if (isNull (_form getOrDefault ["noteBody", controlNull])) exitWith { _id };
    private _title = (trim (["noteTitle", ""] call comspec_atak_native_fnc_formValue)) select [0, 80];
    private _body = ["noteBody", ""] call comspec_atak_native_fnc_formValue;
    if !(_body isEqualType "") then { _body = str _body; };
    _body = _body select [0, 4000];
    private _i = _list findIf { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _id} };
    if (_i < 0) exitWith {
        if (_title isEqualTo "" && {(trim _body) isEqualTo ""}) exitWith { "*NEW*" };
        if ((count _list) >= 60) exitWith { ["60 notes au plus : texte non enregistré", 3] call comspec_atak_native_fnc_toast; "*NEW*" };
        private _nid = call _newId;
        _list pushBack [_nid, _title, _body, call _stamp, "", false];
        [_list] call _store;
        _nid
    };
    private _n = _list select _i;
    if ((_n select 1) isEqualTo _title && {(_n select 2) isEqualTo _body}) exitWith { _id };
    _n set [1, _title];
    _n set [2, _body];
    _n set [3, call _stamp];
    [_list] call _store;
    _id
};
// La note ouverte, enregistrée d'abord ; [] si elle est encore vide.
private _current = {
    private _id = [_edit, _notes] call _saveForm;
    uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", _id];
    (_notes select { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _id} }) param [0, []]
};
switch (_act) do {
    case "new": { uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", "*NEW*"]; call _render; true };
    case "open": { uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", _arg]; call _render; true };
    case "back": {
        [_edit, _notes] call _saveForm;
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        call _render;
        true
    };
    case "autosave": {
        if (_edit isNotEqualTo "") then { uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", [_edit, _notes] call _saveForm]; };
        true
    };
    case "ins": {
        private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["noteBody", controlNull];
        if (!isNull _c) then {
            private _t = if (_arg isEqualTo "grid") then { [[] call comspec_atak_native_fnc_gpsPos, 8] call comspec_atak_native_fnc_gridRef } else { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
            private _old = ctrlText _c;
            _c ctrlSetText ([_old + " " + _t, _t] select (_old isEqualTo ""));
        };
        true
    };
    case "pin": {
        private _n = call _current;
        if ((count _n) >= 3) then {
            _n resize 6;
            if (isNil {_n select 3}) then { _n set [3, 0]; };
            if (isNil {_n select 4}) then { _n set [4, ""]; };
            if (isNil {_n select 5}) then { _n set [5, false]; };
            _n set [5, !(_n select 5)];
            [_notes] call _store;
            call _render;
        };
        true
    };
    case "delete": {
        if !(_edit in ["", "*NEW*"]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_NoteUndo", (_notes select { (_x select 0) isEqualTo _edit }) param [0, []]];
            [_notes select { (_x select 0) isNotEqualTo _edit }] call _store;
            uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
            call _render;
            ["Note supprimée", { ["undo"] call comspec_atak_native_fnc_notesAction; }] call comspec_atak_native_fnc_undoSnack;
        };
        true
    };
    case "undo": {
        private _n = uiNamespace getVariable ["COMSPEC_ATAK_NoteUndo", []];
        if ((count _n) >= 3) then {
            _notes pushBack _n;
            [_notes] call _store;
            uiNamespace setVariable ["COMSPEC_ATAK_NoteUndo", []];
            call _render;
        };
        true
    };
    case "team": {
        private _n = call _current;
        if ((count _n) < 3) then { ["Écrivez d'abord la note", 2] call comspec_atak_native_fnc_toast; } else {
            uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ((format ["%1 : %2", _n select 1, _n select 2]) regexReplace ["[\r\n]+", " "]) select [0, 400]];
            ["Note copiée dans un message : choisissez le destinataire et envoyez", 3] call comspec_atak_native_fnc_toast;
            ["CHAT"] call comspec_atak_native_fnc_navigate;
        };
        true
    };
    case "bt": {
        // App Bluetooth, onglet des données, la note cherchée par son titre.
        private _n = call _current;
        if ((count _n) < 3) then { ["Écrivez d'abord la note", 2] call comspec_atak_native_fnc_toast; } else {
            private _bu = uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap];
            _bu set ["tab", "data"]; _bu set ["page", 0]; _bu set ["q", (_n select 1) select [0, 40]];
            uiNamespace setVariable ["COMSPEC_ATAK_BtUi", _bu];
            ["Choisissez l'appareil appairé, puis ENVOYER sur la note", 3] call comspec_atak_native_fnc_toast;
            ["BLUETOOTH"] call comspec_atak_native_fnc_navigate;
        };
        true
    };
    case "search": {
        uiNamespace setVariable ["COMSPEC_ATAK_NoteQ", trim (["noteQ", ""] call comspec_atak_native_fnc_formValue)];
        call _render;
        true
    };
    case "when": {
        if (_arg isEqualType 0 && {_arg >= 1000000}) then {
            private _dd = floor (_arg / 1440);
            private _min = _arg - _dd * 1440;
            private _two = { params ["_v"]; [str _v, "0" + str _v] select (_v < 10) };
            format ["%1/%2 %3:%4", [(_dd mod 31) + 1] call _two, [((floor (_dd / 31)) mod 12) + 1] call _two, [floor (_min / 60)] call _two, [_min mod 60] call _two]
        } else { "" }
    };
    case "receive": {
        _arg params [["_t", ""], ["_b", ""], ["_from", ""]];
        if ((count _notes) >= 60) then { "" } else {
            _t = (_t regexReplace ["[<>]", ""]) select [0, 80];
            _notes pushBack [call _newId, _t, (_b regexReplace ["[<>]", ""]) select [0, 4000], call _stamp, (_from regexReplace ["[<>]", ""]) select [0, 24], false];
            [_notes] call _store;
            if (_edit isEqualTo "") then { call _render; };
            format [("note « %1 » ajoutée au Bloc-notes") call comspec_atak_native_fnc_tr, [_t, ("Sans titre") call comspec_atak_native_fnc_tr] select (_t isEqualTo "")]
        }
    };
    default { true };
}
