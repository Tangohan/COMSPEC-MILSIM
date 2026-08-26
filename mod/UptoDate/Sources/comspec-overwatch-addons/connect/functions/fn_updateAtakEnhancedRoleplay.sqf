/*
    Met à jour les effets roleplay dans l'ATAK Enhanced (display Hub).
    Appelé périodiquement pour afficher l'état réseau.
*/

if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_Hub_Display", displayNull];
if (isNull _display) exitWith {};

// Vérifier si roleplay activé
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith {};

// Récupérer les stats
private _packetLossStats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _disconnectInfo = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
private _zoneInfo = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;
private _atakStatus = [] call comspec_overwatch_connect_fnc_isAtakFunctional;

private _isCrashed = _atakStatus getOrDefault ["device_crashed", false];
private _isDisconnected = _disconnectInfo get "is_disconnected";
private _packetLoss = _packetLossStats get "packet_loss_percent";
private _zoneOut = false;
private _zoneName = "";
if (!isNil "_zoneInfo" && {_zoneInfo isEqualType createHashMap}) then {
    _zoneName = _zoneInfo getOrDefault ["name", ""];
    _zoneOut = (_zoneInfo getOrDefault ["type", ""]) isEqualTo "no_coverage"
        || {(missionNamespace getVariable ["COMSPEC_ZoneEffects", createHashMap]) getOrDefault ["force_disconnect", false]};
};

// Variables d'état persistantes (pour détecter changements et jouer sons)
private _wasDisconnected = missionNamespace getVariable ["COMSPEC_Roleplay_WasDisconnected", false];
private _wasInZone = missionNamespace getVariable ["COMSPEC_Roleplay_WasInZone", false];
private _wasScreenBroken = missionNamespace getVariable ["COMSPEC_Roleplay_WasScreenBroken", false];
private _wasCrashed = missionNamespace getVariable ["COMSPEC_Roleplay_WasCrashed", false];

// IDC des contrôles roleplay
private _ctrlDisconnect = _display displayCtrl 9200;
private _ctrlZoneWarning = _display displayCtrl 9201;
private _ctrlPacketLoss = _display displayCtrl 9202;
private _ctrlScreenBroken = _display displayCtrl 9203;
private _ctrlGlitch = _display displayCtrl 9204;

// === GEL APPAREIL (crash ATAK) ===
if (_isCrashed && {!isNull _ctrlScreenBroken}) then {
    if (!_wasCrashed) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasCrashed", true];
    };
    _ctrlScreenBroken ctrlSetStructuredText parseText (
        "<t align='center' size='1.4' color='#cccccc'>TERMINAL BLOQUÉ</t><br/>" +
        "<t align='center' size='0.9' color='#888888'>Redémarrage automatique en cours…</t>"
    );
    _ctrlScreenBroken ctrlShow true;
    {
        private _ctrl = _display displayCtrl _x;
        if (!isNull _ctrl) then { _ctrl ctrlShow false; };
    } forEach [9101, 9102, 9103, 9104, 9105, 9106, 9107, 9108];
} else {
    if (_wasCrashed) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasCrashed", false];
    };
};

if (_isCrashed) exitWith {};

// === ÉCRAN CASSÉ/ÉTEINT ===
if (!(_atakStatus get "can_display")) then {
    if (!_wasScreenBroken) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasScreenBroken", true];
    };
    if (!isNull _ctrlScreenBroken) then {
        if (!(_atakStatus get "powered_on")) then {
            _ctrlScreenBroken ctrlSetBackgroundColor [0.02, 0.02, 0.02, 0.88];
            _ctrlScreenBroken ctrlSetStructuredText parseText format [
                "<t align='center' size='2' color='#666666'>ATAK ÉTEINT</t><br/>" +
                "<t align='center' size='1' color='#888888'>ACE Self Interact → Rallumer</t>"
            ];
        } else {
            // Cracks transparents (atak-fx) — laisse la carte / position lisible.
            private _fx = uiNamespace getVariable ["COMSPEC_Hub_ScreenBrokenFx", controlNull];
            private _brokenTex = "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.paa";
            if (!(fileExists _brokenTex)) then {
                _brokenTex = "\z\comspec_overwatch\addons\connect\img\atak-fx\broken-screen.png";
            };
            if (isNull _fx || {ctrlParent _fx != _display}) then {
                _fx = _display ctrlCreate ["RscPicture", 9205];
                uiNamespace setVariable ["COMSPEC_Hub_ScreenBrokenFx", _fx];
                _fx ctrlSetPosition (ctrlPosition _ctrlScreenBroken);
                _fx ctrlCommit 0;
            };
            _fx ctrlSetText _brokenTex;
            _fx ctrlSetFade 0;
            _fx ctrlShow true;
            _fx ctrlEnable false;
            _fx ctrlCommit 0;
            _ctrlScreenBroken ctrlSetBackgroundColor [0.02, 0.02, 0.02, 0.08];
            _ctrlScreenBroken ctrlSetStructuredText parseText format [
                "<t align='center' size='0.95' color='#F4F7FA'>ÉCRAN ENDOMMAGÉ</t><br/>" +
                "<t align='center' size='0.72' color='#C9D4DC'>Position seule — réparez avec un toolkit</t>"
            ];
        };
        _ctrlScreenBroken ctrlShow true;
    };
    
    // Cacher le reste de l'interface
    {
        private _ctrl = _display displayCtrl _x;
        if (!isNull _ctrl) then { _ctrl ctrlShow false; };
    } forEach [9101, 9102, 9103, 9104, 9105, 9106, 9107, 9108];
} else {
    // Ecran fonctionnel, reinitialiser etat
    if (_wasScreenBroken) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasScreenBroken", false];
    };
    
    if (!isNull _ctrlScreenBroken) then {
        _ctrlScreenBroken ctrlShow false;
    };
    private _fxHide = uiNamespace getVariable ["COMSPEC_Hub_ScreenBrokenFx", controlNull];
    if (!isNull _fxHide) then { _fxHide ctrlShow false; };
    
    {
        private _ctrl = _display displayCtrl _x;
        if (!isNull _ctrl) then { _ctrl ctrlShow true; };
    } forEach [9101, 9102, 9103, 9104, 9105, 9106, 9107, 9108];
};

