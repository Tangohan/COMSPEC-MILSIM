/*
    Surveiller un réseau radio (relais net, pas audio 3D monde).
    Params : [_channel, _radioId]
    - ACRE : bascule le canal de la radio active locale (écoute + TX sur ce réseau).
      Si le joueur est déjà spectateur ACRE et qu’un radioId est fourni → addSpectatorRadio.
    - TFAR : bascule la fréquence SW si possible (best-effort).
    Retourne true si une action a été tentée.
*/
params [["_channel", ""], ["_radioId", ""]];

if (!hasInterface) exitWith { false };

private _ok = false;

if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
    private _isSpec = false;
    if (!isNil "acre_api_fnc_isSpectator") then {
        _isSpec = [] call acre_api_fnc_isSpectator;
    };

    // Mode spectateur : écoute passive d’une radio précise
    if (_isSpec && {_radioId != ""} && {!isNil "acre_api_fnc_addSpectatorRadio"}) then {
        [player, _radioId] call acre_api_fnc_addSpectatorRadio;
        missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", _channel, false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorRadioId", _radioId, false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorActive", true, false];
        ["COMSPEC_Info", ["À l’écoute du réseau radio (mode observation)"]] call comspec_overwatch_connect_fnc_showNotification;
        _ok = true;
    } else {
        // Joueur vivant : rejoindre le canal (écoute = même réseau)
        private _chNum = -1;
        if (_channel isEqualType 0) then {
            _chNum = _channel;
        } else {
            if (_channel != "") then { _chNum = parseNumber _channel; };
        };
        if (_chNum >= 0 && {!isNil "acre_api_fnc_setCurrentRadioChannelNumber"}) then {
            [_chNum] call acre_api_fnc_setCurrentRadioChannelNumber;
            missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", str _chNum, false];
            missionNamespace setVariable ["COMSPEC_RadioMonitorRadioId", _radioId, false];
            missionNamespace setVariable ["COMSPEC_RadioMonitorActive", true, false];
            ["COMSPEC_Info", [format ["Réseau radio surveillé — canal %1", _chNum]]] call comspec_overwatch_connect_fnc_showNotification;
            _ok = true;
        } else {
            ["COMSPEC_Warning", ["Unable to switch to this radio network"]] call comspec_overwatch_connect_fnc_showNotification;
        };
    };
} else {
    if (isClass (configFile >> "CfgPatches" >> "tfar_core")) then {
        // Best-effort TFAR : mémoriser l’intention ; la freq exacte dépend du radio actif
        missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", str _channel, false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorActive", true, false];
        ["COMSPEC_Info", ["Network monitoring registered — set frequency on your radio"]] call comspec_overwatch_connect_fnc_showNotification;
        _ok = true;
    } else {
        ["COMSPEC_Warning", ["Radio module not detected"]] call comspec_overwatch_connect_fnc_showNotification;
    };
};

_ok
