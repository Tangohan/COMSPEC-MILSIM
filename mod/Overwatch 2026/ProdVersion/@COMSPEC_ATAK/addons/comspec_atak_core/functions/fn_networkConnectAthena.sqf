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

    private _posteUnavailable = {
        params [["_raw", "", [""]]];
        (_raw find "http_503") >= 0
        || {(_raw find "maintenance") >= 0}
        || {(_raw find "NETWORK_ERROR") >= 0}
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
    private _skipCascade = false;

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

        if ((_result find "OK|") != 0 && {[_result] call _posteUnavailable}) then
        {
            _skipCascade = true;
        };
    };

    private _restored = "";
    if (!_skipCascade && {(_result find "OK|") != 0}) then
    {
        _restored = [
            "RestoreSession",
            [_baseUrl, _version]
        ] call COMSPEC_fnc_extensionCall;
        _result = _restored;
        if ((_result find "OK|") != 0 && {[_result] call _posteUnavailable}) then
        {
            _skipCascade = true;
        };
    };

    // Steam actuel d’abord : une session enregistrée sur ce PC peut encore
    // appartenir à l’ancien compte après un changement d’identifiant Steam.
    if (!_skipCascade && {!(_armaSteam isEqualTo "")}) then
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
            if ([_steamAuth] call _posteUnavailable) then
            {
                _skipCascade = true;
                _result = _steamAuth;
            };
        };
    };

    if (!_skipCascade && {(_result find "OK|") != 0} && {(count _savedKey) >= 16}) then
    {
        _result = [
            "Connect",
            [_baseUrl, _savedKey, _tenantId, _armaSteam, _version]
        ] call COMSPEC_fnc_extensionCall;
        if ((_result find "OK|") != 0 && {[_result] call _posteUnavailable}) then
        {
            _skipCascade = true;
        };
    };

    if (!_skipCascade && {(_result find "OK|") != 0} && {!(_armaSteam isEqualTo "")}) then
    {
        _result = [
            "LinkBySteam",
            [_baseUrl, _armaSteam]
        ] call COMSPEC_fnc_extensionCall;
    };

    if ((_result find "OK|") != 0 && {_armaSteam isEqualTo ""}) then
    {
        if (_result isEqualTo "" || {[_result] call _posteUnavailable}) then
        {
            if (_result isEqualTo "") then { _result = "ERR|STEAM_NOT_LINKED"; };
        }
        else
        {
            if ((_result find "http_") < 0 && {(_result find "SESSION_EXPIRED") < 0}) then
            {
                _result = "ERR|STEAM_NOT_LINKED";
            };
        };
    };

    // -------------------------------------------------------------
    // 5. Auth failure
    // -------------------------------------------------------------
    if ((_result find "OK|") != 0) exitWith
    {
        private _msg = "Connexion Athena impossible.";
        private _pairEligible = false;
        private _openLogin = false;

        switch (true) do
        {
            case (
                (_result find "STEAM_NOT_LINKED") >= 0
                || {(_result find "steam_not_linked") >= 0}
            ):
            {
                _msg = "Ce Steam n’est pas associé à un compte Athena. Connectez-vous avec votre e-mail, ou renseignez l’identifiant Steam dans les options du pack.";
                _openLogin = true;
            };
            case (
                (_result find "SESSION_EXPIRED") >= 0
                || {(_result find "session_expired") >= 0}
            ):
            {
                _msg = "La session du poste a expiré. Connectez-vous avec votre e-mail.";
                _openLogin = true;
            };
            case (
                (_result find "http_503") >= 0
                || {(_result find "maintenance") >= 0}
            ):
            {
                _msg = "Le poste est momentanément indisponible. Réessayez, ou connectez-vous avec votre e-mail.";
                _openLogin = true;
            };
            case ((_result find "NETWORK_ERROR") >= 0):
            {
                _msg = "Le poste ne répond pas. Vérifiez la liaison, ou connectez-vous avec votre e-mail.";
                _openLogin = true;
            };
            case ((_result find "MOD_OUTDATED") >= 0):
            {
                private _stateRaw = ["GetAuthState",[]] call COMSPEC_fnc_extensionCall;
                private _stateHint = [_stateRaw] call COMSPEC_fnc_parseAuthState;
                private _minHint = _stateHint getOrDefault ["minModVersion",""];
                private _detHint = _stateHint getOrDefault ["detectedModVersion",_version];

                _msg = if (_minHint isEqualTo "") then
                {
                    format ["Version du pack non autorisée. Envoyée : %1.", _version]
                }
                else
                {
                    format [
                        "Version du pack non autorisée. Envoyée : %1 / minimum du poste : %2.",
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
            default {};
        };

        [
            "ATHENA",
            _msg + (if (_pairEligible) then {" Saisissez le code affiché, ou un code de secours."} else {""}),
            if (_pairEligible) then {"WARN"} else {"ERR"}
        ] call COMSPEC_fnc_networkUpdateConnectionUI;

        ["if(window.COMSPEC_ATAK_holdGate){window.COMSPEC_ATAK_holdGate(true);}if(window.COMSPEC_ATAK_setGate){window.COMSPEC_ATAK_setGate(true);}"] call COMSPEC_fnc_webExecJS;

        [false] call _finish;

        if (_openLogin) then
        {
            private _js = format [
                "if(window.COMSPEC_ATAK_openAccountLogin){window.COMSPEC_ATAK_openAccountLogin('%1');}",
                [_msg] call COMSPEC_fnc_webJsEscape
            ];
            [_js] call COMSPEC_fnc_webExecJS;
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
