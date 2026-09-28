/*
    Surveiller un réseau radio (relais net, pas audio 3D monde).
    Params : [_channel, _radioId]
    - ACRE spectateur : addSpectatorRadio(radioId) — écoute passive.
    - ACRE joueur vivant : bascule temporairement le canal, en mémorisant
      le canal d’origine pour restauration via unmonitorRadioNet.
    - TFAR : mémorise l’intention (réglage manuel de fréquence).
    Retourne true si une action a été tentée.
*/
params [["_channel", ""], ["_radioId", ""]];

if (!hasInterface) exitWith { false };

// Canal vide / stop → restauration
if (
    (_channel isEqualTo "")
    || {(toLower (str _channel)) in ["stop", "off", "unmonitor", "-1"]}
) exitWith {
    [] call comspec_overwatch_connect_fnc_unmonitorRadioNet
};

private _ok = false;

if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
    private _isSpec = false;
    if (!isNil "acre_api_fnc_isSpectator") then {
        _isSpec = [] call acre_api_fnc_isSpectator;
    };

    // Mode spectateur : écoute passive d’une radio précise (API = radioId seul)
    if (_isSpec && {_radioId isEqualType ""} && {_radioId != ""} && {!isNil "acre_api_fnc_addSpectatorRadio"}) then {
        if (!(missionNamespace getVariable ["COMSPEC_RadioMonitorActive", false])) then {
            missionNamespace setVariable ["COMSPEC_RadioMonitorPrevSpectatorRadioId", "", false];
        };
        [_radioId] call acre_api_fnc_addSpectatorRadio;
        missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", str _channel, false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorRadioId", _radioId, false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorMode", "spectator", false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorActive", true, false];
        ["COMSPEC_Info", ["À l’écoute du réseau radio (mode observation)"]] call comspec_overwatch_connect_fnc_showNotification;
        _ok = true;
    } else {
        // Joueur vivant : rejoindre le canal (écoute = même réseau) — avec restauration
        private _chNum = -1;
        if (_channel isEqualType 0) then {
            _chNum = _channel;
        } else {
            if ((str _channel) != "") then { _chNum = parseNumber (str _channel); };
        };
        if (_chNum >= 0 && {!isNil "acre_api_fnc_setCurrentRadioChannelNumber"}) then {
            // Ne pas écraser le canal d’origine si on re-surveille déjà
            if (!(missionNamespace getVariable ["COMSPEC_RadioMonitorActive", false])) then {
                private _prev = -1;
                if (!isNil "acre_api_fnc_getCurrentRadioChannelNumber") then {
                    _prev = [] call acre_api_fnc_getCurrentRadioChannelNumber;
                    if (isNil "_prev" || {!(_prev isEqualType 0)}) then { _prev = -1; };
                };
                missionNamespace setVariable ["COMSPEC_RadioMonitorPrevChannel", _prev, false];
            };
            [_chNum] call acre_api_fnc_setCurrentRadioChannelNumber;
            missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", str _chNum, false];
            missionNamespace setVariable ["COMSPEC_RadioMonitorRadioId", _radioId, false];
            missionNamespace setVariable ["COMSPEC_RadioMonitorMode", "channel", false];
            missionNamespace setVariable ["COMSPEC_RadioMonitorActive", true, false];
            ["COMSPEC_Info", [format ["Réseau radio surveillé — canal %1 (votre canal d’origine sera restauré à l’arrêt)", _chNum]]] call comspec_overwatch_connect_fnc_showNotification;
            _ok = true;
        } else {
            ["COMSPEC_Warning", ["Impossible de basculer sur ce réseau radio"]] call comspec_overwatch_connect_fnc_showNotification;
        };
    };
} else {
    if (isClass (configFile >> "CfgPatches" >> "tfar_core")) then {
        missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", str _channel, false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorMode", "tfar", false];
        missionNamespace setVariable ["COMSPEC_RadioMonitorActive", true, false];
        ["COMSPEC_Info", ["Surveillance enregistrée — réglez la fréquence sur votre radio"]] call comspec_overwatch_connect_fnc_showNotification;
        _ok = true;
    } else {
        ["COMSPEC_Warning", ["Module radio non détecté"]] call comspec_overwatch_connect_fnc_showNotification;
    };
};

_ok
