/*
    Plan de zones lu du profil ou reçu du réseau (paires, fn_zoneAction "pack") -> HashMap vérifiée : valeurs bornées,
    textes nettoyés, zones et axes mal formés écartés, coins des cases de grille refaits d'après leur rectangle.
    Renvoie une HashMap vide si le plan est illisible.
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
private _pts = { params ["_l", "_max"]; if !(_l isEqualType []) exitWith { [] }; ((_l select { [_x] call _pos }) select [0, _max]) apply { [_x select 0, _x select 1] } };
private _states = (["states"] call comspec_atak_native_fnc_zoneAction) apply { _x select 0 };
private _types = (["types"] call comspec_atak_native_fnc_zoneAction) apply { _x select 0 };
private _natures = (["natures"] call comspec_atak_native_fnc_zoneAction) apply { _x select 0 };
// Style d'une zone ou d'un trait : [couleur (classe CfgMarkerColors ou ""), opacité (-1), épaisseur (-1), trait ("")].
private _colors = (["colors"] call comspec_atak_native_fnc_routeAction) apply { _x select 0 };
private _sty = {
    params ["_s"];
    if !(_s isEqualType []) then { _s = []; };
    [[_s param [0, ""], _colors, ""] call _pick, [_s param [1, -1], -1, -1, 0.8] call _num, round ([_s param [2, -1], -1, -1, 6] call _num), [_s param [3, ""], ["SOLID", "DASH", "DOT", "ARROW"], ""] call _pick]
};
private _zones = [];
private _rawZ = _in getOrDefault ["zones", []];
if (_rawZ isEqualType []) then {
    {
        if (_x isEqualType [] && {(count _x) >= 12} && {(_x select 0) isEqualType ""} && {[_x select 3] call _pos}) then {
            _x params ["_zid", "_name", "_zp", "_lp", "_ty", "_rm", "_st", "_stamp", "_by", "_reps", "_bld", "_rect"];
            private _r = if (_rect isEqualType [] && {(count _rect) isEqualTo 4} && {(_rect findIf { !(_x isEqualType 0) }) < 0}) then { [_rect select 0, _rect select 1, ((_rect select 2) max 1) min 2000, ((_rect select 3) max 1) min 2000] } else { [] };
            private _p = [_zp, 200] call _pts;
            if ((count _r) isEqualTo 4) then {
                _r params ["_cx", "_cy", "_a", "_b"];
                _p = [[_cx - _a, _cy - _b], [_cx + _a, _cy - _b], [_cx + _a, _cy + _b], [_cx - _a, _cy + _b]];
            };
            if ((count _p) >= 3) then {
                private _rs = if (_reps isEqualType []) then {
                    ((_reps select { _x isEqualType [] && {(count _x) >= 8} && {(_x select 0) isEqualType ""} && {(_x select 1) isEqualType 0} }) select [0, 12]) apply {
                        [(_x select 0) select [0, 24], _x select 1, [_x select 2, 5] call _txt, [_x select 3, 24] call _txt, [_x select 4, _natures, "UNK"] call _pick,
                            [_x select 5, 12] call _txt, [_x select 6, 120] call _txt, [[0, 0], [(_x select 7) select 0, (_x select 7) select 1]] select ([_x select 7] call _pos)]
                    }
                } else { [] };
                private _bs = if (_bld isEqualType []) then {
                    ((_bld select { _x isEqualType [] && {(count _x) >= 6} && {(_x findIf { !(_x isEqualType 0) }) < 0} }) select [0, 99]) apply { _x select [0, 6] }
                } else { [] };
                _zones pushBack [(_zid select [0, 12]), [_name, 16] call _txt, _p, [_lp select 0, _lp select 1], [_ty, _types, "GEN"] call _pick,
                    [_rm, ["FILL", "LINE", "POINT"], ""] call _pick, [_st, _states, "UNREC"] call _pick, [_stamp, 0, -1e9, 1e9] call _num, [_by, 24] call _txt, _rs, _bs, _r,
                    [_x param [12, []]] call _sty];
            };
        };
    } forEach (_rawZ select [0, 600]);
};
private _axes = [];
private _rawA = _in getOrDefault ["axes", []];
if (_rawA isEqualType []) then {
    {
        if (_x isEqualType [] && {(count _x) >= 6} && {(_x select 0) isEqualType ""}) then {
            private _p = [_x select 3, 200] call _pts;
            if ((count _p) >= 2) then {
                _axes pushBack [(_x select 0) select [0, 8], [_x select 1, 24] call _txt, [_x select 2, ["MSR", "ASR"], "ASR"] call _pick, _p, [_x select 4, 0, 0, 1e6] call _num, [_x select 5, ["FILL", "LINE", "POINT"], ""] call _pick];
            };
        };
    } forEach (_rawA select [0, 8]);
};
private _cps = [];
private _rawC = _in getOrDefault ["cps", []];
if (_rawC isEqualType []) then {
    { if (_x isEqualType [] && {(count _x) >= 4} && {[_x select 2] call _pos}) then { _cps pushBack [[_x select 0, 0, 0, 99] call _num, [_x select 1, 8] call _txt, [(_x select 2) select 0, (_x select 2) select 1], [_x select 3, 24] call _txt]; }; } forEach (_rawC select [0, 20]);
};
// Contours et traits tracés : [id, nom, points, fermé, style [couleur, épaisseur, trait]].
private _lines = [];
private _rawL = _in getOrDefault ["lines", []];
if (_rawL isEqualType []) then {
    {
        if (_x isEqualType [] && {(count _x) >= 4} && {(_x select 0) isEqualType ""}) then {
            private _p = [_x select 2, 300] call _pts;
            private _cl = [_x select 3, false] call _bool;
            if ((count _p) >= ([2, 3] select _cl)) then {
                private _s = [_x param [4, []]] call _sty;
                _lines pushBack [(_x select 0) select [0, 8], [_x select 1, 16] call _txt, _p, _cl, [_s select 0, _s select 2, _s select 3]];
            };
        };
    } forEach (_rawL select [0, 40]);
};
// Style du plan.
private _st = createHashMapFromArray [["theme", "NATO"], ["fillA", -1], ["width", -1], ["dash", "SOLID"], ["lblSize", 1], ["lblBg", false], ["lblPos", "C"], ["lblMode", "NAME_ST"], ["hatch", false], ["ring", false], ["tcol", createHashMap], ["scol", createHashMap]];
private _rs = _in getOrDefault ["st", []];
if (_rs isEqualType []) then {
    private _m = createHashMap;
    { if (_x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""}) then { _m set [_x select 0, _x select 1]; }; } forEach (_rs select [0, 20]);
    _st set ["theme", [_m getOrDefault ["theme", "NATO"], ["NATO", "HC", "NV"], "NATO"] call _pick];
    _st set ["fillA", [_m getOrDefault ["fillA", -1], -1, -1, 0.8] call _num];
    _st set ["width", round ([_m getOrDefault ["width", -1], -1, -1, 6] call _num)];
    _st set ["dash", [_m getOrDefault ["dash", "SOLID"], ["SOLID", "DASH", "DOT", "ARROW"], "SOLID"] call _pick];
    _st set ["lblSize", [_m getOrDefault ["lblSize", 1], 1, 0.6, 2] call _num];
    _st set ["lblBg", [_m getOrDefault ["lblBg", false], false] call _bool];
    _st set ["lblPos", [_m getOrDefault ["lblPos", "C"], ["C", "N"], "C"] call _pick];
    _st set ["lblMode", [_m getOrDefault ["lblMode", "NAME_ST"], ["NAME_ST", "NAME", "NUM", "NONE"], "NAME_ST"] call _pick];
    _st set ["hatch", [_m getOrDefault ["hatch", false], false] call _bool];
    _st set ["ring", [_m getOrDefault ["ring", false], false] call _bool];
    {
        private _key = _x;
        private _ok = [_types, _states] select (_key isEqualTo "scol");
        private _h = createHashMap;
        private _raw = _m getOrDefault [_key, []];
        if (_raw isEqualType []) then {
            { if (_x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) in _ok} && {(_x select 1) in _colors}) then { _h set [_x select 0, _x select 1]; }; } forEach (_raw select [0, 12]);
        };
        _st set [_key, _h];
    } forEach ["tcol", "scol"];
};
// Zone de travail.
private _area = [];
private _ra = _in getOrDefault ["area", []];
if (_ra isEqualType [] && {(count _ra) isEqualTo 2}) then {
    _ra params ["_k", "_d"];
    switch (_k) do {
        case "RECT": { if (_d isEqualType [] && {(count _d) isEqualTo 2} && {[_d select 0] call _pos} && {[_d select 1] call _pos}) then { _area = ["RECT", [[(_d select 0) select 0, (_d select 0) select 1], [(_d select 1) select 0, (_d select 1) select 1]]]; }; };
        case "POLY": { private _p = [_d, 60] call _pts; if ((count _p) >= 3) then { _area = ["POLY", _p]; }; };
        case "CIRCLE": { if (_d isEqualType [] && {(count _d) isEqualTo 2} && {[_d select 0] call _pos} && {(_d select 1) isEqualType 0}) then { _area = ["CIRCLE", [[(_d select 0) select 0, (_d select 0) select 1], ((_d select 1) max 20) min 5000]]; }; };
    };
};
private _gp = createHashMap;
private _rg = _in getOrDefault ["gp", []];
if (_rg isEqualType []) then { { if (_x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} && {((_x select 1) isEqualType "") || {(_x select 1) isEqualType 0} || {(_x select 1) isEqualType true}}) then { _gp set [_x select 0, _x select 1]; }; } forEach (_rg select [0, 40]); };
createHashMapFromArray [
    ["id", _id], ["name", [_in getOrDefault ["name", "OBJ"], 24] call _txt], ["owner", [_in getOrDefault ["owner", ""]] call _txt],
    ["uid", [_in getOrDefault ["uid", ""], 24] call _txt],
    ["rev", [_in getOrDefault ["rev", 1], 1, 0, 1e7] call _num],
    ["scope", round ([_in getOrDefault ["scope", 0], 0, 0, 3] call _num)],
    ["live", [_in getOrDefault ["live", false], false] call _bool],
    ["vis", [_in getOrDefault ["vis", true], true] call _bool],
    ["arma", [_in getOrDefault ["arma", false], false] call _bool],
    ["render", [_in getOrDefault ["render", "FILL"], ["FILL", "LINE", "POINT"], "FILL"] call _pick],
    ["aff", [_in getOrDefault ["aff", "GEN"], _types, "GEN"] call _pick],
    ["area", _area], ["gp", _gp], ["zones", _zones], ["axes", _axes], ["cps", _cps], ["lines", _lines], ["st", _st],
    ["from", [_in getOrDefault ["from", ""]] call _txt],
    ["side", [_in getOrDefault ["side", ""], 12] call _txt], ["grp", [_in getOrDefault ["grp", ""], 24] call _txt], ["ft", [_in getOrDefault ["ft", ""], 24] call _txt],
    ["auto", [_in getOrDefault ["auto", ""], ["GEN", "AXES", "DRAW"], ""] call _pick],
    ["mk", []]
]
