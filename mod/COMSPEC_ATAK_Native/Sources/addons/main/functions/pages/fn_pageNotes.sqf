/*
    Bloc-notes : liste des notes locales (profil COMSPEC_ATAK_Notes).
    Format : [[id, titre, corps, updated], …] — max 40.
    Édition : uiNamespace COMSPEC_ATAK_NoteEdit = id ("" = nouvelle).
*/
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_NoteEdit", ""];
private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
if !(_notes isEqualType []) then { _notes = []; };
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];

if (_edit isNotEqualTo "") then {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["NOTES", {
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    }]];
    private _note = [];
    if (_edit isEqualTo "*NEW*") then {
        _note = ["*NEW*", "", "", 0];
    } else {
        { if ((_x select 0) isEqualTo _edit) exitWith { _note = _x; }; } forEach _notes;
        if ((count _note) isEqualTo 0) then { _note = ["*NEW*", "", "", 0]; };
    };
    _note params ["_nid", "_title", "_body"];
    private _rows = [
        ["section", ["Nouvelle note", "Modifier la note"] select (_nid isNotEqualTo "*NEW*"), "Enregistrée sur ce poste uniquement"],
        ["edit", "noteTitle", "Titre", _title],
        ["memo", "noteBody", "Texte", _body, 10],
        ["buttons", [
            ["Enregistrer", { ["save"] call comspec_atak_native_fnc_notesAction; }, true],
            ["Annuler", { uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""]; [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, false]
        ]]
    ];
    if (_nid isNotEqualTo "*NEW*") then {
        _rows pushBack ["buttons", [
            ["Supprimer", { ["delete"] call comspec_atak_native_fnc_notesAction; }, false, true, "Supprimer définitivement cette note"]
        ]];
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
} else {
    // Tri récent d'abord
    _notes = [_notes, [], { -(_x select 3) }, "ASC"] call BIS_fnc_sortBy;
    private _rows = [
        ["hero", "\z\comspec_atak_native\addons\main\data\app_notes.paa", format [
            "<t size='1.35' font='COMSPEC_BarlowBold'>Bloc-notes</t><br/><t color='#8a9a93'>%1 note%2 · enregistrées sur ce poste</t>",
            count _notes,
            ["", "s"] select ((count _notes) isNotEqualTo 1)
        ]],
        ["buttons", [
            ["Nouvelle note", {
                uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", "*NEW*"];
                [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
            }, true]
        ]],
        ["section", "Mes notes", ""]
    ];
    if ((count _notes) isEqualTo 0) then {
        _rows pushBack ["text", "<t color='#8a9a93'>Aucune note pour l'instant. Créez-en une pour garder un rappel, une grille ou un message hors liaison.</t>"];
    } else {
        {
            _x params ["_id", "_title", "_body", "_upd"];
            private _preview = (_body splitString toString [10]) param [0, ""];
            if ((count _preview) > 80) then { _preview = (_preview select [0, 77]) + "…"; };
            private _when = if (_upd > 0) then { [_upd, "HH:MM"] call BIS_fnc_timeToString } else { "—" };
            // dayTime string is wrong for unix-like; use systemTime if available
            private _stamp = "récente";
            if (_upd > 100000) then {
                // diag_tickTime-based stamp stored as time — show relative
                private _ago = diag_tickTime - _upd;
                _stamp = if (_ago < 60) then { "à l'instant" } else {
                    if (_ago < 3600) then { format ["il y a %1 min", round (_ago / 60)] } else {
                        if (_ago < 86400) then { format ["il y a %1 h", round (_ago / 3600)] } else { format ["il y a %1 j", round (_ago / 86400)] }
                    }
                };
            };
            private _ttl = if (_title isEqualTo "") then { "Sans titre" } else { _title };
            _rows pushBack ["person", "\z\comspec_atak_native\addons\main\data\app_notes.paa", format [
                "<t font='COMSPEC_BarlowBold'>%1</t><t size='0.75' color='#8a9a93'>  %2</t><br/><t size='0.85' color='#b4c1bb'>%3</t>",
                _ttl, _stamp, [_preview, "—"] select (_preview isEqualTo "")
            ], [
                ["Ouvrir", compile format [
                    "uiNamespace setVariable ['COMSPEC_ATAK_NoteEdit', '%1']; [{ ['NOTES'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;",
                    _id
                ], true]
            ]];
        } forEach _notes;
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
};
true
