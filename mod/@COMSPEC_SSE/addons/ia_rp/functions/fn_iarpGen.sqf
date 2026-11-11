/*
    Générateur de répliques (pur, toute machine, Eden compris) : grammaire procédurale locale, sans modèle de langage
    ni réseau. Choisit une phrase de la banque (data\iarp_bank.sqf) selon l'intention et le ton, puis l'habille :
    ouverture, hésitation, didascalie de contexte, fin, phrase type de la personne, langue du pays.
    [intention, profil, contexte, déjà dit, variables, mode] call comspec_sse_fnc_iarpGen
      -> [modèle fr, modèle en, langue locale, ton, phrase de base fr, phrase de base en]
      mode (facultatif) "pool" -> les phrases de base candidates ([[fr, en, étiquettes]…]) après tous les filtres, sans tirage
                (banc d'essai : aucune ne doit citer un élément de scénario que l'IA ignore)
      profil    HashMap (fn_iarpProfile) : nerve, talk, aggro, honesty, bribe, fear, drunk (0-100), lang, country, phrases
      contexte  HashMap : state (automate des IA : nerveux, refuse, rendu…), tension (0-100), tone (ton imposé), aimed
                (armes pointées, vues même sans peur des armes), emo (émotions actives ; absent : lues dans le profil,
                fn_iarpProfile « emoActive »), wounded, tired, vehicle, night, checkpoint (booléens), country (pays des papiers),
                plain (vrai : phrase de base seule, sans habillage ; réplique suivie d'une autre dans la même bulle),
                told (colleague | other : sujet déjà raconté à un autre joueur, « Je l'ai déjà dit à votre collègue. »),
                forbid (étiquettes interdites : « hint », armed, explosives, military, wanted, drugs, politics ; cadre fixé par
                le maître du jeu, fn_iarpAnswers « frame » : une phrase étiquetée ainsi n'est jamais choisie),
                preferFact (vrai : une phrase qui cite un fait connu de la couverture passe avant les autres),
                noMine (vrai : pas de phrase type de la personne à la fin ; ordre, refus, hors sujet)
      déjà dit  phrases de base déjà servies par cette personne : jamais redites tant qu'il en reste d'autres
      variables (facultatif) faits connus ({city}, {job}…) : une phrase qui cite un fait absent n'est pas choisie
    Ton venu du caractère (peur, nerfs) : seulement s'il y a une raison (tension, arme pointée, état) ; un peureux sans
    menace répond calmement.
    Les modèles gardent leurs variables ({city}…) : fn_iarpFill les remplace dans la langue de chaque joueur.
    Ton : imposé, sinon état de l'automate, sinon le trait de caractère le plus marqué.
*/
params [["_intent", "unknown", [""]], ["_p", createHashMap, [createHashMap]], ["_ctx", createHashMap, [createHashMap]], ["_recent", [], [[]]], ["_vars", nil], ["_mode", "", [""]]];
private _bank = ["bank"] call comspec_sse_fnc_iarpData;
private _num = { params ["_k", "_d"]; private _v = _p getOrDefault [_k, _d]; [_d, _v] select (_v isEqualType 0) };
private _nerve = ["nerve", 50] call _num;
private _talk = ["talk", 50] call _num;
private _aggro = ["aggro", 30] call _num;
private _honesty = ["honesty", 60] call _num;
private _bribe = ["bribe", 30] call _num;
private _fear = ["fear", 50] call _num;
private _drunk = ["drunk", 0] call _num;
private _st = _ctx getOrDefault ["state", ""];
private _tension = _ctx getOrDefault ["tension", 0];

// Émotions actives de cette IA (fn_iarpProfile « emoActive ») : un ton dont l'émotion est décochée n'est jamais pris
// (effrayé : peur ; nerveux : nervosité ; agressif : colère ; méfiant : méfiance), même imposé par une règle.
private _emo = _ctx getOrDefault ["emo", ["emoActive", _p] call comspec_sse_fnc_iarpProfile];
private _toneEmo = createHashMapFromArray [["effraye", "peur"], ["nerveux", "nervosite"], ["agressif", "colere"], ["mefiant", "mefiance"]];
private _toneOk = { params ["_t"]; private _e = _toneEmo getOrDefault [_t, ""]; _e isEqualTo "" || {_e in _emo} };
private _tone = _ctx getOrDefault ["tone", ""];
if !([_tone] call _toneOk) then { _tone = ""; };
if (_tone isEqualTo "") then {
    _tone = call {
        if ((_st isEqualTo "nerveux" || {_tension >= 60}) && {"nervosite" in _emo}) exitWith { "nerveux" };
        if (_st isEqualTo "refuse" && {"mefiance" in _emo}) exitWith { "mefiant" };
        if (_st isEqualTo "rendu" && {"peur" in _emo}) exitWith { "effraye" };
        if (_aggro >= 65 && {"colere" in _emo}) exitWith { "agressif" };
        // Peur et nerfs : un caractère, pas un état ; ils colorent la réponse quand quelque chose les réveille. Une arme
        // braquée ne réveille rien chez une IA sans peur des armes (elle la voit : didascalie « aimed » gardée).
        private _stir = _tension >= 25 || {(_ctx getOrDefault ["aimed", 0]) > 0 && {"armes" in _emo}} || {_st in ["ment", "refuse", "fuit", "agressif", "renforts", "nerveux", "rendu"]};
        if (_fear >= 75 && {_stir} && {"peur" in _emo}) exitWith { "effraye" };
        if (_nerve >= 65 && {_stir} && {"nervosite" in _emo}) exitWith { "nerveux" };
        if (_honesty <= 30 && {"mefiance" in _emo}) exitWith { "mefiant" };
        if (_talk >= 70) exitWith { "bavard" };
        if (_talk <= 30) exitWith { "laconique" };
        if (_bribe >= 75) exitWith { "corrompu" };
        "calme"
    };
};

