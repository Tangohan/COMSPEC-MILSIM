/*
    Lecture d'une tenue, quelle que soit sa forme (toutes machines, sans effet) :
    [_src] call comspec_sse_fnc_loadoutParse -> tableau getUnitLoadout à 10 entrées, ou [] si illisible.
      _src : tableau, ou texte JSON (Athena, fn_loadoutExport) ou str SQF ; 64 Ko au plus.
      Emballages acceptés : [tenue, infos étendues ACE / CBA], [nom, tenue], [tenue, version], tenue elle-même en texte.
    Jamais de compile : parseSimpleArray seulement (null du JSON lu comme "").
*/
params [["_src", []]];
private _is10 = {
    _this isEqualType [] && {(count _this) == 10} && {(_this select 3) isEqualType []} && {(_this select 4) isEqualType []}
        && {(_this select 6) isEqualType ""} && {(_this select 9) isEqualType []}
};
private _out = [];
for "_depth" from 0 to 3 do {
    if (_src isEqualType "") then {
        private _t = trim _src;
        if (_t isEqualTo "" || {(count _t) > 65536} || {(_t select [0, 1]) isNotEqualTo "["}) exitWith { _src = []; };
        // JSON : null n'existe pas en SQF.
        if ((_t find "null") >= 0) then { _t = _t regexReplace ["([\[,])\s*null\s*(?=[,\]])", "$1"""""]; };
        _src = parseSimpleArray _t;
    };
    if !(_src isEqualType []) exitWith { _src = []; };
    if (_src call _is10) exitWith { _out = _src; };
    if ((count _src) != 2) exitWith {};
    _src params ["_a", "_b"];
    _src = switch (true) do {
        // [tenue, infos étendues] ou [tenue, version]
        case (_a call _is10): { _a };
        // [nom, tenue] (tenue en tableau ou en texte)
        case (_a isEqualType "" && {_b isEqualTypeAny [[], ""]}): { _b };
        // [tenue en texte, infos]
        case (_a isEqualType "" && {(_a select [0, 1]) isEqualTo "["}): { _a };
        default { [] };
    };
};
_out
