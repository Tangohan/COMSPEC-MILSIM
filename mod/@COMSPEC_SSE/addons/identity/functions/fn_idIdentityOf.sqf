/*
    Identité unique d'une personne : UNE seule source pour ses papiers (nom, sexe, naissance, pays, numéro et MRZ du
    pays), le nom qu'elle donne dans la discussion (en-tête, bulles), SEEK II et les fichiers, l'analyse du Zeus, la
    fenêtre « IA de la zone », les contacts et les avis BOLO. Avant, trois noms coexistaient pour une même IA : le nom
    d'unité Arma (« Andrey Afanasyev »), le nom donné par le Zeus (atelier IA, comspec_sse_idName) et une identité tirée
    au hasard pour les papiers (fn_idGenerate, qui ne lisait pas le nom donné).

    Variable d'unité diffusée comspec_sse_identity (paires [clé, valeur], jamais de HashMap diffusée) :
      first, last, sex (M | F), dob (AAAA-MM-JJ), pob, country (code du pays : pays fictifs ALT, TNA, LVN, MLD, pays réels
      en alpha-3 ISO ; pack Altis par défaut), src (explicit | arma | generated), rev (n° de version), sig (empreinte
      des sources, pour voir qu'un nom a changé ailleurs).

    Priorité (la première qui donne un nom l'emporte, tableau vérifié par tests/Unit/SseIdentityUniqueTest.php) :
      1. explicit   nom et prénom posés par la mission : COMSPEC_SSE_FirstName / COMSPEC_SSE_LastName (attribut Eden
                    « Nom » / « Prénom », module Zeus « Profil SSE », atelier IA « Nom » et attribut Eden « Profil IA RP »,
                    fenêtre « IA de la zone » (profil Athena, élément gardé), dossier SSE), sinon la fiche « identity »
                    du dossier ; avec, s'ils sont donnés, COMSPEC_SSE_Sex, COMSPEC_SSE_BirthDate, COMSPEC_SSE_BirthPlace
                    et le pays (COMSPEC_ID_Country « Pays des papiers », COMSPEC_SSE_Country, pays de l'atelier IA,
                    COMSPEC_SSE_Nationality s'il est un code connu).
      2. arma       le nom d'unité Arma, SEULEMENT s'il a été choisi par le chef de mission (Eden › Identité › Nom,
                    setName, CfgIdentities) : un nom dont le prénom et le nom viennent des listes du jeu
                    (CfgWorlds › GenericNames, celles qu'Arma pioche pour nommer une IA) est un nom tiré, ignoré.
                    Un nom choisi qui figure par hasard dans ces listes est pris pour un nom tiré (limite assumée).
      3. generated  identité tirée UNE fois (fn_idGenerate : graine de la clé biométrique, réservoirs du pays) puis
                    gardée sur l'unité et diffusée : elle ne change plus, même si le pays de la mission change.
    Ce qu'une identité explicite ne dit pas : sexe d'après le prénom (réservoirs des pays), sinon le modèle, sinon la
    terminaison ; naissance tirée pour la personne (même graine que ses papiers). Pays absent : pays de la mission, jamais
    deviné en silence (« suggest » propose au Zeus, dans sa fenêtre, le pays où ce nom est courant).
    Identité changée (nom donné plus tard) : ses papiers suivent (fn_idAiConfig « sync », réglage « Mettre à jour ses
    papiers quand son identité change », coché par défaut) ; un papier déjà remis à un joueur garde ce qui y est imprimé.

    [_op, _args] call comspec_sse_fnc_idIdentityOf
      "get"          [unité, clé, pays] -> HashMap first, last, sex, dob, pob, country, nat, src, rev (+ profession,
                                           age, seed du tirage). SERVEUR : résout, garde, diffuse. Ailleurs : la variable
                                           diffusée (sinon les noms déclarés). Unité nulle : tirage de la clé (papier
                                           posé au sol), rien de gardé. Joueur : déclaré sinon tiré, jamais son pseudo.
      "full"         [unité]            -> « Prénom NOM »
      "presented"    [unité, décider]   -> nom qu'elle donne et que portent ses papiers (SERVEUR ; décider (défaut vrai) :
                                           papiers décidés d'abord) : son identité, sauf faux papiers ou couverture
                                           (le nom imprimé) ; diffusé dans comspec_sse_idName (bulles, en-tête)
      "set"          [unité, champs, source] (SERVEUR) champs : name (« Prénom NOM ») ou first / last, sex, dob, pob,
                                           country -> HashMap identity, changed, held (papiers déjà remis à un joueur,
                                           gardés tels quels), stale (papiers non suivis : réglage décoché), suggest
      "explicit"     [unité]            -> HashMap des champs explicites (vide sans nom donné)
      "isGenericName" [nom]             -> vrai si le nom vient des listes du jeu (nom tiré par Arma)
      "sexOf"        [prénom, unité]    -> M | F
      "suggest"      [prénom, nom]      -> code du pays dont les réservoirs contiennent ce nom ("" sinon)
      "split"        [nom complet]      -> [prénom, nom] (« Hugo LEBOUBE », « LEBOUBE Hugo » : les capitales sont le nom)
      "heldByPlayer" [document]         -> vrai : papier remis à un joueur ou pris par un joueur
      "selfTest"     []                 (SERVEUR) tableau de priorité rejoué sur une personne de test, sans interface ;
                                           -> [[cas, réussi, détail]…], aussi dans le RPT
*/
params [["_op", "get", [""]], ["_a", []]];
private _str = { if (_this isEqualType "") then { trim _this } else { "" } };
private _same = { params ["_x1", "_x2"]; (toUpper (trim _x1)) isEqualTo (toUpper (trim _x2)) };
private _fullOf = { params ["_h"]; trim format ["%1 %2", _h getOrDefault ["first", ""], toUpper (_h getOrDefault ["last", ""])] };

