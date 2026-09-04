params [["_force",false,[true]]];

if ((["networkBusy", false] call COMSPEC_fnc_getState) && {!_force}) exitWith {false};
["networkBusy", true] call COMSPEC_fnc_setState;

[] spawn
{
    private _baseUrl = profileNamespace getVariable ["COMSPEC_ATAK_AthenaUrl","https://athena.ttrd.fr/public"];
    private _steam = if (isNull player) then {""} else {getPlayerUID player};
    private _version = getText (configFile >> "CfgPatches" >> "comspec_atak_core" >> "versionStr");

    ["ATHENA","Préparation de l'appairage...","INFO"] call COMSPEC_fnc_networkUpdateConnectionUI;

    // Refresh extension capabilities before entering the device-code flow.
    // Contract remains: game generates code -> web confirms -> PairStatus polls.
    if (!isNil "COMSPEC_fnc_extensionCapabilities") then
    {
        private _caps = [] call COMSPEC_fnc_extensionCapabilities;
        if (
            !(_caps getOrDefault ["available",false])
            || {!(_caps getOrDefault ["pairStart",false])}
            || {!(_caps getOrDefault ["pairStatus",false])}
        ) exitWith
        {
            ["networkBusy", false] call COMSPEC_fnc_setState;
            private _msg = "La DLL COMSPEC chargée ne prend pas en charge l'appairage ATAK actuel.";
            ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
            private _js = format [
                "if(window.COMSPEC_ATAK_pairError){window.COMSPEC_ATAK_pairError('%1');}",
                [_msg] call COMSPEC_fnc_webJsEscape
            ];
            [_js] call COMSPEC_fnc_webExecJS;
        };
    };


    // Toujours initialiser la DLL avant PairStart.
    // Certaines versions historiques de COMSPECExtension renvoient ERR|not_connected
    // tant que Init n'a pas mémorisé l'URL ATHENA.
    private _init = ["Init",[_baseUrl]] call COMSPEC_fnc_extensionCall;
    if ((_init find "OK|") != 0) exitWith
    {
        ["networkBusy", false] call COMSPEC_fnc_setState;
        private _msg = format ["Extension non initialisée : %1",_init];
        ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format ["if(window.COMSPEC_ATAK_pairError){window.COMSPEC_ATAK_pairError('%1');}",[_msg] call COMSPEC_fnc_webJsEscape];
        [_js] call COMSPEC_fnc_webExecJS;
    };

    ["ATHENA","Génération du code terminal...","INFO"] call COMSPEC_fnc_networkUpdateConnectionUI;
    private _raw = ["PairStart",[_baseUrl,_steam,_version]] call COMSPEC_fnc_extensionCall;
    ["networkBusy", false] call COMSPEC_fnc_setState;

    if ((_raw find "OK|") != 0) exitWith
    {
        private _msg = switch (true) do
        {
            case (_raw isEqualTo ""): {"Le poste n'a pas répondu. Rechargez le portail, puis réessayez."};
            case ((_raw find "http_404") >= 0): {"Le poste Athena ne prend pas encore en charge l'appairage par code."};
            case ((_raw find "http_400") >= 0): {"Le terminal n'a pas pu demander un code. Réessayez."};
            case ((_raw find "http_500") >= 0);
            case ((_raw find "http_502") >= 0);
            case ((_raw find "http_503") >= 0): {"Le poste n'a pas pu créer le code. Rechargez le portail, puis réessayez."};
            case ((_raw find "timeout") >= 0): {"Le poste n'a pas répondu à temps."};
            case ((_raw find "network") >= 0): {"Pas de liaison avec le poste Athena."};
            default {"L'appairage n'est pas disponible pour le moment."};
        };
        ["ATHENA",_msg,"ERR"] call COMSPEC_fnc_networkUpdateConnectionUI;
        private _js = format ["if(window.COMSPEC_ATAK_pairError){window.COMSPEC_ATAK_pairError('%1');}",[_msg] call COMSPEC_fnc_webJsEscape];
        [_js] call COMSPEC_fnc_webExecJS;
    };

    private _parts = (_raw select [3]) splitString "|";
    private _code = _parts param [0,"---- ----"];
    private _ttl = parseNumber (_parts param [1,"600"]);
    private _verify = _parts param [2,"Compte > Sécurité > Terminaux ATAK"];
    private _pollSeconds = (parseNumber (_parts param [3,"2"])) max 1 min 15;
    missionNamespace setVariable ["COMSPEC_ATAK_PairPollSeconds",_pollSeconds];

    private _js = format [
        "if(window.COMSPEC_ATAK_pairStarted){window.COMSPEC_ATAK_pairStarted('%1',%2,'%3');}",
        [_code] call COMSPEC_fnc_webJsEscape,
        round _ttl,
        [_verify] call COMSPEC_fnc_webJsEscape
    ];
    [_js] call COMSPEC_fnc_webExecJS;
    ["ATHENA",format ["Code d'appairage : %1",_code],"WARN"] call COMSPEC_fnc_networkUpdateConnectionUI;
    ["INFO","AUTH",format ["Appairage device-code demarre : %1",_code],format ["ttl=%1s poll=%2s",round _ttl,_pollSeconds]] call COMSPEC_fnc_log;

    private _deadline = diag_tickTime + ((_ttl max 30) min 900);
    while {diag_tickTime < _deadline} do
    {
        uiSleep _pollSeconds;
        private _status = ["PairStatus",[]] call COMSPEC_fnc_extensionCall;
        if ((_status find "OK|approved") == 0) exitWith
        {
            ["ATHENA","Terminal approuvé. Finalisation de la session...","OK"] call COMSPEC_fnc_networkUpdateConnectionUI;
            ["authState","PAIR_APPROVED"] call COMSPEC_fnc_setState;
            ["if(window.COMSPEC_ATAK_pairApproved){window.COMSPEC_ATAK_pairApproved(false);}"] call COMSPEC_fnc_webExecJS;

            // PairStatus stores the server-issued session in the extension.
            // Re-run the canonical ATHENA connection path so RestoreSession,
            // client-init, profile/tenant/callsign and persistence all happen.
            uiSleep 0.15;
            [] call COMSPEC_fnc_networkConnectAthena;
        };
        if ((_status find "ERR|expired") == 0) exitWith
        {
            ["ATHENA","Code d'appairage expiré.","WARN"] call COMSPEC_fnc_networkUpdateConnectionUI;
            ["if(window.COMSPEC_ATAK_pairExpired){window.COMSPEC_ATAK_pairExpired();}"] call COMSPEC_fnc_webExecJS;
        };
    };
};
true
