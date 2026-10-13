/*
    Connexion au compte Athena par code, sans clé de communauté ni identifiant à saisir.
    Le jeu demande un code court (COMSPEC Link PairStart) ; le joueur l'autorise sur athena.ttrd.fr/lier,
    connecté à son compte ; le jeu reçoit alors sa session (compte, communauté, Steam associé) par PairStatus.
    Aux parties suivantes, la session est restaurée ou la connexion Steam suffit : plus rien à faire.
    Params : [action, option]
      ["start"]          nouveau code (remplace l'ancien) et attente de l'autorisation
      ["offer", ouvrir]  au lancement sans session : un code (gardé s'il est encore valable), une notification,
                         et la page ouverte dans le navigateur si « ouvrir » (juste après « Oui » au service Athéna)
      ["open"]           ouvre la page /lier avec le code déjà rempli (COMSPEC Link 2.0.72+)
      ["cancel"]         abandonne le code en cours
    État : uiNamespace « COMSPEC_ATAK_AthenaLink » = [état, code, échéance diag_tickTime, adresse, adresse complète, détail]
      état : "" (aucun), "asking", "pending", "approved", "expired", "error".
*/
params [["_action", "start"], ["_open", false]];
_action = toLower _action;
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _link = uiNamespace getVariable ["COMSPEC_ATAK_AthenaLink", ["", "", 0, "", "", ""]];
_link params ["_state", "_code", "_until", "_uri", "_full", "_detail"];
private _live = _state in ["asking", "pending"] && {diag_tickTime < _until || {_state isEqualTo "asking"}};

