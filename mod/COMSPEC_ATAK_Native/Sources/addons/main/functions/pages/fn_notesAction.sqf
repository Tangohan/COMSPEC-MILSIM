/*
    Actions du bloc-notes : save | delete | receive [titre, texte, expéditeur] (note reçue en Bluetooth, fn_btItems) ->
    résumé pour la notification | read id (vue lecture) | team (texte de la note lue dans un message à l'équipe).
    Note : [id, titre, corps, mise à jour (diag_tickTime), reçue de (indicatif, "" : écrite ici)].
*/
params [["_act", "save"], ["_arg", []]];
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_NoteEdit", ""];
private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
if !(_notes isEqualType []) then { _notes = []; };

switch (_act) do {
    case "save": {
        private _title = trim (["noteTitle"] call comspec_atak_native_fnc_formValue);
        private _body = ["noteBody"] call comspec_atak_native_fnc_formValue;
        if !(_body isEqualType "") then { _body = str _body; };
        if ((count _body) > 4000) then { _body = _body select [0, 4000]; };
        if (_title isEqualTo "" && {_body isEqualTo ""}) exitWith {
            ["Écrivez un titre ou un texte", 2] call comspec_atak_native_fnc_toast;
        };
        if (_edit isEqualTo "*NEW*" || {_edit isEqualTo ""}) then {
            if ((count _notes) >= 40) exitWith { ["40 notes au plus", 2] call comspec_atak_native_fnc_toast; };
            private _id = format ["n%1", round (diag_tickTime * 1000) + (floor random 999)];
            _notes pushBack [_id, _title select [0, 80], _body, diag_tickTime];
        } else {
            private _found = false;
            {
                if ((_x select 0) isEqualTo _edit) exitWith {
                    _x set [1, _title select [0, 80]];
                    _x set [2, _body];
                    _x set [3, diag_tickTime];
                    _found = true;
                };
            } forEach _notes;
            if (!_found) then {
                _notes pushBack [_edit, _title select [0, 80], _body, diag_tickTime];
            };
        };
        profileNamespace setVariable ["COMSPEC_ATAK_Notes", _notes];
        [] call comspec_atak_native_fnc_saveProfile;
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        ["Note enregistrée", 2] call comspec_atak_native_fnc_toast;
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "receive": {
        _arg params [["_t", ""], ["_b", ""], ["_from", ""]];
        if ((count _notes) >= 40) exitWith { "" };
        private _id = format ["n%1", round (diag_tickTime * 1000) + (floor random 999)];
        _t = ((_t regexReplace ["[<>]", ""]) select [0, 80]);
        _notes pushBack [_id, _t, (_b regexReplace ["[<>]", ""]) select [0, 4000], diag_tickTime, (_from regexReplace ["[<>]", ""]) select [0, 24]];
        profileNamespace setVariable ["COMSPEC_ATAK_Notes", _notes];
        [] call comspec_atak_native_fnc_saveProfile;
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "NOTES") then { [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
        format [("note « %1 » ajoutée au Bloc-notes") call comspec_atak_native_fnc_tr, [_t, ("Sans titre") call comspec_atak_native_fnc_tr] select (_t isEqualTo "")]
    };
    case "read": {
        uiNamespace setVariable ["COMSPEC_ATAK_NoteRead", _arg];
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "team": {
        private _n = (_notes select { (_x select 0) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_NoteRead", ""]) }) param [0, []];
        if ((count _n) < 3) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", ((format ["%1 : %2", _n select 1, _n select 2]) regexReplace ["[
]+", " "]) select [0, 400]];
        ["Note copiée dans un message : choisissez le destinataire et envoyez", 3] call comspec_atak_native_fnc_toast;
        ["CHAT"] call comspec_atak_native_fnc_navigate;
    };
    case "bt": {
        // Partage en Bluetooth : app Bluetooth, onglet des données, la note cherchée par son titre.
        private _n = (_notes select { (_x select 0) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_NoteRead", ""]) }) param [0, []];
        private _bu = uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap];
        _bu set ["tab", "data"]; _bu set ["page", 0]; _bu set ["q", (_n param [1, ""]) select [0, 40]];
        uiNamespace setVariable ["COMSPEC_ATAK_BtUi", _bu];
        ["Choisissez l'appareil appairé, puis ENVOYER sur la note", 3] call comspec_atak_native_fnc_toast;
        ["BLUETOOTH"] call comspec_atak_native_fnc_navigate;
    };
    case "delete": {
        if (_edit isEqualTo "" || {_edit isEqualTo "*NEW*"}) exitWith {};
        _notes = _notes select { (_x select 0) isNotEqualTo _edit };
        profileNamespace setVariable ["COMSPEC_ATAK_Notes", _notes];
        [] call comspec_atak_native_fnc_saveProfile;
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        ["Note supprimée", 2] call comspec_atak_native_fnc_toast;
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
    };
};
true
