/*
    Bloc-notes : liste des notes locales (profil COMSPEC_ATAK_Notes).
    Format : [[id, titre, corps, updated, reçue de], …] — max 40.
    Vues : liste ; lecture (uiNamespace COMSPEC_ATAK_NoteRead = id : texte entier en lecture seule, défilant, MODIFIER,
    PARTAGER EN BLUETOOTH, ENVOYER EN MESSAGE) ; édition (COMSPEC_ATAK_NoteEdit = id, "*NEW*" = nouvelle).
    Le défilement de la page est gardé d'un rendu à l'autre (fn_pageRender) : la liste ne remonte pas en haut.
*/
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_NoteEdit", ""];
private _read = uiNamespace getVariable ["COMSPEC_ATAK_NoteRead", ""];
private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
if !(_notes isEqualType []) then { _notes = []; };
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];

if (_edit isNotEqualTo "") then {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["NOTES", {
        uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""];
        [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
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
        ["memo", "noteBody", "Texte", _body, 14],
        ["buttons", [
            ["Enregistrer", { ["save"] call comspec_atak_native_fnc_notesAction; }, true],
            ["Annuler", { uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", ""]; [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, false]
        ]]
    ];
    if (_nid isNotEqualTo "*NEW*") then {
        _rows pushBack ["buttons", [
            ["Supprimer", { ["delete"] call comspec_atak_native_fnc_notesAction; }, false, true, "Supprimer définitivement cette note"]
        ]];
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
} else {
    private _rn = (_notes select { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _read} }) param [0, []];
    if (_read isNotEqualTo "" && {(count _rn) >= 3}) exitWith {
        // Lecture : texte entier, police lisible, la page défile.
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["NOTES", {
            uiNamespace setVariable ["COMSPEC_ATAK_NoteRead", ""];
            [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        }]];
        _rn params ["_nid", "_title", "_body", ["_upd", 0], ["_from", ""]];
        private _esc = { ((_this regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"] };
        private _txt = ((_body call _esc) splitString (toString [10])) joinString "<br/>";
        [[
            ["buttons", [
                ["◀ NOTES", { uiNamespace setVariable ["COMSPEC_ATAK_NoteRead", ""]; [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }],
                ["MODIFIER", compile format ["uiNamespace setVariable ['COMSPEC_ATAK_NoteRead', '']; uiNamespace setVariable ['COMSPEC_ATAK_NoteEdit', %1]; [{ ['NOTES'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", str _nid], true]
            ]],
            ["section", [_title, "Sans titre"] select (_title isEqualTo ""), [("Écrite sur ce poste") call comspec_atak_native_fnc_tr, format [("Reçue de %1") call comspec_atak_native_fnc_tr, _from]] select (_from isNotEqualTo "")],
            ["text", format ["<t size='1.1'>%1</t>", [_txt, "—"] select (_txt isEqualTo "")], [0.06, 0.075, 0.07, 1]],
            ["buttons", [
                ["PARTAGER EN BLUETOOTH", { ["bt"] call comspec_atak_native_fnc_notesAction; }, false, true, "Vers un téléphone appairé à portée (app Bluetooth, onglet Données)"],
                ["ENVOYER EN MESSAGE", { ["team"] call comspec_atak_native_fnc_notesAction; }, false, true, "Le texte de la note dans un message, à envoyer à l'équipe ou à un joueur"]
            ]]
        ], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    };
    // Tri récent d'abord
    _notes = [_notes, [], { -(_x select 3) }, "ASC"] call BIS_fnc_sortBy;
    private _rows = [
        ["hero", "\z\comspec_atak_native\addons\main\data\app_notes.paa", format [
            "<t size='1.35' font='COMSPEC_BarlowBold'>Bloc-notes</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1 note%2 · enregistrées sur ce poste</t>",
            count _notes,
            ["", "s"] select ((count _notes) isNotEqualTo 1)
        ]],
        ["buttons", [
            ["Nouvelle note", {
                uiNamespace setVariable ["COMSPEC_ATAK_NoteEdit", "*NEW*"];
                [{ ["NOTES"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            }, true]
        ]],
        ["section", "Mes notes", ""]
    ];
    if ((count _notes) isEqualTo 0) then {
        _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucune note pour l'instant. Créez-en une pour garder un rappel, une grille ou un message hors liaison.</t>"];
    } else {
        {
            _x params ["_id", "_title", "_body", "_upd", ["_from", ""]];
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
                "<t font='COMSPEC_BarlowBold'>%1</t><t size='0.75' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>  %2%4</t><br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%3</t>",
                ((_ttl regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"], _stamp, (([_preview, "—"] select (_preview isEqualTo "")) regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"],
                ["", format [" · %1", format [("reçue de %1") call comspec_atak_native_fnc_tr, _from]]] select (_from isNotEqualTo "")
            ], [
                ["Lire", compile format ["['read', %1] call comspec_atak_native_fnc_notesAction;", str _id], true, true, "Lecture de la note entière"],
                ["Modifier", compile format [
                    "uiNamespace setVariable ['COMSPEC_ATAK_NoteEdit', '%1']; [{ ['NOTES'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;",
                    _id
                ], false]
            ]];
        } forEach _notes;
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
};
true
