if (!hasInterface) exitWith {};

// Warmup extension (charge la DLL)
"COMSPECExtension" callExtension "Warmup";

// Callbacks async extension → SQF (inspiré cTab IRL)
if (isNil "COMSPEC_ExtensionCallbackEH") then {
    COMSPEC_ExtensionCallbackEH = addMissionEventHandler ["ExtensionCallback", {
        _this call comspec_overwatch_connect_fnc_extensionCallback;
    }];
};

["CBA_settingsInitialized", {
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

    [] call comspec_overwatch_connect_fnc_initACE;

    // Handshake Athena (inspiré Remastered) puis sync lourde — hors thread principal
    0 spawn {
        [] call comspec_overwatch_connect_fnc_waitAthenaReady;
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [] call comspec_overwatch_connect_fnc_startSyncLoops;
    };

    // Alerte Windows (MessageBox) si compte Athena non lié — une fois / session
    0 spawn {
        // Laisser le monde et l’UI se stabiliser avant le MessageBox bloquant
        uiSleep 2.5;
        waitUntil {
            missionNamespace getVariable ["COMSPEC_AthenaReady", false]
            || {diag_tickTime > (missionNamespace getVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime] + 120)}
        };
        [] call comspec_overwatch_connect_fnc_showAthenaLinkHelp;
    };
    missionNamespace setVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime, false];

    // Indicatif : profil local puis, si liaison Athena, alignement depuis le compte
    private _cs = trim (missionNamespace getVariable ["COMSPEC_Callsign", ""]);
    if (_cs isEqualTo "") then {
        _cs = trim (profileNamespace getVariable ["COMSPEC_Callsign", ""]);
    };
    if (!(_cs isEqualTo "")) then {
        [_cs, false, "profile"] call comspec_overwatch_connect_fnc_setCallsign;
    };
    0 spawn {
        uiSleep 2;
        waitUntil { missionNamespace getVariable ["COMSPEC_AthenaReady", false] || {diag_tickTime > 90} };
        [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
    };

    // Alerte immédiate dès le passage KO (ACE) — le PFH position couvre aussi FC=0
    if (isNil "COMSPEC_aceUnconsciousEH") then {
        COMSPEC_aceUnconsciousEH = ["ace_unconscious", {
            params ["_unit", "_isUnconscious"];
            if (!local _unit || {_unit != player}) exitWith {};
            if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
            if (isNull findDisplay 46) exitWith {};
            if (_isUnconscious) then {
                [_unit] call comspec_overwatch_connect_fnc_checkMedicalAlerts;
            } else {
                // Réveil réel : clôturer l’alerte côté Athena (silent) + reset verrou local.
                [true] call comspec_overwatch_connect_fnc_selfCancelMedicalAlert;
                missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
            };
        }] call CBA_fnc_addEventHandler;
    };

    player addAction [
        "<t color='#7fffd4'>Tableau de briefing</t>",
        { [] call comspec_overwatch_connect_fnc_openBriefingBoard; },
        nil, 6, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    player addAction [
        "<t color='#7fffd4'>Connecter mon téléphone</t>",
        { [] call comspec_overwatch_connect_fnc_phoneConnectShow; },
        nil, 5.9, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    player addAction [
        "<t color='#7fffd4'>Ma tablette Athena</t>",
        { [] call comspec_overwatch_connect_fnc_webBrowserShow; },
        nil, 5.8, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    player addAction [
        "<t color='#8aa0b4'>Tablette (vue classique)</t>",
        { if (isNull (findDisplay 9973)) then { createDialog 'COMSPEC_Device_Dialog'; }; },
        nil, 5.75, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    player addAction [
        "<t color='#7dffb3'>Je vais bien (annuler mon alerte)</t>",
        { [] call comspec_overwatch_connect_fnc_selfCancelMedicalAlert; },
        nil, 5.85, false, true, "",
        "(missionNamespace getVariable ['comspec_overwatch_enabled', true]) && {count ([] call comspec_overwatch_connect_fnc_hasOwnActiveMedicalAlert) > 0}"
    ];

    player addAction [
        "<t color='#ff9080'>Signaler une situation</t>",
        { [] call comspec_overwatch_connect_fnc_tacticalAlertDialogShow; },
        nil, 5.82, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    player addAction [
        "<t color='#7fffd4'>Mon indicatif</t>",
        { [] call comspec_overwatch_connect_fnc_callsignDialogShow; },
        nil, 5.7, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    player addAction [
        "<t color='#ffb070'>Ordres reçus</t>",
        { [] call comspec_overwatch_connect_fnc_orderInboxShow; },
        nil, 5.6, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    // Déconnexion ATAK à la sortie mission / quit Arma (sync extension, timeout court).
    // Réinitialiser à chaque mission (missionNamespace survit au changement de mission).
    missionNamespace setVariable ["COMSPEC_DisconnectSent", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsBootstrapped", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertsSeen", [], false];
    missionNamespace setVariable ["COMSPEC_SyncLoopsStarted", false, false];
    if (isNil "COMSPEC_DisconnectEHs") then {
        COMSPEC_DisconnectEHs = true;
        addMissionEventHandler ["Ended", {
            [] call comspec_overwatch_connect_fnc_disconnect;
        }];
        // Display 46 = jeu principal : Unload = retour menu / quit desktop.
        0 spawn {
            private _t = diag_tickTime + 30;
            waitUntil { !isNull findDisplay 46 || {diag_tickTime > _t} };
            if (isNull findDisplay 46) exitWith {};
            (findDisplay 46) displayAddEventHandler ["Unload", {
                [] call comspec_overwatch_connect_fnc_disconnect;
                false
            }];
        };
    };
}] call CBA_fnc_addEventHandler;