private _redraw = {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isEqualTo "ATHENA") then { [{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
};

switch (_action) do {
    case "cancel": {
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaLink", ["", "", 0, "", "", ""]];
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkToken", diag_tickTime];
        call _redraw;
    };
    case "open": {
        if (!_live || {_state isNotEqualTo "pending"}) exitWith {};
        private _target = [_full, _uri] select (_full isEqualTo "");
        private _ext = ["COMSPECATAKNativeExtension", "COMSPECExtension"] select _bridge;
        private _r = _ext callExtension ["OpenAthenaLink", [_target]];
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if ((_r select [0, 2]) isNotEqualTo "OK") then {
            // DLL plus ancienne (commande inconnue) : le joueur tape l'adresse lui-même.
            uiNamespace setVariable ["COMSPEC_ATAK_AthenaHint", [format ["Ouvrez %1 dans votre navigateur et saisissez le code %2.", _uri, _code], true, diag_tickTime]];
            call _redraw;
        };
    };
    case "offer": {
        if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith {};
        // Code déjà demandé : l'ouvrir maintenant, ou dès qu'il arrive.
        if (_live) exitWith { if (_open) then { if (_state isEqualTo "pending") then { ["open"] call comspec_atak_native_fnc_athenaLink; } else { uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkOpen", true]; }; }; };
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkOpen", _open];
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkNotify", true];
        ["start"] call comspec_atak_native_fnc_athenaLink;
    };
    case "start": {
        private _token = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkToken", _token];
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaLink", ["asking", "", 0, "", "", ""]];
        call _redraw;
        [_token, _bridge, _redraw] spawn {
            params ["_token", "_bridge", "_redraw"];
            private _ext = {
                params ["_cmd", ["_args", []]];
                private _r = if (_bridge) then { ["COMSPECExtension", _cmd, _args] call comspec_atak_native_fnc_extAwait } else { [_cmd, _args] call comspec_atak_native_fnc_extensionCall };
                if (_r isEqualType []) then { _r = _r param [0, ""]; };
                if (_r isEqualType "") then { _r } else { str _r }
            };
            private _mine = { (uiNamespace getVariable ["COMSPEC_ATAK_AthenaLinkToken", -1]) isEqualTo _token };
            // Portail Athena fixe : plus d’adresse réglable ni mémorisée.
            private _url = "https://athena.ttrd.fr/public";
            private _pack = if (_bridge) then { [] call comspec_overwatch_connect_fnc_packVersion } else { "native" };
            private _steam = getPlayerUID player;
            if ((count _steam) >= 8) then { ["SetSteamId", [_steam]] call _ext; };
            // OK|code|durée s|adresse|intervalle s|adresse avec le code (6e champ : COMSPEC Link 2.0.72+)
            private _cells = (["PairStart", [_url, _steam, _pack]] call _ext) splitString "|";
            if !(call _mine) exitWith {};
            if ((_cells param [0, ""]) isNotEqualTo "OK") exitWith {
                private _why = _cells param [1, "sans réponse"];
                if ((count _cells) > 2) then { ["WARN", "ATHENA", format ["PairStart %1 sur %2 : %3", _why, _url, ((_cells select [2]) joinString "|")]] call comspec_atak_native_fnc_log; };
                uiNamespace setVariable ["COMSPEC_ATAK_AthenaLink", ["error", "", 0, "", "", _why]];
                ["WARN", "ATHENA", format ["Code de connexion impossible : %1", _why]] call comspec_atak_native_fnc_log;
                call _redraw;
            };
            private _code = _cells param [1, ""];
            private _ttl = (parseNumber (_cells param [2, "600"])) max 30;
            private _uri = _cells param [3, ""];
            if (((toLower _uri) find "http") isNotEqualTo 0) then { _uri = _url + "/lier"; };
            private _full = _cells param [5, ""];
            if (((toLower _full) find "http") isNotEqualTo 0) then { _full = format ["%1?code=%2", _uri, _code]; };
            private _every = ((parseNumber (_cells param [4, "2"])) max 2) min 15;
            private _until = diag_tickTime + _ttl;
            uiNamespace setVariable ["COMSPEC_ATAK_AthenaLink", ["pending", _code, _until, _uri, _full, ""]];
            ["INFO", "ATHENA", format ["Code de connexion %1 (valable %2 s)", _code, round _ttl]] call comspec_atak_native_fnc_log;
            call _redraw;
            // Adresse courte affichée sans « https:// » ni « /public » : plus simple à taper sur un autre appareil.
            private _short = _uri regexReplace ["^https?://", ""];
            _short = _short regexReplace ["/public/", "/"];
            if (uiNamespace getVariable ["COMSPEC_ATAK_AthenaLinkNotify", false]) then {
                uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkNotify", false];
                ["INFO", format ["Connexion Athena : sur %1, saisissez le code %2", _short, _code], 12, 40] call comspec_atak_native_fnc_notify;
                hintSilent parseText ((format ["<t size='1.1' font='RobotoCondensedBold'>Connexion Athena</t><br/>Sur <t color='#5cc76b'>%1</t>, connecté à votre compte, saisissez le code :<br/><t size='1.8' font='RobotoCondensedBold' color='#5cc76b'>%2</t><br/><t size='0.85' color='#8a9a93'>Aussi dans l'app Athena du téléphone, qui accepte aussi e-mail et mot de passe.</t>", _short, _code]) call comspec_atak_native_fnc_tr);
            };
            if (uiNamespace getVariable ["COMSPEC_ATAK_AthenaLinkOpen", false]) then {
                uiNamespace setVariable ["COMSPEC_ATAK_AthenaLinkOpen", false];
                ["open"] call comspec_atak_native_fnc_athenaLink;
            };
            private _result = "";
            while { (call _mine) && {_result isEqualTo ""} && {diag_tickTime < _until} } do {
                sleep _every;
                if !(call _mine) exitWith {};
                private _r = ["PairStatus", []] call _ext;
                switch (true) do {
                    case (_r isEqualTo "OK|approved"): { _result = "approved"; };
                    case ((_r find "ERR|expired") isEqualTo 0 || {(_r find "ERR|no_pairing") isEqualTo 0}): { _result = "expired"; };
                    case ((_r find "ERR|http_4") isEqualTo 0): { _result = "error"; };
                    default {};
                };
            };
            if !(call _mine) exitWith {};
            if (_result isEqualTo "") then { _result = "expired"; };
            uiNamespace setVariable ["COMSPEC_ATAK_AthenaLink", [_result, _code, 0, _uri, _full, ""]];
            ["INFO", "ATHENA", format ["Code de connexion %1 : %2", _code, _result]] call comspec_atak_native_fnc_log;
            if (_result isEqualTo "approved") then {
                hintSilent (("") call comspec_atak_native_fnc_tr);
                ["SUCCESS", "Compte Athena connecté : synchronisation de votre profil.", 6, 40] call comspec_atak_native_fnc_notify;
                // Même suite qu'une connexion Steam réussie : profil, réglages de la communauté, canal du poste.
                if (_bridge) then {
                    [] call comspec_overwatch_connect_fnc_pollAuth;
                    [] call comspec_overwatch_connect_fnc_applyBootstrap;
                    [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
                };
            };
            call _redraw;
        };
    };
};
true