// Phrase de base : celles du ton comptent double ; déjà dites écartées tant qu'il en reste.
private _sets = _bank getOrDefault [_intent, _bank getOrDefault ["unknown", createHashMap]];
private _pool = [];
{ _pool pushBack _x; _pool pushBack _x; } forEach (_sets getOrDefault [_tone, []]);
_pool append (_sets getOrDefault ["*", []]);
// Faits connus (variables du serveur) : une phrase qui cite un fait absent est écartée, s'il en reste d'autres.
if (!isNil "_vars" && {_vars isEqualType createHashMap}) then {
    private _has = {
        params ["_t"];
        private _ok = true;
        private _rest = _t;
        private _guard = 0;
        while { _ok && {_guard < 8} && {(_rest find "{") >= 0} } do {
            _guard = _guard + 1;
            private _a = _rest find "{";
            private _b = (_rest select [_a]) find "}";
            if (_b < 0) exitWith {};
            private _k = _rest select [_a + 1, _b - 1];
            private _v = _vars getOrDefault [_k, ""];
            if (_v isEqualType []) then { _v = _v param [0, ""]; };
            if (!(_k in ["sir", "player"]) && {!(_v isEqualType "") || {_v isEqualTo ""}}) then { _ok = false; };
            _rest = _rest select [_a + _b + 1];
        };
        _ok
    };
    private _kept = _pool select { [_x select 0] call _has };
    if (_kept isNotEqualTo []) then { _pool = _kept; };
    // Fait connu de la couverture : la phrase qui le cite passe avant les phrases génériques (« Je suis là depuis {since}. »).
    if (_ctx getOrDefault ["preferFact", false]) then {
        private _withFact = _pool select { (([[_x select 0, "{sir}", ""] call CBA_fnc_replace, "{player}", ""] call CBA_fnc_replace) find "{") >= 0 };
        if (_withFact isNotEqualTo []) then { _pool = _withFact; };
    };
};
// Cadre du maître du jeu (fn_iarpAnswers « frame ») : aucune phrase étiquetée d'un thème interdit, ni « hint » chez une IA
// qui ne sait rien. Rien ne reste : phrases neutres de « dunno » (jamais un détail inventé).
private _forbid = _ctx getOrDefault ["forbid", []];
if (_forbid isEqualType [] && {_forbid isNotEqualTo []}) then {
    private _okTags = { params ["_l"]; private _tg = _l param [2, []]; !(_tg isEqualType []) || {(_tg findIf { _x in _forbid }) < 0} };
    _pool = _pool select { [_x] call _okTags };
    if (_pool isEqualTo []) then { _pool = ((_bank getOrDefault ["dunno", createHashMap]) getOrDefault ["*", []]) select { [_x] call _okTags }; };
};
if (_mode isEqualTo "pool") exitWith { _pool };
if (_pool isEqualTo []) then { _pool = [["Je ne comprends pas.", "I don't understand."]]; };
private _fresh = _pool select { !((_x select 0) in _recent) };
// Toutes déjà dites : au moins pas une des 5 dernières répliques.
if (_fresh isEqualTo []) then { private _r5 = _recent select [((count _recent) - 5) max 0]; _fresh = _pool select { !((_x select 0) in _r5) }; };
if (_fresh isEqualTo []) then { _fresh = _pool; };
private _core = selectRandom _fresh;
private _fr = [_core select 0];
private _en = [_core param [1, _core select 0]];