// « Hugo LEBOUBE », « LEBOUBE Hugo » : les mots en capitales sont le nom de famille ; sinon prénom puis nom.
private _split = {
    params ["_n"];
    private _w = ((_n call _str) splitString " ") select { _x isNotEqualTo "" };
    private _up = { (toUpper _this) isEqualTo _this && {(toLower _this) isNotEqualTo _this} && {(count _this) > 1} };
    if (_w isEqualTo []) exitWith { ["", ""] };
    if ((count _w) isEqualTo 1) exitWith { [[_w select 0, ""], ["", _w select 0]] select ((_w select 0) call _up) };
    private _caps = _w select { _x call _up };
    if (_caps isNotEqualTo [] && {(count _caps) < (count _w)}) exitWith { [(_w select { !(_x call _up) }) joinString " ", _caps joinString " "] };
    [_w select 0, (_w select [1, 99]) joinString " "]
};
// Date de naissance saisie (« 12/03/1980 », « 12.03.1980 », « 1980-03-12 ») -> AAAA-MM-JJ (papiers, MRZ) ; "" si illisible.
private _dobNorm = {
    private _p = ((_this call _str) splitString "/.- ") select { _x isNotEqualTo "" };
    if ((count _p) isNotEqualTo 3) exitWith { "" };
    private _n = _p apply { parseNumber _x };
    if ((count (_p select 0)) isNotEqualTo 4) then { _n = [_n select 2, _n select 1, _n select 0]; };
    _n params ["_y", "_m", "_d"];
    if (_y < 1900 || {_m < 1} || {_m > 12} || {_d < 1} || {_d > 31}) exitWith { "" };
    format ["%1-%2%3-%4%5", _y, ["", "0"] select (_m < 10), _m, ["", "0"] select (_d < 10), _d]
};

