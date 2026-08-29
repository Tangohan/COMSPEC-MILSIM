/*
    Extrait le tableau getUnitLoadout depuis une entrée ACE (brut ou wrappé).
*/
params [["_data", [], [[], ""]]];

if (_data isEqualType "") then {
    if (_data isEqualTo "") exitWith { [] };
    private _parsed = parseSimpleArray _data;
    if (!(_parsed isEqualType [])) exitWith { [] };
    _data = _parsed;
};

if (!(_data isEqualType []) || {count _data < 1}) exitWith { [] };

// Format ACE parfois : [loadout, versionNumber]
if (
    count _data == 2
    && {(_data select 0) isEqualType []}
    && {(_data select 1) isEqualType 0}
) exitWith {
    _data select 0
};

_data
