/*
    Bloc-notes (3.1.0, refait) : trois écrans.
      Liste : épinglées d'abord, puis les plus récentes ; sur chaque note OUVRIR, ÉDITER, PARTAGER (MESSAGE, BLUETOOTH sur
      place) et SUPPRIMER (CONFIRMER sur place, 5 s, puis ANNULER 8 s) ; recherche à partir de 7 notes.
      Lecture : le texte seul (COMSPEC_ATAK_NoteRead = id), mêmes actions.
      Note : titre facultatif et texte, modifiables tout de suite (plus d'écran de lecture à part). Enregistrement
      automatique en quittant (retour, autre app, redessin : fn_pageRender) ; insertion de ma grille et de l'heure ;
      épingler, envoyer en message, partager en Bluetooth, supprimer (avec ANNULER).
    Format et actions : fn_notesAction. uiNamespace COMSPEC_ATAK_NoteEdit = id de la note ouverte ("*NEW*" : nouvelle).
*/
disableSerialization;
private _edit = uiNamespace getVariable ["COMSPEC_ATAK_NoteEdit", ""];
private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
if !(_notes isEqualType []) then { _notes = []; };
_notes = _notes select { _x isEqualType [] && {(count _x) >= 3} };
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _icon = "\z\comspec_atak_native\addons\main\data\app_notes.paa";

if (_edit isNotEqualTo "") exitWith {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["NOTES", { ["back"] call comspec_atak_native_fnc_notesAction; }]];
    private _note = (_notes select { (_x select 0) isEqualTo _edit }) param [0, ["*NEW*", "", "", 0]];
    _note params ["_nid", "_title", "_body", "", ["_from", ""], ["_pin", false]];
    private _isNew = _nid isEqualTo "*NEW*";
    // Le texte occupe ce qui reste de l'écran une fois les boutons placés.
    private _font = _l get "font";
    private _lines = (round ((_bh - _font * 13) / (_font * 1.15))) max 5;
    private _rows = [
        ["buttons", [
            ["◀ NOTES", { ["back"] call comspec_atak_native_fnc_notesAction; }],
            [["ÉPINGLER", "ÉPINGLÉE"] select _pin, { ["pin"] call comspec_atak_native_fnc_notesAction; }, _pin, !_isNew, "Garde cette note en tête de liste"]
        ]],
        ["edit", "noteTitle", "Titre (facultatif)", _title],
        ["memo", "noteBody", ["Texte · enregistré en quittant", format [("Reçue de %1 · enregistrée en quittant") call comspec_atak_native_fnc_tr, _from]] select (_from isNotEqualTo ""), _body, _lines],
        ["buttons", [
            ["+ MA GRILLE", { ["ins", "grid"] call comspec_atak_native_fnc_notesAction; }, false, true, "Ajoute ma position au texte"],
            ["+ HEURE", { ["ins", "time"] call comspec_atak_native_fnc_notesAction; }, false, true, "Ajoute l'heure du jeu au texte"]
        ]],
        ["buttons", [
            ["MESSAGE", { ["team"] call comspec_atak_native_fnc_notesAction; }, false, true, "Le texte de la note dans un message, à envoyer à l'équipe ou à un joueur"],
            ["BLUETOOTH", { ["bt"] call comspec_atak_native_fnc_notesAction; }, false, true, "Vers un téléphone appairé à portée (app Bluetooth, onglet Données)"],
            ["SUPPRIMER", { ["delete"] call comspec_atak_native_fnc_notesAction; }, "red", !_isNew, "Supprime cette note ; ANNULER la rend pendant 8 s"]
        ]]
    ];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// Lecture seule : titre, date, origine, texte avec ses retours à la ligne.
