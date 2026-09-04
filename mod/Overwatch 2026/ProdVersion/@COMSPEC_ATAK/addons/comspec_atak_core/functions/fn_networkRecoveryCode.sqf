params [["_code","",[""]]];
_code = toUpper trim _code;
_code = (_code splitString " -_.") joinString "";
private _len = count _code;
if (_len < 6 || {_len > 12}) exitWith
{
    ["ATHENA","Saisissez le code généré sur le poste (Lier le jeu), 6 caractères ou plus.","ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
    ["if(window.COMSPEC_ATAK_recoveryError){window.COMSPEC_ATAK_recoveryError('Saisissez le code généré sur le poste (Lier le jeu).');}"] call COMSPEC_fnc_webExecJS;
    false
};

[_code] spawn
{
    params ["_code"];
    private _baseUrl = profileNamespace getVariable ["COMSPEC_ATAK_AthenaUrl","https://athena.ttrd.fr/public"];
    private _steam = [] call COMSPEC_fnc_networkSteamUid;
    private _version = getText (configFile >> "CfgPatches" >> "comspec_atak_core" >> "versionStr");
    ["ATHENA","Préparation de la récupération...","INFO"] call COMSPEC_fnc_networkUpdateConnectionUI;
    private _init = ["Init",[_baseUrl]] call COMSPEC_fnc_extensionCall;
    if ((_init find "OK|") != 0) exitWith
    {
        private _msg = format ["Extension non initialisée : %1",_init];
        ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format ["if(window.COMSPEC_ATAK_recoveryError){window.COMSPEC_ATAK_recoveryError('%1');}",[_msg] call COMSPEC_fnc_webJsEscape];
        [_js] call COMSPEC_fnc_webExecJS;
    };

    ["ATHENA","Validation du code...","INFO"] call COMSPEC_fnc_networkUpdateConnectionUI;

    // Code « Lier le jeu » du poste (6 caractères) — chemin déjà en production.
    private _raw = ["RedeemGameLink",[_baseUrl,_code,_steam]] call COMSPEC_fnc_extensionCall;
    if ((_raw find "OK|") != 0) then
    {
        _raw = ["RedeemRecoveryCode",[_baseUrl,_code,_steam,_version]] call COMSPEC_fnc_extensionCall;
    };

    if ((_raw find "OK|") == 0) then
    {
        ["authState","RECOVERY_APPROVED"] call COMSPEC_fnc_setState;
        ["ATHENA","Code accepté. Finalisation de la session ATHENA...","OK"] call COMSPEC_fnc_networkUpdateConnectionUI;
        ["if(window.COMSPEC_ATAK_recoveryApproved){window.COMSPEC_ATAK_recoveryApproved(false);}"] call COMSPEC_fnc_webExecJS;
        uiSleep 0.15;
        [] call COMSPEC_fnc_networkConnectAthena;
    }
    else
    {
        private _msg = switch (true) do
        {
            case ((_raw find "used") >= 0);
            case ((_raw find "already_used") >= 0): {"Code déjà utilisé. Générez-en un nouveau sur le poste (Lier le jeu)."};
            case ((_raw find "expired") >= 0): {"Code expiré. Générez-en un nouveau sur le poste."};
            case ((_raw find "invalid") >= 0): {"Code invalide. Générez-le sur le poste : Carte ATAK → Compte → Lier le jeu."};
            case (_raw isEqualTo ""): {"Le poste n'a pas répondu. Rechargez le portail, puis réessayez."};
            case ((_raw find "http_500") >= 0);
            case ((_raw find "http_502") >= 0);
            case ((_raw find "http_503") >= 0): {"Le poste n'a pas pu valider le code. Rechargez le portail, puis réessayez."};
            case ((_raw find "timeout") >= 0): {"Le poste n'a pas répondu à temps."};
            case ((_raw find "network") >= 0): {"Pas de liaison avec le poste Athena."};
            default {"Impossible de valider ce code. Générez-le sur le poste (Lier le jeu)."};
        };
        ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format ["if(window.COMSPEC_ATAK_recoveryError){window.COMSPEC_ATAK_recoveryError('%1');}",[_msg] call COMSPEC_fnc_webJsEscape];
        [_js] call COMSPEC_fnc_webExecJS;
    };
};
true
