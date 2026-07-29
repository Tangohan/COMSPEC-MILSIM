/*
    Extrait les métadonnées d’un point de mission depuis le payload d’ordre MOVE.
    Format : texte libre @WP:pos_x|pos_y|GRID:…|ETA:…|DIST:…|SPD:…|LBL:…
    Retourne HashMap (vide si absent).
*/
params [["_payload", "", [""]]];

private _result = createHashMap;
if (_payload isEqualTo "") exitWith { _result };

private _idx = _payload find "@WP:";
if (_idx < 0) exitWith { _result };

private _text = trim (_payload select [0, _idx]);
if (_text != "") then { _result set ["text", _text]; };

private _meta = _payload select [_idx + 4, count _payload - _idx - 4];
private _parts = _meta splitString "|";
if ((count _parts) < 2) exitWith { _result };

private _px = parseNumber (_parts select 0);
private _py = parseNumber (_parts select 1);
if (_px isEqualTo 0 && {_py isEqualTo 0}) exitWith { _result };

_result set ["pos_x", _px];
_result set ["pos_y", _py];

for "_i" from 2 to (count _parts - 1) do {
    private _p = _parts select _i;
    if (_p find "GRID:" == 0) then {
        _result set ["grid", _p select [5, count _p - 5]];
        continue;
    };
    if (_p find "ETA:" == 0) then {
        _result set ["eta_min", parseNumber (_p select [4, count _p - 4])];
        continue;
    };
    if (_p find "DIST:" == 0) then {
        _result set ["dist_m", parseNumber (_p select [5, count _p - 5])];
        continue;
    };
    if (_p find "SPD:" == 0) then {
        _result set ["speed_kph", parseNumber (_p select [4, count _p - 4])];
        continue;
    };
    if (_p find "LBL:" == 0) then {
        _result set ["label", _p select [4, count _p - 4]];
        continue;
    };
};

_result
