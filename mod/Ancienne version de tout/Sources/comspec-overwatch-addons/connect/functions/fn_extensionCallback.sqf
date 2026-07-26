/*
    Callbacks async de COMSPECExtension (RVExtensionRegisterCallback).
    name = "comspec", function = Connected | Error | Debug
    Note : Connect synchrone (1.11+) valide deja l'auth — ces callbacks sont un filet de secours.
*/
params ["_name", "_function", ["_data", ""]];
if (_name != "comspec") exitWith {};

if (!(_function isEqualType "")) then { _function = str _function; };
if (!(_data isEqualType "")) then { _data = str _data; };

switch (_function) do {
    case "Connected": {
        // N'ecrase pas un echec auth deja constate (race async ancienne DLL).
        private _keyLen = count (missionNamespace getVariable ["comspec_overwatch_api_key", ""]);
        if (_keyLen < 1) exitWith {};
        private _uri = _data;
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        private _label = [_uri] call comspec_overwatch_connect_fnc_portalLabel;
        [format ["[Athena] Connecte a %1", _label], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        // Pas de bandeau / chat pendant le handshake au démarrage (1 message max ailleurs)
        if (
            !(missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false])
            && {[] call comspec_overwatch_connect_fnc_shouldShowScreenNotification}
        ) then {
            systemChat format ["[Athena] Connecte a %1", _label];
            ["COMSPEC_Info", [format ["Connecte a %1", _label]]] call comspec_overwatch_connect_fnc_showNotification;
        };
        [] call comspec_overwatch_connect_fnc_updateLinkDiary;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "Error": {
        private _msg = if (!(_data isEqualTo "")) then { _data } else { "Echec de liaison" };
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", _msg, false];
        ["WARN", "Athena", format ["Extension Error: %1", _msg]] call comspec_overwatch_connect_fnc_log;
        [format ["[Athena] %1", _msg], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
            systemChat format ["[Athena] %1", _msg];
        };
        ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "RateLimited": {
        // Backoff exponentiel côté SQF (la DLL applique aussi un délai d’envoi).
        private _prev = missionNamespace getVariable ["COMSPEC_ApiBackoffSec", 2];
        if (!(_prev isEqualType 0)) then { _prev = 2; };
        private _next = (_prev * 2) min 60;
        missionNamespace setVariable ["COMSPEC_ApiBackoffSec", _next, false];
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", diag_tickTime + _next, false];
        private _msg = if (!(_data isEqualTo "")) then { _data } else {
            "Athena est saturé — synchronisation ralentie quelques instants."
        };
        [format ["[Athena] %1 (pause %2 s)", _msg, round _next], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        if ([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) then {
            systemChat format ["[Athena] Synchronisation ralentie (%1 s).", round _next];
        };
    };
    case "RateLimitClear": {
        missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
        missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
    };
    case "Debug": {
        if (!(_data isEqualTo "")) then {
            ["DEBUG", "Ext", _data] call comspec_overwatch_connect_fnc_log;
            [format ["[Debug] %1", _data], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    };
    default {};
};
