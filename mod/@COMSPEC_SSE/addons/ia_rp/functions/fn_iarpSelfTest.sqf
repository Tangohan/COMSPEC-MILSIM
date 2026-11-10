/*
    Contrôles de l'interprète et du générateur (pur, toute machine, Eden compris : console de débogage).
    [] call comspec_sse_fnc_iarpSelfTest -> [échecs [textes…], infos [textes…]]
      interprète   phrases FR et EN (accents, ponctuation, majuscules, fautes de frappe, négation, faux positifs)
                   -> intention attendue ; deux demandes dans un message -> dans l'ordre ; coût mesuré (info)
      banque       au moins 50 intentions, 6 phrases de base au moins chacune (FR et EN) ; variantes (base × décors)
      mémoire      fiche par joueur (fn_iarpMem), « déjà dit à votre collègue », mémoire d'avant reprise
      paliers      savoir d'une phrase (comme avant) et par paliers avec lieu, palier débloqué selon confiance et pression
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
    ["Au revoir.", "bye"], ["Comment ça va ?", "howareyou"], ["Votre famille va bien ?", "family"], ["Blablabla xyz", "unknown"], ["", "unknown"],
    // Fautes de frappe : lettres inversées, doublées, manquantes, accents absents, majuscules, lettres répétées.
    ["Vos papeirs !", "papers"], ["Vos paapiers svp", "papers"], ["Je vais vous fouiler.", "search"], ["Ouvrez le cofre.", "trunk"],
    ["Decsendez du vehicule !", "dismount"], ["Mettez-vous a genous !", "kneel"], ["Bonjuor", "greet"], ["STOOOOP !", "halt"],
    ["Vous travaillez pour qui ? Votre emploieur ?", "employer"], ["d'ou venez vous", "origin"],
    // Faux positifs : mots courants proches d'un mot du lexique, jamais corrigés.
    ["J'ai une feuille de route.", "unknown"], ["Il fait beau sur la plage.", "unknown"], ["Votre ferme est loin ?", "unknown"],
    ["Le truck est rouge.", "unknown"], ["Great job.", "unknown"],
    // Négation simple : pot-de-vin et fouille annulés, ordres inchangés, une virgule arrête la négation.
    ["Je ne veux pas d'argent.", "unknown"], ["I don't want your money.", "unknown"], ["Je ne vais pas vous fouiller.", "unknown"],
    ["Pas besoin de vous fouiller.", "unknown"], ["We don't need to search you.", "unknown"], ["Prenez cet argent.", "bribe"],
    ["Ne bougez pas !", "halt"], ["Si vous ne coopérez pas, je tire !", "threat"], ["Vous n'êtes pas armé ?", "weapons"],
    // Confrontation (l'emporte sur « papiers »).
    ["Vos papiers sont faux.", "confront"], ["Des faux papiers ?", "confront"], ["Tu mens !", "confront"], ["Je sais que c'est toi.", "confront"],
    ["You're lying.", "confront"], ["Arrêtez de mentir, dites la vérité.", "confront"],
    // Tournures familières (moins de « Je ne comprends pas »).
    ["T'es d'où, toi ?", "origin"], ["Tu t'appelles comment ?", "name"], ["Qu'est-ce que tu fais ici ?", "travel"], ["T'as vu quelque chose d'anormal ?", "intel"],
    ["Ouais.", "yes"], ["Nan, pas question.", "no"]
];
// Deux demandes dans un message : dans l'ordre où elles sont écrites.
{
    _x params ["_txt", "_want"];
    private _got = (([_txt] call comspec_sse_fnc_iarpIntent) param [3, []]) apply { _x select 0 };
    if (_got isNotEqualTo _want) then { _ko pushBack format ["demandes « %1 » : %2 au lieu de %3", _txt, _got, _want]; };
} forEach [
    ["Bonjour, vos papiers svp", ["greet", "papers"]], ["Bonjour vos papiers", ["greet", "papers"]], ["Merci. Vous pouvez partir.", ["thanks", "leave"]],
    ["D'où venez-vous ? Et pour qui travaillez-vous ?", ["origin", "employer"]], ["Salut ! Tu viens d'où ?", ["greet", "origin"]],
    ["Vous avez vu quelque chose de suspect ? Merci.", ["intel", "thanks"]], ["Vos papiers, s'il vous plaît.", ["papers"]], ["Oui, vous pouvez partir.", ["yes", "leave"]]
];
// Coût de l'interprète : 20 messages longs pleins de fautes (mesure indicative, la machine compte).
private _t0 = diag_tickTime;
for "_i" from 1 to 20 do { ["Bonjuor monsieur, vos papeirs svp, et ouvrez le cofre du vehicule, d'ou venez vous exactement ? Decsendez !"] call comspec_sse_fnc_iarpIntent; };
_info pushBack format ["interprète : %1 ms par message long avec fautes", round (((diag_tickTime - _t0) / 20) * 10000) / 10];

// Mémoire par joueur (fn_iarpMem) : confiance propre à chaque joueur, « déjà dit à votre collègue » une fois, mémoire
// d'avant reprise, 24 fiches au plus.
private _m = ["new"] call comspec_sse_fnc_iarpMem;
(["player", [_m, "A", "Alpha", west, 60, 1]] call comspec_sse_fnc_iarpMem) set ["trust", 90];
private _pb = ["player", [_m, "B", "Bravo", west, 60, 2]] call comspec_sse_fnc_iarpMem;
["player", [_m, "C", "Charlie", east, 60, 3]] call comspec_sse_fnc_iarpMem;
if ((_pb get "trust") isEqualTo 90) then { _ko pushBack "mémoire : la confiance d'un joueur est partagée avec un autre"; };
private _tl = [["A", west], ["B", west], ["B", west], ["C", east], ["A", west]] apply { ["told", [_m, _x select 0, _x select 1, "city", 0]] call comspec_sse_fnc_iarpMem };
if (_tl isNotEqualTo ["", "colleague", "", "other", ""]) then { _ko pushBack format ["mémoire : « déjà dit » %1 au lieu de [, colleague, , other, ]", _tl]; };
private _up = ["upgrade", createHashMapFromArray [["said", createHashMapFromArray [["city", ["Kavala", false]]]], ["trust", 50], ["recent", []]]] call comspec_sse_fnc_iarpMem;
if (((_up get "said") getOrDefault ["city", []]) isNotEqualTo ["Kavala", false] || {!("players" in _up)}) then { _ko pushBack "mémoire : la couverture affirmée d'avant est perdue"; };
for "_i" from 1 to 30 do { ["player", [_m, format ["P%1", _i], "x", west, 60, 10 + _i]] call comspec_sse_fnc_iarpMem; };
if ((count (_m get "players")) > 24) then { _ko pushBack format ["mémoire : %1 fiches de joueur (24 au plus)", count (_m get "players")]; };

// Savoir par paliers (fn_iarpProfile « knowTiers », « intelLevel ») : une phrase se comporte comme avant (palier précis).
private _nl = toString [10];
private _k1 = ["knowTiers", "Un pick-up blanc est parti vers le nord."] call comspec_sse_fnc_iarpProfile;
if ((_k1 select 0) isNotEqualTo ["", "Un pick-up blanc est parti vers le nord.", ""] || {(_k1 select 1) isNotEqualTo []}) then { _ko pushBack format ["paliers : savoir d'une phrase mal lu %1", _k1]; };
private _k2 = ["knowTiers", format ["Vague : A%1Précis : B%1Complet : C%1Lieu : [4512, 7820] Ferme abandonnée", _nl]] call comspec_sse_fnc_iarpProfile;
if ((_k2 select 0) isNotEqualTo ["A", "B", "C"] || {(_k2 select 1) isNotEqualTo [4512, 7820]} || {(_k2 select 2) isNotEqualTo "Ferme abandonnée"}) then { _ko pushBack format ["paliers : %1", _k2]; };
private _k3 = ["knowTiers", "Lieu : 045 078 Ferme"] call comspec_sse_fnc_iarpProfile;
if ((count (_k3 select 1)) < 2 || {(_k3 select 2) isNotEqualTo "Ferme"}) then { _ko pushBack format ["paliers : carroyage du lieu mal lu %1", _k3]; };
{
    _x params ["_ul", "_args", "_want"];
    private _got = ["intelLevel", [createHashMapFromArray [["unlock", _ul]]] + _args] call comspec_sse_fnc_iarpProfile;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["paliers : %1 %2 -> %3 au lieu de %4", _ul, _args, _got, _want]; };
} forEach [
    ["trust", [30, false, 0, false, false, "", 0, 0], 0], ["trust", [40, false, 0, false, false, "", 0, 0], 1], ["trust", [20, false, 0, false, false, "", 1, 0], 1],
    ["trust", [20, false, 0, false, false, "", 0, 70], 1], ["trust", [65, false, 0, false, false, "", 0, 0], 2], ["trust", [85, false, 0, false, false, "", 0, 0], 3],
    ["trust", [65, false, 0, false, true, "", 0, 0], 3], ["trust", [45, false, 0, false, false, "rendu", 0, 0], 2], ["never", [100, true, 3, true, true, "rendu", 2, 90], 0],
    ["bribe", [50, false, 0, false, false, "", 0, 0], 1], ["bribe", [50, true, 1, false, false, "", 0, 0], 2], ["bribe", [50, true, 2, false, false, "", 0, 0], 3],
    ["keyword", [20, false, 0, true, false, "", 0, 0], 2]
];

private _bank = ["bank"] call comspec_sse_fnc_iarpData;
private _intents = (keys _bank) select { (_x select [0, 1]) isNotEqualTo "_" };
if ((count _intents) < 50) then { _ko pushBack format ["banque : %1 intentions (50 attendues)", count _intents]; };
private _deco = 1;
{ private _n = 0; { _n = _n + count _y; } forEach (_bank getOrDefault [_x, createHashMap]); _deco = _deco + _n; } forEach ["_open", "_hes", "_close", "_stage"];
private _poor = [];
private _total = 0;
{
    private _n = 0;
    { _n = _n + count _y; } forEach (_bank get _x);
    _total = _total + _n;
    if (_n < 6) then { _poor pushBack _x; };
} forEach _intents;
if (_poor isNotEqualTo []) then { _ko pushBack format ["banque : moins de 6 phrases pour %1", _poor joinString ", "]; };
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
