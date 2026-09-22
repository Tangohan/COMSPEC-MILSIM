if (!isNil "COMSPEC_Overwatch_PostInitDone") exitWith {
    if (!isNil "comspec_overwatch_connect_fnc_log") then {
        ["WARN", "Boot", "PostInit ignoré — déjà exécuté cette mission"] call comspec_overwatch_connect_fnc_log;
    };
};
COMSPEC_Overwatch_PostInitDone = true;

if (isServer) then {
    [] call comspec_overwatch_connect_fnc_initProxyTrackServer;
};
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

// Repères déjà nôtres (poste → jeu) : ne pas les renvoyer, ni relancer le pont cTab.
missionNamespace setVariable ["COMSPEC_fnc_isOwnedMapMarker", {
    params ["_n"];
    if (!(_n isEqualType "") || {_n isEqualTo ""}) exitWith { false };
    private _ul = toLower _n;
    (
        (_ul find "comspec_webmk_") == 0
        || {(_ul find "comspec_shape_") == 0}
        || {(_ul find "comspec_tabletmk_") == 0}
        || {(_ul find "comspec_relay_") == 0}
        || {(_ul find "_comspec_po_ring_") == 0}
        || {(_ul find "_comspec_det_ring_") == 0}
        || {(_ul find "comspec_gps_") == 0}
    )
}, false];

// EH marqueurs dès le PostInit (avant handshake) — file d’attente si Athena pas prêt.
// Un seul rattrapage différé. Jamais de forçage sans téléphone : ça relançait
// quatre envois + le pont cTab à chaque anneau / forme / repère web.
if (isNil "COMSPEC_MapMarkerEHsEarly") then {
    COMSPEC_MapMarkerEHsEarly = true;
    if (isNil "COMSPEC_MapMarkerEHs") then {
        private _resyncSoon = {
            params ["_marker"];
            if ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) > 0) exitWith {};
            if ([_marker] call (missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker", { false }])) exitWith {};
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            if (!(["markers"] call comspec_overwatch_connect_fnc_diagIsolateAllows)) exitWith {};
            private _forceNow = [_marker] call comspec_overwatch_connect_fnc_isSyncableMapMarker;
            [_marker, false, _forceNow] call comspec_overwatch_connect_fnc_syncMapMarker;
            [{
                params ["_m"];
                if ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) > 0) exitWith {};
                if ([_m] call (missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker", { false }])) exitWith {};
                if (!(_m in allMapMarkers)) exitWith {};
                if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                if (!(["markers"] call comspec_overwatch_connect_fnc_diagIsolateAllows)) exitWith {};
                private _forceLater = [_m] call comspec_overwatch_connect_fnc_isSyncableMapMarker;
                [_m, false, _forceLater] call comspec_overwatch_connect_fnc_syncMapMarker;
            }, [_marker], 0.4] call CBA_fnc_waitAndExecute;
        };
        missionNamespace setVariable ["COMSPEC_MarkerResyncSoon", _resyncSoon];
        COMSPEC_MapMarkerEHs = [
            addMissionEventHandler ["MarkerCreated", {
                private _marker = _this call comspec_overwatch_connect_fnc_resolveMarkerEhName;
                if (_marker isEqualTo "") exitWith {};
                private _ch = _this param [1, -1];
                private _own = _this param [2, -1];
                private _local = _this param [3, false];
                if (_local isEqualTo true) then {
                    private _st = systemTime;
                    private _hh = str (_st select 3);
                    private _mm = str (_st select 4);
                    if ((count _hh) < 2) then { _hh = "0" + _hh; };
                    if ((count _mm) < 2) then { _mm = "0" + _mm; };
                    private _by = if (!isNull player) then { name player } else { "" };
                    missionNamespace setVariable [
                        format ["COMSPEC_UserMkMeta_%1", _marker],
                        [_ch, _by, format ["%1:%2", _hh, _mm], _own],
                        false
                    ];
                };
                [_marker] call (missionNamespace getVariable ["COMSPEC_MarkerResyncSoon", {}]);
            }],
            addMissionEventHandler ["MarkerUpdated", {
                private _marker = _this call comspec_overwatch_connect_fnc_resolveMarkerEhName;
                if (_marker isEqualTo "") exitWith {};
                if ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) > 0) exitWith {};
                if ([_marker] call (missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker", { false }])) exitWith {};
                if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                if (!(["markers"] call comspec_overwatch_connect_fnc_diagIsolateAllows)) exitWith {};
                private _force = [_marker] call comspec_overwatch_connect_fnc_isSyncableMapMarker;
                [_marker, false, _force] call comspec_overwatch_connect_fnc_syncMapMarker;
            }],
            addMissionEventHandler ["MarkerDeleted", {
                private _marker = _this call comspec_overwatch_connect_fnc_resolveMarkerEhName;
                if (_marker isEqualTo "") exitWith {};
                if ((missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) > 0) exitWith {};
                if ([_marker] call (missionNamespace getVariable ["COMSPEC_fnc_isOwnedMapMarker", { false }])) exitWith {};
                if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                if (!(["markers"] call comspec_overwatch_connect_fnc_diagIsolateAllows)) exitWith {};
                private _force = [_marker] call comspec_overwatch_connect_fnc_isSyncableMapMarker;
                [_marker, true, _force] call comspec_overwatch_connect_fnc_syncMapMarker;
            }]
        ];
        ["INFO", "Markers", "EH MarkerCreated/Updated/Deleted enregistrés (early)"] call comspec_overwatch_connect_fnc_log;
    };
};

