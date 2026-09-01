/*
    Découpe une chaîne en conservant les champs vides (contrairement à splitString).
    Params: [_text, _sep (défaut tabulation)]
*/
params [["_text", "", [""]], ["_sep", toString [9], [""]]];

if (!(_text isEqualType "")) then { _text = str _text; };
if (_sep isEqualTo "") exitWith { [_text] };

private _out = [];
private _rest = _text;
private _sepLen = count _sep;
while { true } do {
    private _i = _rest find _sep;
    if (_i < 0) exitWith { _out pushBack _rest; };
    _out pushBack (_rest select [0, _i]);
    private _next = _i + _sepLen;
    private _len = count _rest;
    _rest = if (_next >= _len) then { "" } else { _rest select [_next, _len - _next] };
};
_out
