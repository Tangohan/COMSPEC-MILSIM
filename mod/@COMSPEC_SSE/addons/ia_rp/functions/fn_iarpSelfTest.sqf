/*
    Contrôles de l'interprète et du générateur (pur, toute machine, Eden compris : console de débogage).
    [] call comspec_sse_fnc_iarpSelfTest -> [échecs [textes…], infos [textes…]]
      interprète   phrases FR et EN (accents, ponctuation, majuscules, fautes de frappe, négation, faux positifs)
                   -> intention attendue ; deux demandes dans un message -> dans l'ordre ; coût mesuré (info)
      banc d'essai data\iarp_corpus.sqf (1 000 phrases FR et 500 EN au moins) : taux de reconnaissance (95 % attendus),
                   aucun pot-de-vin, menace ni aveu sorti d'une phrase qui ne les demande pas ; phrases de la capture 4.2.1
      jamais vues  data\iarp_unseen.sqf (200 FR et 100 EN au moins, jamais utilisées pour régler le lexique) : taux honnête
                   (85 % attendus), aucun faux positif dangereux
      cadre        civil sans savoir : 100 questions de renseignement au moins, aucune réplique candidate ne contient un terme
                   de scénario (arme, armés, pick-up, mine, explosif, insurgé, cache…) ; le même civil avec « Précis : » et
                   « Lieu : » donne son savoir et le lieu quand il a confiance ; thèmes interdits et niveaux
      réponses     réponses perso (fn_iarpAnswers) : validation, variables {prenom} -> {first}, situation la plus précise,
                   ordre, question perso « mot de passe », échappement ; profil sérialisé et relu avec ses réponses
      guide        chaque exemple du guide (lexique) compris comme son intention ; menu « Faire une action » : chaque
                   geste FR et EN reconnu ; ton : un peureux sans menace répond calmement ; faits absents jamais cités
      banque       au moins 50 intentions, 6 phrases de base au moins chacune (FR et EN) ; variantes (base × décors)
      émotions     réglage global, réglage de l'IA qui l'emporte, IA neutre jamais effrayée, nerveuse, agressive ni
                   méfiante ; sans peur des armes, un peureux braqué reste calme ; émotions et noWeaponFear sérialisés
      mémoire      fiche par joueur (fn_iarpMem), « déjà dit à votre collègue », mémoire d'avant reprise
      paliers      savoir d'une phrase (comme avant) et par paliers avec lieu, palier débloqué selon confiance et pression
      redite       même question 10 fois à la même personne : moins de 3 répliques identiques
      cohérence    10 réponses « origine » d'une personne honnête : toutes citent la ville de sa couverture
      hors sujet   data\iarp_ottests.sqf (fn_iarpOffTopic « selfTest ») : 60 messages hors sujet sans demande à effet, 120 questions
                   légitimes (tu, vous, court, SMS) comprises, guide, dialogues (relance, incompris, provocation), phrases
      suivi        « Suivez-moi » (fn_iarpFollow « step », table data\iarp_follow.sqf) : à l'arrêt entre 2,5 et 4 m, repart
                   au-delà de 4 m, s'arrête à 2,5 m, marche près d'un joueur lent, court derrière un joueur qui court ou
                   loin devant ; « Suivez-moi. », « Reste ici. », « Va-t'en. » compris ; répliques d'une IA menottée
      agressivité  fn_iarpAggro (pur) : paliers selon la colère, le niveau et le trait (jamais au-delà de « hostile » en
                   verbal ou pour un lâche qui bluffe), dégâts plafonnés par minute et jamais mortels sans le réglage,
                   moteur de corps à corps (TSP Melee ou intégré), répliques hostiles : 3 au moins par palier et par
                   caractère, en français et en anglais, sans redite sur 6 tirages
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
    ["Ouvrez le coffre.", "trunk"], ["Je vais vous tuer.", "threat"], ["Combien pour oublier ça ? 50 dollars.", "bribe"], ["Avez-vous vu des hommes armés ?", "seenArmed"],
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
    if (_got in ["bribe", "threat", "confront"] && {_got isNotEqualTo _want}) then { _danger pushBack format ["« %1 » -> %2", _txt, _got]; };
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
if (_nFr < 1000 || {_nEn < 500}) then { _ko pushBack format ["banc d'essai : %1 phrases FR et %2 EN (1 000 et 500 attendues)", _nFr, _nEn]; };
if (_rFr < 95 || {_rEn < 95}) then { _ko pushBack format ["banc d'essai : %1 %% FR, %2 %% EN (95 %% attendus) ; %3", _rFr, _rEn, (_missC select [0, 8]) joinString " ; "]; };
if (_danger isNotEqualTo []) then { _ko pushBack format ["faux positifs dangereux : %1", _danger joinString " ; "]; };
{ _info pushBack ("banc d'essai, raté : " + _x); } forEach (_missC select [0, 5]);
// Phrases jamais vues (data\iarp_unseen.sqf) : taux honnête, séparé du banc d'essai qui sert à régler le lexique.
private _unseen = ["unseen"] call comspec_sse_fnc_iarpData;
private _okU = createHashMapFromArray [["fr", [0, 0]], ["en", [0, 0]]];
private _missU = [];
{
    _x params [["_lg", "fr"], ["_txt", ""], ["_want", ""]];
    private _got = ([_txt] call comspec_sse_fnc_iarpIntent) select 0;
    private _e = _okU getOrDefault [_lg, [0, 0]];
    _okU set [_lg, [(_e select 0) + (parseNumber (_got isEqualTo _want)), (_e select 1) + 1]];
    if (_got isNotEqualTo _want) then { _missU pushBack format ["« %1 » : %2 au lieu de %3", _txt, _got, _want]; };
    if (_got in ["bribe", "threat", "confront"] && {_got isNotEqualTo _want}) then { _danger pushBack format ["(jamais vue) « %1 » -> %2", _txt, _got]; };
} forEach _unseen;
private _rateU = { params ["_lg"]; (_okU getOrDefault [_lg, [0, 0]]) params ["_o", "_n"]; [round ((_o / (_n max 1)) * 1000) / 10, _n] };
(["fr"] call _rateU) params ["_uFr", "_unFr"];
(["en"] call _rateU) params ["_uEn", "_unEn"];
_info pushBack format ["phrases jamais vues : %1 %% en français (%2 phrases), %3 %% en anglais (%4 phrases)", _uFr, _unFr, _uEn, _unEn];
if (_unFr < 200 || {_unEn < 100}) then { _ko pushBack format ["phrases jamais vues : %1 FR et %2 EN (200 et 100 attendues)", _unFr, _unEn]; };
if (_uFr < 85 || {_uEn < 85}) then { _ko pushBack format ["phrases jamais vues : %1 %% FR, %2 %% EN (85 %% attendus) ; %3", _uFr, _uEn, (_missU select [0, 6]) joinString " ; "]; };
if ((_danger select { (_x find "(jamais vue)") >= 0 }) isNotEqualTo []) then { _ko pushBack format ["faux positifs dangereux (jamais vues) : %1", (_danger select { (_x find "(jamais vue)") >= 0 }) joinString " ; "]; };
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
if !(["talk", "identity", "cover", "presence", "search", "orders", "pressure", "bribe", "confront", "intel", "local", "relations", "personal", "rules", "offtopic", "answer", "rp"] isEqualTo (_themes select [0, 17])) then { _ko pushBack format ["guide : thèmes %1", _themes]; };
private _nI = count ((["lexicon"] call comspec_sse_fnc_iarpData) select { ((_x select 0) select [0, 1]) isNotEqualTo "_" });
if (_nI < 114) then { _ko pushBack format ["lexique : %1 intentions (114 attendues, trois fois celles de la 4.3.0)", _nI]; };
_info pushBack format ["lexique : %1 intentions", _nI];
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
// Émotions (fn_iarpProfile « emoActive ») : réglage global, réglage de l'IA qui l'emporte, tons écartés, profil sérialisé.
// Réglages globaux posés le temps du contrôle (sur cette machine seulement), puis remis.
private _gVars = ["comspec_sse_iarp_emoOn"] + ((["emoIds"] call comspec_sse_fnc_iarpProfile) apply { format ["comspec_sse_iarp_emo_%1", _x] });
private _gOld = _gVars apply { missionNamespace getVariable [_x, true] };
{ missionNamespace setVariable [_x, true]; } forEach _gVars;
private _pAuto = ["defaults"] call comspec_sse_fnc_iarpProfile;
if ((count (["emoActive", _pAuto] call comspec_sse_fnc_iarpProfile)) isNotEqualTo 6) then { _ko pushBack "émotions : un profil « auto » n'a pas les 6 émotions du réglage global"; };
missionNamespace setVariable ["comspec_sse_iarp_emo_armes", false];
if (["emoHas", [_pAuto, "armes"]] call comspec_sse_fnc_iarpProfile) then { _ko pushBack "émotions : peur des armes décochée au global, toujours active"; };
private _pCop = ["normalize", createHashMapFromArray [["emotions", ["peur", "armes", "mefiance"]]]] call comspec_sse_fnc_iarpProfile;
if !(["emoHas", [_pCop, "armes"]] call comspec_sse_fnc_iarpProfile) then { _ko pushBack "émotions : le réglage de l'IA ne l'emporte pas sur le global"; };
missionNamespace setVariable ["comspec_sse_iarp_emo_armes", true];
missionNamespace setVariable ["comspec_sse_iarp_emoOn", false];
if ((["emoActive", _pAuto] call comspec_sse_fnc_iarpProfile) isNotEqualTo []) then { _ko pushBack "émotions : interrupteur global coupé, émotions toujours actives"; };
if ((["emoActive", _pCop] call comspec_sse_fnc_iarpProfile) isNotEqualTo ["peur", "armes", "mefiance"]) then { _ko pushBack "émotions : interrupteur global coupé, réglage de l'IA perdu"; };
missionNamespace setVariable ["comspec_sse_iarp_emoOn", true];
private _pNeutral = ["normalize", createHashMapFromArray [["fear", 95], ["nerve", 90], ["aggro", 90], ["honesty", 10], ["emotionsOn", false]]] call comspec_sse_fnc_iarpProfile;
private _toneN = (["howareyou", _pNeutral, createHashMapFromArray [["tension", 90], ["aimed", 2], ["state", "rendu"], ["tone", "agressif"]], []] call comspec_sse_fnc_iarpGen) select 3;
if (_toneN in ["effraye", "nerveux", "agressif", "mefiant"]) then { _ko pushBack format ["émotions : IA neutre au ton « %1 »", _toneN]; };
// Sans peur des armes : braquée sans autre menace, un peureux reste calme ; braqué avec la peur des armes, il a peur.
private _pNoGun = ["normalize", createHashMapFromArray [["fear", 95], ["nerve", 80], ["aggro", 0], ["emotions", ["peur", "nervosite", "colere", "mefiance", "soulagement"]]]] call comspec_sse_fnc_iarpProfile;
private _toneG = (["howareyou", _pNoGun, createHashMapFromArray [["tension", 5], ["aimed", 1]], []] call comspec_sse_fnc_iarpGen) select 3;
if (_toneG in ["effraye", "nerveux"]) then { _ko pushBack format ["émotions : sans peur des armes, un peureux braqué répond « %1 »", _toneG]; };
private _pGun = ["normalize", createHashMapFromArray [["fear", 95], ["nerve", 80], ["aggro", 0]]] call comspec_sse_fnc_iarpProfile;
private _toneG2 = (["howareyou", _pGun, createHashMapFromArray [["tension", 5], ["aimed", 1]], []] call comspec_sse_fnc_iarpGen) select 3;
if !(_toneG2 in ["effraye", "nerveux"]) then { _ko pushBack format ["émotions : avec la peur des armes, un peureux braqué répond « %1 »", _toneG2]; };
// Profil : émotions gardées à la sérialisation ; noWeaponFear écrit (synchro web) et relu.
private _rawE = ["serialize", _pNoGun] call comspec_sse_fnc_iarpProfile;
private _pE = ["parse", _rawE] call comspec_sse_fnc_iarpProfile;
if ((_pE get "emotions") isNotEqualTo (_pNoGun get "emotions") || {(_rawE find """noWeaponFear"",true") < 0}) then { _ko pushBack format ["émotions : perdues à la sérialisation (%1)", _pE get "emotions"]; };
private _pN2 = ["parse", ["serialize", _pNeutral] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile;
if (_pN2 get "emotionsOn") then { _ko pushBack "émotions : interrupteur « Émotions » perdu à la sérialisation"; };
private _pW = ["normalize", createHashMapFromArray [["noWeaponFear", true]]] call comspec_sse_fnc_iarpProfile;
if ((_pW get "emotions") isNotEqualTo ["peur", "nervosite", "colere", "mefiance", "soulagement"]) then { _ko pushBack format ["émotions : noWeaponFear importé mal lu %1", _pW get "emotions"]; };
{ missionNamespace setVariable [_x, _gOld select _forEachIndex]; } forEach _gVars;
_info pushBack format ["émotions : neutre « %1 », sans peur des armes « %2 », avec « %3 »", _toneN, _toneG, _toneG2];
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
if ((count _intents) < 150) then { _ko pushBack format ["banque : %1 répliques (150 attendues)", count _intents]; };
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

// ---------------------------------------------------------------- cadre : une IA ne parle que de ce qu'elle sait
// Civil sans savoir (« Rien de sensible » par défaut) : pour chaque question de renseignement du banc d'essai et des phrases
// jamais vues, la réplique que le serveur choisirait (intelNo, variante de la question, thème interdit : offLimits) ; AUCUNE
// des phrases candidates (pas seulement celle tirée) ne contient un terme de scénario.
private _intelSubs = ["seenArmed", "seenVehicles", "strangers", "explosives", "mines", "whereIs", "leader", "howMany", "whenPassed", "happened",
    "heard", "whereWent", "describe", "cache", "base", "checkpoints", "safety", "problems", "roadSafe", "knowPerson", "threatened"];
private _watch = ["arme", "armes", "armee", "armees", "pick", "pickup", "pickups", "mine", "mines", "minee", "minees", "explosif", "explosifs", "explosive",
    "explosives", "bombe", "bombes", "bomb", "bombs", "insurge", "insurges", "insurgent", "insurgents", "rebelle", "rebelles", "rebel", "rebels", "combattant",
    "combattants", "fighters", "milice", "militia", "cache", "caches", "stash", "technique", "technical", "embuscade", "ambush", "attentat", "ied",
    "detonateur", "detonator", "grenade", "grenades", "weapon", "weapons", "gun", "guns", "armed", "rifle", "fusil", "terroriste", "terroristes", "terrorist"];
private _civ = ["normalize", createHashMapFromArray [["role", "civil"]]] call comspec_sse_fnc_iarpProfile;
(["frame", _civ] call comspec_sse_fnc_iarpAnswers) params ["_lvlC", "_forbidC"];
if (_lvlC isNotEqualTo "none") then { _ko pushBack format ["cadre : civil sans savoir au niveau %1 (« none » attendu)", _lvlC]; };
private _ctxC = createHashMapFromArray [["forbid", _forbidC], ["tension", 0], ["aimed", 0]];
private _varsC = createHashMapFromArray [["sir", ["monsieur", "sir"]], ["player", "Martin"]];
private _qs = (_corpus + _unseen) select { (_x select 2) in (_intelSubs + ["intel"]) };
private _bad = [];
private _seenKeys = createHashMap;
{
    private _got = ([_x select 1] call comspec_sse_fnc_iarpIntent) select 0;
    private _gi = [_got, "intelNo"] select (_got in (_intelSubs + ["intel"]));
    private _gk = format ["%1.%2", _gi, _got];
    if (_gk in _bank) then { _gi = _gk; };
    if !(_gi in _seenKeys) then {
        private _pool = [_gi, _civ, _ctxC, [], _varsC, "pool"] call comspec_sse_fnc_iarpGen;
        private _hits = [];
        {
            private _l = _x;
            { private _toks = (_x call comspec_sse_fnc_iarpNorm) splitString " "; { if (_x in _toks) then { _hits pushBackUnique _x; }; } forEach _watch; } forEach [_l select 0, _l param [1, ""]];
        } forEach _pool;
        _seenKeys set [_gi, _hits];
    };
    private _h = _seenKeys get _gi;
    if (_h isNotEqualTo []) then { _bad pushBack format ["« %1 » (%2) : %3", _x select 1, _gi, _h joinString ", "]; };
} forEach _qs;
if ((count _qs) < 100) then { _ko pushBack format ["cadre : %1 questions de renseignement (100 attendues)", count _qs]; };
if (_bad isNotEqualTo []) then { _ko pushBack format ["cadre : civil sans savoir, terme de scénario dans %1 réponse(s) : %2", count _bad, (_bad select [0, 4]) joinString " ; "]; };
_info pushBack format ["cadre : civil sans savoir, %1 questions de renseignement, %2 réplique(s) avec un terme de scénario", count _qs, count _bad];
// Phrases étiquetées (« hint », thèmes) : jamais chez « Rien de sensible » ; « Libre » les garde.
// La seule phrase « armed » d'intelNo est du ton « effraye » (banque) : ton imposé, sinon un civil calme ne l'a jamais
// dans ses candidates, quel que soit le cadre.
private _intelNoC = ["intelNo", _civ, _ctxC, [], _varsC, "pool"] call comspec_sse_fnc_iarpGen;
private _intelNoCe = ["intelNo", _civ, createHashMapFromArray [["forbid", _forbidC], ["tone", "effraye"]], [], _varsC, "pool"] call comspec_sse_fnc_iarpGen;
if (((_intelNoC + _intelNoCe) findIf { (_x param [2, []]) isNotEqualTo [] }) >= 0) then { _ko pushBack "cadre : phrase étiquetée chez un civil sans savoir"; };
private _free = ["normalize", createHashMapFromArray [["role", "civil"], ["evoke", "free"]]] call comspec_sse_fnc_iarpProfile;
(["frame", _free] call comspec_sse_fnc_iarpAnswers) params ["", "_forbidF"];
private _intelNoF = ["intelNo", _free, createHashMapFromArray [["forbid", _forbidF], ["tone", "effraye"]], [], _varsC, "pool"] call comspec_sse_fnc_iarpGen;
private _intelNoFc = ["intelNo", _free, createHashMapFromArray [["forbid", _forbidF]], [], _varsC, "pool"] call comspec_sse_fnc_iarpGen;
if ((_intelNoF findIf { "armed" in (_x param [2, []]) }) < 0 || {(_intelNoFc findIf { "hint" in (_x param [2, []]) }) < 0}) then { _ko pushBack "cadre : « Libre » écarte encore les phrases étiquetées"; };
// Thème interdit : « Explosifs » coché même en « Libre ».
private _banP = ["normalize", createHashMapFromArray [["role", "insurge"], ["evoke", "free"], ["banned", ["explosives", "inconnu"]]]] call comspec_sse_fnc_iarpProfile;
(["frame", _banP] call comspec_sse_fnc_iarpAnswers) params ["_lvlB", "_forbidB", "_bannedB"];
if (_bannedB isNotEqualTo ["explosives"] || {!("explosives" in _forbidB)} || {_lvlB isNotEqualTo "free"}) then { _ko pushBack format ["cadre : thèmes interdits %1 / %2", _bannedB, _forbidB]; };
if (((["topics"] call comspec_sse_fnc_iarpData) getOrDefault ["mines", ""]) isNotEqualTo "explosives") then { _ko pushBack "cadre : « mines » n'a pas le thème explosives"; };
// Le même civil avec un savoir et un lieu : il le donne quand il a confiance (palier précis), avec la position.
private _knowP = ["normalize", createHashMapFromArray [["role", "civil"], ["know", format ["Précis : Trois hommes dorment à la ferme.%1Lieu : [4512, 7820] Ferme abandonnée", _nl]]]] call comspec_sse_fnc_iarpProfile;
(["frame", _knowP] call comspec_sse_fnc_iarpAnswers) params ["_lvlK", "", "", "_knowsK"];
(["knowTiers", _knowP get "know"] call comspec_sse_fnc_iarpProfile) params ["_tK", "_posK", "_labK"];
private _lvK = ["intelLevel", [_knowP, 70, false, 0, false, false, "", 0, 0]] call comspec_sse_fnc_iarpProfile;
if (_lvlK isNotEqualTo "known" || {!_knowsK} || {_lvK < 2} || {(_tK select 1) isEqualTo ""} || {_posK isNotEqualTo [4512, 7820]} || {_labK isNotEqualTo "Ferme abandonnée"}) then {
    _ko pushBack format ["cadre : civil avec savoir %1 %2 palier %3 lieu %4 %5", _lvlK, _knowsK, _lvK, _posK, _labK];
};
private _lvK0 = ["intelLevel", [_knowP, 20, false, 0, false, false, "", 0, 0]] call comspec_sse_fnc_iarpProfile;
if (_lvK0 >= 2) then { _ko pushBack "cadre : civil avec savoir le donne sans confiance"; };

// ---------------------------------------------------------------- réponses écrites par le maître du jeu (fn_iarpAnswers)
private _ansIn = [
    ["name", "", [], "random", [["*", ["Je suis {Prénom}, le boulanger. Tout le monde me connaît ici."]], ["fear", ["Je… je suis {prenom} !"]], ["bidon", ["x"]]], 0, ""],
    ["seenArmed", "", [], "seq", [["*", ["Hier soir, trois pick-up vers le nord.", "Ils étaient six."]]], 2, "[4512, 7820] Ferme"],
    ["c1", "Mot de passe", ["mot de passe", "password"], "random", [["*", ["Le faucon vole bas."]]], 0, ""],
    ["c2", "Sans déclencheur", [], "random", [["*", ["x"]]], 0, ""],
    ["papers", "", [], "random", [], 0, ""],
    ["job", "", [], "random", [["*", [format ["Ligne%1coupée <b>&", toString [10]]]]], 9, ""]
];
private _ans = ["normalize", _ansIn] call comspec_sse_fnc_iarpAnswers;
if (((_ans apply { _x select 0 }) isNotEqualTo ["name", "seenArmed", "c1", "job"])) then { _ko pushBack format ["réponses : validation %1", _ans apply { _x select 0 }]; };
private _eName = ["get", [_ans, "name"]] call comspec_sse_fnc_iarpAnswers;
if (((_eName select 4) apply { _x select 0 }) isNotEqualTo ["*", "fear"]) then { _ko pushBack "réponses : situation inconnue gardée"; };
if (((["get", [_ans, "job"]] call comspec_sse_fnc_iarpAnswers) param [5, -1]) isNotEqualTo 3) then { _ko pushBack "réponses : palier non borné"; };
if ((((((["get", [_ans, "job"]] call comspec_sse_fnc_iarpAnswers) select 4) select 0) select 1) select 0) find (toString [10]) >= 0) then { _ko pushBack "réponses : retour à la ligne gardé"; };
private _tpl = ["vars", "Je suis {Prénom}, de {ville}. {joueur}, il est {heure}. {inconnue}"] call comspec_sse_fnc_iarpAnswers;
if (_tpl isNotEqualTo "Je suis {first}, de {city}. {player}, il est {time}. {inconnue}") then { _ko pushBack format ["réponses : variables %1", _tpl]; };
if ((["unknownVars", "{prenom} {inconnue}"] call comspec_sse_fnc_iarpAnswers) isNotEqualTo ["inconnue"]) then { _ko pushBack "réponses : variable inconnue non signalée"; };
private _filled = [_tpl, createHashMapFromArray [["first", "Nikos"], ["city", "Kavala"], ["player", "Martin"], ["time", "18:05"]], "fr"] call comspec_sse_fnc_iarpFill;
if (_filled isNotEqualTo "Je suis Nikos, de Kavala. Martin, il est 18:05. …") then { _ko pushBack format ["réponses : rendu %1", _filled]; };
private _pk1 = ["pick", [_eName, ["calm", "first"], [0, ""]]] call comspec_sse_fnc_iarpAnswers;
private _pk2 = ["pick", [_eName, ["fear", "threat", "again"], [0, ""]]] call comspec_sse_fnc_iarpAnswers;
if ((_pk1 param [1, ""]) isNotEqualTo "*" || {(_pk2 param [1, ""]) isNotEqualTo "fear"}) then { _ko pushBack format ["réponses : situation %1 / %2", _pk1, _pk2]; };
private _eArm = ["get", [_ans, "seenArmed"]] call comspec_sse_fnc_iarpAnswers;
private _s1 = ["pick", [_eArm, [], [0, ""]]] call comspec_sse_fnc_iarpAnswers;
private _s2 = ["pick", [_eArm, [], [_s1 select 2, ""]]] call comspec_sse_fnc_iarpAnswers;
private _s3 = ["pick", [_eArm, [], [_s2 select 2, ""]]] call comspec_sse_fnc_iarpAnswers;
if ([_s1 select 0, _s2 select 0, _s3 select 0] isNotEqualTo ["Hier soir, trois pick-up vers le nord.", "Ils étaient six.", "Ils étaient six."]) then { _ko pushBack "réponses : ordre « dans l'ordre »"; };
{
    _x params ["_txt", "_want"];
    private _got = ["match", [_ans, _txt call comspec_sse_fnc_iarpNorm]] call comspec_sse_fnc_iarpAnswers;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["réponses : question perso « %1 » -> « %2 »", _txt, _got]; };
} forEach [["Quel est le mot de passe ?", "c1"], ["Le MOT DE PASSE, vite.", "c1"], ["What's the password?", "c1"], ["Passe-moi le mot.", ""], ["Vos papiers.", ""]];
if ((["esc", "<t>&"] call comspec_sse_fnc_iarpAnswers) isNotEqualTo "&lt;t&gt;&amp;") then { _ko pushBack "réponses : échappement"; };
if ((["newKey", _ans] call comspec_sse_fnc_iarpAnswers) isNotEqualTo "c2") then { _ko pushBack "réponses : clé libre"; };
// Profil : réponses, cadre et thèmes gardés après sérialisation (attribut Eden, import / export).
private _pA = ["normalize", createHashMapFromArray [["name", "Nikos KARRAS"], ["answers", _ansIn], ["evoke", "known"], ["banned", ["drugs"]]]] call comspec_sse_fnc_iarpProfile;
private _pB = ["parse", ["serialize", _pA] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile;
if ((_pB get "answers") isNotEqualTo (_pA get "answers") || {(_pB get "evoke") isNotEqualTo "known"} || {(_pB get "banned") isNotEqualTo ["drugs"]}) then { _ko pushBack "réponses : perdues à la sérialisation du profil"; };
_info pushBack format ["réponses : %1 entrée(s) valides sur %2, « %3 »", count _ans, count _ansIn, _filled];
// ---------------------------------------------------------------- question / réponse simple (attribut Eden, atelier, Zeus)
// Une question perso du même format que la fenêtre des réponses ; une paraphrase proche la déclenche.
private _qaQ = "Où est le chef du village ?";
private _qaR = format ["Le chef est à la mosquée, il revient ce soir.%1Demandez à {prenom}, il sait tout.", _nl];
private _qa = ["qaSet", [[], "", _qaQ, _qaR]] call comspec_sse_fnc_iarpAnswers;
private _qaE = ["get", [_qa, "c1"]] call comspec_sse_fnc_iarpAnswers;
if (_qaE isEqualTo [] || {(_qaE select 1) isNotEqualTo _qaQ} || {((_qaE select 4) select 0) isNotEqualTo ["*", ["Le chef est à la mosquée, il revient ce soir.", "Demandez à {prenom}, il sait tout."]]}) then {
    _ko pushBack format ["question / réponse : entrée %1", _qaE];
};
if ((["normalize", _qa] call comspec_sse_fnc_iarpAnswers) isNotEqualTo _qa) then { _ko pushBack "question / réponse : hors du format des réponses"; };
{
    _x params ["_txt", "_want"];
    private _got = ["match", [_qa, _txt call comspec_sse_fnc_iarpNorm]] call comspec_sse_fnc_iarpAnswers;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["question / réponse : « %1 » -> « %2 »", _txt, _got]; };
} forEach [["Où est le chef du village ?", "c1"], ["Le chef du village, il est où ?", "c1"], ["Vous savez où se trouve le chef du village ?", "c1"],
    ["ou est le CHEF du village", "c1"], ["Où est le marché ?", ""], ["Vos papiers.", ""]];
private _qaPk = ["pick", [_qaE, ["calm", "first"], [0, ""]]] call comspec_sse_fnc_iarpAnswers;
if !((_qaPk param [0, ""]) in ["Le chef est à la mosquée, il revient ce soir.", "Demandez à {prenom}, il sait tout."]) then { _ko pushBack format ["question / réponse : réplique %1", _qaPk]; };
(["qaGet", _qa] call comspec_sse_fnc_iarpAnswers) params ["_qgK", "_qgQ", "_qgR", "_qgN"];
if ([_qgK, _qgQ, _qgR, _qgN] isNotEqualTo ["c1", _qaQ, _qaR, 1]) then { _ko pushBack format ["question / réponse : relue %1", [_qgK, _qgQ, _qgR, _qgN]]; };
// Réponse retouchée au même endroit : situations avancées, palier et déclencheurs retouchés gardés ; question seule : rien
// ne change ; question et réponse vidées : la question perso sort, les autres restent.
private _qaAdv = [["name", "", [], "random", [["*", ["Je suis {prenom}."]]], 0, ""], ["c1", _qaQ, ["chef"], "seq", [["*", ["x"]], ["fear", ["Pitié !"]]], 2, ""]];
private _qa2 = ["qaSet", [_qaAdv, "c1", _qaQ, "Il est parti."]] call comspec_sse_fnc_iarpAnswers;
private _qa2E = ["get", [_qa2, "c1"]] call comspec_sse_fnc_iarpAnswers;
if ((_qa2 apply { _x select 0 }) isNotEqualTo ["name", "c1"] || {(_qa2E select 2) isNotEqualTo ["chef"]} || {(_qa2E select 3) isNotEqualTo "seq"} || {(_qa2E select 5) isNotEqualTo 2}
    || {(_qa2E select 4) isNotEqualTo [["*", ["Il est parti."]], ["fear", ["Pitié !"]]]}) then { _ko pushBack format ["question / réponse : retouche %1", _qa2E]; };
if ((["qaSet", [_qaAdv, "c1", "Autre question ?", ""]] call comspec_sse_fnc_iarpAnswers) isNotEqualTo _qaAdv) then { _ko pushBack "question / réponse : question seule enregistrée"; };
if (((["qaSet", [_qaAdv, "c1", "", ""]] call comspec_sse_fnc_iarpAnswers) apply { _x select 0 }) isNotEqualTo ["name"]) then { _ko pushBack "question / réponse : vidée mais gardée"; };
// Aller-retour par le profil (attribut Eden) avec un savoir et un déblocage d'avant : rien ne se perd.
private _qaP = ["normalize", createHashMapFromArray [["know", format ["Vague : Des hommes la nuit.%1Précis : À la ferme.", _nl]], ["unlock", "keyword"], ["unlockWord", "faucon"], ["answers", _qa]]] call comspec_sse_fnc_iarpProfile;
private _qaP2 = ["parse", ["serialize", _qaP] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile;
if ((_qaP2 get "answers") isNotEqualTo _qa || {(_qaP2 get "know") isNotEqualTo (_qaP get "know")} || {(_qaP2 get "unlock") isNotEqualTo "keyword"} || {(_qaP2 get "unlockWord") isNotEqualTo "faucon"}) then {
    _ko pushBack "question / réponse : perdue (ou savoir perdu) à la sérialisation du profil";
};
// Mission d'avant (profil sans réponses, savoir d'une phrase, déblocage « Pot-de-vin ») : chargée telle quelle, aucune question.
private _old = ["parse", '[["name","Nikos KARRAS"],["know","Les armes sont sous la grange."],["unlock","bribe"],["unlockWord",""],["react",[["order","refuse"]]]]'] call comspec_sse_fnc_iarpProfile;
if ((_old get "know") isNotEqualTo "Les armes sont sous la grange." || {(_old get "unlock") isNotEqualTo "bribe"} || {((_old get "react") get "order") isNotEqualTo "refuse"}
    || {(["qaGet", _old get "answers"] call comspec_sse_fnc_iarpAnswers) isNotEqualTo ["", "", "", 0]}) then { _ko pushBack "question / réponse : ancienne mission mal relue"; };
// Textes des nouveaux champs (FR et EN dans stringtable.xml : ici, au moins présents dans la langue du jeu).
{ if !(isLocalized ("STR_COMSPEC_IARP_" + _x)) then { _ko pushBack format ["question / réponse : texte %1 absent", _x]; }; } forEach
    ["W_Question", "W_Question_D", "W_QuestionHint", "W_QuestionMore", "W_Reply", "W_Reply_D", "W_ReplyHint", "W_ReplyVar", "W_ReplyVar_D", "W_QaHalf",
     "W_Adv", "W_AdvHide", "W_Adv_D", "W_TestCustom", "Z_Qa"];
_info pushBack format ["question / réponse : déclencheurs %1", _qaE param [2, []]];
// Coût : message long, fautes, plusieurs demandes (index des motifs : quelques dizaines cherchés par morceau).
private _t1 = diag_tickTime;
for "_i" from 1 to 20 do { ["Bonjour, vous avez vu des hommes armés ou des pick-up cette nuit ? Où sont-ils partis ? Combien étaient-ils, et qui commande ici ?"] call comspec_sse_fnc_iarpIntent; };
_info pushBack format ["interprète : %1 ms par message de quatre questions", round (((diag_tickTime - _t1) / 20) * 10000) / 10];
// Suivi d'un joueur : table de décision (pure), ordres qui le lancent et le terminent, répliques.
{
    _x params ["_d", "_ls", "_mov", "_wMv", "_wPace"];
    private _r = ["step", [_d, _ls, _mov]] call comspec_sse_fnc_iarpFollow;
    if (_r isNotEqualTo [_wMv, _wPace]) then { _ko pushBack format ["suivi : à %1 m, joueur à %2 km/h, %3 -> %4 au lieu de %5", _d, _ls, ["à l'arrêt", "en mouvement"] select _mov, _r, [_wMv, _wPace]]; };
} forEach [
    [1.5, 0, false, false, "walk"], [3, 0, false, false, "walk"], [3, 0, true, true, "walk"], [2.4, 0, true, false, "walk"], [4.5, 0, false, true, "walk"],
    [5, 5, true, true, "walk"], [5, 10, true, true, "jog"], [8, 0, false, true, "jog"], [5, 18, true, true, "run"], [20, 0, false, true, "run"], [3.5, 18, false, false, "run"]
];
{
    _x params ["_txt", "_want"];
    private _got = ([_txt] call comspec_sse_fnc_iarpIntent) select 0;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["suivi : « %1 » compris %2 au lieu de %3", _txt, _got, _want]; };
} forEach [["Suivez-moi.", "follow"], ["Suis-moi", "follow"], ["Follow me.", "follow"], ["Reste ici.", "halt"], ["Restez ici.", "halt"], ["Stay here.", "halt"], ["Va-t'en.", "leave"], ["Circulez.", "leave"]];
{ if !(_x in _bank) then { _ko pushBack format ["suivi : répliques « %1 » absentes de la banque", _x]; }; } forEach ["comply.follow", "noComply.follow", "followCuffed"];
if ((["block", objNull] call comspec_sse_fnc_iarpFollow) isNotEqualTo "dead") then { _ko pushBack "suivi : une IA absente n'est pas refusée"; };
// Agressivité : paliers, plafonds de dégâts, moteur de corps à corps, répliques (pur, tables data\iarp_aggro.sqf).
{
    _x params ["_anger", "_mode", "_trait", "_want"];
    private _got = ["stage", [_anger, _mode, _trait]] call comspec_sse_fnc_iarpAggro;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["agressivité : colère %1, niveau %2, trait %3 -> %4 au lieu de %5", _anger, _mode, _trait, _got, _want]; };
} forEach [
    [100, "off", "hostile", "calm"], [10, "physical", "none", "calm"], [25, "verbal", "none", "irritated"], [45, "verbal", "none", "hostile"],
    [95, "verbal", "hostile", "hostile"], [65, "physical", "none", "shove"], [85, "physical", "none", "punch"], [100, "physical", "bluff", "hostile"]
];
{
    _x params ["_args", "_want"];
    private _got = ["damage", _args] call comspec_sse_fnc_iarpAggro;
    if ((abs (_got - _want)) > 0.0005) then { _ko pushBack format ["agressivité : dégâts %1 -> %2 au lieu de %3", _args, _got, _want]; };
} forEach [
    [["punch", 50, false, 0, 0], 0.08], [["punch", 100, false, 0, 0], 0.16], [["punch", 50, false, 0.28, 0], 0.02], [["punch", 50, false, 0, 0.58], 0.02],
    [["punch", 50, true, 0, 0.58], 0.08], [["punch", 0, false, 0, 0], 0], [["shove", 50, false, 0.3, 0], 0], [["punch", 100, false, 0, 0.6], 0]
];
{
    _x params ["_args", "_want"];
    if ((["backend", _args] call comspec_sse_fnc_iarpAggro) isNotEqualTo _want) then { _ko pushBack format ["agressivité : moteur %1 -> pas %2", _args, _want]; };
} forEach [[["auto", true], "tsp"], [["auto", false], "sse"], [["sse", true], "sse"], [["tsp", false], "sse"], [["tsp", true], "tsp"]];
if ((abs ((["gain", ["insulted", "hostile"]] call comspec_sse_fnc_iarpAggro) - 37.5)) > 0.01) then { _ko pushBack "agressivité : une insulte à un hostile ne vaut pas 37,5"; };
if ((["decay", [50, 60, "none"]] call comspec_sse_fnc_iarpAggro) isNotEqualTo 30) then { _ko pushBack "agressivité : la colère ne retombe pas de 20 par minute"; };
private _hl = ["lines"] call comspec_sse_fnc_iarpAggro;
private _hCells = 0;
{
    private _lv = _x;
    {
        private _pe = _x;
        private _n = 0;
        {
            if ((_x select 0) isEqualTo _lv && {(_x select 1) isEqualTo _pe}) then {
                { if ((_x param [0, ""]) isEqualTo "" || {(_x param [1, ""]) isEqualTo ""}) then { _ko pushBack format ["agressivité : réplique sans français ou sans anglais (%1, %2)", _lv, _pe]; }; } forEach (_x select 2);
                _n = _n + count (_x select 2);
            };
        } forEach (_hl getOrDefault ["lines", []]);
        _hCells = _hCells + 1;
        if (_n < 3) then { _ko pushBack format ["agressivité : %1 réplique(s) seulement pour %2 / %3 (3 attendues)", _n, _lv, _pe]; };
    } forEach (_hl getOrDefault ["personas", []]);
} forEach (_hl getOrDefault ["levels", []]);
if (_hCells < 42) then { _ko pushBack format ["agressivité : %1 cases palier × caractère (42 attendues)", _hCells]; };
private _hSaid = [];
for "_i" from 1 to 6 do {
    private _l = ["pick", ["hostile", "irritable", "insulted", "street", "FRA", _hSaid]] call comspec_sse_fnc_iarpAggro;
    if (_l isEqualTo [] || {(_l select 0) in _hSaid}) then { _ko pushBack "agressivité : réplique redite ou absente en 6 tirages"; } else { _hSaid pushBack (_l select 0); };
};
_info pushBack format ["agressivité : %1 cases de répliques, %2 répliques tirées sans redite", _hCells, count _hSaid];
// Couche hors sujet (fn_iarpOffTopic « selfTest ») : grossièretés, insultes, charabia, relance, tutoiement, formes courtes.
(["selfTest"] call comspec_sse_fnc_iarpOffTopic) params [["_koO", []], ["_infoO", []]];
_ko append _koO;
_info append _infoO;
[_ko, _info]