private _pickIn = {
    params ["_cat", "_k"];
    private _l = (_bank getOrDefault [_cat, createHashMap]) getOrDefault [_k, []];
    if (_l isEqualTo []) then { [] } else { selectRandom _l }
};
private _pickTone = {
    params ["_cat"];
    private _e = [_cat, _tone] call _pickIn;
    if (_e isEqualTo [] || {(random 1) < 0.4}) then { private _g = [_cat, "*"] call _pickIn; if (_g isNotEqualTo []) then { _e = _g; }; };
    _e
};
private _pre = { params ["_e"]; if (_e isNotEqualTo []) then { _fr = [_e select 0] + _fr; _en = [_e param [1, _e select 0]] + _en; }; };
private _post = { params ["_e"]; if (_e isNotEqualTo []) then { _fr pushBack (_e select 0); _en pushBack (_e param [1, _e select 0]); }; };

// Habillage : jamais sur « je ne comprends pas » ni sur un silence ; à peine chez un laconique ; jamais sur une réplique
// suivie d'une autre dans la même bulle (contexte « plain » : « Bonjour. » avant la vraie réponse).
private _deco = !(_intent in ["unknown", "silent"]) && {_tone isNotEqualTo "laconique"} && {!(_ctx getOrDefault ["plain", false])};
if (_deco) then {
    // Hésitation : nerveux, effrayé, ivre.
    if ((_tone in ["nerveux", "effraye"] || {_drunk >= 50}) && {(random 1) < 0.45}) then {
        [[["_hes", "ivre"] call _pickIn, "_hes" call _pickTone] select (_drunk < 50 || {(random 1) < 0.5})] call _pre;
    } else {
        if ((random 1) < (0.1 + _talk / 300)) then { ["_open" call _pickTone] call _pre; };
    };
    // Didascalie : un seul détail du contexte, le plus visible.
    private _cues = [];
    if ((_ctx getOrDefault ["aimed", 0]) > 0) then { _cues pushBack ["aimed", 0.55]; };
    if (_ctx getOrDefault ["wounded", false]) then { _cues pushBack ["wounded", 0.5]; };
    if (_drunk >= 60) then { _cues pushBack ["drunk", 0.4]; };
    if (_ctx getOrDefault ["tired", false]) then { _cues pushBack ["tired", 0.25]; };
    if (_ctx getOrDefault ["vehicle", false]) then { _cues pushBack ["vehicle", 0.25]; };
    if ((_ctx getOrDefault ["night", false]) && {_intent in ["travel", "greet", "origin"]}) then { _cues pushBack ["night", 0.35]; };
    if ((_ctx getOrDefault ["checkpoint", false]) && {_intent in ["papers", "travel", "greet", "howareyou"]}) then { _cues pushBack ["checkpoint", 0.3]; };
    {
        _x params ["_k", "_pr"];
        if ((random 1) < _pr) exitWith { [["_stage", _k] call _pickIn] call _pre; };
    } forEach (_cues call BIS_fnc_arrayShuffle);
    // Fin : bavard plus souvent ; phrase type de la personne (atelier IA) de temps en temps.
    private _mine = (_p getOrDefault ["phrases", []]) select { _x isEqualType "" && {_x isNotEqualTo ""} };
    if (_mine isNotEqualTo [] && {!(_ctx getOrDefault ["noMine", false])} && {(random 1) < 0.22}) then {
        private _l = selectRandom _mine;
        [[_l, _l]] call _post;
    } else {
        if ((random 1) < (0.08 + _talk / 250)) then { ["_close" call _pickTone] call _post; };
    };
};

// Déjà dit à un autre joueur (fn_iarpMem « told ») : « Je l'ai déjà dit à votre collègue. » en tête, même sans habillage.
private _toldTo = _ctx getOrDefault ["told", ""];
if (_toldTo isNotEqualTo "") then { [["_told", _toldTo] call _pickIn] call _pre; };

// Langue du pays : une expression locale, la phrase traduite entre crochets (fn_iarpClient, si le joueur ne la parle pas).
private _local = "";
if ((_p getOrDefault ["lang", "common"]) isEqualTo "local") then {
    private _c = _p getOrDefault ["country", "auto"];
    if (_c isEqualTo "auto") then { _c = _ctx getOrDefault ["country", "*"]; };
    private _e = ["_local", _c] call _pickIn;
    // Pays réel du registre : expressions de sa langue (registre « speech »), sinon la phrase générique.
    if (_e isEqualTo [] && {!isNil "comspec_sse_fnc_idCountries"}) then {
        private _sp = (([] call comspec_sse_fnc_idCountries) getOrDefault [_c, createHashMap]) getOrDefault ["speech", []];
        if (_sp isNotEqualTo []) then { _e = [selectRandom _sp]; };
    };
    if (_e isEqualTo []) then { _e = ["_local", "*"] call _pickIn; };
    if (_e isNotEqualTo []) then { _local = _e select 0; };
};
[_fr joinString " ", _en joinString " ", _local, _tone, _core select 0, _core param [1, _core select 0]]
