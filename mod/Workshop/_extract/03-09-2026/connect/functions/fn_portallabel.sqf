/*
    Libellé lisible du portail Athena à partir de l’URL (évite le bug « Portail : public »
    quand splitString ommet les segments vides de https://…).
    Params: [_url]
    Retourne: string
*/
params [["_url", ""]];
_url = trim _url;
if (_url isEqualTo "") exitWith { "—" };

private _rest = _url;
private _schemeAt = _url find "://";
if (_schemeAt >= 0) then {
    _rest = _url select [_schemeAt + 3, (count _url) - _schemeAt - 3];
};

// Retirer éventuel préfixe userinfo@
private _at = _rest find "@";
if (_at >= 0) then {
    _rest = _rest select [_at + 1, (count _rest) - _at - 1];
};

private _parts = _rest splitString "/";
private _host = "";
{ if (_host isEqualTo "" && {!(_x isEqualTo "")}) then { _host = _x; }; } forEach _parts;

if (_host isEqualTo "") exitWith { _url };

private _path1 = "";
private _seenHost = false;
{
    if (!_seenHost) then {
        if (_x isEqualTo _host) then { _seenHost = true; };
    } else {
        if (_path1 isEqualTo "" && {!(_x isEqualTo "")}) then { _path1 = _x; };
    };
} forEach _parts;

if (_path1 isEqualTo "public" || {_path1 isEqualTo "index.php"}) then {
    _host + "/" + _path1
} else {
    _host
}
