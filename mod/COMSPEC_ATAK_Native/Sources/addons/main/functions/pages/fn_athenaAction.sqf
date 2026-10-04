/*
    Connexion Athena depuis le terminal : mêmes commandes que le panneau Athena d'Overwatch
    (Steam, e-mail + mot de passe, code e-mail, code d'appairage du portail, Entrer, Déconnexion).
    Avec COMSPEC Link, on passe par sa DLL et ses fonctions pour garder une seule session.
    Params : [action]
*/
params [["_action", ""]];
_action = toLower _action;
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _ext = { params ["_cmd", ["_args", []]]; if (_bridge) then { private _r = "COMSPECExtension" callExtension [_cmd, _args]; if (_r isEqualType []) then { _r select 0 } else { _r } } else { [_cmd, _args] call comspec_atak_native_fnc_extensionCall } };
private _url = if (_bridge) then { [] call comspec_overwatch_connect_fnc_portalUrl } else { profileNamespace getVariable ["COMSPEC_ATAK_Native_AthenaUrl", "https://athena.ttrd.fr/public"] };
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
        if (_bridge) then { [false] call comspec_overwatch_connect_fnc_loginSteam; } else { ["AuthSteam", [_url, _steam, _pack]] call _ext; };
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
    case "logout": {
        if (_bridge) then { [] call comspec_overwatch_connect_fnc_logout; } else { ["Logout", []] call _ext; };
        ["Déconnecté."] call _hint;
    };
};
if (_bridge) then { [] call comspec_overwatch_connect_fnc_pollAuth; };
[{ ["ATHENA"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
true
