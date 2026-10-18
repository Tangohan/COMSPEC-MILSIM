/*
    Bluetooth du téléphone : appairage par code entre ATAK proches, puis échanges entre appareils appairés.
    Ce qui s'envoie (onglets de l'app Bluetooth) :
      SONS / MUSIQUE (serveur)  : le son est joué par le haut-parleur du téléphone qui l'accepte (snd, mus) ;
      MUSIQUE (mes fichiers)    : le morceau lui-même est copié dans le dossier musique de l'autre (mp3) ;
      PHOTOS                    : la photo est copiée dans les captures de l'autre, visible dans sa Galerie (pho) ;
      DONNÉES                   : ma position, mon itinéraire, un plan de l'app Plan (pos, wp, plan) ;
      FICHES                    : fiches SEEK (journal du terminal) et fiches SSE (personnes enregistrées) (seek, sse).
    Tout passe par une demande que l'autre accepte ou refuse. Les fichiers partent ensuite par morceaux
    (DLL BtFileRead / BtRecvWrite, fn_btItems pour le reste), à la vitesse simulée du Bluetooth, tant que les
    deux téléphones restent à portée.
    Params : [action, argument]
      "state"                 : état (HashMap) ; "on" : Bluetooth allumé ? (booléen, vérifie aussi que le téléphone est utilisable)
      "toggle"                : allume / éteint le Bluetooth (indépendant du mode avion)
      "showCode" / "hideCode" : affiche un code d'appairage à 6 chiffres (2 min), publié sur l'unité (COMSPEC_ATAK_BtCode)
      "enterCode"             : appaire avec l'ATAK proche (portée Bluetooth) qui affiche le code saisi (champ btCode)
      "unpair" uid            : oublie l'appareil (et le prévient s'il est en ligne)
      "target" uid / "tab" / "search" / "page" : choix du destinataire et navigation dans la liste à envoyer
      "send" index            : propose l'élément n° index de la liste affichée au destinataire choisi
      "accept" id / "refuse" id : réponse à une demande reçue ; "stopPlay" : coupe le son en cours sur mon téléphone
      "cancel" id             : interrompt un transfert en cours (envoi ou réception)
      "view" id / "unview"    : affiche ou referme une fiche reçue (app Bluetooth, section Reçus)
      "pump"                  : envoie les morceaux suivants des fichiers en cours (gestionnaire par image)
      "recv" [type, args]     : événement CBA comspec_atak_native_bt reçu d'un autre téléphone
                                (paired, unpaired, offer, answer, data, fbeg, fchk, fend, fabort, done)
    Portées (variables de mission, modifiables par le créateur) : comspec_atak_native_bt_range (liaison, 15 m),
    comspec_atak_native_bt_sound_range (le son est audible autour du téléphone qui le joue, 20 m).
    Débit : 4 morceaux de 3 Ko tous les quarts de seconde (48 Ko/s), une photo passe en quelques secondes.
    État (missionNamespace COMSPEC_ATAK_Bt, gardé toute la mission) : on, code, codeUntil, paired (uid → nom),
    pending [[id, uid, nom, kind, ref, titre, reçu à]...], sent [[id, destinataire, titre, état, kind, ref, uid]...],
    playing (id joué ici), xfers (id → HashMap des transferts en cours, sens "out" ou "in").
*/
params [["_act", "state"], ["_arg", ""]];
private _bt = missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap];
if ((count _bt) isEqualTo 0) then {
    _bt = createHashMapFromArray [["on", false], ["code", ""], ["codeUntil", 0], ["paired", createHashMap], ["pending", []], ["sent", []], ["playing", ""], ["xfers", createHashMap]];
    missionNamespace setVariable ["COMSPEC_ATAK_Bt", _bt];
};
private _xf = _bt getOrDefault ["xfers", createHashMap];
_bt set ["xfers", _xf];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_BtUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_BtUi", _ui];
private _now = [time, serverTime] select isMultiplayer;
private _range = missionNamespace getVariable ["comspec_atak_native_bt_range", 15];
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 30] call comspec_atak_native_fnc_notify; };
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BLUETOOTH") then { ["BLUETOOTH"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _usable = { (_bt get "on") && {([] call comspec_atak_native_fnc_canUse) isEqualTo ""} };
private _byUid = {
    params ["_uid"];
    private _i = allPlayers findIf { getPlayerUID _x isEqualTo _uid };
    if (_i < 0) then { objNull } else { allPlayers select _i }
};
// Un autre téléphone est-il joignable en Bluetooth : en ligne, vivant, téléphone porté, Bluetooth allumé, à portée.
private _reach = {
    params ["_u"];
    !isNull _u && {alive _u} && {(_u distance player) <= _range} && {_u getVariable ["COMSPEC_ATAK_BtOn", false]} && {[_u] call comspec_atak_native_fnc_hasDevice}
};
private _event = { params ["_to", "_type", "_args"]; ["comspec_atak_native_bt", [_type, _args], _to] call CBA_fnc_targetEvent; };
private _publish = {
    private _on = call _usable;
    if ((player getVariable ["COMSPEC_ATAK_BtOn", false]) isNotEqualTo _on) then { player setVariable ["COMSPEC_ATAK_BtOn", _on, true]; };
    private _code = if (_on && {(_bt get "code") isNotEqualTo ""} && {_now < (_bt get "codeUntil")}) then { [_bt get "code", _bt get "codeUntil"] } else { [] };
    if ((player getVariable ["COMSPEC_ATAK_BtCode", []]) isNotEqualTo _code) then { player setVariable ["COMSPEC_ATAK_BtCode", _code, true]; };
};
// Demandes reçues de plus d'une minute : refusées d'office.
private _prune = {
    private _p = _bt get "pending";
    {
        if ((_now - (_x select 6)) > 60) then {
            private _u = [_x select 1] call _byUid;
            if (!isNull _u) then { [_u, "answer", [_x select 0, false, "pas de réponse", name player, _x select 5]] call _event; };
        };
    } forEach _p;
    _bt set ["pending", _p select { (_now - (_x select 6)) <= 60 }];
};
private _play = ["snd", "mus"];
private _files = createHashMapFromArray [["pho", "photo"], ["mp3", "music"]];
private _kindName = { params ["_k"]; switch (_k) do { case "snd": { "son" }; case "mus": { "musique" }; case "mp3": { "morceau" }; case "pho": { "photo" }; case "pos": { "position" }; case "wp": { "itinéraire" }; case "plan": { "plan" }; case "seek": { "fiche SEEK" }; case "sse": { "fiche SSE" }; default { "élément" }; } };
private _sentSet = {
    params ["_id", "_state"];
    { if ((_x select 0) isEqualTo _id) then { _x set [3, _state]; }; } forEach (_bt get "sent");
};
private _dll = { params ["_cmd", ["_args", []]]; ([_cmd, _args] call comspec_atak_native_fnc_extensionCall) splitString "|" };
// Fin d'un transfert (les deux sens) : fichier fermé ou réception abandonnée côté DLL.
private _drop = {
    params ["_id", ["_dllToo", true]];
    private _x0 = _xf getOrDefault [_id, createHashMap];
    if (_dllToo && {(count _x0) > 0}) then {
        if ((_x0 get "dir") isEqualTo "out") then {
            if ((_x0 getOrDefault ["handle", ""]) isNotEqualTo "") then { ["BtFileClose", [_x0 get "handle"]] call _dll; };
        } else {
            if (_x0 getOrDefault ["open", false]) then { ["BtRecvAbort", [_id]] call _dll; };
        };
    };
    _xf deleteAt _id;
};

