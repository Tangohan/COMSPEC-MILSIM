/*
    Tinder · chasse aux HVT : le renseignement derrière l'app de rencontre (fn_pageDating).
      LIKES    : des informateurs « likent » : chaque like est un tuyau sur un HVT de la carte (IA marquée HVT par le
                 Zeus, ou personne SSE au profil « Chef / HVT »). Une partie est fausse (erreur, rumeur, intox). Le joueur
                 CONFIRME (garde) ou REJETTE chaque tuyau, comme un swipe.
      Cotation : code OTAN de l'Amirauté. Fiabilité de la source de A à F, tirée de ses résultats passés (F = impossible
                 à juger tant qu'on ne la connaît pas). Crédibilité du renseignement de 1 à 6, tirée de sa cohérence
                 avec le dossier (1 = recoupé par une autre source).
      DOSSIERS : position estimée = moyenne pondérée des tuyaux confirmés qui se recoupent. L'incertitude grossit avec
                 le temps (la cible bouge) et un tuyau est périmé après 45 min. Deux sources différentes qui se recoupent :
                 MATCH (étape FIX). Cible tuée ou capturée : JACKPOT, la vérité de chaque tuyau est révélée et les
                 sources sont cotées sur leurs résultats.
      MESSAGES : chaque source a son fil ; on peut la relancer (une fois toutes les 5 min) : une source honnête précise,
                 une source qui ment continue de mentir.
    Tout reste local au joueur ; PARTAGER envoie les tuyaux confirmés d'un dossier à son groupe (comspec_atak_native_hvtTip).
    Fréquence : réglage de mission comspec_atak_native_hvt_tips ; plus de civils autour du joueur, plus de tuyaux.
    Tuyau : [id, clé HVT, code source, position annoncée, rayon d'origine, heure (CBA_missionTime), état NEW|OK|NO,
             texte, vrai, partagé par, nom du HVT]
    Params : [action, argument]
      "tick"                  tirage périodique d'un tuyau et contrôle des JACKPOT (XEH_postInitClient, toutes les 60 s)
      "tip", [clé, source, forcé (-1 hasard, 0 faux, 1 vrai), rayon, partagé par, position, texte] → id du tuyau créé
                              ("" sinon) ; source "DIRECT" = la cible elle-même, en conversation Tinder (position et
                              texte imposés par fn_datingSpecials « clue », qui décide si elle ment)
      "recv", [tuyau, nom]    tuyau partagé par un équipier
      "review", [id, état]    "OK" confirmer, "NO" rejeter
      "dossier", clé          HashMap : pos, radius, conf, stage (FIND, FIX, JACKPOT), srcs, contra, cluster, last, tips,
                              jackpot, activity (minutes depuis le dernier mouvement, -1 inconnu)
      "keys"                  clés des dossiers connus, les plus récents d'abord
      "get", id               tuyau ([] s'il n'existe plus)
      "grade", code           lettre de fiabilité        "cred", tuyau : chiffre de crédibilité     "radius", tuyau
      "source", code          [métier, âge] ([] si source inconnue : tuyau partagé)
      "name", clé             nom affiché du HVT          "unread" : tuyaux non lus     "stats" : bilan du joueur
      "followup", code        relancer une source          "mark", clé / "markTip", id : marquer sur la carte
      "share", clé            partager au groupe           "salute", clé : SALUTE prérempli
*/
params [["_act", ""], ["_arg", ""]];
private _tips = missionNamespace getVariable ["COMSPEC_ATAK_HvtTips", []];
missionNamespace setVariable ["COMSPEC_ATAK_HvtTips", _tips];
private _srcs = missionNamespace getVariable ["COMSPEC_ATAK_HvtSources", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_HvtSources", _srcs];
private _jack = missionNamespace getVariable ["COMSPEC_ATAK_HvtJackpots", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_HvtJackpots", _jack];
private _fixed = missionNamespace getVariable ["COMSPEC_ATAK_HvtFixed", []];
missionNamespace setVariable ["COMSPEC_ATAK_HvtFixed", _fixed];
private _credited = missionNamespace getVariable ["COMSPEC_ATAK_HvtCredited", []];
missionNamespace setVariable ["COMSPEC_ATAK_HvtCredited", _credited];
private _pol = missionNamespace getVariable ["COMSPEC_ATAK_HvtPol", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_HvtPol", _pol];
private _now = CBA_missionTime;
private _life = 2700;
private _hhmm = { [(dayTime - ((_now - _this) / 3600) + 24) mod 24, "HH:MM"] call BIS_fnc_timeToString };

private _unitOf = { ["unit", _this] call comspec_atak_native_fnc_datingSpecials };
// Rayon actuel : rayon annoncé + ce qu'une cible à pied a pu parcourir depuis (0,8 m/s), 3 km au plus.
private _radiusOf = { params ["_t"]; round (((_t select 4) + 0.8 * (_now - (_t select 5))) min 3000) };
private _gradeOf = {
    params ["_code"];
    private _e = _srcs getOrDefault [_code, []];
    if (_e isEqualTo []) exitWith { "F" };
    private _n = (_e select 3) + (_e select 4);
    if (_n < 2) exitWith { "F" };
    private _r = (_e select 3) / _n;
    switch (true) do {
        case (_n >= 5 && {_r >= 0.9}): { "A" };
        case (_r >= 0.75): { "B" };
        case (_r >= 0.55): { "C" };
        case (_r >= 0.35): { "D" };
        default { "E" };
    }
};
private _gradeW = { params ["_g"]; (createHashMapFromArray [["A", 1], ["B", 0.8], ["C", 0.6], ["D", 0.4], ["E", 0.2], ["F", 0.5]]) getOrDefault [_g, 0.5] };
private _weight = { params ["_t"]; ([[_t select 2] call _gradeOf] call _gradeW) / ((([_t] call _radiusOf) max 50) ^ 2) };
private _nameOf = {
    params ["_key"];
    private _u = _key call _unitOf;
    if (!isNull _u) exitWith { (["profile", _u] call comspec_atak_native_fnc_datingSpecials) get "name" };
    private _t = (_tips select { (_x select 1) isEqualTo _key }) param [0, []];
    _t param [10, "HVT"]
};
private _aiChat = missionNamespace getVariable ["COMSPEC_ATAK_RencardAiChat", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_RencardAiChat", _aiChat];
private _say = {
    params ["_key", "_mine", "_txt", ["_pos", []]];
    private _c = _aiChat getOrDefault [_key, []];
    _c pushBack [_mine, _txt, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _pos];
    while { (count _c) > 30 } do { _c deleteAt 0; };
    _aiChat set [_key, _c];
};
private _notify = {
    params ["_type", "_txt", ["_dur", 5], ["_prio", 25]];
    [_type, _txt, _dur, _prio, "DATING"] call comspec_atak_native_fnc_notify;
    [] call comspec_atak_native_fnc_vibrate;
};
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DATING") then { ["DATING"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };

// Estimation d'un dossier : ancre = tuyau le plus sûr (source la mieux cotée, rayon le plus petit), groupe = tuyaux
// confirmés et frais dont la zone touche celle de l'ancre. Les autres sont des renseignements contradictoires.
private _dossier = {
    params ["_key"];
    private _all = _tips select { (_x select 1) isEqualTo _key };
    private _ok = _all select { (_x select 6) isEqualTo "OK" && {(_now - (_x select 5)) < _life} };
    private _pl = _pol getOrDefault [_key, []];
    private _res = createHashMapFromArray [
        ["key", _key], ["tips", _all], ["stage", "FIND"], ["pos", []], ["radius", 0], ["conf", 0], ["srcs", 0], ["contra", 0],
        ["cluster", []], ["last", -1], ["jackpot", _jack getOrDefault [_key, []]], ["confirmed", count _ok],
        ["activity", [-1, round ((_now - (_pl param [1, _now])) / 60)] select (_pl isNotEqualTo [])]
    ];
    if (_all isNotEqualTo []) then { _res set ["last", selectMax (_all apply { _x select 5 })]; };
    if ((count (_res get "jackpot")) > 0) exitWith { _res set ["stage", "JACKPOT"]; _res };
    if (_ok isEqualTo []) exitWith { _res };
    private _anchor = _ok select 0;
    { if (([_x] call _weight) > ([_anchor] call _weight)) then { _anchor = _x; }; } forEach _ok;
    private _ra = [_anchor] call _radiusOf;
    private _cl = _ok select { ((_x select 3) distance2D (_anchor select 3)) <= (([_x] call _radiusOf) + _ra) };
    private _sw = 0;
    private _sx = 0;
    private _sy = 0;
    {
        private _k = [_x] call _weight;
        _sw = _sw + _k;
        _sx = _sx + _k * ((_x select 3) select 0);
        _sy = _sy + _k * ((_x select 3) select 1);
    } forEach _cl;
    private _codes = [];
    { _codes pushBackUnique (_x select 2); } forEach _cl;
    private _n = count _codes;
    private _rmin = selectMin (_cl apply { [_x] call _radiusOf });
    private _youngest = selectMin (_cl apply { _now - (_x select 5) });
    private _best = selectMax (_codes apply { [[_x] call _gradeOf] call _gradeW });
    _res set ["pos", [_sx / _sw, _sy / _sw, 0]];
    _res set ["radius", round ((_rmin * (0.7 ^ (_n - 1))) max 75)];
    _res set ["conf", round (((20 + 25 * (_n - 1) + 20 * _best) * (1 - _youngest / _life)) max 5 min 95)];
    _res set ["srcs", _n];
    _res set ["contra", (count _ok) - (count _cl)];
    _res set ["cluster", _cl apply { _x select 0 }];
    if (_n >= 2) then { _res set ["stage", "FIX"]; };
    _res
};
// Crédibilité (1 à 6) : recoupé, cohérent avec l'estimation, plus ou moins loin, ou impossible à juger.
private _credOf = {
    params ["_t"];
    if ((_now - (_t select 5)) >= _life) exitWith { 6 };
    private _d = [_t select 1] call _dossier;
    private _in = (_t select 0) in (_d get "cluster");
    if (_in && {(_d get "srcs") >= 2}) exitWith { 1 };
    private _p = _d get "pos";
    if (_p isEqualTo [] || _in) exitWith { 3 };
    private _gap = ((_t select 3) distance2D _p) - ([_t] call _radiusOf);
    private _r = _d get "radius";
    switch (true) do {
        case (_gap <= _r): { 2 };
        case (_gap <= _r * 3): { 3 };
        case (_gap <= _r * 6): { 4 };
        default { 5 };
    }
};
private _jobs = ["Chauffeur de taxi", "Épicier", "Berger", "Mécanicien", "Étudiant", "Gardien de nuit", "Vendeur de cartes SIM", "Pharmacien", "Pêcheur", "Boulanger", "Réparateur de téléphones", "Livreur"];
// Informateurs : quatre au départ, puis un nouveau de temps en temps (il se présente de lui-même). Leur honnêteté réelle
// reste cachée : seuls leurs résultats la révèlent (cotation).
private _pickSource = {
    if ((count _srcs) >= 4 && {random 1 >= 0.15}) exitWith { selectRandom (keys _srcs) };
    private _code = "";
    while { _code isEqualTo "" || {_code in _srcs} } do {
        _code = format ["%1-%2", selectRandom ["ALPHA", "BRAVO", "CHARLIE", "DELTA", "ECHO", "FOXTROT", "GOLF", "HOTEL", "KILO", "LIMA", "MIKE", "OSCAR", "ROMEO", "SIERRA", "TANGO", "VICTOR"], 10 + floor random 89];
    };
    _srcs set [_code, [selectRandom _jobs, 19 + floor random 45, selectRandom [0.9, 0.75, 0.6, 0.45, 0.25], 0, 0]];
    _code
};
// Texte du tuyau : un vrai tuyau dit ce qui est vrai (véhicule, escorte, bâtiment), un faux invente.
private _tipText = {
    params ["_u", "_pos", "_true", "_r0"];
    private _locs = nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal", "Hill"], 2000];
    private _grid = [_pos, [4, 6] select (_r0 <= 300)] call comspec_atak_native_fnc_gridRef;
    private _place = if (_locs isEqualTo []) then { _grid } else { format ["%1 (%2)", text (_locs select 0), _grid] };
    private _veh = vehicle _u;
    private _esc = count ((_u nearEntities ["CAManBase", 60]) select { alive _x && {_x isNotEqualTo _u} && {(side group _x) isEqualTo (side group _u)} });
    private _inside = !isNull (nearestBuilding _u) && {(_u distance2D (nearestBuilding _u)) < 12};
    private _what = if (_true) then {
        switch (true) do {
            case (_veh isNotEqualTo _u): { format ["Il se déplace en %1", getText (configOf _veh >> "displayName")] };
            case (_esc > 0): { format ["Environ %1 hommes armés avec lui", _esc max 2] };
            case (_inside): { "Il ne sort pas de la maison" };
            default { "Il est à pied, seul" };
        }
    } else {
        selectRandom [format ["Il se déplace en %1", selectRandom ["pick-up blanc", "berline grise", "moto", "camion de livraison"]],
            format ["Environ %1 hommes armés avec lui", 2 + floor random 6], "Il ne sort pas de la maison", "Il est à pied, seul", "Il change de téléphone tous les jours"]
    };
    format [selectRandom ["Je l'ai vu vers %1. %2.", "Mon cousin l'a croisé près de %1. %2.", "Il a passé la nuit vers %1. %2.", "On parle de lui au marché : vers %1. %2."], _place, _what]
};

switch (_act) do {
    case "tip": {
        _arg params ["_key", ["_src", ""], ["_forced", -1], ["_r0", -1], ["_from", ""], ["_fixPos", []], ["_fixText", ""]];
        private _u = _key call _unitOf;
        if (isNull _u || {!alive _u}) exitWith { "" };
        if (_src isEqualTo "") then { _src = call _pickSource; };
        private _e = _srcs getOrDefault [_src, ["", 30, 0.6, 0, 0]];
        private _true = [_forced isEqualTo 1, random 1 < (_e select 2)] select (_forced isEqualTo -1);
        if (_r0 < 0) then { _r0 = selectRandom [120, 200, 300, 450, 700]; };
        private _hp = getPosATL _u;
        private _pick = { if (_true) then { _hp getPos [random (_r0 * 0.8), random 360] } else { _hp getPos [900 + random 2200, random 360] } };
        private _pos = +_fixPos;
        if (_pos isEqualTo []) then {
            _pos = call _pick;
            private _try = 0;
            while { surfaceIsWater _pos && {_try < 8} } do { _pos = call _pick; _try = _try + 1; };
        };
        _pos set [2, 0];
        private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_HvtSeq", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_HvtSeq", _seq];
        private _id = format ["%1-%2", getPlayerUID player, _seq];
        private _text = [_fixText, [_u, _pos, _true, _r0] call _tipText] select (_fixText isEqualTo "");
        _tips pushBack [_id, _key, _src, _pos, _r0, _now, "NEW", _text, _true, _from, (["profile", _u] call comspec_atak_native_fnc_datingSpecials) get "name"];
        while { (count _tips) > 80 } do { _tips deleteAt 0; };
        if (_from isEqualTo "" && {_src isNotEqualTo "DIRECT"}) then { [format ["src:%1", _src], false, _text, _pos] call _say; };
        _id
    };
    case "tick": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        if !(missionNamespace getVariable ["comspec_atak_native_civil_apps", true]) exitWith {};
        if (((toLower (missionNamespace getVariable ["comspec_atak_native_apps_off", ""])) find "dating") >= 0) exitWith {};
        private _hvts = (["hvts"] call comspec_atak_native_fnc_datingSpecials) select { !((["key", _x] call comspec_atak_native_fnc_datingSpecials) in _jack) };
        // Activité du téléphone de la cible (« activité récente ») : dernier déplacement de plus de 30 m.
        {
            private _k = ["key", _x] call comspec_atak_native_fnc_datingSpecials;
            private _pl = _pol getOrDefault [_k, []];
            if (_pl isEqualTo [] || {((_pl select 0) distance2D _x) > 30}) then { _pol set [_k, [getPosATL _x, _now]]; };
        } forEach _hvts;
        // JACKPOT : cible d'un dossier tuée ou capturée.
        private _known = [];
        { _known pushBackUnique (_x select 1); } forEach _tips;
        {
            private _key = _x;
            private _u = _key call _unitOf;
            if (!(_key in _jack) && {!isNull _u}) then {
                private _cap = alive _u && {captive _u || {_u getVariable ["ace_captives_isHandcuffed", false]} || {_u getVariable ["ace_captives_isSurrendering", false]}};
                if (!alive _u || _cap) then {
                    private _mine = _tips select { (_x select 1) isEqualTo _key };
                    private _nT = { _x select 8 } count _mine;
                    private _rev = _mine select { (_x select 6) in ["OK", "NO"] };
                    private _good = { ((_x select 6) isEqualTo "OK") isEqualTo (_x select 8) } count _rev;
                    // Les sources sont jugées sur la vérité, une fois la cible entre nos mains.
                    {
                        private _e = _srcs getOrDefault [_x select 2, []];
                        if (_e isNotEqualTo [] && {(_x select 9) isEqualTo ""}) then {
                            if (_x select 8) then { _e set [3, (_e select 3) + 1]; } else { _e set [4, (_e select 4) + 1]; };
                        };
                        _credited pushBackUnique (_x select 0);
                    } forEach (_mine select { !((_x select 0) in _credited) });
                    private _how = ["CAPTURÉ", "EKIA"] select (!alive _u);
                    _jack set [_key, [_now, _how, _nT, (count _mine) - _nT, _good, count _rev, [_key] call _nameOf]];
                    ["SUCCESS", format ["Tinder : JACKPOT ! %1 %2. %3 tuyaux vrais sur %4, %5 bonnes décisions sur %6.", [_key] call _nameOf, _how, _nT, count _mine, _good, count _rev], 8, 60] call _notify;
                    call _rerender;
                };
            };
        } forEach _known;
        private _p = [0, 0.04, 0.09, 0.18] select ((missionNamespace getVariable ["comspec_atak_native_hvt_tips", 2]) max 0 min 3);
        if (_p <= 0 || {_hvts isEqualTo []}) exitWith {};
        // Le renseignement humain vient de la population : plus de civils autour du joueur, plus de tuyaux.
        private _civ = { alive _x && {(side group _x) isEqualTo civilian} && {!isPlayer _x} } count (player nearEntities ["CAManBase", 600]);
        if (random 1 >= _p * (0.4 + ((0.15 * _civ) min 1.2))) exitWith {};
        private _u = selectRandom _hvts;
        private _id = ["tip", [["key", _u] call comspec_atak_native_fnc_datingSpecials]] call comspec_atak_native_fnc_hvtIntel;
        if (_id isEqualTo "") exitWith {};
        private _t = ["get", _id] call comspec_atak_native_fnc_hvtIntel;
        ["MESSAGE", format ["Tinder : %1 vous a liké (tuyau sur %2)", _t select 2, _t select 10], 5, 25] call _notify;
        call _rerender;
    };
    case "recv": {
        _arg params ["_t", ["_who", ""]];
        if !(_t isEqualType [] && {(count _t) >= 11}) exitWith {};
        if ((_tips findIf { (_x select 0) isEqualTo (_t select 0) }) >= 0) exitWith {};
        private _c = +_t;
        _c set [6, "NEW"];
        _c set [9, _who];
        _tips pushBack _c;
        while { (count _tips) > 80 } do { _tips deleteAt 0; };
        ["MESSAGE", format ["Tinder : %1 vous a partagé un tuyau sur %2", _who, _c select 10], 4, 20] call _notify;
        call _rerender;
    };
    case "review": {
        _arg params ["_id", "_st"];
        private _t = (_tips select { (_x select 0) isEqualTo _id }) param [0, []];
        if (_t isEqualTo []) exitWith {};
        _t set [6, _st];
        private _key = _t select 1;
        private _d = [_key] call _dossier;
        if ((_d get "stage") isNotEqualTo "FIX") exitWith { if (_key in _fixed) then { _fixed deleteAt (_fixed find _key); }; };
        if (_key in _fixed) exitWith {};
        _fixed pushBack _key;
        // Recoupement : chaque source du groupe gagne un bon résultat (une fois par tuyau).
        {
            private _id2 = _x;
            private _t2 = (_tips select { (_x select 0) isEqualTo _id2 }) param [0, []];
            if (_t2 isNotEqualTo [] && {!(_id2 in _credited)} && {(_t2 select 9) isEqualTo ""}) then {
                private _e = _srcs getOrDefault [_t2 select 2, []];
                if (_e isNotEqualTo []) then { _e set [3, (_e select 3) + 1]; };
                _credited pushBack _id2;
            };
        } forEach (_d get "cluster");
        ["SUCCESS", format ["Tinder : c'est un match ! %1 : %2 sources se recoupent, zone de %3 m", [_key] call _nameOf, _d get "srcs", _d get "radius"], 6, 50] call _notify;
    };
    case "dossier": { [_arg] call _dossier };
    case "get": { +((_tips select { (_x select 0) isEqualTo _arg }) param [0, []]) };
    case "grade": { [_arg] call _gradeOf };
    case "cred": { [_arg] call _credOf };
    case "radius": { [_arg] call _radiusOf };
    case "name": { [_arg] call _nameOf };
    case "source": { private _e = _srcs getOrDefault [_arg, []]; if (_e isEqualTo []) then { [] } else { [_e select 0, _e select 1] } };
    case "unread": { { (_x select 6) isEqualTo "NEW" } count _tips };
    case "keys": {
        private _k = [];
        { _k pushBackUnique (_x select 1); } forEach ([_tips, [], { _x select 5 }, "DESCEND"] call BIS_fnc_sortBy);
        {
            private _u = _x call _unitOf;
            if (!isNull _u && {(["kind", _u] call comspec_atak_native_fnc_datingSpecials) isEqualTo "HVT"}) then { _k pushBackUnique _x; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_RencardAiMatches", []]);
        _k
    };
    case "stats": {
        private _rev = _tips select { (_x select 6) in ["OK", "NO"] };
        private _good = 0;
        private _judged = 0;
        { _good = _good + (_x select 4); _judged = _judged + (_x select 5); } forEach (values _jack);
        createHashMapFromArray [["tips", count _tips], ["ok", { (_x select 6) isEqualTo "OK" } count _tips], ["reviewed", count _rev],
            ["jackpots", count _jack], ["good", _good], ["judged", _judged], ["sources", count _srcs]]
    };
    case "followup": {
        private _code = _arg;
        private _ck = format ["src:%1", _code];
        [_ck, true, "Tu peux m'en dire plus ?"] call _say;
        private _at = missionNamespace getVariable ["COMSPEC_ATAK_HvtAskAt", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_HvtAskAt", _at];
        private _mine = _tips select { (_x select 2) isEqualTo _code && {(_x select 9) isEqualTo ""} };
        private _last = _mine param [(count _mine) - 1, []];
        switch (true) do {
            case (_now - (_at getOrDefault [_code, -1e9]) < 300): {
                [_ck, false, selectRandom ["Pas maintenant, c'est dangereux.", "Je t'ai déjà tout dit.", "Laisse-moi du temps, je me renseigne."]] call _say;
            };
            case (_last isEqualTo [] || {(_now - (_last select 5)) >= _life} || {isNull ((_last select 1) call _unitOf)}): {
                _at set [_code, _now];
                [_ck, false, "Rien de nouveau pour l'instant."] call _say;
            };
            default {
                _at set [_code, _now];
                // Une source honnête précise (zone deux fois plus petite), une source qui ment continue de mentir.
                private _id = ["tip", [_last select 1, _code, [0, 1] select (_last select 8), ((_last select 4) * 0.5) max 60]] call comspec_atak_native_fnc_hvtIntel;
                if (_id isNotEqualTo "") then { [] call comspec_atak_native_fnc_vibrate; };
            };
        };
        call _rerender;
    };
    case "mark";
    case "markTip": {
        private _tip = _act isEqualTo "markTip";
        private _t = if (_tip) then { ["get", _arg] call comspec_atak_native_fnc_hvtIntel } else { [] };
        private _key = [_arg, _t param [1, ""]] select _tip;
        private _d = [_key] call _dossier;
        private _pos = [_d get "pos", _t param [3, []]] select _tip;
        if (_pos isEqualTo []) exitWith { ["INFO", "Tinder : pas encore de position estimée (confirmez des tuyaux)", 4, 20] call comspec_atak_native_fnc_notify; };
        private _r = if (_tip) then { [_t] call _radiusOf } else { _d get "radius" };
        private _name = [_key] call _nameOf;
        private _m = [format ["comspec_hvt_%1", _key], format ["comspec_hvttip_%1", _arg]] select _tip;
        deleteMarkerLocal _m;
        deleteMarkerLocal (_m + "_z");
        private _z = createMarkerLocal [_m + "_z", _pos];
        _z setMarkerShapeLocal "ELLIPSE";
        _z setMarkerSizeLocal [_r, _r];
        _z setMarkerBrushLocal "Border";
        _z setMarkerColorLocal (["ColorOrange", "ColorRed"] select ((_d get "stage") isEqualTo "FIX" && {!_tip}));
        private _mk = createMarkerLocal [_m, _pos];
        _mk setMarkerTypeLocal (["hd_objective", "hd_unknown"] select _tip);
        _mk setMarkerColorLocal (["ColorOrange", "ColorRed"] select ((_d get "stage") isEqualTo "FIX" && {!_tip}));
        _mk setMarkerTextLocal (if (_tip) then {
            format ["Tuyau %1 : %2 (%3)", _t select 2, _name, (_t select 5) call _hhmm]
        } else {
            format ["HVT %1 · %2 %% · ± %3 m (%4)", _name, _d get "conf", _r, [dayTime, "HH:MM"] call BIS_fnc_timeToString]
        });
        ["SUCCESS", format ["Tinder : %1 marqué sur votre carte (± %2 m)", _name, _r], 4, 30] call comspec_atak_native_fnc_notify;
    };
    case "share": {
        private _d = [_arg] call _dossier;
        private _send = (_d get "tips") select { (_x select 6) isEqualTo "OK" && {(_now - (_x select 5)) < _life} };
        private _to = (units group player) select { isPlayer _x && {_x isNotEqualTo player} };
        if (_send isEqualTo []) exitWith { ["INFO", "Tinder : aucun tuyau confirmé à partager", 3, 20] call comspec_atak_native_fnc_notify; };
        if (_to isEqualTo []) exitWith { ["INFO", "Tinder : personne d'autre dans votre groupe", 3, 20] call comspec_atak_native_fnc_notify; };
        { ["comspec_atak_native_hvtTip", [_x, name player], _to] call CBA_fnc_targetEvent; } forEach _send;
        ["SUCCESS", format ["Tinder : %1 tuyau(x) sur %2 partagé(s) avec %3 équipier(s)", count _send, [_arg] call _nameOf, count _to], 4, 30] call comspec_atak_native_fnc_notify;
    };
    case "salute": {
        private _d = [_arg] call _dossier;
        private _draft = uiNamespace getVariable ["COMSPEC_ATAK_SaluteDraft", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_SaluteDraft", _draft];
        private _tr = { _this call comspec_atak_native_fnc_tr };
        _draft set ["sS", format ["1 HVT (%1)" call _tr, [_arg] call _nameOf]];
        _draft set ["sA", format [(["Renseignement humain non recoupé (%1 source)", "Renseignement humain recoupé (%1 sources)"] select ((_d get "srcs") >= 2)) call _tr, _d get "srcs"]];
        _draft set ["sL", if ((_d get "pos") isEqualTo []) then { "" } else { [_d get "pos", 8] call comspec_atak_native_fnc_gridRef }];
        _draft set ["sT", [(_d get "last") call _hhmm, [dayTime, "HH:MM"] call BIS_fnc_timeToString] select ((_d get "last") < 0)];
        _draft set ["sU", format ["Confiance %1 %%, ± %2 m" call _tr, _d get "conf", _d get "radius"]];
        ["SALUTE"] call comspec_atak_native_fnc_navigate;
    };
    default { [] };
};
