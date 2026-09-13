/*
    Envoie le texte composé sur le canal Messagerie actif.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Comms_group", controlNull];
if (isNull _group) exitWith {};

private _edit = _group controlsGroupCtrl 9924;
if (isNull _edit) exitWith {};

private _msg = trim (ctrlText _edit);
if (_msg isEqualTo "") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Saisissez un message avant d’envoyer.", 3] call cTab_fnc_addNotification;
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
        ["MSG", "Liaison au poste requise pour envoyer. Ouvrez Connexion Athena → Entrer.", 5] call cTab_fnc_addNotification;
    };
    if (!isNil "comspec_overwatch_connect_fnc_announce") then {
        ["Liaison au poste requise pour envoyer un message.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
};

if (isNil "comspec_overwatch_connect_fnc_tabletChatSend") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Envoi indisponible pour le moment.", 4] call cTab_fnc_addNotification;
    };
};

[_msg] call comspec_overwatch_connect_fnc_tabletChatSend;
_edit ctrlSetText "";
[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
