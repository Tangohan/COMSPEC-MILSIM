/*
    Nettoie une chaine pour injection dans un litteral JS single-quoted (ExecJS).
    Les caracteres non-ASCII sont escapes en \uXXXX pour eviter tout souci
    d'encodage dans le canal SQF -> Chromium.
*/
params [["_text", ""]];
if (!(_text isEqualType "")) then { _text = str _text; };

private _hex = "0123456789abcdef";
private _out = "";
{
    // ' " \ CR LF et hors ASCII imprimable -> escape
    if (_x == 39 || {_x == 34} || {_x == 92} || {_x == 10} || {_x == 13} || {_x < 32} || {_x > 126}) then {
        if (_x == 10 || {_x == 13}) then {
            if (_x == 10) then { _out = _out + " "; };
        } else {
            private _n = _x max 0 min 65535;
            _out = _out + "\\u"
                + (_hex select [floor (_n / 4096), 1])
                + (_hex select [floor ((_n % 4096) / 256), 1])
                + (_hex select [floor ((_n % 256) / 16), 1])
                + (_hex select [_n % 16, 1]);
        };
    } else {
        _out = _out + toString [_x];
    };
} forEach (toArray _text);

_out
