/*
    App FRS / FRM : fiche de renseignement simplifiée, mêmes champs que le rédacteur d'Overwatch
    (type FRM/FRO/FRC/FRA/FRT, urgence, recueil, date, lieu, carroyage, texte, 1 à 4 thèmes, dossier).
    Envoyée au bureau SSE d'Athena (/api/sse/notes) par la DLL d'Overwatch ; mise en file si la liaison coupe.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["title", "Fiches de renseignement"], ["text", "<t color='#8a9a93'>Les fiches partent vers Athena par COMSPEC Overwatch, qui n'est pas chargé sur ce serveur.</t>"]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _cat = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
private _tab = uiNamespace getVariable ["COMSPEC_ATAK_FrsTab", "write"];
private _tabBtn = {
    params ["_label", "_key"];
    [_label, compile format ["[] call comspec_atak_native_fnc_frsDraftSave; uiNamespace setVariable ['COMSPEC_ATAK_FrsTab', '%1']; ['FRS'] call comspec_atak_native_fnc_pageRender;", _key], _tab isEqualTo _key]
};
private _sent = uiNamespace getVariable ["COMSPEC_ATAK_FrsSent", []];
private _rows = [["buttons", [["RÉDIGER", "write"] call _tabBtn, [format ["ENVOYÉES (%1)", count _sent], "sent"] call _tabBtn]]];
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_FrsHint", ["", false]];
if ((_hint select 0) isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), _hint select 0]]; };

if (_tab isEqualTo "sent") then {
    if ((count _sent) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune fiche envoyée pendant cette session.</t>"]; };
    {
        _x params ["_time", "_kind", "_ref", "_status", "_excerpt"];
        _rows pushBack ["text", format ["<t color='#5cc76b' font='RobotoCondensedBold'>%1</t>  %2  <t color='#8a9a93'>%3 · %4</t><br/>%5", _kind, _ref, _time, _status, _excerpt]];
    } forEach (+_sent call { reverse _this; _this });
} else {
    private _now = date;
    private _pad = { params ["_n"]; if (_n < 10) then { format ["0%1", _n] } else { str _n } };
    private _dateNow = format ["%1/%2/%3 %4:%5", [_now select 2] call _pad, [_now select 1] call _pad, _now select 0, [_now select 3] call _pad, [_now select 4] call _pad];
    private _kinds = (_cat get "kinds") apply { [format ["%1 · %2", _x select 0, _x select 1], _x select 0] };
    private _urg = (_cat get "urgencies") apply { [_x select 1, _x select 0] };
    private _src = [["Non précisé", ""]] + ((_cat get "sources") apply { [format ["%1 · %2", _x select 0, _x select 1], _x select 0] });
    private _themes = _draft getOrDefault ["themes", []];
    _rows append [
        ["combo", "kind", "Type de fiche", _kinds, _draft getOrDefault ["kind", "FRM"]],
        ["combo", "urgency", "Urgence", _urg, _draft getOrDefault ["urgency", "routine"]],
        ["combo", "source", "Recueil", _src, _draft getOrDefault ["source", ""]],
        ["edit", "date", "Date et heure de l'événement (JJ/MM/AAAA HH:MM)", _draft getOrDefault ["date", _dateNow]],
        ["edit", "place", "Lieu", _draft getOrDefault ["place", ""]],
        ["edit", "grid", "Repère (carroyage)", _draft getOrDefault ["grid", [player, 8] call comspec_atak_native_fnc_gridRef]],
        ["memo", "body", format ["Renseignement (%1 caractères max)", _cat getOrDefault ["body_max", 1000]], _draft getOrDefault ["body", ""], 6],
        ["title", format ["Thèmes : %1 / %2", count _themes, _cat getOrDefault ["themes_max", 4]]]
    ];
    // Thèmes en bascules, 3 par ligne ; un clic garde la saisie et redessine.
    private _row = [];
    {
        _x params ["_code", "_label"];
        private _on = _code in _themes;
        _row pushBack [[_label, format ["● %1", _label]] select _on, compile format ["['%1'] call comspec_atak_native_fnc_frsToggleTheme;", _code], _on];
        if ((count _row) isEqualTo 3) then { _rows pushBack ["buttons", _row]; _row = []; };
    } forEach (_cat get "themes");
    if ((count _row) > 0) then { _rows pushBack ["buttons", _row]; };
    _rows append [
        ["edit", "case", "Rattacher à un dossier (facultatif)", _draft getOrDefault ["case", ""]],
        ["buttons", [
            ["ENVOYER LA FICHE", { [{ [] call comspec_atak_native_fnc_frsSubmit; }] call CBA_fnc_execNextFrame; }, true],
            ["EFFACER", { uiNamespace setVariable ["COMSPEC_ATAK_FrsDraft", createHashMap]; uiNamespace setVariable ["COMSPEC_ATAK_FrsHint", ["", false]]; uiNamespace setVariable ["COMSPEC_ATAK_FrsClearing", true]; [{ ["FRS"] call comspec_atak_native_fnc_pageRender; uiNamespace setVariable ["COMSPEC_ATAK_FrsClearing", false]; }] call CBA_fnc_execNextFrame; }]
        ]]
    ];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
