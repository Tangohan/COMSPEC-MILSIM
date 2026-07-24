/*
    Met à jour l'overlay roleplay ingame.
    Appelé par PFH toutes les 0.5 secondes.
*/

// Vérifier activation
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false])) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false])) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_RoleplayOverlay", displayNull];
if (isNull _display) exitWith {};

// Récupérer les stats
private _packetLossStats = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _disconnectInfo = [] call comspec_overwatch_connect_fnc_getNetworkDisconnectInfo;
private _isDisconnected = _disconnectInfo get "is_disconnected";

// === GESTION DE LA DÉCONNEXION ===
// Note : pas de voile noir, juste le message informatif
private _disconnectOverlay = _display displayCtrl 16803;
private _disconnectMsg = _display displayCtrl 16811;
private _disconnectProgress = _display displayCtrl 16813;

if (_isDisconnected) then {
    // PAS d'overlay qui bloque la vue
    _disconnectOverlay ctrlShow false;
    
    // Message de déconnexion (sans fond opaque)
    private _remaining = _disconnectInfo get "remaining_seconds";
    private _msgText = format [
        "<t align='center' size='1.2' color='#ff4444' shadow='2'>⚠ LIAISON ATAK PERDUE ⚠</t><br/>" +
        "<t align='center' size='0.9' color='#ffffff' shadow='2'>Reconnexion dans <t color='#ff8888'>%1s</t></t><br/>" +
        "<t align='center' size='0.7' color='#aaaaaa' shadow='2'>Aucune donnée transmise</t>",
        _remaining
    ];
    _disconnectMsg ctrlSetStructuredText parseText _msgText;
    _disconnectMsg ctrlSetBackgroundColor [0.1, 0.1, 0.1, 0.8]; // Fond discret
    _disconnectMsg ctrlShow true;
    
    // Barre de progression (inversée)
    private _disconnectUntil = _disconnectInfo get "disconnect_until";
    private _startTime = _disconnectUntil - _remaining;
    private _totalDuration = _disconnectUntil - _startTime;
    private _progress = 1 - (_remaining / _totalDuration);
    _disconnectProgress progressSetPosition _progress;
    _disconnectProgress ctrlShow true;
} else {
    // Cacher overlay de déconnexion
    _disconnectOverlay ctrlShow false;
    _disconnectMsg ctrlShow false;
    _disconnectProgress ctrlShow false;
};

// === INDICATEUR DE QUALITÉ RÉSEAU ===
private _networkQuality = _display displayCtrl 16810;
if (!_isDisconnected) then {
    private _packetLoss = _packetLossStats get "packet_loss_percent";
    private _sent = _packetLossStats get "packets_sent_window";
    private _received = _packetLossStats get "packets_received_window";
    
    // Déterminer la couleur selon le taux de perte
    private _color = "#00ff00"; // Vert
    private _status = "EXCELLENTE";
    
    if (_packetLoss > 1) then {
        _color = "#88ff00";
        _status = "BONNE";
    };
    if (_packetLoss > 3) then {
        _color = "#ffff00";
        _status = "ACCEPTABLE";
    };
    if (_packetLoss > 5) then {
        _color = "#ffaa00";
        _status = "DÉGRADÉE";
    };
    if (_packetLoss > 10) then {
        _color = "#ff4444";
        _status = "CRITIQUE";
    };
    
    private _qualityText = format [
        "<t align='right' size='0.7' color='#aaaaaa'>LIAISON ATAK</t><br/>" +
        "<t align='right' size='0.9' color='%1'>%2</t><br/>" +
        "<t align='right' size='0.6' color='#ffffff'>Pertes: <t color='%1'>%3%%</t></t><br/>" +
        "<t align='right' size='0.6' color='#888888'>%4/%5 reçus</t>",
        _color, _status, (_packetLoss toFixed 1), _received, _sent
    ];
    
    _networkQuality ctrlSetStructuredText parseText _qualityText;
    _networkQuality ctrlShow true;
} else {
    _networkQuality ctrlShow false;
};

// === INDICATEUR DE PACKET LOSS (bas écran) ===
private _packetLossInd = _display displayCtrl 16812;
if (!_isDisconnected) then {
    private _packetLoss = _packetLossStats get "packet_loss_percent";
    
    // Afficher seulement si perte > 5%
    if (_packetLoss > 5) then {
        private _msgText = format [
            "<t align='center' size='0.9' color='#ff8888'>⚠ Qualité de liaison dégradée</t><br/>" +
            "<t align='center' size='0.7' color='#ffffff'>%1%% de paquets perdus</t>",
            (_packetLoss toFixed 1)
        ];
        _packetLossInd ctrlSetStructuredText parseText _msgText;
        _packetLossInd ctrlShow true;
    } else {
        _packetLossInd ctrlShow false;
    };
} else {
    _packetLossInd ctrlShow false;
};

// === EFFETS VISUELS ===
// DÉSACTIVÉS : Pas de parasites, pas de flash, pas de filtre sur la vue
// Le joueur garde une vision claire en permanence
private _scanLines = _display displayCtrl 16801;
private _glitchOverlay = _display displayCtrl 16802;

_scanLines ctrlShow false;
_glitchOverlay ctrlShow false;
