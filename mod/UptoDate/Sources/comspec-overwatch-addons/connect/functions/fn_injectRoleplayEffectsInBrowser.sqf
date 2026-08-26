/*
    Injecte des effets visuels roleplay dans le navigateur cTab/tablette.
    Appelé périodiquement pour mettre à jour l'affichage ATAK in-game.
*/

if (!hasInterface) exitWith {};

// Récupérer le display du navigateur
private _display = uiNamespace getVariable ["RscCustomInfoMiniMap", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["COMSPEC_WebBrowser", displayNull];
};

if (isNull _display) exitWith {};

// Récupérer l'état réseau actuel
private _packetLossStats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _disconnectInfo = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
private _zoneInfo = [] call comspec_overwatch_connect_fnc_getPlayerRoleplayZone;

private _isDisconnected = _disconnectInfo get "is_disconnected";
private _packetLoss = _packetLossStats get "packet_loss_percent";

// Construire le code JavaScript à injecter
private _jsCode = "";

// 1. Mettre à jour l'état de déconnexion
if (_isDisconnected) then {
    private _remaining = _disconnectInfo get "remaining_seconds";
    _jsCode = _jsCode + format [
        "if (window.AtakRoleplayEffects) { " +
        "  AtakRoleplayEffects.showConnectionError('Liaison perdue', 'Reconnexion dans %1 s'); " +
        "}",
        _remaining
    ];
} else {
    // Retirer l'overlay de déconnexion
    _jsCode = _jsCode + "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.hideConnectionError(); }";
};

// 2. Appliquer les effets selon packet loss
if (_packetLoss > 5) then {
    private _intensity = (_packetLoss / 100) min 0.4;
    _jsCode = _jsCode + format [
        "if (window.AtakRoleplayEffects) { " +
        "  AtakRoleplayEffects.applyGlitchEffect(%1); " +
        "  AtakRoleplayEffects.applyMapInterference(%1); " +
        "}",
        _intensity
    ];
};

// 3. Mettre à jour l'indicateur de zone
if (!isNil "_zoneInfo") then {
    private _zoneName = _zoneInfo get "name";
    private _intensity = _zoneInfo get "intensity";
    
    _jsCode = _jsCode + format [
        "if (window.AtakRoleplayEffects) { " +
        "  AtakRoleplayEffects.showZoneWarning('%1', %2); " +
        "}",
        _zoneName,
        _intensity
    ];
} else {
    _jsCode = _jsCode + "if (window.AtakRoleplayEffects) { AtakRoleplayEffects.hideZoneWarning(); }";
};

// 4. Mettre à jour les indicateurs de qualité
private _sent = _packetLossStats get "packets_sent_window";
private _received = _packetLossStats get "packets_received_window";

_jsCode = _jsCode + format [
    "if (window.AtakRoleplayEffects) { " +
    "  AtakRoleplayEffects.updateNetworkQualityIndicators(null, null, %1); " +
    "}",
    _packetLoss
];

// Exécuter le JavaScript dans le navigateur
if (_jsCode != "") then {
    // Via l'API webBrowser si disponible
    private _browser = _display displayCtrl 1100; // IDC du navigateur
    if (!isNull _browser) then {
        // Note : htmlLoad n'exécute pas de JS, il faut utiliser un callback
        // On stocke le code pour l'exécuter côté web via polling
        missionNamespace setVariable ["COMSPEC_RoleplayJS", _jsCode, false];
    };
};
