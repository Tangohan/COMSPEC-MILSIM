/*
    Volée un instantané diagnostic dans le RPT (environnement + dernières lignes).
    Params optionnels: [_reason] — pourquoi on dump (Esc, commande, erreur…).
*/
params [["_reason", "manuel", [""]]];

["INFO", "Diag", format ["=== Dump journal (%1) ===", _reason]] call comspec_overwatch_connect_fnc_log;

private _ext = if (!isNil "comspec_overwatch_connect_fnc_extensionStatus") then {
    [] call comspec_overwatch_connect_fnc_extensionStatus
} else {
    [false, "n/a", -1]
};
_ext params [["_extOk", false], ["_extCode", ""], ["_ping", -1]];

private _snapshot = [
    format ["productVersion=%1", productVersion],
    format ["mission=%1", missionName],
    format ["clientState=%1", getClientStateNumber],
    format ["hasInterface=%1", hasInterface],
    format ["playerNull=%1", isNull player],
    format ["overwatch_enabled=%1", missionNamespace getVariable ["comspec_overwatch_enabled", true]],
    format ["ace_menus=%1", missionNamespace getVariable ["comspec_overwatch_ace_menus", false]],
    format ["log_level=%1", missionNamespace getVariable ["comspec_overwatch_log_level", 3]],
    format ["linkState=%1", missionNamespace getVariable ["COMSPEC_LinkState", "?"]],
    format ["athenaReady=%1", missionNamespace getVariable ["COMSPEC_AthenaReady", false]],
    format ["extOk=%1 code=%2 ping=%3", _extOk, _extCode, _ping],
    format ["mavic_setting_enableConnectionDistance isNil=%1 val=%2", isNil "mavic_setting_enableConnectionDistance", missionNamespace getVariable ["mavic_setting_enableConnectionDistance", "NIL"]],
    format ["mavic_setting_maxConnectionDistance isNil=%1 val=%2", isNil "mavic_setting_maxConnectionDistance", missionNamespace getVariable ["mavic_setting_maxConnectionDistance", "NIL"]],
    format ["zen_attributes_fnc_addAttribute isNil=%1", isNil "zen_attributes_fnc_addAttribute"],
    format ["displayEsc=%1", !isNull findDisplay 49],
    format ["pauseManager=%1", !isNull findDisplay 9979]
];

{
    ["INFO", "Diag", _x] call comspec_overwatch_connect_fnc_log;
} forEach _snapshot;

private _buf = missionNamespace getVariable ["COMSPEC_DiagLog", []];
["INFO", "Diag", format ["Tampon: %1 ligne(s)", count _buf]] call comspec_overwatch_connect_fnc_log;
{
    diag_log format ["[COMSPEC Overwatch][BUFFER] %1", _x];
} forEach _buf;

["INFO", "Diag", "=== Fin dump ==="] call comspec_overwatch_connect_fnc_log;
true
