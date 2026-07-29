/*
    Vérifie si une transmission vers Athena est autorisée.
    Params: [_requireFull] — true = exige une liaison complète (pas écran cassé / gel appareil)
    Returns: HashMap — can_transmit, mode ("full"|"position_only"|"none"), reason, link_state
*/
params [["_requireFull", false, [false]]];

private _result = createHashMap;
_result set ["can_transmit", true];
_result set ["mode", "full"];
_result set ["reason", ""];
_result set ["link_state", missionNamespace getVariable ["COMSPEC_LinkState", "linked"]];

private _atak = [] call comspec_overwatch_connect_fnc_isAtakFunctional;

// Gel / redémarrage appareil (distinct déconnexion réseau)
if (_atak getOrDefault ["device_crashed", false]) exitWith {
    _result set ["can_transmit", false];
    _result set ["mode", "none"];
    _result set ["reason", "device_crash"];
    _result set ["link_state", "offline"];
    _result
};

// Appareil détruit
if !(_atak getOrDefault ["connection_ok", true]) exitWith {
    _result set ["can_transmit", false];
    _result set ["mode", "none"];
    _result set ["reason", "device_destroyed"];
    _result set ["link_state", "offline"];
    _result
};

// Déconnexion réseau simulée
if ([] call comspec_overwatch_connect_fnc_isNetworkDisconnected) exitWith {
    _result set ["can_transmit", false];
    _result set ["mode", "none"];
    _result set ["reason", "network_disconnect"];
    _result set ["link_state", "offline"];
    _result
};

// Zone sans couverture / brouillage actif
private _zoneFx = missionNamespace getVariable ["COMSPEC_ZoneEffects", nil];
if (!isNil "_zoneFx" && {_zoneFx isEqualType createHashMap}) then {
    if (_zoneFx getOrDefault ["force_disconnect", false]) then {
        _result set ["can_transmit", false];
        _result set ["mode", "none"];
        _result set ["reason", "zone_jammed"];
        _result set ["link_state", "offline"];
    } else {
        private _drop = _zoneFx getOrDefault ["tx_drop_chance", 0];
        if (_drop isEqualType 0 && {_drop > 0} && {random 100 < _drop}) then {
            _result set ["can_transmit", false];
            _result set ["mode", "none"];
            _result set ["reason", "zone_packet_loss"];
            _result set ["link_state", "degraded"];
        };
    };
};
if !(_result getOrDefault ["can_transmit", true]) exitWith { _result };

// Écran endommagé : position seule (GPS / BFT) — avant le test « éteint » car l’historique
// couplait écran cassé + powered_off=false, ce qui masquait l’opérateur du web.
if !(_atak getOrDefault ["screen_ok", true]) && {_atak getOrDefault ["connection_ok", true]} exitWith {
    if (_requireFull) then {
        _result set ["can_transmit", false];
    } else {
        _result set ["can_transmit", true];
    };
    _result set ["mode", "position_only"];
    _result set ["reason", "screen_destroyed"];
    _result set ["link_state", [] call comspec_overwatch_connect_fnc_refreshLinkState];
    _result
};

// ATAK éteint volontairement
if !(_atak getOrDefault ["powered_on", true]) exitWith {
    _result set ["can_transmit", false];
    _result set ["mode", "none"];
    _result set ["reason", "powered_off"];
    _result set ["link_state", "offline"];
    _result
};

private _refreshed = [] call comspec_overwatch_connect_fnc_refreshLinkState;
_result set ["link_state", _refreshed];

_result
