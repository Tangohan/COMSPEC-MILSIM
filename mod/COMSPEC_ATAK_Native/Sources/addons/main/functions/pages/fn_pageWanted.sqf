/*
    App Avis de recherche : ce qu'Athena demande de rechercher sur le terrain.
    - personnes prioritaires du registre SSE, avec leur photo ;
    - liste de surveillance (nom, alias, niveau de menace) ;
    - dossiers d'intérêt prioritaires ou critiques (désignation, éléments à recueillir).
    Filtre par source, fiche détaillée avec la photo. Données : comspec_atak_native_fnc_wantedAction.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["title", "Avis de recherche"], ["text", "<t color='#8a9a93'>Les avis viennent d'Athena par COMSPEC Overwatch, qui n'est pas chargé sur ce serveur.</t>"]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Wanted", []];
if ((count _data) isEqualTo 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_Wanted", [[], "", ""]];
    [{ ["load"] call comspec_atak_native_fnc_wantedAction; }] call CBA_fnc_execNextFrame;
    _data = [[], "", ""];
};
_data params ["_list", "_at", "_err"];
private _imgs = uiNamespace getVariable ["COMSPEC_ATAK_WantedImg", createHashMap];
private _filter = _s getOrDefault ["wantedFilter", "ALL"];
private _open = _s getOrDefault ["wantedOpen", ""];
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _kindIcon = createHashMapFromArray [["person", "app_wanted.paa"], ["watchlist", "app_intel.paa"], ["interest", "app_sse.paa"]];
private _kindColor = createHashMapFromArray [["person", "#e05050"], ["watchlist", "#f2ab33"], ["interest", "#7d6ff0"]];
private _rows = [];

private _sel = _list param [(_list findIf { format ["%1:%2", _x select 0, _x select 1] isEqualTo _open }), []];
if (_open isNotEqualTo "" && {(count _sel) > 0}) then {
    _sel params ["_kind", "_id", "_ref", "_name", "_alias", "_level", "_details", ["_url", ""], ["_upd", ""]];
    private _img = _imgs getOrDefault [_open, ""];
    _rows append [
        ["title", "AVIS DE RECHERCHE"],
        ["text", format ["<t size='1.3' font='RobotoCondensedBold'>%1</t>%2<br/><t color='%3' font='RobotoCondensedBold'>%4</t>  <t color='#8a9a93' size='0.85'>%5</t>",
            [_name] call _esc, ["", format ["<br/><t color='#c9d4cf'>dit « %1 »</t>", [_alias] call _esc]] select (_alias isNotEqualTo ""),
            _kindColor getOrDefault [_kind, "#c9d4cf"], toUpper _level, _ref]]
    ];
    if (_img isNotEqualTo "") then { _rows pushBack ["image", _img]; } else {
        if (_url isNotEqualTo "") then { _rows pushBack ["text", "<t size='0.85' color='#8a9a93'>Photo en chargement, ou au format PNG (visible sur Athena seulement).</t>"]; } else { _rows pushBack ["text", "<t size='0.85' color='#8a9a93'>Aucune photo sur Athena.</t>"]; };
    };
    if (_details isNotEqualTo "") then { _rows append [["section", "Éléments connus", ""], ["text", [_details] call _esc]]; };
    _rows append [
        ["text", format ["<t size='0.8' color='#8a9a93'>Mis à jour le %1. En cas de contact : contrôle d'identité dans l'app SSE, puis FRS au bureau SSE.</t>", _upd]],
        ["buttons", [["RETOUR", { ["open", ""] call comspec_atak_native_fnc_wantedAction; }], ["CONTRÔLE SSE", { ["SSE"] call comspec_atak_native_fnc_navigate; }, true]]]
    ];
} else {
    private _count = { params ["_k"]; { (_x select 0) isEqualTo _k } count _list };
    _rows append [
        ["title", "Avis de recherche"],
        ["segment", "", [
            [format ["TOUS (%1)", count _list], { ["filter", "ALL"] call comspec_atak_native_fnc_wantedAction; }, _filter isEqualTo "ALL"],
            [format ["PERSONNES (%1)", ["person"] call _count], { ["filter", "person"] call comspec_atak_native_fnc_wantedAction; }, _filter isEqualTo "person"],
            [format ["SURVEILLANCE (%1)", ["watchlist"] call _count], { ["filter", "watchlist"] call comspec_atak_native_fnc_wantedAction; }, _filter isEqualTo "watchlist"],
            [format ["DOSSIERS (%1)", ["interest"] call _count], { ["filter", "interest"] call comspec_atak_native_fnc_wantedAction; }, _filter isEqualTo "interest"]
        ]]
    ];
    if (_err isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e0a040'>%1</t>", _err]]; };
    if (_at isEqualTo "" && {_err isEqualTo ""}) then { _rows pushBack ["text", "<t color='#8a9a93'>Récupération des avis…</t>"]; };
    private _shown = _list select { _filter isEqualTo "ALL" || {(_x select 0) isEqualTo _filter} };
    if (_at isNotEqualTo "" && {(count _shown) isEqualTo 0} && {_err isEqualTo ""}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun avis de recherche en cours.</t>"]; };
    {
        _x params ["_kind", "_id", "_ref", "_name", "_alias", "_level"];
        private _key = format ["%1:%2", _kind, _id];
        private _img = _imgs getOrDefault [_key, ""];
        _rows pushBack ["person", [_dir + (_kindIcon getOrDefault [_kind, "app_intel.paa"]), _img] select (_img isNotEqualTo ""),
            format ["<t font='RobotoCondensedBold'>%1</t>%2<br/><t size='0.8' color='%3'>%4</t>  <t size='0.75' color='#8a9a93'>%5</t>",
                [_name] call _esc, ["", format ["  <t color='#c9d4cf' size='0.85'>« %1 »</t>", [_alias] call _esc]] select (_alias isNotEqualTo ""), _kindColor getOrDefault [_kind, "#c9d4cf"], _level, _ref],
            [["FICHE", compile format ["['open', %1] call comspec_atak_native_fnc_wantedAction;", str _key], true]],
            [[0.9, 0.9, 0.9, 1], [1, 1, 1, 1]] select (_img isNotEqualTo "")];
    } forEach _shown;
    _rows pushBack ["buttons", [[format ["ACTUALISER%1", ["", format [" (%1)", _at]] select (_at isNotEqualTo "")], { ["load"] call comspec_atak_native_fnc_wantedAction; }]]];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
