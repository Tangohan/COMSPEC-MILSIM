/*
    Checkpoints IA qui contrôlent les joueurs (SERVEUR). Posés par le module Eden « Checkpoint IA » ou par le Zeus.
    Une seule boucle serveur d'une seconde pour tous les checkpoints (nearEntities sur la zone de chacun) : rien chez
    les clients, rien par image.

    Joueur qui entre dans la zone (hors camp du poste, hors ennemis déclarés) :
      halte     « Papiers, s'il vous plaît » : demande ATAK (menu ACE personnel › Mes papiers), délai réglable ; le garde
                le plus proche vient à lui
      examen    le garde applique les niveaux de SON équipement, comme un joueur (fn_idEvaluate, compétence de l'IA) :
                visuel seul N0 · scanner MRZ N1 · lecteur NFC N2 · liaison base N3 ; sans liaison, liste de recherchés
                sur papier (nom imprimé) ; visage d'une personne fichée parfois reconnu
      suspicion papiers (verdicts), équipement visible (arme, tenue militaire, gilet, casque), comportement (course, arme
                à la main ou pointée sur un garde, véhicule qui ne s'arrête pas, refus, fuite) ; × difficulté
      questions (Lot 4, réglage) le garde pose 2 ou 3 questions de légende (« D'où venez-vous ? »…) : réponse au choix
                dans l'app Ma couverture ou le menu ACE personnel ; mauvaise réponse ou silence = suspicion
      issue     selon la sévérité du poste : laisser passer · fouiller (8 s : armes, papiers à un autre nom) · retenir
                (180 s, Zeus prévenu) · alerter (groupe en combat, postes voisins) · ouvrir le feu (menace armée)
    Infiltré (fn_idCover) : contrôlé comme un civil même s'il est d'un camp ennemi du poste ; sa cohérence (papiers,
    légende, tenue, comportement) pèse sur la suspicion ; retenu = soupçons, alerte ou arme trouvée = couverture brûlée.
    BOLO (fn_idHvt) : liste papier (noms, alias, numéros) et visage joint, reconnus selon la sévérité du poste.
    IA qui traverse la zone (réglage) : contrôle « abstrait » d'ambiance, une toutes les 20 s par poste : elle s'arrête,
    l'automate des IA réalistes la fait réagir (fn_idAiFsm), le garde lit ses papiers avec son équipement, puis la
    laisse repartir, la fait se rendre ou donne l'alerte.

    ["init"]                                   boucle et évènements (XEH_postInit serveur)
    ["create", [position | objet, réglages, zeus]] -> [texte, genre]
        réglages (HashMap) : radius (m, -1 = réglage), side ("auto" | "west" | "east" | "guer" | "civ"), equip 0-3,
        severity 0-2, name, guards [unités] (vide : IA du camp dans la zone), temp, only [personnes]
    ["trigger", [personne, réglages, zeus]] -> [texte, genre]   contrôle forcé : checkpoint qui la couvre, sinon IA
                                               proche qui l'arrête (checkpoint provisoire)
    ["remove", [position]] -> [texte, genre]   ["inZone", [unité]] -> vrai si dans une zone de checkpoint
    ["tick"] (boucle)   ["presented", [porteur, docId, garde]]   ["refused", [porteur, garde]]   ["list"] -> postes
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { false };
if (isNil "comspec_sse_idCps") then { comspec_sse_idCps = []; };

if (_op isEqualTo "inZone") exitWith {
    _a params [["_u", objNull]];
    !isNull _u && {(comspec_sse_idCps findIf { (_u distance (_x get "pos")) <= (_x get "radius") }) >= 0}
};
if (_op isEqualTo "list") exitWith { comspec_sse_idCps };
if (_op isEqualTo "init") exitWith {
    if (!isNil "comspec_sse_idCpPfh") exitWith { true };
    comspec_sse_idCpPfh = [{ ["tick"] call comspec_sse_fnc_idCheckpoint; }, 1] call CBA_fnc_addPerFrameHandler;
    ["comspec_sse_idPresented", { params ["_h", "_id", "_to"]; ["presented", [_h, _id, _to]] call comspec_sse_fnc_idCheckpoint; }] call CBA_fnc_addEventHandler;
    ["comspec_sse_idRefused", { params ["_h", "_to"]; ["refused", [_h, _to]] call comspec_sse_fnc_idCheckpoint; }] call CBA_fnc_addEventHandler;
    ["comspec_sse_idAnswered", { ["answered", _this] call comspec_sse_fnc_idCheckpoint; }] call CBA_fnc_addEventHandler;
    true
};

private _sevName = ["laxiste", "normale", "stricte"];
private _equipName = ["visuel seul", "scanner MRZ", "lecteur NFC", "liaison base"];
private _do = { params ["_u", "_cmd", ["_args", []]]; ["comspec_sse_idAiDo", [_u, _cmd, _args], _u] call CBA_fnc_targetEvent; };
private _tell = {
    params ["_u", "_t", ["_type", "INFO"]];
    if (isNull _u || {!isPlayer _u}) exitWith {};
    if (local _u && {hasInterface}) then { ["notify", [_t, _type]] call comspec_sse_fnc_idClient; } else { ["notify", [_t, _type]] remoteExecCall ["comspec_sse_fnc_idClient", owner _u]; };
};
private _zeus = {
    params ["_t", ["_k", "info"]];
    {
        private _z = getAssignedCuratorUnit _x;
        if (!isNull _z && {isPlayer _z}) then {
            if (local _z && {hasInterface}) then { ["zeus", [_t, _k]] call comspec_sse_fnc_idClient; } else { ["zeus", [_t, _k]] remoteExecCall ["comspec_sse_fnc_idClient", owner _z]; };
        };
    } forEach allCurators;
};
private _nearestGuard = {
    params ["_cp", "_u"];
    private _g = (_cp get "guards") apply { [_x distance _u, _x] };
    _g sort true;
    (_g param [0, [0, objNull]]) select 1
};
private _sideOf = {
    params ["_s"];
    if (_s isEqualType west) exitWith { _s };
    switch (toLower _s) do { case "west": { west }; case "east": { east }; case "guer": { independent }; case "civ": { civilian }; default { sideUnknown }; }
};
// Suspicion : une somme de motifs plafonnés (papiers, équipement, comportement…), × difficulté.
private _bump = {
    params ["_s", "_why", "_inc", ["_cap", 100]];
    private _p = _s get "pts";
    _p set [_why, ((_p getOrDefault [_why, 0]) + _inc) min _cap];
    private _m = [0.8, 1, 1.2] select ((missionNamespace getVariable ["comspec_sse_id_difficulty", 1]) min 2);
    private _sum = 0;
    { _sum = _sum + _y; } forEach _p;
    _s set ["susp", _sum * _m];
};
private _visible = {
    params ["_u"];
    private _out = [];
    if ((primaryWeapon _u) isNotEqualTo "" || {(secondaryWeapon _u) isNotEqualTo ""}) then { _out pushBack ["arme longue visible", 15]; } else {
        if ((handgunWeapon _u) isNotEqualTo "") then { _out pushBack ["arme de poing", 6]; };
    };
    private _uc = getText (configFile >> "CfgWeapons" >> uniform _u >> "ItemInfo" >> "uniformClass");
    if (_uc isNotEqualTo "" && {(getNumber (configFile >> "CfgVehicles" >> _uc >> "side")) in [0, 1, 2]}) then { _out pushBack ["tenue militaire", 15]; };
    if ((vest _u) isNotEqualTo "" && {(getNumber (configFile >> "CfgWeapons" >> vest _u >> "ItemInfo" >> "HitpointsProtectionInfo" >> "Chest" >> "armor")) > 0}) then { _out pushBack ["gilet pare-balles", 10]; };
    if ((headgear _u) isNotEqualTo "" && {(getNumber (configFile >> "CfgWeapons" >> headgear _u >> "ItemInfo" >> "HitpointsProtectionInfo" >> "Head" >> "armor")) > 0}) then { _out pushBack ["casque", 5]; };
    _out
};
private _whyText = {
    params ["_s"];
    private _p = _s get "pts";
    private _k = (keys _p) select { (_p get _x) > 0 };
    [_k joinString ", ", "rien à signaler"] select (_k isEqualTo [])
};
private _who = {
    params ["_s"];
    private _d = ["doc", [_s getOrDefault ["doc", ""]]] call comspec_sse_fnc_idRegistry;
    if ((count _d) > 0) exitWith { format ["%1 %2", toUpper (_d get "last"), _d get "first"] };
    name (_s get "unit")
};

// Issue d'un contrôle.
private _outcome = {
    params ["_cp", "_s", "_what"];
    private _u = _s get "unit";
    private _g = _s get "guard";
    private _cpn = _cp get "name";
    private _ai = !isPlayer _u;
    _s set ["outcome", _what];
    // Infiltré : être retenu éveille les soupçons ; une alerte le démasque.
    if (_u getVariable ["comspec_sse_idCoverOn", false]) then {
        if (_what isEqualTo "hold") then { ["suspect", [_u, 60, format ["retenu au checkpoint %1", _cpn]]] call comspec_sse_fnc_idCover; };
        if (_what in ["alert", "fire"]) then { ["burn", [_u, format ["alerte au checkpoint %1", _cpn]]] call comspec_sse_fnc_idCover; };
    };
    private _giveBack = {
        // Document remis au garde (« remettre ») : rendu au passage.
        private _cls = (["doc", [_s getOrDefault ["doc", ""]]] call comspec_sse_fnc_idRegistry) getOrDefault ["cls", ""];
        if (_cls isNotEqualTo "" && {!isNull _g} && {(([_g, 0] call comspec_sse_fnc_idDocsOf) findIf { (_x select 0) isEqualTo _cls }) >= 0}) then { [_cls, _g, _u] call comspec_sse_fnc_idTransfer; };
    };
    switch (_what) do {
        case "pass": {
            call _giveBack;
            [_g, "gesture", ["GestureGo"]] call _do;
            [{ ["comspec_sse_idAiDo", [_this, "post", []], _this] call CBA_fnc_targetEvent; }, _g, 3] call CBA_fnc_waitAndExecute;
            if (_ai) then { ["release", [_u]] call comspec_sse_fnc_idAiFsm; } else { [_u, format ["Checkpoint %1 : c'est bon, circulez.", _cpn], "SUCCESS"] call _tell; };
            (_cp get "cleared") set [netId _u, time + ([600, 900] select _ai)];
            _s set ["end", true];
        };
        case "search": {
            if (_ai) exitWith {
                // Fouille abstraite d'une IA : une arme sur elle et elle est retenue, sinon elle repart.
                [_cp, _s, ["pass", "hold"] select ((primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""})] call _outcome;
            };
            _s set ["stage", "search"]; _s set ["t", time]; _s set ["deadline", time + 8]; _s set ["searched", true];
            [_g, "approach", [_u]] call _do;
            [_u, format ["Checkpoint %1 : fouille. Restez immobile près du garde (8 s).", _cpn], "WARNING"] call _tell;
        };
        case "hold": {
            _s set ["stage", "hold"]; _s set ["t", time]; _s set ["deadline", time + 180];
            { [_x, "aim", [_u]] call _do; } forEach ((_cp get "guards") select { (_x distance _u) < 40 });
            if (_ai) then { ["force", [_u, "rendu"]] call comspec_sse_fnc_idAiFsm; } else {
                [_u, format ["Checkpoint %1 : vous êtes retenu. Ne bougez pas, mains sur la tête (ACE › Se rendre).", _cpn], "WARNING"] call _tell;
            };
            [format ["Checkpoint %1 : %2 retenu (%3).", _cpn, [_s] call _who, [_s] call _whyText], "warn"] call _zeus;
            ["add", ["INTERPELLATION", format ["%1 retenu au checkpoint %2 (%3)", [_s] call _who, _cpn, [_s] call _whyText], format ["cphold|%1|%2", netId _u, floor (time / 300)]]] call comspec_sse_fnc_idAar;
        };
        case "alert";
        case "fire": {
            private _fire = _what isEqualTo "fire";
            // Le poste passe en combat et prévient les groupes du même camp à 400 m.
            { [_x, "alert", [[_u]]] call _do; } forEach (_cp get "guards");
            {
                if (alive _x && {!isPlayer _x} && {(side group _x) isEqualTo (_cp get "side")} && {!(_x in (_cp get "guards"))} && {(leader group _x) isEqualTo _x}) then { [_x, "alert", [[_u]]] call _do; };
            } forEach ((_cp get "pos") nearEntities ["CAManBase", 400]);
            if (_fire) then {
                // Pour que les gardes tirent sur un joueur qui n'est pas ennemi de leur camp : il devient hostile à tous.
                if (!([side group _u, _cp get "side"] call BIS_fnc_sideIsEnemy)) then { ["comspec_sse_idLocalRating", [_u, -10000], _u] call CBA_fnc_targetEvent; };
                { [_x, "fire", [_u]] call _do; } forEach ((_cp get "guards") select { (_x distance _u) < 300 });
            };
            if (!_ai) then { [_u, format [["Checkpoint %1 : ALERTE ! Les gardes vous mettent en joue.", "Checkpoint %1 : LES GARDES OUVRENT LE FEU !"] select _fire, _cpn], "WARNING"] call _tell; };
            [format [["Checkpoint %1 : alerte sur %2 (%3).", "Checkpoint %1 : ouverture du feu sur %2 (%3)."] select _fire, _cpn, [_s] call _who, [_s] call _whyText], "warn"] call _zeus;
            ["add", [["ALERTE", "TIR"] select _fire, format ["Checkpoint %1 : %2 sur %3 (%4)", _cpn, ["alerte", "ouverture du feu"] select _fire, [_s] call _who, [_s] call _whyText], format ["cp%1|%2|%3", _what, netId _u, floor (time / 300)]]] call comspec_sse_fnc_idAar;
            (_cp get "cleared") set [netId _u, time + 600];
            _s set ["end", true];
        };
    };
};
// Choix de l'issue selon la suspicion et la sévérité ; « feu » seulement pour une menace armée (poste normal ou strict).
private _decide = {
    params ["_cp", "_s"];
    private _th = [[50, 70, 90, 120], [30, 55, 75, 100], [15, 40, 60, 85]] select (_cp get "severity");
    private _v = _s get "susp";
    private _what = call {
        if (_v >= (_th select 3)) exitWith { ["alert", "fire"] select ((_s getOrDefault ["threat", false]) && {(_cp get "severity") >= 1}) };
        if (_v >= (_th select 2)) exitWith { "hold" };
        if (_v >= (_th select 1)) exitWith { ["search", "hold"] select (_s getOrDefault ["searched", false]) };
        if (_v >= (_th select 0)) exitWith { ["search", "pass"] select (_s getOrDefault ["searched", false]) };
        "pass"
    };
    [_cp, _s, _what] call _outcome;
};
// Lecture des papiers par le garde, avec les niveaux de son équipement.
private _examine = {
    params ["_cp", "_s"];
    private _u = _s get "unit";
    private _g = _s get "guard";
    private _d = ["doc", [_s getOrDefault ["doc", ""]]] call comspec_sse_fnc_idRegistry;
    if ((count _d) isEqualTo 0) exitWith { [_s, "document vierge", 40] call _bump; };
    // Couverture (infiltré, papiers « couverture ») : ce qui ne colle pas aux papiers pèse sur la suspicion.
    if ((_u getVariable ["comspec_sse_idCoverOn", false]) || {(_d getOrDefault ["kind", ""]) isEqualTo "cover"}) then {
        (["score", [_u]] call comspec_sse_fnc_idCover) params [["_sc", 100]];
        if (_sc < 70) then { [_s, "couverture peu crédible", round ((70 - _sc) * 0.6)] call _bump; };
    };
    private _pts = createHashMapFromArray [["doute", 15], ["expire", 15], ["signale", 30], ["inconnu", 45], ["faux", 50], ["vole", 45], ["revoque", 45], ["recherche", 70], ["hvt", 90]];
    private _best = 0;
    private _worst = "ras";
    {
        private _res = [_d, _g, _x] call comspec_sse_fnc_idEvaluate;
        private _p = _pts getOrDefault [_res getOrDefault ["verdict", "ras"], 0];
        if (_p > _best) then { _best = _p; _worst = _res get "verdict"; };
        ["comspec_sse_idChecked", [_g, _d get "id", _x, _res]] call CBA_fnc_localEvent;
    } forEach (_s get "levels");
    if (_best > 0) then { [_s, format ["papiers : %1", _worst], _best] call _bump; };
    private _sev = _cp get "severity";
    // Sans liaison base : liste des recherchés imprimée au poste, comparée au nom du document.
    if !(3 in (_s get "levels")) then {
        private _L = toUpper (_d get "last");
        private _i = ((missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) getOrDefault ["watch", []]) findIf { (toUpper (_x param [0, ""])) isEqualTo _L && {((_x param [5, []]) isEqualTo []) || {(["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (_cp get "side")) max 0)) in (_x param [5, []])}} };
        if (_i >= 0 && {(random 1) < ([0.5, 0.75, 0.9] select _sev)}) then {
            private _lvl = (((missionNamespace getVariable ["comspec_sse_idReg", createHashMap]) get "watch") select _i) param [3, "surveillance"];
            [_s, "nom sur la liste des recherchés", [40, 80] select (_lvl isEqualTo "hvt")] call _bump;
            _worst = ["recherche", "hvt"] select (_lvl isEqualTo "hvt");
        };
    };
    // BOLO : numéro du document sur la liste diffusée, visage joint au BOLO (même sous un autre nom).
    (["docFlag", [_d]] call comspec_sse_fnc_idHvt) params [["_bRef", ""], ["_bLvl", ""]];
    if (_bRef isNotEqualTo "" && {(random 1) < ([0.6, 0.8, 0.95] select _sev)}) then {
        [_s, format ["document sur le BOLO %1", _bRef], [30, 70] select (_bLvl isNotEqualTo "surveillance")] call _bump;
        if (_worst in ["ras", "doute", "expire", "signale"]) then { _worst = ["signale", "recherche"] select (_bLvl isNotEqualTo "surveillance"); };
    };
    (["faceFlag", [_u]] call comspec_sse_fnc_idHvt) params [["_fRef", ""], ["_fLvl", ""]];
    if (_fRef isNotEqualTo "" && {(random 1) < ([0.2, 0.35, 0.5] select _sev)}) then {
        [_s, format ["visage du BOLO %1", _fRef], [40, 80] select (_fLvl isEqualTo "hvt")] call _bump;
        _worst = ["recherche", "hvt"] select (_fLvl isEqualTo "hvt");
    };
    // IA contrôlée : quelques questions de légende, réponses plus ou moins cohérentes selon sa couverture.
    if (!isPlayer _u && {missionNamespace getVariable ["comspec_sse_id_questions", true]}) then {
        private _bad = { !((["aiAnswer", [_u, _x]] call comspec_sse_fnc_idLegend) select 1) } count ["origin", "employer", "travel"];
        if (_bad > 0) then { [_s, "réponses incohérentes", 15 * _bad] call _bump; };
    };
    // Visage d'une personne fichée (avis affichés au poste), même sous une couverture parfaite.
    if ((toLower (_u getVariable ["COMSPEC_SSE_BioDb", "auto"])) in ["bewl1", "bewl2"] && {(random 1) < ([0.1, 0.2, 0.3] select _sev)}) then {
        [_s, "visage reconnu (avis de recherche)", 40] call _bump;
    };
    if (_worst in ["faux", "inconnu", "vole", "revoque", "recherche", "hvt"]) then {
        ["add", [["DÉTECTION", "HVT"] select (_worst isEqualTo "hvt"), format ["%1 : %2 au checkpoint %3", [_s] call _who, toUpper _worst, _cp get "name"], format ["cpdet|%1|%2", _d get "id", _worst]]] call comspec_sse_fnc_idAar;
    };
};
// Niveaux que l'équipement du poste permet sur ce document.
private _levelsFor = {
    params ["_cp", "_d"];
    private _e = _cp get "equip";
    private _l = [0];
    if (_e >= 1 && {(_d getOrDefault ["type", ""]) in ["id_card", "passport", "driving_license", "military_id"]}) then { _l pushBack 1; };
    if (_e >= 2 && {_d getOrDefault ["chipExpected", false]}) then { _l pushBack 2; };
    if (_e >= 3) then { _l pushBack 3; };
    _l
};
private _startPlayer = {
    params ["_cp", "_u"];
    private _g = [_cp, _u] call _nearestGuard;
    private _delay = missionNamespace getVariable ["comspec_sse_id_cpDelay", 45];
    private _s = createHashMapFromArray [["unit", _u], ["stage", "halt"], ["t", time], ["deadline", time + _delay], ["susp", 0], ["pts", createHashMap], ["guard", _g], ["doc", ""], ["levels", []], ["searched", false]];
    (_cp get "subj") set [netId _u, _s];
    { [_s, _x select 0, _x select 1] call _bump; } forEach ([_u] call _visible);
    // Image suivante : hors du contexte d'un appel distant (Zeus « Déclencher un contrôle »), sinon aiAsk serait refusé.
    [{ ["aiAsk", _this] call comspec_sse_fnc_idServer; }, [_g, _u]] call CBA_fnc_execNextFrame;
    [_g, "approach", [_u]] call _do;
    [_g, "gesture", ["GestureFreeze"]] call _do;
    [_u, format ["Checkpoint %1 : halte ! Papiers, s'il vous plaît. Approchez-vous du garde et présentez-les (menu ACE personnel › Mes papiers), vous avez %2 s.", _cp get "name", round _delay], "WARNING"] call _tell;
};
private _startAi = {
    params ["_cp", "_u", ["_forced", false]];
    _cp set ["amb", time + 20];
    private _g = [_cp, _u] call _nearestGuard;
    [_g, "watch", [_u]] call _do;
    [_g, "gesture", ["GestureFreeze"]] call _do;
    private _ans = [_u, _g] call comspec_sse_fnc_idAiAnswer;
    private _doc = _ans getOrDefault ["docId", ""];
    private _s = createHashMapFromArray [["unit", _u], ["stage", "ai"], ["t", time], ["susp", 0], ["pts", createHashMap], ["guard", _g], ["doc", _doc], ["ans", _ans], ["searched", false],
        ["levels", [_cp, ["doc", [_doc]] call comspec_sse_fnc_idRegistry] call _levelsFor]];
    _s set ["deadline", time + 3 + (_ans getOrDefault ["delay", 0]) + 1.5 * (count (_s get "levels"))];
    (_cp get "subj") set [netId _u, _s];
};

switch (_op) do {
    case "presented": {
        _a params [["_h", objNull], ["_id", ""], ["_to", objNull]];
        if (isNull _h || {!isPlayer _h}) exitWith {};
        {
            private _cp = _x;
            private _s = (_cp get "subj") getOrDefault [netId _h, createHashMap];
            if ((count _s) > 0 && {(_s get "stage") isEqualTo "halt"} && {_to in (_cp get "guards")}) exitWith {
                private _d = ["doc", [_id]] call comspec_sse_fnc_idRegistry;
                _s set ["guard", _to];
                _s set ["doc", _id];
                _s set ["levels", [_cp, _d] call _levelsFor];
                _s set ["stage", "check"];
                _s set ["t", time];
                _s set ["deadline", time + 2 + 2 * (count (_s get "levels"))];
                [_to, "watch", [_h]] call _do;
                [_h, format [[
                    "Checkpoint %1 : le garde examine votre document à l'œil.",
                    "Checkpoint %1 : le garde examine votre document et lit sa bande MRZ.",
                    "Checkpoint %1 : le garde examine votre document, lit sa bande MRZ et sa puce.",
                    "Checkpoint %1 : le garde examine votre document et interroge la base."
                ] select (_cp get "equip"), _cp get "name"]] call _tell;
            };
        } forEach comspec_sse_idCps;
    };
    case "answered": {
        _a params [["_h", objNull], ["_asker", objNull], ["_ok", false], ["_k", ""], ["_ctx", ""], ["_evasive", false]];
        if (isNull _h) exitWith {};
        {
            private _cp = _x;
            private _s = (_cp get "subj") getOrDefault [netId _h, createHashMap];
            if ((count _s) > 0 && {(_s get "stage") isEqualTo "quiz"} && {_ctx isEqualTo format ["cp%1", _cp get "id"]}) exitWith {
                _s set ["qPending", ((_s getOrDefault ["qPending", 1]) - 1) max 0];
                if (_evasive) exitWith { [_s, "ne répond pas aux questions", 15, 45] call _bump; };
                if (!_ok) then { [_s, "réponses qui ne collent pas aux papiers", 20, 60] call _bump; };
            };
        } forEach comspec_sse_idCps;
    };
    case "refused": {
        _a params [["_h", objNull], ["_to", objNull]];
        if (isNull _h || {!isPlayer _h}) exitWith {};
        {
            private _cp = _x;
            private _s = (_cp get "subj") getOrDefault [netId _h, createHashMap];
            if ((count _s) > 0 && {(_s get "stage") isEqualTo "halt"} && {_to in (_cp get "guards")}) exitWith {
                [_s, "refus de présenter ses papiers", 35] call _bump;
                [_cp, _s] call _decide;
            };
        } forEach comspec_sse_idCps;
    };

    case "tick": {
        {
            private _cp = _x;
            call {
                private _guards = (_cp get "guards") select { alive _x && {!(_x getVariable ["ACE_isUnconscious", false])} && {!(_x getVariable ["ace_captives_isHandcuffed", false])} };
                _cp set ["guards", _guards];
                if (_guards isEqualTo []) exitWith { if (_cp get "temp") then { _cp set ["dead", true]; }; };
                private _pos = _cp get "pos";
                private _r = _cp get "radius";
                private _subj = _cp get "subj";
                private _cleared = _cp get "cleared";
                private _only = _cp get "only";
                private _cs = _cp get "side";
                private _men = [];
                {
                    if (_x isKindOf "CAManBase") then { _men pushBack _x; } else { _men append ((crew _x) select { alive _x }); };
                } forEach (_pos nearEntities [["CAManBase", "LandVehicle"], _r]);
                // Nouvelles personnes dans la zone.
                {
                    private _u = _x;
                    if (alive _u && {!(_u in _guards)} && {!((netId _u) in _subj)} && {(_cleared getOrDefault [netId _u, 0]) < time} && {_only isEqualTo [] || {_u in _only}}
                        && {!(_u getVariable ["ace_captives_isHandcuffed", false])}) then {
                        private _forced = _u in _only;
                        if (isPlayer _u) then {
                            if (_forced || {_u getVariable ["comspec_sse_idCoverOn", false]} || {(side group _u) isNotEqualTo _cs && {!([side group _u, _cs] call BIS_fnc_sideIsEnemy)}}) then { [_cp, _u] call _startPlayer; };
                        } else {
                            if (_forced || {(missionNamespace getVariable ["comspec_sse_id_cpAmbient", true]) && {time >= (_cp getOrDefault ["amb", 0])}
                                && {(side group _u) isNotEqualTo _cs} && {!([side group _u, _cs] call BIS_fnc_sideIsEnemy)}
                                && {!(_u in (missionNamespace getVariable ["comspec_sse_idFsmList", []]))} && {(vehicle _u) isEqualTo _u}}) then {
                                [_cp, _u, _forced] call _startAi;
                            };
                        };
                    };
                } forEach _men;
                // Suivi des contrôles en cours.
                {
                    private _s = _y;
                    private _u = _s get "unit";
                    call {
                        if (isNull _u || {!alive _u}) exitWith { _s set ["end", true]; };
                        private _g = _s get "guard";
                        if (isNull _g || {!alive _g}) then { _g = [_cp, _u] call _nearestGuard; _s set ["guard", _g]; };
                        private _stage = _s get "stage";
                        private _dist = _u distance _pos;
                        if (_stage isEqualTo "ai") exitWith {
                            // IA : fuite (automate) = alerte ; sinon examen à l'échéance.
                            private _st = ["state", [_u]] call comspec_sse_fnc_idAiFsm;
                            if (_st in ["fuit", "agressif"]) exitWith { [_s, ["fuite au contrôle", "menace un garde"] select (_st isEqualTo "agressif"), 100] call _bump; [_cp, _s, "alert"] call _outcome; };
                            if (time < (_s get "deadline")) exitWith {};
                            private _ans = (_s get "ans") getOrDefault ["answer", "none"];
                            switch (_ans) do {
                                case "present": { [_cp, _s] call _examine; };
                                case "refuse": { [_s, "refus de présenter ses papiers", 35] call _bump; };
                                case "none": { [_s, "sans papiers", 25] call _bump; };
                                case "flee": { [_s, "fuite au contrôle", 100] call _bump; };
                            };
                            [_cp, _s] call _decide;
                        };
                        // Joueur : comportement observé par les gardes, chaque seconde.
                        if (_stage in ["halt", "check", "quiz", "search", "hold"]) then {
                            private _onFoot = (vehicle _u) isEqualTo _u;
                            if (_onFoot && {(speed _u) > 14}) then { [_s, "court", 5, 20] call _bump; };
                            private _w = currentWeapon _u;
                            if (_onFoot && {_w isNotEqualTo ""} && {_w isNotEqualTo (binocular _u)} && {!(weaponLowered _u)}) then {
                                [_s, "arme à la main", 5, 25] call _bump;
                                private _dir = _u weaponDirection _w;
                                if ((_cp get "guards") findIf { (_x distance _u) < 60 && {(_dir vectorDotProduct (vectorNormalized ((aimPos _x) vectorDiff (eyePos _u)))) > 0.985} } >= 0) then {
                                    if !(_s getOrDefault ["threat", false]) then { _s set ["threat", true]; [_s, "arme pointée sur un garde", 60] call _bump; };
                                };
                            };
                            if (!_onFoot && {(speed (vehicle _u)) > 8} && {(time - (_s get "t")) > 6}) then { [_s, "ne s'arrête pas", 6, 30] call _bump; };
                        };
                        // Sortie de la zone avant la fin du contrôle : fuite.
                        if (_dist > (_r * 1.5)) exitWith {
                            [_s, "fuite du checkpoint", [40, 70] select (_stage isEqualTo "hold")] call _bump;
                            private _armed = (primaryWeapon _u) isNotEqualTo "" || {(handgunWeapon _u) isNotEqualTo ""};
                            if (_armed && {(_cp get "severity") >= 2}) then { _s set ["threat", true]; };
                            if ((_cp get "severity") isEqualTo 0 && {_stage isEqualTo "halt"} && {(_s get "susp") < 40}) exitWith {
                                (_cleared) set [netId _u, time + 600]; _s set ["end", true];
                                [format ["Checkpoint %1 : %2 est passé sans s'arrêter.", _cp get "name", name _u], "info"] call _zeus;
                            };
                            [_cp, _s, ["alert", "fire"] select ((_s getOrDefault ["threat", false]) && {(_cp get "severity") >= 1})] call _outcome;
                        };
                        // Arme pointée sur un garde : réaction immédiate.
                        if (_s getOrDefault ["threat", false]) exitWith { [_cp, _s, ["alert", "fire"] select ((_cp get "severity") >= 1)] call _outcome; };
                        if (time < (_s get "deadline") && {!(_stage isEqualTo "quiz" && {(_s getOrDefault ["qPending", 0]) <= 0})}) exitWith {};
                        switch (_stage) do {
                            case "halt": { [_s, "aucun papier présenté", 30] call _bump; [_cp, _s] call _decide; };
                            case "check": {
                                [_cp, _s] call _examine;
                                // Questions de légende (réglage) : 2, 3 au poste strict ; réponses attendues avant la décision.
                                private _n = ["ask", [_s get "guard", _u, [2, 2, 3] select (_cp get "severity"), format ["cp%1", _cp get "id"]]] call comspec_sse_fnc_idQuestion;
                                if (_n isEqualType 0 && {_n > 0}) exitWith {
                                    _s set ["stage", "quiz"]; _s set ["qPending", _n]; _s set ["t", time];
                                    _s set ["deadline", time + (missionNamespace getVariable ["comspec_sse_id_questionTime", 20]) + 10 * (_n - 1) + 2];
                                    [_u, format ["Checkpoint %1 : le garde vous pose quelques questions.", _cp get "name"], "WARNING"] call _tell;
                                };
                                [_cp, _s] call _decide;
                            };
                            case "quiz": { [_cp, _s] call _decide; };
                            case "search": {
                                if ((_u distance _g) > 8) exitWith { [_s, "s'éloigne pendant la fouille", 40] call _bump; [_cp, _s] call _decide; };
                                private _wpn = (weapons _u) select { _x isNotEqualTo (binocular _u) };
                                if (_wpn isNotEqualTo []) then {
                                    [_s, "arme trouvée à la fouille", 25 * ((count _wpn) min 2)] call _bump;
                                    if (_u getVariable ["comspec_sse_idCoverOn", false]) then { ["burn", [_u, "arme trouvée à la fouille"]] call comspec_sse_fnc_idCover; };
                                };
                                // Papiers à des noms différents sur lui : identités multiples.
                                private _names = [];
                                {
                                    private _dd = ["ofClass", [_x select 0]] call comspec_sse_fnc_idRegistry;
                                    if ((count _dd) > 0) then { _names pushBackUnique toUpper format ["%1 %2", _dd get "last", _dd get "first"]; };
                                } forEach ([_u, 0] call comspec_sse_fnc_idDocsOf);
                                if ((count _names) > 1) then { [_s, "papiers à plusieurs noms", 45] call _bump; };
                                [_u, format ["Checkpoint %1 : fouille terminée.", _cp get "name"]] call _tell;
                                [_cp, _s] call _decide;
                            };
                            case "hold": {
                                [_cp, _s, "pass"] call _outcome;
                                [format ["Checkpoint %1 : %2 relâché après 3 minutes.", _cp get "name", [_s] call _who], "info"] call _zeus;
                            };
                        };
                    };
                } forEach _subj;
                { if ((_subj get _x) getOrDefault ["end", false]) then { _subj deleteAt _x; }; } forEach (keys _subj);
                // Poste provisoire (Zeus) : retiré une fois son contrôle fini.
                if ((_cp get "temp") && {(count _subj) isEqualTo 0} && {(time - (_cp get "born")) > 10}) then { _cp set ["dead", true]; };
            };
        } forEach comspec_sse_idCps;
        if ((comspec_sse_idCps findIf { _x getOrDefault ["dead", false] }) >= 0) then {
            {
                if (_x getOrDefault ["dead", false]) then { { [_x, "post", []] call _do; } forEach (_x get "guards"); };
            } forEach comspec_sse_idCps;
            comspec_sse_idCps = comspec_sse_idCps select { !(_x getOrDefault ["dead", false]) };
        };
    };

    case "create": {
        _a params [["_pos", [0, 0, 0]], ["_spec", createHashMap], ["_by", objNull]];
        if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
        private _r = _spec getOrDefault ["radius", -1];
        if (_r <= 0) then { _r = missionNamespace getVariable ["comspec_sse_id_cpRadius", 25]; };
        private _side = [_spec getOrDefault ["side", "auto"]] call _sideOf;
        private _guards = (_spec getOrDefault ["guards", []]) select { alive _x && {!isPlayer _x} && {_x isKindOf "CAManBase"} };
        if (_guards isEqualTo []) then {
            private _ais = ((_pos nearEntities ["CAManBase", _r + 15]) select { alive _x && {!isPlayer _x} && {_side isNotEqualTo sideUnknown || {(side group _x) isNotEqualTo civilian}} }) apply { [_x distance _pos, _x] };
            _ais sort true;
            _ais = _ais apply { _x select 1 };
            if (_side isEqualTo sideUnknown && {_ais isNotEqualTo []}) then { _side = side group (_ais select 0); };
            _guards = _ais select { (side group _x) isEqualTo _side };
        };
        if (_guards isEqualTo []) exitWith { ["Checkpoint IA : aucune IA du camp choisi pour tenir le poste dans la zone. Placez des soldats, puis reposez le module.", "warn"] };
        if (_side isEqualTo sideUnknown) then { _side = side group (_guards select 0); };
        // Poste de chaque garde, public : l'ordre « retour au poste » s'exécute là où il est local (serveur, HC).
        { if (isNil { _x getVariable "comspec_sse_idPost" }) then { _x setVariable ["comspec_sse_idPost", getPosATL _x, true]; }; } forEach _guards;
        private _n = (missionNamespace getVariable ["comspec_sse_idCpSeq", 0]) + 1;
        missionNamespace setVariable ["comspec_sse_idCpSeq", _n];
        private _name = trim (_spec getOrDefault ["name", ""]);
        if (_name isEqualTo "") then { _name = format ["CP%1", _n]; };
        private _cp = createHashMapFromArray [
            ["id", _n], ["name", _name], ["pos", _pos], ["radius", _r], ["side", _side],
            ["equip", ((round (_spec getOrDefault ["equip", 1])) max 0) min 3], ["severity", ((round (_spec getOrDefault ["severity", 1])) max 0) min 2],
            ["guards", _guards], ["temp", _spec getOrDefault ["temp", false]], ["only", _spec getOrDefault ["only", []]],
            ["subj", createHashMap], ["cleared", createHashMap], ["amb", time + 10], ["born", time]
        ];
        comspec_sse_idCps pushBack _cp;
        ["init"] call comspec_sse_fnc_idCheckpoint;
        [format ["Checkpoint IA « %1 » : %2 garde(s), %3 m, équipement : %4, sévérité %5.", _name, count _guards, round _r, _equipName select (_cp get "equip"), _sevName select (_cp get "severity")], "info"]
    };

    case "trigger": {
        _a params [["_u", objNull], ["_spec", createHashMap], ["_by", objNull]];
        if (isNull _u || {!(_u isKindOf "CAManBase")} || {!alive _u}) exitWith { ["Placez le module sur une personne vivante.", "warn"] };
        private _i = comspec_sse_idCps findIf { (_u distance (_x get "pos")) <= ((_x get "radius") * 1.5) };
        if (_i >= 0) exitWith {
            private _cp = comspec_sse_idCps select _i;
            (_cp get "subj") deleteAt (netId _u);
            (_cp get "cleared") deleteAt (netId _u);
            if (isPlayer _u) then { [_cp, _u] call _startPlayer; } else { [_cp, _u, true] call _startAi; };
            [format ["Contrôle de %1 déclenché au checkpoint %2.", name _u, _cp get "name"], "info"]
        };
        // Pas de checkpoint : l'IA armée la plus proche (80 m), d'un autre groupe, l'arrête (poste provisoire).
        private _ais = ((_u nearEntities ["CAManBase", 80]) select { alive _x && {!isPlayer _x} && {_x isNotEqualTo _u} && {(group _x) isNotEqualTo (group _u)} && {(side group _x) isNotEqualTo civilian} }) apply { [_x distance _u, _x] };
        _ais sort true;
        if (_ais isEqualTo []) exitWith { ["Aucune IA (hors civils) à moins de 80 m pour faire le contrôle.", "warn"] };
        private _g = (_ais select 0) select 1;
        private _spec2 = +_spec;
        _spec2 set ["side", side group _g];
        _spec2 set ["guards", (units group _g) select { alive _x && {!isPlayer _x} && {(_x distance _g) < 40} }];
        _spec2 set ["radius", ((_g distance _u) + 15) max 20];
        _spec2 set ["temp", true];
        _spec2 set ["only", [_u]];
        _spec2 set ["name", format ["contrôle %1", groupId group _g]];
        private _r = ["create", [getPosATL _g, _spec2, _by]] call comspec_sse_fnc_idCheckpoint;
        [format ["%1 contrôle %2. %3", name _g, name _u, _r select 0], _r select 1]
    };

    case "remove": {
        _a params [["_pos", [0, 0, 0]]];
        if (_pos isEqualType objNull) then { _pos = getPosATL _pos; };
        private _l = comspec_sse_idCps apply { [(_x get "pos") distance _pos, _x] };
        _l sort true;
        if (_l isEqualTo [] || {((_l select 0) select 0) > 200}) exitWith { ["Aucun checkpoint IA à moins de 200 m.", "warn"] };
        private _cp = (_l select 0) select 1;
        { private _u = _y get "unit"; if (!isNull _u && {!isPlayer _u}) then { ["release", [_u]] call comspec_sse_fnc_idAiFsm; }; } forEach (_cp get "subj");
        _cp set ["dead", true];
        [format ["Checkpoint IA « %1 » retiré.", _cp get "name"], "info"]
    };
};
