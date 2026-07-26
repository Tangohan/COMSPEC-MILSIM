if (!hasInterface) exitWith {};

["INFO", "Boot", "PostInit client — warmup extension"] call comspec_overwatch_connect_fnc_log;

// Warmup extension (charge la DLL)
"COMSPECExtension" callExtension "Warmup";

// Re-applique compat Mavic après init settings CBA (au cas où PreInit était trop tôt)
["CBA_settingsInitialized", {
    private _wasNil = isNil "mavic_setting_enableConnectionDistance";
    if (_wasNil) then {
        mavic_setting_enableConnectionDistance = false;
    };
    ["INFO", "Compat", format [
        "CBA_settingsInitialized — mavic wasNil=%1 nowNil=%2",
        _wasNil,
        isNil "mavic_setting_enableConnectionDistance"
    ]] call comspec_overwatch_connect_fnc_log;
    ["boot"] call comspec_overwatch_connect_fnc_logDump;
}] call CBA_fnc_addEventHandler;

// Callbacks async extension → SQF (inspiré cTab IRL)
if (isNil "COMSPEC_ExtensionCallbackEH") then {
    COMSPEC_ExtensionCallbackEH = addMissionEventHandler ["ExtensionCallback", {
        _this call comspec_overwatch_connect_fnc_extensionCallback;
    }];
    ["DEBUG", "Boot", "ExtensionCallback EH enregistré"] call comspec_overwatch_connect_fnc_log;
};

["CBA_settingsInitialized", {
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
        ["WARN", "Boot", "Overwatch désactivé — pas de sync / ACE"] call comspec_overwatch_connect_fnc_log;
    };

    // Menus ACE uniquement si l’option est activée (évite conflits pack au démarrage).
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (!(missionNamespace getVariable ["comspec_overwatch_ace_menus", false])) exitWith {
            ["INFO", "ACE", "Menus ACE désactivés (réglage comspec_overwatch_ace_menus)"] call comspec_overwatch_connect_fnc_log;
        };
        if (isNull player) exitWith {
            [{
                if (missionNamespace getVariable ["comspec_overwatch_ace_menus", false]) then {
                    [] call comspec_overwatch_connect_fnc_initACE;
                };
            }, [], 2] call CBA_fnc_waitAndExecute;
        };
        [] call comspec_overwatch_connect_fnc_initACE;
    }, [], 8] call CBA_fnc_waitAndExecute;

    // Handshake Athena (inspiré Remastered) puis sync lourde — hors thread principal
    0 spawn {
        ["INFO", "Athena", "Handshake démarré"] call comspec_overwatch_connect_fnc_log;
        private _ok = [] call comspec_overwatch_connect_fnc_waitAthenaReady;
        ["INFO", "Athena", format ["Handshake terminé ok=%1", _ok]] call comspec_overwatch_connect_fnc_log;
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [] call comspec_overwatch_connect_fnc_startSyncLoops;
        ["INFO", "Boot", "Boucles de sync démarrées"] call comspec_overwatch_connect_fnc_log;
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

    // Repli : note d’accès anticipé si le menu principal n’a pas pu l’afficher
    // + enrichissement Steam une fois le joueur disponible en mission
    0 spawn {
        uiSleep 3;
        if (profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false]) then {
            private _needSend = !(profileNamespace getVariable ["comspec_overwatch_beta_registered", false]);
            private _steamNow = if (!isNull player) then { getPlayerUID player } else { "" };
            if (!_needSend && {(count _steamNow) >= 15} && {!(profileNamespace getVariable ["comspec_overwatch_beta_has_steam", false])}) then {
                _needSend = true;
            };
            if (_needSend) then {
                [] call comspec_overwatch_connect_fnc_registerBetaClient;
            };
        } else {
            [] call comspec_overwatch_connect_fnc_showBetaAccessNote;
        };
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

    // Roleplay : PFH pour simuler les déconnexions réseau aléatoires
    [{
        [] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;
    }, 5, []] call CBA_fnc_addPerFrameHandler; // Vérifier toutes les 5 secondes
    
    // Roleplay : PFH pour détecter les zones géographiques (pas d'overlay UI ingame)
    [{
        [] call comspec_overwatch_connect_fnc_applyZoneEffects;
    }, 2, []] call CBA_fnc_addPerFrameHandler; // Vérifier toutes les 2 secondes
    
    // Réalisme ATAK : Event handler pour les blessures
    player addEventHandler ["Hit", {
        params ["_unit", "_source", "_damage", "_instigator"];
        // Vérifier dommages ATAK
        [] call comspec_overwatch_connect_fnc_checkAtakDamage;
    }];
    
    // Vérification périodique de l'état ATAK
    [{
        [] call comspec_overwatch_connect_fnc_checkAtakDamage;
    }, 10, []] call CBA_fnc_addPerFrameHandler; // Toutes les 10 secondes
    
    // Actions réparation ATAK — seulement si menus ACE activés
    [{
        if (missionNamespace getVariable ["comspec_overwatch_ace_menus", false]) then {
            [] call comspec_overwatch_connect_fnc_addAtakRepairAction;
        };
    }, [], 9] call CBA_fnc_waitAndExecute;

    // Déconnexion ATAK à la sortie mission / quit Arma (sync extension, timeout court).
    // Réinitialiser à chaque mission (missionNamespace survit au changement de mission).
    // Si le client crash ou quitte sans Unload : le portail expire la liaison (TTL heartbeat).
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
