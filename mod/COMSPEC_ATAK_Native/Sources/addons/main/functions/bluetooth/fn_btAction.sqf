/*
    Bluetooth du téléphone : appairage par code entre ATAK proches, puis envoi de sons entre appareils appairés.
    Params : [action, argument]
      "state"                 : état (HashMap) ; "on" : Bluetooth allumé ? (booléen, vérifie aussi que le téléphone est utilisable)
      "toggle"                : allume / éteint le Bluetooth (indépendant du mode avion)
      "showCode" / "hideCode" : affiche un code d'appairage à 6 chiffres (2 min), publié sur l'unité (COMSPEC_ATAK_BtCode)
      "enterCode"             : appaire avec l'ATAK proche (portée Bluetooth) qui affiche le code saisi (champ btCode)
      "unpair" uid            : oublie l'appareil (et le prévient s'il est en ligne)
      "target" uid / "tab" / "search" / "page" : choix du destinataire et navigation dans la bibliothèque de sons
      "send" index            : propose le son n° index de la liste affichée au destinataire choisi
      "accept" id / "refuse" id : réponse à une demande reçue ; "stopPlay" : coupe le son en cours sur mon téléphone
      "recv" [type, args]     : événement CBA comspec_atak_native_bt reçu d'un autre téléphone (paired, unpaired, offer, answer)
    Portées (variables de mission, modifiables par le créateur) : comspec_atak_native_bt_range (liaison, 15 m),
    comspec_atak_native_bt_sound_range (le son est audible autour du téléphone qui le joue, 20 m).
    État (missionNamespace COMSPEC_ATAK_Bt, gardé toute la mission) : on, code, codeUntil, paired (uid → nom),
    pending [[id, uid, nom, kind, ref, titre, reçu à]...], sent [[id, destinataire, titre, état]...], playing (id joué ici).
*/
params [["_act", "state"], ["_arg", ""]];
private _bt = missionNamespace getVariable ["COMSPEC_ATAK_Bt", createHashMap];
if ((count _bt) isEqualTo 0) then {
    _bt = createHashMapFromArray [["on", false], ["code", ""], ["codeUntil", 0], ["paired", createHashMap], ["pending", []], ["sent", []], ["playing", ""]];
    missionNamespace setVariable ["COMSPEC_ATAK_Bt", _bt];
};
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

switch (_act) do {
    case "state": { _bt };
    case "on": { call _usable };
    case "publish": { call _publish; };
    case "toggle": {
        _bt set ["on", !(_bt get "on")];
        if !(_bt get "on") then { _bt set ["code", ""]; _bt set ["pending", []]; };
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
    case "tab": { _ui set ["tab", _arg]; _ui set ["page", 0]; call _rerender; };
    case "search": { _ui set ["q", (["btSearch"] call comspec_atak_native_fnc_formValue) trim [" ", 0]]; _ui set ["page", 0]; call _rerender; };
    case "page": { _ui set ["page", _arg max 0]; call _rerender; };
    case "send": {
        if !(call _usable) exitWith { ["WARNING", "Activez d'abord le Bluetooth"] call _say; };
        private _uid = _ui getOrDefault ["target", ""];
        private _u = [_uid] call _byUid;
        if !([_u] call _reach) exitWith { ["WARNING", format ["%1 hors de portée Bluetooth (%2 m) ou Bluetooth éteint", (_bt get "paired") getOrDefault [_uid, "L'appareil"], round _range]] call _say; };
        ((uiNamespace getVariable ["COMSPEC_ATAK_BtList", []]) param [_arg, []]) params [["_kind", ""], ["_ref", ""], ["_title", ""]];
        if (_ref isEqualTo "") exitWith {};
        private _id = format ["%1-%2", getPlayerUID player, round (diag_tickTime * 1000)];
        [_u, "offer", [_id, getPlayerUID player, name player, _kind, _ref, _title]] call _event;
        private _sent = _bt get "sent";
        _sent pushBack [_id, name _u, _title, "EN ATTENTE"];
        while { (count _sent) > 6 } do { _sent deleteAt 0; };
        ["INFO", format ["« %1 » proposé à %2", _title, name _u]] call _say;
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
        if (_ok) then {
            // Coupe le son précédent, puis le haut-parleur de mon téléphone joue le son : entendu par les joueurs proches.
            ["stopPlay"] call comspec_atak_native_fnc_btAction;
            private _sr = missionNamespace getVariable ["comspec_atak_native_bt_sound_range", 20];
            private _near = allPlayers select { (_x distance player) < (_sr + 10) };
            ["comspec_atak_native_btPlay", ["play", [player, _kind, _ref, _sr, _id]], _near] call CBA_fnc_targetEvent;
            _bt set ["playing", _id];
            _bt set ["playingNear", _near];
            ["SUCCESS", format ["Lecture de « %1 » (envoyé par %2)", _title, _name]] call _say;
        };
        if (!isNull _u) then { [_u, "answer", [_id, _ok, "", name player, _title]] call _event; };
        call _rerender;
    };
    case "stopPlay": {
        private _id = _bt get "playing";
        if (_id isEqualTo "") exitWith {};
        ["comspec_atak_native_btPlay", ["stop", [_id]], (_bt getOrDefault ["playingNear", []]) select { !isNull _x }] call CBA_fnc_targetEvent;
        _bt set ["playing", ""];
        call _rerender;
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
                ["MESSAGE", format ["Bluetooth : %1 veut lire « %2 » sur votre téléphone (app Bluetooth : accepter ou refuser)", _name, _title]] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                call _rerender;
            };
            case "answer": {
                _a params [["_id", ""], ["_ok", false], ["_why", ""], ["_name", ""], ["_title", ""]];
                {
                    if ((_x select 0) isEqualTo _id) then { _x set [3, ["REFUSÉ", "ACCEPTÉ"] select _ok]; };
                } forEach (_bt get "sent");
                if (_ok) then { ["SUCCESS", format ["%1 a accepté « %2 »", _name, _title]] call _say; }
                else { ["WARNING", format ["%1 a refusé « %2 »%3", _name, _title, ["", format [" (%1)", _why]] select (_why isNotEqualTo "")]] call _say; };
                call _rerender;
            };
        };
    };
};
