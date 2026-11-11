/*
    Couche « hors sujet » de l'IA RP (pur, toute machine ; données : data\iarp_offtopic.sqf, fn_iarpData « offtopic »).
    Ce que l'IA fait d'un message qui n'est pas une vraie demande, AVANT les demandes à effet (papiers, ordres, aveu…).
      ["data", clé]                         -> section des données (sms, cats, not, junk, actions, mineOk, more…)
      ["classify", texte]                   -> [catégorie, score, indice] : sexual | insult | advance | personal | world |
                                               absurd | "" (rien de repéré)
      ["gibberish", texte]                  -> vrai : charabia (« azerty », « bla bla », mots sans voyelle, rangée du clavier)
      ["filter", [texte, résultat de fn_iarpIntent]] -> [intention, [demandes…], catégorie, confiance]
               grossièreté sexuelle : otSexual, rien d'autre ; insulte : insult (une vraie demande comprise reste, après) ;
               avances : flirt ; question trop personnelle : otPersonal ; hors du monde : otWorld ; absurde : absurd ;
               demande à effet sous le seuil de confiance (0,4) : « unknown » ; incompris fait de charabia : otGibberish
      ["react", [intention, fiche joueur, profil, état, contrôle en cours, heure]] -> [] (rien à faire ici) ou
               [réplique, confiance ±, tension ±, issue, conversation coupée]
               incompris : otMiss1 (contrôle en cours : « unknown », piste des papiers), otMiss2, puis otLost (jamais deux
               fois la même tournure de suite) ; provocation (insulte 1,2 · grossièreté 1,6 · moquerie, avances 0,6 · question
               trop personnelle 0,3, retombe de 1 par minute) : 3 et plus, conversation coupée 90 s (otEnd) ; pendant ce temps
               il ne parle plus (otCold), sauf ordres, menace, pot-de-vin, fouille, et des excuses (otApology) ;
               relance (« Et alors ? ») : otMore.<groupe> d'après sa dernière réplique (moins de 3 min), sinon otMore.none
      ["after", [fiche joueur, répliques, heure]] -> répliques : dernière réplique retenue (relance) ; après deux échanges de
               small talk de suite, retour au sujet (otSteer, « Vous vouliez quelque chose ? »)
      ["selfTest"]                          -> [échecs, infos] : banc d'essai data\iarp_ottests.sqf (fn_iarpSelfTest)
    Style : insurgé ou recherché « Dur » (mépris), policier ou agent « Pro » (refus professionnel), sinon civil ; le ton
    (calme, nerveux, méfiant, agressif, effrayé…) reste celui de fn_iarpGen (caractère, état, arme braquée).
    Exemple : ["filter", ["Tu aimes le cul ?", ["Tu aimes le cul ?"] call comspec_sse_fnc_iarpIntent]] call comspec_sse_fnc_iarpOffTopic
              -> ["otSexual", ["otSexual"], "sexual", 0.5]
*/
params [["_op", "", [""]], ["_a", []]];
private _data = {
    params ["_k", ["_d", []]];
    private _r = _d;
    { if ((_x param [0, ""]) isEqualTo _k) exitWith { _r = _x param [1, _d]; }; } forEach (["offtopic"] call comspec_sse_fnc_iarpData);
    _r
};
private _offI = ["insult", "mock", "joke", "laugh", "bot", "absurd", "hobbies", "flirt", "compliment", "time", "politics", "followup",
    "otSexual", "otPersonal", "otWorld", "otGibberish", "unknown", "yes", "no"];

