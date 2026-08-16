/*
    Bridge JS → SQF du panneau "Gestion du mod" (alert() intercepté via JSDialog).
    Protocole : "COMSPEC_PM|<commande>"
      close
      refresh
      toggle:enabled | toggle:aceMenus | toggle:quiet | toggle:milsim
      tool:forcesync | tool:syncmarkers | tool:replaynda | tool:reinitace
      tool:reconnect | tool:bugreport
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
        try {
            [_name, _val, true, "client"] call cba_settings_fnc_set;
        } catch {
            // Réglage mémoire déjà appliqué ; CBA peut échouer selon la source.
        };
    };
};

private _fnc_linkOk = {
    (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked"
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
        if (!_cur) then {
            // Réactivation → relancer la liaison
            [] spawn {
                uiSleep 0.2;
                [] call comspec_overwatch_connect_fnc_connect;
            };
        };
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "toggle:aceMenus"): {
        private _cur = missionNamespace getVariable ["comspec_overwatch_ace_menus", false];
        ["comspec_overwatch_ace_menus", !_cur] call _fnc_setBool;
        if (!_cur) then {
            missionNamespace setVariable ["COMSPEC_ACEMenuReady", false, false];
            [] call comspec_overwatch_connect_fnc_initACE;
        };
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
    case (_cmd isEqualTo "tool:reconnect"): {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
            ["COMSPEC_Warning", ["Activez d’abord « Overwatch actif »."]] call comspec_overwatch_connect_fnc_showNotification;
            call _fnc_refresh;
        };
        ["COMSPEC_Info", ["Reconnexion Athena en cours…"]] call comspec_overwatch_connect_fnc_showNotification;
        missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "Reconnexion manuelle…", false];
        call _fnc_refresh;
        [] spawn {
            [] call comspec_overwatch_connect_fnc_connect;
            private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
            if (_state isEqualTo "linked") then {
                ["COMSPEC_Info", ["Liaison Athena rétablie."]] call comspec_overwatch_connect_fnc_showNotification;
            } else {
                private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
                if (_detail isEqualTo "") then { _detail = "Toujours hors ligne — vérifiez le lien de compte."; };
                ["COMSPEC_Warning", [_detail]] call comspec_overwatch_connect_fnc_showNotification;
            };
            private _disp = findDisplay 9979;
            if (!isNull _disp) then {
                private _browser = _disp displayCtrl 9601;
                if (!isNull _browser) then {
                    [_browser] call comspec_overwatch_connect_fnc_pauseManagerPageLoaded;
                };
            };
        };
    };
    case (_cmd isEqualTo "tool:bugreport"): {
        [] call comspec_overwatch_connect_fnc_bugReportShow;
    };
    case (_cmd isEqualTo "tool:forcesync"): {
        if (!(call _fnc_linkOk)) then {
            ["COMSPEC_Warning", ["Liaison hors ligne — utilisez d’abord « Reconnecter Athena »."]] call comspec_overwatch_connect_fnc_showNotification;
        } else {
            [] call comspec_overwatch_connect_fnc_forceSyncData;
        };
        call _fnc_refresh;
    };
    case (_cmd isEqualTo "tool:syncmarkers"): {
        if (!(call _fnc_linkOk)) then {
            ["COMSPEC_Warning", ["Liaison hors ligne — reconnectez Athena avant d’envoyer les marqueurs."]] call comspec_overwatch_connect_fnc_showNotification;
        } else {
            if (!isNil "comspec_overwatch_connect_fnc_forceSyncMapMarkers") then {
                [true] call comspec_overwatch_connect_fnc_forceSyncMapMarkers;
            } else {
                ["COMSPEC_Warning", ["Module marqueurs indisponible — redémarrez avec le mod à jour."]] call comspec_overwatch_connect_fnc_showNotification;
            };
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
