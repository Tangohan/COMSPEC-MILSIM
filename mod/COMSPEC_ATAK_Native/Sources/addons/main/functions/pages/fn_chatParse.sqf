/*
    Découpe un message Athena en [heure, puces, texte, système].
    "[12:04:31][GROUPE][ROUTINE][FREE] Contact nord" -> ["12:04", ["GROUPE","ROUTINE"], "Contact nord", false]
    Les lignes techniques ("EFFECTIF|1|NewPI") sont marquées système pour être masquées.
*/
params [["_body", ""], ["_time", ""]];
if !(_body isEqualType "") then { _body = str _body; };
private _text = trim _body;
private _tags = [];
while { (_text select [0, 1]) isEqualTo "[" } do {
    private _end = _text find "]";
    if (_end < 0) exitWith {};
    private _tag = trim (_text select [1, _end - 1]);
    _text = trim (_text select [_end + 1]);
    private _parts = _tag splitString ":";
    if ((count _parts) >= 2 && {(_parts findIf { (count _x) isNotEqualTo 2 || {(parseNumber _x) isEqualTo 0 && {_x isNotEqualTo "00"}} }) < 0}) then {
        _time = (_parts select [0, 2]) joinString ":";
    } else {
        // FREE / NORMAL n'apportent rien au lecteur.
        if !((toUpper _tag) in ["FREE", "NORMAL", ""]) then { _tags pushBack (toUpper _tag); };
    };
};
if !(_time isEqualType "") then { _time = str _time; };
if ((count _time) > 5 && {(_time select [2, 1]) isEqualTo ":"}) then { _time = _time select [0, 5]; };
private _first = (_text splitString "|") param [0, ""];
private _system = (_text find "|") > 0 && {(_text find " ") < 0 || {(_text find " ") > (_text find "|")}} && {(toUpper _first) isEqualTo _first};
[_time, _tags, _text, _system]
