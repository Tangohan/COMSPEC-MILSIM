/*
    Gabarit hexadécimal stable (affichage des codes d'empreinte, d'iris, de visage).
    [_key, _salt, _length] call comspec_sse_fnc_bioHex -> "3FA90C1E"
*/
params [["_key", "", [""]], ["_salt", "", ["", 0]], ["_length", 8, [0]]];

private _digits = toArray "0123456789ABCDEF";
private _out = [];
private _block = 0;
while { (count _out) < _length } do {
    private _h = [format ["%1|%2|%3", _key, _salt, _block]] call comspec_sse_fnc_bioHash;
    for "_i" from 0 to 5 do {
        if ((count _out) < _length) then {
            _out pushBack (_digits select (_h mod 16));
            _h = floor (_h / 16);
        };
    };
    _block = _block + 1;
};
toString _out
