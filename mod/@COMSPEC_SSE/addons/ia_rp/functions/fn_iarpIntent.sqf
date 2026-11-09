/*
    Interprète d'intentions (pur, toute machine, Eden compris) : rattache une phrase libre, en français ou en anglais,
    à une intention connue. Aucune IA générative ni requête réseau : mots-clés et synonymes du lexique
    (data\iarp_lexicon.sqf), texte normalisé (accents, ponctuation : fn_iarpNorm).
    [texte] call comspec_sse_fnc_iarpIntent -> [intention, score, [motifs trouvés]]
      intention : origin, employer, job, travel, pob, contact, address, name, age, family, papers, search, handsup,
                  follow, halt, leave, kneel, dismount, trunk, threat, bribe, intel, weapons, calm, help, greet, bye,
                  thanks, sorry, howareyou, yes, no (+ celles d'une mission) ; rien de reconnu : "unknown".
    Exemple : ["Bonjour, d'où venez-vous ?"] call comspec_sse_fnc_iarpIntent -> ["origin", 11, [" d ou venez"]]
*/
params [["_text", "", [""]]];
private _n = _text call comspec_sse_fnc_iarpNorm;
if ((trim _n) isEqualTo "") exitWith { ["unknown", 0, []] };
private _best = "unknown";
private _bs = 0;
private _bm = [];
{
    _x params ["_intent", ["_w", 1], ["_pats", []]];
    private _s = 0;
    private _m = [];
    {
        if ((_n find _x) >= 0) then { _s = _s + (count _x); _m pushBack _x; };
    } forEach _pats;
    _s = _s * _w;
    if (_s > _bs) then { _best = _intent; _bs = _s; _bm = _m; };
} forEach (["lexicon"] call comspec_sse_fnc_iarpData);
[_best, round (_bs * 10) / 10, _bm]