switch (_op) do {
    case "data": { [_a] call _data };

    case "classify": {
        _a params [["_text", "", [""]]];
        private _n = _text call comspec_sse_fnc_iarpNorm;
        // Tournures qui annulent un mot repéré (« tu me prends pour un con » : une accusation).
        { _n = [_n, _x, " "] call CBA_fnc_replace; } forEach (["not"] call _data);
        private _toks = _n splitString " ";
        private _best = ["", 0, ""];
        {
            _x params [["_cat", ""], ["_w", 1], ["_pats", []], ["_words", []]];
            private _sc = 0;
            private _ev = "";
            { if ((_n find _x) >= 0) then { _sc = _sc + count _x; if (_ev isEqualTo "") then { _ev = trim _x; }; }; } forEach _pats;
            {
                private _p = _x;
                private _hit = if ((_p select [(count _p) - 1]) isEqualTo "*") then {
                    private _pre = _p select [0, (count _p) - 1];
                    (_toks findIf { (_x select [0, count _pre]) isEqualTo _pre }) >= 0
                } else { _p in _toks };
                if (_hit) then { _sc = _sc + 2 + count _p; if (_ev isEqualTo "") then { _ev = _p; }; };
            } forEach _words;
            _sc = _sc * _w;
            if (_sc > (_best select 1)) then { _best = [_cat, _sc, _ev]; };
        } forEach (["cats"] call _data);
        _best
    };

    case "gibberish": {
        _a params [["_text", "", [""]]];
        private _toks = (_text call comspec_sse_fnc_iarpNorm) splitString " ";
        if (_toks isEqualTo []) exitWith { true };
        private _junk = ["junk"] call _data;
        private _rows = ["azertyuiop", "qsdfghjklm", "wxcvbn", "qwertyuiop", "asdfghjkl", "zxcvbnm"];
        private _bad = {
            private _w = _x;
            private _c = toArray _w;
            _w in _junk
            || {(count _c) >= 3 && {(_c findIf { _x in [97, 101, 105, 111, 117, 121] }) < 0} && {(_c findIf { _x < 97 || {_x > 122} }) < 0}}
            || {(count _c) >= 3 && {(count (_c arrayIntersect _c)) isEqualTo 1}}
            || {(count _c) >= 3 && {(_rows findIf { (_x find _w) >= 0 }) >= 0}}
            || {(_c findIf { _x < 48 || {_x > 57} }) < 0}
        };
        private _n = { call _bad } count _toks;
        (_n * 2) >= (count _toks)
    };

    case "filter": {
        _a params [["_text", "", [""]], ["_ir", [], [[]]]];
        _ir params [["_main", "unknown"], ["_score", 0], "", ["_list", []], ["_conf", 0]];
        private _names = _list apply { _x param [0, ""] };
        if (_names isEqualTo []) then { _names = [_main]; };
        (["classify", _text] call comspec_sse_fnc_iarpOffTopic) params ["_cat", "", ""];
        private _act = ["actions"] call _data;
        private _min = 0.4;
        // Vraies demandes du message (ni hors sujet, ni oui / non), demandes à effet seulement au-dessus du seuil.
        private _legit = _names select { !(_x in _offI) && {!(_x in ["greet", "thanks", "sorry", "bye"])} && {!(_x in _act) || {_conf >= _min}} };
        private _sure = _legit isNotEqualTo [] && {_conf >= 0.6} && {!(_main in _offI)};
        private _out = call {
            if (_cat isEqualTo "sexual") exitWith { ["otSexual", ["otSexual"]] };
            if (_cat isEqualTo "insult" || {_main isEqualTo "insult"}) exitWith {
                if (_legit isNotEqualTo [] && {_conf >= _min}) exitWith { [_legit select 0, ["insult", _legit select 0]] };
                ["insult", ["insult"]]
            };
            if (_cat isEqualTo "advance") exitWith { if (_sure) exitWith { [_legit select 0, ["flirt", _legit select 0]] }; ["flirt", ["flirt"]] };
            if (_cat isEqualTo "personal" && {!_sure}) exitWith { ["otPersonal", ["otPersonal"]] };
            if (_cat isEqualTo "world" && {!(_main in _act && {_conf >= _min})}) exitWith { ["otWorld", ["otWorld"]] };
            if (_cat isEqualTo "absurd" && {!_sure}) exitWith { ["absurd", ["absurd"]] };
            // Demande à effet sans preuve suffisante dans le texte : non comprise (jamais « D'accord, voilà. »).
            if (_main in _act && {_conf < _min}) exitWith { ["unknown", ["unknown"]] };
            [_main, _names select { !(_x in _act) || {_conf >= _min} }]
        };
        _out params ["_m", "_l"];
        if (_l isEqualTo []) then { _l = [_m]; };
        if (_m isEqualTo "unknown" && {["gibberish", _text] call comspec_sse_fnc_iarpOffTopic}) then { _m = "otGibberish"; _l = ["otGibberish"]; };
        [_m, _l, _cat, _conf]
    };

    case "react": {
        _a params [["_intent", ""], ["_pm", createHashMap], ["_p", createHashMap], ["_st", ""], ["_ctl", false], ["_now", 0]];
        private _role = _p getOrDefault ["role", "auto"];
        private _style = call {
            if (_role in ["insurge", "hvt"]) exitWith { "Dur" };
            if (_role in ["policier", "agent"]) exitWith { "Pro" };
            ""
        };
        // Incompris : la tournure change à chaque fois (jamais deux fois « Pardon ? » de suite).
        if (_intent in ["unknown", "otGibberish"]) exitWith {
            private _n = (_pm getOrDefault ["miss", 0]) + 1;
            _pm set ["miss", _n];
            private _g = call {
                if (_n >= 3) exitWith { "otLost" };
                if (_n isEqualTo 2) exitWith { "otMiss2" };
                if (_intent isEqualTo "otGibberish") exitWith { "otGibberish" };
                // Contrôle en cours : il propose ses papiers (une question ; rien n'est tendu sans demande).
                if (_ctl) exitWith { "unknown" };
                "otMiss1"
            };
            [_g, 0, 0, format ["incompris (%1 de suite)", _n], false]
        };
        _pm set ["miss", 0];
        // Conversation coupée : il ne parle plus, sauf des excuses ; ordres, menace, pot-de-vin, fouille restent traités.
        if ((_pm getOrDefault ["coldUntil", -1]) > _now) exitWith {
            if (_intent isEqualTo "sorry") exitWith {
                _pm set ["coldUntil", -1];
                _pm set ["provoke", 1];
                _pm set ["provokeAt", _now];
                ["otApology", 3, -4, "accepte des excuses", false]
            };
            if (_intent in ["threat", "bribe", "confront", "search", "cuff", "badge", "givePapers", "noPapers", "watchMe", "calm", "photo", "cooperate"]) exitWith { [] };
            ["otCold", [0, -5] select (_intent in ["insult", "otSexual", "mock"]), 0, "ne parle plus (provocations)", false]
        };
        private _sev = (createHashMapFromArray [["otSexual", 1.6], ["insult", 1.2], ["mock", 0.6], ["flirt", 0.6], ["otPersonal", 0.3]]) getOrDefault [_intent, -1];
        if (_sev >= 0) exitWith {
            private _pv = ((_pm getOrDefault ["provoke", 0]) - ((_now - (_pm getOrDefault ["provokeAt", _now])) / 60)) max 0;
            _pv = _pv + _sev;
            _pm set ["provoke", _pv];
            _pm set ["provokeAt", _now];
            if (_pv >= 3) exitWith {
                _pm set ["coldUntil", _now + 90];
                _pm set ["provoke", 1];
                [["otEnd", "otEndPro"] select (_style isEqualTo "Pro"), -20, 6, "conversation coupée (provocations répétées)", true]
            };
            private _l2 = _pv >= 1.8;
            switch (_intent) do {
                case "otSexual": { ["otSexual" + _style, -15, 8, "grossièreté : choquée", false] };
                case "mock": { [["mock", "otInsult2"] select _l2, -5, 3, "provoquée", false] };
                case "flirt": { ["flirt", -3, 2, "avances repoussées", false] };
                case "otPersonal": { ["otPersonal", -2, 0, "question trop personnelle", false] };
                default {
                    private _g = if (_l2) then { ["otInsult2", "otInsultDur", "otInsultPro"] select (["", "Dur", "Pro"] find _style) } else { "otInsult" + _style };
                    [_g, [-10, -15] select _l2, [6, 10] select _l2, ["insultée", "insultée encore"] select _l2, false]
                };
            }
        };
        if (_intent isEqualTo "otWorld") exitWith { ["otWorld", 0, 0, "question hors du monde : ne sait pas", false] };
        // Relance : il développe sa dernière réplique, sans rien inventer.
        if (_intent isEqualTo "followup") exitWith {
            (_pm getOrDefault ["lastGi", ["", "", -1e6]]) params [["_lg", ""], ["_li", ""], ["_lt", -1e6]];
            private _grp = "none";
            if ((_now - _lt) < 180) then {
                {
                    _x params ["_g", "_l"];
                    if (((format ["%1.%2", _lg, _li]) in _l) || {_lg in _l}) exitWith { _grp = _g; };
                } forEach (["more"] call _data);
            };
            ["otMore." + _grp, 0, 0, "relance : " + _grp, false]
        };
        []
    };

    case "after": {
        _a params [["_pm", createHashMap], ["_parts", []], ["_now", 0]];
        if (_parts isEqualTo []) exitWith { _parts };
        private _ask = ((_parts select -1) param [3, ""]) isEqualTo "ask";
        private _m = _parts select ([(count _parts) - 1, ((count _parts) - 2) max 0] select _ask);
        _pm set ["lastGi", [_m select 0, _m param [3, ""], _now]];
        // Small talk qui dure : il revient au sujet (une question, jamais un ordre).
        private _small = ["howareyou", "smalltalk", "hobbies", "compliment", "joke", "laugh", "time", "bot", "greet", "greetAgain", "introduce", "otWorld", "absurd", "politics", "thanks"];
        if ((_parts findIf { !((_x select 0) in _small) }) < 0) then {
            private _n = (_pm getOrDefault ["small", 0]) + 1;
            _pm set ["small", _n];
            if (_n >= 2 && {!_ask}) then { _parts pushBack ["otSteer", "", "", "ask", ""]; _pm set ["small", 0]; };
        } else { _pm set ["small", 0]; };
        _parts
    };

    // Banc d'essai (data\iarp_ottests.sqf), repris par fn_iarpSelfTest -> [échecs, infos]. Même batterie hors jeu :
    // tools\iarp_harness.py.
    case "selfTest": {
        private _ko = [];
        private _info = [];
        private _T = ["ottests"] call comspec_sse_fnc_iarpData;
        private _sec = { params ["_k"]; private _r = []; { if ((_x param [0, ""]) isEqualTo _k) exitWith { _r = _x param [1, []]; }; } forEach _T; _r };
        private _act = ["actions"] call _data;
        private _flt = { ["filter", [_this, [_this] call comspec_sse_fnc_iarpIntent]] call comspec_sse_fnc_iarpOffTopic };
        private _smallI = ["howareyou", "smalltalk", "hobbies", "compliment", "joke", "laugh", "time", "bot", "greet", "greetAgain", "introduce", "politics", "thanks"];
        // Hors sujet : la bonne catégorie, aucune demande à effet.
        private _nO = 0;
        {
            _x params ["_txt", "_want"];
            (_txt call _flt) params ["_m", "_l"];
            _nO = _nO + 1;
            if ((_l findIf { _x in _act }) >= 0) then { _ko pushBack format ["hors sujet « %1 » : demande à effet %2", _txt, _l]; };
            if (([_m, "small"] select (_m in _smallI)) isNotEqualTo _want) then { _ko pushBack format ["hors sujet « %1 » : %2 au lieu de %3", _txt, _m, _want]; };
        } forEach (["offtopic"] call _sec);
        // Légitime (tutoiement, vouvoiement, formes courtes, SMS) : la bonne intention, jamais la couche hors sujet.
        private _nL = 0;
        {
            _x params ["_txt", "_want"];
            private _m = (_txt call _flt) select 0;
            _nL = _nL + 1;
            if (_m isNotEqualTo _want) then { _ko pushBack format ["légitime « %1 » : %2 au lieu de %3", _txt, _m, _want]; };
        } forEach (["legit"] call _sec);
        // Guide : chaque exemple compris comme son intention après la couche hors sujet.
        private _nG = 0;
        {
            {
                _x params ["_i", ["_ex", []]];
                if ((_i select [0, 1]) isNotEqualTo "_") then {
                    { { _nG = _nG + 1; private _m = (_x call _flt) select 0; if (_m isNotEqualTo _i) then { _ko pushBack format ["guide « %1 » : %2 au lieu de %3", _x, _m, _i]; }; } forEach _x; } forEach _ex;
                };
            } forEach (_x select 1);
        } forEach (["guide"] call comspec_sse_fnc_iarpData);
        // Dialogues : relance, incompris, provocation, conversation coupée (tour simplifié : civil sans savoir, ordre obéi).
        private _orders = ["handsup", "halt", "kneel", "follow", "leave", "dismount", "trunk", "dropWeapon", "stepBack", "comeHere", "turnAround", "sit", "quiet",
            "hurry", "handsDown", "bag"];
        private _intel = ["intel", "seenArmed", "seenVehicles", "strangers", "explosives", "mines", "whereIs", "leader", "howMany", "whenPassed", "happened",
            "heard", "whereWent", "describe", "cache", "base", "checkpoints", "safety", "problems", "roadSafe", "knowPerson", "threatened"];
        private _prof = createHashMapFromArray [["role", "civil"]];
        private _nD = 0;
        {
            private _pm = createHashMap;
            private _now = 0;
            {
                _x params ["_txt", "_want"];
                (_txt call _flt) params ["", "_l"];
                private _parts = _l apply {
                    private _i = _x;
                    private _g = _i;
                    if (_i in _orders) then { _g = "comply"; } else {
                        private _r = ["react", [_i, _pm, _prof, "", false, _now]] call comspec_sse_fnc_iarpOffTopic;
                        call {
                            if (_r isNotEqualTo []) exitWith { _g = _r select 0; };
                            if (_i in _intel) exitWith { _g = "intelNo"; };
                            if (_i isEqualTo "name" && {((_txt call comspec_sse_fnc_iarpNorm) find "prenom") >= 0}) exitWith { _g = "firstName"; };
                        };
                    };
                    [_g, "", "", _i, ""]
                };
                _parts = ["after", [_pm, _parts, _now]] call comspec_sse_fnc_iarpOffTopic;
                private _gis = _parts apply { _x select 0 };
                _now = _now + 8;
                _nD = _nD + 1;
                private _ok = call {
                    if (_want isEqualTo "*") exitWith { (_gis findIf { (_x select [0, 2]) isEqualTo "ot" || {_x isEqualTo "unknown"} }) < 0 };
                    if (_want isEqualTo "*+otSteer") exitWith { (_gis select -1) isEqualTo "otSteer" };
                    _want in _gis
                };
                if (!_ok) then { _ko pushBack format ["dialogue « %1 » : %2 au lieu de %3", _txt, _gis, _want]; };
            } forEach _x;
        } forEach (["dialog"] call _sec);
        // Phrases : 6 au moins par ton principal, jamais « D'accord, voilà. », aucun mot grossier du joueur repris.
        private _bank = ["bank"] call comspec_sse_fnc_iarpData;
        private _banned = [];
        { if ((_x select 0) in ["sexual", "insult"]) then { { _banned pushBackUnique ([_x, "*", ""] call CBA_fnc_replace); } forEach (_x select 3); }; } forEach (["cats"] call _data);
        private _nP = 0;
        {
            private _k = _x;
            if ((_k select [0, 2]) isEqualTo "ot" || {(_k select [0, 7]) isEqualTo "comply."} || {(_k select [0, 9]) isEqualTo "noComply."}) then {
                {
                    private _tone = _x;
                    private _lines = _y;
                    _nP = _nP + count _lines;
                    private _need = [3, 6] select ((_tone in ["*", "nerveux", "mefiant", "agressif", "effraye"] && {(_k select [0, 7]) isNotEqualTo "comply."} && {_k isNotEqualTo "otGibberish"}) || {_tone isEqualTo "*"});
                    if ((count _lines) < _need) then { _ko pushBack format ["banque %1.%2 : %3 phrases (%4 attendues)", _k, _tone, count _lines, _need]; };
                    {
                        private _toks = ((_x select 0) call comspec_sse_fnc_iarpNorm) splitString " ";
                        if (((_x select 0) find "accord, voil") >= 0) then { _ko pushBack format ["banque %1 : « %2 »", _k, _x select 0]; };
                        if ((_toks findIf { _x in _banned }) >= 0) then { _ko pushBack format ["banque %1 : mot grossier repris « %2 »", _k, _x select 0]; };
                    } forEach _lines;
                } forEach (_bank get _k);
            };
        } forEach (keys _bank);
        _info pushBack format ["hors sujet : %1 messages hors sujet, %2 légitimes (tu, vous, court, SMS), %3 exemples du guide, %4 tours de dialogue, %5 phrases", _nO, _nL, _nG, _nD, _nP];
        if (_nO < 60 || {_nL < 120}) then { _ko pushBack format ["hors sujet : %1 messages hors sujet et %2 légitimes (60 et 120 attendus)", _nO, _nL]; };
        [_ko, _info]
    };
};