// Noms qu'Arma tire pour nommer une IA (toutes les listes de CfgWorlds › GenericNames), lus une fois par poste.
private _generic = {
    private _g = missionNamespace getVariable "comspec_sse_idGenericNames";
    if (isNil "_g") then {
        private _f = createHashMap;
        private _l = createHashMap;
        {
            private _cls = _x;
            { _f set [toLower (getText _x), true]; } forEach (configProperties [_cls >> "FirstNames", "isText _x"]);
            { _l set [toLower (getText _x), true]; } forEach (configProperties [_cls >> "LastNames", "isText _x"]);
        } forEach ("true" configClasses (configFile >> "CfgWorlds" >> "GenericNames"));
        _g = [_f, _l];
        missionNamespace setVariable ["comspec_sse_idGenericNames", _g];
    };
    _g
};
private _isGeneric = {
    params ["_n"];
    private _w = ((_n call _str) splitString " ") select { _x isNotEqualTo "" };
    if (_w isEqualTo [] || {((_n call _str) find "Error") >= 0}) exitWith { true };
    (call _generic) params ["_f", "_l"];
    private _rest = _w select [1, 99];
    ((toLower (_w select 0)) in _f) && {_rest isEqualTo [] || {(toLower (_rest joinString " ")) in _l} || {(_rest findIf { !((toLower _x) in _l) }) < 0}}
};

// Sexe : d'après le prénom (réservoirs des pays), sinon le modèle, sinon la terminaison ; homme par défaut.
private _sexOf = {
    params ["_first", ["_u", objNull]];
    private _k = toLower (_first call _str);
    private _r = "";
    if (_k isNotEqualTo "" && {!isNil "comspec_sse_fnc_idCountries"}) then {
        private _m = false;
        private _w = false;
        {
            if (((_y getOrDefault ["firstF", []]) findIf { (toLower _x) isEqualTo _k }) >= 0) then { _w = true; };
            if (((_y getOrDefault ["first", []]) findIf { (toLower _x) isEqualTo _k }) >= 0) then { _m = true; };
        } forEach ([] call comspec_sse_fnc_idCountries);
        if (_w && {!_m}) then { _r = "F"; };
        if (_m && {!_w}) then { _r = "M"; };
    };
    if (_r isEqualTo "" && {!isNull _u} && {((getArray (configOf _u >> "identityTypes")) findIf { "woman" in toLower _x }) >= 0}) then { _r = "F"; };
    if (_r isEqualTo "" && {(_k select [(count _k) - 1]) isEqualTo "a"} && {!(_k in ["luca", "nikita", "ilya", "sasha", "misha", "kosta", "costa", "mustafa", "musa", "yahya", "isa", "hamza", "taha", "joshua", "elia"])}) then { _r = "F"; };
    [_r, "M"] select (_r isEqualTo "")
};

// Pays de l'identité : imposé, sinon déclaré (attribut, atelier, nationalité si c'est un code connu), sinon la mission.
private _countryOf = {
    params ["_u", ["_want", ""]];
    private _all = [] call comspec_sse_fnc_idCountries;
    private _c = toUpper (_want call _str);
    if !(_c in _all) then { _c = ""; };
    if (_c isEqualTo "" && {!isNull _u}) then {
        {
            private _v = toUpper (_x call _str);
            if (_c isEqualTo "" && {_v in _all}) then { _c = _v; };
        } forEach [_u getVariable ["COMSPEC_SSE_Country", ""], _u getVariable ["COMSPEC_ID_Country", ""],
            (_u getVariable ["comspec_sse_iarpProfile", createHashMap]) getOrDefault ["country", ""], _u getVariable ["COMSPEC_SSE_Nationality", ""]];
    };
    if (_c isEqualTo "") then { _c = ["default"] call comspec_sse_fnc_idCountries; };
    _c
};

