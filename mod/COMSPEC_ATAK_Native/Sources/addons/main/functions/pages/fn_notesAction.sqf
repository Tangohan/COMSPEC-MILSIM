/*
    Actions du bloc-notes : save | delete.
*/
params [["_act", "save"]];
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
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
    case "delete": {
        if (_edit isEqualTo "" || {_edit isEqualTo "*NEW*"}) exitWith {};
        _notes = _notes select { (_x select 0) isNotEqualTo _edit };
        profileNamespace setVariable ["COMSPEC_ATAK_Notes", _notes];
        [] call comspec_atak_native_fnc_saveProfile;
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        ["Note supprimée", 2] call comspec_atak_native_fnc_toast;
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
};
true
