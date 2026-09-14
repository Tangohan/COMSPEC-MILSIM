/*
    Retour à la liste des canaux depuis un fil.
*/
if (!hasInterface) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Comms_ignoreSelUntil", diag_tickTime + 0.5];
uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuilding", true];
uiNamespace setVariable ["COMSPEC_ATAK_Comms_listSig", ""];
uiNamespace setVariable ["COMSPEC_ATAK_Comms_renderSig", ""];
missionNamespace setVariable ["COMSPEC_Comms_View", "list", false];

[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
[] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;

private _token = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuildToken", _token];
[_token] spawn {
    params ["_token"];
    uiSleep 0.2;
    if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_rebuildToken", -1]) isNotEqualTo _token) exitWith {};
    private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
    if (!isNull _group) then {
        private _lb = _group controlsGroupCtrl 9922;
        if (!isNull _lb) then {
            _lb lbSetCurSel -1;
        };
    };
    uiSleep 0.15;
    if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_rebuildToken", -1]) isEqualTo _token) then {
        uiNamespace setVariable ["COMSPEC_ATAK_Comms_rebuilding", false];
    };
};
