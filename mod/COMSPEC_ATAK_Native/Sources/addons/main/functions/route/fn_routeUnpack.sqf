/*
    Route lue du profil ou reçue du réseau (tableau de paires, fn_routeAction "pack") -> HashMap vérifiée : valeurs
    bornées, textes nettoyés, étapes mal formées écartées. Renvoie une HashMap vide si la route est illisible.
    Params : [paires]
*/
params [["_pk", []]];
if !(_pk isEqualType []) exitWith { createHashMap };
private _in = createHashMap;
{ if (_x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""}) then { _in set [_x select 0, _x select 1]; }; } forEach _pk;
private _id = _in getOrDefault ["id", ""];
if !(_id isEqualType "" && {_id isNotEqualTo ""} && {(count _id) < 48}) exitWith { createHashMap };
private _txt = { params ["_v", ["_n", 32]]; if !(_v isEqualType "") exitWith { "" }; ((trim _v) regexReplace ["[<>""\\]", ""]) select [0, _n] };
private _num = { params ["_v", "_d", "_lo", "_hi"]; if !(_v isEqualType 0) exitWith { _d }; (_v max _lo) min _hi };
private _bool = { params ["_v", "_d"]; [_d, _v] select (_v isEqualType true) };
private _pick = { params ["_v", "_ok", "_d"]; [_d, _v] select (_v isEqualType "" && {_v in _ok}) };
private _pos = { params ["_p"]; _p isEqualType [] && {(count _p) >= 2} && {(_p select 0) isEqualType 0} && {(_p select 1) isEqualType 0} };
private _types = (["types"] call comspec_atak_native_fnc_routeAction) apply { _x select 0 };
private _acts = (["acts"] call comspec_atak_native_fnc_routeAction) apply { _x select 0 };
private _steps = [];
{
    if (_x isEqualType [] && {(count _x) >= 3} && {[_x select 0] call _pos}) then {
        _x params ["_p", ["_k", "WP"], ["_n", ""], ["_note", ""], ["_hp", -1], ["_ac", ""]];
        _steps pushBack [[_p select 0, _p select 1, 0], [_k, _types, "WP"] call _pick, [_n, 24] call _txt, [_note, 120] call _txt,
            [-1, round _hp] select (_hp isEqualType 0 && {_hp >= 0} && {_hp < 10000}), [_ac, _acts, ""] call _pick];
    };
} forEach ((_in getOrDefault ["steps", []]) select [0, 80]);
private _legs = [];
private _rawLegs = _in getOrDefault ["legs", []];
if (_rawLegs isEqualType [] && {(count _rawLegs) isEqualTo ((count _steps) - 1)}) then {
    {
        private _l = if (_x isEqualType []) then { (_x select { [_x] call _pos }) apply { [_x select 0, _x select 1, 0] } } else { [] };
        _legs pushBack ((_l select [0, 600]));
    } forEach _rawLegs;
};
if ((_legs findIf { (count _x) < 2 }) >= 0) then { _legs = []; };
private _hh = _in getOrDefault ["hh", ""];
if !(_hh isEqualType "" && {(count _hh) <= 5}) then { _hh = ""; };
createHashMapFromArray [
    ["id", _id], ["name", [_in getOrDefault ["name", "Route"]] call _txt], ["owner", [_in getOrDefault ["owner", ""]] call _txt],
    ["uid", [_in getOrDefault ["uid", ""], 24] call _txt],
    ["kind", [_in getOrDefault ["kind", "FOOT"], ["FOOT", "VEH", "AIR", "SEA"], "FOOT"] call _pick],
    ["roads", [_in getOrDefault ["roads", false], false] call _bool],
    ["speed", [_in getOrDefault ["speed", 4], 4, 1, 900] call _num],
    ["corr", [_in getOrDefault ["corr", 500], 500, 50, 5000] call _num],
    ["color", [_in getOrDefault ["color", "ColorGreen"], ["ColorRed", "ColorOrange", "ColorYellow", "ColorGreen", "ColorBlue", "ColorPink", "ColorBlack", "ColorWhite"], "ColorGreen"] call _pick],
    ["width", round ([_in getOrDefault ["width", 2], 2, 1, 3] call _num)],
    ["style", [_in getOrDefault ["style", "SOLID"], ["SOLID", "DASH", "ARROW"], "SOLID"] call _pick],
    ["vis", [_in getOrDefault ["vis", true], true] call _bool],
    ["arma", [_in getOrDefault ["arma", false], false] call _bool],
    ["d3", [_in getOrDefault ["d3", false], false] call _bool],
    ["hh", _hh], ["steps", _steps], ["legs", _legs],
    ["rev", [_in getOrDefault ["rev", 1], 1, 0, 1e7] call _num],
    ["from", [_in getOrDefault ["from", ""]] call _txt],
    ["scope", round ([_in getOrDefault ["scope", 0], 0, 0, 3] call _num)],
    ["live", [_in getOrDefault ["live", false], false] call _bool],
    ["mk", []]
]
