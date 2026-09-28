/*
    Arrête la surveillance radio et restaure l’état ACRE d’origine si possible.
    - Mode channel : restore setCurrentRadioChannelNumber(prev)
    - Mode spectator : removeSpectatorRadio(radioId)
    Retourne true si une restauration a été tentée ou si rien n’était actif.
*/
if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_RadioMonitorActive", false])) exitWith { true };

private _mode = missionNamespace getVariable ["COMSPEC_RadioMonitorMode", ""];
private _radioId = missionNamespace getVariable ["COMSPEC_RadioMonitorRadioId", ""];
private _prevCh = missionNamespace getVariable ["COMSPEC_RadioMonitorPrevChannel", -1];
private _ok = false;

if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
    if (_mode isEqualTo "spectator" && {_radioId isEqualType ""} && {_radioId != ""}) then {
        if (!isNil "acre_api_fnc_removeSpectatorRadio") then {
            [_radioId] call acre_api_fnc_removeSpectatorRadio;
            _ok = true;
        };
    };
    if (_mode isEqualTo "channel" && {_prevCh isEqualType 0} && {_prevCh >= 0}) then {
        if (!isNil "acre_api_fnc_setCurrentRadioChannelNumber") then {
            [_prevCh] call acre_api_fnc_setCurrentRadioChannelNumber;
            _ok = true;
        };
    };
};

missionNamespace setVariable ["COMSPEC_RadioMonitorActive", false, false];
missionNamespace setVariable ["COMSPEC_RadioMonitorChannel", "", false];
missionNamespace setVariable ["COMSPEC_RadioMonitorRadioId", "", false];
missionNamespace setVariable ["COMSPEC_RadioMonitorMode", "", false];
missionNamespace setVariable ["COMSPEC_RadioMonitorPrevChannel", -1, false];

if (_ok) then {
    ["COMSPEC_Info", ["Surveillance radio arrêtée — canal d’origine restauré"]] call comspec_overwatch_connect_fnc_showNotification;
} else {
    ["COMSPEC_Info", ["Surveillance radio arrêtée"]] call comspec_overwatch_connect_fnc_showNotification;
};

true
