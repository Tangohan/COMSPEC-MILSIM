params [["_code","",[""]]];

_code = toUpper (trim _code);
_code = (_code splitString " -_.") joinString "";
if ((count _code) < 4) exitWith
{
    ["ATHENA","Saisissez le code généré dans ATHENA (Lier le jeu).","WARN"] call COMSPEC_fnc_networkUpdateConnectionUI;
    false
};

if (["networkBusy", false] call COMSPEC_fnc_getState) exitWith {false};
["networkBusy", true] call COMSPEC_fnc_setState;

[_code] spawn
{
    params ["_code"];
    private _baseUrl = profileNamespace getVariable ["COMSPEC_ATAK_AthenaUrl","https://athena.ttrd.fr/public"];
    private _steam = [] call COMSPEC_fnc_networkSteamUid;
    private _version = getText (configFile >> "CfgPatches" >> "comspec_atak_core" >> "versionStr");

    ["ATHENA","Validation du code ATHENA...","INFO"] call COMSPEC_fnc_networkUpdateConnectionUI;
    ["INFO","AUTH","Tentative d'appairage avec un code ATHENA.","code=masked"] call COMSPEC_fnc_log;

    private _init = ["Init",[_baseUrl]] call COMSPEC_fnc_extensionCall;
    if ((_init find "OK|") != 0) then
    {
        ["networkBusy", false] call COMSPEC_fnc_setState;
        private _msg = format ["Extension non initialisée : %1",_init];
        ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format ["if(window.COMSPEC_ATAK_pairError){window.COMSPEC_ATAK_pairError('%1');}",[_msg] call COMSPEC_fnc_webJsEscape];
        [_js] call COMSPEC_fnc_webExecJS;
    }
    else
    {
        private _raw = ["RedeemGameLink",[_baseUrl,_code,_steam]] call COMSPEC_fnc_extensionCall;
        if ((_raw find "OK|") != 0) then
        {
            _raw = ["RedeemPairingCode",[_baseUrl,_code,_steam,_version]] call COMSPEC_fnc_extensionCall;
        };
        ["networkBusy", false] call COMSPEC_fnc_setState;

        if ((_raw find "OK|") == 0) exitWith
        {
            ["ATHENA","Terminal associé. Chargement du profil ATHENA...","OK"] call COMSPEC_fnc_networkUpdateConnectionUI;
            ["if(window.COMSPEC_ATAK_pairApproved){window.COMSPEC_ATAK_pairApproved(false);}"] call COMSPEC_fnc_webExecJS;
            uiSleep 0.15;
            [] call COMSPEC_fnc_networkConnectAthena;
        };

        private _msg = switch (true) do
        {
            case ((_raw find "ERR|expired") == 0);
            case ((_raw find "expired") >= 0): {"Code expiré. Générez un nouveau code dans ATHENA (Lier le jeu)."};
            case ((_raw find "ERR|used") == 0);
            case ((_raw find "already_used") >= 0): {"Ce code a déjà été utilisé. Générez-en un nouveau dans ATHENA."};
            case ((_raw find "ERR|invalid_code") == 0);
            case ((_raw find "invalid") >= 0): {"Code invalide. Générez-le sur le poste : Carte ATAK → Compte → Lier le jeu."};
            case (_raw isEqualTo ""): {"Extension COMSPEC obsolète : RedeemPairingCode indisponible."};
            default {format ["Appairage refusé : %1",_raw]};
        };
        ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format ["if(window.COMSPEC_ATAK_pairError){window.COMSPEC_ATAK_pairError('%1');}",[_msg] call COMSPEC_fnc_webJsEscape];
        [_js] call COMSPEC_fnc_webExecJS;
    };
};
true
