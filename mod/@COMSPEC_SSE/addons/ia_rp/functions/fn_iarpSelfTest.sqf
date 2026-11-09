/*
    Contrôles de l'interprète et du générateur (pur, toute machine, Eden compris : console de débogage).
    [] call comspec_sse_fnc_iarpSelfTest -> [échecs [textes…], infos [textes…]]
      interprète   phrases FR et EN (accents, ponctuation, majuscules) -> intention attendue
      banque       au moins 25 intentions, 3 phrases de base au moins chacune ; variantes possibles (base × décors)
      redite       même question 10 fois à la même personne : moins de 3 répliques identiques
      cohérence    10 réponses « origine » d'une personne honnête : toutes citent la ville de sa couverture
    Repris par l'autotest (scripts\ia_rp.sqf) ; rien n'est envoyé ni enregistré.
*/
private _ko = [];
private _info = [];
{
    _x params ["_txt", "_want"];
    private _got = ([_txt] call comspec_sse_fnc_iarpIntent) select 0;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["intention « %1 » : %2 au lieu de %3", _txt, _got, _want]; };
} forEach [
    ["D'où venez-vous ?", "origin"], ["Vous venez d'où ?", "origin"], ["WHERE ARE YOU FROM?", "origin"], ["Bonjour, d'où êtes-vous ?", "origin"],
    ["Pour qui travaillez-vous ?", "employer"], ["Who do you work for?", "employer"], ["Quel est votre métier ?", "job"], ["What do you do for a living?", "job"],
    ["Qu'est-ce que vous faites ici ?", "travel"], ["Where are you going?", "travel"], ["Où êtes-vous né ?", "pob"], ["Qui peut confirmer ?", "contact"],
    ["Comment vous appelez-vous ?", "name"], ["What's your name?", "name"], ["Quel âge avez-vous ?", "age"], ["Vos papiers, s'il vous plaît.", "papers"],
    ["Show me your ID", "papers"], ["Je vais vous fouiller.", "search"], ["Mains en l'air !", "handsup"], ["Hands up!", "handsup"],
    ["Suivez-moi.", "follow"], ["Ne bougez plus !", "halt"], ["Circulez.", "leave"], ["À genoux !", "kneel"], ["Descendez du véhicule.", "dismount"],
    ["Ouvrez le coffre.", "trunk"], ["Je vais vous tuer.", "threat"], ["Combien pour oublier ça ? 50 dollars.", "bribe"], ["Avez-vous vu des hommes armés ?", "intel"],
    ["Vous êtes armé ?", "weapons"], ["Calmez-vous, on ne vous veut pas de mal.", "calm"], ["Bonjour !", "greet"], ["Merci beaucoup.", "thanks"],
    ["Au revoir.", "bye"], ["Comment ça va ?", "howareyou"], ["Votre famille va bien ?", "family"], ["Blablabla xyz", "unknown"], ["", "unknown"]
];

private _bank = ["bank"] call comspec_sse_fnc_iarpData;
private _intents = (keys _bank) select { (_x select [0, 1]) isNotEqualTo "_" };
if ((count _intents) < 25) then { _ko pushBack format ["banque : %1 intentions (25 attendues)", count _intents]; };
private _deco = 1;
{ private _n = 0; { _n = _n + count _y; } forEach (_bank getOrDefault [_x, createHashMap]); _deco = _deco + _n; } forEach ["_open", "_hes", "_close", "_stage"];
private _poor = [];
private _total = 0;
{
    private _n = 0;
    { _n = _n + count _y; } forEach (_bank get _x);
    _total = _total + _n;
    if (_n < 3) then { _poor pushBack _x; };
} forEach _intents;
if (_poor isNotEqualTo []) then { _ko pushBack format ["banque : moins de 3 phrases pour %1", _poor joinString ", "]; };
_info pushBack format ["banque : %1 intentions, %2 phrases de base, %3 décors (≈ %4 variantes par intention)", count _intents, _total, _deco - 1, round ((_total / ((count _intents) max 1)) * _deco)];

// Redite et cohérence : même personne, mémoire courte comme sur le serveur (fn_iarpServer).
private _p = ["normalize", createHashMapFromArray [["honesty", 95], ["talk", 60]]] call comspec_sse_fnc_iarpProfile;
private _vars = createHashMapFromArray [["city", "Kavala"], ["sir", ["monsieur", "sir"]]];
private _recent = [];
private _seen = [];
private _dup = 0;
private _miss = 0;
for "_i" from 1 to 10 do {
    private _g = ["origin", _p, createHashMap, _recent] call comspec_sse_fnc_iarpGen;
    _recent pushBack (_g select 4);
    if ((count _recent) > 14) then { _recent deleteAt 0; };
    private _s = [_g select 0, _vars, "fr"] call comspec_sse_fnc_iarpFill;
    if (_s in _seen) then { _dup = _dup + 1; } else { _seen pushBack _s; };
    if ((_s find "Kavala") < 0) then { _miss = _miss + 1; };
    if (_i isEqualTo 1) then { _info pushBack format ["exemple : %1 / %2", _s, [_g select 1, _vars, "en"] call comspec_sse_fnc_iarpFill]; };
};
if (_dup >= 3) then { _ko pushBack format ["redite : %1 doublons sur 10", _dup]; };
if (_miss > 0) then { _ko pushBack format ["cohérence : %1 réponse(s) sur 10 sans la ville de la couverture", _miss]; };
_info pushBack format ["redite : %1 doublon(s) sur 10 ; cohérence : %2 / 10", _dup, 10 - _miss];
[_ko, _info]
