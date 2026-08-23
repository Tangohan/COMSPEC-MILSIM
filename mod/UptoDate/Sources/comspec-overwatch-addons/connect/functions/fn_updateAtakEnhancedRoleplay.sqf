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
        ["crash"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
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
    // Son si changement d'état
    if (!_wasScreenBroken) then {
        ["screen_broken"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
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
            _ctrlScreenBroken ctrlSetBackgroundColor [0.02, 0.02, 0.02, 0.16];
            _ctrlScreenBroken ctrlSetStructuredText parseText format [
                "<br/><br/><t align='center' size='1.5' color='#ff4444'>ÉCRAN ENDOMMAGÉ</t><br/>" +
                "<t align='center' size='0.9' color='#ffffff'>Position seule — connexion maintenue</t><br/>" +
                "<t align='center' size='0.8' color='#aaaaaa'>Toolkit ACE pour réparer</t>"
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
if (_isDisconnected && {!isNull _ctrlDisconnect}) then {
    // Son si nouvelle déconnexion
    if (!_wasDisconnected) then {
        ["disconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        missionNamespace setVariable ["COMSPEC_Roleplay_WasDisconnected", true];
    };
    private _remaining = _disconnectInfo get "remaining_seconds";
    private _fxDisc = uiNamespace getVariable ["COMSPEC_Hub_DisconnectFx", controlNull];
    private _noSig = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.paa";
    if (!(fileExists _noSig)) then {
        _noSig = "\z\comspec_overwatch\addons\connect\img\overlays\comspec_overlay_no_signal_ca.png";
    };
    if (isNull _fxDisc || {ctrlParent _fxDisc isNotEqualTo _display}) then {
        ctrlDelete _fxDisc;
        _fxDisc = _display ctrlCreate ["RscPicture", 9206];
        uiNamespace setVariable ["COMSPEC_Hub_DisconnectFx", _fxDisc];
    };
    if (!isNull _fxDisc) then {
        _fxDisc ctrlSetPosition (ctrlPosition _ctrlDisconnect);
        _fxDisc ctrlSetText _noSig;
        _fxDisc ctrlSetFade 0;
        _fxDisc ctrlEnable false;
        _fxDisc ctrlShow true;
        _fxDisc ctrlCommit 0;
    };
    _ctrlDisconnect ctrlSetBackgroundColor [0.02, 0.05, 0.08, 0.08];
    _ctrlDisconnect ctrlSetStructuredText parseText format [
        "<t align='center' size='1.2' color='#ff4444'>⚠ LIAISON ATAK PERDUE ⚠</t><br/>" +
        "<t align='center' size='0.9' color='#ffffff'>Reconnexion dans <t color='#ff8888'>%1s</t></t><br/>" +
        "<t align='center' size='0.7' color='#aaaaaa'>Aucune donnée transmise</t>",
        _remaining
    ];
    _ctrlDisconnect ctrlShow true;
} else {
    // Reconnexion
    if (_wasDisconnected) then {
        ["reconnect"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        missionNamespace setVariable ["COMSPEC_Roleplay_WasDisconnected", false];
    };
    
    if (!isNull _ctrlDisconnect) then {
        _ctrlDisconnect ctrlShow false;
    };
    private _fxDiscHide = uiNamespace getVariable ["COMSPEC_Hub_DisconnectFx", controlNull];
    if (!isNull _fxDiscHide) then { _fxDiscHide ctrlShow false; };
};

// === AVERTISSEMENT ZONE ===
if (!isNil "_zoneInfo" && {!isNull _ctrlZoneWarning}) then {
    // Son si entrée dans une zone
    if (!_wasInZone) then {
        ["zone_alert"] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
        missionNamespace setVariable ["COMSPEC_Roleplay_WasInZone", true];
    };
    private _zoneName = _zoneInfo get "name";
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
    if (_packetLoss > 8 && {missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", true]}) then {
        private _pp = linearConversion [8, 45, _packetLoss, 0.22, 1, true];
        [_pp, 2.5, false] call comspec_overwatch_connect_fnc_applyRoleplayPpEffects;
    };
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
