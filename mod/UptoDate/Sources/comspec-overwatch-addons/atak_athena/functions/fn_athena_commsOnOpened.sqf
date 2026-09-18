/*
    Ouverture de l’app Messagerie (canaux radio Athena) dans cTab.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

private _prev = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", _group];
["comms"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _fresh = _interfaceInit || {isNull _prev} || {_prev isNotEqualTo _group};
if (_fresh) then {
    uiNamespace setVariable ["COMSPEC_ATAK_Comms_renderSig", ""];
    uiNamespace setVariable ["COMSPEC_ATAK_Comms_listSig", ""];
    missionNamespace setVariable ["COMSPEC_Comms_View", "list", false];
};

private _token = diag_tickTime + random 1;
uiNamespace setVariable ["COMSPEC_ATAK_Comms_token", _token];

if (isNil { missionNamespace getVariable "COMSPEC_Comms_Channel" }) then {
    missionNamespace setVariable ["COMSPEC_Comms_Channel", "general", false];
};
if (isNil { missionNamespace getVariable "COMSPEC_Comms_Unread" }) then {
    missionNamespace setVariable ["COMSPEC_Comms_Unread", createHashMap, false];
};

[] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;

if (!isNil "comspec_overwatch_connect_fnc_pollChatChannels") then {
    [] spawn {
        if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
            [] call comspec_overwatch_connect_fnc_pollChatChannels;
        };
        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {};
        [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
        [] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
    };
};

if (!isNil "comspec_overwatch_connect_fnc_pollChatMessages") then {
    [] spawn {
        if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
            [] call comspec_overwatch_connect_fnc_pollChatMessages;
        };
        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {};
        [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
        [] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
[] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;

// Le calage IceMan réaffiche tous les enfants : recoller liste vs fil après l’anim.
{
    [{
        if !([] call comspec_overwatch_atak_athena_fnc_athena_commsIsOpen) exitWith {};
        [] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
    }, [], _x] call CBA_fnc_waitAndExecute;
} forEach [0.2, 0.55, 1.1];

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token } do {
        uiSleep 4;
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isNotEqualTo _token) exitWith {};

        private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
        if (isNull _display) then {
            _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
        };
        if (isNull _display) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", controlNull];
            };
        };

        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", controlNull];
            };
        };

        if !([] call comspec_overwatch_atak_athena_fnc_athena_commsIsOpen) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", controlNull];
            };
        };

        if (!isNil "comspec_overwatch_connect_fnc_pollChatMessages") then {
            if (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) then {
                [] call comspec_overwatch_connect_fnc_pollChatMessages;
            };
        };
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isNotEqualTo _token) exitWith {};
        if (isNull _group || {!ctrlShown _group}) exitWith {};
        [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
        [] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
    };
};
