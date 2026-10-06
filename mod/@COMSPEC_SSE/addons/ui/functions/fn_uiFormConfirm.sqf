/*
    Bouton « VALIDER » du formulaire générique (idd 93600).
    Lit les champs, ferme le dialogue puis appelle le code fourni à uiForm
    avec [_values, _args].
*/
private _state = uiNamespace getVariable ["COMSPEC_SSE_FormState", []];
if !(_state isEqualType [] && {count _state >= 3}) exitWith { closeDialog 2; false };
_state params [["_entries", [], [[]]], ["_code", {}, [{}]], ["_args", []]];

private _values = _entries apply {
    _x params ["_type", "_ctrl", "_data"];
    switch (_type) do {
        case "COMBO": {
            private _vals = _data param [0, []];
            private _idx = lbCurSel _ctrl;
            if (_idx < 0) then { _idx = _data param [2, 0]; };
            _vals param [_idx, _vals param [0, ""]]
        };
        case "CHECK": { cbChecked _ctrl };
        case "SLIDER": {
            private _dec = _data param [3, 0];
            private _v = sliderPosition _ctrl;
            if (_dec > 0) then { parseNumber (_v toFixed _dec) } else { round _v }
        };
        case "NUMBER": {
            private _txt = trim (ctrlText _ctrl);
            private _v = if (_txt isEqualTo "") then { _data param [0, 0] } else { parseNumber _txt };
            ((_v max (_data param [1, -1e9])) min (_data param [2, 1e9]))
        };
        default { trim (ctrlText _ctrl) };
    }
};

uiNamespace setVariable ["COMSPEC_SSE_FormState", nil];
closeDialog 1;

[_values, _args] call _code;
true