// Champs explicites : variables déclarées (un seul écrivain : fn_applyProfile, « set »), sinon fiche « identity ».
private _explicitOf = {
    params ["_u"];
    private _h = createHashMap;
    if (isNull _u) exitWith { _h };
    private _f = (_u getVariable ["COMSPEC_SSE_FirstName", ""]) call _str;
    private _l = (_u getVariable ["COMSPEC_SSE_LastName", ""]) call _str;
    private _sec = [_u, "identity"] call comspec_sse_fnc_getSection;
    if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) then { _sec = createHashMap; };
    if (_f isEqualTo "" && {_l isEqualTo ""}) then {
        _f = (_sec getOrDefault ["first_name", ""]) call _str;
        _l = (_sec getOrDefault ["last_name", ""]) call _str;
        if (_f isEqualTo "" && {_l isEqualTo ""}) then { ([(_sec getOrDefault ["name", ""]) call _str] call _split) params ["_sf", "_sl"]; _f = _sf; _l = _sl; };
    };
    if (_f isEqualTo "" && {_l isEqualTo ""}) exitWith { _h };
    _h set ["first", _f];
    _h set ["last", _l];
    private _sx = toUpper ((_u getVariable ["COMSPEC_SSE_Sex", ""]) call _str);
    if (_sx in ["M", "F"]) then { _h set ["sex", _sx]; };
    private _dob = (_u getVariable ["COMSPEC_SSE_BirthDate", ""]) call _str;
    if (_dob isEqualTo "") then { _dob = (_sec getOrDefault ["birth_date", ""]) call _str; };
    _dob = _dob call _dobNorm;
    if (_dob isNotEqualTo "") then { _h set ["dob", _dob]; };
    private _pob = (_u getVariable ["COMSPEC_SSE_BirthPlace", ""]) call _str;
    if (_pob isEqualTo "") then { _pob = (_sec getOrDefault ["birth_place", ""]) call _str; };
    if (_pob isNotEqualTo "") then { _h set ["pob", _pob]; };
    _h
};
// Empreinte des sources : un nom posé ailleurs (module Zeus, attribut, script) est vu au prochain appel.
private _sigOf = {
    params ["_u"];
    str ([["COMSPEC_SSE_FirstName", ""], ["COMSPEC_SSE_LastName", ""], ["COMSPEC_SSE_Sex", ""], ["COMSPEC_SSE_BirthDate", ""], ["COMSPEC_SSE_BirthPlace", ""],
        ["COMSPEC_SSE_Country", ""], ["COMSPEC_ID_Country", ""], ["COMSPEC_SSE_Nationality", ""]] apply { _u getVariable _x }) + "|" + name _u
};
private _stored = {
    params ["_u"];
    private _v = _u getVariable ["comspec_sse_identity", []];
    if (_v isEqualType [] && {_v isNotEqualTo []}) then { createHashMapFromArray (_v select { _x isEqualType [] && {(count _x) isEqualTo 2} }) } else { createHashMap }
};
// Papier remis à un joueur, ou passé par les mains d'un joueur : ce qui y est imprimé ne change plus.
private _held = {
    params ["_d"];
    private _h = _d getOrDefault ["holder", objNull];
    (_h isEqualType objNull && {!isNull _h} && {isPlayer _h})
        || {((_d getOrDefault ["custody", []]) findIf { (_x param [3, ""]) isNotEqualTo "" }) >= 0}
        || {(_d getOrDefault ["ownerUid", ""]) isNotEqualTo ""}
};
private _docsOf = {
    params ["_u"];
    private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
    if ((count _reg) isEqualTo 0) exitWith { [] };
    private _k = _u getVariable ["COMSPEC_SSE_BioKey", ""];
    if !(_k isEqualType "") then { _k = ""; };
    if (_k isEqualTo "") exitWith { [] };
    (((_reg getOrDefault ["units", createHashMap]) getOrDefault [_k, []]) apply { (_reg getOrDefault ["docs", createHashMap]) getOrDefault [_x, createHashMap] }) select { (count _x) > 0 }
};
// Nom présenté : son identité, sauf faux papiers ou couverture (le nom imprimé sur sa pièce principale).
private _present = {
    params ["_u", "_id"];
    private _n = [_id] call _fullOf;
    private _docs = [_u] call _docsOf;
    private _i = _docs findIf { (_x getOrDefault ["kind", "civil"]) in ["forged", "cover", "alias"] };
    if (_i >= 0) then { private _d = _docs select _i; _n = trim format ["%1 %2", _d getOrDefault ["first", ""], toUpper (_d getOrDefault ["last", ""])]; };
    if (_n isNotEqualTo "" && {(_u getVariable ["comspec_sse_idName", ""]) isNotEqualTo _n}) then { _u setVariable ["comspec_sse_idName", _n, true]; };
    _n
};