switch (_act) do {
    case "state": { _bt };
    case "on": { call _usable };
    case "publish": {
        call _publish;
        // Réception figée (l'autre est parti sans prévenir) : abandonnée au bout de 30 s.
        {
            if ((_y get "dir") isEqualTo "in" && {(_now - (_y getOrDefault ["last", _now])) > 30}) then {
                [_x] call _drop;
                ["WARNING", format ["Bluetooth : réception de « %1 » interrompue (plus rien de %2)", _y get "title", _y get "name"]] call _say;
                call _rerender;
            };
        } forEach +_xf;
    };
    case "toggle": {
        _bt set ["on", !(_bt get "on")];
        if !(_bt get "on") then {
            _bt set ["code", ""]; _bt set ["pending", []];
            // Bluetooth coupé : transferts en cours interrompus des deux côtés.
            {
                private _u = [_y get "uid"] call _byUid;
                if (!isNull _u) then { [_u, "fabort", [_x, "Bluetooth coupé"]] call _event; };
                [_x] call _drop;
                if ((_y get "dir") isEqualTo "out") then { [_x, "INTERROMPU"] call _sentSet; };
            } forEach +_xf;
        };
        call _publish;
        ["INFO", ["Bluetooth désactivé", "Bluetooth activé"] select (_bt get "on")] call _say;
        call _rerender;
    };
    case "showCode": {
        if !(call _usable) exitWith { ["WARNING", "Activez d'abord le Bluetooth"] call _say; };
        _bt set ["code", str (100000 + floor random 900000)];
        _bt set ["codeUntil", _now + 120];
        call _publish;
        call _rerender;
        // Fin de validité : code retiré de l'unité et page redessinée.
        [{
            ["publish"] call comspec_atak_native_fnc_btAction;
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BLUETOOTH") then { ["BLUETOOTH"] call comspec_atak_native_fnc_pageRender; };
        }, [], 121] call CBA_fnc_waitAndExecute;
    };
    case "hideCode": { _bt set ["code", ""]; call _publish; call _rerender; };
    case "enterCode": {
        if !(call _usable) exitWith { ["WARNING", "Activez d'abord le Bluetooth"] call _say; };
        private _code = (["btCode"] call comspec_atak_native_fnc_formValue) regexReplace ["[^0-9]", ""];
        if ((count _code) < 4) exitWith { ["WARNING", "Saisissez le code affiché par l'autre téléphone"] call _say; };
        private _found = allPlayers select {
            _x isNotEqualTo player && {[_x] call _reach} && {
                (_x getVariable ["COMSPEC_ATAK_BtCode", []]) params [["_c", ""], ["_until", 0]];
                _c isEqualTo _code && {_now < _until}
            }
        };
        if ((count _found) isEqualTo 0) exitWith { ["WARNING", format ["Aucun appareil à moins de %1 m n'affiche ce code", round _range]] call _say; };
        private _u = _found select 0;
        (_bt get "paired") set [getPlayerUID _u, name _u];
        [_u, "paired", [getPlayerUID player, name player]] call _event;
        ["SUCCESS", format ["Appairé avec l'ATAK de %1", name _u]] call _say;
        call _rerender;
    };
    case "unpair": {
        private _name = (_bt get "paired") getOrDefault [_arg, "?"];
        (_bt get "paired") deleteAt _arg;
        if ((_ui getOrDefault ["target", ""]) isEqualTo _arg) then { _ui set ["target", ""]; };
        private _u = [_arg] call _byUid;
        if (!isNull _u) then { [_u, "unpaired", [getPlayerUID player]] call _event; };
        ["INFO", format ["Appareil oublié : %1", _name]] call _say;
        call _rerender;
    };
    case "target": { _ui set ["target", [_arg, ""] select ((_ui getOrDefault ["target", ""]) isEqualTo _arg)]; _ui set ["page", 0]; call _rerender; };
    case "tab": {
        _ui set ["tab", _arg]; _ui set ["page", 0];
        // Listes relues à l'ouverture de l'onglet : nouvelles photos, morceaux ajoutés au dossier.
        if (_arg isEqualTo "pho") then { ["list"] call comspec_atak_native_fnc_photoLibrary; };
        if (_arg isEqualTo "mus") then { _ui deleteAt "mp3"; };
        call _rerender;
    };
    case "search": { _ui set ["q", (["btSearch"] call comspec_atak_native_fnc_formValue) trim [" ", 0]]; _ui set ["page", 0]; call _rerender; };
    case "page": { _ui set ["page", _arg max 0]; call _rerender; };
    case "view": { _ui set ["view", [_arg, ""] select ((_ui getOrDefault ["view", ""]) isEqualTo _arg)]; call _rerender; };
    case "send": {
        if !(call _usable) exitWith { ["WARNING", "Activez d'abord le Bluetooth"] call _say; };
        private _uid = _ui getOrDefault ["target", ""];
        private _u = [_uid] call _byUid;
        if !([_u] call _reach) exitWith { ["WARNING", format ["%1 hors de portée Bluetooth (%2 m) ou Bluetooth éteint", (_bt get "paired") getOrDefault [_uid, "L'appareil"], round _range]] call _say; };
        ((uiNamespace getVariable ["COMSPEC_ATAK_BtList", []]) param [_arg, []]) params [["_kind", ""], ["_ref", ""], ["_title", ""]];
        if (_kind isEqualTo "") exitWith {};
        if (_ref isEqualTo "" && {!(_kind in ["pos", "wp"])}) exitWith {};
        private _id = format ["%1-%2", getPlayerUID player, round (diag_tickTime * 1000)];
        [_u, "offer", [_id, getPlayerUID player, name player, _kind, _ref, _title]] call _event;
        private _sent = _bt get "sent";
        _sent pushBack [_id, name _u, _title, "EN ATTENTE", _kind, _ref, _uid];
        while { (count _sent) > 8 } do { _sent deleteAt 0; };
        ["INFO", format ["%1 « %2 » proposé(e) à %3", [_kind] call _kindName, _title, name _u]] call _say;
        call _rerender;
    };
    case "accept";
    case "refuse": {
        call _prune;
        private _p = _bt get "pending";
        private _i = _p findIf { (_x select 0) isEqualTo _arg };
        if (_i < 0) exitWith { ["WARNING", "Demande expirée"] call _say; call _rerender; };
        (_p deleteAt _i) params ["_id", "_uid", "_name", "_kind", "_ref", "_title"];
        private _u = [_uid] call _byUid;
        private _ok = _act isEqualTo "accept";
        if (_ok && {!([_u] call _reach)}) exitWith {
            ["WARNING", format ["%1 n'est plus à portée Bluetooth", _name]] call _say;
            call _rerender;
        };
        if (_ok && {_kind in _play}) then {
            // Coupe le son précédent, puis le haut-parleur de mon téléphone joue le son : entendu par les joueurs proches.
            ["stopPlay"] call comspec_atak_native_fnc_btAction;
            private _sr = missionNamespace getVariable ["comspec_atak_native_bt_sound_range", 20];
            private _near = allPlayers select { (_x distance player) < (_sr + 10) };
            ["comspec_atak_native_btPlay", ["play", [player, _kind, _ref, _sr, _id]], _near] call CBA_fnc_targetEvent;
            _bt set ["playing", _id];
            _bt set ["playingNear", _near];
            ["SUCCESS", format ["Lecture de « %1 » (envoyé par %2)", _title, _name]] call _say;
        };
        if (_ok && {!(_kind in _play)}) then {
            // Réception attendue : seuls les envois acceptés sont pris (fn_btAction « data », « fbeg »).
            _xf set [_id, createHashMapFromArray [["dir", "in"], ["kind", _kind], ["title", _title], ["uid", _uid], ["name", _name], ["done", 0], ["total", 0], ["last", _now]]];
            ["INFO", format ["Réception de « %1 » depuis %2…", _title, _name]] call _say;
        };
        if (!isNull _u) then { [_u, "answer", [_id, _ok, "", name player, _title]] call _event; };
        call _rerender;
    };
    case "cancel": {
        private _x0 = _xf getOrDefault [_arg, createHashMap];
        if ((count _x0) isEqualTo 0) exitWith {};
        private _u = [_x0 get "uid"] call _byUid;
        if (!isNull _u) then { [_u, "fabort", [_arg, format ["annulé par %1", name player]]] call _event; };
        if ((_x0 get "dir") isEqualTo "out") then { [_arg, "ANNULÉ"] call _sentSet; };
        [_arg] call _drop;
        ["INFO", format ["Transfert de « %1 » annulé", _x0 get "title"]] call _say;
        call _rerender;
    };
    case "stopPlay": {
        private _id = _bt get "playing";
        if (_id isEqualTo "") exitWith {};
        ["comspec_atak_native_btPlay", ["stop", [_id]], (_bt getOrDefault ["playingNear", []]) select { !isNull _x }] call CBA_fnc_targetEvent;
        _bt set ["playing", ""];
        call _rerender;
    };
    // Envoi accepté par l'autre : données tout de suite, fichiers ouverts puis envoyés par morceaux (« pump »).
    case "deliver": {
        _arg params ["_id", "_u", "_kind", "_ref", "_title"];
        if !([_u] call _reach) exitWith { [_id, "HORS PORTÉE"] call _sentSet; [_u, "fabort", [_id, "hors de portée"]] call _event; };
        if (_kind in _files) then {
            private _r = ["BtFileOpen", [_files get _kind, _ref]] call _dll;
            if ((_r param [0, ""]) isNotEqualTo "OK") exitWith {
                private _why = _r param [1, "DLL du mod à recompiler (build_mod.bat)"];
                if (_why in ["unknown_command", "unknown_function", "not_connected"]) then { _why = "DLL du mod à recompiler (build_mod.bat)"; };
                [_u, "fabort", [_id, _why]] call _event;
                [_id, "ÉCHEC"] call _sentSet;
                ["WARNING", format ["« %1 » non envoyé : %2", _title, _why]] call _say;
            };
            _r params ["", "_h", "_fname", "_size", "_chunks"];
            _xf set [_id, createHashMapFromArray [["dir", "out"], ["kind", _kind], ["title", _title], ["uid", getPlayerUID _u], ["name", name _u],
                ["handle", _h], ["done", 0], ["total", parseNumber _chunks], ["last", _now]]];
            [_u, "fbeg", [_id, _kind, _fname, parseNumber _size, parseNumber _chunks, _title]] call _event;
            [_id, "0 %"] call _sentSet;
            if ((uiNamespace getVariable ["COMSPEC_ATAK_BtPump", -1]) < 0) then {
                uiNamespace setVariable ["COMSPEC_ATAK_BtPump", [{ ["pump"] call comspec_atak_native_fnc_btAction; }, 0.25] call CBA_fnc_addPerFrameHandler];
            };
        } else {
            private _data = ["payload", [_kind, _ref]] call comspec_atak_native_fnc_btItems;
            if ((count _data) isEqualTo 0) exitWith { [_u, "fabort", [_id, "élément introuvable"]] call _event; [_id, "ÉCHEC"] call _sentSet; };
            [_u, "data", [_id, _kind, _title, _data, [player] call comspec_atak_native_fnc_unitCallsign]] call _event;
            [_id, "ENVOYÉ"] call _sentSet;
        };
        call _rerender;
    };
    case "pump": {
        private _outs = (keys _xf) select { ((_xf get _x) get "dir") isEqualTo "out" };
        if ((count _outs) isEqualTo 0) exitWith {
            [uiNamespace getVariable ["COMSPEC_ATAK_BtPump", -1]] call CBA_fnc_removePerFrameHandler;
            uiNamespace setVariable ["COMSPEC_ATAK_BtPump", -1];
        };
        private _redraw = false;
        {
            private _id = _x;
            private _t = _xf get _id;
            private _u = [_t get "uid"] call _byUid;
            if !([_u] call _reach && {call _usable}) then {
                if (!isNull _u) then { [_u, "fabort", [_id, "hors de portée Bluetooth"]] call _event; };
                [_id, "HORS PORTÉE"] call _sentSet;
                [_id] call _drop;
                ["WARNING", format ["« %1 » interrompu : %2 hors de portée Bluetooth", _t get "title", _t get "name"]] call _say;
                _redraw = true;
                continue;
            };
            private _n = _t get "done";
            private _total = _t get "total";
            private _batch = [];
            for "_k" from _n to ((_n + 3) min (_total - 1)) do {
                private _r = ["BtFileRead", [_t get "handle", str _k]] call _dll;
                if ((_r param [0, ""]) isNotEqualTo "OK") exitWith { _batch = []; _t set ["err", _r param [1, "lecture"]]; };
                _batch pushBack (_r param [1, ""]);
            };
            if ((_t getOrDefault ["err", ""]) isNotEqualTo "") then {
                [_u, "fabort", [_id, _t get "err"]] call _event;
                [_id, "ÉCHEC"] call _sentSet;
                [_id] call _drop;
                _redraw = true;
                continue;
            };
            [_u, "fchk", [_id, _n, _batch]] call _event;
            _n = _n + (count _batch);
            _t set ["done", _n];
            private _pct = floor (100 * _n / (_total max 1));
            if (_pct >= ((_t getOrDefault ["shown", 0]) + 10) || {_n >= _total}) then { _t set ["shown", _pct]; [_id, format ["%1 %2", _pct, "%"]] call _sentSet; _redraw = true; };
            if (_n >= _total) then {
                [_u, "fend", [_id]] call _event;
                [_id, "VÉRIFICATION"] call _sentSet;
                [_id] call _drop;
            };
        } forEach _outs;
        if (_redraw) then { call _rerender; };
    };
    case "recv": {
        _arg params [["_type", ""], ["_a", []]];
        switch (_type) do {
            case "paired": {
                _a params [["_uid", ""], ["_name", ""]];
                if !(call _usable) exitWith {};
                // Le code n'est valable que pour un appairage : il disparaît une fois utilisé.
                _bt set ["code", ""];
                call _publish;
                (_bt get "paired") set [_uid, _name];
                ["SUCCESS", format ["Bluetooth : appairé avec l'ATAK de %1", _name]] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                call _rerender;
            };
            case "unpaired": {
                _a params [["_uid", ""]];
                private _name = (_bt get "paired") getOrDefault [_uid, ""];
                if (_name isEqualTo "") exitWith {};
                (_bt get "paired") deleteAt _uid;
                ["INFO", format ["Bluetooth : %1 a oublié votre appareil", _name]] call _say;
                call _rerender;
            };
            case "offer": {
                _a params [["_id", ""], ["_uid", ""], ["_name", ""], ["_kind", ""], ["_ref", ""], ["_title", ""]];
                private _u = [_uid] call _byUid;
                private _why = switch (true) do {
                    case !(call _usable): { "Bluetooth éteint" };
                    case !(_uid in (_bt get "paired")): { "appareil non appairé" };
                    case ((count (_bt get "pending")) >= 5): { "trop de demandes en attente" };
                    default { "" };
                };
                if (_why isNotEqualTo "") exitWith { if (!isNull _u) then { [_u, "answer", [_id, false, _why, name player, _title]] call _event; }; };
                call _prune;
                (_bt get "pending") pushBack [_id, _uid, _name, _kind, _ref, _title, _now];
                ["MESSAGE", if (_kind in _play) then {
                    format ["Bluetooth : %1 veut lire « %2 » sur votre téléphone (app Bluetooth : accepter ou refuser)", _name, _title]
                } else {
                    format ["Bluetooth : %1 veut vous envoyer %2 « %3 » (app Bluetooth : accepter ou refuser)", _name, [_kind] call _kindName, _title]
                }] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                call _rerender;
            };
            case "answer": {
                _a params [["_id", ""], ["_ok", false], ["_why", ""], ["_name", ""], ["_title", ""]];
                private _row = (_bt get "sent") select { (_x select 0) isEqualTo _id } param [0, []];
                [_id, ["REFUSÉ", "ACCEPTÉ"] select _ok] call _sentSet;
                if (_ok) then { ["SUCCESS", format ["%1 a accepté « %2 »", _name, _title]] call _say; }
                else { ["WARNING", format ["%1 a refusé « %2 »%3", _name, _title, ["", format [" (%1)", _why]] select (_why isNotEqualTo "")]] call _say; };
                if (_ok && {(count _row) >= 7} && {!((_row select 4) in _play)}) then {
                    ["deliver", [_id, [_row select 6] call _byUid, _row select 4, _row select 5, _row select 2]] call comspec_atak_native_fnc_btAction;
                };
                call _rerender;
            };
            case "data": {
                _a params [["_id", ""], ["_kind", ""], ["_title", ""], ["_data", []], ["_from", ""]];
                private _t = _xf getOrDefault [_id, createHashMap];
                if ((count _t) isEqualTo 0 || {(_t get "kind") isNotEqualTo _kind}) exitWith {};
                [_id, false] call _drop;
                private _sum = ["apply", [_id, _kind, _title, _data, [_from, _t get "name"] select (_from isEqualTo "")]] call comspec_atak_native_fnc_btItems;
                private _u = [_t get "uid"] call _byUid;
                if (!isNull _u) then { [_u, "done", [_id, _sum isNotEqualTo "", ["élément illisible", ""] select (_sum isNotEqualTo "")]] call _event; };
                if (_sum isEqualTo "") exitWith { ["WARNING", format ["Bluetooth : « %1 » illisible", _title]] call _say; call _rerender; };
                ["SUCCESS", format ["Bluetooth : reçu de %1, %2", _t get "name", _sum]] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                call _rerender;
            };
            case "fbeg": {
                _a params [["_id", ""], ["_kind", ""], ["_fname", ""], ["_size", 0], ["_chunks", 0], ["_title", ""]];
                private _t = _xf getOrDefault [_id, createHashMap];
                if ((count _t) isEqualTo 0 || {!(_kind in _files)}) exitWith {};
                private _u = [_t get "uid"] call _byUid;
                private _r = ["BtRecvOpen", [_id, _files get _kind, _fname, str _size]] call _dll;
                if ((_r param [0, ""]) isNotEqualTo "OK") exitWith {
                    private _why = _r param [1, "DLL du mod à recompiler (build_mod.bat)"];
                    if (_why in ["unknown_command", "unknown_function", "not_connected"]) then { _why = "DLL du mod à recompiler chez le destinataire (build_mod.bat)"; };
                    if (!isNull _u) then { [_u, "fabort", [_id, _why]] call _event; };
                    [_id, false] call _drop;
                    ["WARNING", format ["Bluetooth : « %1 » refusé par le téléphone : %2", _title, _why]] call _say;
                    call _rerender;
                };
                _t set ["open", true]; _t set ["fname", _fname]; _t set ["total", _chunks]; _t set ["size", _size]; _t set ["last", _now];
                call _rerender;
            };
            case "fchk": {
                _a params [["_id", ""], ["_first", 0], ["_parts", []]];
                private _t = _xf getOrDefault [_id, createHashMap];
                if ((count _t) isEqualTo 0 || {!(_t getOrDefault ["open", false])}) exitWith {};
                private _bad = "";
                {
                    private _r = ["BtRecvWrite", [_id, str (_first + _forEachIndex), _x]] call _dll;
                    if ((_r param [0, ""]) isNotEqualTo "OK") exitWith { _bad = _r param [1, "écriture"]; };
                } forEach _parts;
                if (_bad isNotEqualTo "") exitWith {
                    private _u = [_t get "uid"] call _byUid;
                    if (!isNull _u) then { [_u, "fabort", [_id, _bad]] call _event; };
                    [_id] call _drop;
                    ["WARNING", format ["Bluetooth : réception de « %1 » interrompue (%2)", _t get "title", _bad]] call _say;
                    call _rerender;
                };
                private _n = _first + (count _parts);
                _t set ["done", _n]; _t set ["last", _now];
                private _pct = floor (100 * _n / ((_t get "total") max 1));
                if (_pct >= ((_t getOrDefault ["shown", 0]) + 10)) then { _t set ["shown", _pct]; call _rerender; };
            };
            case "fend": {
                _a params [["_id", ""]];
                private _t = _xf getOrDefault [_id, createHashMap];
                if ((count _t) isEqualTo 0) exitWith {};
                private _r = ["BtRecvEnd", [_id]] call _dll;
                _xf deleteAt _id;
                private _u = [_t get "uid"] call _byUid;
                private _ok = (_r param [0, ""]) isEqualTo "OK";
                if (!isNull _u) then { [_u, "done", [_id, _ok, ["", _r param [1, "fichier incomplet"]] select !_ok]] call _event; };
                if (!_ok) exitWith { ["WARNING", format ["Bluetooth : « %1 » incomplet (%2)", _t get "title", _r param [1, "?"]]] call _say; call _rerender; };
                private _shown = _r param [2, _t get "title"];
                private _in = missionNamespace getVariable ["COMSPEC_ATAK_BtInbox", []];
                _in pushBack [_id, _t get "kind", _shown, _t get "name", [dayTime, "HH:MM"] call BIS_fnc_timeToString, [_r param [1, ""], _shown]];
                while { (count _in) > 30 } do { _in deleteAt 0; };
                missionNamespace setVariable ["COMSPEC_ATAK_BtInbox", _in];
                if ((_t get "kind") isEqualTo "pho") then {
                    // Galerie relue : la photo reçue y apparaît tout de suite.
                    uiNamespace setVariable ["COMSPEC_ATAK_PhotoLibAt", nil];
                    ["SUCCESS", format ["Bluetooth : photo reçue de %1, dans la Galerie (%2)", _t get "name", _shown]] call _say;
                } else {
                    ["files", "", false] call comspec_atak_native_fnc_musicAction;
                    ["SUCCESS", format ["Bluetooth : morceau reçu de %1, dans l'app Musique, Mes fichiers (%2)", _t get "name", _shown]] call _say;
                };
                [] call comspec_atak_native_fnc_vibrate;
                call _rerender;
            };
            case "fabort": {
                _a params [["_id", ""], ["_why", ""]];
                private _t = _xf getOrDefault [_id, createHashMap];
                // Refus avant même le premier morceau : la ligne d'envoi garde la raison.
                [_id, "ÉCHEC"] call _sentSet;
                if ((count _t) isEqualTo 0) exitWith {
                    if ((((_bt get "sent") select { (_x select 0) isEqualTo _id }) param [0, []]) isNotEqualTo []) then { ["WARNING", format ["Bluetooth : envoi interrompu (%1)", _why]] call _say; call _rerender; };
                };
                [_id] call _drop;
                ["WARNING", format ["Bluetooth : « %1 » interrompu (%2)", _t get "title", _why]] call _say;
                call _rerender;
            };
            case "done": {
                _a params [["_id", ""], ["_ok", false], ["_why", ""]];
                private _row = ((_bt get "sent") select { (_x select 0) isEqualTo _id }) param [0, []];
                if ((count _row) isEqualTo 0) exitWith {};
                [_id, ["ÉCHEC", "REÇU"] select _ok] call _sentSet;
                if (_ok) then { ["SUCCESS", format ["« %1 » bien reçu par %2", _row select 2, _row select 1]] call _say; }
                else { ["WARNING", format ["« %1 » : échec chez %2 (%3)", _row select 2, _row select 1, _why]] call _say; };
                call _rerender;
            };
        };
    };
};