private _read = uiNamespace getVariable ["COMSPEC_ATAK_NoteRead", ""];
private _rn = (_notes select { (_x select 0) isEqualTo _read }) param [0, []];
private _esc = { params ["_v"]; ((_v regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"] };
if (_read isNotEqualTo "" && {(count _rn) >= 3}) exitWith {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["NOTES", { ["back"] call comspec_atak_native_fnc_notesAction; }]];
    _rn params ["_nid", "_title", "_body", ["_upd", 0], ["_from", ""]];
    private _ids = str _nid;
    private _when = ["when", _upd] call comspec_atak_native_fnc_notesAction;
    private _sub = ([_when, [format [("reçue de %1") call comspec_atak_native_fnc_tr, _from], ""] select (_from isEqualTo "")] select { _x isNotEqualTo "" }) joinString " · ";
    private _rows = [
        ["buttons", [
            ["◀ NOTES", { ["back"] call comspec_atak_native_fnc_notesAction; }],
            ["ÉDITER", compile format ["['open', %1] call comspec_atak_native_fnc_notesAction;", _ids], true]
        ]],
        ["section", [_title, ("Sans titre") call comspec_atak_native_fnc_tr] select (_title isEqualTo ""), _sub],
        ["text", ((([_body] call _esc) splitString toString [13, 10]) joinString "<br/>"), COMSPEC_ATAK_Th get "surface"],
        ["buttons", [
            ["MESSAGE", compile format ["['team', %1] call comspec_atak_native_fnc_notesAction;", _ids], false, true, "Le texte de la note dans un message, à envoyer à l'équipe ou à un joueur"],
            ["BLUETOOTH", compile format ["['bt', %1] call comspec_atak_native_fnc_notesAction;", _ids], false, true, "Vers un téléphone appairé à portée (app Bluetooth, onglet Données)"],
            ["SUPPRIMER", compile format ["params ['_c']; ['ask', [%1, _c]] call comspec_atak_native_fnc_notesAction;", _ids], "danger", true, "Demande confirmation ; ANNULER rend la note pendant 8 s"]
        ]]
    ];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
uiNamespace setVariable ["COMSPEC_ATAK_NoteRead", ""];

private _q = uiNamespace getVariable ["COMSPEC_ATAK_NoteQ", ""];
private _qLow = toLower _q;
private _shown = _notes select { _q isEqualTo "" || {((toLower ((_x select 1) + " " + (_x select 2))) find _qLow) >= 0} };
// Épinglées d'abord, puis les plus récentes.
private _order = [];
{ _order pushBack [[1, 0] select (_x param [5, false]), -(_x param [3, 0]), _forEachIndex]; } forEach _shown;
_order sort true;
private _rows = [["buttons", [["NOUVELLE NOTE", { ["new"] call comspec_atak_native_fnc_notesAction; }, true]]]];
if ((count _notes) > 6 || {_q isNotEqualTo ""}) then {
    _rows pushBack ["field", "noteQ", "Chercher dans mes notes", _q, "OK", { ["search"] call comspec_atak_native_fnc_notesAction; }];
};
if ((count _notes) isEqualTo 0) then {
    _rows pushBack ["empty", _icon, "Aucune note", "Une grille, un rappel, un message à garder hors liaison : tout reste sur ce poste."];
};
if ((count _notes) > 0 && {(count _shown) isEqualTo 0}) then {
    _rows pushBack ["empty", _icon, "Aucune note trouvée", ""];
};
private _accent = COMSPEC_ATAK_Th get "accent";
{
    (_shown select (_x select 2)) params ["_id", "_title", "_body", ["_upd", 0], ["_from", ""], ["_pin", false]];
    private _first = trim ((_body splitString toString [13, 10]) param [0, ""]);
    private _head = [_title, _first] select (_title isEqualTo "");
    if (_head isEqualTo "") then { _head = ("Sans titre") call comspec_atak_native_fnc_tr; };
    private _sub = [_first, ""] select (_title isEqualTo "");
    if ((count _sub) > 70) then { _sub = (_sub select [0, 67]) + "…"; };
    if (_from isNotEqualTo "") then { _sub = format ["%1 · %2", format [("reçue de %1") call comspec_atak_native_fnc_tr, _from], _sub]; };
    // Ligne inerte (ni chevron ni info-bulle : celle de « row » flottait sous le curseur) ; les actions sont des boutons.
    _rows pushBack ["row", _icon, _head select [0, 60], [_sub] call _esc, ["when", _upd] call comspec_atak_native_fnc_notesAction, {}, [], "", [[], _accent] select _pin];
    private _ids = str _id;
    _rows pushBack ["buttons", [
        ["OUVRIR", compile format ["['read', %1] call comspec_atak_native_fnc_notesAction;", _ids], false, true, "Lire la note"],
        ["ÉDITER", compile format ["['open', %1] call comspec_atak_native_fnc_notesAction;", _ids], false, true, "Modifier la note"],
        ["PARTAGER", compile format ["['share', [%1, true]] call comspec_atak_native_fnc_notesAction;", _ids], false, true, "En message ou en Bluetooth vers un téléphone appairé"],
        ["SUPPRIMER", compile format ["params ['_c']; ['ask', [%1, _c]] call comspec_atak_native_fnc_notesAction;", _ids], "danger", true, "Demande confirmation ; ANNULER rend la note pendant 8 s"]
    ], "noteBtns_" + _id];
} forEach _order;
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