switch (_op) do {
    case "split": { [_a param [0, ""]] call _split };
    case "isGenericName": { [_a param [0, ""]] call _isGeneric };
    case "sexOf": { _a params [["_f", ""], ["_u", objNull]]; [_f, _u] call _sexOf };
    case "heldByPlayer": { [_a param [0, createHashMap]] call _held };
    case "explicit": { [_a param [0, objNull]] call _explicitOf };
    case "suggest": {
        _a params [["_f", ""], ["_l", ""]];
        private _kl = toLower (_l call _str);
        private _kf = toLower (_f call _str);
        private _best = "";
        private _score = 0;
        {
            private _s = 0;
            if (_kl isNotEqualTo "" && {((_y getOrDefault ["last", []]) findIf { (toLower _x) isEqualTo _kl }) >= 0}) then { _s = _s + 2; };
            if (_kf isNotEqualTo "" && {(((_y getOrDefault ["first", []]) + (_y getOrDefault ["firstF", []])) findIf { (toLower _x) isEqualTo _kf }) >= 0}) then { _s = _s + 1; };
            if (_s > _score) then { _score = _s; _best = _x; };
        } forEach ([] call comspec_sse_fnc_idCountries);
        _best
    };
    case "full": { [["get", [_a param [0, objNull]]] call comspec_sse_fnc_idIdentityOf] call _fullOf };

    case "get": {
        _a params [["_u", objNull, [objNull]], ["_key", "", [""]], ["_want", "", [""]]];
        // Papier sans personne (posé au sol) : tirage de la clé, rien de gardé.
        if (isNull _u) exitWith {
            private _g = [objNull, _key, "", [objNull, _want] call _countryOf, true] call comspec_sse_fnc_idGenerate;
            _g set ["src", "generated"];
            _g set ["country", _g get "nat"];
            _g
        };
        if (!isServer) exitWith {
            private _h = [_u] call _stored;
            if ((count _h) isEqualTo 0) then { _h = [_u] call _explicitOf; };
            _h
        };
        if (_key isEqualTo "") then { _key = [_u] call comspec_sse_fnc_bioPersonKey; };
        private _sig = [_u] call _sigOf;
        private _cur = [_u] call _stored;
        private _id = _cur;
        if ((count _cur) isEqualTo 0 || {(_cur getOrDefault ["sig", ""]) isNotEqualTo _sig}) then {
            // Pays : celui de la personne ; un joueur (rien de gardé) prend celui du papier demandé, comme avant.
            private _country = [_u, ["", _want] select (isPlayer _u)] call _countryOf;
            private _ex = [_u] call _explicitOf;
            private _src = "explicit";
            if ((count _ex) isEqualTo 0) then {
                if (!isPlayer _u && {!(_u in playableUnits)} && {!([name _u] call _isGeneric)}) then {
                    ([name _u] call _split) params ["_af", "_al"];
                    _ex = createHashMapFromArray [["first", _af], ["last", _al]];
                    _src = "arma";
                } else { _src = "generated"; };
            };
            // Tirage de la personne (graine de sa clé) : naissance et, sans nom, son identité ; une identité déjà tirée
            // reste la même (gardée), même si le pays de la mission a changé depuis.
            private _draw = [_u, _key, "", _country, true] call comspec_sse_fnc_idGenerate;
            _id = createHashMap;
            if (_src isEqualTo "generated") then {
                private _base = [_draw, _cur] select ((_cur getOrDefault ["src", ""]) isEqualTo "generated");
                { _id set [_x, _base getOrDefault [_x, _draw get _x]]; } forEach ["first", "last", "sex", "dob", "pob"];
                _id set ["country", _cur getOrDefault ["country", _country]];
                if ((_cur getOrDefault ["src", ""]) isNotEqualTo "generated") then { _id set ["country", _country]; };
            } else {
                _id set ["first", _ex get "first"];
                _id set ["last", _ex get "last"];
                _id set ["sex", _ex getOrDefault ["sex", [_ex get "first", _u] call _sexOf]];
                _id set ["dob", _ex getOrDefault ["dob", _draw get "dob"]];
                _id set ["pob", _ex getOrDefault ["pob", _draw get "pob"]];
                _id set ["country", _country];
            };
            _id set ["src", _src];
            _id set ["sig", _sig];
            private _fields = ["first", "last", "sex", "dob", "pob", "country"];
            private _changed = (count _cur) > 0 && {(_fields apply { _cur getOrDefault [_x, ""] }) isNotEqualTo (_fields apply { _id get _x })};
            _id set ["rev", (_cur getOrDefault ["rev", 0]) + (parseNumber (_changed || {(count _cur) isEqualTo 0}))];
            if (!isPlayer _u) then { _u setVariable ["comspec_sse_identity", (keys _id) apply { [_x, _id get _x] }, true]; };
            if (_changed && {!isPlayer _u}) then {
                // Profil de l'atelier IA : son nom suit (sinon le prochain « Appliquer » remettrait l'ancien).
                private _pp = _u getVariable ["comspec_sse_iarpProfile", createHashMap];
                if (_pp isEqualType createHashMap && {(_pp getOrDefault ["name", ""]) isNotEqualTo ""}) then { _pp set ["name", [_id] call _fullOf]; };
                // Papiers déjà décidés : ils suivent (un faux reste faux, un papier remis à un joueur reste tel quel).
                if (!(_u getVariable ["comspec_sse_idSyncing", false]) && {!isNil "comspec_sse_fnc_idAiConfig"} && {([_u] call _docsOf) isNotEqualTo []}) then {
                    ["sync", [_u]] call comspec_sse_fnc_idAiConfig;
                };
                [_u, _id] call _present;
                diag_log text format ["[COMSPEC SSE] Identité : %1 s'appelle désormais %2 (%3).", netId _u, [_id] call _fullOf, _src];
            };
        };
        // Copie : l'appelant peut l'imprimer sans toucher à l'identité gardée ; extras du tirage (métier, âge, graine).
        private _out = +_id;
        private _draw2 = [_u, _key, "", _id getOrDefault ["country", ""], true] call comspec_sse_fnc_idGenerate;
        { _out set [_x, _draw2 get _x]; } forEach ["profession", "age", "seed"];
        _out set ["nat", _id getOrDefault ["country", _draw2 get "nat"]];
        _out deleteAt "sig";
        _out
    };

    case "presented": {
        _a params [["_u", objNull, [objNull]], ["_decide", true, [true]]];
        if (isNull _u) exitWith { "" };
        if (isPlayer _u) exitWith { name _u };
        if (!isServer) exitWith { (_u getVariable ["comspec_sse_idName", ""]) call _str };
        // Papiers décidés d'abord : un faux décidé plus tard changerait le nom qu'elle a déjà donné.
        if (_decide && {!isNil "comspec_sse_fnc_idRegistry"} && {missionNamespace getVariable ["comspec_sse_id_enabled", true]}) then { ["ensure", [_u]] call comspec_sse_fnc_idRegistry; };
        [_u, ["get", [_u]] call comspec_sse_fnc_idIdentityOf] call _present
    };

    case "set": {
        _a params [["_u", objNull, [objNull]], ["_in", createHashMap, [createHashMap]], ["_src", "zeus", [""]]];
        private _res = createHashMapFromArray [["identity", createHashMap], ["changed", false], ["held", 0], ["stale", 0], ["suggest", ""]];
        if (!isServer || {isNull _u} || {!(_u isKindOf "CAManBase")} || {isPlayer _u}) exitWith { _res };
        private _f = (_in getOrDefault ["first", ""]) call _str;
        private _l = (_in getOrDefault ["last", ""]) call _str;
        if (_f isEqualTo "" && {_l isEqualTo ""}) then { ([(_in getOrDefault ["name", ""]) call _str] call _split) params ["_sf", "_sl"]; _f = _sf; _l = _sl; };
        if (_f isEqualTo "" && {_l isEqualTo ""}) exitWith { _res };
        private _before = [["get", [_u]] call comspec_sse_fnc_idIdentityOf] call _fullOf;
        // Un seul jeu de variables pour tous (SEEK, dossier, Athena, papiers) : celles du profil d'identité SSE.
        _u setVariable ["COMSPEC_SSE_FirstName", _f, true];
        _u setVariable ["COMSPEC_SSE_LastName", _l, true];
        _u setVariable ["COMSPEC_SSE_NameAuthored", true, true];
        _u setVariable ["comspec_sse_identityFrom", _src, true];
        private _sx = toUpper ((_in getOrDefault ["sex", ""]) call _str);
        if (_sx in ["M", "F"]) then { _u setVariable ["COMSPEC_SSE_Sex", _sx, true]; } else { _u setVariable ["COMSPEC_SSE_Sex", nil, true]; };
        private _dobIn = (_in getOrDefault ["dob", ""]) call _dobNorm;
        if (_dobIn isNotEqualTo "") then { _u setVariable ["COMSPEC_SSE_BirthDate", _dobIn, true]; };
        private _pobIn = (_in getOrDefault ["pob", ""]) call _str;
        if (_pobIn isNotEqualTo "") then { _u setVariable ["COMSPEC_SSE_BirthPlace", _pobIn, true]; };
        private _cty = toUpper ((_in getOrDefault ["country", ""]) call _str);
        if (_cty in ([] call comspec_sse_fnc_idCountries)) then { _u setVariable ["COMSPEC_SSE_Country", _cty, true]; };
        // Dossier SSE déjà ouvert : sa fiche « identity » suit (SEEK, fichiers, envoi Athena).
        private _sec = [_u, "identity"] call comspec_sse_fnc_getSection;
        if (!isNil "_sec" && {_sec isEqualType createHashMap} && {!isNil "comspec_sse_fnc_setIdentity"}) then {
            [_u, [["first_name", _f], ["last_name", _l], ["name", trim format ["%1 %2", _f, _l]]]] call comspec_sse_fnc_setIdentity;
        };
        private _id = ["get", [_u]] call comspec_sse_fnc_idIdentityOf;
        private _now = [_id] call _fullOf;
        [_u, _id] call _present;
        _res set ["identity", _id];
        _res set ["changed", !([_before, _now] call _same)];
        private _follow = missionNamespace getVariable ["comspec_sse_id_papersFollowName", true];
        {
            private _printed = trim format ["%1 %2", _x getOrDefault ["first", ""], toUpper (_x getOrDefault ["last", ""])];
            if ((_x getOrDefault ["kind", "civil"]) in ["civil", "generated"] && {!([_printed, _now] call _same)}) then {
                if ([_x] call _held) then { _res set ["held", (_res get "held") + 1]; } else { if (!_follow) then { _res set ["stale", (_res get "stale") + 1]; }; };
            };
        } forEach ([_u] call _docsOf);
        // Pays non précisé : une suggestion pour le Zeus (jamais appliquée seule).
        if (_cty isEqualTo "" && {((_u getVariable ["COMSPEC_SSE_Country", ""]) call _str) isEqualTo ""} && {!((toUpper ((_u getVariable ["COMSPEC_ID_Country", "auto"]) call _str)) in ([] call comspec_sse_fnc_idCountries))}) then {
            private _sg = ["suggest", [_f, _l]] call comspec_sse_fnc_idIdentityOf;
            if (_sg isNotEqualTo "" && {_sg isNotEqualTo (_id getOrDefault ["country", ""])}) then { _res set ["suggest", _sg]; };
        };
        _u setVariable ["comspec_sse_identityLastSet", _res];
        _res
    };

    case "selfTest": {
        // Personne de test (agent local, loin de tout, supprimé à la fin) : chaque ligne du tableau de priorité.
        if (!isServer) exitWith { [] };
        private _out = [];
        private _ok = { params ["_case", "_pass", ["_why", ""]]; _out pushBack [_case, _pass, _why]; };
        private _t = createAgent ["C_man_1", [-4800, -4800, 0], [], 0, "CAN_COLLIDE"];
        if (isNull _t) exitWith { [["agent", false, "createAgent a échoué"]] };
        _t setVariable ["COMSPEC_SSE_BioKey", format ["SELFTEST%1", floor random 99999], true];
        // Nom tiré par le jeu à la création de l'agent (listes GenericNames de sa classe).
        private _g1 = ["get", [_t]] call comspec_sse_fnc_idIdentityOf;
        ["nom Arma tiré ignoré -> identité générée", ([name _t] call _isGeneric) && {(_g1 get "src") isEqualTo "generated"}, format ["%1 -> %2", name _t, [_g1] call _fullOf]] call _ok;
        private _g2 = ["get", [_t]] call comspec_sse_fnc_idIdentityOf;
        ["identité générée gardée (stable)", ([_g1] call _fullOf) isEqualTo ([_g2] call _fullOf) && {(_g1 get "dob") isEqualTo (_g2 get "dob")}, [_g2] call _fullOf] call _ok;
        _t setName ["Zorglub Testeurovitch", "Zorglub", "Testeurovitch"];
        private _g3 = ["get", [_t]] call comspec_sse_fnc_idIdentityOf;
        ["nom Arma choisi -> pris", (_g3 get "src") isEqualTo "arma" && {(_g3 get "last") isEqualTo "Testeurovitch"}, [_g3] call _fullOf] call _ok;
        private _r = ["set", [_t, createHashMapFromArray [["name", "Hugo LEBOUBE"]], "selftest"]] call comspec_sse_fnc_idIdentityOf;
        private _g4 = ["get", [_t]] call comspec_sse_fnc_idIdentityOf;
        ["nom donné -> identité explicite", (_g4 get "src") isEqualTo "explicit" && {(_g4 get "first") isEqualTo "Hugo"} && {(_g4 get "last") isEqualTo "LEBOUBE"} && {(_g4 get "sex") isEqualTo "M"}, [_g4] call _fullOf] call _ok;
        ["sexe d'après le prénom", (["sexOf", ["Eleni"]] call comspec_sse_fnc_idIdentityOf) isEqualTo "F", ""] call _ok;
        if (!isNil "comspec_sse_fnc_idIssue") then {
            private _did = [_t, createHashMapFromArray [["type", "id_card"], ["kind", "civil"]]] call comspec_sse_fnc_idIssue;
            private _d = ["doc", [_did]] call comspec_sse_fnc_idRegistry;
            ["papiers au nom donné", (_d getOrDefault ["last", ""]) isEqualTo "LEBOUBE" && {(_d getOrDefault ["first", ""]) isEqualTo "Hugo"}, format ["%1 %2", _d getOrDefault ["first", ""], _d getOrDefault ["last", ""]]] call _ok;
            ["set", [_t, createHashMapFromArray [["name", "Jean DUPONT"]], "selftest"]] call comspec_sse_fnc_idIdentityOf;
            _d = ["doc", [_did]] call comspec_sse_fnc_idRegistry;
            private _follow = missionNamespace getVariable ["comspec_sse_id_papersFollowName", true];
            ["renommée -> papiers refaits", !_follow || {(_d getOrDefault ["last", ""]) isEqualTo "DUPONT"}, format ["%1 %2", _d getOrDefault ["first", ""], _d getOrDefault ["last", ""]]] call _ok;
            private _fid = [_t, createHashMapFromArray [["type", "id_card"], ["kind", "forged"], ["quality", 50]]] call comspec_sse_fnc_idIssue;
            private _fd = ["doc", [_fid]] call comspec_sse_fnc_idRegistry;
            ["faux -> imprimé ≠ vrai, vrai = identité", (_fd getOrDefault ["trueLast", ""]) isEqualTo "DUPONT" && {(_fd getOrDefault ["last", ""]) isNotEqualTo "DUPONT"}, format ["imprimé %1, vrai %2", _fd getOrDefault ["last", ""], _fd getOrDefault ["trueLast", ""]]] call _ok;
            // Ménage : papiers de test rendus au stock et retirés du registre.
            private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
            private _k = _t getVariable ["COMSPEC_SSE_BioKey", ""];
            {
                private _dd = (_reg get "docs") getOrDefault [_x, createHashMap];
                if ((_dd getOrDefault ["cls", ""]) isNotEqualTo "") then { ["release", [_dd get "cls"]] call comspec_sse_fnc_idRegistry; };
                (_reg get "issued") deleteAt format ["%1|%2|%3", _dd getOrDefault ["country", ""], _dd getOrDefault ["type", ""], _dd getOrDefault ["number", ""]];
                (_reg get "docs") deleteAt _x;
            } forEach ((_reg get "units") getOrDefault [_k, []]);
            (_reg get "units") deleteAt _k;
        };
        deleteVehicle _t;
        { diag_log text format ["[COMSPEC SSE] Identité, auto-test : %1 : %2 (%3)", _x select 0, ["ÉCHEC", "ok"] select (_x select 1), _x select 2]; } forEach _out;
        _out
    };
    default { nil };
}
