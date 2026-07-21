if (!hasInterface) exitWith {};

// Warmup extension (charge la DLL)
"COMSPECExtension" callExtension "Warmup";

["CBA_settingsInitialized", {
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

    [] call comspec_overwatch_connect_fnc_connect;
    [] call comspec_overwatch_connect_fnc_initACE;

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

    // Action "Tableau de briefing" : disponible par défaut sur le joueur, sans placement Eden requis.
    // Limite connue : comme le reste de ce postInit, l'action est ajoutée à l'objet joueur courant
    // et ne suit pas automatiquement un respawn (objet joueur recréé) — à ré-ajouter via un handler
    // MPRespawn côté mission si besoin. Pour un vrai écran/tableau posé dans Eden, voir le
    // commentaire d'en-tête de fn_openBriefingBoard.sqf (this addAction sur un objet nommé).
    player addAction [
        "<t color='#7fffd4'>Tableau de briefing</t>",
        { [] call comspec_overwatch_connect_fnc_openBriefingBoard; },
        nil, 6, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    // Action "Connecter mon téléphone" : QR + code court pour consulter le briefing en cours
    // depuis un navigateur mobile (voir aussi le bouton dans le dialog Tableau de briefing).
    player addAction [
        "<t color='#7fffd4'>Connecter mon téléphone</t>",
        { [] call comspec_overwatch_connect_fnc_phoneConnectShow; },
        nil, 5.9, false, true, "",
        "missionNamespace getVariable ['comspec_overwatch_enabled', true]"
    ];

    private _interval = missionNamespace getVariable ["comspec_overwatch_position_interval", 0.25];
    [{ [player] call comspec_overwatch_connect_fnc_updatePosition }, _interval] call CBA_fnc_addPerFrameHandler;

    // CAS polling: every 10s check for CAS assigned to this callsign
    private _casPollInterval = 10;
    [{
        params ["_args", "_pfhId"];
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        private _callsign = missionNamespace getVariable ["COMSPEC_Callsign", name player];
        if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
        private _raw = ["COMSPECExtension" callExtension ["GetCASForCallsign", [_callsign, "1"]]] call comspec_overwatch_connect_fnc_extResult;
        if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
        private _payload = _raw select [3, count _raw - 3];
        private _lastPayload = missionNamespace getVariable ["COMSPEC_LastCASPayload", ""];
        if (_payload != "" && {_payload != _lastPayload}) then {
            missionNamespace setVariable ["COMSPEC_LastCASPayload", _payload];
            missionNamespace setVariable ["COMSPEC_CAS_Raw", _payload];
            [] call comspec_overwatch_connect_fnc_receiveCASRequest;
            ["COMSPEC_Info", ["Nouvelle demande CAS reçue"]] call BIS_fnc_showNotification;
            ["[CAS] Nouvelle demande d’appui aérien reçue.", "cas"] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    }, _casPollInterval, []] call CBA_fnc_addPerFrameHandler;

    // Map shapes polling: every 10s fetch shapes and update local markers
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollMapShapes;
    }, 10, []] call CBA_fnc_addPerFrameHandler;


    // Event bus wiring C2: propagation hiérarchique simple (Commandant -> Squad -> Fireteam)
    ["OnOrderIssued", {
        params ["_order"];
        private _target = _order getOrDefault ["target", ""];
        if (_target isEqualTo "") exitWith {};

        // Simule la propagation en log local, consommable par UI/replay
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
    }] call comspec_overwatch_connect_fnc_registerEventHandler;

    ["OnTrackingAnomaly", {
        params ["_alert"];
        private _kind = _alert getOrDefault ["kind", "ANOMALY"];
        systemChat format ["[COMSPEC][TRACK] %1 détectée.", _kind];
    }] call comspec_overwatch_connect_fnc_registerEventHandler;

    [] spawn comspec_overwatch_connect_fnc_playtimeTracker;
}] call CBA_fnc_addEventHandler;
