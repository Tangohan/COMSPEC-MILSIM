/*
    Attribut Eden « Pays des papiers » (COMSPEC_ID_Country, COMSPEC_ID_GroupCountry) : liste dynamique du registre des pays
    (CfgComspecCountries, extensions comprises).
    ["load", [combo, valeur]] · ["save", combo] -> valeur · ["rows"] -> [[valeur, libellé]…]
    Ordre : « Auto (selon la carte) », pays réels (France, Allemagne, États-Unis…), séparateur, pays fictifs d'Arma (Altis,
    Tanoa, Livonie, Malden). Valeur enregistrée hors liste (pays d'une extension absente de ce poste) : gardée telle quelle.
*/
params [["_op", "load"], ["_a", []]];
if (_op isEqualTo "rows") exitWith {
    private _rows = [["auto", localize "STR_COMSPEC_SSE_ID_CountryAuto"]];
    if (isNil "comspec_sse_fnc_idCountries") exitWith {
        _rows + [["ALT", "Altis"], ["TNA", "Tanoa"], ["LVN", "Livonia"], ["MLD", "Malden"]]
    };
    private _all = [] call comspec_sse_fnc_idCountries;
    private _sep = false;
    {
        private _fict = (_all get _x) getOrDefault ["fictional", false];
        if (_fict && {!_sep}) then { _sep = true; _rows pushBack ["", localize "STR_COMSPEC_SSE_ID_FictionalSep"]; };
        _rows pushBack [_x, format ["%1 (%2)", ["label", [_x]] call comspec_sse_fnc_idCountries, _x]];
    } forEach (["ordered"] call comspec_sse_fnc_idCountries);
    _rows
};
disableSerialization;
if (_op isEqualTo "save") exitWith {
    private _c = _a;
    private _i = lbCurSel _c;
    if (_i < 0) exitWith { "auto" };
    private _v = _c lbData _i;
    [_v, "auto"] select (_v isEqualTo "")
};
_a params ["_c", ["_value", "auto"]];
if !(_value isEqualType "") then { _value = "auto"; };
if (_value isEqualTo "") then { _value = "auto"; };
lbClear _c;
private _sel = 0;
{
    _x params ["_v", "_label"];
    private _i = _c lbAdd _label;
    _c lbSetData [_i, _v];
    if (_v isEqualTo "") then { _c lbSetColor [_i, [0.6, 0.63, 0.68, 1]]; };
    if (_v isNotEqualTo "" && {_v isEqualTo _value}) then { _sel = _i; };
} forEach (["rows"] call comspec_sse_fnc_edenCountries);
if (_sel isEqualTo 0 && {_value isNotEqualTo "auto"}) then {
    private _i = _c lbAdd format [localize "STR_COMSPEC_SSE_ID_CountryUnknown", _value];
    _c lbSetData [_i, _value];
    _sel = _i;
};
_c lbSetCurSel _sel;
