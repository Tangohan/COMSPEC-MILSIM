/*
    Salle TOC (demande de TTRD, 11/11 : « une vraie salle d'opérations en jeu ») : état partagé du poste de commandement
    d'un camp, tenu par le serveur seul. Les PC du poste (fn_tocLaptop), l'app Poste TOC (fn_pageToc) et le menu ACE des
    écrans envoient des demandes ; le serveur applique, publie et sauvegarde (fn_tocPersist). Rien d'autre ne circule :
    chaque joueur dessine lui-même ses écrans et rend lui-même ses caméras (fn_tocSat, fn_tocBoard).

    Variable publique COMSPEC_ATAK_TocRoom_<WEST | EAST | GUER | CIV> = [
      0 révision,
      1 chronos      [[id, nom, genre "UP" (chrono) | "DOWN" (compte à rebours) | "AT" (heure H en ZULU), valeur
                       (durée en s pour DOWN, minute du jour ZULU pour AT), en marche, cumul en s, départ (serverTime)]...]
      2 satellites   [SAT 1, SAT 2] : [x, y, demi-largeur au sol en m, image "DAY" | "NV" | "TI", suivi "" | "U" | "G",
                       cible du suivi (netId de l'unité ou nom du groupe), opérateur qui a la main (libellé), son UID]
      3 main courante [[heure ZULU, genre, auteur, texte]...] (les plus récentes à la fin, 150 au plus)
      4 exécution    [[id, n°, événement, mot de code, élément, heure prévue, heure réelle ZULU ("" : pas encore)]...]
      5 demandes     [[id, heure ZULU, genre, demandeur, UID du demandeur, grille, texte, statut, réponse, [x, y]]...]
      6 FLASH        [texte, auteur, heure ZULU, serverTime] ou []
      7 postes       [[poste, opérateur, UID, heure ZULU de prise]...]
      8 pertes       [[élément, blessés amis, tués amis, ennemis déclarés neutralisés, ennemis déclarés blessés ou capturés]...]
    ]
    Modes :
      ["init"]                            serveur (fn_tocScreen initServer)
      ["state", camp]                     partout : état publié (vide si rien)
      ["send", [action, args]]            client : demande au serveur pour son camp (libellé et UID joints)
      ["do", [camp, action, args, libellé, UID]]   serveur
      ["dump"] / ["load", données]        serveur (fn_tocPersist) : chronos arrêtés à leur valeur, puis repris
      ["timerText", chrono]               partout : [texte, couleur RGBA, état "RUN" | "STOP" | "OVER", couleur #rrggbb]
      ["zulu"]                            partout : heure ZULU "HH:MM:SS" (horloge UTC du poste)
      ["me"]                              client : libellé de l'opérateur « indicatif du poste · nom »
    Actions (serveur) : timerAdd [nom, genre, valeur] | timerStart id | timerStop id | timerReset id | timerDel id
      sat [n° 0-1, clé, valeur] : clés take [x, y] | release | center [x, y] | pan [dx, dy] (part de la vue) | zoom facteur
      | mode "DAY" | "NV" | "TI" | follow ["U" | "G", cible] | unfollow
      log [genre, texte] | flash texte | flashClear | login poste | logout
      exAdd [événement, mot de code, élément, heure prévue] | exDone id | exUndo id | exDel id | exSet [lignes]
      reqAdd [genre, demandeur, UID, grille, texte, [x, y]] | reqSet [id, statut, réponse] | reqDel id
      kill [élément, clé "wia" | "kia" | "ekia" | "ewia", nombre]
    Exemple de mission (serveur) : ["do", ["WEST", "timerAdd", ["H-HOUR", "AT", 22 * 60], "Mission", ""]] call comspec_atak_native_fnc_tocRoom;
*/
params [["_mode", "state"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocRoom;
private _sides = ["WEST", "EAST", "GUER", "CIV"];
private _two = { private _n = floor _this; [str _n, "0" + str _n] select (_n < 10) };
// Satellite sans position (-1) : centré sur le poste à la première prise en main.
private _blank = { [0, [], [[-1, -1, 600, "DAY", "", "", "", ""], [-1, -1, 600, "DAY", "", "", "", ""]], [], [], [], [], [], []] };
switch (_mode) do {
    case "zulu": {
        systemTimeUTC params ["", "", "", "_h", "_mi", "_s"];
        format ["%1:%2:%3", _h call _two, _mi call _two, _s call _two]
    };
    case "me": {
        format ["%1 · %2", ["callsign"] call comspec_atak_native_fnc_tocPost, name player]
    };
    case "state": {
        private _s = missionNamespace getVariable [format ["COMSPEC_ATAK_TocRoom_%1", _a], []];
        if !(_s isEqualType [] && {(count _s) >= 9}) then { _s = call _blank; };
        _s
    };
    case "timerText": {
        _a params [["_id", ""], ["_name", ""], ["_kind", "UP"], ["_val", 0], ["_run", false], ["_acc", 0], ["_t0", 0]];
        private _hms = {
            private _v = round (abs _this);
            format ["%1:%2:%3", floor (_v / 3600) call _two, floor ((_v mod 3600) / 60) call _two, (_v mod 60) call _two]
        };
        private _e = _acc + ([0, (serverTime - _t0) max 0] select _run);
        switch (_kind) do {
            case "DOWN": {
                private _r = _val - _e;
                if (_r >= 0) then { ["-" + (_r call _hms), [[0.62, 0.66, 0.64, 1], [0.36, 0.85, 0.42, 1]] select _run, ["STOP", "RUN"] select _run, ["#9aa39f", "#5cc76b"] select _run] }
                    else { ["+" + (_r call _hms), [1, 0.36, 0.3, 1], "OVER", "#ff5a4a"] };
            };
            case "AT": {
                systemTimeUTC params ["", "", "", "_h", "_mi", "_s"];
                private _d = _val * 60 - (_h * 3600 + _mi * 60 + _s);
                if (_d > 43200) then { _d = _d - 86400; };
                if (_d < -43200) then { _d = _d + 86400; };
                if (_d > 0) then { ["H-" + (_d call _hms), [0.88, 0.69, 0.25, 1], "RUN", "#e0b040"] } else { ["H+" + (_d call _hms), [1, 0.36, 0.3, 1], "OVER", "#ff5a4a"] };
            };
            default { ["+" + (_e call _hms), [[0.62, 0.66, 0.64, 1], [0.36, 0.85, 0.42, 1]] select _run, ["STOP", "RUN"] select _run, ["#9aa39f", "#5cc76b"] select _run] };
        }
    };
    case "send": {
        _a params [["_act", ""], ["_args", []]];
        if (!hasInterface || {isNull player}) exitWith { false };
        ["comspec_atak_native_tocRoom", ["do", [str side group player, _act, _args, ["me"] call _fn, getPlayerUID player]]] call CBA_fnc_serverEvent;
        true
    };
    case "initClient": {
        if (!hasInterface) exitWith {};
        // Réponse du poste à ma demande : dans le téléphone (centre de notifications), jamais en fenêtre du jeu.
        ["comspec_atak_native_tocReqAnswer", {
            params [["_k", ""], ["_lbl", ""], ["_ans", ""], ["_who", ""]];
            ["TACTICAL", format ["TOC · %1 %2%3", _k, _lbl, ["", " : " + _ans] select (_ans isNotEqualTo "")], 8, 60, "TOC"] call comspec_atak_native_fnc_notify;
        }] call CBA_fnc_addEventHandler;
    };
    case "init": {
        if (!isServer) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocRoomInit", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocRoomInit", true];
        ["comspec_atak_native_tocRoom", { _this call comspec_atak_native_fnc_tocRoom; }] call CBA_fnc_addEventHandler;
        // Opérateur parti : ses satellites et son poste sont libérés.
        addMissionEventHandler ["HandleDisconnect", {
            params ["", "", "_uid"];
            { ["do", [_x, "dropUid", [], "", _uid]] call comspec_atak_native_fnc_tocRoom; } forEach ["WEST", "EAST", "GUER", "CIV"];
            false
        }];
    };
    case "dump": {
        // Chronos figés à leur valeur (le serverTime repart de zéro à la partie suivante).
        private _out = [];
        {
            private _s = +(["state", _x] call _fn);
            if ((_s select 0) > 0) then {
                _s set [1, (_s select 1) apply {
                    _x params ["_id", "_n", "_k", "_v", "_run", "_acc", "_t0"];
                    [_id, _n, _k, _v, _run, _acc + ([0, (serverTime - _t0) max 0] select _run), 0]
                }];
                // Personne n'a la main à la reprise ; les postes sont repris par qui s'y assoit.
                _s set [2, (_s select 2) apply { private _c = +_x; _c set [6, ""]; _c set [7, ""]; _c }];
                _s set [6, []];
                _s set [7, []];
                _out pushBack [_x, _s];
            };
        } forEach _sides;
        _out
    };
    case "load": {
        if (!isServer || {!(_a isEqualType [])}) exitWith {};
        {
            _x params [["_side", ""], ["_s", []]];
            if (!(_side in _sides) || {!(_s isEqualType [])} || {(count _s) < 9}) then { continue };
            private _st = +_s;
            _st set [1, (_st select 1) apply { private _t = +_x; _t set [6, serverTime]; _t }];
            _st set [0, 1];
            missionNamespace setVariable [format ["COMSPEC_ATAK_TocRoom_%1", _side], _st, true];
        } forEach _a;
    };
    case "do": {
        if (!isServer) exitWith { ["comspec_atak_native_tocRoom", ["do", _a]] call CBA_fnc_serverEvent; };
        _a params [["_side", ""], ["_act", ""], ["_args", []], ["_who", ""], ["_uid", ""]];
        if !(_side in _sides) exitWith { false };
        if !(_who isEqualType "") then { _who = str _who; };
        _who = _who select [0, 48];
        private _var = format ["COMSPEC_ATAK_TocRoom_%1", _side];
        private _st = +(["state", _side] call _fn);
        private _zulu = (["zulu"] call _fn) select [0, 5];
        private _txt = { params ["_t", ["_n", 120]]; if !(_t isEqualType "") then { _t = str _t; }; (trim _t) select [0, _n] };
        private _log = {
            params ["_k", "_t"];
            private _l = _st select 3;
            _l pushBack [["zulu"] call _fn, _k, _who, _t select [0, 200]];
            if ((count _l) > 150) then { _l deleteRange [0, (count _l) - 150]; };
        };
        private _newId = { format ["%1%2", _this, floor (serverTime * 100) + floor random 1000] };
        private _changed = true;
        private _tm = _st select 1;
        private _ti = _tm findIf { (_x select 0) isEqualTo _args };
        switch (_act) do {
            case "timerAdd": {
                _args params [["_n", ""], ["_k", "UP"], ["_v", 0]];
                _n = toUpper ([_n, 24] call _txt);
                if (_n isEqualTo "" || {!(_k in ["UP", "DOWN", "AT"])} || {(count _tm) >= 16}) exitWith { _changed = false; };
                if !(_v isEqualType 0) then { _v = 0; };
                _v = [(_v max 0) min 86400, ((round _v) max 0) min 1439] select (_k isEqualTo "AT");
                _tm pushBack ["T" call _newId, _n, _k, _v, _k isEqualTo "AT", 0, serverTime];
                ["CHRONO", format ["Chrono %1 créé", _n]] call _log;
            };
            case "timerStart": {
                if (_ti < 0) exitWith { _changed = false; };
                private _t = _tm select _ti;
                if (_t select 4) exitWith { _changed = false; };
                _t set [4, true]; _t set [6, serverTime];
                ["CHRONO", format ["Chrono %1 lancé", _t select 1]] call _log;
            };
            case "timerStop": {
                if (_ti < 0) exitWith { _changed = false; };
                private _t = _tm select _ti;
                if !(_t select 4) exitWith { _changed = false; };
                _t set [5, (_t select 5) + ((serverTime - (_t select 6)) max 0)]; _t set [4, false];
                ["CHRONO", format ["Chrono %1 arrêté", _t select 1]] call _log;
            };
            case "timerReset": {
                if (_ti < 0) exitWith { _changed = false; };
                private _t = _tm select _ti;
                _t set [5, 0]; _t set [6, serverTime];
            };
            case "timerDel": { if (_ti < 0) exitWith { _changed = false; }; _tm deleteAt _ti; };
            case "sat": {
                _args params [["_n", 0], ["_k", ""], ["_v", []]];
                if !(_n in [0, 1]) exitWith { _changed = false; };
                private _s = (_st select 2) select _n;
                private _free = (_s select 7) isEqualTo "" || {(allPlayers findIf { (getPlayerUID _x) isEqualTo (_s select 7) }) < 0};
                private _mine = (_s select 7) isEqualTo _uid;
                if (_k isEqualTo "take") exitWith {
                    if (!_free && {!_mine}) exitWith { _changed = false; };
                    _s set [6, _who]; _s set [7, _uid];
                    if ((_s select 0) < 0 && {_v isEqualType []} && {(count _v) >= 2}) then { _s set [0, round (_v select 0)]; _s set [1, round (_v select 1)]; };
                    ["SAT", format ["SAT %1 : prise en main", _n + 1]] call _log;
                };
                if (_k isEqualTo "release") exitWith {
                    if (!_mine) exitWith { _changed = false; };
                    _s set [6, ""]; _s set [7, ""];
                };
                // Une seule main sur un satellite : un autre opérateur présent garde la main.
                if (!_mine && {!_free}) exitWith { _changed = false; };
                if (_free && {!_mine}) then { _s set [6, _who]; _s set [7, _uid]; };
                switch (_k) do {
                    case "center": { if (_v isEqualType [] && {(count _v) >= 2}) then { _s set [0, round (_v select 0)]; _s set [1, round (_v select 1)]; _s set [4, ""]; _s set [5, ""]; }; };
                    case "pan": {
                        if !(_v isEqualType [] && {(count _v) >= 2}) exitWith {};
                        private _h = _s select 2;
                        if ((_s select 0) < 0) exitWith {};
                        _s set [0, round (((_s select 0) + (_v select 0) * 2 * _h) max 0 min worldSize)];
                        _s set [1, round (((_s select 1) + (_v select 1) * 2 * _h * 9 / 16) max 0 min worldSize)];
                        _s set [4, ""]; _s set [5, ""];
                    };
                    case "zoom": { if (_v isEqualType 0 && {_v > 0}) then { _s set [2, round (((_s select 2) * _v) max 40 min 2500)]; }; };
                    case "mode": { if (_v in ["DAY", "NV", "TI"]) then { _s set [3, _v]; }; };
                    case "follow": {
                        _v params [["_fk", ""], ["_fid", ""]];
                        if (_fk in ["U", "G"] && {_fid isEqualType ""} && {_fid isNotEqualTo ""}) then { _s set [4, _fk]; _s set [5, _fid select [0, 40]]; };
                    };
                    case "unfollow": { _s set [4, ""]; _s set [5, ""]; };
                };
            };
            case "dropUid": {
                _changed = false;
                { if ((_x select 7) isEqualTo _uid && {_uid isNotEqualTo ""}) then { _x set [6, ""]; _x set [7, ""]; _changed = true; }; } forEach (_st select 2);
                private _ps = _st select 7;
                private _pi = _ps findIf { (_x select 2) isEqualTo _uid };
                if (_pi >= 0) then { _ps deleteAt _pi; _changed = true; };
            };
            case "log": {
                _args params [["_k", "NOTE"], ["_t", ""]];
                _t = [_t, 200] call _txt;
                if (_t isEqualTo "") exitWith { _changed = false; };
                [toUpper ([_k, 12] call _txt), _t] call _log;
            };
            case "flash": {
                private _t = [_args, 160] call _txt;
                if (_t isEqualTo "") exitWith { _changed = false; };
                _st set [6, [_t, _who, _zulu, serverTime]];
                ["FLASH", _t] call _log;
            };
            case "flashClear": { _st set [6, []]; };
            case "login": {
                private _post = toUpper ([_args, 24] call _txt);
                private _ps = (_st select 7) select { (_x select 2) isNotEqualTo _uid && {(_x select 0) isNotEqualTo _post} };
                _ps pushBack [_post, _who, _uid, _zulu];
                _st set [7, _ps];
                ["POSTE", format ["Prise du poste %1", _post]] call _log;
            };
            case "logout": {
                private _ps = _st select 7;
                private _pi = _ps findIf { (_x select 2) isEqualTo _uid };
                if (_pi < 0) exitWith { _changed = false; };
                ["POSTE", format ["Poste %1 libéré", (_ps select _pi) select 0]] call _log;
                _ps deleteAt _pi;
            };
            case "exAdd": {
                _args params [["_ev", ""], ["_cw", ""], ["_el", ""], ["_at", ""]];
                private _ex = _st select 4;
                if ((count _ex) >= 40 || {([_ev, 60] call _txt) isEqualTo ""}) exitWith { _changed = false; };
                _ex pushBack ["E" call _newId, (count _ex) + 1, [_ev, 60] call _txt, toUpper ([_cw, 24] call _txt), toUpper ([_el, 24] call _txt), [_at, 8] call _txt, ""];
            };
            case "exSet": {
                // Liste préparée (Eden, Zeus) : une ligne par étape, « événement ; mot de code ; élément ; heure prévue ».
                private _ex = [];
                {
                    if (_x isEqualType [] && {(count _ex) < 40}) then {
                        _x params [["_ev", ""], ["_cw", ""], ["_el", ""], ["_at", ""]];
                        if (([_ev, 60] call _txt) isNotEqualTo "") then {
                            _ex pushBack [format ["E%1", _forEachIndex], (count _ex) + 1, [_ev, 60] call _txt, toUpper ([_cw, 24] call _txt), toUpper ([_el, 24] call _txt), [_at, 8] call _txt, ""];
                        };
                    };
                } forEach _args;
                _st set [4, _ex];
            };
            case "exDone": {
                private _e = (_st select 4) select { (_x select 0) isEqualTo _args };
                if ((count _e) isEqualTo 0 || {((_e select 0) select 6) isNotEqualTo ""}) exitWith { _changed = false; };
                (_e select 0) set [6, _zulu + "Z"];
                ["EXCHECK", format ["%1 · %2 %3 passé", (_e select 0) select 4, (_e select 0) select 2, (_e select 0) select 3]] call _log;
            };
            case "exUndo": {
                private _e = (_st select 4) select { (_x select 0) isEqualTo _args };
                if ((count _e) isEqualTo 0) exitWith { _changed = false; };
                (_e select 0) set [6, ""];
            };
            case "exDel": {
                private _ex = (_st select 4) select { (_x select 0) isNotEqualTo _args };
                { _x set [1, _forEachIndex + 1]; } forEach _ex;
                _st set [4, _ex];
            };
            case "reqAdd": {
                _args params [["_k", "DEMANDE"], ["_by", ""], ["_byUid", ""], ["_grid", ""], ["_t", ""], ["_p", []]];
                private _rq = _st select 5;
                _rq pushBack ["R" call _newId, _zulu, toUpper ([_k, 16] call _txt), [_by, 32] call _txt, _byUid, [_grid, 12] call _txt, [_t, 200] call _txt, "RECUE", "",
                    [[], [round (_p select 0), round (_p select 1)]] select (_p isEqualType [] && {(count _p) >= 2})];
                if ((count _rq) > 40) then { _rq deleteRange [0, (count _rq) - 40]; };
                ["DEMANDE", format ["%1 de %2 reçue", toUpper ([_k, 16] call _txt), [_by, 32] call _txt]] call _log;
            };
            case "reqSet": {
                _args params [["_id", ""], ["_stt", ""], ["_ans", ""]];
                private _r = (_st select 5) select { (_x select 0) isEqualTo _id };
                if ((count _r) isEqualTo 0 || {!(_stt in ["RECUE", "PRISE", "REFUSEE", "TERMINEE"])}) exitWith { _changed = false; };
                _r = _r select 0;
                _r set [7, _stt];
                if (_ans isNotEqualTo "") then { _r set [8, [_ans, 160] call _txt]; };
                private _lbl = (createHashMapFromArray [["RECUE", "reçue"], ["PRISE", "prise en compte"], ["REFUSEE", "refusée"], ["TERMINEE", "terminée"]]) get _stt;
                ["DEMANDE", format ["%1 de %2 : %3", _r select 2, _r select 3, _lbl]] call _log;
                // Réponse sur le téléphone du demandeur (centre de notifications), jamais en fenêtre du jeu.
                private _to = allPlayers select { (getPlayerUID _x) isEqualTo (_r select 4) };
                if ((count _to) > 0) then {
                    ["comspec_atak_native_tocReqAnswer", [_r select 2, _lbl, _r select 8, _who], _to select 0] call CBA_fnc_targetEvent;
                };
            };
            case "reqDel": { _st set [5, (_st select 5) select { (_x select 0) isNotEqualTo _args }]; };
            case "kill": {
                _args params [["_el", ""], ["_k", ""], ["_n", 1]];
                private _i = ["wia", "kia", "ekia", "ewia"] find _k;
                _el = [_el, 32] call _txt;
                if (_i < 0 || {_el isEqualTo ""} || {!(_n isEqualType 0)}) exitWith { _changed = false; };
                private _kc = _st select 8;
                private _r = _kc findIf { (_x select 0) isEqualTo _el };
                if (_r < 0) then { _kc pushBack [_el, 0, 0, 0, 0]; _r = (count _kc) - 1; };
                private _row = _kc select _r;
                _row set [_i + 1, ((_row select (_i + 1)) + round _n) max 0];
            };
            default { _changed = false; };
        };
        if (!_changed) exitWith { false };
        _st set [0, (_st select 0) + 1];
        missionNamespace setVariable [_var, _st, true];
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    default { false };
};
