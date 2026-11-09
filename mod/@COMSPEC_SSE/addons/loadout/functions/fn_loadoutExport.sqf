/*
    Tenue d'une unité en texte JSON pour Athena (LOCAL) : [_unit, _copy] call comspec_sse_fnc_loadoutExport -> texte
      _copy : true (défaut) = copié au presse-papiers et montré au joueur ; false = texte rendu seulement (scripts).
    JSON strict (lu par JSON.parse du site et par parseSimpleArray) : chaînes entre guillemets, \ et " échappés,
    nombres, true / false, tableaux. Radios ACRE d'instance (ACRE_PRC152_ID_3) ramenées à leur classe de base.
    En multijoueur copyToClipboard est sans effet : le texte s'ouvre dans une fenêtre, à sélectionner et copier.
*/
params [["_unit", objNull, [objNull]], ["_copy", true, [true]]];
if (isNull _unit) exitWith { "" };
private _lo = getUnitLoadout _unit;
private _acre = !isNil "acre_api_fnc_getBaseRadio";
private _str = {
    private _s = _this;
    if (_acre && {(toLower _s) regexMatch "acre_.+_id_[0-9]+"}) then { _s = [_s] call acre_api_fnc_getBaseRadio; };
    private _o = [34];
    {
        switch (true) do {
            case (_x == 92): { _o append [92, 92]; };
            case (_x == 34): { _o append [92, 34]; };
            case (_x == 10): { _o append [92, 110]; };
            case (_x == 13): { _o append [92, 114]; };
            case (_x == 9): { _o append [92, 116]; };
            case (_x < 32): { _o pushBack 32; };
            default { _o pushBack _x; };
        };
    } forEach toArray _s;
    _o pushBack 34;
    toString _o
};
private _json = {
    if (_this isEqualType "") exitWith { _this call _str };
    if (_this isEqualType true) exitWith { ["false", "true"] select _this };
    if (_this isEqualType 0) exitWith { if ((_this % 1) == 0 && {abs _this < 1e6}) then { _this toFixed 0 } else { str _this } };
    if (_this isEqualType []) exitWith { "[" + ((_this apply { _x call _json }) joinString ",") + "]" };
    "null"
};
private _txt = _lo call _json;
if (!_copy) exitWith { _txt };
copyToClipboard _txt;
diag_log text format ["[COMSPEC SSE] Équipement : tenue de %1 exportée (%2 caractères).", name _unit, count _txt];
if (isMultiplayer) then {
    ["Ma tenue pour Athena", "Ctrl+A puis Ctrl+C, puis collez dans Athena › Équipement › Configurateur.", _txt] call comspec_sse_fnc_loadoutTextDialog;
} else {
    private _msg = "Équipement : tenue copiée au presse-papiers. Collez-la dans Athena › Équipement › Configurateur.";
    if !([_msg, "SUCCESS", "Équipement"] call comspec_sse_fnc_notify) then { hint _msg; };
};
_txt
