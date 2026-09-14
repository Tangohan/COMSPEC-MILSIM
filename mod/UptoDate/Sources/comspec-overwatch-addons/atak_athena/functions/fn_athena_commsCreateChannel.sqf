/*
    Crée un canal radio personnalisé depuis Messagerie (libellé saisi).
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (isNull _group) exitWith {};

private _edit = _group controlsGroupCtrl 9927;
if (isNull _edit) exitWith {};

private _label = trim (ctrlText _edit);
if (_label isEqualTo "") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Indiquez le nom du nouveau canal.", 3] call cTab_fnc_addNotification;
    };
};

private _canTx = false;
if (!isNil "comspec_overwatch_connect_fnc_canStartSync") then {
    _canTx = [] call comspec_overwatch_connect_fnc_canStartSync;
} else {
    _canTx = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
};
if (!_canTx) exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Liaison au poste requise pour créer un canal. Ouvrez Connexion Athena → Entrer.", 5] call cTab_fnc_addNotification;
    };
};

if (isNil "comspec_overwatch_connect_fnc_createChatChannel") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Création de canal indisponible pour le moment.", 4] call cTab_fnc_addNotification;
    };
};

private _ok = [_label] call comspec_overwatch_connect_fnc_createChatChannel;
if (!_ok) exitWith {};

_edit ctrlSetText "";

missionNamespace setVariable ["COMSPEC_Comms_View", "list", false];

if (!isNil "comspec_overwatch_connect_fnc_pollChatChannels") then {
    [] call comspec_overwatch_connect_fnc_pollChatChannels;
};
[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
