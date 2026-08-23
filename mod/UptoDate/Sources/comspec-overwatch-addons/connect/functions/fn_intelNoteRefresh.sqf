/*
    Remet à jour tout ce qui se lit sans être saisi : bandeau date / lieu,
    étiquettes, compteur de caractères, bascules de thème et emplacements de
    pièces jointes.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

private _catalog = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _kinds = _catalog getOrDefault ["kinds", []];
private _themes = _catalog getOrDefault ["themes", []];
private _bodyMax = _catalog getOrDefault ["body_max", 1000];
private _piecesMax = _catalog getOrDefault ["pieces_max", 4];
private _themesMax = _catalog getOrDefault ["themes_max", 4];

private _selected = uiNamespace getVariable ["COMSPEC_IntelNote_Themes", []];
if (!(_selected isEqualType [])) then { _selected = []; };
private _pieces = uiNamespace getVariable ["COMSPEC_IntelNote_Pieces", []];
if (!(_pieces isEqualType [])) then { _pieces = []; };

// --- Bandeau : date à gauche, lieu à droite ---
// Lecture par la mémoire : ces champs vivent dans le volet contexte, et le
// bandeau reste affiché quand ce volet est masqué.
private _dateText = trim (["value", "date"] call comspec_overwatch_connect_fnc_intelNoteCache);
if (_dateText isEqualTo "") then { _dateText = "DATE À PRÉCISER"; };
(_disp displayCtrl 9611) ctrlSetStructuredText parseText format [
    "<t size='0.44' color='#f4f5f6'>%1</t>",
    _dateText
];

private _placeText = toUpper (trim (["value", "place"] call comspec_overwatch_connect_fnc_intelNoteCache));
if (_placeText isEqualTo "") then {
    private _grid = trim (["value", "grid"] call comspec_overwatch_connect_fnc_intelNoteCache);
    _placeText = if (_grid isEqualTo "") then { "LIEU À PRÉCISER" } else { "REPÈRE " + _grid };
};
(_disp displayCtrl 9612) ctrlSetStructuredText parseText format [
    "<t size='0.44' align='right' color='#f4f5f6'>%1</t>",
    _placeText
];

// --- Étiquettes : pastilles pleines, thèmes colorés puis sigle du type ---
// Le texte structuré d'Arma ne peint pas de fond derrière un fragment : chaque
// étiquette est donc un vrai contrôle, posé à la volée.
private _kindCombo = _disp displayCtrl 9656;
private _kindIdx = lbCurSel _kindCombo;
private _kindCode = if (_kindIdx >= 0) then { _kindCombo lbData _kindIdx } else { "FRM" };
if (_kindCode isEqualTo "") then { _kindCode = "FRM"; };

private _chipLabels = [];
{
    private _code = _x;
    {
        _x params ["_themeCode", "_label", "_color"];
        if (_themeCode isEqualTo _code) exitWith {
            _chipLabels pushBack [toUpper _label, _color];
        };
    } forEach _themes;
} forEach _selected;
if (_chipLabels isEqualTo []) then {
    _chipLabels pushBack ["THÈME À CHOISIR", "#4b5563"];
};
_chipLabels pushBack [_kindCode, "#2a247f"];

// Ce rafraîchissement suit chaque frappe : ne reconstruire les pastilles que
// lorsqu'elles changent vraiment, sinon elles clignoteraient à chaque lettre.
private _signature = (_chipLabels apply { (_x select 0) + (_x select 1) }) joinString "|";
if (_signature isNotEqualTo (_disp getVariable ["COMSPEC_IntelNote_ChipsKey", ""])) then {
    _disp setVariable ["COMSPEC_IntelNote_ChipsKey", _signature];

    {
        if (!isNull _x) then { ctrlDelete _x; };
    } forEach (_disp getVariable ["COMSPEC_IntelNote_Chips", []]);

    private _fnc_hexToRgba = {
        params ["_hex"];
        private _map = toArray "0123456789abcdef";
        private _digits = (toArray (toLower _hex)) select {_x != 35};
        if ((count _digits) < 6) exitWith { [0.3, 0.3, 0.3, 1] };
        private _byte = {
            params ["_hi", "_lo"];
            (16 * ((_map find _hi) max 0) + ((_map find _lo) max 0)) / 255
        };
        [
            [_digits select 0, _digits select 1] call _byte,
            [_digits select 2, _digits select 3] call _byte,
            [_digits select 4, _digits select 5] call _byte,
            1
        ]
    };

    private _frame = uiNamespace getVariable ["COMSPEC_IntelNote_Frame", []];
    if (!(_frame isEqualType []) || {(count _frame) < 4}) then {
        _frame = [safezoneX, safezoneY, safezoneW, safezoneH, 1, 1];
    };
    _frame params ["_frameX", "_frameY", "_frameW", "_frameH", ["_size", 1], ["_ui", 1]];
    private _chipH = 0.019 * _frameH;
    private _chipY = _frameY + (0.030 * _frameH) + (0.004 * _frameH);
    private _chipX = _frameX + 0.008 * _frameW;
    private _chipGap = 0.005 * _frameW;
    private _chips = [];
    {
        _x params ["_label", "_hex"];
        // Largeur estimée sur le nombre de caractères : Arma ne mesure un texte
        // qu'une fois le contrôle posé, ce qui imposerait un second passage.
        private _chipW = (0.0042 * _frameW) * (count _label) + (0.010 * _frameW);
        private _ctrl = _disp ctrlCreate ["RscText", -1];
        if (isNull _ctrl) then { continue };
        _ctrl ctrlSetPosition [_chipX, _chipY, _chipW, _chipH];
        _ctrl ctrlSetBackgroundColor ([_hex] call _fnc_hexToRgba);
        _ctrl ctrlSetTextColor [1, 1, 1, 1];
        _ctrl ctrlSetFontHeight ((0.014 * _frameH) * _ui);
        _ctrl ctrlSetText ("  " + _label);
        _ctrl ctrlCommit 0;
        _chips pushBack _ctrl;
        _chipX = _chipX + _chipW + _chipGap;
    } forEach _chipLabels;
    _disp setVariable ["COMSPEC_IntelNote_Chips", _chips];
};

// --- Compteur ---
private _body = ["value", "body"] call comspec_overwatch_connect_fnc_intelNoteCache;
private _length = count _body;
private _counter = _disp displayCtrl 9617;
_counter ctrlSetBackgroundColor (
    if (_length >= _bodyMax) then { [0.725, 0.110, 0.110, 1] } else {
        if (_length > 0) then { [0.216, 0.255, 0.318, 1] } else { [0.863, 0.149, 0.149, 1] }
    }
);
_counter ctrlSetStructuredText parseText format [
    "<t size='0.40' align='center' color='#ffffff'>%1/%2</t>",
    _length,
    _bodyMax
];

// --- Bascules de thème ---
private _reached = (count _selected) >= _themesMax;
{
    _x params ["_code", "_label"];
    private _ctrl = _disp displayCtrl (9660 + _forEachIndex);
    if (isNull _ctrl) then { continue };
    private _on = _code in _selected;
    _ctrl ctrlSetText (if (_on) then { "* " + _label } else { _label });
    _ctrl ctrlSetBackgroundColor (
        if (_on) then { [0.165, 0.141, 0.498, 1] } else { [0.063, 0.067, 0.078, 1] }
    );
    // Plafond atteint : les thèmes non retenus se désactivent plutôt que de
    // refuser silencieusement le clic.
    _ctrl ctrlEnable (_on || {!_reached});
    _ctrl ctrlCommit 0;
} forEach _themes;

// --- Emplacements de pièces jointes ---
(_disp displayCtrl 9630) ctrlSetStructuredText parseText format [
    "<t size='0.50' color='#f4f5f6'>Pièce(s) jointe(s) (%1/%2)</t>",
    count _pieces,
    _piecesMax
];

private _pickerOn = _disp getVariable ["COMSPEC_IntelNote_PickerOn", false];
private _onPieces = (uiNamespace getVariable ["COMSPEC_IntelNote_Pane", "redaction"]) isEqualTo "pieces";
private _showSlot = _onPieces && {!_pickerOn};

for "_i" from 0 to (_piecesMax - 1) do {
    private _slot = _disp displayCtrl (9632 + _i);
    private _drop = _disp displayCtrl (9636 + _i);
    private _pic = _disp displayCtrl (9710 + _i);
    private _bg = _disp displayCtrl (9715 + _i);
    if (isNull _slot) then { continue };

    if (_i < (count _pieces)) then {
        private _piece = _pieces select _i;
        _piece params [["_kind", "capture"], ["_path", ""], ["_name", ""], ["_grid", ""], ["_author", ""], ["_caption", ""]];
        private _kindLabel = switch (toLower _kind) do {
            case "photo": { "Photographie" };
            case "document": { "Document" };
            case "croquis": { "Relevé" };
            default { "Capture d’écran" };
        };
        if (_name isEqualTo "") then { _name = "capture à la validation"; };
        _slot ctrlSetStructuredText parseText format [
            "<t size='0.36' color='#f4f5f6'>%1</t><br/><t size='0.32' color='#8b929c'>%2</t>",
            _kindLabel,
            if (_caption isEqualTo "") then { _name } else { _caption }
        ];
        if (!isNull _pic) then {
            if (_path isNotEqualTo "") then {
                _pic ctrlSetText ((_path splitString "\") joinString "/");
                _pic ctrlShow _showSlot;
            } else {
                _pic ctrlSetText "";
                _pic ctrlShow false;
            };
        };
        if (!isNull _bg) then { _bg ctrlShow _showSlot };
        _slot ctrlShow _showSlot;
        if (!isNull _drop) then {
            _drop ctrlShow _showSlot;
            _drop ctrlEnable true;
        };
    } else {
        _slot ctrlSetStructuredText parseText
            "<t size='0.36' align='center' color='#565c66'>Libre</t>";
        if (!isNull _pic) then {
            _pic ctrlSetText "";
            _pic ctrlShow false;
        };
        if (!isNull _bg) then { _bg ctrlShow _showSlot };
        _slot ctrlShow _showSlot;
        if (!isNull _drop) then {
            _drop ctrlShow false;
            _drop ctrlEnable false;
        };
    };
};