// Fermeture de la carte Arma : les repères joueur (canal global) sont souvent
// confirmés seulement à ce moment. Deux envois courts, puis le rattrapage 8 s.
if (isNil "COMSPEC_MapClosedEh") then {
    COMSPEC_MapClosedEh = addMissionEventHandler ["Map", {
        params ["_mapIsOpened"];
        if (_mapIsOpened) exitWith {};
        [{
            if (!isNil "comspec_overwatch_connect_fnc_syncUserMapMarkers") then {
                [] call comspec_overwatch_connect_fnc_syncUserMapMarkers;
            };
        }, [], 0.35] call CBA_fnc_waitAndExecute;
        [{
            if (!isNil "comspec_overwatch_connect_fnc_syncUserMapMarkers") then {
                [] call comspec_overwatch_connect_fnc_syncUserMapMarkers;
            };
        }, [], 1.25] call CBA_fnc_waitAndExecute;
    }];
    ["INFO", "Markers", "EH Map fermée — envoi des repères joueur"] call comspec_overwatch_connect_fnc_log;
};

if (hasInterface && {isNil "COMSPEC_RelayMapPfh"}) then {
    COMSPEC_RelayMapPfh = [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (isNull player || {!alive player}) exitWith {};
        if (!isNil "comspec_overwatch_connect_fnc_updateNearestRelayMap") then {
            [] call comspec_overwatch_connect_fnc_updateNearestRelayMap;
        };
    }, 2] call CBA_fnc_addPerFrameHandler;
};

