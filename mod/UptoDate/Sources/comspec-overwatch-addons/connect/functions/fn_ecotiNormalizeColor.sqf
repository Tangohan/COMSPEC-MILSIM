/*
    Convertit un tableau RGBA (nombres ou expressions CfgMarkerColors) en [r,g,b,a] numériques.
*/
params [["_color", [1, 1, 1, 1], [[]]]];

if (!(_color isEqualType []) || {(count _color) < 3}) exitWith { [1, 1, 1, 0.95] };

private _out = [];
{
    private _c = _x;
    if (_c isEqualType 0) then {
        _out pushBack (_c max 0 min 1);
    } else {
        if (_c isEqualType "") then {
            private _v = 0;
            if (_c find "profilenamespace" >= 0 || {_c find "(" == 0}) then {
                private _compiled = compile _c;
                _v = call _compiled;
            } else {
                _v = parseNumber _c;
            };
            if (!(_v isEqualType 0)) then { _v = 1; };
            _out pushBack (_v max 0 min 1);
        } else {
            _out pushBack 1;
        };
    };
} forEach _color;

if ((count _out) < 4) then { _out pushBack 0.95 };
_out
