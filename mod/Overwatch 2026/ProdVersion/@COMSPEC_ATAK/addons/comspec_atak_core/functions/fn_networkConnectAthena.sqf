if (
    ["networkBusy", false] call COMSPEC_fnc_getState
) exitWith
{
    false
};

["networkBusy", true] call COMSPEC_fnc_setState;

[
    "ATHENA",
    "Initialisation de la liaison...",
    "INFO"
] call COMSPEC_fnc_networkUpdateConnectionUI;

diag_log "[COMSPEC ATAK][ATHENA] début de connexion";

[] spawn
{
    private _finish = {
        params [
            ["_success", false, [true]]
        ];

        ["networkBusy", false] call COMSPEC_fnc_setState;

        diag_log format [
            "[COMSPEC ATAK][ATHENA] séquence terminée success=%1",
            _success
        ];[] call COMSPEC_fnc_webPushState;
    };

    (call COMSPEC_fnc_networkCredentials) params ["_baseUrl", "_savedKey", "_tenantId"];
    if (!(_tenantId isEqualType "")) then { _tenantId = ""; };

    // Product version, not the internal UI/runtime build number.
    private _version = profileNamespace getVariable [
        "COMSPEC_ATAK_ModVersionOverride",
        ""
    ];

    if (_version isEqualTo "") then
    {
        _version = getText (
            configFile
            >> "CfgPatches"
            >> "comspec_atak_core"
            >> "versionStr"
        );
    };

    if (_version isEqualTo "") then
    {
        _version = "0.0.0";
    };

    diag_log format [
        "[COMSPEC ATAK][ATHENA] URL=%1 MOD=%2 STEAM=%3",
        _baseUrl,
        _version,
        [] call COMSPEC_fnc_networkSteamUid
    ];

    // -------------------------------------------------------------
    // 1. DLL probe
    // -------------------------------------------------------------
    [
        "ATHENA",
        "Verification de COMSPECExtension...",
        "INFO"
    ] call COMSPEC_fnc_networkUpdateConnectionUI;

    private _ping = [
        "Ping",
        []
    ] call COMSPEC_fnc_extensionCall;

    if ((_ping find "OK|") != 0) exitWith
    {
        [
            "ATHENA",
            "Extension COMSPEC indisponible. Ouvrez Diagnostic.",
            "ERR"
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        [false] call _finish;
    };

    // -------------------------------------------------------------
    // 2. Init
    // -------------------------------------------------------------
    [
        "ATHENA",
        "Initialisation de l'extension...",
        "INFO"
    ] call COMSPEC_fnc_networkUpdateConnectionUI;

    private _init = [
        "Init",
        [_baseUrl]
    ] call COMSPEC_fnc_extensionCall;

    if ((_init find "OK|") != 0) exitWith
    {
        [
            "ATHENA",
            format ["Initialisation impossible : %1", _init],
            "ERR"
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        [false] call _finish;
    };

    // -------------------------------------------------------------
    // 2b. Prime the extension with the real Steam64 from Arma.
    // A restored ATHENA session can be valid while the ATAK extension
    // still has no _steamUid, which causes /api/atak/client-init to fail
    // with steam_required.
    // -------------------------------------------------------------
    private _armaSteam = [] call COMSPEC_fnc_networkSteamUid;

    if !(_armaSteam isEqualTo "") then
    {
        private _steamPrime = [
            "SetSteamId",
            [_armaSteam]
        ] call COMSPEC_fnc_extensionCall;

        diag_log format [
            "[COMSPEC ATAK][ATHENA][STEAM] UID=%1 PRIME=%2",
            _armaSteam,
            _steamPrime
        ];
    };

    // -------------------------------------------------------------
    // 3. Restore session
    // -------------------------------------------------------------
    [
        "ATHENA",
        "Recherche d'une session Athena...",
        "INFO"
    ] call COMSPEC_fnc_networkUpdateConnectionUI;

    private _result = "";

    if ((count _savedKey) >= 16) then
    {
        [
            "ATHENA",
            "Liaison avec le jeton de la communauté...",
            "INFO"
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        _result = [
            "Connect",
            [_baseUrl, _savedKey, _tenantId, _armaSteam, _version]
        ] call COMSPEC_fnc_extensionCall;
    };

    private _restored = "";
    if ((_result find "OK|") != 0) then
    {
        _restored = [
            "RestoreSession",
            [_baseUrl, _version]
        ] call COMSPEC_fnc_extensionCall;
        _result = _restored;
    };

    // Steam actuel d’abord : une session enregistrée sur ce PC peut encore
    // appartenir à l’ancien compte après un changement d’identifiant Steam.
    if (!(_armaSteam isEqualTo "")) then
    {
        [
            "ATHENA",
            "Authentification Steam...",
            "INFO"
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        private _steamAuth = [
            "AuthSteam",
            [_baseUrl, _armaSteam, _version]
        ] call COMSPEC_fnc_extensionCall;

        if ((_steamAuth find "OK|") == 0) then
        {
            _result = _steamAuth;
        }
        else
        {
            if ((_restored find "OK|") == 0) then
            {
                ["Logout", []] call COMSPEC_fnc_extensionCall;
                [
                    "INFO",
                    "ATHENA",
                    "Ancienne session ignorée : ce Steam n’est pas celui du compte enregistré."
                ] call COMSPEC_fnc_log;
                _result = _steamAuth;
            };
        };
    };

    if ((_result find "OK|") != 0 && {(count _savedKey) >= 16}) then
    {
        _result = [
            "Connect",
            [_baseUrl, _savedKey, _tenantId, _armaSteam, _version]
        ] call COMSPEC_fnc_extensionCall;
    };

    if ((_result find "OK|") != 0 && {!(_armaSteam isEqualTo "")}) then
    {
        _result = [
            "LinkBySteam",
            [_baseUrl, _armaSteam]
        ] call COMSPEC_fnc_extensionCall;
    };

    if ((_result find "OK|") != 0 && {_armaSteam isEqualTo ""}) then
    {
        _result = "ERR|STEAM_NOT_LINKED";
    };

    // -------------------------------------------------------------
    // 5. Auth failure
    // -------------------------------------------------------------
    if ((_result find "OK|") != 0) exitWith
    {
        private _msg = "Connexion Athena impossible.";
        private _pairEligible = false;
        private _openLogin = false;

        if (
            (_result find "STEAM_NOT_LINKED") >= 0
            || {(_result find "steam_not_linked") >= 0}
        ) then
        {
            _msg = "Ce Steam n’est pas associé à un compte Athena. Connectez-vous avec votre e-mail, ou renseignez l’identifiant Steam dans les options du pack.";
            _openLogin = true;
        };

        if (
            (_result find "SESSION_EXPIRED") >= 0
            || {(_result find "session_expired") >= 0}
        ) then
        {
            _msg = "Session Athena expiree.";
        };

        if (
            (_result find "NETWORK_ERROR") >= 0
            || {(_result find "network") >= 0}
        ) then
        {
            _msg = "Serveur Athena inaccessible.";
        };

        if ((_result find "MOD_OUTDATED") >= 0) then
        {
            private _stateRaw = ["GetAuthState",[]] call COMSPEC_fnc_extensionCall;
            private _stateHint = [_stateRaw] call COMSPEC_fnc_parseAuthState;
            private _minHint = _stateHint getOrDefault ["minModVersion",""];
            private _detHint = _stateHint getOrDefault ["detectedModVersion",_version];

            _msg = if (_minHint isEqualTo "") then
            {
                format ["Version du mod non autorisee. Envoyee : %1.", _version]
            }
            else
            {
                format [
                    "Version du mod non autorisee. Envoyee : %1 / minimum Athena : %2.",
                    _detHint,
                    _minHint
                ]
            };

            diag_log format [
                "[COMSPEC ATAK][ATHENA][VERSION] configured=%1 detected=%2 minimum=%3 raw=%4",
                _version,
                _detHint,
                _minHint,
                _result
            ];
        };

        [
            "ATHENA",
            _msg + (if (_pairEligible) then {" Saisissez le code affiché, ou un code de secours."} else {" Voir le statut."}),
            if (_pairEligible) then {"WARN"} else {"ERR"}
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        [false] call _finish;

        if (_openLogin) then
        {
            ["if(window.COMSPEC_ATAK_openAccountLogin){window.COMSPEC_ATAK_openAccountLogin('Ce Steam n’est pas associé à un compte. Connectez-vous avec votre e-mail.');}"] call COMSPEC_fnc_webExecJS;
        };
        if (_pairEligible) then
        {
            ["if(window.COMSPEC_ATAK_showUnenrolled){window.COMSPEC_ATAK_showUnenrolled(true);}"] call COMSPEC_fnc_webExecJS;
        };
    };

    if ((count _savedKey) >= 16) then
    {
        profileNamespace setVariable ["COMSPEC_ATAK_ApiKey", _savedKey];
        profileNamespace setVariable ["COMSPEC_ATAK_AthenaUrl", _baseUrl];
        if (!(_tenantId isEqualTo "")) then
        {
            profileNamespace setVariable ["COMSPEC_ATAK_TenantId", _tenantId];
        };
        saveProfileNamespace;
    };

    ["desktop"] call COMSPEC_fnc_networkApplyGameAuth;

    [true] call _finish;
};

true
