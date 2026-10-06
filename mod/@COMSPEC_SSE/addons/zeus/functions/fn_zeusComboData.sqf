/*
    Données d'une ligne COMBO du formulaire (comspec_sse_fnc_uiForm) à partir
    d'une liste partagée, présélectionnée sur la valeur courante.
    [_kind, _current] call comspec_sse_fnc_zeusComboData → [valeurs, libellés, index]
*/
params [["_kind", "", [""]], ["_current", ""]];
([_kind] call comspec_sse_fnc_zeusChoices) params ["_vals", "_labs"];
private _idx = _vals findIf {
    if (_x isEqualType "" && {_current isEqualType ""}) then { (toUpper _x) isEqualTo (toUpper _current) } else { _x isEqualTo _current }
};
[_vals, _labs, _idx max 0]
