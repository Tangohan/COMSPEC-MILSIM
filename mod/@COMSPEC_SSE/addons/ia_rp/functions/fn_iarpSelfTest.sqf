/*
    Contrôles de l'interprète et du générateur (pur, toute machine, Eden compris : console de débogage).
    [] call comspec_sse_fnc_iarpSelfTest -> [échecs [textes…], infos [textes…]]
      interprète   phrases FR et EN (accents, ponctuation, majuscules, fautes de frappe, négation, faux positifs)
                   -> intention attendue ; deux demandes dans un message -> dans l'ordre ; coût mesuré (info)
      banc d'essai data\iarp_corpus.sqf (300 phrases FR et 150 EN au moins) : taux de reconnaissance (95 % attendus),
                   aucun pot-de-vin ni menace sorti d'une phrase qui ne les demande pas ; phrases de la capture 4.2.1
      guide        chaque exemple du guide (lexique) compris comme son intention ; menu « Faire une action » : chaque
                   geste FR et EN reconnu ; ton : un peureux sans menace répond calmement ; faits absents jamais cités
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
    ["J'ai une feuille de route.", "unknown"], ["Il y a du monde sur la plage.", "unknown"], ["Votre ferme est loin ?", "unknown"],
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
// Banc d'essai (data\iarp_corpus.sqf) : phrases réelles FR et EN, variantes, fautes, abréviations ; taux mesuré
// (95 % attendus) et aucun faux positif dangereux (pot-de-vin, menace sortis d'une phrase qui ne les demande pas).
private _corpus = ["corpus"] call comspec_sse_fnc_iarpData;
private _okC = createHashMapFromArray [["fr", [0, 0]], ["en", [0, 0]]];
private _danger = [];
private _missC = [];
private _tc = diag_tickTime;
{
    _x params [["_lg", "fr"], ["_txt", ""], ["_want", ""]];
    private _got = ([_txt] call comspec_sse_fnc_iarpIntent) select 0;
    private _e = _okC getOrDefault [_lg, [0, 0]];
    _okC set [_lg, [(_e select 0) + ([0, 1] select (_got isEqualTo _want)), (_e select 1) + 1]];
    if (_got isNotEqualTo _want) then { _missC pushBack format ["« %1 » : %2 au lieu de %3", _txt, _got, _want]; };
    if (_got in ["bribe", "threat"] && {_got isNotEqualTo _want}) then { _danger pushBack format ["« %1 » -> %2", _txt, _got]; };
} forEach _corpus;
private _msC = round (((diag_tickTime - _tc) / ((count _corpus) max 1)) * 10000) / 10;
private _rate = {
    params ["_lg"];
    (_okC getOrDefault [_lg, [0, 0]]) params ["_o", "_n"];
    [round ((_o / (_n max 1)) * 1000) / 10, _n]
};
(["fr"] call _rate) params ["_rFr", "_nFr"];
(["en"] call _rate) params ["_rEn", "_nEn"];
_info pushBack format ["banc d'essai : %1 %% en français (%2 phrases), %3 %% en anglais (%4 phrases), %5 ms par phrase", _rFr, _nFr, _rEn, _nEn, _msC];
if (_nFr < 300 || {_nEn < 150}) then { _ko pushBack format ["banc d'essai : %1 phrases FR et %2 EN (300 et 150 attendues)", _nFr, _nEn]; };
if (_rFr < 95 || {_rEn < 95}) then { _ko pushBack format ["banc d'essai : %1 %% FR, %2 %% EN (95 %% attendus) ; %3", _rFr, _rEn, (_missC select [0, 8]) joinString " ; "]; };
if (_danger isNotEqualTo []) then { _ko pushBack format ["faux positifs dangereux : %1", _danger joinString " ; "]; };
{ _info pushBack ("banc d'essai, raté : " + _x); } forEach (_missC select [0, 5]);
// Phrases de la capture du propriétaire (4.2.1) et small talk.
{
    _x params ["_txt", "_want"];
    private _got = ([_txt] call comspec_sse_fnc_iarpIntent) select 0;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["intention « %1 » : %2 au lieu de %3", _txt, _got, _want]; };
} forEach [["Donner vos papiers", "papers"], ["Ton prenom ?", "name"], ["Votre prénom c'est quoi ?", "name"], ["Tu vas bien ?", "howareyou"],
    ["Comment tu t'appelles ?", "name"], ["Tu habites où ?", "origin"], ["Tu fais quoi ici ?", "travel"], ["Ça va ?", "howareyou"], ["What's your name?", "name"]];
// Guide : chaque exemple affiché (lexique) est compris comme son intention, en français et en anglais.
private _nEx = 0;
{
    _x params ["_th", "_entries"];
    {
        _x params ["_i", ["_ex", []]];
        if ((_i select [0, 1]) isNotEqualTo "_") then {
            {
                {
                    _nEx = _nEx + 1;
                    private _got = ([_x] call comspec_sse_fnc_iarpIntent) select 0;
                    if (_got isNotEqualTo _i) then { _ko pushBack format ["guide (%1) : « %2 » compris %3 au lieu de %4", _th, _x, _got, _i]; };
                } forEach _x;
            } forEach _ex;
        };
    } forEach _entries;
} forEach (["guide"] call comspec_sse_fnc_iarpData);
private _themes = (["guide"] call comspec_sse_fnc_iarpData) apply { _x select 0 };
if !(["talk", "identity", "cover", "search", "orders", "pressure", "bribe", "confront", "intel", "answer", "rp"] isEqualTo (_themes select [0, 11])) then { _ko pushBack format ["guide : thèmes %1", _themes]; };
_info pushBack format ["guide : %1 thèmes, %2 exemples vérifiés", count _themes, _nEx];
// Menu « Faire une action » : chaque geste (FR et EN) est reconnu par les règles des /me (lexique « _gesture »).
private _gest = [];
private _acts = [];
{ if ((_x select 0) isEqualTo "_gesture") then { _gest append (_x select 2); }; if ((_x select 0) isEqualTo "_actions") then { _acts append (_x select 2); }; } forEach (["lexicon"] call comspec_sse_fnc_iarpData);
private _gestOf = {
    params ["_txt"];
    private _norm = _txt call comspec_sse_fnc_iarpNorm;
    private _toks = _norm splitString " ";
    private _g = "";
    {
        _x params ["_k", "_groups"];
        if (_g isEqualTo "" && {(_groups findIf { private _grp = _x; (_grp findIf { if ((_x find " ") >= 0) then { (_norm find (" " + _x + " ")) >= 0 } else { _x in _toks } }) < 0 }) < 0}) then { _g = _k; };
    } forEach _gest;
    _g
};
private _wantG = createHashMapFromArray [["handsup", "calm"], ["lower", "calm"], ["aim", "threat"], ["badge", "badge"], ["money", "bribe"], ["search", "search"], ["cuff", "cuff"], ["take", "takePapers"], ["give", "givePapers"], ["photo", "photo"]];
{
    _x params ["_id", "", "", "_meFr", "_meEn"];
    { private _g = [_x] call _gestOf; if (_g isNotEqualTo (_wantG getOrDefault [_id, "?"])) then { _ko pushBack format ["action « %1 » : « %2 » compris %3", _id, _x, _g]; }; } forEach [_meFr, _meEn];
} forEach _acts;
if ((count _acts) < 10) then { _ko pushBack format ["menu « Faire une action » : %1 actions (10 attendues)", count _acts]; };
if ((["leve lentement les mains"] call _gestOf) isNotEqualTo "calm") then { _ko pushBack "/me « lève lentement les mains » non compris (capture 4.2.1)"; };
// Ton : un peureux sans menace répond calmement (la peur est un trait, pas un état permanent).
private _fearful = ["normalize", createHashMapFromArray [["fear", 95], ["nerve", 80], ["aggro", 0]]] call comspec_sse_fnc_iarpProfile;
private _calmTone = (["howareyou", _fearful, createHashMapFromArray [["tension", 5], ["aimed", 0]], []] call comspec_sse_fnc_iarpGen) select 3;
private _scaredTone = (["howareyou", _fearful, createHashMapFromArray [["tension", 70], ["aimed", 1]], []] call comspec_sse_fnc_iarpGen) select 3;
if (_calmTone in ["effraye", "nerveux"]) then { _ko pushBack format ["peur : un peureux sans menace répond « %1 »", _calmTone]; };
if !(_scaredTone in ["effraye", "nerveux"]) then { _ko pushBack format ["peur : un peureux braqué répond « %1 »", _scaredTone]; };
// Faits absents : une phrase qui cite {job} n'est pas choisie sans métier.
for "_i" from 1 to 12 do {
    private _g = ["howareyou", ["defaults"] call comspec_sse_fnc_iarpProfile, createHashMap, [], createHashMapFromArray [["sir", ["monsieur", "sir"]]]] call comspec_sse_fnc_iarpGen;
    if (((_g select 0) find "{job}") >= 0 || {((_g select 0) find "{city}") >= 0} || {((_g select 0) find "{employer}") >= 0}) exitWith { _ko pushBack format ["faits : phrase avec un fait absent : %1", _g select 0]; };
};
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
