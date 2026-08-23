if (!hasInterface) exitWith {};

["INFO", "Boot", "PostInit client — warmup extension"] call comspec_overwatch_connect_fnc_log;

// Tampons SSE locaux (icône « ? » orange) : ils se superposent sur la carte ATAK.
{
    private _txt = markerText _x;
    if ((_x find "_comspec_sse_") == 0 || {(_txt select [0, 4]) isEqualTo "SSE "}) then {
        deleteMarkerLocal _x;
    };
} forEach allMapMarkers;

// Warmup extension (charge la DLL)
"COMSPECExtension" callExtension "Warmup";

// Nouveau journal fichier pour cette session Arma (purge des anciens)
if (missionNamespace getVariable ["comspec_overwatch_log_to_file", true]) then {
    private _logPath = [] call comspec_overwatch_connect_fnc_startLogSession;
    if (_logPath isNotEqualTo "") then {
        ["INFO", "Boot", format ["Journal session : %1", _logPath]] call comspec_overwatch_connect_fnc_log;
    };
};

// EH marqueurs dès le PostInit (avant handshake) — file d’attente si Athena pas prêt
if (isNil "COMSPEC_MapMarkerEHsEarly") then {
    COMSPEC_MapMarkerEHsEarly = true;
    if (isNil "COMSPEC_MapMarkerEHs") then {
        private _resyncSoon = {
            params ["_marker"];
            [_marker, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
            [{
                params ["_m"];
                if (_m in allMapMarkers) then {
                    [_m, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
                };
            }, [_marker], 0.15] call CBA_fnc_waitAndExecute;
            [{
                params ["_m"];
                if (_m in allMapMarkers) then {
                    [_m, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
                };
                if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers") then {
                    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
                };
            }, [_marker], 0.5] call CBA_fnc_waitAndExecute;
            [{
                params ["_m"];
                if (_m in allMapMarkers) then {
                    [_m, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
                };
                if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers") then {
                    [] call comspec_overwatch_atak_athena_fnc_athena_bridgeCtabMarkers;
                };
            }, [_marker], 1.0] call CBA_fnc_waitAndExecute;
        };
        missionNamespace setVariable ["COMSPEC_MarkerResyncSoon", _resyncSoon];
        COMSPEC_MapMarkerEHs = [
            addMissionEventHandler ["MarkerCreated", {
                params ["_marker"];
                [_marker] call (missionNamespace getVariable ["COMSPEC_MarkerResyncSoon", {}]);
            }],
            addMissionEventHandler ["MarkerUpdated", {
                params ["_marker"];
                [_marker, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
            }],
            addMissionEventHandler ["MarkerDeleted", {
                params ["_marker"];
                [_marker, true, true] call comspec_overwatch_connect_fnc_syncMapMarker;
            }]
        ];
        ["INFO", "Markers", "EH MarkerCreated/Updated/Deleted enregistrés (early)"] call comspec_overwatch_connect_fnc_log;
    };
};

// Re-applique compat Mavic apres init settings CBA (au cas ou PreInit etait trop tot)
["CBA_settingsInitialized", {
    if (isNil "mavic_setting_enableConnectionDistance") then {
        missionNamespace setVariable ["mavic_setting_enableConnectionDistance", false];
    };
    if (isNil "mavic_setting_maxConnectionDistance") then {
        missionNamespace setVariable ["mavic_setting_maxConnectionDistance", 6000];
    };
    if (isNil "mavic_setting_showInterface") then {
        missionNamespace setVariable ["mavic_setting_showInterface", true];
    };
    if (isNil "mavic_setting_vanillaInterface") then {
        missionNamespace setVariable ["mavic_setting_vanillaInterface", false];
    };
    ["INFO", "Compat", format [
        "CBA_settingsInitialized — mavic enableConn isNil=%1 maxDist=%2",
        isNil "mavic_setting_enableConnectionDistance",
        missionNamespace getVariable ["mavic_setting_maxConnectionDistance", -1]
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

    // Charges ACE (minuterie + déclenchement TOC) → section ATAK web.
    [{
        isClass (configFile >> "CfgPatches" >> "ace_explosives")
    }, {
        [] call comspec_overwatch_connect_fnc_initExplosiveTimers;
    }, [], 120, {}] call CBA_fnc_waitUntilAndExecute;

    // Handshake Athena puis attendre stabilisation spawn/JIP avant sync lourde + alertes médicales.
    // CTD observé (RPT 15-22-54) : Handshake OK puis crash avant « Boucles de sync » sur __cur_mp JIP
    // (ACE/ACM init + MessageBox + sync extension dans la même fenêtre).
    0 spawn {
        ["INFO", "Athena", "Handshake démarré"] call comspec_overwatch_connect_fnc_log;
        private _ok = [] call comspec_overwatch_connect_fnc_waitAthenaReady;
        ["INFO", "Athena", format ["Handshake terminé ok=%1", _ok]] call comspec_overwatch_connect_fnc_log;
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

        private _deadline = diag_tickTime + 90;
        waitUntil {
            (
                !isNull player
                && {alive player}
                && {!isNull findDisplay 46}
                && {
                    !isMultiplayer
                    || {
                        private _st = getClientStateNumber;
                        _st >= 10 && {_st < 11}
                    }
                }
                && {
                    private _p = getPosWorld player;
                    (abs (_p select 0) >= 1) || {abs (_p select 1) >= 1}
                }
            ) || {diag_tickTime > _deadline}
        };

        // ACE (~3k delayed) + MRH JIP + handshake : attendre longtemps avant sync
        uiSleep 15;

        // Si REAPP pendant l’attente : prolonger jusqu’à fin de grâce
        waitUntil {
            diag_tickTime >= (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])
            || {diag_tickTime > (_deadline + 60)}
        };
        uiSleep 3;

        if (isNull player || {!alive player} || {isNull findDisplay 46}) exitWith {
            ["WARN", "Boot", "Spawn non stabilisé — sync différée annulée"] call comspec_overwatch_connect_fnc_log;
        };

        missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", true, false];
        missionNamespace setVariable ["COMSPEC_SpawnStableAt", diag_tickTime, false];
        missionNamespace setVariable ["COMSPEC_DeathThenRespawn", false, false];
        ["INFO", "Boot", "Spawn stabilisé — armement alertes médicales"] call comspec_overwatch_connect_fnc_log;

        [] call comspec_overwatch_connect_fnc_startSyncLoops;
        ["INFO", "Boot", "Boucles de sync démarrées"] call comspec_overwatch_connect_fnc_log;
    };

    // Alerte Windows (MessageBox) si compte Athena non lié — JAMAIS pendant REAPP
    0 spawn {
        uiSleep 8;
        waitUntil {
            missionNamespace getVariable ["COMSPEC_AthenaReady", false]
            || {diag_tickTime > ((missionNamespace getVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime]) + 120)}
        };
        waitUntil {
            missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false]
            || {diag_tickTime > ((missionNamespace getVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime]) + 180)}
        };
        // Après armement médical : encore une marge (spike ACE/MRH)
        uiSleep 20;
        waitUntil {
            ([] call comspec_overwatch_connect_fnc_canShowWinMessageBox)
            || {diag_tickTime > ((missionNamespace getVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime]) + 240)}
        };
        if (missionNamespace getVariable ["COMSPEC_CancelPendingAthenaHelp", false]) exitWith {
            ["INFO", "Athena", "Aide liaison annulée (respawn / REAPP)"] call comspec_overwatch_connect_fnc_log;
        };
        if !([] call comspec_overwatch_connect_fnc_canShowWinMessageBox) exitWith {};
        [] call comspec_overwatch_connect_fnc_showAthenaLinkHelp;
    };

    // Note bêta : différée (pas de MessageBox Win32 en mission sur REAPP)
    0 spawn {
        uiSleep 25;
        waitUntil {
            (
                missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false]
                && {diag_tickTime >= (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])}
            )
            || {diag_tickTime > ((missionNamespace getVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime]) + 200)}
        };
        uiSleep 5;
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

    // Alerte immédiate dès le passage KO (ACE) — le PFH position couvre aussi FC=0 / KAT
    if (isNil "COMSPEC_aceUnconsciousEH") then {
        COMSPEC_aceUnconsciousEH = ["ace_unconscious", {
            params ["_unit", "_isUnconscious"];
            if (!local _unit || {_unit != player}) exitWith {};
            if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
            if (isNull findDisplay 46) exitWith {};
            if !(missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false]) exitWith {};
            if !([] call comspec_overwatch_connect_fnc_isPlayerSpawnStable) exitWith {};
            if (_isUnconscious) then {
                [_unit] call comspec_overwatch_connect_fnc_checkMedicalAlerts;
            } else {
                [true] call comspec_overwatch_connect_fnc_selfCancelMedicalAlert;
                missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
            };
        }] call CBA_fnc_addEventHandler;
    };

    // ACE : inconscience / rétablissement
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

    // Zeus Enhanced : modules roleplay posables (carte / objet / joueur)
    [{
        [] call comspec_overwatch_connect_fnc_registerZenRoleplayModules;
        [] call comspec_overwatch_connect_fnc_registerZenAtakPlayerActions;
        [] call comspec_overwatch_connect_fnc_registerZenSseModules;
    }, [], 2] call CBA_fnc_waitAndExecute;
    [{
        [] call comspec_overwatch_connect_fnc_registerZenRoleplayModules;
        [] call comspec_overwatch_connect_fnc_registerZenAtakPlayerActions;
        [] call comspec_overwatch_connect_fnc_registerZenSseModules;
    }, [], 8] call CBA_fnc_waitAndExecute;

    // Tampon hors ligne : rejeu des transmissions mises en attente. La boucle est
    // lente à dessein — c'est fn_outboxFlush qui porte la temporisation, ici on ne
    // fait que lui donner l'occasion de regarder si la liaison est revenue.
    [{
        [] call comspec_overwatch_connect_fnc_outboxFlush;
    }, 10, []] call CBA_fnc_addPerFrameHandler;

    // Identifiants ATAK visibles côté Zeus
    [{
        [] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;
    }, [], 5] call CBA_fnc_waitAndExecute;
    [{
        if (!hasInterface || {isNull player}) exitWith {};
        [] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;
    }, 60, []] call CBA_fnc_addPerFrameHandler;
    
    // Réalisme ATAK : Hit + Explosion sur l’unité (rebranchés au Respawn)
    [] call comspec_overwatch_connect_fnc_attachAtakDamageHandlers;

    if (isNil "COMSPEC_AtakDamagePFH") then {
        COMSPEC_AtakDamagePFH = [{
            if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
            [] call comspec_overwatch_connect_fnc_checkAtakDamage;
        }, 10, []] call CBA_fnc_addPerFrameHandler;
    };

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
    missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", false, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertBusy", false, false];
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertAt", -1e9, false];
    missionNamespace setVariable ["COMSPEC_RespawnGraceUntil", -1e9, false];
    missionNamespace setVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9, false];
    missionNamespace setVariable ["COMSPEC_CancelPendingAthenaHelp", false, false];
    missionNamespace setVariable ["COMSPEC_DeathThenRespawn", false, false];
    missionNamespace setVariable ["COMSPEC_VehicleTrackingInited", false, false];
    missionNamespace setVariable ["COMSPEC_VehTrackPlayer", objNull, false];
    missionNamespace setVariable ["COMSPEC_VehicleTrackingBootMsg", false, false];
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

// Sync multi-clients du briefing Google Slides (URL + index).
if (isNil "COMSPEC_GoogleBriefingStateEH") then {
    COMSPEC_GoogleBriefingStateEH = [
        "COMSPEC_GoogleBriefingState",
        { _this call comspec_overwatch_connect_fnc_handleGoogleBriefingState; }
    ] call CBA_fnc_addEventHandler;
};
