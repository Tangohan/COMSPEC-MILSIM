/*
    Lit la liste de fichiers saisie dans Eden ou Zeus.
    Blocs séparés par une ligne « --- ». Première ligne d'un bloc : « nom | type | taille en Ko » (type et taille
    facultatifs), lignes suivantes : contenu. Types : texte, document, identifiants, coord, image, audio.
      rapport.txt | texte
      Convoi prévu à 04h00 par la route nord.
      ---
      cache | coord
      045123
    Renvoie [[nom, type, contenu, taille Ko]...]. Un tableau déjà au bon format est renvoyé normalisé.
*/
params [["_src", ""]];
private _types = ["texte", "document", "identifiants", "coord", "image", "audio"];
private _norm = {
    params [["_name", ""], ["_type", "texte"], ["_content", ""], ["_size", 0]];
    _name = trim _name;
    _type = toLower trim _type;
    if !(_type in _types) then { _type = "texte"; };
    if !(_content isEqualType "") then { _content = str _content; };
    if (!(_size isEqualType 0) || {_size <= 0}) then {
        _size = switch (_type) do {
            case "image": { 640 + floor random 900 };
            case "audio": { 1800 + floor random 2600 };
            case "document": { 90 + ceil ((count _content) / 300) };
            case "coord": { 1 };
            default { 1 max ceil ((count _content) / 700) };
        };
    };
    if (_name isEqualTo "") then { _name = format ["fichier_%1", 100 + floor random 900]; };
    [_name, _type, _content, _size]
};
if (_src isEqualType []) exitWith {
    (_src select { _x isEqualType [] && {(count _x) >= 1} }) apply {
        _x params [["_n", ""], ["_t", "texte"], ["_c", ""], ["_s", 0]];
        [if (_n isEqualType "") then { _n } else { str _n }, _t, _c, _s] call _norm
    }
};
if !(_src isEqualType "") exitWith { [] };
// Fins de ligne Windows et Eden ramenées à « \n ».
private _lf = toString [10];
_src = (_src splitString toString [13]) joinString "";
// splitString saute les lignes vides : on les garde (paragraphes) en y glissant une espace.
while {(_src find (_lf + _lf)) >= 0} do { _src = _src regexReplace [_lf + _lf, _lf + " " + _lf]; };
private _out = [];
private _block = [];
private _flush = {
    if ((_block findIf { (trim _x) isNotEqualTo "" }) < 0) exitWith { _block = []; };
    while {(count _block) > 0 && {(trim (_block select 0)) isEqualTo ""}} do { _block deleteAt 0; };
    private _head = (_block select 0) splitString "|";
    private _name = trim (_head param [0, ""]);
    private _type = trim (_head param [1, "texte"]);
    private _size = parseNumber trim (_head param [2, "0"]);
    private _body = (_block select [1]) joinString _lf;
    _out pushBack ([_name, _type, trim _body, _size] call _norm);
    _block = [];
};
{
    if ((trim _x) isEqualTo "---") then { call _flush; } else { _block pushBack _x; };
} forEach (_src splitString _lf);
call _flush;
_out
