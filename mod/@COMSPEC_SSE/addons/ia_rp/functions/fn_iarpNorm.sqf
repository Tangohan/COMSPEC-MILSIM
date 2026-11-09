/*
    Texte libre normalisé pour l'interprète (pur, toute machine, Eden compris) : minuscules, accents retirés,
    ponctuation, apostrophes et retours à la ligne en espaces, espaces simples, une espace en tête et en fin
    (les motifs du lexique marquent ainsi une limite de mot).
    "D'où venez-vous ?" call comspec_sse_fnc_iarpNorm -> " d ou venez vous "
*/
params [["_s", "", [""]]];
private _map = missionNamespace getVariable ["comspec_sse_iarpAccents", createHashMap];
if ((count _map) isEqualTo 0) then {
    _map = createHashMap;
    {
        _x params ["_to", "_from"];
        private _rep = toArray _to;
        { _map set [_x, _rep]; } forEach (toArray _from);
    } forEach [
        ["a", "àáâãäåāÀÁÂÃÄÅĀ"], ["c", "çćčÇĆČ"], ["e", "èéêëēėęÈÉÊËĒĖĘ"], ["i", "ìíîïīÌÍÎÏĪ"], ["n", "ñńÑŃ"],
        ["o", "òóôõöøōÒÓÔÕÖØŌ"], ["u", "ùúûüūÙÚÛÜŪ"], ["y", "ýÿÝŸ"], ["z", "žźżŽŹŻ"], ["s", "šśŠŚ"], ["l", "łŁ"],
        ["oe", "œŒ"], ["ae", "æÆ"], ["ss", "ß"]
    ];
    missionNamespace setVariable ["comspec_sse_iarpAccents", _map];
};
private _out = [];
private _sp = true;
{
    private _rep = _map getOrDefault [_x, []];
    call {
        if (_rep isNotEqualTo []) exitWith { _out append _rep; _sp = false; };
        // Lettres et chiffres ASCII gardés (majuscules ramenées en minuscules ici : toLower ne suffit pas partout).
        if (_x >= 65 && {_x <= 90}) exitWith { _out pushBack (_x + 32); _sp = false; };
        if ((_x >= 97 && {_x <= 122}) || {_x >= 48 && {_x <= 57}}) exitWith { _out pushBack _x; _sp = false; };
        if (!_sp) then { _out pushBack 32; _sp = true; };
    };
} forEach (toArray _s);
if (_out isNotEqualTo [] && {(_out select -1) isEqualTo 32}) then { _out deleteAt ((count _out) - 1); };
" " + (toString _out) + " "