// Re-applique compat Mavic apres init settings CBA (au cas ou PreInit etait trop tot).
// CBA_settingsInitialized peut se rejouer (briefing MP, overlay mission, synchro serveur) :
// sans garde, dump + handshake + PFH s'empilent toutes les secondes.
if (isNil "COMSPEC_CbaSettingsEhDump") then {
COMSPEC_CbaSettingsEhDump = ["CBA_settingsInitialized", {
    if (missionNamespace getVariable ["COMSPEC_CbaSettingsBootDone", false]) exitWith {};
    missionNamespace setVariable ["COMSPEC_CbaSettingsBootDone", true, false];

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
};

// Callbacks async extension → SQF (inspiré cTab IRL)
if (isNil "COMSPEC_ExtensionCallbackEH") then {
    COMSPEC_ExtensionCallbackEH = addMissionEventHandler ["ExtensionCallback", {
        _this call comspec_overwatch_connect_fnc_extensionCallback;
    }];
    ["DEBUG", "Boot", "ExtensionCallback EH enregistré"] call comspec_overwatch_connect_fnc_log;
};

if (isNil "COMSPEC_CbaSettingsEhArmed") then {
COMSPEC_CbaSettingsEhArmed = ["CBA_settingsInitialized", {
    if (missionNamespace getVariable ["COMSPEC_CbaSettingsBootArmed", false]) exitWith {};
    missionNamespace setVariable ["COMSPEC_CbaSettingsBootArmed", true, false];

    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
        ["WARN", "Boot", "Overwatch désactivé — pas de sync / ACE"] call comspec_overwatch_connect_fnc_log;
    };
    [] call comspec_overwatch_connect_fnc_applyNetworkProfile;

    if (isNil "COMSPEC_AthenaLoginSyncEH") then {
        COMSPEC_AthenaLoginSyncEH = ["COMSPEC_AthenaLinkChanged", {
            params ["_st"];
            if (!(_st in ["ready", "linked"])) exitWith {};
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            // Pendant HandshakeQuiet : reporter l’ouverture (sinon canStartSync refuse
            // et plus personne ne relance les boucles → position « pas encore »).
            if (!isNil "comspec_overwatch_connect_fnc_canStartSync"
                && {!([] call comspec_overwatch_connect_fnc_canStartSync)}) exitWith {
                if (missionNamespace getVariable ["COMSPEC_LinkSyncRetryScheduled", false]) exitWith {};
                missionNamespace setVariable ["COMSPEC_LinkSyncRetryScheduled", true, false];
                [{
                    missionNamespace setVariable ["COMSPEC_LinkSyncRetryScheduled", false, false];
                    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                    if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith {};
                    if (!isNil "comspec_overwatch_connect_fnc_reopenTransmitChannel") then {
                        [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
                    };
                }, [], 22] call CBA_fnc_waitAndExecute;
            };
            missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", true, false];
            [] call comspec_overwatch_connect_fnc_startSyncLoops;
            if (!isNil "comspec_overwatch_connect_fnc_applyCtabBftCallsign") then {
                [] call comspec_overwatch_connect_fnc_applyCtabBftCallsign;
            };
            // Première position dès l’ouverture du canal (ne pas attendre un déplacement).
            if (!isNull player && {alive player}
                && {!isNil "comspec_overwatch_connect_fnc_updatePosition"}) then {
                [player, true] call comspec_overwatch_connect_fnc_updatePosition;
            };
        }] call CBA_fnc_addEventHandler;
    };

    // Filet : canal ouvert mais boucles absentes / position jamais partie / sync devenue trop vieille.
    // Avant : on ne forçait que si LastPositionSync < 0 → dès qu’une position avait été
    // envoyée une fois, un blocage (backoff, relais, zone) exigeait un Resynch manuel.
    if (isNil "COMSPEC_PosUplinkWatchdog") then {
        COMSPEC_PosUplinkWatchdog = [{
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
            if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith {};
            if (isNull player || {!alive player}) exitWith {};
            if !(missionNamespace getVariable ["COMSPEC_SyncLoopsStarted", false]) exitWith {
                if (isNull player || {!([player] call comspec_overwatch_connect_fnc_hasTerminal)}) exitWith {};
                if (!isNil "comspec_overwatch_connect_fnc_reopenTransmitChannel") then {
                    [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
                };
            };
            private _lastPos = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
            private _stale = true;
            if ((_lastPos isEqualType 0) && {_lastPos >= 0}) then {
                _stale = (diag_tickTime - _lastPos) > 60;
            };
            if (!_stale) exitWith {};
            private _lastTry = missionNamespace getVariable ["COMSPEC_PosWatchdogForceAt", -1e9];
            if ((diag_tickTime - _lastTry) < 20) exitWith {};
            missionNamespace setVariable ["COMSPEC_PosWatchdogForceAt", diag_tickTime, false];
            // Comme Resynch : lever les freins API sinon la force échoue encore.
            missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
            missionNamespace setVariable ["COMSPEC_SendBackoffSec", 0, false];
            if (!isNil "comspec_overwatch_connect_fnc_updatePosition") then {
                [player, true] call comspec_overwatch_connect_fnc_updatePosition;
            };
        }, 8] call CBA_fnc_addPerFrameHandler;
    };

    // COMSPEC Athena (connexion + téléphone) et menus ATAK (rapports, appui, réparation).
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if (isNull player) exitWith {
            [{
                if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                [] call comspec_overwatch_connect_fnc_initACEAthena;
                [] call comspec_overwatch_connect_fnc_initACE;
                [] call comspec_overwatch_connect_fnc_initATAKMenu;
            }, [], 2] call CBA_fnc_waitAndExecute;
        };
        [] call comspec_overwatch_connect_fnc_initACEAthena;
        [] call comspec_overwatch_connect_fnc_initACE;
        [] call comspec_overwatch_connect_fnc_initATAKMenu;
    }, [], 8] call CBA_fnc_waitAndExecute;

    // Affichage situation JVN (Draw3D) — OFF par défaut ; profil ATAK si présent ; pas de conflit F-PANO.
    [{
        private _raw = profileNamespace getVariable ["COMSPEC_EcotiHudEnabled", "UNSET"];
        if (_raw isEqualType true) then {
            [_raw, false] call comspec_overwatch_connect_fnc_ecotiApplyHudSetting;
        };
        private _cut = profileNamespace getVariable ["COMSPEC_EcotiCutawayEnabled", "UNSET"];
        if (_cut isEqualType true) then {
            [_cut, true] call comspec_overwatch_connect_fnc_ecotiApplyCutawaySetting;
            profileNamespace setVariable ["COMSPEC_EcotiCutawayEnabled", nil];
            saveProfileNamespace;
        };
        private _theme = profileNamespace getVariable ["COMSPEC_EcotiTheme", "UNSET"];
        if (_theme isEqualType "" && {_theme isNotEqualTo "UNSET"}) then {
            [_theme, false] call comspec_overwatch_connect_fnc_ecotiApplyThemeSetting;
        };
        private _render = profileNamespace getVariable ["COMSPEC_EcotiRenderMode", "UNSET"];
        if (_render isEqualType "" && {_render isNotEqualTo "UNSET"}) then {
            [_render, false] call comspec_overwatch_connect_fnc_ecotiApplyRenderModeSetting;
        };
        private _tubeInfo = profileNamespace getVariable ["COMSPEC_EcotiTubeInfo", "UNSET"];
        if (_tubeInfo isEqualType "" && {_tubeInfo isNotEqualTo "UNSET"}) then {
            [_tubeInfo, false] call comspec_overwatch_connect_fnc_ecotiApplyTubeInfoSetting;
        };
        [false, true] call comspec_overwatch_connect_fnc_linkStripApplySetting;
        private _linkSim = profileNamespace getVariable ["COMSPEC_LinkDegradeSimEnabled", "UNSET"];
        if (_linkSim isEqualType true) then {
            [_linkSim, false] call comspec_overwatch_connect_fnc_linkDegradeSimApplySetting;
        };
        if (!isNil "comspec_overwatch_connect_fnc_ecotiInit") then {
            [] call comspec_overwatch_connect_fnc_ecotiInit;
        };
    }, [], 3] call CBA_fnc_waitAndExecute;

    // Charges ACE (minuterie + déclenchement TOC) → section ATAK web.
    [{
        isClass (configFile >> "CfgPatches" >> "ace_explosives")
    }, {
        [] call comspec_overwatch_connect_fnc_initExplosiveTimers;
    }, [], 120, {}] call CBA_fnc_waitUntilAndExecute;

    // Handshake Athena puis attendre stabilisation spawn/JIP avant sync lourde + alertes médicales.
    // CTD observé (RPT 15-22-54) : Handshake OK puis crash avant « Boucles de sync » sur __cur_mp JIP
    // (ACE/ACM init + MessageBox + sync extension dans la même fenêtre).
    if (isNil "COMSPEC_BootHandshakeSpawn") then {
        COMSPEC_BootHandshakeSpawn = true;
        0 spawn {
        ["INFO", "Athena", "Handshake démarré"] call comspec_overwatch_connect_fnc_log;
        private _ok = [] call comspec_overwatch_connect_fnc_waitAthenaReady;
        ["INFO", "Athena", format ["Handshake terminé ok=%1", _ok]] call comspec_overwatch_connect_fnc_log;
        missionNamespace setVariable ["COMSPEC_BootHandshakeDone", true, false];
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        if !([] call comspec_overwatch_connect_fnc_isReady) then {
            ["WARN", "Boot", "Session Athena absente — attente d’une connexion"] call comspec_overwatch_connect_fnc_log;
            private _loginUntil = diag_tickTime + 600;
            waitUntil {
                ([] call comspec_overwatch_connect_fnc_isReady)
                || {diag_tickTime > _loginUntil}
            };
        };
        if !([] call comspec_overwatch_connect_fnc_isReady) exitWith {
            ["WARN", "Boot", "Toujours hors liaison — sync au prochain login"] call comspec_overwatch_connect_fnc_log;
            missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", true, false];
        };

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
            // Filet : après un respawn / Zeus, relancer dès que le joueur est de nouveau jouable.
            if (isNil "COMSPEC_SpawnRetryWatch") then {
                COMSPEC_SpawnRetryWatch = [{
                    if (missionNamespace getVariable ["COMSPEC_SyncLoopsStarted", false]) exitWith {
                        if (!isNil "COMSPEC_SpawnRetryWatch") then {
                            [COMSPEC_SpawnRetryWatch] call CBA_fnc_removePerFrameHandler;
                            COMSPEC_SpawnRetryWatch = nil;
                        };
                    };
                    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
                    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
                    if (isNull player || {!alive player} || {isNull findDisplay 46}) exitWith {};
                    if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
                    private _p = getPosWorld player;
                    if ((abs (_p select 0) < 1) && {abs (_p select 1) < 1}) exitWith {};
                    if (!isNil "COMSPEC_SpawnRetryWatch") then {
                        [COMSPEC_SpawnRetryWatch] call CBA_fnc_removePerFrameHandler;
                        COMSPEC_SpawnRetryWatch = nil;
                    };
                    ["INFO", "Boot", "Spawn rétabli — relance des boucles de sync"] call comspec_overwatch_connect_fnc_log;
                    if (!isNil "comspec_overwatch_connect_fnc_reopenTransmitChannel") then {
                        [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
                    } else {
                        [] call comspec_overwatch_connect_fnc_startSyncLoops;
                    };
                }, 5] call CBA_fnc_addPerFrameHandler;
            };
        };

        missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", true, false];
        missionNamespace setVariable ["COMSPEC_SpawnStableAt", diag_tickTime, false];
        missionNamespace setVariable ["COMSPEC_DeathThenRespawn", false, false];
        ["INFO", "Boot", "Spawn stabilisé — armement alertes médicales"] call comspec_overwatch_connect_fnc_log;

        [] call comspec_overwatch_connect_fnc_startSyncLoops;
        if (missionNamespace getVariable ["COMSPEC_SyncLoopsStarted", false]) then {
            ["INFO", "Boot", "Boucles de sync démarrées"] call comspec_overwatch_connect_fnc_log;
        } else {
            ["INFO", "Boot", "Boucles de sync encore en attente"] call comspec_overwatch_connect_fnc_log;
        };
        };
    };

    // Alerte Windows « lier Athena » : plus d’affichage automatique en mission
    // (parade de fenêtres). Le rappel reste dans Échap → gestion du mod.

    // Note bêta : affichée au menu principal (fenêtre Windows). En mission on
    // n’ouvre plus de dialogue — seulement l’inscription Athena si déjà acceptée.
    0 spawn {
        uiSleep 8;
        waitUntil {
            missionNamespace getVariable ["COMSPEC_AthenaReady", false]
            || {diag_tickTime > ((missionNamespace getVariable ["COMSPEC_HandshakeStartedAt", diag_tickTime]) + 90)}
        };
        private _ack = profileNamespace getVariable ["comspec_overwatch_beta_note_ack", false];
        private _cgu = profileNamespace getVariable ["comspec_overwatch_cgu_ack", false];
        if (
            (_ack isEqualTo true) || {_ack isEqualTo 1}
            || {_cgu isEqualTo true} || {_cgu isEqualTo 1}
        ) then {
            private _needSend = !(profileNamespace getVariable ["comspec_overwatch_beta_registered", false]);
            private _steamNow = if (!isNull player) then { getPlayerUID player } else { "" };
            if (!_needSend && {(count _steamNow) >= 15} && {!(profileNamespace getVariable ["comspec_overwatch_beta_has_steam", false])}) then {
                _needSend = true;
            };
            if (_needSend) then {
                [] call comspec_overwatch_connect_fnc_registerBetaClient;
            };
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

    // Inconscient / hors combat → liste PANIC IceMan (tous les téléphones ATAK)
    if (isNil "COMSPEC_IcemanMedicalPanicEH") then {
        COMSPEC_IcemanMedicalPanicEH = ["COMSPEC_IcemanMedicalPanic", {
            (_this + [false]) call comspec_overwatch_connect_fnc_pushIcemanMedicalAlert;
        }] call CBA_fnc_addEventHandler;
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
    if (isNil "COMSPEC_NetworkSimPFH") then {
        COMSPEC_NetworkSimPFH = [{
            [] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;
        }, 5, []] call CBA_fnc_addPerFrameHandler;
    };

    // Roleplay : PFH pour détecter les zones géographiques (pas d'overlay UI ingame)
    if (isNil "COMSPEC_ZoneEffectsPFH") then {
        COMSPEC_ZoneEffectsPFH = [{
            [] call comspec_overwatch_connect_fnc_applyZoneEffects;
        }, 2, []] call CBA_fnc_addPerFrameHandler;
    };

    // Zeus / ACE : première passe tôt (menus ACE Zeus, double-clic, boutons attributs).
    // Zeus Enhanced peut arriver plus tard : une 2e passe attend vraiment ZEN,
    // sinon les catégories COMSPEC restent vides (drapeau « déjà enregistré » trop tôt).
    [{
        [] call comspec_overwatch_connect_fnc_registerZenRoleplayModules;
        [] call comspec_overwatch_connect_fnc_registerZenAtakPlayerActions;
        [] call comspec_overwatch_connect_fnc_registerZenSseModules;
        [] call comspec_overwatch_connect_fnc_registerZenTrackActions;
        [] call comspec_overwatch_connect_fnc_registerZenTheaterSurvey;
        [] call comspec_overwatch_connect_fnc_registerZeusAttributeButtons;
    }, [], 2] call CBA_fnc_waitAndExecute;
    missionNamespace setVariable ["COMSPEC_ZenRegisterDeadline", diag_tickTime + 45, false];
    [{
        !isNil "zen_custom_modules_fnc_register"
        || {diag_tickTime > (missionNamespace getVariable ["COMSPEC_ZenRegisterDeadline", 0])}
    }, {
        [] call comspec_overwatch_connect_fnc_registerZenRoleplayModules;
        [] call comspec_overwatch_connect_fnc_registerZenAtakPlayerActions;
        [] call comspec_overwatch_connect_fnc_registerZenSseModules;
        [] call comspec_overwatch_connect_fnc_registerZenTrackActions;
        [] call comspec_overwatch_connect_fnc_registerZenTheaterSurvey;
    }, [], 60] call CBA_fnc_waitUntilAndExecute;

    // Tampon hors ligne : rejeu des transmissions mises en attente. La boucle est
    // lente à dessein — c'est fn_outboxFlush qui porte la temporisation, ici on ne
    // fait que lui donner l'occasion de regarder si la liaison est revenue.
    private _outboxNow = profileNamespace getVariable ["COMSPEC_Outbox", []];
    if (_outboxNow isEqualType []) then {
        private _outboxKeep = _outboxNow select {
            !((toLower (_x param [0, ""])) in ["syncwardrobe", "syncwardrobesbatch"])
        };
        if ((count _outboxKeep) isNotEqualTo (count _outboxNow)) then {
            profileNamespace setVariable ["COMSPEC_Outbox", _outboxKeep];
            saveProfileNamespace;
        };
    };
    if (isNil "COMSPEC_OutboxFlushPFH") then {
        COMSPEC_OutboxFlushPFH = [{
            [] call comspec_overwatch_connect_fnc_outboxFlush;
        }, 10, []] call CBA_fnc_addPerFrameHandler;
    };

    // Identifiants ATAK visibles côté Zeus
    [{
        [] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;
    }, [], 5] call CBA_fnc_waitAndExecute;
    if (isNil "COMSPEC_AtakPublicVarsPFH") then {
        COMSPEC_AtakPublicVarsPFH = [{
            if (!hasInterface || {isNull player}) exitWith {};
            [] call comspec_overwatch_connect_fnc_syncPlayerAtakPublicVars;
        }, 60, []] call CBA_fnc_addPerFrameHandler;
    };
    
    // Réalisme ATAK : Hit + Explosion sur l’unité (rebranchés au Respawn)
    [] call comspec_overwatch_connect_fnc_attachAtakDamageHandlers;
    [] call comspec_overwatch_connect_fnc_initCombatJournal;

    if (isNil "COMSPEC_AtakDamagePFH") then {
        COMSPEC_AtakDamagePFH = [{
            if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
            [] call comspec_overwatch_connect_fnc_checkAtakDamage;
        }, 10, []] call CBA_fnc_addPerFrameHandler;
    };

    // Actions réparation ATAK (ACE Équipement)
    [{
        [] call comspec_overwatch_connect_fnc_addAtakRepairAction;
    }, [], 9] call CBA_fnc_waitAndExecute;
    
    // Actions tableau de bord relais (ACE/scroll sur objets marqués)
    [{
        [] call comspec_overwatch_connect_fnc_addRelayDashboardActions;
    }, [], 10] call CBA_fnc_waitAndExecute;

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
    missionNamespace setVariable ["COMSPEC_IcemanMedicalPushed", createHashMap, false];
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
};

// Sync multi-clients du briefing Google Slides (URL + index).
if (isNil "COMSPEC_GoogleBriefingStateEH") then {
    COMSPEC_GoogleBriefingStateEH = [
        "COMSPEC_GoogleBriefingState",
        { _this call comspec_overwatch_connect_fnc_handleGoogleBriefingState; }
    ] call CBA_fnc_addEventHandler;
};
