/*
    Connexion Athena depuis le terminal : mêmes commandes que le panneau Athena d'Overwatch
    (Steam, e-mail + mot de passe, code e-mail, code d'appairage du portail, Entrer, Reconnecter, Déconnexion).
    Avec COMSPEC Link, on passe par sa DLL et ses fonctions pour garder une seule session.
    Params : [action]
*/
params [["_action", ""]];
_action = toLower _action;
private _bridge = [] call comspec_atak_native_fnc_bridge;
// Connexion par COMSPEC Link (requêtes HTTP bloquantes de la DLL) : relancée dans un spawn pour que extAwait passe
// en Async, sinon le jeu fige pendant chaque tentative. Le redessin final reste en fin de script.
// Steam, Entrer (ConnectC2 → VerifyClientInitSync) et Déconnexion (POST logout) font aussi de l'HTTP synchrone.
// Sans COMSPEC Link, la DLL native (GameAuth) fait les mêmes requêtes synchrones : même spawn, puis extAwait en Async.
if (!canSuspend && {_action in ["password", "otp_ask", "otp_ok", "pair", "steam", "enter", "reconnect", "logout"]}) exitWith {
    // Une seule requête à la fois : un deuxième clic pendant l'appel HTTP renverrait un code OTP ou écraserait la réponse.
    if (missionNamespace getVariable ["COMSPEC_ATAK_AthenaBusy", false]) exitWith {
        uiNamespace setVariable ["COMSPEC_ATAK_AthenaHint", ["Patientez quelques secondes.", true, diag_tickTime]];
        [{ private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_p in ["ATHENA", "NETWORK"]) then { [_p] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        true
    };
    missionNamespace setVariable ["COMSPEC_ATAK_AthenaBusy", true];
    [_action] spawn {
        params ["_a"];
        [_a] call comspec_atak_native_fnc_athenaAction;
        missionNamespace setVariable ["COMSPEC_ATAK_AthenaBusy", false];
    };
    true
};
private _ext = { params ["_cmd", ["_args", []]]; if (_bridge) then { private _r = ["COMSPECExtension", _cmd, _args] call comspec_atak_native_fnc_extAwait; if (_r isEqualType []) then { _r select 0 } else { _r } } else { private _r = ["COMSPECATAKNativeExtension", _cmd, _args] call comspec_atak_native_fnc_extAwait; private _q = if (_r isEqualType []) then { _r param [0, ""] } else { _r }; if !(_q isEqualType "") then { _q = str _q; }; _q } };
private _url = "https://athena-comspec.eu/public"; // portail Athena fixe
private _pack = if (_bridge) then { [] call comspec_overwatch_connect_fnc_packVersion } else { "native" };
private _steam = getPlayerUID player;
private _email = trim (["email"] call comspec_atak_native_fnc_formValue);
private _hint = {
    params ["_text", ["_warn", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_AthenaHint", [_text, _warn, diag_tickTime]];
};
if ((count _steam) >= 8) then { ["SetSteamId", [_steam]] call _ext; };
switch (_action) do {
    case "steam": {
        ["Connexion Steam…"] call _hint;
        if (_bridge) then {
            // Pas fnc_loginSteam : son waitUntil et son callExtension direct figent le jeu. AuthSteam passe par _ext
            // (extAwait en Async dans ce spawn), puis même suite que loginSteam en cas de succès.
            if ((count _steam) < 8) exitWith { ["Steam n'est pas disponible sur cet ordinateur.", true] call _hint; };
            private _purl = if (isNil "comspec_overwatch_connect_fnc_portalUrl") then { _url } else { [] call comspec_overwatch_connect_fnc_portalUrl };
            private _r = ["AuthSteam", [_purl, _steam, _pack]] call _ext;
            if !(_r isEqualType "") then { _r = str _r; };
            if ((_r find "OK|READY") isEqualTo 0 && {!isNil "comspec_overwatch_connect_fnc_applyBootstrap"}) then { [] call comspec_overwatch_connect_fnc_applyBootstrap; };
            if ((_r find "STEAM_NOT_LINKED") >= 0) then { ["Ce Steam n'est pas encore associé à un compte Athena : connectez-vous une fois par e-mail ou liez Steam sur le portail.", true] call _hint; };
        } else { ["AuthSteam", [_url, _steam, _pack]] call _ext; };
    };
    case "password": {
        private _pass = ["password"] call comspec_atak_native_fnc_formValue;
        if ((count _email) < 5 || {_pass isEqualTo ""}) exitWith { ["Indiquez votre e-mail et votre mot de passe.", true] call _hint; };
        ["Authentification…"] call _hint;
        ["AuthPassword", [_url, _email, _pass, _pack, _steam]] call _ext;
    };
    case "otp_ask": {
        if ((count _email) < 5) exitWith { ["Indiquez d'abord votre e-mail.", true] call _hint; };
        private _r = ["RequestOtp", [_url, _email]] call _ext;
        [["Code envoyé par e-mail. Saisissez-le puis Valider le code.", "Envoi du code impossible : vérifiez l'e-mail."] select ((toUpper _r) find "ERR" isEqualTo 0), (toUpper _r) find "ERR" isEqualTo 0] call _hint;
    };
    case "otp_ok": {
        private _code = trim (["otp"] call comspec_atak_native_fnc_formValue);
        if ((count _code) < 4) exitWith { ["Saisissez le code reçu par e-mail.", true] call _hint; };
        ["Vérification du code…"] call _hint;
        ["VerifyOtp", [_url, _email, _code, _pack, _steam]] call _ext;
    };
    case "pair": {
        private _code = toUpper trim (["pair"] call comspec_atak_native_fnc_formValue);
        if ((count _code) < 4) exitWith { ["Collez le code généré sur le portail (page Appairer).", true] call _hint; };
        ["Échange du code…"] call _hint;
        if (_bridge) then {
            [_url, _code, _steam] call comspec_overwatch_connect_fnc_accountLinkSubmit;
            [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
        } else { ["RedeemGameLink", [_url, _code, _steam]] call _ext; };
    };
    case "enter": {
        ["Ouverture du canal poste…"] call _hint;
        ["ConnectC2", []] call _ext;
        if (_bridge) then { [] call comspec_overwatch_connect_fnc_pollAuth; [] call comspec_overwatch_connect_fnc_reopenTransmitChannel; };
    };
    case "reconnect": {
        // Relance complète de la liaison (même chemin que « Reconnecter Athena » du dépannage Échap).
        ["Reconnexion à Athena…"] call _hint;
        if (_bridge) then {
            missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
            missionNamespace setVariable ["COMSPEC_LinkDetail", "Reconnexion manuelle…", false];
            // En ligne (déjà dans le spawn du verrou AthenaBusy) : un deuxième appui pendant la reconnexion est refusé.
            call {
                [] call comspec_overwatch_connect_fnc_connect;
                private _ok = (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked";
                uiNamespace setVariable ["COMSPEC_ATAK_AthenaHint", [["Reconnexion impossible : voir les étapes de la liaison.", "Liaison Athena rétablie."] select _ok, !_ok, diag_tickTime]];
                [] call comspec_overwatch_connect_fnc_pollAuth;
                [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
                [{ private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_p in ["ATHENA", "NETWORK"]) then { [_p] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
            };
        } else {
            ["AuthSteam", [_url, _steam, _pack]] call _ext;
            ["ConnectC2", []] call _ext;
        };
    };
    case "logout": {
        // Pont : Logout d'abord par _ext (Async) ; fnc_logout le rappelle ensuite en direct, mais le jeton est déjà vidé
        // par la DLL, donc sans requête HTTP. fnc_logout remet à zéro l'état Overwatch.
        ["Logout", []] call _ext;
        if (_bridge) then { [] call comspec_overwatch_connect_fnc_logout; };
        ["cancel"] call comspec_atak_native_fnc_athenaLink;
        ["Déconnecté."] call _hint;
    };
};
if (_bridge) then { [] call comspec_overwatch_connect_fnc_pollAuth; };
// Redessin de la page qui a lancé l'action (Athena ou Réseau) seulement si elle est encore ouverte : après les appels
// HTTP (spawn), l'app ouverte entre-temps ne doit pas être recouverte.
[{ private _p = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]; if (_p in ["ATHENA", "NETWORK"]) then { [_p] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
true
