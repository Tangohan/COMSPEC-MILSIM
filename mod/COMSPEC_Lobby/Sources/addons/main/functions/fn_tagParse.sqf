/*
    Étiquette d'un poste, lue dans le texte du rôle (Eden, « Description du rôle »).
    « [A-1|P] Chef de groupe » -> ["A-1", true] ; « Fusilier » -> ["", false]. P : poste protégé par un code.
    Même règle que le script de l'écran des rôles (fn_lobbyOpen.sqf, qui ne peut pas appeler CfgFunctions avant une mission).
    [texte] call comspec_lobby_fnc_tagParse -> [étiquette, protégé]
*/
params [["_text", "", [""]]];
private _a = _text find "[";
private _b = _text find "]";
if (_a < 0 || {_b <= _a}) exitWith { ["", false] };
private _parts = (_text select [_a + 1, _b - _a - 1]) splitString "|";
if (_parts isEqualTo []) exitWith { ["", false] };
private _flags = _parts select [1, count _parts];
[trim (_parts select 0), ("P" in _flags) || {"p" in _flags}]