// Ecran casse / eteint : ne pas continuer les overlays roleplay
if (!(_atakStatus get "can_display")) exitWith {};

// === DECONNEXION ===
// Overlay unique : fn_updateDeviceOverlay (panneau C2, sans ancien habillage).
if (!isNull _ctrlDisconnect) then {
    _ctrlDisconnect ctrlShow false;
};
private _fxDiscHide = uiNamespace getVariable ["COMSPEC_Hub_DisconnectFx", controlNull];
if (!isNull _fxDiscHide) then { _fxDiscHide ctrlShow false; };
if (_isDisconnected || _zoneOut) then {
    if (!_wasDisconnected) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasDisconnected", true];
    };
} else {
    if (_wasDisconnected) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasDisconnected", false];
    };
};

// === AVERTISSEMENT ZONE ===
if (!isNil "_zoneInfo" && {!isNull _ctrlZoneWarning}) then {
    if (!_wasInZone) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasInZone", true];
    };
    _zoneName = _zoneInfo get "name";
    private _intensity = _zoneInfo get "intensity";
    private _color = switch (_zoneInfo get "type") do {
        case "no_coverage": { "#ff4444" };
        case "interference": { "#ffaa00" };
        case "degraded": { "#ffff00" };
        case "jammer": { "#ff88ff" };
        default { "#ffffff" };
    };
    
    _ctrlZoneWarning ctrlSetStructuredText parseText format [
        "<t size='0.8' color='%1'>📡 %2</t><br/>" +
        "<t size='0.6' color='#ffffff'>Intensité: %3%%</t>",
        _color, _zoneName, _intensity
    ];
    _ctrlZoneWarning ctrlShow true;
} else {
    // Sortie de zone
    if (_wasInZone) then {
        missionNamespace setVariable ["COMSPEC_Roleplay_WasInZone", false];
    };
    
    if (!isNull _ctrlZoneWarning) then {
        _ctrlZoneWarning ctrlShow false;
    };
};

// === INDICATEUR PACKET LOSS ===
if (_packetLoss > 5 && {!isNull _ctrlPacketLoss}) then {
    private _color = if (_packetLoss > 10) then {"#ff4444"} else {"#ffaa00"};
    _ctrlPacketLoss ctrlSetStructuredText parseText format [
        "<t align='center' size='0.7' color='%1'>⚠ Pertes: %2%%</t>",
        _color, (_packetLoss toFixed 1)
    ];
    _ctrlPacketLoss ctrlShow true;
} else {
    if (!isNull _ctrlPacketLoss) then {
        _ctrlPacketLoss ctrlShow false;
    };
};

// === EFFET GLITCH ===
if (_packetLoss > 10 && {!isNull _ctrlGlitch} && {random 100 < 10}) then {
    // Flash glitch aléatoire
    _ctrlGlitch ctrlShow true;
    _ctrlGlitch ctrlSetBackgroundColor [0.8, 0, 0, 0.2];
    
    [{
        params ["_ctrl"];
        if (!isNull _ctrl) then {
            _ctrl ctrlShow false;
        };
    }, [_ctrlGlitch], 0.1] call CBA_fnc_waitAndExecute;
};
