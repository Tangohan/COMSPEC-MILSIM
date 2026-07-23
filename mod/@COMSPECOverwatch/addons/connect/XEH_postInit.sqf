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

    // Plus d’entrées dans le menu molette : tablette = K, hub = Ctrl+Shift+K, messagerie = Ctrl+K.
    // (Les outils restent accessibles via le hub / ACE / tablette.)

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
