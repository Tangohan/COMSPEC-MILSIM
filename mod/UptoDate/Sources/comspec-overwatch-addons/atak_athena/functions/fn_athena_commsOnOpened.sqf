/*
    Ouverture de l’app Messagerie (canaux radio Athena) dans cTab.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", _group];
["comms"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _token = diag_tickTime + random 1;
uiNamespace setVariable ["COMSPEC_ATAK_Comms_token", _token];

if (isNil { missionNamespace getVariable "COMSPEC_Comms_Channel" }) then {
    missionNamespace setVariable ["COMSPEC_Comms_Channel", "general", false];
};

if (!isNil "comspec_overwatch_connect_fnc_pollChatChannels") then {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_pollChatChannels;
        [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
    };
};

if (!isNil "comspec_overwatch_connect_fnc_pollChatMessages") then {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_pollChatMessages;
        [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token } do {
        uiSleep 4;
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isNotEqualTo _token) exitWith {};

        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", controlNull];
            };
        };

        private _page = toLower ((["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""]);
        if !(_page in ["atakcomms", "comspec_atak_comms", "atak_comms", "comms", "messagerie"]) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Comms_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Comms_group", controlNull];
            };
        };

        if (!isNil "comspec_overwatch_connect_fnc_pollChatMessages") then {
            [] call comspec_overwatch_connect_fnc_pollChatMessages;
        };
        [] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
    };
};
