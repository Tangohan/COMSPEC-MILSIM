/*
    Actions connexion / appairage depuis le panneau Athena ATAK (Rsc natif).
    Params: ["password"|"otp_ask"|"otp_ok"|"steam"|"pair"|"enter"|"logout"]
*/
params [["_action", "", [""]]];
_action = toLower _action;
if (_action isEqualTo "") exitWith {};

private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (isNull _group) exitWith {};

private _hint = [_group, 9791] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _emailCtrl = [_group, 9792] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _passCtrl = [_group, 9793] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _otpCtrl = [_group, 9794] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _btnLogin = [_group, 9795] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _btnOtpAsk = [_group, 9796] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _btnOtpOk = [_group, 9797] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;
private _pairCtrl = [_group, 9799] call comspec_overwatch_atak_athena_fnc_athena_pageCtrl;

private _setHint = {
    params ["_text", ["_warn", false]];
    if (isNull _hint) exitWith {};
    private _col = if (_warn) then { "#e8b84a" } else { "#7aa89a" };
    _hint ctrlSetStructuredText parseText format ["<t color='%1'>%2</t>", _col, _text];
};

private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _pack = [] call comspec_overwatch_connect_fnc_packVersion;

switch (_action) do {
    case "password": {
        private _email = if (!isNull _emailCtrl) then { trim (ctrlText _emailCtrl) } else { "" };
        private _pass = if (!isNull _passCtrl) then { ctrlText _passCtrl } else { "" };
        if ((count _email) < 5 || {(count _pass) < 1}) exitWith {
            ["Indiquez votre adresse e-mail et votre mot de passe.", true] call _setHint;
        };
        ["Authentification en cours…"] call _setHint;
        ["COMSPECExtension" callExtension ["AuthPassword", [_url, _email, _pass, _pack]]] call comspec_overwatch_connect_fnc_extResult;
        [] call comspec_overwatch_connect_fnc_pollAuth;
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
    case "otp_ask": {
        private _email = if (!isNull _emailCtrl) then { trim (ctrlText _emailCtrl) } else { "" };
        if ((count _email) < 5) exitWith {
            ["Indiquez d’abord votre adresse e-mail.", true] call _setHint;
        };
        ["COMSPECExtension" callExtension ["RequestOtp", [_url, _email]]] call comspec_overwatch_connect_fnc_extResult;
        if (!isNull _passCtrl) then { _passCtrl ctrlShow false; };
        if (!isNull _btnLogin) then { _btnLogin ctrlShow false; };
        if (!isNull _otpCtrl) then { _otpCtrl ctrlShow true; };
        if (!isNull _btnOtpOk) then { _btnOtpOk ctrlShow true; };
        ["Un code vient d’être envoyé. Saisissez-le ci-dessous."] call _setHint;
    };
    case "otp_ok": {
        private _email = if (!isNull _emailCtrl) then { trim (ctrlText _emailCtrl) } else { "" };
        private _code = if (!isNull _otpCtrl) then { trim (ctrlText _otpCtrl) } else { "" };
        if ((count _code) < 4) exitWith {
            ["Saisissez le code reçu par e-mail.", true] call _setHint;
        };
        ["COMSPECExtension" callExtension ["VerifyOtp", [_url, _email, _code, _pack]]] call comspec_overwatch_connect_fnc_extResult;
        [] call comspec_overwatch_connect_fnc_pollAuth;
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
    case "steam": {
        ["Authentification Steam…"] call _setHint;
        [false] call comspec_overwatch_connect_fnc_loginSteam;
        private _state = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
        if (_state isEqualTo "READY") then {
            ["Compte prêt. Appuyez sur Entrer."] call _setHint;
        } else {
            private _err = missionNamespace getVariable ["comspec_overwatch_auth_error", ""];
            if (_err isEqualTo "STEAM_NOT_LINKED") then {
                ["Ce Steam n’est pas associé à un compte. Connectez-vous une fois avec l’e-mail, ou utilisez un code Appairer.", true] call _setHint;
            } else {
                ["Connexion Steam en cours ou refusée. Réessayez ou utilisez l’e-mail / un code.", true] call _setHint;
            };
        };
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
    case "pair": {
        private _code = if (!isNull _pairCtrl) then { toUpper (trim (ctrlText _pairCtrl)) } else { "" };
        if ((count _code) < 4) exitWith {
            ["Collez le code généré sur le portail (Appairer).", true] call _setHint;
        };
        ["Échange du code en cours…"] call _setHint;
        private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
        [_url, _code, _steam] call comspec_overwatch_connect_fnc_accountLinkSubmit;
        [] call comspec_overwatch_connect_fnc_pollAuth;
        private _opened = false;
        if (!isNil "comspec_overwatch_connect_fnc_reopenTransmitChannel") then {
            _opened = [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
        };
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
        if (_opened || {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) then {
            ["Liaison réussie — canal poste ouvert. Votre position doit apparaître au poste."] call _setHint;
        } else {
            if ((missionNamespace getVariable ["COMSPEC_LinkState", ""]) isEqualTo "linked") then {
                ["Code accepté, mais le canal poste reste fermé. Appuyez sur Rouvrir canal.", true] call _setHint;
            } else {
                ["Liaison incomplète. Vérifiez le code ou reconnectez-vous.", true] call _setHint;
            };
        };
    };
    case "enter": {
        ["Ouverture du canal poste…"] call _setHint;
        private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
        if ((count _steam) >= 8) then {
            ["COMSPECExtension" callExtension ["SetSteamId", [_steam]]] call comspec_overwatch_connect_fnc_extResult;
        };
        ["COMSPECExtension" callExtension ["ConnectC2", []]] call comspec_overwatch_connect_fnc_extResult;
        private _opened = false;
        if (!isNil "comspec_overwatch_connect_fnc_reopenTransmitChannel") then {
            _opened = [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
        } else {
            [] call comspec_overwatch_connect_fnc_applyBootstrap;
            [] call comspec_overwatch_connect_fnc_pollAuth;
            if ([] call comspec_overwatch_connect_fnc_isReady) then {
                [] call comspec_overwatch_connect_fnc_startSyncLoops;
                _opened = true;
            };
        };
        if (_opened) then {
            ["Canal poste ouvert — position et fiche en cours d’envoi."] call _setHint;
        } else {
            ["Canal encore refusé. Déconnectez-vous puis utilisez un nouveau code Appairer.", true] call _setHint;
        };
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
    case "logout": {
        [] call comspec_overwatch_connect_fnc_logout;
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
        ["Session fermée. Vous pouvez vous reconnecter ou coller un nouveau code Appairer."] call _setHint;
    };
};
