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

    [] call comspec_overwatch_connect_fnc_connect;
    [] call comspec_overwatch_connect_fnc_initACE;

    // Alerte Windows (MessageBox) si compte Athena non lié — une fois / session
    0 spawn {
        // Laisser le monde et l’UI se stabiliser avant le MessageBox bloquant
        uiSleep 2.5;
        [] call comspec_overwatch_connect_fnc_showAthenaLinkHelp;
    };

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
        [false] call comspec_overwatch_connect_fnc_syncCallsignFromAthena;
    };

    // Alerte immédiate dès le passage KO (ACE) — le PFH position couvre aussi FC=0
    if (isNil "COMSPEC_aceUnconsciousEH") then {
        COMSPEC_aceUnconsciousEH = ["ace_unconscious", {
            params ["_unit", "_isUnconscious"];
            if (!local _unit || {_unit != player}) exitWith {};
            if (_isUnconscious) then {
                [_unit] call comspec_overwatch_connect_fnc_checkMedicalAlerts;
            } else {
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

    private _interval = missionNamespace getVariable ["comspec_overwatch_position_interval", 3];
    if (!(_interval isEqualType 0)) then { _interval = 2; };
    _interval = (_interval max 1) min 15;
    [{
        [{ [player] call comspec_overwatch_connect_fnc_updatePosition }, [], "updatePosition"] call comspec_overwatch_connect_fnc_profileWrap;
    }, _interval] call CBA_fnc_addPerFrameHandler;

    // Radio proximité / pastilles « Émet » (ACRE2 / TFAR optionnels)
    [] call comspec_overwatch_connect_fnc_initRadioMonitor;

    // Sync marqueurs carte → Athena (inspiré cTab MarkerCreated/Updated/Deleted)
    if (isNil "COMSPEC_MapMarkerEHs") then {
        COMSPEC_MapMarkerEHs = [
            addMissionEventHandler ["MarkerCreated", {
                params ["_marker"];
                [_marker, false] call comspec_overwatch_connect_fnc_syncMapMarker;
            }],
            addMissionEventHandler ["MarkerUpdated", {
                params ["_marker"];
                [_marker, false] call comspec_overwatch_connect_fnc_syncMapMarker;
            }],
            addMissionEventHandler ["MarkerDeleted", {
                params ["_marker"];
                [_marker, true] call comspec_overwatch_connect_fnc_syncMapMarker;
            }]
        ];
    };

    private _casPollInterval = 10;
    [{
        params ["_args", "_pfhId"];
        [{
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
            if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
            private _raw = ["COMSPECExtension" callExtension ["GetCASForCallsign", [_callsign, "1"]]] call comspec_overwatch_connect_fnc_extResult;
            if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
            private _payload = _raw select [3, count _raw - 3];
            private _lastPayload = missionNamespace getVariable ["COMSPEC_LastCASPayload", ""];
            if (_payload != "" && {_payload != _lastPayload}) then {
                missionNamespace setVariable ["COMSPEC_LastCASPayload", _payload];
                missionNamespace setVariable ["COMSPEC_CAS_Raw", _payload];
                [] call comspec_overwatch_connect_fnc_receiveCASRequest;
                ["COMSPEC_Info", ["Nouvelle demande CAS reçue"]] call comspec_overwatch_connect_fnc_showNotification;
                ["[CAS] Nouvelle demande d’appui aérien reçue.", "cas"] call comspec_overwatch_connect_fnc_appendLinkLog;
            };
        }, [], "casPoll"] call comspec_overwatch_connect_fnc_profileWrap;
    }, _casPollInterval, []] call CBA_fnc_addPerFrameHandler;

    
    [{
        [{
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            [] call comspec_overwatch_connect_fnc_pollMedicalAlerts;
        }, [], "pollMedicalAlerts"] call comspec_overwatch_connect_fnc_profileWrap;
    }, 8, []] call CBA_fnc_addPerFrameHandler;

    [{
        [{
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            [] call comspec_overwatch_connect_fnc_pollOrders;
        }, [], "pollOrders"] call comspec_overwatch_connect_fnc_profileWrap;
    }, 8, []] call CBA_fnc_addPerFrameHandler;
[{
        [{
            if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
            [] call comspec_overwatch_connect_fnc_pollMapShapes;
        }, [], "pollMapShapes"] call comspec_overwatch_connect_fnc_profileWrap;
    }, 10, []] call CBA_fnc_addPerFrameHandler;

    ["OnOrderIssued", {
        params ["_order"];
        private _target = _order getOrDefault ["target", ""];
        if (_target isEqualTo "") exitWith {};

        private _chainLog = missionNamespace getVariable ["COMSPEC_OrderPropagationLog", []];
        _chainLog pushBack [
            serverTime,
            _order getOrDefault ["id", ""],
            "COMMANDER",
            "SQUAD_LEADER",
            _target
        ];
        _chainLog pushBack [
            serverTime,
            _order getOrDefault ["id", ""],
            "SQUAD_LEADER",
            "FIRETEAM",
            _target
        ];
        missionNamespace setVariable ["COMSPEC_OrderPropagationLog", _chainLog, true];

        // Réception joueur (local + clients via remoteExec côté issueOrder)
        [_order] call comspec_overwatch_connect_fnc_receiveOrder;
    }] call comspec_overwatch_connect_fnc_registerEventHandler;

    // Filet de sécurité : nouveaux ordres synchronisés via missionNamespace
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        private _orders = missionNamespace getVariable ["COMSPEC_Orders", []];
        private _seen = missionNamespace getVariable ["COMSPEC_OrdersSeen", []];
        {
            if (!(_x isEqualType createHashMap)) then { continue };
            private _id = _x getOrDefault ["id", ""];
            if (_id isEqualTo "" || {_id in _seen}) then { continue };
            [_x] call comspec_overwatch_connect_fnc_receiveOrder;
        } forEach _orders;
    }, 5, []] call CBA_fnc_addPerFrameHandler;

    ["OnTrackingAnomaly", {
        params ["_alert"];
        private _kind = _alert getOrDefault ["kind", "ANOMALY"];
        [format ["Anomalie détectée : %1", _kind], "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    }] call comspec_overwatch_connect_fnc_registerEventHandler;

    [] spawn comspec_overwatch_connect_fnc_playtimeTracker;

    // Roleplay : PFH pour simuler les déconnexions réseau aléatoires
    [{
        [] call comspec_overwatch_connect_fnc_simulateNetworkDisconnect;
    }, 5, []] call CBA_fnc_addPerFrameHandler; // Vérifier toutes les 5 secondes

    // Déconnexion ATAK à la sortie mission / quit Arma (sync extension, timeout court).
    // Réinitialiser à chaque mission (missionNamespace survit au changement de mission).
    missionNamespace setVariable ["COMSPEC_DisconnectSent", false, false];
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
