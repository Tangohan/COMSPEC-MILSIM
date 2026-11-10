/*
    Texte libre avec les IA (SERVEUR). Appel des postes : ["send", [op, args]] call comspec_sse_fnc_iarpClient.
    Le demandeur est retrouvé par remoteExecutedOwner (jamais cru sur parole), anti-flood par poste et par opération.
      say      [moi, ia, texte, commande "" | me | do | try | todo, nom de profil Steam]
               bulle aux joueurs proches (pas à l'émetteur), journal, puis réaction de l'IA à moins de 5 m (à pied) :
               intention reconnue (fn_iarpIntent), réponse tirée de SA couverture (légende de l'identité SSE et faits de
               l'atelier IA), état de l'automate des IA réalistes (fn_idAiFsm : nerveux, refuse, fuit…), mémoire courte
               de l'échange (pas de redite ; une IA menteuse peut se contredire), ordres simples, /try (tirage serveur
               commun), /me à règles (lever les mains, braquer, tendre de l'argent, montrer un badge).
               Plusieurs demandes dans un message (« bonjour, vos papiers ») : traitées dans l'ordre, une seule bulle de
               réponse ; papiers ou fouille : l'action ACE part après la réponse.
      typing   [moi, vrai | faux]           indicateur « … » au-dessus de ma tête pour les joueurs proches
      log      [moi]                        journal : mes échanges ; Zeus et administrateur : tous
      profileGet [moi, ia] · profileSet [moi, [ia…], profil texte, "apply" | "generate"] · puppet [moi, ia, texte]
                                            Zeus (atelier IA) : lire, appliquer, générer, faire parler une IA
    Papiers et fouille : l'action ACE habituelle (barre de progression, distance) part du poste (fn_iarpUi) ; ici, rien
    n'est contourné (aucune demande de papiers, aucune fouille sans l'action, rien vers Athena).
    Évènement CBA serveur : comspec_sse_iarpExchange [ligne du journal].
    Point d'accroche réservé (désactivé) : missionNamespace comspec_sse_iarpGenHook (code serveur) peut remplacer le
    générateur local ; rien ne le pose dans ce mod, aucune requête réseau n'en part.
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
if (!isServer) exitWith {};
if !(missionNamespace getVariable ["comspec_sse_iarp_enabled", true]) exitWith {};
private _owner = [clientOwner, remoteExecutedOwner] select isRemoteExecuted;
_args params [["_me", objNull, [objNull]]];
if (isNull _me) exitWith {};
if (isMultiplayer && {isRemoteExecuted} && {(owner _me) isNotEqualTo _owner}) exitWith {
    diag_log text format ["[COMSPEC SSE] IA RP : « %1 » refusé au poste %2 (unité d'un autre poste).", _op, _owner];
};
private _flood = missionNamespace getVariable ["comspec_sse_iarpFlood", createHashMap];
missionNamespace setVariable ["comspec_sse_iarpFlood", _flood];
private _fk = format ["%1|%2", _owner, _op];
if (isRemoteExecuted && {(time - (_flood getOrDefault [_fk, -10])) < ([0.5, 0.8] select (_op isEqualTo "say"))}) exitWith {};
_flood set [_fk, time];

private _range = missionNamespace getVariable ["comspec_sse_iarp_range", 20];
private _send = {
    params ["_unit", "_cop", "_ca"];
    if (isNull _unit) exitWith {};
    if (local _unit && {hasInterface}) then { [_cop, _ca] call comspec_sse_fnc_iarpClient; } else { [_cop, _ca] remoteExecCall ["comspec_sse_fnc_iarpClient", owner _unit]; };
};
private _reply = {
    params ["_cop", "_ca"];
    if (_owner isEqualTo clientOwner || {!isMultiplayer}) then { [_cop, _ca] call comspec_sse_fnc_iarpClient; } else { [_cop, _ca] remoteExecCall ["comspec_sse_fnc_iarpClient", _owner]; };
};
// Joueurs qui entendent (à portée de bulle, vivants), sauf l'émetteur s'il est donné.
private _hearers = {
    params ["_src", ["_skip", objNull]];
    private _pl = allPlayers select { !(_x isKindOf "HeadlessClient_F") };
    if (hasInterface && {!isNull player}) then { _pl pushBackUnique player; };
    _pl select { alive _x && {_x isNotEqualTo _skip} && {(_x distance _src) <= _range} }
};
private _tell = { params ["_list", "_cop", "_ca"]; { [_x, _cop, _ca] call _send; } forEach _list; };
private _nameAi = { params ["_u"]; _u getVariable ["comspec_sse_idName", ""] };
private _log = {
    params ["_dir", "_ai", "_cmd", "_text", "_intent", "_outcome", ["_steam", ""]];
    if !(missionNamespace getVariable ["comspec_sse_iarp_log", true]) exitWith {};
    private _list = missionNamespace getVariable ["comspec_sse_iarpLog", []];
    private _seq = (missionNamespace getVariable ["comspec_sse_iarpLogSeq", 0]) + 1;
    missionNamespace setVariable ["comspec_sse_iarpLogSeq", _seq];
    private _st = systemTime;
    private _real = format ["%1-%2-%3 %4:%5:%6", _st select 0, [_st select 1, 2] call CBA_fnc_formatNumber, [_st select 2, 2] call CBA_fnc_formatNumber,
        [_st select 3, 2] call CBA_fnc_formatNumber, [_st select 4, 2] call CBA_fnc_formatNumber, [_st select 5, 2] call CBA_fnc_formatNumber];
    private _row = [_seq, [daytime, "HH:MM"] call BIS_fnc_timeToString, _real, _dir, name _me, _steam,
        [[_ai] call _nameAi, ""] select (isNull _ai), [netId _ai, ""] select (isNull _ai), mapGridPosition _me,
        [round (_me distance _ai), -1] select (isNull _ai), _cmd, _text, _intent, _outcome, netId _me];
    _list pushBack _row;
    private _max = round (missionNamespace getVariable ["comspec_sse_iarp_logMax", 500]);
    if ((count _list) > _max) then { _list deleteRange [0, (count _list) - _max]; };
    missionNamespace setVariable ["comspec_sse_iarpLog", _list];
    ["comspec_sse_iarpExchange", [_row]] call CBA_fnc_localEvent;
    // Zeus : chaque ligne en direct (onglet JOURNAL du terminal, module « Journal des échanges »).
    if (missionNamespace getVariable ["comspec_sse_iarp_zeusFeed", true]) then {
        { private _c = getAssignedCuratorUnit _x; if (!isNull _c && {isPlayer _c || {_c isEqualTo player}}) then { [_c, "zeus", [_row]] call _send; }; } forEach allCurators;
    };
    _row
};
private _sanitize = {
    params ["_t"];
    private _max = round (missionNamespace getVariable ["comspec_sse_iarp_maxLen", 600]);
    // Caractères de contrôle (retours compris) remplacés par des espaces ; coupe sur les points de code.
    private _cp = (toArray _t) apply { [_x, 32] select (_x < 32 || {_x isEqualTo 127}) };
    trim (toString (_cp select [0, _max]))
};

switch (_op) do {
    case "typing": {
        _args params ["", ["_on", false, [false]]];
        if !(missionNamespace getVariable ["comspec_sse_iarp_typing", true]) exitWith {};
        [[_me, _me] call _hearers, "typing", [_me, _on]] call _tell;
    };

    case "log": {
        private _all = missionNamespace getVariable ["comspec_sse_iarpLog", []];
        private _mine = if ([_me] call comspec_sse_fnc_isAuthority) then { _all } else { private _n = netId _me; _all select { (_x select 14) isEqualTo _n } };
        ["log", [_mine select [((count _mine) - 300) max 0]]] call _reply;
    };

    case "profileGet": {
        if !([_me] call comspec_sse_fnc_isAuthority) exitWith {};
        _args params ["", ["_t", objNull, [objNull]]];
        if (isNull _t) exitWith {};
        ["profile", [_t, ["serialize", ["get", _t] call comspec_sse_fnc_iarpProfile] call comspec_sse_fnc_iarpProfile]] call _reply;
    };

    case "profileSet": {
        if !([_me] call comspec_sse_fnc_isAuthority) exitWith {
            diag_log text format ["[COMSPEC SSE] IA RP : profil refusé au poste %1 (réservé au Zeus).", _owner];
        };
        _args params ["", ["_units", [], [[]]], ["_raw", "", [""]], ["_mode", "apply", [""]]];
        private _p = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
        if ((count _p) isEqualTo 0) exitWith { ["notice", ["STR_COMSPEC_IARP_N_BadProfile"]] call _reply; };
        private _n = 0;
        {
            if (_x isEqualType objNull && {!isNull _x} && {_x isKindOf "CAManBase"} && {!isPlayer _x}) then {
                private _q = if (_mode isEqualTo "generate") then { ["generate", [_p, _p get "locks"]] call comspec_sse_fnc_iarpProfile } else { +_p };
                if (["apply", [_x, _q]] call comspec_sse_fnc_iarpProfile) then { _n = _n + 1; };
            };
        } forEach _units;
        ["notice", ["STR_COMSPEC_IARP_N_Applied", _n]] call _reply;
    };

    case "puppet": {
        if !([_me] call comspec_sse_fnc_isAuthority) exitWith {};
        _args params ["", ["_t", objNull, [objNull]], ["_text", "", [""]]];
        _text = [_text] call _sanitize;
        if (isNull _t || {_text isEqualTo ""}) exitWith {};
        private _pay = [_text, _text, createHashMap, "", ""];
        [[_t] call _hearers, "bubble", [_t, "ai", [_t] call _nameAi, _pay]] call _tell;
        ["ZEUS>IA", _t, "", _text, "zeus", "réplique du Zeus"] call _log;
    };

    case "say": {
        _args params ["", ["_t", objNull, [objNull]], ["_raw", "", [""]], ["_cmd", "", [""]], ["_steam", "", [""]]];
        if !(_cmd in ["", "me", "do", "try", "todo"]) then { _cmd = ""; };
        private _text = [_raw] call _sanitize;
        if (_text isEqualTo "") exitWith {};
        if (!alive _me || {!isNull objectParent _me}) exitWith {};
        // L'IA visée : vivante, pas un joueur, à 5 m (plus une marge de latence).
        if (isNull _t || {!alive _t} || {isPlayer _t} || {!(_t isKindOf "CAManBase")} || {(_me distance _t) > 6.5}) exitWith {
            ["notice", ["STR_COMSPEC_IARP_N_TooFar"]] call _reply;
        };
        private _steamName = (_steam select [0, 64]);
        // /try : tirage du serveur, le même résultat pour tous, émetteur compris.
        if (_cmd isEqualTo "try") exitWith {
            private _ok = (random 100) < (missionNamespace getVariable ["comspec_sse_iarp_tryChance", 50]);
            [[_me] call _hearers, "bubble", [_me, ["tryKo", "tryOk"] select _ok, name _me, _text]] call _tell;
            ["P", _t, "try", _text, "try", ["ÉCHEC", "RÉUSSI"] select _ok, _steamName] call _log;
            ["ack", [_t, "try", ["fail", "success"] select _ok]] call _reply;
        };
        // Ma ligne : bulle au-dessus de ma tête pour les joueurs proches (je la lis déjà dans le terminal).
        [[_me, _me] call _hearers, "bubble", [_me, ["say", _cmd] select (_cmd isNotEqualTo ""), name _me, _text]] call _tell;

        private _norm = _text call comspec_sse_fnc_iarpNorm;
        private _has = { params ["_words"]; (_words findIf { (_norm find _x) >= 0 }) >= 0 };
        // Demandes du message dans l'ordre (« bonjour, vos papiers » : salut puis papiers) ; _main : l'intention principale.
        private _list = [];
        private _main = "";
        if (_cmd isEqualTo "me") then {
            // /me : ambiance, sauf gestes que l'IA voit et comprend.
            _main = call {
                if ([[" leve les mains", " leve ses mains", " mains en l air", " raises his hands", " raises her hands", " hands up", " baisse son arme", " lowers his weapon", " lowers her weapon"]] call _has) exitWith { "calm" };
                if ([[" braque", " pointe son arme", " pointe son fusil", " pointe son pistolet", " met en joue", " aims ", " points his weapon", " points her weapon", " points a gun"]] call _has) exitWith { "threat" };
                if ([[" billet", " argent", " dollars", " euros", " liasse", " money", " cash", " banknote", " bribe"]] call _has) exitWith { "bribe" };
                if ([[" badge", " carte de police", " insigne", " carte professionnelle", " shows his badge", " shows her badge", " his badge", " her badge"]] call _has) exitWith { "badge" };
                ""
            };
            if (_main isNotEqualTo "") then { _list = [_main]; };
        } else {
            if (_cmd isEqualTo "") then {
                ([_text] call comspec_sse_fnc_iarpIntent) params ["_i", "", "", ["_l", []]];
                if (_i isNotEqualTo "unknown") then { _main = _i; _list = _l apply { _x select 0 }; } else { _main = "unknown"; _list = ["unknown"]; };
            };
        };
        private _p = ["get", _t] call comspec_sse_fnc_iarpProfile;
        private _sens = ((_p get "sensitive") apply { trim (_x call comspec_sse_fnc_iarpNorm) }) select { _x isNotEqualTo "" };
        private _touchy = _cmd isEqualTo "" && {(_sens findIf { (_norm find (" " + _x)) >= 0 }) >= 0};
        if (_main isEqualTo "" && {!_touchy}) exitWith {
            ["P", _t, _cmd, _text, "", "ambiance", _steamName] call _log;
            ["ack", [_t, "", "ambient"]] call _reply;
        };
        if (_list isEqualTo []) then { _list = [""]; };
        // Papiers : l'action ACE (2 s) est lancée par le poste ; la réponse vient du contrôle d'identité habituel.
        // Fouille d'une personne rendue, menottée ou inconsciente : l'action ACE de fouille des papiers (6 s).
        // Avec une autre demande (« bonjour, vos papiers ») : l'IA répond d'abord, puis l'action ACE part.
        private _ace = "";
        {
            if (_ace isEqualTo "") then {
                if (_x isEqualTo "papers" && {!isNil "comspec_sse_fnc_idClient"}) then { _ace = "papers"; };
                if (_x isEqualTo "search" && {!isNil "comspec_sse_fnc_idCanSearch"} && {[_t] call comspec_sse_fnc_idCanSearch}) then { _ace = "search"; };
            };
        } forEach _list;
        if (_ace isNotEqualTo "") then { _list = _list - [_ace]; };
        // Oui / non glissé dans une autre demande (« d'accord, suivez-moi ») : la demande seule compte.
        if ((count _list) > 1) then { _list = _list - ["yes", "no"]; };
        if (_list isEqualTo []) exitWith {
            ["P", _t, _cmd, _text, _ace, ["demande des papiers (action ACE)", "fouille des papiers (action ACE)"] select (_ace isEqualTo "search"), _steamName] call _log;
            ["ack", [_t, _ace, "ace"]] call _reply;
        };

        // ---------------------------------------------------------------- contexte et mémoire de l'IA
        private _fsm = !isNil "comspec_sse_fnc_idAiFsm" && {missionNamespace getVariable ["comspec_sse_id_aiFsm", true]};
        if (_fsm && {(_list - ["bye"]) isNotEqualTo []}) then { ["engage", [_t, _me]] call comspec_sse_fnc_idAiFsm; };
        private _st = "";
        if (_fsm) then { _st = ["state", [_t]] call comspec_sse_fnc_idAiFsm; };
        if (isNil "_st" || {!(_st isEqualType "")}) then { _st = ""; };
        // Mémoire de l'IA (serveur seulement, fn_iarpMem) : sa couverture affirmée (la même pour tous), à qui elle a dit quoi
        // en premier, et une fiche par joueur (identifiant Steam) : confiance, déjà-dit, pot-de-vin, mot-clé, badge…
        private _mem = ["upgrade", _t getVariable ["comspec_sse_iarpMem", createHashMap]] call comspec_sse_fnc_iarpMem;
        _t setVariable ["comspec_sse_iarpMem", _mem];
        private _uid = getPlayerUID _me;
        if (_uid isEqualTo "") then { _uid = "N:" + name _me; };
        private _pm = ["player", [_mem, _uid, name _me, side group _me, _p get "honesty", time]] call comspec_sse_fnc_iarpMem;
        private _trust = { params ["_d"]; _pm set ["trust", (((_pm get "trust") + _d) max 0) min 100]; };
        // Sujet déjà raconté à un autre joueur : « Je l'ai déjà dit à votre collègue. » (une fois par sujet et par joueur).
        private _told = { params ["_topic"]; ["told", [_mem, _uid, side group _me, _topic, random 1]] call comspec_sse_fnc_iarpMem };
        private _tense = { params ["_d"]; _t setVariable ["comspec_sse_idTension", (((_t getVariable ["comspec_sse_idTension", 20]) + _d) max 0) min 100]; };
        private _want = { params ["_s"]; if (_fsm) then { _t setVariable ["comspec_sse_idWant", _s]; }; };
        private _do = {
            params ["_cmdAi", ["_a", []]];
            if !(missionNamespace getVariable ["comspec_sse_iarp_orders", true]) exitWith {};
            // Un autre ordre met fin au suivi d'un « stop » (fn_iarpReact « halt »).
            if (_cmdAi in ["follow", "free", "surrender", "out", "flee"]) then { _t setVariable ["comspec_sse_iarpHaltId", (_t getVariable ["comspec_sse_iarpHaltId", 0]) + 1]; };
            ["comspec_sse_iarpAiDo", [_t, _cmdAi, _a], _t] call CBA_fnc_targetEvent;
            if (_cmdAi isEqualTo "stop") then { ["halt", [_t, _me]] call comspec_sse_fnc_iarpReact; };
        };
        private _w = _p get "unlockWord";
        if (_w isNotEqualTo "" && {(_norm find (" " + trim (_w call comspec_sse_fnc_iarpNorm))) >= 0}) then { _pm set ["word", true]; };

        // Légende (identité SSE) puis faits de l'atelier IA, qui l'emportent.
        private _L = createHashMap;
        if (!isNil "comspec_sse_fnc_idLegend") then { _L = ["get", [_t]] call comspec_sse_fnc_idLegend; };
        if !(_L isEqualType createHashMap) then { _L = createHashMap; };
        private _facts = createHashMap;
        {
            _x params ["_fk2", "_lk"];
            private _v = _L getOrDefault [_lk, ""];
            if (_v isEqualType "" && {_v isNotEqualTo ""}) then { _facts set [_fk2, _v]; };
        } forEach [["city", "city"], ["employer", "employer"], ["job", "profession"], ["travel", "travel"], ["pob", "pob"], ["address", "address"], ["family", "family"], ["name", "name"]];
        private _ct = ((_L getOrDefault ["contacts", []]) param [0, []]) param [0, ""];
        if (_ct isEqualType "" && {_ct isNotEqualTo ""}) then { _facts set ["contact", _ct]; };
        private _dob = _L getOrDefault ["dob", ""];
        if (_dob isEqualType "") then {
            private _yr = ((_dob splitString "./- ") select { (count _x) isEqualTo 4 }) param [0, ""];
            if (_yr isNotEqualTo "") then { _facts set ["age", str (((date select 0) - (parseNumber _yr)) max 16)]; };
        };
        { _facts set [_x, _y]; } forEach (["facts", _p] call comspec_sse_fnc_iarpProfile);
        if ((_p get "name") isNotEqualTo "") then { _facts set ["name", _p get "name"]; };
        // Prénom : le mot qui n'est pas en capitales (« KARRAS Nikos » des papiers), sinon le premier.
        private _parts = (_facts getOrDefault ["name", ""]) splitString " ";
        private _fi = _parts findIf { (toUpper _x) isNotEqualTo _x };
        _facts set ["first", if (_fi >= 0) then { _parts select _fi } else { _parts param [0, ""] }];

        // Couverture : même règle que fn_idLegend « aiAnswer » (civil presque toujours cohérent, couverture selon sa
        // préparation, faux papiers mal appris), corrigée par l'honnêteté de l'atelier (90 et plus : ne ment jamais).
        private _honest = _p get "honesty";
        private _pOk = 0.98;
        if ((count _L) > 0 && {(_L getOrDefault ["kind", "civil"]) isNotEqualTo "civil"}) then { _pOk = ((_L getOrDefault ["quality", 70]) / 100) * 0.95 + 0.04; };
        if ((count _L) isEqualTo 0 && {(_p get "role") in ["insurge", "hvt", "agent"]}) then { _pOk = 0.75; };
        _pOk = ((_pOk * (0.75 + 0.5 * _honest / 100)) min 0.99) max 0.05;
        if (_honest >= 90) then { _pOk = 1; };
        private _pools = (["presets"] call comspec_sse_fnc_iarpData) getOrDefault ["pools", createHashMap];
        private _cities = _pools getOrDefault ["cities", ["Kavala"]];
        if (!isNil "comspec_sse_fnc_idCountries") then {
            private _cc = ([] call comspec_sse_fnc_idCountries) getOrDefault [_L getOrDefault ["country", ""], createHashMap];
            if (_cc isEqualType createHashMap && {(_cc getOrDefault ["cities", []]) isNotEqualTo []}) then { _cities = _cc get "cities"; };
        };
        private _slip = false;
        private _fact = {
            params ["_k"];
            private _truth = _facts getOrDefault [_k, ""];
            if (_truth isEqualTo "") exitWith { ["", true] };
            if !(_k in ["city", "employer", "job", "travel", "pob", "contact"]) exitWith { [_truth, true] };
            private _said = _mem get "said";
            private _prev = _said getOrDefault [_k, []];
            private _wrong = {
                private _pool = (switch (_k) do {
                    case "city"; case "pob": { _cities };
                    case "employer": { (_pools getOrDefault ["employer", []]) + ["une entreprise du port", "un cousin, au noir"] };
                    case "job": { _pools getOrDefault ["job", []] };
                    case "travel": { (_pools getOrDefault ["travel", []]) + ["Je vais voir un ami", "Je me promène"] };
                    default { ["Personne"] };
                }) select { _x isNotEqualTo _truth && {_x isNotEqualTo ((_prev param [0, ""]))} };
                [_truth, selectRandom _pool] select (_pool isNotEqualTo [])
            };
            if (_prev isEqualTo []) exitWith {
                private _ok = (random 1) < _pOk;
                private _v = [call _wrong, _truth] select _ok;
                _said set [_k, [_v, _ok]];
                [_v, _ok]
            };
            _prev params ["_v0", "_ok0"];
            if (_ok0) exitWith { [_v0, true] };
            // Menteur relancé : il tient sa version, sauf s'il se trahit (moins bien préparé, plus souvent).
            if ((random 1) < ((1 - _pOk) * 0.5)) exitWith {
                _slip = true;
                private _v = [call _wrong, _truth] select ((random 1) < 0.5);
                _said set [_k, [_v, false]];
                [_v, false]
            };
            [_v0, false]
        };

        // Joueur : faction (manière de s'adresser à lui), armes pointées vers l'IA.
        private _aimed = { alive _x && {(currentWeapon _x) isNotEqualTo ""} && {!(weaponLowered _x)} && {(_x distance _t) < 30}
            && {((_x weaponDirection (currentWeapon _x)) vectorDotProduct (vectorNormalized ((aimPos _t) vectorDiff (eyePos _x)))) > 0.985} } count ((allPlayers + [_me]) arrayIntersect (allPlayers + [_me]));
        private _civ = (side group _me) isEqualTo civilian;
        private _ctx = createHashMapFromArray [
            ["state", _st], ["tension", _t getVariable ["comspec_sse_idTension", 0]], ["aimed", _aimed],
            ["wounded", (damage _t) > 0.3 || {(_t getVariable ["ace_medical_woundBleeding", 0]) > 0.02}],
            ["tired", (getFatigue _t) > 0.6], ["vehicle", !isNull objectParent _t], ["night", sunOrMoon < 0.5],
            ["checkpoint", !isNil "comspec_sse_fnc_idCheckpoint" && {["inZone", [_t]] call comspec_sse_fnc_idCheckpoint}],
            ["country", _L getOrDefault ["country", "*"]]
        ];
        private _vars = +_facts;
        _vars set ["player", name _me];
        _vars set ["know", _p get "know"];
        _vars set ["sir", selectRandom ([[["monsieur", "sir"], ["chef", "boss"]], [["monsieur", "sir"]]] select _civ)];
        private _wpn = [primaryWeapon _t, handgunWeapon _t] select ((primaryWeapon _t) isEqualTo "");
        _vars set ["weapon", [getText (configFile >> "CfgWeapons" >> _wpn >> "displayName"), ""] select (_wpn isEqualTo "")];

        // Réaction : ordres, pression, renseignement, couverture.
        private _r = (_p get "react");
        private _obeys = {
            params ["_kind"];
            private _x1 = _r getOrDefault [_kind, "auto"];
            if (_x1 in ["coopere", "rendu"]) exitWith { true };
            if (_x1 in ["refuse", "fuit", "agressif"]) exitWith { false };
            if (_st isEqualTo "rendu") exitWith { true };
            private _c = 0.45 + 0.004 * (_p get "fear") - 0.004 * (_p get "aggro") + 0.2 * (_aimed min 2) + 0.003 * ((_pm get "trust") - 30);
            if ((time - (_pm get "badge")) < 90) then { _c = _c + 0.15; };
            if (_st isEqualTo "refuse") then { _c = _c - 0.3; };
            if (_st in ["fuit", "agressif", "renforts"]) then { _c = _c - 0.35; };
            if ((_p get "role") in ["insurge", "hvt"]) then { _c = _c - 0.15; };
            (random 1) < _c
        };
        private _quiet = _st in ["fuit", "agressif", "renforts"];
        // Une réaction par demande, dans l'ordre : [réplique, ton, issue, intention].
        private _parts = [];
        {
            private _intent = _x;
            private _gi = _intent;
            private _tone = "";
            private _outcome = "";
            private _topic = "";
            call {
                if (_touchy && {!(_intent in ["threat", "bribe", "handsup", "halt", "kneel", "follow", "dismount", "trunk", "leave"])}) exitWith {
                    [15] call _tense; _gi = "sensitive"; _outcome = "sujet sensible";
                };
                if (_intent in ["handsup", "halt", "kneel", "follow", "leave", "dismount", "trunk"]) exitWith {
                    if (_intent isEqualTo "dismount" && {isNull objectParent _t}) exitWith { _gi = "dismountNone"; _outcome = "déjà à pied"; };
                    private _veh = objectParent _t;
                    if (_intent isEqualTo "trunk" && {isNull _veh}) then {
                        private _near = (nearestObjects [_t, ["LandVehicle"], 8]) select { alive _x };
                        _veh = _near param [0, objNull];
                    };
                    if (_intent isEqualTo "trunk" && {isNull _veh}) exitWith { _gi = "trunkNone"; _outcome = "aucun véhicule"; };
                    if !(["order"] call _obeys) exitWith { _gi = "noComply"; _outcome = "refuse l'ordre"; [5] call _tense; };
                    _gi = "comply";
                    _outcome = "obéit";
                    switch (_intent) do {
                        case "handsup": { ["surrender"] call _do; ["rendu"] call _want; };
                        case "halt": { ["stop"] call _do; };
                        case "kneel": { ["pos", [["MIDDLE", "DOWN"] select ([[" couch", " allonge", " a terre", " au sol", " par terre", " lie down", " ground"]] call _has)]] call _do; };
                        case "follow": { ["follow", [_me]] call _do; };
                        case "leave": { ["free"] call _do; if (_fsm) then { ["release", [_t]] call comspec_sse_fnc_idAiFsm; }; };
                        case "dismount": { ["out"] call _do; };
                        case "trunk": {
                            if (missionNamespace getVariable ["comspec_sse_iarp_orders", true]) then { ["comspec_sse_iarpAiDo", [_veh, "unlock", []], _veh] call CBA_fnc_targetEvent; };
                            _outcome = "ouvre le véhicule";
                        };
                    };
                };
                if (_quiet) exitWith { _gi = "silent"; _outcome = "ne répond pas (" + _st + ")"; };
                switch (_intent) do {
                    case "threat": {
                        [-20] call _trust;
                        [22] call _tense;
                        private _x1 = _r getOrDefault ["threat", "auto"];
                        if (_x1 isEqualTo "auto") then {
                            _x1 = call {
                                if (((_p get "fear") >= 70 || {_aimed > 0}) && {(random 1) < 0.5}) exitWith { "rendu" };
                                if ((_p get "aggro") >= 75 && {(random 1) < 0.3}) exitWith { "refuse" };
                                ""
                            };
                        };
                        if (_x1 isNotEqualTo "" && {_x1 isNotEqualTo "coopere"}) then { [_x1] call _want; if (_x1 isEqualTo "rendu") then { ["surrender"] call _do; }; };
                        _outcome = ["menace : " + _x1, "menace encaissée"] select (_x1 in ["", "coopere"]);
                        if (_x1 isEqualTo "agressif") then { _tone = "agressif"; };
                    };
                    case "bribe": {
                        private _x1 = _r getOrDefault ["bribe", "auto"];
                        private _yes = call {
                            if (_x1 isEqualTo "coopere") exitWith { true };
                            if (_x1 in ["refuse", "fuit", "agressif"]) exitWith { false };
                            if (_st isEqualTo "refuse") exitWith { false };
                            (random 100) < (_p get "bribe")
                        };
                        if (_yes) then { _pm set ["bribed", true]; _pm set ["bribes", (_pm get "bribes") + 1]; [40] call _trust; _gi = "bribeYes"; _tone = "corrompu"; _outcome = "pot-de-vin accepté"; }
                        else { [-5] call _trust; [8] call _tense; _gi = "bribeNo"; _outcome = "pot-de-vin refusé"; };
                    };
                    case "intel": {
                        private _ul = _p get "unlock";
                        private _open = switch (_ul) do {
                            case "bribe": { _pm get "bribed" };
                            case "keyword": { _pm get "word" };
                            case "never": { false };
                            default { (_pm get "trust") >= 60 || {_pm get "bribed"} };
                        };
                        if (_st isEqualTo "rendu" && {_ul isNotEqualTo "never"} && {(_pm get "trust") >= 40}) then { _open = true; };
                        if (_open && {(_p get "know") isNotEqualTo ""}) then { _gi = "intelYes"; _outcome = "renseignement donné"; _topic = "intel"; } else { _gi = "intelNo"; _outcome = ["ne sait rien", "se tait"] select ((_p get "know") isNotEqualTo ""); };
                    };
                    case "confront": {
                        // « Vos papiers sont faux », « tu mens », « je sais que c'est toi » : ment-elle vraiment ? Mensonge
                        // affirmé (couverture), faux papiers ou couverture d'agent, secret, rôle caché.
                        private _said = _mem get "said";
                        private _lies = (keys _said) select { !((_said get _x) select 1) };
                        private _guilty = _lies isNotEqualTo [] || {_t getVariable ["comspec_sse_idLiar", false]} || {(_p get "secrets") isNotEqualTo []}
                            || {(_p get "role") in ["insurge", "hvt", "agent"]} || {(count _L) > 0 && {(_L getOrDefault ["kind", "civil"]) isNotEqualTo "civil"}};
                        _pm set ["pressed", (_pm getOrDefault ["pressed", 0]) + 1];
                        if (!_guilty) exitWith {
                            [-10] call _trust; [8] call _tense;
                            _gi = "confrontInnocent"; _outcome = "accusé à tort";
                            if ((_p get "aggro") >= 60) then { _tone = "agressif"; };
                        };
                        if (_mem get "confessed") exitWith { _gi = "confessed"; _outcome = "a déjà avoué"; };
                        // Craque : peur, armes pointées, faux découvert, mensonges, insistance ; bon menteur, HVT, agent : moins.
                        private _crack = 0.1 + 0.004 * (_p get "fear") + 0.15 * (_aimed min 2) - 0.003 * (_p get "aggro") + 0.002 * _honest
                            + 0.08 * ((count _lies) min 2) + 0.08 * (((_pm get "pressed") - 1) min 3);
                        if ((_t getVariable ["comspec_sse_idBust", 0]) > 0) then { _crack = _crack + 0.25; };
                        if (_st isEqualTo "rendu") then { _crack = _crack + 0.2; };
                        _crack = _crack - ((createHashMapFromArray [["hvt", 0.25], ["agent", 0.15]]) getOrDefault [_p get "role", 0]);
                        _crack = (_crack max 0.03) min 0.9;
                        if ((random 1) < _crack) exitWith {
                            _mem set ["confessed", true];
                            [-15] call _tense; [10] call _trust;
                            // Aveu : un secret (atelier IA), sinon une ligne libre de l'histoire, sinon la vérité sur un mensonge.
                            private _free = ((_p get "story") splitString (toString [10])) select { (trim _x) isNotEqualTo "" && {(_x find ":") < 0} };
                            private _sec = ((_p get "secrets") + (_free apply { trim _x })) param [0, ""];
                            _gi = "confessVague";
                            if (_sec isNotEqualTo "") then { _gi = "confess"; _vars set ["secret", _sec]; } else {
                                if (_lies isNotEqualTo []) then {
                                    private _k = _lies select 0;
                                    private _v = _facts getOrDefault [_k, ""];
                                    private _how = (createHashMapFromArray [
                                        ["city", ["je viens de %1", "I'm from %1"]], ["employer", ["je travaille pour %1", "I work for %1"]], ["job", ["je suis %1", "I'm a %1"]],
                                        ["travel", ["%1", "%1"]], ["pob", ["je suis né à %1", "I was born in %1"]], ["contact", ["c'est %1 qui peut confirmer", "%1 is the one who can confirm"]]
                                    ]) getOrDefault [_k, ["%1", "%1"]];
                                    if (_v isNotEqualTo "") then { _gi = "confessTruth"; _vars set ["truth", [format [_how select 0, _v], format [_how select 1, _v]]]; };
                                };
                            };
                            // Plus de mensonge : la vérité remplace ce qu'elle avait affirmé, pour tous les joueurs.
                            { private _v = _facts getOrDefault [_x, ""]; if (_v isNotEqualTo "") then { _said set [_x, [_v, true]]; }; } forEach _lies;
                            _outcome = "craque et avoue";
                            // Celle qui cachait quelque chose de sérieux se rend (automate : reddition).
                            if (((_p get "role") in ["insurge", "hvt", "agent"] || {_t getVariable ["comspec_sse_idLiar", false]}) && {_st in ["interpelle", "coopere", "ment", "nerveux", "refuse"]}) then {
                                ["rendu"] call _want;
                            };
                        };
                        // Elle ment vraiment et tient bon : l'automate le sait (faux découvert : la tension monte).
                        if (_fsm) then { ["bust", [_t, _me]] call comspec_sse_fnc_idAiFsm; };
                        private _onFoot = isNull objectParent _t && {!(_t getVariable ["ace_captives_isSurrendering", false])} && {!(_t getVariable ["ace_captives_isHandcuffed", false])};
                        if ((_p get "fear") >= 60 && {(_p get "nerve") >= 50} && {_aimed isEqualTo 0} && {_onFoot} && {(random 1) < 0.5}) exitWith {
                            _gi = "confrontFlee"; _outcome = "nie puis s'enfuit"; [20] call _tense;
                            if (_fsm && {_st isNotEqualTo ""}) then { ["fuit"] call _want; } else { ["flee", [(getPosATL _t) getPos [150, _me getDir _t]]] call _do; };
                        };
                        if ((_p get "aggro") >= 60) exitWith {
                            _gi = "confrontAngry"; _tone = "agressif"; _outcome = "nie et s'énerve"; [20] call _tense; [-10] call _trust;
                            if (_st in ["interpelle", "coopere", "ment", "nerveux"]) then { ["refuse"] call _want; };
                        };
                        _gi = "confrontDeny"; _outcome = "nie"; [12] call _tense;
                        if ((_p get "fear") >= 60) then { _tone = "nerveux"; };
                    };
                    case "weapons": {
                        private _armed = _wpn isNotEqualTo "";
                        private _liar = _honest < 40 || {(_p get "role") in ["insurge", "hvt"] && {_st isNotEqualTo "rendu"}};
                        _gi = ["weaponsNo", "weaponsYes"] select (_armed && {!_liar});
                        _outcome = [["non armé", "ment : armé"] select _armed, "avoue son arme"] select (_gi isEqualTo "weaponsYes");
                    };
                    case "search": {
                        private _x1 = _r getOrDefault ["search", "auto"];
                        private _yes = call {
                            if (_x1 in ["coopere", "rendu"]) exitWith { true };
                            if (_x1 in ["refuse", "fuit", "agressif"]) exitWith { false };
                            (random 100) < (((_p get "fear") + _honest) / 2 - ([0, 25] select ((_p get "role") in ["insurge", "hvt"])))
                        };
                        [-5] call _trust;
                        // Accord : il lève les bras (reddition ACE), la fouille ACE devient possible ; sinon il refuse.
                        if (_yes) then { _gi = "searchYes"; _outcome = "accepte la fouille"; ["surrender"] call _do; } else { _gi = "searchNo"; _outcome = "refuse la fouille"; [6] call _tense; };
                    };
                    case "badge": { _pm set ["badge", time]; [10] call _trust; [5] call _tense; _outcome = "voit le badge"; };
                    case "calm": { [8] call _trust; [-12] call _tense; _outcome = "rassuré"; };
                    case "help";
                    case "greet";
                    case "thanks";
                    case "sorry";
                    case "howareyou": { [5] call _trust; _outcome = "conversation"; };
                    default {
                        if (_intent in ["origin", "employer", "job", "travel", "pob", "contact", "address", "name", "age", "family"]) then {
                            if (_st isEqualTo "refuse") exitWith { _gi = "refuse"; _outcome = "refuse de répondre"; };
                            private _k = ["city", _intent] select (_intent isNotEqualTo "origin");
                            ([_k] call _fact) params ["_v", "_ok"];
                            if (_v isEqualTo "") exitWith { _gi = "dunno"; _outcome = "ne sait pas"; };
                            _vars set [_k, _v];
                            _topic = _k;
                            if (!_ok) then {
                                // Réponse qui ne colle pas : la tension monte (comme une question de légende du menu ACE).
                                _t setVariable ["comspec_sse_idAsks", (_t getVariable ["comspec_sse_idAsks", 1]) + 1];
                                if (_k isEqualTo "contact") then { _gi = "dunno"; };
                            };
                            _outcome = [["incohérent", "se contredit"] select _slip, "cohérent"] select _ok;
                        } else {
                            _outcome = ["conversation", "incompris"] select (_intent isEqualTo "unknown");
                        };
                    };
                };
            };
            // /me sans geste compris : pas de réplique.
            if (_cmd isNotEqualTo "me" || {_gi isNotEqualTo ""}) then { _parts pushBack [_gi, _tone, _outcome, _intent, [_topic] call _told]; };
        } forEach _list;
        // Deux fois la même réplique de suite (silence, refus) : une seule.
        private _dd = [];
        { if (_dd isEqualTo [] || {((_dd select -1) select 0) isNotEqualTo (_x select 0)}) then { _dd pushBack _x; }; } forEach _parts;
        _parts = _dd;
        private _outcome = (_parts apply { _x select 2 }) select { _x isNotEqualTo "" };
        _outcome = _outcome joinString " · ";
        if (_ace isNotEqualTo "") then { _outcome = _outcome + ([" · ", ""] select (_outcome isEqualTo "")) + (["demande des papiers (action ACE)", "fouille des papiers (action ACE)"] select (_ace isEqualTo "search")); };
        private _logI = ((_list + ([[], [_ace]] select (_ace isNotEqualTo ""))) select { _x isNotEqualTo "" }) joinString "+";
        ["P", _t, _cmd, _text, _logI, _outcome, _steamName] call _log;
        // Accusé : intention principale (comme avant) ; papiers ou fouille : l'action ACE part après la réponse.
        ["ack", [_t, [_main, _ace] select (_ace isNotEqualTo ""), [_outcome, "ace"] select (_ace isNotEqualTo "")]] call _reply;
        if (_parts isEqualTo []) exitWith {};
        if ((_t getVariable ["ACE_isUnconscious", false]) || {(lifeState _t) isEqualTo "INCAPACITATED"}) exitWith {};

        // Répliques : générateur local (ou point d'accroche réservé), sans redite, mises bout à bout dans une seule bulle ;
        // seule la dernière (la vraie réponse) reçoit ouverture, hésitation et didascalie.
        private _recent = _pm get "recent";
        private _frs = [];
        private _ens = [];
        private _local = "";
        private _toneOut = "";
        private _gi = (_parts select -1) select 0;
        private _hook = missionNamespace getVariable ["comspec_sse_iarpGenHook", 0];
        {
            _x params ["_gi1", "_tone1", "", "", ["_told1", ""]];
            private _c1 = +_ctx;
            if (_tone1 isNotEqualTo "") then { _c1 set ["tone", _tone1]; };
            if (_told1 isNotEqualTo "") then { _c1 set ["told", _told1]; };
            if (_forEachIndex < ((count _parts) - 1)) then { _c1 set ["plain", true]; };
            private _gen = [];
            if (_hook isEqualType {}) then { _gen = [_gi1, _p, _c1, _recent, _vars, _text] call _hook; };
            if !(_gen isEqualType [] && {(count _gen) >= 2}) then { _gen = [_gi1, _p, _c1, _recent] call comspec_sse_fnc_iarpGen; };
            _gen params [["_fr1", ""], ["_en1", ""], ["_loc1", ""], ["_to1", ""], ["_core1", ""]];
            _frs pushBack _fr1;
            _ens pushBack _en1;
            if (_local isEqualTo "") then { _local = _loc1; };
            _toneOut = _to1;
            _recent pushBack _core1;
            if ((count _recent) > 14) then { _recent deleteAt 0; };
        } forEach _parts;
        private _fr = _frs joinString " ";
        private _en = _ens joinString " ";
        private _say = [_fr, _vars, "fr"] call comspec_sse_fnc_iarpFill;
        private _pay = [_fr, _en, _vars, _local, _L getOrDefault ["country", ""]];
        private _delay = (0.5 + random 1) + ([0, 0.7] select (_toneOut in ["nerveux", "effraye"]));
        if (missionNamespace getVariable ["comspec_sse_iarp_typing", true]) then {
            [[_t] call _hearers, "typing", [_t, true]] call _tell;
        };
        [{
            params ["_t", "_me", "_pay", "_say", "_gi", "_outcome", "_owner", "_range"];
            if (isNull _t || {!alive _t}) exitWith {};
            private _pl = (allPlayers select { !(_x isKindOf "HeadlessClient_F") });
            if (hasInterface && {!isNull player}) then { _pl pushBackUnique player; };
            private _args = [_t, "ai", _t getVariable ["comspec_sse_idName", ""], _pay];
            {
                if (alive _x && {(_x distance _t) <= _range}) then {
                    if (local _x && {hasInterface}) then { ["bubble", _args] call comspec_sse_fnc_iarpClient; } else { ["bubble", _args] remoteExecCall ["comspec_sse_fnc_iarpClient", owner _x]; };
                };
            } forEach _pl;
            private _r = ["reply", [_t, _pay, _gi]];
            if (_owner isEqualTo clientOwner || {!isMultiplayer}) then { _r call comspec_sse_fnc_iarpClient; } else { _r remoteExecCall ["comspec_sse_fnc_iarpClient", _owner]; };
            ["logAi", [_me, _t, _say, _gi, _outcome]] call comspec_sse_fnc_iarpServer;
        }, [_t, _me, _pay, _say, _gi, _outcome, _owner, _range], _delay] call CBA_fnc_waitAndExecute;
    };

    // Ligne IA -> joueur du journal, posée par le serveur lui-même à la fin de la réplique (jamais par un poste).
    case "logAi": {
        if (isRemoteExecuted) exitWith {};
        _args params ["", ["_t", objNull], ["_say", ""], ["_gi", ""], ["_outcome", ""]];
        ["IA>P", _t, "", _say, _gi, _outcome] call _log;
    };
};
