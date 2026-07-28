/*
    Bridge JS → SQF du panneau "Gestion du mod" (alert() intercepté via JSDialog).
    Protocole : "COMSPEC_PM|<commande>"
      close
      refresh
      toggle:enabled | toggle:aceMenus | toggle:quiet | toggle:milsim
      tool:forcesync | tool:syncmarkers | tool:replaynda | tool:reinitace
*/
params ["_ctrl", "_isConfirmDialog", "_message"];

private _msg = _message;
if (!(_msg isEqualType "")) then { _msg = str _msg; };

if ((_msg select [0, 11]) != "COMSPEC_PM|") exitWith {
    true
};

private _cmd = _msg select [11, (count _msg) - 11];

private _fnc_refresh = {
    if (!isNull _ctrl) then {
        [_ctrl] call comspec_overwatch_connect_fnc_pauseManagerPageLoaded;
    };
};

private _fnc_setBool = {
    params ["_name", "_val"];
    missionNamespace setVariable [_name, _val];
    if (!isNil "cba_settings_fnc_set") then {
        [_name, _val, 2, "client"] call cba_settings_fnc_set;
    };
};

switch (true) do {
    case (_cmd isEqualTo "close"): {
        private _disp = findDisplay 9979;
        if (!isNull _disp) then { _disp closeDisplay 1; } else { closeDialog 0; };
    };
    case (_cmd isEqualTo "refresh"): {
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "toggle:enabled"): {
        private _cur = missionNamespace getVariable ["comspec_overwatch_enabled", true];
        ["comspec_overwatch_enabled", !_cur] call _fnc_setBool;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "toggle:aceMenus"): {
        private _cur = missionNamespace getVariable ["comspec_overwatch_ace_menus", false];
        ["comspec_overwatch_ace_menus", !_cur] call _fnc_setBool;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "toggle:quiet"): {
        private _cur = missionNamespace getVariable ["comspec_overwatch_quiet_mode", false];
        ["comspec_overwatch_quiet_mode", !_cur] call _fnc_setBool;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "toggle:milsim"): {
        private _cur = missionNamespace getVariable ["comspec_overwatch_milsim_ui", false];
        ["comspec_overwatch_milsim_ui", !_cur] call _fnc_setBool;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "tool:forcesync"): {
        [] call comspec_overwatch_connect_fnc_forceSyncData;
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "tool:syncmarkers"): {
        if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
            [true] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
        } else {
            ["COMSPEC_Warning", ["Module marqueurs indisponible — redémarrez avec le mod à jour."]] call comspec_overwatch_connect_fnc_showNotification;
        };
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "tool:replaynda"): {
        [] call comspec_overwatch_connect_fnc_resetBetaNdaAck;
    };
    case (_cmd isEqualTo "tool:reinitace"): {
        missionNamespace setVariable ["COMSPEC_ACEMenuReady", false, false];
        if (missionNamespace getVariable ["comspec_overwatch_ace_menus", false]) then {
            [] call comspec_overwatch_connect_fnc_initACE;
            ["COMSPEC_Info", ["Menus ACE Overwatch réinitialisés."]] call comspec_overwatch_connect_fnc_showNotification;
        } else {
            ["COMSPEC_Warning", ["Activez d'abord « Menus ACE » ci-dessus."]] call comspec_overwatch_connect_fnc_showNotification;
        };
        call _fnc_refresh;
    };
    default {};
};

true
