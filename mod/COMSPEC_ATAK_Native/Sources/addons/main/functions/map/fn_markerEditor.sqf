/* Panneau d'édition d'un marqueur, par-dessus la carte (pleine largeur en mini, à droite en plein écran). */
params ["_rect"];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = _s getOrDefault ["markerEdit", createHashMap];
if ((count _e) isEqualTo 0) exitWith { false };
([] call comspec_atak_native_fnc_markerCatalog) params ["_types", "_colors"];
private _isNew = (_e get "name") isEqualTo "";
private _poly = (_e get "shape") isEqualTo "POLYLINE";
// Fiche en sections (système de design 3.1.0) : en-tête avec la grille, puis Identité, Apparence, Diffusion, actions.
private _rows = [
    ["header", ["Marqueur", "Nouveau marqueur"] select _isNew, format ["%1 · %2", [_e get "pos"] call comspec_atak_native_fnc_gridRef, ["point", "tracé"] select _poly], [], {}],
    ["section", "Identité", "Titre affiché sur la carte, description partagée"],
    ["edit", "text", "Titre", _e get "text"],
    ["memo", "desc", "Description", _e get "desc", 2],
    ["section", "Apparence", ["Type, couleur, taille, orientation, opacité", "Couleur et opacité du tracé"] select _poly]
];
if !(_poly) then {
    // Liste courte d'abord (palette : tactique, puis chaque camp et son type d'unité), puis la bibliothèque des
    // marqueurs chargés sans doublon de libellé : les mods en déclarent des centaines au même nom (« Inconnu »…).
    ([] call comspec_atak_native_fnc_markerPaletteData) params ["_affs", "_by"];
    private _items = [];
    private _seen = createHashMap;
    private _add = {
        params ["_label", "_cls", ["_color", [1, 1, 1, 1]]];
        if (_cls in _seen) exitWith {};
        private _icon = getText (configFile >> "CfgMarkers" >> _cls >> "icon");
        if (_icon isEqualTo "") exitWith {};
        _seen set [_cls, true];
        _items pushBack [_label, _cls, _icon, _color];
    };
    {
        _x params ["_k", "_side", "", "_rgba"];
        if (_k isNotEqualTo "lib") then {
            private _pre = [format ["%1 · ", _side], ""] select (_k isEqualTo "mil");
            { [_pre + (_x select 1), _x select 0, [_rgba, [1, 1, 1, 1]] select (_k isEqualTo "mil")] call _add; } forEach (_by getOrDefault [_k, []]);
        };
    } forEach _affs;
    private _labels = createHashMapFromArray (_items apply { [toLower (_x select 0), true] });
    {
        _x params ["_lab", "_cls", "", "_grp"];
        private _key = toLower _lab;
        // Libellé déjà pris : on le précise par sa catégorie, sinon on l'écarte (sauf le type du marqueur édité).
        if (_key in _labels) then { _lab = format ["%1 (%2)", _lab, _grp]; _key = toLower _lab; };
        if (!(_key in _labels) || {_cls isEqualTo (_e get "type")}) then {
            _labels set [_key, true];
            [_lab, _cls] call _add;
        };
    } forEach _types;
    _rows pushBack ["combo", "type", "Type", _items, _e get "type"];
};
_rows pushBack ["combo", "color", "Couleur", _colors apply { [_x select 0, _x select 1, "#(argb,8,8,3)color(1,1,1,1)", _x select 2] }, _e get "color"];
if !(_poly) then {
    _rows pushBack ["combo", "size", "Taille", [["Petit", "0.6"], ["Normal", "1"], ["Grand", "1.5"], ["Très grand", "2.2"]] apply { [_x select 0, _x select 1] }, _e get "size"];
    _rows pushBack ["edit", "dir", "Orientation (°)", _e get "dir"];
};
_rows pushBack ["combo", "alpha", "Opacité", [["100 %", "1"], ["75 %", "0.75"], ["50 %", "0.5"], ["25 %", "0.25"]], _e get "alpha"];
if (_isNew) then {
    private _ch = [["Global", "0"], ["Camp", "1"], ["Commandement", "2"], ["Groupe", "3"], ["Véhicule", "4"], ["Direct", "5"]];
    _rows pushBack ["section", "Diffusion", "Qui voit ce marqueur"];
    _rows pushBack ["combo", "channel", "Canal", _ch apply { [_x select 0, _x select 1] }, _e get "channel"];
};
_rows pushBack ["gap"];
// Actions : ENREGISTRER (principal) et ANNULER côte à côte ; SUPPRIMER en rouge, à part, pour un marqueur existant.
_rows pushBack ["buttons", [
    ["ENREGISTRER", { [] call comspec_atak_native_fnc_markerEditSave; }, true],
    ["ANNULER", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["markerEdit", createHashMap]; [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }]
]];
if !(_isNew) then { _rows pushBack ["buttons", [["SUPPRIMER LE MARQUEUR", { [((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) get "markerEdit") get "name"] call comspec_atak_native_fnc_markerDelete; }, "red", true, "Efface ce marqueur de la carte (Suppr sur le marqueur pointé fait pareil)"]]]; };
[_rows, _rect, false] call comspec_atak_native_fnc_formRender;
true
