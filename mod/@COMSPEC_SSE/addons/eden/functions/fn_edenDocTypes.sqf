/*
    Attribut Eden « Papiers » (COMSPEC_ID_Carry, COMSPEC_ID_GroupCarry) : liste dynamique des types de documents.
    ["load", [combo, valeur]] · ["save", combo] -> valeur · ["rows"] -> [[valeur, libellé]…]
    Valeur produite (chaîne, lue par fn_idRegistry « kit ») :
      auto | none                                  automatique (selon le camp) / aucun papier
      card | passport | license | military         anciens alias (un papier intégré)
      card_license | card_passport                 anciens alias (deux papiers)
      <clé>                                        clé de CfgComspecDocuments (id_card, press_card…), extensions comprises
      <clé1>,<clé2>                                liste de clés (valeur déjà enregistrée dans la mission ou posée par script :
                                                   gardée telle quelle, jamais perdue en silence)
    Liste : alias d'abord, puis chaque clé du registre (["list", ["", true]] call comspec_sse_fnc_idDocTypes : extensions
    coupées comprises, l'activation se décide en jeu) ; « Carte de presse (Documents d'exemple) » pour une extension.
*/
params [["_op", "load"], ["_a", []]];
if (_op isEqualTo "rows") exitWith {
    private _rows = [["auto", "Automatique"], ["none", "Aucun"], ["card", "Carte d'identité"], ["passport", "Passeport"], ["license", "Permis de conduire"],
        ["military", "Carte militaire"], ["card_license", "Carte et permis"], ["card_passport", "Carte et passeport"]];
    _rows = _rows apply { [_x select 0, (_x select 1) call comspec_sse_fnc_tr] };
    if (isNil "comspec_sse_fnc_idDocTypes") exitWith { _rows };
    private _extNames = createHashMap;
    { _extNames set [toLower (_x select 0), _x select 1]; } forEach (["extensions"] call comspec_sse_fnc_idDocTypes);
    {
        private _t = ["get", _x] call comspec_sse_fnc_idDocTypes;
        private _ext = toLower (_t getOrDefault ["extension", ""]);
        private _label = ["label", _x] call comspec_sse_fnc_idDocTypes;
        _rows pushBack [_x, if (_ext isEqualTo "") then { format ["%1 [%2]", _label, _x] } else { format ["%1 (%2)", _label, _extNames getOrDefault [_ext, _ext]] }];
    } forEach (["list", ["", true]] call comspec_sse_fnc_idDocTypes);
    _rows
};
disableSerialization;
if (_op isEqualTo "save") exitWith {
    private _c = _a;
    private _i = lbCurSel _c;
    if (_i < 0) exitWith { "auto" };
    _c lbData _i
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
    if (_v isEqualTo _value) then { _sel = _i; };
} forEach (["rows"] call comspec_sse_fnc_edenDocTypes);
// Valeur enregistrée hors liste (liste « a,b », type d'une extension absente de ce poste) : gardée telle quelle.
if (_sel isEqualTo 0 && {_value isNotEqualTo "auto"}) then {
    private _i = _c lbAdd format [("%1 (hors liste)" call comspec_sse_fnc_tr), _value];
    _c lbSetData [_i, _value];
    _sel = _i;
};
_c lbSetCurSel _sel;
