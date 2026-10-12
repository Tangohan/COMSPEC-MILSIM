/*
    Gabarits d'une clé biométrique : 10 empreintes et 2 iris, tels que le registre
    les tient pour garantir qu'aucune autre identité ne les partage.
    [_key] call comspec_sse_fnc_bioTemplates -> ["F0:3FA90C1E", ..., "I1:…"]
*/
params [["_key", "", [""]]];
if (_key isEqualTo "") exitWith { [] };
private _out = [];
for "_i" from 0 to 9 do { _out pushBack format ["F%1:%2", _i, [_key, "fp" + str _i, 8] call comspec_sse_fnc_bioHex]; };
for "_e" from 0 to 1 do { _out pushBack format ["I%1:%2", _e, [_key, "ir" + str _e, 16] call comspec_sse_fnc_bioHex]; };
_out
