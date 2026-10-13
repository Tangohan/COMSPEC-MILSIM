/*
    Formulaire générique SSE (vanilla, sans ZEN) — dialogue 93600.

    [_title, _rows, _onConfirm, _args, _description] call comspec_sse_fnc_uiForm

    _rows : tableau de [_type, _label, _tooltip, _data]
      "COMBO"  _data = [[valeurs], [libellés], indexParDéfaut]
      "EDIT"   _data = texte par défaut
      "NUMBER" _data = [défaut, min, max]           (saisie bornée)
      "CHECK"  _data = booléen
      "SLIDER" _data = [min, max, défaut, décimales]

    _onConfirm : code appelé avec [_values, _args] après « VALIDER »
                 (_values dans l'ordre des lignes : valeur, texte, nombre ou booléen).
    Retour : true si le dialogue est ouvert.
*/
params [
    ["_title", "COMSPEC SSE", [""]],
    ["_rows", [], [[]]],
    ["_onConfirm", {}, [{}]],
    ["_args", []],
    ["_description", "", [""]]
];

if (!hasInterface) exitWith { false };

if !(createDialog "COMSPEC_SSE_FormDialog") exitWith {
    hint (("COMSPEC SSE : impossible d'ouvrir le formulaire.") call comspec_sse_fnc_tr);
    false
};

private _display = findDisplay 93600;
if (isNull _display) exitWith { false };
uiNamespace setVariable ["COMSPEC_SSE_FormDisplay", _display];

([] call comspec_sse_fnc_uiGrid) params ["", "", "_gw", "_gh"];

(_display displayCtrl 93601) ctrlSetText (((toUpper _title)) call comspec_sse_fnc_tr);
private _desc = _display displayCtrl 93602;
if (_description isEqualTo "") then {
    _desc ctrlSetStructuredText parseText (("<t color='#9FAAA2'>Réglez les champs puis validez. Survolez un libellé pour son aide.</t>") call comspec_sse_fnc_tr);
} else {
    _desc ctrlSetStructuredText parseText ((format ["<t color='#C8D2CA'>%1</t>", _description]) call comspec_sse_fnc_tr);
};

private _group = _display displayCtrl 93610;
private _entries = [];
private _rowStep = 1.45;
private _labelW = 8.4;
private _fieldX = 8.8;
private _fieldW = 11.6;

{
    _x params [
        ["_type", "EDIT", [""]],
        ["_label", "", [""]],
        ["_tip", "", [""]],
        ["_data", ""]
    ];
    _type = toUpper _type;
    private _y = (0.15 + _forEachIndex * _rowStep) * _gh;

    private _lbl = _display ctrlCreate ["COMSPEC_SSE_RscText", -1, _group];
    _lbl ctrlSetPosition [0, _y, _labelW * _gw, 1.15 * _gh];
    _lbl ctrlSetText ((_label) call comspec_sse_fnc_tr);
    if (_tip isNotEqualTo "") then { _lbl ctrlSetTooltip ((_tip) call comspec_sse_fnc_tr); };
    _lbl ctrlCommit 0;

    private _ctrl = controlNull;
    private _pos = [_fieldX * _gw, _y, _fieldW * _gw, 1.15 * _gh];

    switch (_type) do {
        case "COMBO": {
            _data params [["_vals", [], [[]]], ["_labs", [], [[]]], ["_def", 0, [0]]];
            _ctrl = _display ctrlCreate ["COMSPEC_SSE_RscCombo", -1, _group];
            {
                private _txt = _labs param [_forEachIndex, str _x];
                if !(_txt isEqualType "") then { _txt = str _txt; };
                _ctrl lbAdd ((_txt) call comspec_sse_fnc_tr);
            } forEach _vals;
            if (_vals isNotEqualTo []) then {
                _ctrl lbSetCurSel ((_def max 0) min ((count _vals) - 1));
            };
        };
        case "CHECK": {
            _ctrl = _display ctrlCreate ["COMSPEC_SSE_RscCheckBox", -1, _group];
            _pos = [_fieldX * _gw, _y, 1.5 * _gw, 1.15 * _gh];
            _ctrl cbSetChecked (_data isEqualTo true);
        };
        case "SLIDER": {
            _data params [["_min", 0, [0]], ["_max", 100, [0]], ["_def", 50, [0]], ["_dec", 0, [0]]];
            _ctrl = _display ctrlCreate ["COMSPEC_SSE_RscSlider", -1, _group];
            _pos = [_fieldX * _gw, _y, (_fieldW - 2.6) * _gw, 1.15 * _gh];
            _ctrl sliderSetRange [_min, _max];
            private _step = if (_dec > 0) then { 10 ^ (-_dec) } else { 1 max (round ((_max - _min) / 50)) };
            _ctrl sliderSetSpeed [_step, _step * 5];
            _ctrl sliderSetPosition ((_def max _min) min _max);

            private _val = _display ctrlCreate ["COMSPEC_SSE_RscText", -1, _group];
            _val ctrlSetPosition [(_fieldX + _fieldW - 2.4) * _gw, _y, 2.4 * _gw, 1.15 * _gh];
            _val ctrlSetText (((((_def max _min) min _max) toFixed _dec)) call comspec_sse_fnc_tr);
            _val ctrlCommit 0;
            _ctrl setVariable ["comspec_sse_valueCtrl", _val];
            _ctrl setVariable ["comspec_sse_decimals", _dec];
            _ctrl ctrlAddEventHandler ["SliderPosChanged", {
                params ["_c", "_v"];
                private _out = _c getVariable ["comspec_sse_valueCtrl", controlNull];
                if (!isNull _out) then {
                    _out ctrlSetText (((_v toFixed (_c getVariable ["comspec_sse_decimals", 0]))) call comspec_sse_fnc_tr);
                };
            }];
        };
        case "NUMBER": {
            _data params [["_def", 0, [0]], ["_min", -1e9, [0]], ["_max", 1e9, [0]]];
            _ctrl = _display ctrlCreate ["COMSPEC_SSE_RscEdit", -1, _group];
            _ctrl ctrlSetText (str _def); // i18n:brut
        };
        default {
            _type = "EDIT";
            _ctrl = _display ctrlCreate ["COMSPEC_SSE_RscEdit", -1, _group];
            _ctrl ctrlSetText ((if (_data isEqualType "") then { _data } else { str _data })); // i18n:brut
        };
    };

    _ctrl ctrlSetPosition _pos;
    if (_tip isNotEqualTo "") then { _ctrl ctrlSetTooltip ((_tip) call comspec_sse_fnc_tr); };
    _ctrl ctrlCommit 0;
    _entries pushBack [_type, _ctrl, _data];
} forEach _rows;

uiNamespace setVariable ["COMSPEC_SSE_FormState", [_entries, _onConfirm, _args]];
true
