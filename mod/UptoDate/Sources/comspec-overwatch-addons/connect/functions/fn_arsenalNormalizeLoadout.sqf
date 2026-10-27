/*
    Extrait le tableau getUnitLoadout depuis une entrée ACE (brut, wrappé ou texte).
    ACE enregistre parfois [tenue, version], [tenue, infos] ou un HashMap : sans ce déballage,
    l'aperçu Tenues Athena affichait « Équipement non disponible » alors que la tenue s'enfilait.
*/
params [["_data", [], [[], "", createHashMap]]];

private _fncParse = {
    params ["_raw"];
    if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { [] };
    private _parsed = parseSimpleArray _raw;
    if (_parsed isEqualType []) then { _parsed } else { [] }
};

if (_data isEqualType "") then {
    _data = [_data] call _fncParse;
};

if (_data isEqualType createHashMap) then {
    private _lo = _data getOrDefault ["loadout", _data getOrDefault ["Loadout", []]];
    if (_lo isEqualType "") then { _lo = [_lo] call _fncParse; };
    if (!(_lo isEqualType [])) exitWith { [] };
    _data = _lo;
};

if (!(_data isEqualType []) || {(count _data) < 1}) exitWith { [] };

private _fncUnwrap = {
    params ["_arr"];
    if (
        (count _arr) >= 2
        && {(_arr select 0) isEqualType []}
        && {(count (_arr select 0)) >= 9}
    ) then {
        _arr select 0
    } else {
        _arr
    }
};

_data = [_data] call _fncUnwrap;
// Parfois double enveloppe [[tenue, infos], version].
_data = [_data] call _fncUnwrap;

if (!(_data isEqualType []) || {(count _data) < 9}) exitWith { [] };

_data
