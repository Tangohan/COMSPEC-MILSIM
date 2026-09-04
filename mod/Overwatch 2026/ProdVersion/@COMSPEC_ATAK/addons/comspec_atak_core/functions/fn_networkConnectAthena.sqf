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

    private _baseUrl = profileNamespace getVariable [
        "COMSPEC_ATAK_AthenaUrl",
        "https://athena.ttrd.fr/public"
    ];

    // Product version, not the internal UI/runtime build number.
    // Priority:
    // 1. explicit admin/dev override;
    // 2. historical official COMSPEC Overwatch patch;
    // 3. standalone ATAK core patch.
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

    // Legacy addon is fallback only. It must never override the new standalone ATAK.
    if (_version isEqualTo "") then
    {
        _version = getText (
            configFile
            >> "CfgPatches"
            >> "comspec_overwatch_atak_athena"
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
        getPlayerUID player
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
    private _armaSteam = if (isNull player) then {""} else {getPlayerUID player};

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

    private _result = [
        "RestoreSession",
        [_baseUrl, _version]
    ] call COMSPEC_fnc_extensionCall;

    // -------------------------------------------------------------
    // 4. Steam fallback
    // -------------------------------------------------------------
    if ((_result find "OK|") != 0) then
    {
        private _sessionMissing =
            (_result find "SESSION_EXPIRED") >= 0
            || {(_result find "session_expired") >= 0};

        if (_sessionMissing) then
        {
            [
                "ATHENA",
                "Session absente. Authentification Steam...",
                "WARN"
            ] call COMSPEC_fnc_networkUpdateConnectionUI;

            private _steam = getPlayerUID player;

            if (_steam isEqualTo "") then
            {
                _result = "ERR|STEAM_NOT_LINKED";
            }
            else
            {
                _result = [
                    "AuthSteam",
                    [_baseUrl, _steam, _version]
                ] call COMSPEC_fnc_extensionCall;

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

        if (
            (_result find "STEAM_NOT_LINKED") >= 0
            || {(_result find "steam_not_linked") >= 0}
        ) then
        {
            _msg = "Terminal non enrôlé.";
            _pairEligible = true;
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

        // Steam non lié : ATHENA Web génère le code, ATAK ne fait que le saisir.
        if (_pairEligible) then
        {
            ["if(window.COMSPEC_ATAK_showUnenrolled){window.COMSPEC_ATAK_showUnenrolled(true);}"] call COMSPEC_fnc_webExecJS;
        };
    };

    // -------------------------------------------------------------
    // 6. State/profile
    // -------------------------------------------------------------
    private _authRaw = [
        "GetAuthState",
        []
    ] call COMSPEC_fnc_extensionCall;

    private _auth = [
        _authRaw
    ] call COMSPEC_fnc_parseAuthState;

    private _callsign = _auth getOrDefault [
        "callsign",
        "-"
    ];

    if (_callsign in ["", "-"]) then
    {
        _callsign = profileName;
    };

    private _tenant = _auth getOrDefault [
        "tenant",
        "-"
    ];

    ["callsign", _callsign] call COMSPEC_fnc_setState;
    ["athenaTenant", _tenant] call COMSPEC_fnc_setState;

    ["athenaName", _auth getOrDefault ["name",""]] call COMSPEC_fnc_setState;
    ["athenaFirstName", _auth getOrDefault ["firstName",""]] call COMSPEC_fnc_setState;
    ["athenaLastName", _auth getOrDefault ["lastName",""]] call COMSPEC_fnc_setState;
    ["athenaCallsign", _auth getOrDefault ["callsign",""]] call COMSPEC_fnc_setState;
    ["athenaGrade", _auth getOrDefault ["grade",""]] call COMSPEC_fnc_setState;
    ["athenaUnit", _auth getOrDefault ["unit",""]] call COMSPEC_fnc_setState;
    ["athenaRole", _auth getOrDefault ["role",""]] call COMSPEC_fnc_setState;
    ["athenaFunction", _auth getOrDefault ["function",""]] call COMSPEC_fnc_setState;
    ["athenaAvatar", _auth getOrDefault ["avatar",""]] call COMSPEC_fnc_setState;
    ["athenaAccountId", _auth getOrDefault ["accountId",""]] call COMSPEC_fnc_setState;
    ["athenaEmail", _auth getOrDefault ["email",""]] call COMSPEC_fnc_setState;
    ["athenaDeviceId", _auth getOrDefault ["deviceId",""]] call COMSPEC_fnc_setState;
    ["athenaSessionExpiresAt", _auth getOrDefault ["sessionExpiresAt",""]] call COMSPEC_fnc_setState;
    ["athenaTenantSlug", _auth getOrDefault ["tenantSlug",""]] call COMSPEC_fnc_setState;
    ["athenaExtensionVersion", _auth getOrDefault ["extensionVersion",""]] call COMSPEC_fnc_setState;
    ["athenaDetectedModVersion", _auth getOrDefault ["detectedModVersion",""]] call COMSPEC_fnc_setState;
    ["athenaMinModVersion", _auth getOrDefault ["minModVersion",""]] call COMSPEC_fnc_setState;
    private _reportedSteamLinked = _auth getOrDefault ["steamLinked",false];
    private _armaSteamNow = if (isNull player) then {""} else {getPlayerUID player};

    // The game auth notice may be stale after a restored session.
    // A successful full connection after SetSteamId means client-init accepted
    // this Steam identity; reflect that rather than showing a false negative.
    private _effectiveSteamLinked =
        _reportedSteamLinked
        || {!(_armaSteamNow isEqualTo "")};

    ["athenaSteamLinked", _effectiveSteamLinked] call COMSPEC_fnc_setState;
    ["athenaSteamDetected", !(_armaSteamNow isEqualTo "")] call COMSPEC_fnc_setState;
    ["athenaSteamNotice", _auth getOrDefault ["steamNotice",""]] call COMSPEC_fnc_setState;
    ["athenaAuthState", _auth getOrDefault ["state",""]] call COMSPEC_fnc_setState;

    [
        "ATHENA",
        format ["Connecte : %1", _callsign],
        "OK"
    ] call COMSPEC_fnc_networkUpdateConnectionUI;

    [
        "ATHENA",
        _tenant
    ] call COMSPEC_fnc_networkApplyMode;

    [false] call COMSPEC_fnc_networkShowConnection;

    [
        "if(window.COMSPEC_ATAK_forceDesktop){window.COMSPEC_ATAK_forceDesktop();}"
        + "else{"
        + "var g=document.getElementById('bootGate');if(g){g.hidden=true;}"
        + "var a=document.querySelectorAll('.phone-app');"
        + "for(var i=0;i<a.length;i++){a[i].classList.remove('active');}"
        + "var d=document.getElementById('phoneDesktop');if(d){d.classList.add('active');}"
        + "}"
    ] call COMSPEC_fnc_webExecJS;

    [] call COMSPEC_fnc_webPushState;

    [true] call _finish;
};

true
