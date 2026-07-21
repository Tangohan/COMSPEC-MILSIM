/*
    Callbacks async de COMSPECExtension (RVExtensionRegisterCallback).
    name = "comspec", function = Connected | Error | Debug
*/
params ["_name", "_function", "_data"];
if (_name != "comspec") exitWith {};

switch (_function) do {
    case "Connected": {
        private _uri = if (_data isEqualType "") then { _data } else { str _data };
        missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
        missionNamespace setVariable ["COMSPEC_LastHealthOk", diag_tickTime, false];
        private _label = [_uri] call comspec_overwatch_connect_fnc_portalLabel;
        [format ["[Athena] Connecté à %1", _label], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        systemChat format ["[Athena] Connecté à %1", _label];
        ["COMSPEC_Info", [format ["Connecté à %1", _label]]] call BIS_fnc_showNotification;
        [] call comspec_overwatch_connect_fnc_updateLinkDiary;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "Error": {
        private _msg = if (_data isEqualType "" && {!(_data isEqualTo "")}) then { _data } else { "Échec de liaison" };
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", _msg, false];
        [format ["[Athena] %1", _msg], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        systemChat format ["[Athena] %1", _msg];
        ["COMSPEC_Warning", [_msg]] call BIS_fnc_showNotification;
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    };
    case "Debug": {
        if (!(_data isEqualTo "")) then {
            [format ["[Debug] %1", _data], "system"] call comspec_overwatch_connect_fnc_appendLinkLog;
        };
    };
    default {};
};
