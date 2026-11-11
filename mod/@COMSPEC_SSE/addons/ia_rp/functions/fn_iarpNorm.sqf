/*
    Texte libre normalisé pour l'interprète (pur, toute machine, Eden compris) : minuscules, accents retirés,
    ponctuation, apostrophes et retours à la ligne en espaces, espaces simples, une espace en tête et en fin
    (les motifs du lexique marquent ainsi une limite de mot).
    Écriture SMS courante ramenée au français écrit, mot entier (« kel », « koi », « pk », « vs »… : data\iarp_offtopic.sqf).
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
// Écriture SMS et abréviations (data\iarp_offtopic.sqf « sms ») : mot entier remplacé (« kel est ton prenom » -> « quel… »).
private _sms = missionNamespace getVariable ["comspec_sse_iarpData_sms", 0];
if !(_sms isEqualType createHashMap) then {
    _sms = createHashMapFromArray ((["data", "sms"] call comspec_sse_fnc_iarpOffTopic) select { _x isEqualType [] && {(count _x) >= 2} });
    missionNamespace setVariable ["comspec_sse_iarpData_sms", _sms];
};
private _res = toString _out;
if ((count _sms) > 0 && {_res isNotEqualTo ""}) then { _res = ((_res splitString " ") apply { _sms getOrDefault [_x, _x] }) joinString " "; };
" " + _res + " "
