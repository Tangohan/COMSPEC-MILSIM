/*
 * Connexion Athena par e-mail et mot de passe depuis le téléphone.
 * Remonte aussi l’identifiant Steam (saisie options ou détection en jeu).
 */
if (["networkBusy", false] call COMSPEC_fnc_getState) exitWith { false };

private _email = missionNamespace getVariable ["COMSPEC_ATAK_LoginEmail", ""];
private _password = missionNamespace getVariable ["COMSPEC_ATAK_LoginSecret", ""];
if (!(_email isEqualType "")) then { _email = ""; };
if (!(_password isEqualType "")) then { _password = ""; };
_email = trim _email;

if (_email isEqualTo "" || {_password isEqualTo ""}) exitWith
{
    ["if(window.COMSPEC_ATAK_loginError){window.COMSPEC_ATAK_loginError('Saisissez votre adresse e-mail et votre mot de passe.');}"] call COMSPEC_fnc_webExecJS;
    false
};

["networkBusy", true] call COMSPEC_fnc_setState;
["ATHENA", "Connexion au compte…", "INFO"] call COMSPEC_fnc_networkUpdateConnectionUI;

[] spawn
{
    private _finish = {
        params [["_ok", false, [true]]];
        ["networkBusy", false] call COMSPEC_fnc_setState;
        missionNamespace setVariable ["COMSPEC_ATAK_LoginSecret", "", false];
        [] call COMSPEC_fnc_webPushState;
        _ok
    };

    (call COMSPEC_fnc_networkCredentials) params ["_baseUrl", "_savedKey", "_tenantId"];
    private _version = getText (configFile >> "CfgPatches" >> "comspec_atak_core" >> "versionStr");
    if (_version isEqualTo "") then { _version = "0.0.0"; };

    private _steam = [] call COMSPEC_fnc_networkSteamUid;
    if (_steam isNotEqualTo "") then
    {
        ["SetSteamId", [_steam]] call COMSPEC_fnc_extensionCall;
    };

    private _init = ["Init", [_baseUrl]] call COMSPEC_fnc_extensionCall;
    if ((_init find "OK|") != 0) exitWith
    {
        ["ATHENA", "Le poste n’a pas pu être joint.", "ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        ["if(window.COMSPEC_ATAK_loginError){window.COMSPEC_ATAK_loginError('Le poste n’a pas pu être joint. Vérifiez l’adresse dans les options du pack.');}"] call COMSPEC_fnc_webExecJS;
        [false] call _finish;
    };

    private _email = missionNamespace getVariable ["COMSPEC_ATAK_LoginEmail", ""];
    private _password = missionNamespace getVariable ["COMSPEC_ATAK_LoginSecret", ""];
    private _raw = ["AuthPassword", [_baseUrl, _email, _password, _version]] call COMSPEC_fnc_extensionCall;

    if ((_raw find "OK|") != 0) exitWith
    {
        private _msg = "Connexion impossible.";
        switch (true) do
        {
            case ((_raw find "INVALID_CREDENTIALS") >= 0): { _msg = "Adresse e-mail ou mot de passe incorrect."; };
            case ((_raw find "ACCOUNT_DISABLED") >= 0): { _msg = "Ce compte n’est pas actif. Contactez l’encadrement de votre communauté."; };
            case ((_raw find "TENANT_DISABLED") >= 0);
            case ((_raw find "NO_TENANT") >= 0): { _msg = "Aucune communauté n’est associée à ce compte."; };
            case ((_raw find "SESSION_EXPIRED") >= 0): { _msg = "La session a expiré. Réessayez."; };
            case ((_raw find "NETWORK") >= 0);
            case ((_raw find "timeout") >= 0): { _msg = "Le poste n’a pas répondu. Réessayez dans un instant."; };
            case ((_raw find "MOD_OUTDATED") >= 0): { _msg = "Ce pack n’est plus accepté par le poste. Mettez-le à jour."; };
            default { _msg = "Connexion refusée. Vérifiez vos identifiants, puis réessayez."; };
        };
        ["ATHENA", _msg, "ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format [
            "if(window.COMSPEC_ATAK_loginError){window.COMSPEC_ATAK_loginError('%1');}",
            [_msg] call COMSPEC_fnc_webJsEscape
        ];
        [_js] call COMSPEC_fnc_webExecJS;
        [false] call _finish;
    };

    if ((count _savedKey) >= 16) then
    {
        profileNamespace setVariable ["COMSPEC_ATAK_ApiKey", _savedKey];
        profileNamespace setVariable ["COMSPEC_ATAK_AthenaUrl", _baseUrl];
        if (_tenantId isNotEqualTo "") then
        {
            profileNamespace setVariable ["COMSPEC_ATAK_TenantId", _tenantId];
        };
        saveProfileNamespace;
    };

    ["account"] call COMSPEC_fnc_networkApplyGameAuth;
    ["if(window.COMSPEC_ATAK_loginOk){window.COMSPEC_ATAK_loginOk();}"] call COMSPEC_fnc_webExecJS;
    [true] call _finish;
};

true
