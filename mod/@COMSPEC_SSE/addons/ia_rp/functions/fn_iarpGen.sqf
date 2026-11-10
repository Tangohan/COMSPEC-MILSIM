/*
    Générateur de répliques (pur, toute machine, Eden compris) : grammaire procédurale locale, sans modèle de langage
    ni réseau. Choisit une phrase de la banque (data\iarp_bank.sqf) selon l'intention et le ton, puis l'habille :
    ouverture, hésitation, didascalie de contexte, fin, phrase type de la personne, langue du pays.
    [intention, profil, contexte, déjà dit] call comspec_sse_fnc_iarpGen -> [modèle fr, modèle en, langue locale, ton, phrase de base]
      profil    HashMap (fn_iarpProfile) : nerve, talk, aggro, honesty, bribe, fear, drunk (0-100), lang, country, phrases
      contexte  HashMap : state (automate des IA : nerveux, refuse, rendu…), tension (0-100), tone (ton imposé), aimed
                (armes pointées), wounded, tired, vehicle, night, checkpoint (booléens), country (pays des papiers),
                plain (vrai : phrase de base seule, sans habillage ; réplique suivie d'une autre dans la même bulle),
                told (colleague | other : sujet déjà raconté à un autre joueur, « Je l'ai déjà dit à votre collègue. »)
      déjà dit  phrases de base déjà servies par cette personne : jamais redites tant qu'il en reste d'autres
    Les modèles gardent leurs variables ({city}…) : fn_iarpFill les remplace dans la langue de chaque joueur.
    Ton : imposé, sinon état de l'automate, sinon le trait de caractère le plus marqué.
*/
params [["_intent", "unknown", [""]], ["_p", createHashMap, [createHashMap]], ["_ctx", createHashMap, [createHashMap]], ["_recent", [], [[]]]];
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

private _tone = _ctx getOrDefault ["tone", ""];
if (_tone isEqualTo "") then {
    _tone = call {
        if (_st isEqualTo "nerveux" || {_tension >= 60}) exitWith { "nerveux" };
        if (_st isEqualTo "refuse") exitWith { "mefiant" };
        if (_st isEqualTo "rendu") exitWith { "effraye" };
        if (_aggro >= 65) exitWith { "agressif" };
        if (_fear >= 75) exitWith { "effraye" };
        if (_nerve >= 65) exitWith { "nerveux" };
        if (_honesty <= 30) exitWith { "mefiant" };
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
if (_pool isEqualTo []) then { _pool = [["Je ne comprends pas.", "I don't understand."]]; };
private _fresh = _pool select { !((_x select 0) in _recent) };
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
    if (_mine isNotEqualTo [] && {(random 1) < 0.22}) then {
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
    if (_e isEqualTo []) then { _e = ["_local", "*"] call _pickIn; };
    if (_e isNotEqualTo []) then { _local = _e select 0; };
};
[_fr joinString " ", _en joinString " ", _local, _tone, _core select 0]
