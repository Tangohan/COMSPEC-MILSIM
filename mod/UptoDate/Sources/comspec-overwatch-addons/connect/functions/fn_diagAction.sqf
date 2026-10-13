/*
    Boutons de la fenêtre Dépannage liaison (display_diag_isolate.hpp).
      recheck | reconnect | link | enable | athena | copy | bug | quick | full | stop
*/
params [["_what", "", [""]]];
if (!hasInterface) exitWith {};

private _refresh = {
    private _d = uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull];
    if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_diagIsolateOnLoad; };
};
private _say = {
    params ["_kind", "_txt"];
    [["COMSPEC_Info", "COMSPEC_Warning"] select (_kind isEqualTo "warn"), [_txt call comspec_overwatch_connect_fnc_tr]] call comspec_overwatch_connect_fnc_showNotification;
};

["INFO", "Diag", format ["Dépannage liaison : action %1", _what]] call comspec_overwatch_connect_fnc_log;

switch (_what) do {
    case "recheck": {
        missionNamespace setVariable ["COMSPEC_ExtVersionCached", "", false];
        if (missionNamespace getVariable ["comspec_overwatch_enabled", true]) then {
            [] spawn {
                [] call comspec_overwatch_connect_fnc_refreshLinkStatus;
                private _d = uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull];
                if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_diagIsolateOnLoad; };
            };
        };
        call _refresh;
    };
    case "reconnect": {
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
            ["warn", "Rallumez d'abord Overwatch."] call _say;
        };
        ["info", "Reconnexion Athena en cours…"] call _say;
        missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "Reconnexion manuelle…", false];
        call _refresh;
        [] spawn {
            [] call comspec_overwatch_connect_fnc_connect;
            private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
            if (_state isEqualTo "linked") then {
                ["COMSPEC_Info", ["Liaison Athena rétablie." call comspec_overwatch_connect_fnc_tr]] call comspec_overwatch_connect_fnc_showNotification;
            } else {
                private _detail = missionNamespace getVariable ["COMSPEC_LinkDetail", ""];
                if (_detail isEqualTo "") then { _detail = "Toujours hors ligne : vérifiez le lien de compte."; };
                ["COMSPEC_Warning", [_detail call comspec_overwatch_connect_fnc_tr]] call comspec_overwatch_connect_fnc_showNotification;
            };
            private _d = uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull];
            if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_diagIsolateOnLoad; };
        };
    };
    case "link": {
        closeDialog 0;
        [] spawn { uiSleep 0.2; [] call comspec_overwatch_connect_fnc_accountLinkShow; };
    };
    case "enable": {
        missionNamespace setVariable ["comspec_overwatch_enabled", true, false];
        ["info", "Overwatch rallumé : la liaison redémarre."] call _say;
        [] spawn {
            [] call comspec_overwatch_connect_fnc_connect;
            private _d = uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull];
            if (!isNull _d) then { [_d] call comspec_overwatch_connect_fnc_diagIsolateOnLoad; };
        };
        call _refresh;
    };
    case "athena": {
        closeDialog 0;
        [] spawn { uiSleep 0.2; [true] call comspec_overwatch_connect_fnc_athenaChoiceShow; };
    };
    case "copy": {
        // Rapport en texte brut (balises retirées) : à coller sur Discord ou dans un signalement.
        private _last = uiNamespace getVariable ["COMSPEC_DiagLastChecks", []];
        if ((count _last) < 2) then { _last = ([] call comspec_overwatch_connect_fnc_diagChecks) select [0, 2]; };
        _last params ["_rows", "_verdict"];
        private _lines = [
            format ["COMSPEC — Dépannage liaison — %1", _verdict select 1],
            format ["ATAK %1 · Link attendu %2 · Overwatch connect %3 · Arma %4",
                missionNamespace getVariable ["COMSPEC_ATAK_CodeVersion", "-"],
                missionNamespace getVariable ["COMSPEC_ATAK_LinkExpected", "-"],
                getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr"),
                format ["%1.%2", productVersion select 2, productVersion select 3]],
            format ["Serveur : %1 · mission : %2", [serverName, "solo"] select (!isMultiplayer), briefingName],
            ""
        ];
        {
            _x params ["_lvl", "_title", "_value", "_hint"];
            _lines pushBack format ["[%1] %2 : %3", toUpper _lvl, _title, _value];
            if (_hint isNotEqualTo "") then { _lines pushBack ("    " + _hint); };
        } forEach _rows;
        if (!isNil "comspec_overwatch_connect_fnc_diagStatusSnapshot") then {
            private _snap = [] call comspec_overwatch_connect_fnc_diagStatusSnapshot;
            if (_snap isEqualType createHashMap) then { _lines pushBack ""; _lines append (_snap getOrDefault ["lines", []]); };
        };
        copyToClipboard (_lines joinString endl);
        ["info", "Rapport copié : collez-le dans votre signalement ou sur Discord."] call _say;
    };
    case "bug": {
        closeDialog 0;
        [] spawn { uiSleep 0.2; [] call comspec_overwatch_connect_fnc_bugReportShow; };
    };
    case "quick": {
        closeDialog 0;
        [] spawn { uiSleep 0.2; [20] call comspec_overwatch_connect_fnc_diagIsolateStart; };
    };
    case "full": {
        closeDialog 0;
        [] spawn { uiSleep 0.2; [55] call comspec_overwatch_connect_fnc_diagIsolateStart; };
    };
    case "stop": {
        [] call comspec_overwatch_connect_fnc_diagIsolateStop;
        call _refresh;
    };
    default {};
};
