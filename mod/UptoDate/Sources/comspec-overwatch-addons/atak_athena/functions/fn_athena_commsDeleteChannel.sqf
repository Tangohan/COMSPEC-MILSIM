/*
    Supprime le canal Messagerie sélectionné (personnalisé uniquement).
*/
if (!hasInterface) exitWith {};

private _channel = toLower (trim (missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"]));
if (_channel in ["", "squad", "global"]) then { _channel = "general"; };
if (_channel in ["hq", "c2", "command"]) then { _channel = "commandement"; };
if (_channel in ["group"]) then { _channel = "groupe"; };

if (_channel in ["groupe", "commandement", "general", "jtac", "air"]) exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Les canaux système ne peuvent pas être supprimés.", 4] call cTab_fnc_addNotification;
    };
};

if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Liaison Athena requise pour supprimer un canal.", 4] call cTab_fnc_addNotification;
    };
};

if (isNil "comspec_overwatch_connect_fnc_deleteChatChannel") exitWith {
    if (!isNil "cTab_fnc_addNotification") then {
        ["MSG", "Suppression de canal indisponible pour le moment.", 4] call cTab_fnc_addNotification;
    };
};

private _ok = [_channel] call comspec_overwatch_connect_fnc_deleteChatChannel;
if (!_ok) exitWith {};

[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
