/*
    Volet de la carte, onglets CANAL, SMS et ALERTE : l'essentiel de ces trois apps sans quitter la carte (TTRD, 14/11 :
    « il faut forcément quitter la carte pour accéder au reste »). Le volet garde sa place à droite de la carte ; l'onglet
    CARTE rend le volet d'avant (situation, fond de carte, calques).
    Ce ne sont pas les apps entières : derniers messages, saisie et envoi ; pour le reste (canaux, conversations, accusés,
    SALUTE, détresse), le bouton du bas ouvre l'app.
    Params : [onglet "CHAT" | "SMS" | "ALERT"]. Renvoie les lignes de fn_formRender.
    Les envois passent par les mêmes fonctions que les apps (fn_chatSend, fn_p2pSend, fn_alertsAction « quick »).
*/
params [["_tab", "CHAT", [""]], ["_force", false, [false]]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _muted = COMSPEC_ATAK_Th get "hexTextMuted";
private _card = [0.06, 0.075, 0.068, 0.96];
// Texte libre d'un joueur dans un texte structuré : « & » et « < » neutralisés.
private _esc = { ((_this regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) };
private _rows = [];

switch (_tab) do {
    case "CHAT": {
        private _ch = _s getOrDefault ["chatChannel", "general"];
        if !(_ch isEqualType "") then { _ch = "general"; };
        _rows pushBack ["section", "Canal", _ch];
        private _msgs = [_ch] call comspec_atak_native_fnc_messagesAll;
        private _n = count _msgs;
        if (_n isEqualTo 0) then {
            _rows pushBack ["text", format ["<t color='%1'>Aucun message sur ce canal.</t>", _muted], _card];
        } else {
            private _lines = [];
            {
                _lines pushBack format ["<t size='0.8' color='%1'>%2 · %3</t><br/>%4", _muted, _x getOrDefault ["time", ""],
                    [(_x getOrDefault ["author", ""]) call _esc, "Vous"] select (_x getOrDefault ["mine", false]), ((_x getOrDefault ["body", ""]) select [0, 240]) call _esc];
            } forEach (_msgs select [(_n - 8) max 0, 8]);
            _rows pushBack ["text", _lines joinString "<br/>", _card];
        };
        _rows pushBack ["field", "mqChat", "Message", "", "ENVOYER", {
            // fn_chatSend lit le champ de saisie de Messages : on lui donne celui du volet.
            uiNamespace setVariable ["COMSPEC_ATAK_ChatEdit", (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["mqChat", controlNull]];
            [] call comspec_atak_native_fnc_chatSend;
            [{ ["refresh", true] call comspec_atak_native_fnc_mapQuickApp; }, [], 0.4] call comspec_atak_native_fnc_waitAndExec;
        }, true];
        _rows pushBack ["buttons", [
            ["ACTUALISER", { ["refresh", true] call comspec_atak_native_fnc_mapQuickApp; }, false, true, "Relit les derniers messages du canal"],
            ["OUVRIR MESSAGES", { ["CHAT"] call comspec_atak_native_fnc_navigate; }, false, true, "Tous les canaux et les conversations"]
        ]];
    };
    case "SMS": {
        _rows pushBack ["section", "SMS", "de téléphone à téléphone"];
        private _p2p = _data getOrDefault ["p2p", []];
        private _n = count _p2p;
        if (_n isEqualTo 0) then {
            _rows pushBack ["text", format ["<t color='%1'>Aucun SMS pour l'instant.</t>", _muted], _card];
        } else {
            private _lines = [];
            {
                private _in = (_x getOrDefault ["dir", "out"]) isEqualTo "in";
                _lines pushBack format ["<t size='0.8' color='%1'>%2 · %3 %4</t><br/>%5", _muted, _x getOrDefault ["time", ""], ["à", "de"] select _in,
                    (_x getOrDefault ["peer", ""]) call _esc, ((_x getOrDefault ["body", ""]) select [0, 240]) call _esc];
            } forEach (_p2p select [(_n - 8) max 0, 8]);
            _rows pushBack ["text", _lines joinString "<br/>", _card];
        };
        // Destinataires : les joueurs de mon camp ; le dernier correspondant est proposé d'abord.
        private _names = (allPlayers select { _x isNotEqualTo player && {(side group _x) isEqualTo (side group player)} }) apply { name _x };
        _names sort true;
        if (_names isEqualTo []) then {
            _rows pushBack ["text", format ["<t color='%1'>Aucun autre joueur de votre camp à qui écrire.</t>", _muted]];
        } else {
            private _last = if (_n > 0) then { (_p2p select (_n - 1)) getOrDefault ["peer", ""] } else { "" };
            private _pick = _s getOrDefault ["mqSmsPeer", _last];
            if !(_pick in _names) then { _pick = _names select 0; };
            _rows pushBack ["combo", "mqPeer", "Destinataire", _names apply { [_x, _x] }, _pick];
            _rows pushBack ["field", "mqSms", "SMS", "", "ENVOYER", {
                private _peer = ["mqPeer"] call comspec_atak_native_fnc_formValue;
                (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mqSmsPeer", _peer];
                if ([_peer, ["mqSms"] call comspec_atak_native_fnc_formValue] call comspec_atak_native_fnc_p2pSend) then {
                    [{ ["refresh", true] call comspec_atak_native_fnc_mapQuickApp; }, [], 0.3] call comspec_atak_native_fnc_waitAndExec;
                };
            }, true];
        };
        _rows pushBack ["buttons", [
            ["ACTUALISER", { ["refresh", true] call comspec_atak_native_fnc_mapQuickApp; }, false, true, "Relit les derniers SMS"],
            ["OUVRIR SMS", { ["SMS"] call comspec_atak_native_fnc_navigate; }, false, true, "Toutes les conversations et leurs accusés"]
        ]];
    };
    case "ALERT": {
        _rows pushBack ["section", "Alerte", "à tout le camp, avec votre position"];
        private _quick = {
            params ["_k"];
            compile format ["['quick', '%1'] call comspec_atak_native_fnc_alertsAction; [{ ['refresh', true] call comspec_atak_native_fnc_mapQuickApp; }, [], 0.3] call comspec_atak_native_fnc_waitAndExec;", _k]
        };
        _rows pushBack ["big", [
            ["CONTACT", "TIC" call _quick, "red", true, "Troupes au contact"],
            ["FIN DE CONTACT", "TIC_CLEAR" call _quick, "green", true, "Le contact est terminé"],
            ["APPAREIL ABATTU", "EAGLE_DOWN" call _quick, "amber", true, "Aéronef au sol"]
        ]];
        private _hint = _s getOrDefault ["alertsHint", ""];
        if (_hint isEqualType "" && {_hint isNotEqualTo ""}) then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", _muted, _hint call _esc]]; };
        private _log = missionNamespace getVariable ["COMSPEC_ATAK_AlertLog", []];
        private _n = count _log;
        if (_n > 0) then {
            private _lines = [];
            {
                _x params [["_type", ""], ["_who", ""], "", ["_grid", ""], ["_text", ""], ["_time", ""]];
                _lines pushBack format ["<t size='0.8' color='%1'>%2 · %3 · %4</t><br/>%5%6", _muted, _time, _who call _esc, _grid, _type, ["", " : " + (_text call _esc)] select (_text isNotEqualTo "")];
            } forEach (_log select [(_n - 6) max 0, 6]);
            _rows pushBack ["text", _lines joinString "<br/>", _card];
        };
        _rows pushBack ["buttons", [
            ["OUVRIR ALERTES", { ["ALERTS"] call comspec_atak_native_fnc_navigate; }, false, true, "Détresse, journal complet et centrage sur une alerte"]
        ]];
    };
    // Message reçu ou envoyé : le volet est redessiné si la carte est affichée sur l'un de ces onglets.
    case "refresh": {
        // Saisie en cours dans le volet : pas de rendu (il viderait le champ), sauf après un envoi ou sur ACTUALISER.
        if (!_force && {((["mqChat"] call comspec_atak_native_fnc_formValue) + (["mqSms"] call comspec_atak_native_fnc_formValue)) isNotEqualTo ""}) exitWith {};
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "MAP" && {(missionNamespace getVariable ["COMSPEC_ATAK_MapTab", "SIT"]) isNotEqualTo "SIT"}
            && {missionNamespace getVariable ["COMSPEC_ATAK_InspOpen", false]}) then {
            [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        };
    };
};
_rows
